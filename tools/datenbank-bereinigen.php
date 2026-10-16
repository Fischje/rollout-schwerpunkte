<?php
declare(strict_types=1);

/*
 * Einmalige Bereinigung der Datenbank auf Schema-Version 24 (ab Rollout-Schwerpunkte 0.1.71).
 *
 * Aufruf im Anwendungsordner, während niemand die Anwendung nutzt:
 *   php tools/datenbank-bereinigen.php [Pfad/zur/rollout.sqlite]
 * Ohne Pfad gilt db_path aus config.php, sonst data/rollout.sqlite.
 *
 * Ablauf: Vorbedingungen prüfen → Sicherungskopie → neue Datenbank nach src/schema.sql aufbauen →
 * alle Daten übernehmen → Altlasten entfernen und prüfen → Datei austauschen. Bricht etwas ab,
 * bleibt die bisherige Datenbank unverändert.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const TARGET_VERSION = 24;
const SOURCE_VERSION = 23;

$root = dirname(__DIR__);

function say(string $text = ''): void { fwrite(STDOUT, $text . PHP_EOL); }
function fail(string $text, int $code = 1): never { fwrite(STDERR, PHP_EOL . 'ABBRUCH: ' . $text . PHP_EOL . 'Die Datenbank wurde nicht verändert.' . PHP_EOL); exit($code); }
function open_db(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout = 10000');
    return $pdo;
}
function tables(PDO $db, string $schema = 'main'): array
{
    return $db->query("SELECT name FROM $schema.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
}
function columns(PDO $db, string $table, string $schema = 'main'): array
{
    return array_column($db->query("PRAGMA $schema.table_info(\"$table\")")->fetchAll(), 'name');
}
function count_rows(PDO $db, string $table, string $schema = 'main'): int
{
    return (int)$db->query("SELECT COUNT(*) FROM $schema.\"$table\"")->fetchColumn();
}

// --- Datenbankdatei bestimmen
$path = $argv[1] ?? null;
if ($path === null) {
    $configFile = $root . '/config.php';
    $config = is_file($configFile) ? require $configFile : [];
    $path = (string)($config['db_path'] ?? ($root . '/data/rollout.sqlite'));
}
$path = realpath($path) ?: $path;
if (!is_file($path)) fail('Die Datenbank „' . $path . '“ wurde nicht gefunden. Bitte den Pfad als Parameter angeben.');
$schemaFile = $root . '/src/schema.sql';
$schemaSql = file_get_contents($schemaFile);
if ($schemaSql === false) fail('src/schema.sql konnte nicht gelesen werden.');

say('Datenbank: ' . $path);
$old = open_db($path);
$version = (int)$old->query('PRAGMA user_version')->fetchColumn();
if ($version === TARGET_VERSION) {
    say('Die Datenbank ist bereits bereinigt (Schema-Version ' . TARGET_VERSION . '). Es ist nichts zu tun.');
    exit(0);
}
if ($version !== SOURCE_VERSION) fail('Die Datenbank hat Schema-Version ' . $version . '. Das Skript erwartet Version ' . SOURCE_VERSION . ' (Stand bis 0.1.70).');
if ((string)$old->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') fail('Die Integritätsprüfung der bisherigen Datenbank ist fehlgeschlagen.');

// --- Vorbedingungen: Dinge, die nur ein Mensch entscheiden kann
$oldTables = tables($old);
$problems = [];
if (in_array('catalog_service_suggestions', $oldTables, true)) {
    $pending = $old->query("SELECT s.proposed_name, s.provider_type, p.name project_name FROM catalog_service_suggestions s LEFT JOIN projects p ON p.id=s.project_id WHERE s.status='pending' ORDER BY s.proposed_name")->fetchAll();
    if ($pending) {
        $problems[] = 'Offene Katalogvorschläge aus früheren Importen (' . count($pending) . '):';
        foreach ($pending as $row) $problems[] = '  - ' . $row['proposed_name'] . ' (' . $row['provider_type'] . ', Projekt ' . ($row['project_name'] ?? '?') . ')';
        $problems[] = '  → Im Standard-Leistungskatalog übernehmen oder ablehnen.';
    }
}
$multi = $old->query("SELECT s.name, GROUP_CONCAT(t.provider_type, ', ') targets FROM support_services s JOIN support_service_provider_targets t ON t.support_service_id=s.id GROUP BY s.id HAVING COUNT(*)>1 ORDER BY s.name")->fetchAll();
if ($multi) {
    $problems[] = 'Leistungen, die noch mehreren Katalogen zugeordnet sind (' . count($multi) . '):';
    foreach ($multi as $row) $problems[] = '  - ' . $row['name'] . ' (' . $row['targets'] . ')';
    $problems[] = '  → Im Standard-Leistungskatalog über „Jetzt aufräumen“ trennen.';
}
if ($problems) {
    say();
    say('Vor der Bereinigung ist noch etwas in der Anwendung zu erledigen:');
    foreach ($problems as $line) say($line);
    say();
    say('Diese Seiten gibt es nur bis Version 0.1.70. Bitte vorübergehend 0.1.70 einspielen, die Punkte erledigen,');
    say('dann wieder die aktuelle Version einspielen und dieses Skript erneut ausführen.');
    fail('Vorbedingungen nicht erfüllt.', 2);
}

// --- Sicherungskopie (vollständig, auch mit noch nicht übertragenem WAL-Inhalt)
$old->exec('PRAGMA wal_checkpoint(TRUNCATE)');
$backup = $path . '.vor-bereinigung-' . date('Ymd-His');
$old->exec('VACUUM INTO ' . $old->quote($backup));
if (!is_file($backup) || filesize($backup) === 0) fail('Die Sicherungskopie konnte nicht angelegt werden.');
say('Sicherung:  ' . $backup);
$old = null;

// --- Neue Datenbank nach dem aktuellen Schema aufbauen und Daten übernehmen
$newPath = $path . '.neu';
foreach ([$newPath, $newPath . '-wal', $newPath . '-shm', $newPath . '-journal'] as $file) if (is_file($file)) unlink($file);
$new = open_db($newPath);
$new->exec('PRAGMA journal_mode = DELETE');
$new->exec($schemaSql);
$new->exec('PRAGMA foreign_keys = OFF');
$new->exec('ATTACH DATABASE ' . $new->quote($backup) . ' AS alt');

// Reihenfolge nach Abhängigkeiten: Die Prüfregeln (Trigger) der Projektmatrix brauchen Leistungserbringer und Katalogzuordnungen
$order = ['users', 'strategic_fields', 'projects', 'rollout_objects', 'providers', 'provider_rollout_contacts', 'app_settings', 'provider_class_levels', 'rollout_object_provider_classes', 'support_services', 'support_service_classes', 'support_service_provider_targets', 'project_services', 'project_service_rollout_objects', 'project_support_matrix', 'rollout_object_support_matrix', 'project_provider_contacts'];
$newTables = tables($new);
usort($newTables, static fn(string $a, string $b): int => (array_search($a, $order, true) === false ? PHP_INT_MAX : array_search($a, $order, true)) <=> (array_search($b, $order, true) === false ? PHP_INT_MAX : array_search($b, $order, true)) ?: strcmp($a, $b));
$oldTables = tables($new, 'alt');
$report = [];
$new->beginTransaction();
try {
    foreach ($newTables as $table) {
        $new->exec("DELETE FROM main.\"$table\"");
        if (!in_array($table, $oldTables, true)) continue;
        $target = columns($new, $table);
        $source = columns($new, $table, 'alt');
        $common = array_values(array_intersect($target, $source));
        $select = array_map(static fn(string $column): string => '"' . $column . '"', $common);
        // Erbringung zentral/regional aus dem früheren Kennzeichen „obligatorisch“, falls die Spalte noch fehlt
        if ($table === 'support_services' && !in_array('default_delivery', $source, true) && in_array('mandatory_for_all', $source, true)) {
            $common[] = 'default_delivery';
            $select[] = "CASE WHEN mandatory_for_all=1 THEN 'central' ELSE 'regional' END";
        }
        $list = implode(',', array_map(static fn(string $column): string => '"' . $column . '"', $common));
        $new->exec("INSERT INTO main.\"$table\"($list) SELECT " . implode(',', $select) . " FROM alt.\"$table\"");
        $dropped = array_values(array_diff($source, $target));
        if ($dropped) $report[] = 'Spalten entfernt in ' . $table . ': ' . implode(', ', $dropped);
    }

    // Rollout-Ansprechpartner aus der Zwischenlösung von 0.1.65 übernehmen, falls die Tabelle noch fehlte
    if (!in_array('provider_rollout_contacts', $oldTables, true) && in_array('rollout_contact_name', columns($new, 'providers', 'alt'), true)) {
        $count = $new->exec("INSERT INTO main.provider_rollout_contacts(provider_id,name,email,sort_order) SELECT id,TRIM(rollout_contact_name),TRIM(rollout_contact_email),1 FROM alt.providers WHERE TRIM(rollout_contact_name)<>'' OR TRIM(rollout_contact_email)<>''");
        if ($count) $report[] = $count . ' Rollout-Ansprechpartner aus den früheren Feldern übernommen';
    }

    // FI-Klasse, die nur noch in der früheren Spalte rollout_objects.technical_class stand
    if (in_array('technical_class', columns($new, 'rollout_objects', 'alt'), true)) {
        $count = $new->exec("INSERT OR IGNORE INTO main.rollout_object_provider_classes(rollout_object_id,provider_type,class_code)
            SELECT o.id,'FI',CAST(o.technical_class AS TEXT) FROM alt.rollout_objects o
            WHERE o.technical_class IS NOT NULL
              AND EXISTS (SELECT 1 FROM main.provider_class_levels l WHERE l.provider_type='FI' AND l.class_code=CAST(o.technical_class AS TEXT))");
        if ($count) $report[] = $count . ' FI-Klasse(n) aus der früheren Spalte übernommen';
    }

    // Feste Klasse 0 („keine Verbundpartnerleistung erforderlich“) muss in beiden Schemata vorhanden sein
    $insertZero = $new->prepare("INSERT OR IGNORE INTO main.provider_class_levels(provider_type,class_code,class_label,sort_order) VALUES(?,'0','0 – keine Verbundpartnerleistung erforderlich',0)");
    foreach (['FI', 'DSV'] as $type) $insertZero->execute([$type]);

    // Unverändert gespeicherte E-Mail-Vorschläge aus 0.1.67 bis 0.1.69 entfernen; dann gilt die aktuelle Vorgabe
    $outdatedSubjects = ['Abfrage Rolloutschwerpunkte {Jahr} – {Regionalverband}'];
    $outdatedBodies = [
        "Guten Tag {Ansprechpartner},\n\nanbei erhalten Sie die Excel-Datei zur Abfrage der Rolloutschwerpunkte {Jahr} für den {Regionalverband}.\n\nBitte ergänzen Sie die gelb markierten Felder – vor allem die Bereitstellungsart je Rolloutleistung – und senden Sie die Datei bis zum TT.MM.JJJJ an uns zurück.\n\nBei Fragen melden Sie sich gern.\n\nVielen Dank und viele Grüße",
        "Sehr geehrte Kolleginnen und Kollegen,\n\nanbei erhalten Sie die Excel-Datei mit den Unterstützungsleistungen für den {Regionalverband}. Wir bitten Sie, je Unterstützungsleistung anzugeben, wie Sie die Bereitstellung sicherstellen: selbst, über einen anderen Regionalverband, über einen externen Dienstleister oder gar nicht. Soweit relevant, ergänzen Sie bitte Termin bzw. Zeitraum, Bemerkungen und Ansprechpartner.\n\nBitte füllen Sie die gelb markierten Felder aus und senden Sie die Datei bis zum TT.MM.JJJJ an uns zurück.\n\nBei Fragen melden Sie sich gern.\n\nVielen Dank und viele Grüße",
        "Sehr geehrte Kolleginnen und Kollegen,\n\nanbei erhalten Sie die Excel-Datei mit den Unterstützungsleistungen für den {Regionalverband}. Wir bitten Sie, je Unterstützungsleistung anzugeben, wie Sie die Bereitstellung sicherstellen: selbst, über einen anderen Regionalverband, über einen externen Dienstleister oder gar nicht. Soweit relevant, ergänzen Sie bitte Termin bzw. Zeitraum, Bemerkungen und Ansprechpartner.\n\nZusätzlich zu den vorgegebenen Unterstützungsleistungen können Sie je Projekt eigene Unterstützungsleistungen ergänzen, die Sie für Ihre Sparkassen anbieten. Nutzen Sie dafür die Zeilen „Neue Rolloutleistung“ unter den vorgegebenen Leistungen des jeweiligen Projekts und geben Sie dort auch an, für welche Rolloutobjekte die Leistung gilt.\n\nBitte füllen Sie die gelb markierten Felder aus und senden Sie die Datei bis zum TT.MM.JJJJ an uns zurück.\n\nBei Fragen melden Sie sich gern.\n\nVielen Dank und viele Grüße",
    ];
    $deleteSetting = $new->prepare('DELETE FROM main.app_settings WHERE key=? AND value=?');
    $removedSettings = 0;
    foreach ($outdatedSubjects as $text) { $deleteSetting->execute(['rv_mail_subject', $text]); $removedSettings += $deleteSetting->rowCount(); }
    foreach ($outdatedBodies as $text) { $deleteSetting->execute(['rv_mail_body', $text]); $removedSettings += $deleteSetting->rowCount(); }
    if ($removedSettings) $report[] = $removedSettings . ' veraltete(r) E-Mail-Vorschlag/-Betreff entfernt (es gilt die aktuelle Vorgabe)';

    // Verwaiste Datensätze (Verweis auf nicht mehr vorhandene Einträge) entfernen
    for ($round = 0; $round < 5; $round++) {
        $orphans = $new->query('PRAGMA main.foreign_key_check')->fetchAll();
        if (!$orphans) break;
        $byTable = [];
        foreach ($orphans as $orphan) $byTable[$orphan['table']][] = (int)$orphan['rowid'];
        foreach ($byTable as $table => $rowIds) {
            $rowIds = array_values(array_unique($rowIds));
            $new->exec("DELETE FROM main.\"$table\" WHERE rowid IN (" . implode(',', $rowIds) . ')');
            $report[] = count($rowIds) . ' verwaiste(r) Datensatz/Datensätze in ' . $table . ' entfernt';
        }
    }
    if ($new->query('PRAGMA main.foreign_key_check')->fetchAll()) throw new RuntimeException('Die Fremdschlüsselprüfung ist nach dem Aufräumen weiterhin fehlgeschlagen.');
    $new->commit();
} catch (Throwable $error) {
    if ($new->inTransaction()) $new->rollBack();
    $new = null;
    @unlink($newPath);
    fail('Beim Übernehmen der Daten: ' . $error->getMessage());
}

// --- Bericht: entfernte Tabellen und Zeilenzahlen
foreach (array_diff($oldTables, $newTables) as $table) $report[] = 'Tabelle entfernt: ' . $table . ' (' . count_rows($new, $table, 'alt') . ' Zeilen)';
$counts = [];
foreach ($newTables as $table) {
    $before = in_array($table, $oldTables, true) ? count_rows($new, $table, 'alt') : 0;
    $after = count_rows($new, $table);
    $counts[] = sprintf('  %-34s %6d → %6d', $table, $before, $after);
}
$new->exec('DETACH DATABASE alt');
$new->exec('PRAGMA foreign_keys = ON');
$integrity = (string)$new->query('PRAGMA integrity_check')->fetchColumn();
$newVersion = (int)$new->query('PRAGMA user_version')->fetchColumn();
$new = null;
if ($integrity !== 'ok' || $newVersion !== TARGET_VERSION) {
    @unlink($newPath);
    fail('Die neue Datenbank hat die Abschlussprüfung nicht bestanden (Integrität: ' . $integrity . ', Version: ' . $newVersion . ').');
}

// --- Austausch: Rechte und Eigentümer der bisherigen Datei übernehmen, dann ersetzen
$stat = stat($path);
@chmod($newPath, $stat['mode'] & 0777);
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    @chown($newPath, $stat['uid']);
    @chgrp($newPath, $stat['gid']);
}
foreach ([$path . '-wal', $path . '-shm'] as $file) if (is_file($file)) unlink($file);
if (!rename($newPath, $path)) fail('Die neue Datenbank konnte nicht an ihren Platz verschoben werden. Sie liegt unter ' . $newPath . '.');

say();
say('Zeilen je Tabelle (vorher → nachher):');
foreach ($counts as $line) say($line);
say();
say($report ? 'Entfernte Altlasten und Übernahmen:' : 'Keine weiteren Altlasten gefunden.');
foreach ($report as $line) say('  - ' . $line);
say();
say('Fertig: Die Datenbank hat jetzt Schema-Version ' . TARGET_VERSION . '. Die Anwendung kann wieder genutzt werden.');
say('Die Sicherung kann gelöscht werden, sobald alles geprüft ist: ' . $backup);
