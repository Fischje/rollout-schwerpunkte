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
