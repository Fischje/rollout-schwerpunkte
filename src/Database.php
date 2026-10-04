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
