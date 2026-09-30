<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Dieses Skript darf nur auf der Kommandozeile ausgeführt werden.\n");
    exit(1);
}

$databasePath = $argv[1] ?? dirname(__DIR__) . '/data/rollout.sqlite';
$databasePath = str_starts_with($databasePath, DIRECTORY_SEPARATOR)
    ? $databasePath
    : getcwd() . DIRECTORY_SEPARATOR . $databasePath;

if (!is_file($databasePath)) {
    fwrite(STDERR, "Datenbank nicht gefunden: {$databasePath}\n");
    exit(1);
}

function table_names(PDO $db): array {
    return array_column($db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(), 'name');
}

function column_names(PDO $db, string $table): array {
    return array_column($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
}

function append_detail(string $current, string $value, string $rolloutObject, string $label = ''): string {
    $value = trim($value);
    if ($value === '') return $current;
    $entry = '[' . $rolloutObject . '] ' . ($label !== '' ? $label . ': ' : '') . $value;
    $lines = preg_split('/\R{2,}/u', trim($current)) ?: [];
    if (in_array($entry, $lines, true)) return $current;
    return trim($current) === '' ? $entry : trim($current) . "\n\n" . $entry;
}

try {
    $db = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec('PRAGMA busy_timeout = 10000; PRAGMA foreign_keys = ON;');

    $version = (int)$db->query('PRAGMA user_version')->fetchColumn();
    $tables = table_names($db);
    if ($version === 22 && in_array('project_services', $tables, true) && in_array('project_service_rollout_objects', $tables, true)) {
        fwrite(STDOUT, "Die Datenbank ist bereits auf Schema-Version 22. Es wurde nichts geändert.\n");
        exit(0);
    }
    if (!in_array($version, [16, 21], true)) {
        throw new RuntimeException("Unterstützt werden Schema-Version 16 (Version 0.1.20) und Schema-Version 21 (Version 0.1.21); gefunden wurde {$version}.");
    }

    $required = ['users','projects','rollout_objects','providers','support_services','support_service_classes','support_service_provider_targets','project_standard_services','support_matrix','project_support_matrix','project_provider_contacts'];
    $missing = array_values(array_diff($required, $tables));
    if ($missing) throw new RuntimeException('Es fehlen erforderliche Tabellen: ' . implode(', ', $missing));
    $serviceColumns = column_names($db, 'support_services');
    foreach (['is_standard','scope_type','scope_project_id','mandatory_for_all','schedule_relevant'] as $column) {
        if (!in_array($column, $serviceColumns, true)) throw new RuntimeException("Die erwartete Spalte support_services.{$column} fehlt.");
    }
    if ((string)$db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Die Datenbank ist bereits vor dem Patch beschädigt.');
    $hasObjectScope = in_array('scope_rollout_object_id', $serviceColumns, true);
    $invalidCustomSql = $hasObjectScope
        ? "SELECT COUNT(*) FROM support_services s WHERE s.is_standard=0 AND COALESCE(s.scope_project_id,(SELECT r.project_id FROM rollout_objects r WHERE r.id=s.scope_rollout_object_id)) IS NULL"
        : "SELECT COUNT(*) FROM support_services WHERE is_standard=0 AND scope_project_id IS NULL";
    $invalidCustom = (int)$db->query($invalidCustomSql)->fetchColumn();
    if ($invalidCustom > 0) throw new RuntimeException("Es wurden {$invalidCustom} zusätzliche Leistungen ohne Projektzuordnung gefunden. Der Patch wurde vorsorglich abgebrochen.");

    $backupPath = $databasePath . '.backup-before-0.1.22-' . date('Ymd-His');
    $db->exec('VACUUM INTO ' . $db->quote($backupPath));
    $before = [];
    foreach (['users','projects','rollout_objects','providers','support_services','project_support_matrix','support_matrix'] as $table) $before[$table] = (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    $oldObjectResponses = $db->query("SELECT m.*,r.project_id,r.name rollout_object_name,p.type provider_type FROM support_matrix m JOIN rollout_objects r ON r.id=m.rollout_object_id JOIN providers p ON p.id=m.provider_id")->fetchAll();

    $db->exec('PRAGMA foreign_keys = OFF');
    $transactionOpen = false;
    $db->exec('BEGIN IMMEDIATE');
    $transactionOpen = true;
    try {
        if ($hasObjectScope) {
            $db->exec("UPDATE support_services SET scope_project_id=(SELECT r.project_id FROM rollout_objects r WHERE r.id=support_services.scope_rollout_object_id) WHERE is_standard=0 AND scope_project_id IS NULL AND scope_rollout_object_id IS NOT NULL");
        }
        $db->exec(<<<'SQL'
CREATE TABLE support_services_v22 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    is_standard INTEGER NOT NULL DEFAULT 1,
    project_id INTEGER,
    mandatory_for_all INTEGER NOT NULL DEFAULT 0,
    schedule_relevant INTEGER NOT NULL DEFAULT 1,
    active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK(is_standard IN (0,1)),
    CHECK(mandatory_for_all IN (0,1)),
    CHECK(schedule_relevant IN (0,1)),
    CHECK(active IN (0,1)),
    CHECK((is_standard=1 AND project_id IS NULL) OR (is_standard=0 AND project_id IS NOT NULL)),
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
);
INSERT INTO support_services_v22(id,name,description,is_standard,project_id,mandatory_for_all,schedule_relevant,active,sort_order,created_at)
SELECT id,name,COALESCE(description,''),CASE WHEN is_standard=0 THEN 0 ELSE 1 END,
       CASE WHEN is_standard=0 THEN scope_project_id ELSE NULL END,
       CASE WHEN mandatory_for_all=0 THEN 0 ELSE 1 END,
       CASE WHEN schedule_relevant=0 THEN 0 ELSE 1 END,
       CASE WHEN active=0 THEN 0 ELSE 1 END,COALESCE(sort_order,0),COALESCE(created_at,CURRENT_TIMESTAMP)
FROM support_services;
DROP TABLE support_services;
ALTER TABLE support_services_v22 RENAME TO support_services;

CREATE TABLE project_services_v22 (
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(project_id,support_service_id),
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY(support_service_id) REFERENCES support_services(id) ON DELETE CASCADE
);
INSERT OR IGNORE INTO project_services_v22(project_id,support_service_id)
SELECT ps.project_id,ps.support_service_id FROM project_standard_services ps JOIN support_services s ON s.id=ps.support_service_id;
INSERT OR IGNORE INTO project_services_v22(project_id,support_service_id)
SELECT project_id,id FROM support_services WHERE is_standard=0;
INSERT OR IGNORE INTO project_services_v22(project_id,support_service_id)
SELECT project_id,support_service_id FROM project_support_matrix;
INSERT OR IGNORE INTO project_services_v22(project_id,support_service_id)
SELECT r.project_id,m.support_service_id FROM support_matrix m JOIN rollout_objects r ON r.id=m.rollout_object_id;

CREATE UNIQUE INDEX IF NOT EXISTS idx_rollout_project_id ON rollout_objects(project_id,id);
CREATE TABLE project_service_rollout_objects_v22 (
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    rollout_object_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(project_id,support_service_id,rollout_object_id),
    FOREIGN KEY(project_id,support_service_id) REFERENCES project_services_v22(project_id,support_service_id) ON DELETE CASCADE,
    FOREIGN KEY(project_id,rollout_object_id) REFERENCES rollout_objects(project_id,id) ON DELETE CASCADE
);
INSERT OR IGNORE INTO project_service_rollout_objects_v22(project_id,support_service_id,rollout_object_id)
SELECT ps.project_id,ps.support_service_id,r.id
FROM project_services_v22 ps
JOIN rollout_objects r ON r.project_id=ps.project_id
WHERE EXISTS (
    SELECT 1 FROM support_service_classes c
    WHERE c.support_service_id=ps.support_service_id AND (
        (c.dimension='functional' AND EXISTS(SELECT 1 FROM support_service_provider_targets t WHERE t.support_service_id=ps.support_service_id AND t.provider_type='RV')
         AND CASE c.class_code WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C' THEN 3 WHEN 'D' THEN 4 ELSE 99 END
             <= CASE r.functional_class WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C' THEN 3 WHEN 'D' THEN 4 ELSE 0 END)
        OR
        (c.dimension='technical' AND EXISTS(SELECT 1 FROM support_service_provider_targets t WHERE t.support_service_id=ps.support_service_id AND t.provider_type IN ('FI','DSV'))
         AND CAST(c.class_code AS INTEGER) <= COALESCE(r.technical_class,0))
    )
);
INSERT OR IGNORE INTO project_service_rollout_objects_v22(project_id,support_service_id,rollout_object_id)
SELECT r.project_id,m.support_service_id,r.id
FROM support_matrix m
JOIN rollout_objects r ON r.id=m.rollout_object_id
JOIN project_services_v22 ps ON ps.project_id=r.project_id AND ps.support_service_id=m.support_service_id;

CREATE TABLE project_support_matrix_v22 (
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    provider_id INTEGER NOT NULL,
    offered INTEGER NOT NULL DEFAULT 0,
    automatic INTEGER NOT NULL DEFAULT 0,
    delivery_mode TEXT NOT NULL DEFAULT '' CHECK(delivery_mode IN ('','self','other_rv','external','none')),
    delivery_partner TEXT NOT NULL DEFAULT '',
    schedule TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(project_id,support_service_id,provider_id),
    FOREIGN KEY(project_id,support_service_id) REFERENCES project_services_v22(project_id,support_service_id) ON DELETE CASCADE,
    FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE
);
INSERT OR IGNORE INTO project_support_matrix_v22(project_id,support_service_id,provider_id,offered,automatic,delivery_mode,delivery_partner,schedule,note,updated_at)
SELECT m.project_id,m.support_service_id,m.provider_id,
       CASE WHEN m.offered=0 THEN 0 ELSE 1 END,CASE WHEN m.automatic=0 THEN 0 ELSE 1 END,
       CASE WHEN m.delivery_mode IN ('self','other_rv','external','none') THEN m.delivery_mode ELSE '' END,
       COALESCE(m.delivery_partner,''),COALESCE(m.schedule,''),COALESCE(m.note,''),COALESCE(m.updated_at,CURRENT_TIMESTAMP)
FROM project_support_matrix m
JOIN project_services_v22 ps ON ps.project_id=m.project_id AND ps.support_service_id=m.support_service_id
JOIN providers p ON p.id=m.provider_id
WHERE EXISTS(SELECT 1 FROM support_service_provider_targets t WHERE t.support_service_id=m.support_service_id AND t.provider_type=p.type);
SQL);

        $selectCurrent = $db->prepare('SELECT * FROM project_support_matrix_v22 WHERE project_id=? AND support_service_id=? AND provider_id=?');
        $upsert = $db->prepare('INSERT INTO project_support_matrix_v22(project_id,support_service_id,provider_id,offered,automatic,delivery_mode,delivery_partner,schedule,note) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(project_id,support_service_id,provider_id) DO UPDATE SET offered=excluded.offered,automatic=excluded.automatic,delivery_mode=excluded.delivery_mode,delivery_partner=excluded.delivery_partner,schedule=excluded.schedule,note=excluded.note,updated_at=CURRENT_TIMESTAMP');
        $targetCheck = $db->prepare('SELECT 1 FROM support_service_provider_targets WHERE support_service_id=? AND provider_type=?');
        foreach ($oldObjectResponses as $old) {
            $targetCheck->execute([(int)$old['support_service_id'], (string)$old['provider_type']]);
            if (!$targetCheck->fetchColumn()) continue;
            $projectId = (int)$old['project_id'];$serviceId = (int)$old['support_service_id'];$providerId = (int)$old['provider_id'];$objectName = (string)$old['rollout_object_name'];
            $selectCurrent->execute([$projectId,$serviceId,$providerId]);$current=$selectCurrent->fetch()?:['offered'=>0,'automatic'=>0,'delivery_mode'=>'','delivery_partner'=>'','schedule'=>'','note'=>''];
            $mode = (string)$current['delivery_mode'];$partner = (string)$current['delivery_partner'];$note = (string)$current['note'];$oldMode = (string)$old['delivery_mode'];$oldPartner = (string)$old['delivery_partner'];
            if ($mode === '' && in_array($oldMode,['self','other_rv','external','none'],true)) {$mode=$oldMode;$partner=$oldPartner;} elseif ($oldMode !== '' && ($mode !== $oldMode || $partner !== $oldPartner)) {$note=append_detail($note,trim($oldMode.' '.$oldPartner),$objectName,'bisherige Bereitstellung');}
            $schedule=append_detail((string)$current['schedule'],(string)$old['schedule'],$objectName);
            $note=append_detail($note,(string)$old['note'],$objectName);
            $upsert->execute([$projectId,$serviceId,$providerId,max((int)$current['offered'],(int)$old['offered']),max((int)$current['automatic'],(int)$old['automatic']),$mode,$partner,$schedule,$note]);
        }

        $db->exec(<<<'SQL'
DROP TABLE project_support_matrix;
DROP TABLE support_matrix;
DROP TABLE project_standard_services;
ALTER TABLE project_services_v22 RENAME TO project_services;
ALTER TABLE project_service_rollout_objects_v22 RENAME TO project_service_rollout_objects;
ALTER TABLE project_support_matrix_v22 RENAME TO project_support_matrix;

DELETE FROM support_service_classes WHERE NOT ((dimension='functional' AND class_code IN ('A','B','C','D')) OR (dimension='technical' AND class_code IN ('1','2','3')));
DELETE FROM project_provider_contacts WHERE contact_name='' AND contact_phone='';
UPDATE project_support_matrix SET schedule='' WHERE support_service_id IN (SELECT id FROM support_services WHERE schedule_relevant=0);
UPDATE project_support_matrix SET delivery_mode='',delivery_partner='' WHERE support_service_id IN (SELECT id FROM support_services WHERE mandatory_for_all=1);

DROP INDEX IF EXISTS idx_service_scope_object;
DROP INDEX IF EXISTS idx_service_scope_project;
DROP INDEX IF EXISTS idx_project_standard_service;
DROP INDEX IF EXISTS idx_support_matrix_service;
CREATE INDEX IF NOT EXISTS idx_rollout_project ON rollout_objects(project_id);
CREATE INDEX IF NOT EXISTS idx_project_name ON projects(name);
CREATE INDEX IF NOT EXISTS idx_rollout_name ON rollout_objects(name);
CREATE INDEX IF NOT EXISTS idx_service_project ON support_services(project_id,is_standard,active);
CREATE INDEX IF NOT EXISTS idx_service_catalog ON support_services(is_standard,active,name);
CREATE INDEX IF NOT EXISTS idx_service_class_lookup ON support_service_classes(dimension,class_code,support_service_id);
CREATE INDEX IF NOT EXISTS idx_project_service ON project_services(support_service_id,project_id);
CREATE INDEX IF NOT EXISTS idx_service_rollout_object ON project_service_rollout_objects(rollout_object_id,support_service_id);
CREATE INDEX IF NOT EXISTS idx_service_provider_target ON support_service_provider_targets(provider_type,support_service_id);
CREATE INDEX IF NOT EXISTS idx_project_matrix_service ON project_support_matrix(support_service_id,provider_id);

CREATE TRIGGER IF NOT EXISTS validate_project_service_insert
BEFORE INSERT ON project_services
WHEN EXISTS (SELECT 1 FROM support_services s WHERE s.id=NEW.support_service_id AND s.is_standard=0 AND s.project_id<>NEW.project_id)
BEGIN SELECT RAISE(ABORT, 'Zusätzliche Leistung gehört zu einem anderen Projekt'); END;
CREATE TRIGGER IF NOT EXISTS validate_project_service_update
BEFORE UPDATE ON project_services
WHEN EXISTS (SELECT 1 FROM support_services s WHERE s.id=NEW.support_service_id AND s.is_standard=0 AND s.project_id<>NEW.project_id)
BEGIN SELECT RAISE(ABORT, 'Zusätzliche Leistung gehört zu einem anderen Projekt'); END;
CREATE TRIGGER IF NOT EXISTS validate_project_matrix_provider_insert
BEFORE INSERT ON project_support_matrix
WHEN NOT EXISTS (SELECT 1 FROM providers p JOIN support_service_provider_targets t ON t.provider_type=p.type WHERE p.id=NEW.provider_id AND t.support_service_id=NEW.support_service_id)
BEGIN SELECT RAISE(ABORT, 'Leistung ist diesem Leistungserbringer nicht zugeordnet'); END;
CREATE TRIGGER IF NOT EXISTS validate_project_matrix_provider_update
BEFORE UPDATE ON project_support_matrix
WHEN NOT EXISTS (SELECT 1 FROM providers p JOIN support_service_provider_targets t ON t.provider_type=p.type WHERE p.id=NEW.provider_id AND t.support_service_id=NEW.support_service_id)
BEGIN SELECT RAISE(ABORT, 'Leistung ist diesem Leistungserbringer nicht zugeordnet'); END;
PRAGMA user_version = 22;
SQL);
        $foreignKeyErrors = $db->query('PRAGMA foreign_key_check')->fetchAll();
        if ($foreignKeyErrors) throw new RuntimeException('Die Fremdschlüsselprüfung nach der Bereinigung ist fehlgeschlagen.');
        $db->exec('COMMIT');$transactionOpen=false;
    } catch (Throwable $error) {
        if ($transactionOpen) $db->exec('ROLLBACK');
        throw $error;
    } finally {
        $db->exec('PRAGMA foreign_keys = ON');
    }

    foreach (['users','projects','rollout_objects','providers','support_services'] as $table) {
        $after=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        if ($after !== $before[$table]) throw new RuntimeException("Unerwartete Änderung der Datensatzanzahl in {$table}.");
    }
    $db->exec('VACUUM');$db->exec('ANALYZE');
    $integrity=(string)$db->query('PRAGMA integrity_check')->fetchColumn();if($integrity!=='ok')throw new RuntimeException('Die abschließende Integritätsprüfung ist fehlgeschlagen: '.$integrity);
    $associations=(int)$db->query('SELECT COUNT(*) FROM project_services')->fetchColumn();$mappings=(int)$db->query('SELECT COUNT(*) FROM project_service_rollout_objects')->fetchColumn();$responses=(int)$db->query('SELECT COUNT(*) FROM project_support_matrix')->fetchColumn();
    fwrite(STDOUT,"Datenbank erfolgreich auf Schema-Version 22 aktualisiert.\n");
    fwrite(STDOUT,"Sicherung: {$backupPath}\n");
    fwrite(STDOUT,"Projekte: {$before['projects']} | Rolloutobjekte: {$before['rollout_objects']} | Leistungen: {$before['support_services']}\n");
    fwrite(STDOUT,"Projektzuordnungen: {$associations} | Rolloutobjekt-Nutzungen: {$mappings} | zusammengeführte Rückmeldungen: {$responses}\n");
} catch (Throwable $error) {
    fwrite(STDERR,"FEHLER: {$error->getMessage()}\n");
    exit(1);
}
