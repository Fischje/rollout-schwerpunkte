<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Dieses Skript darf nur auf der Kommandozeile ausgeführt werden.\n");
    exit(1);
}

$databasePath=$argv[1]??dirname(__DIR__).'/data/rollout.sqlite';
$databasePath=str_starts_with($databasePath,DIRECTORY_SEPARATOR)?$databasePath:getcwd().DIRECTORY_SEPARATOR.$databasePath;
if(!is_file($databasePath)){fwrite(STDERR,"Datenbank nicht gefunden: {$databasePath}\n");exit(1);}

try{
    $db=new PDO('sqlite:'.$databasePath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec('PRAGMA busy_timeout=10000; PRAGMA foreign_keys=ON;');
    $version=(int)$db->query('PRAGMA user_version')->fetchColumn();
    if($version===23){fwrite(STDOUT,"Die Datenbank ist bereits auf Schema-Version 23. Es wurde nichts geändert.\n");exit(0);}
    if($version!==22)throw new RuntimeException("Unterstützt wird Schema-Version 22; gefunden wurde {$version}.");
    if((string)$db->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Die Datenbank ist bereits vor dem Patch beschädigt.');

    $backupPath=$databasePath.'.backup-before-0.1.30-'.date('Ymd-His');
    $db->exec('VACUUM INTO '.$db->quote($backupPath));
    $before=[];foreach(['users','projects','rollout_objects','providers','support_services','project_services'] as $table)$before[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();

    $db->exec('PRAGMA foreign_keys=OFF');
    $db->exec('BEGIN IMMEDIATE');
    try{
        $db->exec(<<<'SQL'
CREATE TABLE provider_class_levels (
    provider_type TEXT NOT NULL CHECK(provider_type IN ('FI','DSV')),
    class_code TEXT NOT NULL,
    class_label TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    PRIMARY KEY(provider_type,class_code),
    UNIQUE(provider_type,sort_order)
);
INSERT INTO provider_class_levels(provider_type,class_code,class_label,sort_order) VALUES
('FI','1','1',1),('FI','2','2',2),('FI','3','3',3),
('DSV','L1','1',1),('DSV','L2','2',2),('DSV','L3','3',3);

CREATE TABLE support_service_classes_v23 (
    support_service_id INTEGER NOT NULL,
    dimension TEXT NOT NULL CHECK(dimension IN ('functional','technical','dsv')),
    class_code TEXT NOT NULL,
    PRIMARY KEY(support_service_id,dimension,class_code),
    FOREIGN KEY(support_service_id) REFERENCES support_services(id) ON DELETE CASCADE
);
INSERT INTO support_service_classes_v23 SELECT support_service_id,dimension,class_code FROM support_service_classes;
INSERT OR IGNORE INTO support_service_classes_v23(support_service_id,dimension,class_code)
SELECT c.support_service_id,'dsv','L'||c.class_code
FROM support_service_classes c
WHERE c.dimension='technical' AND c.class_code IN ('1','2','3')
  AND EXISTS(SELECT 1 FROM support_service_provider_targets t WHERE t.support_service_id=c.support_service_id AND t.provider_type='DSV');
DROP TABLE support_service_classes;
ALTER TABLE support_service_classes_v23 RENAME TO support_service_classes;

CREATE TABLE rollout_object_provider_classes (
    rollout_object_id INTEGER NOT NULL,
    provider_type TEXT NOT NULL CHECK(provider_type IN ('FI','DSV')),
    class_code TEXT NOT NULL,
    PRIMARY KEY(rollout_object_id,provider_type),
    FOREIGN KEY(rollout_object_id) REFERENCES rollout_objects(id) ON DELETE CASCADE,
    FOREIGN KEY(provider_type,class_code) REFERENCES provider_class_levels(provider_type,class_code)
);
INSERT INTO rollout_object_provider_classes(rollout_object_id,provider_type,class_code)
SELECT id,'FI',CAST(technical_class AS TEXT) FROM rollout_objects WHERE technical_class IN (1,2,3);
INSERT INTO rollout_object_provider_classes(rollout_object_id,provider_type,class_code)
SELECT id,'DSV','L'||technical_class FROM rollout_objects WHERE technical_class IN (1,2,3);

CREATE TABLE rollout_object_support_matrix (
    rollout_object_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    provider_id INTEGER NOT NULL,
    offered INTEGER NOT NULL DEFAULT 0,
    automatic INTEGER NOT NULL DEFAULT 0,
    schedule TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(rollout_object_id,support_service_id,provider_id),
    FOREIGN KEY(project_id,support_service_id) REFERENCES project_services(project_id,support_service_id) ON DELETE CASCADE,
    FOREIGN KEY(project_id,rollout_object_id) REFERENCES rollout_objects(project_id,id) ON DELETE CASCADE,
    FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE
);
INSERT OR IGNORE INTO rollout_object_support_matrix(rollout_object_id,project_id,support_service_id,provider_id,offered,automatic,schedule,note,updated_at)
SELECT u.rollout_object_id,m.project_id,m.support_service_id,m.provider_id,m.offered,m.automatic,m.schedule,m.note,m.updated_at
FROM project_support_matrix m
JOIN providers p ON p.id=m.provider_id AND p.type IN ('FI','DSV')
JOIN project_service_rollout_objects u ON u.project_id=m.project_id AND u.support_service_id=m.support_service_id;

CREATE TABLE catalog_service_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_type TEXT NOT NULL CHECK(provider_type IN ('FI','DSV')),
    provider_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    rollout_object_id INTEGER NOT NULL,
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
    FOREIGN KEY(project_id,rollout_object_id) REFERENCES rollout_objects(project_id,id) ON DELETE CASCADE,
    FOREIGN KEY(accepted_service_id) REFERENCES support_services(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_service_class_lookup ON support_service_classes(dimension,class_code,support_service_id);
CREATE INDEX idx_object_provider_class ON rollout_object_provider_classes(provider_type,class_code,rollout_object_id);
CREATE INDEX idx_object_matrix_service ON rollout_object_support_matrix(support_service_id,provider_id,rollout_object_id);
CREATE INDEX idx_catalog_suggestion_status ON catalog_service_suggestions(status,provider_type,created_at);
PRAGMA user_version=23;
SQL);
        $foreignKeyErrors=$db->query('PRAGMA foreign_key_check')->fetchAll();
        if($foreignKeyErrors)throw new RuntimeException('Die Fremdschlüsselprüfung nach dem Patch ist fehlgeschlagen.');
        $db->exec('COMMIT');
    }catch(Throwable $error){$db->exec('ROLLBACK');throw $error;}finally{$db->exec('PRAGMA foreign_keys=ON');}

    foreach($before as $table=>$count){$after=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();if($after!==$count)throw new RuntimeException("Unerwartete Änderung der Datensatzanzahl in {$table}.");}
    if((string)$db->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Die Datenbankprüfung nach dem Patch ist fehlgeschlagen.');
    fwrite(STDOUT,"Datenbank erfolgreich auf Schema-Version 23 aktualisiert.\nSicherung: {$backupPath}\n");
}catch(Throwable $error){fwrite(STDERR,"Update fehlgeschlagen: ".$error->getMessage()."\n");exit(1);}
