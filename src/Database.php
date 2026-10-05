<?php
declare(strict_types=1);

final class Database
{
    private const SCHEMA_VERSION = 23;

    public static function connect(string $path): PDO
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
        return $pdo;
    }

    public static function initialize(PDO $db, string $schemaPath): void
    {
        $hasApplicationTables = (int)$db->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('users','projects','support_services')"
        )->fetchColumn() > 0;

        if ($hasApplicationTables) {
            $version = (int)$db->query('PRAGMA user_version')->fetchColumn();
            if ($version !== self::SCHEMA_VERSION) {
                throw new RuntimeException(
                    'Die Datenbank hat Schema-Version ' . $version . '. '
                    . 'Bitte vor dem Start einmal „php tools/update-database-0.1.30.php“ ausführen.'
                );
            }
            self::ensureNoSupportLevels($db);
            self::ensureAlwaysIncludedColumn($db);
            self::ensureOpenSuggestionTable($db);
            self::ensureDeliveryLevels($db);
            self::ensureServiceNamesPerCatalog($db);
            self::ensureOriginColumns($db);
            return;
        }

        $schema = file_get_contents($schemaPath);
        if ($schema === false) throw new RuntimeException('Das Datenbankschema konnte nicht geladen werden.');
        $db->exec($schema);
        self::seed($db);
    }

    private static function seed(PDO $db): void
    {
        $count = (int)$db->query('SELECT COUNT(*) FROM providers')->fetchColumn();
        if ($count > 0) return;

        $insert = $db->prepare('INSERT INTO providers(name, type, sort_order) VALUES(?, ?, ?)');
        $insert->execute(['FI', 'FI', 1]);
        $insert->execute(['DSV', 'DSV', 2]);
        foreach (self::regionalAssociations() as $index => $association) {
            $insert->execute([$association['abbreviation'], 'RV', $index + 3]);
        }
        $insertClass=$db->prepare('INSERT INTO provider_class_levels(provider_type,class_code,class_label,sort_order) VALUES(?,?,?,?)');
        $insertClass->execute(['FI','0','0 – keine Verbundpartnerleistung erforderlich',0]);
        foreach(['1','2','3'] as $index=>$label)$insertClass->execute(['FI',$label,$label,$index+1]);
        $insertClass->execute(['DSV','0','0 – keine Verbundpartnerleistung erforderlich',0]);
        foreach(['1','2','3'] as $index=>$label)$insertClass->execute(['DSV','L'.($index+1),$label,$index+1]);
    }

    /** Seit 0.1.43: Flag „Immer enthalten“ an Leistungen; wird bei bestehenden Datenbanken einmalig ergänzt, das Schema bleibt Version 23. */
    /** Herkunftsvermerk: wer eine Angabe zuletzt inhaltlich geändert hat (Import, Rückmeldung, Projektmatrix). */
    private static function ensureOriginColumns(PDO $db): void
    {
        foreach (['project_service_rollout_objects', 'project_support_matrix', 'rollout_object_support_matrix'] as $table) {
            $columns = array_column($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
            if (!in_array('origin', $columns, true)) $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN origin TEXT');
            if (!in_array('origin_at', $columns, true)) $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN origin_at TEXT');
        }
    }

    private static function ensureAlwaysIncludedColumn(PDO $db): void
    {
        $columns = array_column($db->query('PRAGMA table_info(support_services)')->fetchAll(), 'name');
        if (!in_array('always_included', $columns, true)) {
            $db->exec('ALTER TABLE support_services ADD COLUMN always_included INTEGER NOT NULL DEFAULT 0 CHECK(always_included IN (0,1))');
        }
    }

    /**
     * Seit 0.1.47: Katalogvorschläge kommen von TPL/PL, Regionalverbänden, FI und DSV.
     * Bestehende Tabellen (nur FI/DSV, Rolloutobjekt Pflicht) werden einmalig umgebaut; vorhandene Vorschläge bleiben erhalten.
     */
    private static function ensureOpenSuggestionTable(PDO $db): void
    {
        $sql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='catalog_service_suggestions'")->fetchColumn();
        if ($sql === '' || str_contains($sql, 'source TEXT')) return;
        $db->exec('PRAGMA foreign_keys = OFF');
        $db->beginTransaction();
        try {
            $db->exec("CREATE TABLE catalog_service_suggestions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider_type TEXT NOT NULL CHECK(provider_type IN ('RV','FI','DSV')),
                source TEXT NOT NULL DEFAULT 'DSV' CHECK(source IN ('TPL','RV','FI','DSV')),
                provider_id INTEGER,
                project_id INTEGER NOT NULL,
                rollout_object_id INTEGER,
                proposed_name TEXT NOT NULL,
                class_code TEXT NOT NULL,
                offered INTEGER NOT NULL DEFAULT 0,
                schedule TEXT NOT NULL DEFAULT '',
                note TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','accepted','rejected')),
                accepted_service_id INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE,
                FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY(project_id, rollout_object_id) REFERENCES rollout_objects(project_id, id) ON DELETE CASCADE,
                FOREIGN KEY(accepted_service_id) REFERENCES support_services(id) ON DELETE SET NULL
            )");
            $db->exec("INSERT INTO catalog_service_suggestions_new(id,provider_type,source,provider_id,project_id,rollout_object_id,proposed_name,class_code,offered,schedule,note,status,accepted_service_id,created_at,updated_at)
                SELECT id,provider_type,provider_type,provider_id,project_id,rollout_object_id,proposed_name,class_code,offered,schedule,note,status,accepted_service_id,created_at,updated_at FROM catalog_service_suggestions");
            $db->exec('DROP TABLE catalog_service_suggestions');
            $db->exec('ALTER TABLE catalog_service_suggestions_new RENAME TO catalog_service_suggestions');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_catalog_suggestion_status ON catalog_service_suggestions(status,provider_type,created_at)');
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        } finally {
            $db->exec('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Seit 0.1.50: Erbringung zentral (Projekt/FI/DSV) oder regional (Regionalverbände) statt „obligatorisch“.
     * Katalogvorgabe je Leistung, Abweichung je Rolloutobjekt. Beim Umstieg wird „obligatorisch: Ja“ zu „zentral“.
     */
    private static function ensureDeliveryLevels(PDO $db): void
    {
        $serviceColumns = array_column($db->query('PRAGMA table_info(support_services)')->fetchAll(), 'name');
        if (!in_array('default_delivery', $serviceColumns, true)) {
            $db->exec("ALTER TABLE support_services ADD COLUMN default_delivery TEXT NOT NULL DEFAULT 'regional' CHECK(default_delivery IN ('regional','central'))");
            $db->exec("UPDATE support_services SET default_delivery='central' WHERE mandatory_for_all=1");
            $db->exec("UPDATE support_services SET mandatory_for_all=0");
            $db->exec("DELETE FROM project_support_matrix WHERE automatic=1 AND offered=1 AND TRIM(note)='' AND TRIM(schedule)='' AND TRIM(delivery_mode)='' AND TRIM(delivery_partner)=''");
            $db->exec("UPDATE project_support_matrix SET automatic=0");
        }
        $usageColumns = array_column($db->query('PRAGMA table_info(project_service_rollout_objects)')->fetchAll(), 'name');
        if (!in_array('delivery_level', $usageColumns, true)) {
            $db->exec("ALTER TABLE project_service_rollout_objects ADD COLUMN delivery_level TEXT CHECK(delivery_level IN ('regional','central') OR delivery_level IS NULL)");
        }
    }

    /**
     * Seit 0.1.51: Derselbe Leistungsname darf in den getrennten Katalogen (Bankfachlich, FI, DSV) je einmal vorkommen.
     * Die bisherige Eindeutigkeit des Namens wird einmalig entfernt; die Anwendung prüft Namen je Katalog.
     */
    private static function ensureServiceNamesPerCatalog(PDO $db): void
    {
        $sql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='support_services'")->fetchColumn();
        if ($sql === '' || !preg_match('/\bname\s+TEXT\s+NOT\s+NULL\s+UNIQUE\b/i', $sql)) return;
        $newSql = preg_replace('/\bname\s+TEXT\s+NOT\s+NULL\s+UNIQUE\b/i', 'name TEXT NOT NULL', $sql, 1);
        $newSql = preg_replace('/^CREATE TABLE\s+(IF NOT EXISTS\s+)?"?support_services"?/i', 'CREATE TABLE support_services_rebuild', (string)$newSql, 1);
        $indexes = $db->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='support_services' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $triggers = $db->query("SELECT name, sql FROM sqlite_master WHERE type='trigger' AND sql IS NOT NULL")->fetchAll();
        $db->exec('PRAGMA foreign_keys = OFF');
        $db->beginTransaction();
        try {
            foreach ($triggers as $trigger) $db->exec('DROP TRIGGER IF EXISTS "' . str_replace('"', '""', (string)$trigger['name']) . '"');
            $db->exec((string)$newSql);
            $db->exec('INSERT INTO support_services_rebuild SELECT * FROM support_services');
            $db->exec('DROP TABLE support_services');
            $db->exec('ALTER TABLE support_services_rebuild RENAME TO support_services');
            foreach ($indexes as $indexSql) $db->exec((string)$indexSql);
            foreach ($triggers as $trigger) $db->exec((string)$trigger['sql']);
            if ($db->query('PRAGMA foreign_key_check')->fetchAll()) throw new RuntimeException('Die Umstellung der Leistungsnamen hat eine Fremdschlüsselprüfung nicht bestanden.');
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        } finally {
            $db->exec('PRAGMA foreign_keys = ON');
        }
    }

    private static function ensureNoSupportLevels(PDO $db): void
    {
        $stmt=$db->prepare('INSERT OR IGNORE INTO provider_class_levels(provider_type,class_code,class_label,sort_order) VALUES(?,?,?,0)');
        $stmt->execute(['FI','0','0 – keine Verbundpartnerleistung erforderlich']);
        $stmt->execute(['DSV','0','0 – keine Verbundpartnerleistung erforderlich']);
    }

    private static function regionalAssociations(): array
    {
        return [
            ['abbreviation' => 'HSGV', 'full_name' => 'Hanseatischer Sparkassen- und Giroverband'],
            ['abbreviation' => 'OSV', 'full_name' => 'Ostdeutscher Sparkassenverband'],
            ['abbreviation' => 'RSGV', 'full_name' => 'Rheinischer Sparkassen- und Giroverband'],
            ['abbreviation' => 'SGVSH', 'full_name' => 'Sparkassen- und Giroverband für Schleswig-Holstein'],
            ['abbreviation' => 'SGVHT', 'full_name' => 'Sparkassen- und Giroverband Hessen-Thüringen'],
            ['abbreviation' => 'SVBW', 'full_name' => 'Sparkassenverband Baden-Württemberg'],
            ['abbreviation' => 'SVB', 'full_name' => 'Sparkassenverband Bayern'],
            ['abbreviation' => 'SV Berlin', 'full_name' => 'Sparkassenverband Berlin'],
            ['abbreviation' => 'SVN', 'full_name' => 'Sparkassenverband Niedersachsen'],
            ['abbreviation' => 'SVRP', 'full_name' => 'Sparkassenverband Rheinland-Pfalz'],
            ['abbreviation' => 'SV Saar', 'full_name' => 'Sparkassenverband Saar'],
            ['abbreviation' => 'SVWL', 'full_name' => 'Sparkassenverband Westfalen-Lippe'],
        ];
    }
}
