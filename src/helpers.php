<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function is_logged_in(): bool { return isset($_SESSION['user_id']); }
function require_login(): void { if (!is_logged_in()) redirect('?page=login'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        http_response_code(419); exit('Die Sitzung ist abgelaufen. Bitte Seite neu laden.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = [$type, $message]; }
function flashes(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function post_string(string $key): string { return trim((string)($_POST[$key] ?? '')); }
function nullable(string $value): ?string { return $value === '' ? null : $value; }
function date_to_iso(?string $date): ?string {
    if (!$date) return null;
    foreach (['d.m.Y', 'Y-m-d', 'd.m.y', 'm/d/Y'] as $format) {
        $parsed = DateTime::createFromFormat('!' . $format, $date);
        if ($parsed && $parsed->format($format) === $date) return $parsed->format('Y-m-d');
    }
    return null;
}
function valid_date(?string $date): ?string {
    if (!$date) return null;
    $iso = date_to_iso($date);
    if (!$iso) throw new RuntimeException('Bitte Datumsangaben im Format TT.MM.JJJJ eingeben, zum Beispiel 01.01.2026.');
    return $iso;
}
function format_date(?string $date, string $empty = '–'): string {
    if (!$date) return $empty;
    $iso = date_to_iso($date);
    if (!$iso) return $date;
    return DateTime::createFromFormat('!Y-m-d', $iso)->format('d.m.Y');
}
function class_label(array $object): string {
    $parts = [];
    if (!empty($object['functional_class'])) $parts[] = 'Rolloutklasse Bankfachlich ' . $object['functional_class'];
    $fiLabel=(string)($object['fi_class_label']??$object['technical_class']??'');
    $dsvLabel=(string)($object['dsv_class_label']??'');
    if ($fiLabel!=='') $parts[] = 'Rolloutklasse FI ' . $fiLabel;
    if ($dsvLabel!=='') $parts[] = 'Rolloutklasse DSV ' . $dsvLabel;
    return $parts ? implode(' · ', $parts) : 'noch nicht klassifiziert';
}
function cumulative_functional_classes(?string $class): array {
    $order = ['A', 'B', 'C', 'D'];
    $position = array_search($class, $order, true);
    return $position === false ? [] : array_slice($order, 0, $position + 1);
}
/** FI-Klassenschema (Code => Bezeichnung), beim Start aus der Datenbank geladen; Standard 1–3. */
function fi_class_registry(?array $levels = null): array {
    static $registry = ['1' => '1', '2' => '2', '3' => '3'];
    if ($levels !== null) { $registry = []; foreach ($levels as $level) $registry[(string)$level['class_code']] = (string)$level['class_label']; }
    return $registry;
}
function fi_class_codes(): array { return array_map('strval', array_keys(fi_class_registry())); }
function fi_class_label(string $code): string { return $code === '0' ? '0 – keine Verbundpartnerleistung erforderlich' : (fi_class_registry()[$code] ?? $code); }

function cumulative_technical_classes(null|int|string $class): array {
    $order = fi_class_codes();
    $position = array_search((string)$class, $order, true);
    return $position === false ? [] : array_slice($order, 0, $position + 1);
}

function provider_class_levels(PDO $db,string $providerType,bool $includeNoSupport=false): array {
    if(!in_array($providerType,['FI','DSV'],true))return [];
    $sql='SELECT class_code,class_label,sort_order FROM provider_class_levels WHERE provider_type=?';
    if(!$includeNoSupport)$sql.=" AND class_code<>'0'";
    $stmt=$db->prepare($sql.' ORDER BY sort_order');
    $stmt->execute([$providerType]);return $stmt->fetchAll();
}

function cumulative_dsv_classes(?string $classCode): array {
    if(!$classCode||!preg_match('/^L([1-8])$/',$classCode,$match))return [];
    $result=[];for($level=1;$level<=(int)$match[1];$level++)$result[]='L'.$level;return $result;
}

function enrich_rollout_objects(PDO $db,array $objects): array {
    if(!$objects)return [];$ids=array_map('intval',array_column($objects,'id'));$placeholders=implode(',',array_fill(0,count($ids),'?'));
    $stmt=$db->prepare("SELECT c.rollout_object_id,c.provider_type,c.class_code,l.class_label FROM rollout_object_provider_classes c JOIN provider_class_levels l ON l.provider_type=c.provider_type AND l.class_code=c.class_code WHERE c.rollout_object_id IN ($placeholders)");$stmt->execute($ids);$classes=[];
    foreach($stmt->fetchAll() as $row)$classes[(int)$row['rollout_object_id']][(string)$row['provider_type']]=['code'=>(string)$row['class_code'],'label'=>(string)$row['class_label']];
    foreach($objects as &$object){$id=(int)$object['id'];$object['fi_class_code']=$classes[$id]['FI']['code']??((string)($object['technical_class']??''));$object['fi_class_label']=$classes[$id]['FI']['label']??((string)($object['technical_class']??''));$object['dsv_class_code']=$classes[$id]['DSV']['code']??'';$object['dsv_class_label']=$classes[$id]['DSV']['label']??'';}unset($object);return $objects;
}

function enrich_support_services(PDO $db, array $services): array {
    if (!$services) return [];
    static $cache = [];
    static $targetCache = [];
    $dbKey = spl_object_id($db);
    $ids = array_values(array_unique(array_map('intval', array_column($services, 'id'))));
    $missing = array_values(array_filter($ids, fn(int $id): bool => !array_key_exists($id, $cache[$dbKey] ?? [])));
    if ($missing) {
        foreach ($missing as $id) $cache[$dbKey][$id] = [];
        $placeholders = implode(',', array_fill(0, count($missing), '?'));
        $stmt = $db->prepare("SELECT support_service_id,dimension,class_code FROM support_service_classes WHERE support_service_id IN ($placeholders) ORDER BY CASE dimension WHEN 'functional' THEN 1 ELSE 2 END,class_code");
        $stmt->execute($missing);
        foreach ($stmt->fetchAll() as $row) $cache[$dbKey][(int)$row['support_service_id']][] = $row['dimension'] . ':' . $row['class_code'];
    }
    $missingTargets = array_values(array_filter($ids, fn(int $id): bool => !array_key_exists($id, $targetCache[$dbKey] ?? [])));
    if ($missingTargets) {
        foreach ($missingTargets as $id) $targetCache[$dbKey][$id] = [];
        $placeholders = implode(',', array_fill(0, count($missingTargets), '?'));
        $stmt = $db->prepare("SELECT support_service_id,provider_type FROM support_service_provider_targets WHERE support_service_id IN ($placeholders) ORDER BY CASE provider_type WHEN 'RV' THEN 1 WHEN 'FI' THEN 2 ELSE 3 END");
        $stmt->execute($missingTargets);
        foreach ($stmt->fetchAll() as $row) $targetCache[$dbKey][(int)$row['support_service_id']][] = $row['provider_type'];
    }
    $dsvLabels=[];foreach(provider_class_levels($db,'DSV') as $level)$dsvLabels[(string)$level['class_code']]=(string)$level['class_label'];
    foreach ($services as &$service) {
        $service['class_codes'] = $cache[$dbKey][(int)$service['id']] ?? [];
        $service['provider_targets'] = $targetCache[$dbKey][(int)$service['id']] ?? [];
        $service['dsv_class_labels']=$dsvLabels;
        $service['requires_delivery_source'] = (int)(bool)array_intersect(
            ['functional:A', 'functional:B', 'functional:C', 'functional:D'],
            $service['class_codes']
        );
    }
    unset($service);
    return $services;
}

function support_service_class_label(array $service): string {
    $functional = [];
    $technical = [];
    $dsv = [];
    foreach ((array)($service['class_codes'] ?? []) as $class) {
        [$dimension, $code] = array_pad(explode(':', (string)$class, 2), 2, '');
        if ($dimension === 'functional') $functional[] = $code;
        if ($dimension === 'technical') $technical[] = $code;
        if ($dimension === 'dsv') $dsv[] = (string)(($service['dsv_class_labels']??[])[$code]??$code);
    }
    $parts = [];
    if ($functional) $parts[] = 'Rolloutklasse Bankfachlich ' . implode(', ', $functional);
    if ($technical) $parts[] = 'Rolloutklasse FI ' . implode(', ', $technical);
    if ($dsv) $parts[] = 'Rolloutklasse DSV ' . implode(', ', $dsv);
    return implode(' · ', $parts);
}

function service_class_badges(array $service): string {
    $badges = [];
    foreach ((array)($service['class_codes'] ?? []) as $class) {
        [$dimension, $code] = array_pad(explode(':', (string)$class, 2), 2, '');
        if ($dimension === 'functional' && in_array($code, ['A','B','C','D'], true)) {
            $badges[] = '<span class="badge service-class-badge class-tone-functional-' . strtolower($code) . '">Bankfachlich ' . $code . '</span>';
        }
        if ($dimension === 'technical' && in_array($code, fi_class_codes(), true)) {
            $badges[] = '<span class="badge service-class-badge class-tone-technical-' . $code . '">FI ' . $code . '</span>';
        }
        if ($dimension === 'dsv' && preg_match('/^L([1-8])$/',$code,$match)) {$label=(string)(($service['dsv_class_labels']??[])[$code]??$code);$tone=min(3,(int)$match[1]);$badges[]='<span class="badge service-class-badge class-tone-technical-'.$tone.'">DSV '.e($label).'</span>';}
    }
    return implode('', $badges);
}

function project_rollout_class_scope(PDO $db, int $projectId): array {
    $stmt = $db->prepare('SELECT * FROM rollout_objects WHERE project_id=?');$stmt->execute([$projectId]);$objects=enrich_rollout_objects($db,$stmt->fetchAll());
    $functional = [];
    $technical = [];
    $dsv=[];
    foreach ($objects as $object) {
        $functional = array_values(array_unique(array_merge($functional, cumulative_functional_classes($object['functional_class'] ?? null))));
        $technical = array_values(array_unique(array_merge($technical, cumulative_technical_classes($object['fi_class_code'] ?? $object['technical_class'] ?? null))));
        $dsv=array_values(array_unique(array_merge($dsv,cumulative_dsv_classes($object['dsv_class_code']??null))));
    }
    $functional = array_values(array_intersect(['A','B','C','D'], $functional));
    $technical = array_values(array_intersect(fi_class_codes(), $technical));
    return ['functional' => $functional, 'technical' => $technical, 'dsv'=>$dsv];
}

/**
 * Gruppiert Auswahllisten nach den im Projekt verfügbaren Klassen. Eine
 * mehrfach klassifizierte Leistung erscheint in beiden passenden Bereichen;
 * die gleichnamigen Auswahlfelder werden in der Oberfläche synchronisiert.
 * Innerhalb jeder Klasse wird deutsch alphabetisch sortiert.
 */
function service_selection_groups(array $services, array $allowedFunctional = ['A','B','C','D'], ?array $allowedTechnical = null, array $allowedDsv = ['L1','L2','L3','L4','L5','L6','L7','L8']): array {
    $allowedTechnical ??= fi_class_codes();
    $groups = [
        'functional' => array_fill_keys(['A','B','C','D'], []),
        'technical' => array_fill_keys(fi_class_codes(), []),
        'dsv'=>array_fill_keys(['L1','L2','L3','L4','L5','L6','L7','L8'],[]),
    ];
    foreach ($services as $service) {
        $codes = ['functional' => [], 'technical' => [],'dsv'=>[]];
        $targets = (array)($service['provider_targets'] ?? []);
        foreach ((array)($service['class_codes'] ?? []) as $class) {
            [$dimension, $code] = array_pad(explode(':', (string)$class, 2), 2, '');
            if ($dimension === 'functional' && in_array('RV', $targets, true)) $codes[$dimension][] = $code;
            if ($dimension === 'technical' && in_array('FI',$targets,true)) $codes[$dimension][] = $code;
            if ($dimension === 'dsv' && in_array('DSV',$targets,true)) $codes[$dimension][] = $code;
        }
        foreach ([
            'functional' => [['A','B','C','D'], $allowedFunctional],
            'technical' => [fi_class_codes(), $allowedTechnical],
            'dsv'=>[['L1','L2','L3','L4','L5','L6','L7','L8'],$allowedDsv],
        ] as $dimension => [$order, $allowed]) foreach ($order as $code) {
            if (in_array($code, $allowed, true) && in_array($code, $codes[$dimension], true)) {
                $groups[$dimension][$code][] = $service;
                break;
            }
        }
    }
    $sortKey = static fn(array $service): string => strtr(
        mb_strtolower((string)($service['name'] ?? '')),
        ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']
    );
    foreach ($groups as &$dimensionGroups) foreach ($dimensionGroups as &$classServices) {
        usort($classServices, static fn(array $a, array $b): int => $sortKey($a) <=> $sortKey($b));
    }
    unset($dimensionGroups, $classServices);
    return $groups;
}

function service_selection_group_ids(array $groups): array {
    $ids = [];
    foreach ($groups as $dimensionGroups) foreach ($dimensionGroups as $services) {
        foreach ($services as $service) $ids[(int)$service['id']] = true;
    }
    return array_keys($ids);
}

function service_applies_to_provider(array $service, array $provider, ?array $object = null): bool {
    $providerType=(string)($provider['type']??'');
    if(!in_array($providerType,(array)($service['provider_targets']??[]),true))return false;
    $dimension = $providerType === 'RV' ? 'functional' : ($providerType==='DSV'?'dsv':'technical');
    $codes = [];
    foreach ((array)($service['class_codes'] ?? []) as $class) {
        [$classDimension, $code] = array_pad(explode(':', (string)$class, 2), 2, '');
        if ($classDimension === $dimension) $codes[] = $code;
    }
    if (!$codes) return false;
    if ($object === null) return true;
    $applicable = $dimension === 'functional'?cumulative_functional_classes($object['functional_class']??null):($dimension==='dsv'?cumulative_dsv_classes($object['dsv_class_code']??null):cumulative_technical_classes($object['fi_class_code']??$object['technical_class']??null));
    return (bool)array_intersect($codes, $applicable);
}

function service_applies_to_rollout_object(array $service, array $object): bool {
    $targets = (array)($service['provider_targets'] ?? []);
    $functional = cumulative_functional_classes($object['functional_class'] ?? null);
    $technical = cumulative_technical_classes($object['fi_class_code'] ?? $object['technical_class'] ?? null);
    $dsv=cumulative_dsv_classes($object['dsv_class_code']??null);
    foreach ((array)($service['class_codes'] ?? []) as $class) {
        [$dimension, $code] = array_pad(explode(':', (string)$class, 2), 2, '');
        if ($dimension === 'functional' && in_array('RV', $targets, true) && in_array($code, $functional, true)) return true;
        if ($dimension === 'technical' && in_array('FI',$targets,true) && in_array($code,$technical,true))return true;
        if ($dimension === 'dsv' && in_array('DSV',$targets,true) && in_array($code,$dsv,true))return true;
    }
    return false;
}

function prune_invalid_service_rollout_usage(PDO $db, ?int $projectId = null, ?int $serviceId = null): void {
    $sql = 'SELECT r.id,u.project_id,u.support_service_id,u.rollout_object_id,r.functional_class,r.technical_class
            FROM project_service_rollout_objects u
            JOIN rollout_objects r ON r.id=u.rollout_object_id WHERE 1=1';
    $params = [];
    if ($projectId !== null) {$sql .= ' AND u.project_id=?';$params[]=$projectId;}
    if ($serviceId !== null) {$sql .= ' AND u.support_service_id=?';$params[]=$serviceId;}
    $stmt=$db->prepare($sql);$stmt->execute($params);$rows=enrich_rollout_objects($db,$stmt->fetchAll());if(!$rows)return;
    $serviceIds=array_values(array_unique(array_map('intval',array_column($rows,'support_service_id'))));$placeholders=implode(',',array_fill(0,count($serviceIds),'?'));$stmt=$db->prepare("SELECT * FROM support_services WHERE id IN ($placeholders)");$stmt->execute($serviceIds);$services=[];foreach(enrich_support_services($db,$stmt->fetchAll()) as $service)$services[(int)$service['id']]=$service;
    $delete=$db->prepare('DELETE FROM project_service_rollout_objects WHERE project_id=? AND support_service_id=? AND rollout_object_id=?');
    foreach($rows as $row)if(!isset($services[(int)$row['support_service_id']])||!service_applies_to_rollout_object($services[(int)$row['support_service_id']],$row))$delete->execute([(int)$row['project_id'],(int)$row['support_service_id'],(int)$row['rollout_object_id']]);
}

/**
 * Seit 0.1.50 gibt es kein „obligatorisch“ mehr: Ob die Regionalverbände eine Leistung erbringen,
 * regelt die Erbringung zentral/regional. Die Funktion bleibt für bestehende Aufrufer erhalten.
 */
function service_is_mandatory_for_provider(array $service, array $provider): bool {
    return false;
}

/** Nur bankfachliche Leistungen mit Zuständigkeit Regionalverbände können regional erbracht werden. */
function service_regional_capable(array $service): bool {
    if (!in_array('RV', (array)($service['provider_targets'] ?? []), true)) return false;
    foreach ((array)($service['class_codes'] ?? []) as $code) if (str_starts_with((string)$code, 'functional:')) return true;
    return false;
}

/** Wirksame Erbringung an einem Rolloutobjekt: Abweichung am Objekt, sonst Katalogvorgabe; nicht regional fähige Leistungen sind immer zentral. */
function service_effective_delivery(array $service, ?string $objectLevel = null): string {
    if (!service_regional_capable($service)) return 'central';
    if (in_array($objectLevel, ['regional', 'central'], true)) return $objectLevel;
    return ($service['default_delivery'] ?? 'regional') === 'central' ? 'central' : 'regional';
}

/** Je Rolloutleistung: Rolloutobjekte mit regionaler bzw. zentraler Erbringung und ob die Regionalverbände gefragt werden. */
function project_service_delivery_map(PDO $db, int $projectId, array $services): array {
    $stmt = $db->prepare('SELECT support_service_id, rollout_object_id, delivery_level FROM project_service_rollout_objects WHERE project_id=?');
    $stmt->execute([$projectId]);
    $usage = [];
    foreach ($stmt->fetchAll() as $row) $usage[(int)$row['support_service_id']][(int)$row['rollout_object_id']] = $row['delivery_level'];
    $map = [];
    foreach ($services as $service) {
        $id = (int)$service['id'];
        $entry = ['regional' => [], 'central' => [], 'levels' => $usage[$id] ?? []];
        foreach ($usage[$id] ?? [] as $objectId => $level) $entry[service_effective_delivery($service, $level)][] = (int)$objectId;
        $entry['rv_required'] = service_regional_capable($service) && ($entry['regional'] || (!($usage[$id] ?? []) && service_effective_delivery($service) === 'regional'));
        $map[$id] = $entry;
    }
    return $map;
}

function normalize_delivery_mode(string $value): string {
    $key = mb_strtolower(trim($value));
    $map = [
        'self' => 'self', 'selbst' => 'self', 'eigene bereitstellung' => 'self',
        'other_rv' => 'other_rv', 'anderer rv' => 'other_rv', 'anderer regionalverband' => 'other_rv',
        'external' => 'external', 'externer dl' => 'external', 'externer dienstleister' => 'external',
        'none' => 'none', 'keine' => 'none', 'keine bereitstellung' => 'none', 'nein' => 'none',
    ];
    return $map[$key] ?? '';
}

function delivery_mode_label(string $mode, bool $automatic = false): string {
    if ($automatic && $mode === '') return 'Basisleistung (automatisch)';
    return [
        'self' => 'Eigene Bereitstellung',
        'other_rv' => 'Anderer Regionalverband',
        'external' => 'Externer Dienstleister',
        'none' => 'Keine Bereitstellung',
    ][$mode] ?? '';
}

/**
 * Synchronisiert verpflichtende Leistungen. Verpflichtend sind Leistungen der
 * Basisklassen Bankfachlich A für Regionalverbände, FI 1 sowie die erste
 * Klasse des jeweils gepflegten DSV-Schemas
 * sowie vom Projektteam für alle jeweils zuständigen Leistungserbringer
 * vorgegebene zusätzliche Leistungen.
 */
function sync_mandatory_services(PDO $db, ?int $onlyObjectId = null, ?int $onlyProjectId = null): void
{
    $providers = $db->query('SELECT id,type FROM providers WHERE active=1')->fetchAll();
    if ($onlyObjectId !== null && $onlyProjectId === null) {
        $stmt = $db->prepare('SELECT project_id FROM rollout_objects WHERE id=?');
        $stmt->execute([$onlyObjectId]);
        $onlyProjectId = (int)$stmt->fetchColumn() ?: null;
    }
    $projectSql = 'SELECT id FROM projects';
    $projectParams = [];
    if ($onlyProjectId !== null) {
        $projectSql .= ' WHERE id=?';
        $projectParams[] = $onlyProjectId;
    }
    sync_always_included_services($db, $onlyProjectId);
    $stmt = $db->prepare($projectSql);
    $stmt->execute($projectParams);
    $projects = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    $projectServiceStmt = $db->prepare(
        "SELECT DISTINCT s.* FROM support_services s
         JOIN project_services ps ON ps.support_service_id=s.id
         WHERE s.active=1 AND ps.project_id=?
           AND (s.mandatory_for_all=1 OR EXISTS (
               SELECT 1 FROM support_service_classes b
               WHERE b.support_service_id=s.id AND
                     ((b.dimension='functional' AND b.class_code='A') OR
                      (b.dimension='technical' AND b.class_code='1') OR
                      (b.dimension='dsv' AND b.class_code='L1'))
           ))"
    );
    $validProjects = [];
    $servicesByProject = [];
    foreach ($projects as $projectId) {
        $projectServiceStmt->execute([$projectId]);
        $servicesByProject[$projectId] = enrich_support_services($db, $projectServiceStmt->fetchAll());
        $classScope = project_rollout_class_scope($db, $projectId);
        $projectClassObject = [
            'functional_class' => $classScope['functional'] ? $classScope['functional'][count($classScope['functional']) - 1] : null,
            'technical_class' => $classScope['technical'] ? $classScope['technical'][count($classScope['technical']) - 1] : null,
            'dsv_class_code'=>$classScope['dsv']?$classScope['dsv'][count($classScope['dsv'])-1]:null,
        ];
        foreach ($servicesByProject[$projectId] as $service) {
            foreach ($providers as $provider) if(service_applies_to_provider($service,$provider,$projectClassObject)&&service_is_mandatory_for_provider($service,$provider)) {
                $validProjects[$projectId . ':' . $service['id'] . ':' . $provider['id']] = true;
            }
        }
    }
    $existingSql = 'SELECT m.project_id,m.support_service_id,m.provider_id,m.note,m.schedule,m.delivery_mode,m.delivery_partner
                    FROM project_support_matrix m WHERE m.automatic=1';
    $existingParams = [];
    if ($onlyProjectId !== null) {
        $existingSql .= ' AND m.project_id=?';
        $existingParams[] = $onlyProjectId;
    }
    $stmt = $db->prepare($existingSql);
    $stmt->execute($existingParams);
    $delete = $db->prepare('DELETE FROM project_support_matrix WHERE project_id=? AND support_service_id=? AND provider_id=?');
    $preserve = $db->prepare('UPDATE project_support_matrix SET offered=0,automatic=0,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND support_service_id=? AND provider_id=?');
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['project_id'] . ':' . $row['support_service_id'] . ':' . $row['provider_id'];
        if (!isset($validProjects[$key])) {
            $keyParams = [(int)$row['project_id'], (int)$row['support_service_id'], (int)$row['provider_id']];
            $hasDetails = trim((string)$row['note']) !== '' || trim((string)$row['schedule']) !== '' || trim((string)$row['delivery_mode']) !== '' || trim((string)$row['delivery_partner']) !== '';
            $hasDetails ? $preserve->execute($keyParams) : $delete->execute($keyParams);
        }
    }
    $upsert = $db->prepare(
        'INSERT INTO project_support_matrix(project_id,support_service_id,provider_id,offered,automatic,note)
         VALUES(?,?,?,?,?,?) ON CONFLICT(project_id,support_service_id,provider_id)
         DO UPDATE SET offered=1,automatic=1,updated_at=CURRENT_TIMESTAMP'
    );
    foreach ($projects as $projectId) {
        foreach ($servicesByProject[$projectId] ?? [] as $service) {
            foreach ($providers as $provider) if(isset($validProjects[$projectId . ':' . $service['id'] . ':' . $provider['id']])) $upsert->execute([$projectId, $service['id'], $provider['id'], 1, 1, '']);
        }
    }
}

/**
 * „Immer enthalten“: Solche Leistungen werden jedem Rolloutobjekt zugeordnet, dessen
 * Klasse zur Leistung passt (kumulativ), und lassen sich nicht abwählen.
 * Zusätzliche Rolloutleistungen gelten nur innerhalb ihres Projekts.
 */
function sync_always_included_services(PDO $db, ?int $onlyProjectId = null): void
{
    $services = enrich_support_services($db, $db->query('SELECT * FROM support_services WHERE active=1 AND always_included=1')->fetchAll());
    if (!$services) return;
    $sql = 'SELECT * FROM rollout_objects';
    $params = [];
    if ($onlyProjectId !== null) { $sql .= ' WHERE project_id=?'; $params[] = $onlyProjectId; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $objects = enrich_rollout_objects($db, $stmt->fetchAll());
    $projectInsert = $db->prepare('INSERT OR IGNORE INTO project_services(project_id,support_service_id) VALUES(?,?)');
    $usageInsert = $db->prepare('INSERT OR IGNORE INTO project_service_rollout_objects(project_id,support_service_id,rollout_object_id) VALUES(?,?,?)');
    foreach ($objects as $object) {
        foreach ($services as $service) {
            if (empty($service['is_standard']) && (int)$service['project_id'] !== (int)$object['project_id']) continue;
            if (!service_applies_to_rollout_object($service, $object)) continue;
            $projectInsert->execute([(int)$object['project_id'], (int)$service['id']]);
            $usageInsert->execute([(int)$object['project_id'], (int)$service['id'], (int)$object['id']]);
        }
    }
}

function mandatory_project_pairs(PDO $db, int $projectId): array
{
    sync_mandatory_services($db, null, $projectId);
    $stmt = $db->prepare('SELECT support_service_id,provider_id FROM project_support_matrix WHERE project_id=? AND automatic=1');
    $stmt->execute([$projectId]);
    $pairs = [];
    foreach ($stmt->fetchAll() as $row) $pairs[(int)$row['support_service_id']][(int)$row['provider_id']] = true;
    return $pairs;
}

/**
 * Reduziert eine kumulative Klassenauswahl auf ihre niedrigste Ausgangsklasse.
 * Alle höheren Klassen gelten automatisch und werden nur in der Oberfläche
 * beziehungsweise in Exporten als abgeleitete Auswahl angezeigt.
 */
function canonical_cumulative_class_selection(array $selected, array $ordered): array
{
    $selected = array_map('strval', $selected);
    foreach ($ordered as $class) {
        $class = (string)$class;
        if (in_array($class, $selected, true)) return [$class];
    }
    return [];
}

/** Liest CHANGELOG.md: [['version'=>'0.1.39','items'=>['…']], …], neueste zuerst. */
function changelog_entries(): array {
    $path = dirname(__DIR__) . '/CHANGELOG.md';
    $text = is_file($path) ? (string)file_get_contents($path) : '';
    $entries = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        if (preg_match('/^##\s+(\d+\.\d+\.\d+)\s*$/u', $line, $match)) { $entries[] = ['version' => $match[1], 'items' => []]; continue; }
        if ($entries && preg_match('/^-\s+(.+)$/u', $line, $match)) $entries[count($entries) - 1]['items'][] = trim($match[1]);
    }
    return $entries;
}

/** Die Programmversion ist der oberste Eintrag im CHANGELOG.md. */
function app_version(?string $fallback = null): string {
    return changelog_entries()[0]['version'] ?? ($fallback ?? '0.0.0');
}

/** Kennzeichnungen „Immer enthalten“ und Erbringung (zentral/regional) laut Katalog. */
function service_flag_badges(array $service): string {
    $badges = '';
    if (!empty($service['always_included'])) $badges .= '<span class="badge text-bg-primary" title="Automatisch an jedem passenden Rolloutobjekt, nicht abwählbar">Immer enthalten</span> ';
    if (service_regional_capable($service)) $badges .= service_effective_delivery($service) === 'central'
        ? '<span class="badge text-bg-dark" title="Katalogvorgabe: zentral durch das Projekt erbracht; die Regionalverbände werden nicht gefragt (je Rolloutobjekt änderbar)">Zentral</span>'
        : '<span class="badge text-bg-warning" title="Katalogvorgabe: regional durch die Regionalverbände erbracht; sie werden nach der Bereitstellung gefragt (je Rolloutobjekt änderbar)">Regional (RV)</span>';
    return $badges;
}

/** Zuständige Leistungserbringer einer Leistung als Klartext, z. B. „Regionalverbände (bankfachlich) · FI“. */
function service_provider_summary(array $service): string {
    $labels = ['RV' => 'Regionalverbände (bankfachlich)', 'FI' => 'FI', 'DSV' => 'DSV'];
    $parts = [];
    foreach ($labels as $type => $label) if (in_array($type, (array)($service['provider_targets'] ?? []), true)) $parts[] = $label;
    return implode(' · ', $parts);
}

/**
 * Stand der Leistungserbringung eines Leistungserbringers für eine Rolloutleistung.
 * Rückgabe: ['key'=>…, 'label'=>…, 'by'=>…]; key ∈ mandatory, self, other_rv, external, none, open, planned, not_planned, na.
 * Als Lücke gelten „open“ (keine Angabe) und „none“ (keine Bereitstellung).
 */
function service_provision_status(array $service, array $provider, ?array $cell, bool $applicable): array {
    if (!$applicable) return ['key' => 'na', 'label' => 'Nicht relevant', 'by' => ''];
    $type = (string)($provider['type'] ?? '');
    $name = (string)($provider['name'] ?? '');
    if (service_is_mandatory_for_provider($service, $provider)) return ['key' => 'mandatory', 'label' => 'Verbindlich (obligatorisch)', 'by' => $name];
    if ($type === 'RV' && !empty($service['requires_delivery_source'])) {
        $mode = normalize_delivery_mode((string)($cell['delivery_mode'] ?? ''));
        $partner = trim((string)($cell['delivery_partner'] ?? ''));
        return match ($mode) {
            'self' => ['key' => 'self', 'label' => 'Zugesagt – selbst', 'by' => $name],
            'other_rv' => ['key' => 'other_rv', 'label' => 'Zugesagt – über anderen Regionalverband', 'by' => $partner !== '' ? $partner . ' (für ' . $name . ')' : $name],
            'external' => ['key' => 'external', 'label' => 'Zugesagt – externer Dienstleister', 'by' => ($partner !== '' ? $partner : 'externer Dienstleister') . ' (für ' . $name . ')'],
            'none' => ['key' => 'none', 'label' => 'Keine Bereitstellung', 'by' => ''],
            default => ['key' => 'open', 'label' => 'Offen – noch keine Angabe', 'by' => ''],
        };
    }
    if ($cell === null) return $type === 'RV' ? ['key' => 'open', 'label' => 'Offen – noch keine Angabe', 'by' => ''] : ['key' => 'open', 'label' => 'Noch keine Rückmeldung', 'by' => ''];
    if (!empty($cell['offered'])) return $type === 'RV' ? ['key' => 'self', 'label' => 'Zugesagt', 'by' => $name] : ['key' => 'planned', 'label' => 'Geplant', 'by' => $name];
    return $type === 'RV' ? ['key' => 'none', 'label' => 'Keine Bereitstellung', 'by' => ''] : ['key' => 'not_planned', 'label' => 'Nicht geplant', 'by' => ''];
}

/** Katalog einer Leistung: RV (bankfachlich), FI oder DSV; null, wenn noch mehreren Anbietern zugeordnet (Aufräumen nötig). */
function service_catalog_type(array $service): ?string {
    $targets = array_values(array_intersect(['RV', 'FI', 'DSV'], (array)($service['provider_targets'] ?? [])));
    return count($targets) === 1 ? $targets[0] : null;
}

function catalog_type_label(string $type): string {
    return ['RV' => 'Bankfachlich', 'FI' => 'FI', 'DSV' => 'DSV'][$type] ?? $type;
}

/** Klassen-Dimension eines Katalogs. */
function catalog_dimension(string $type): string {
    return ['RV' => 'functional', 'FI' => 'technical', 'DSV' => 'dsv'][$type] ?? '';
}

/**
 * Ist der Name im angegebenen Katalog bereits vergeben? Standardleistungen sind katalogweit eindeutig,
 * zusätzliche Rolloutleistungen innerhalb ihres Projekts. Groß-/Kleinschreibung und Leerzeichen zählen nicht.
 */
function service_name_taken(PDO $db, string $name, string $catalogType, int $excludeId = 0, ?int $projectId = null): ?array {
    $key = mb_strtolower((string)preg_replace('/[^a-zA-Z0-9äöüÄÖÜß]/u', '', $name));
    $stmt = $db->prepare('SELECT s.* FROM support_services s JOIN support_service_provider_targets t ON t.support_service_id=s.id AND t.provider_type=? WHERE s.id<>?');
    $stmt->execute([$catalogType, $excludeId]);
    foreach ($stmt->fetchAll() as $row) {
        if (mb_strtolower((string)preg_replace('/[^a-zA-Z0-9äöüÄÖÜß]/u', '', (string)$row['name'])) !== $key) continue;
        if (!empty($row['is_standard']) || $projectId === null || (int)$row['project_id'] === $projectId) return $row;
    }
    return null;
}

/** Findet eine Leistung über den Namen, bevorzugt im angegebenen Katalog; nur eindeutige Treffer zählen. */
function find_service_by_name(PDO $db, string $name, ?string $catalogType = null, ?int $projectId = null): ?array {
    $stmt = $db->prepare('SELECT * FROM support_services WHERE name=?');
    $stmt->execute([$name]);
    $rows = array_values(array_filter($stmt->fetchAll(), static fn(array $row): bool => !empty($row['is_standard']) || $projectId === null || (int)$row['project_id'] === $projectId));
    if (count($rows) <= 1) return $rows[0] ?? null;
    if ($catalogType === null) return null;
    $matches = array_values(array_filter(enrich_support_services($db, $rows), static fn(array $row): bool => in_array($catalogType, (array)$row['provider_targets'], true)));
    if (count($matches) === 1) return $matches[0];
    $exact = array_values(array_filter($matches, static fn(array $row): bool => service_catalog_type($row) === $catalogType));
    return count($exact) === 1 ? $exact[0] : null;
}

/** Tabellen mit Herkunftsvermerk: Schlüsselspalten und inhaltlich relevante Spalten. */
function origin_tables(): array {
    return [
        'project_service_rollout_objects' => [['project_id', 'support_service_id', 'rollout_object_id'], ['delivery_level']],
        'project_support_matrix' => [['project_id', 'support_service_id', 'provider_id'], ['offered', 'delivery_mode', 'delivery_partner', 'schedule', 'note']],
        'rollout_object_support_matrix' => [['rollout_object_id', 'support_service_id', 'provider_id'], ['offered', 'schedule', 'note']],
    ];
}

/** Stand vor einer Änderung: je Zeile Inhalt und bisheriger Vermerk. */
function origin_snapshot(PDO $db): array {
    $snapshot = [];
    foreach (origin_tables() as $table => [$keys, $values]) {
        foreach ($db->query('SELECT * FROM ' . $table)->fetchAll() as $row) {
            $key = implode(':', array_map(static fn($column) => (string)$row[$column], $keys));
            $snapshot[$table][$key] = [serialize(array_map(static fn($column) => (string)($row[$column] ?? ''), $values)), $row['origin'] ?? null, $row['origin_at'] ?? null];
        }
    }
    return $snapshot;
}

/**
 * Setzt nach einer Änderung den Herkunftsvermerk: Neue oder inhaltlich geänderte Zeilen erhalten
 * $labelFor($table,$row), unveränderte (auch gelöscht und wieder angelegte) behalten ihren bisherigen Vermerk.
 */
function apply_origins(PDO $db, array $before, callable $labelFor): void {
    $now = date('Y-m-d H:i:s');$user = trim((string)($_SESSION['display_name'] ?? ''));
    foreach (origin_tables() as $table => [$keys, $values]) {
        $where = implode(' AND ', array_map(static fn($column) => $column . '=?', $keys));
        $update = $db->prepare('UPDATE ' . $table . ' SET origin=?,origin_at=? WHERE ' . $where);
        foreach ($db->query('SELECT * FROM ' . $table)->fetchAll() as $row) {
            $keyValues = array_map(static fn($column) => $row[$column], $keys);$key = implode(':', array_map('strval', $keyValues));
            $content = serialize(array_map(static fn($column) => (string)($row[$column] ?? ''), $values));$old = $before[$table][$key] ?? null;
            if ($old && $old[0] === $content) {$origin = $old[1];$at = $old[2];}
            else {$label = (string)$labelFor($table, $row);if ($label === '') continue;$origin = $label . ($user !== '' ? ' · ' . $user : '');$at = $now;}
            if (($row['origin'] ?? null) !== $origin || ($row['origin_at'] ?? null) !== $at) $update->execute(array_merge([$origin, $at], $keyValues));
        }
    }
}

/** Kurzer Vermerk für die Anzeige, z. B. „Rückmeldung FI · Anna, 05.10.2026 14:30“. */
function origin_text(?string $origin, ?string $at): string {
    if (!$origin) return '';
    $date = $at ? date('d.m.Y H:i', strtotime($at)) : '';
    return 'zuletzt: ' . $origin . ($date !== '' ? ', ' . $date : '');
}

/** Gleichnamige Leistungen am selben Rolloutobjekt (verschiedene Kataloge): [objectId][serviceId] => Liste der anderen Kataloge. */
function duplicate_service_names(PDO $db, int $projectId): array {
    $stmt = $db->prepare('SELECT u.rollout_object_id,s.id,s.name FROM project_service_rollout_objects u JOIN support_services s ON s.id=u.support_service_id WHERE u.project_id=? AND s.active=1');
    $stmt->execute([$projectId]);$groups = [];
    foreach ($stmt->fetchAll() as $row) $groups[(int)$row['rollout_object_id']][mb_strtolower(trim((string)$row['name']))][] = (int)$row['id'];
    $ids = [];foreach ($groups as $byName) foreach ($byName as $serviceIds) if (count($serviceIds) > 1) array_push($ids, ...$serviceIds);
    if (!$ids) return [];
    $types = [];$services = enrich_support_services($db, $db->query('SELECT * FROM support_services WHERE id IN (' . implode(',', array_unique($ids)) . ')')->fetchAll());
    foreach ($services as $service) $types[(int)$service['id']] = catalog_type_label(service_catalog_type($service) ?? implode('/', (array)$service['provider_targets']));
    $result = [];
    foreach ($groups as $objectId => $byName) foreach ($byName as $serviceIds) if (count($serviceIds) > 1)
        foreach ($serviceIds as $serviceId) $result[$objectId][$serviceId] = array_values(array_map(static fn($other) => $types[$other] ?? '', array_filter($serviceIds, static fn($other) => $other !== $serviceId)));
    return $result;
}

/** Hinweis-Badge für eine gleichnamige Leistung eines anderen Katalogs am selben Rolloutobjekt. */
function duplicate_badge(array $others): string {
    if (!$others) return '';
    $text = 'Gleicher Name auch bei ' . implode(', ', array_unique($others));
    return '<span class="badge duplicate-badge" title="' . e($text . ' am selben Rolloutobjekt – erlaubt, bitte prüfen, ob beides gewollt ist.') . '">⚠ ' . e($text) . '</span>';
}

/** Die neun strategischen Themenfelder: [id => Name]. */
function strategic_fields(PDO $db): array {
    return array_column($db->query('SELECT id,name FROM strategic_fields ORDER BY id')->fetchAll(), 'name', 'id');
}

/** „1. Beratung …“; ohne gepflegten Namen „Themenfeld 5“. */
function strategic_field_label(?int $id, array $fields): string {
    if (!$id) return '';
    $name = trim((string)($fields[$id] ?? ''));
    return $id . '. ' . ($name !== '' ? $name : 'Themenfeld ' . $id);
}

/** Rollout-Ansprechpartner je Leistungserbringer: [provider_id => [['name'=>…,'email'=>…], …]] in gepflegter Reihenfolge. */
function provider_rollout_contacts(PDO $db): array {
    $contacts = [];
    foreach ($db->query('SELECT provider_id,name,email FROM provider_rollout_contacts ORDER BY provider_id,sort_order,id')->fetchAll() as $row) {
        $contacts[(int)$row['provider_id']][] = ['name' => (string)$row['name'], 'email' => (string)$row['email']];
    }
    return $contacts;
}

/** Leistungserbringer in der Reihenfolge der Einstellungsseite: erst Regionalverbände, dann Dienstleister. */
function providers_for_contacts(PDO $db): array {
    return $db->query("SELECT * FROM providers ORDER BY CASE type WHEN 'RV' THEN 1 WHEN 'FI' THEN 2 WHEN 'DSV' THEN 3 ELSE 4 END,sort_order,name")->fetchAll();
}

/** Ersetzt die Rollout-Ansprechpartner eines Leistungserbringers; leere Zeilen werden übergangen. */
function save_provider_rollout_contacts(PDO $db, int $providerId, string $providerName, array $contacts): void {
    $clean = [];
    foreach ($contacts as $contact) {
        $name = trim((string)($contact['name'] ?? ''));
        $email = trim((string)($contact['email'] ?? ''));
        if ($name === '' && $email === '') continue;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new RuntimeException('Die E-Mail-Adresse „' . $email . '“ bei ' . $providerName . ' ist ungültig.');
        $key = mb_strtolower($email !== '' ? $email : $name);
        if (isset($clean[$key])) throw new RuntimeException('Bei ' . $providerName . ' ist „' . ($email !== '' ? $email : $name) . '“ doppelt eingetragen.');
        $clean[$key] = [$name, $email];
    }
    $db->prepare('DELETE FROM provider_rollout_contacts WHERE provider_id=?')->execute([$providerId]);
    $insert = $db->prepare('INSERT INTO provider_rollout_contacts(provider_id,name,email,sort_order) VALUES(?,?,?,?)');
    foreach (array_values($clean) as $index => [$name, $email]) $insert->execute([$providerId, $name, $email, $index + 1]);
}

/** Excel-Pflegedatei der Rollout-Ansprechpartner: eine Zeile je Person, freie Zeilen für Ergänzungen. */
function provider_contacts_workbook(PDO $db): array {
    $providers = providers_for_contacts($db);
    $contacts = provider_rollout_contacts($db);
    $rows = [['Leistungserbringer', 'Bereich', 'Name', 'E-Mail-Adresse']];
    foreach ($providers as $provider) {
        $area = $provider['type'] === 'RV' ? 'Regionalverband' : 'Dienstleister';
        $list = $contacts[(int)$provider['id']] ?? [['name' => '', 'email' => '']];
        foreach ($list as $contact) $rows[] = [(string)$provider['name'], $area, $contact['name'], $contact['email']];
    }
    for ($i = 0; $i < 20; $i++) $rows[] = ['', '', '', ''];
    $legend = [
        ['type' => 'title', 'values' => ['Rollout-Ansprechpartner der Leistungserbringer']],
        ['type' => 'note', 'values' => ['Eine Zeile je Person', 'Weitere Personen in einer freien Zeile oder durch Einfügen einer Zeile ergänzen; den Leistungserbringer aus der Auswahlliste wählen.']],
        ['type' => 'note', 'values' => ['Datei ist maßgeblich', 'Für jeden Leistungserbringer, der in der Datei vorkommt, ersetzt die Datei die bisherige Liste. Wer gelöscht werden soll, wird aus der Datei entfernt; eine Zeile nur mit dem Leistungserbringer leert dessen Liste.']],
        ['type' => 'note', 'values' => ['Nicht enthalten', 'Leistungserbringer, die in der Datei gar nicht vorkommen, bleiben unverändert.']],
        ['type' => 'note', 'values' => ['Spalte „Bereich“', 'Nur zur Information; sie wird beim Import nicht ausgewertet.']],
    ];
    return ['rows' => $rows, 'legend' => $legend, 'sheet_name' => 'Rollout-Ansprechpartner', 'info_columns' => [1], 'widths' => ['Leistungserbringer' => 22, 'Bereich' => 18, 'Name' => 36, 'E-Mail-Adresse' => 46], 'lists' => [0 => array_column($providers, 'name')]];
}

/** Liest eine Pflegedatei der Rollout-Ansprechpartner ein und schreibt sie (innerhalb einer Transaktion des Aufrufers). Liefert die Anzahl Personen. */
function import_provider_contacts(PDO $db, array $rows): int {
    if (count($rows) < 2) throw new RuntimeException('Die Datei enthält keine Datenzeilen.');
    $normalize = static fn(string $value): string => mb_strtolower((string)preg_replace('/[^a-zA-Z0-9äöüÄÖÜß]/u', '', $value));
    $map = [];
    foreach ((array)$rows[0] as $index => $header) {
        $key = $normalize((string)$header);
        if (in_array($key, ['leistungserbringer', 'regionalverband', 'dienstleister'], true)) $map['provider'] ??= $index;
        elseif (in_array($key, ['name', 'rolloutansprechpartner', 'ansprechpartner'], true)) $map['name'] ??= $index;
        elseif (in_array($key, ['email', 'emailadresse', 'mail'], true)) $map['email'] ??= $index;
    }
    foreach (['provider' => 'Leistungserbringer', 'name' => 'Name', 'email' => 'E-Mail-Adresse'] as $field => $label) {
        if (!isset($map[$field])) throw new RuntimeException('Die erwartete Spalte „' . $label . '“ fehlt. Bitte die Datei „Rollout-Ansprechpartner“ von der Export-Seite verwenden.');
    }
    $providers = [];
    foreach ($db->query('SELECT id,name FROM providers')->fetchAll() as $provider) $providers[mb_strtolower(trim((string)$provider['name']))] = $provider;
    $lists = [];
    foreach (array_slice($rows, 1) as $offset => $row) {
        $get = static fn(string $field): string => trim((string)($row[$map[$field]] ?? ''));
        $providerName = $get('provider');
        $name = $get('name');
        $email = $get('email');
        if ($providerName === '' && $name === '' && $email === '') continue;
        $line = 'Zeile ' . ($offset + 2) . ': ';
        if ($providerName === '') throw new RuntimeException($line . 'Bitte den Leistungserbringer für „' . ($name !== '' ? $name : $email) . '“ auswählen.');
        $provider = $providers[mb_strtolower($providerName)] ?? null;
        if (!$provider) throw new RuntimeException($line . 'Den Leistungserbringer „' . $providerName . '“ gibt es nicht. Bitte einen Namen aus der Auswahlliste verwenden.');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new RuntimeException($line . 'Die E-Mail-Adresse „' . $email . '“ ist ungültig.');
        $lists[(int)$provider['id']] ??= ['name' => (string)$provider['name'], 'contacts' => []];
        if ($name !== '' || $email !== '') $lists[(int)$provider['id']]['contacts'][] = ['name' => $name, 'email' => $email];
    }
    if (!$lists) throw new RuntimeException('Die Datei enthält keine Leistungserbringer.');
    $count = 0;
    foreach ($lists as $providerId => $entry) {
        save_provider_rollout_contacts($db, $providerId, $entry['name'], $entry['contacts']);
        $count += count($entry['contacts']);
    }
    return $count;
}

/** Einfache Einstellungen (z. B. der zuletzt verwendete E-Mail-Text). */
function app_setting(PDO $db, string $key, string $default = ''): string {
    $stmt = $db->prepare('SELECT value FROM app_settings WHERE key=?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function set_app_setting(PDO $db, string $key, string $value): void {
    $db->prepare('INSERT INTO app_settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$key, $value]);
}

/** Vorgabe für die Begleit-E-Mail zur RV-Abfrage; Platzhalter in geschweiften Klammern. */
function rv_mail_defaults(): array {
    return [
        'subject' => 'Abfrage der Unterstützungsleistungen und ihrer Bereitstellung – {Regionalverband}',
        'body' => "Sehr geehrte Kolleginnen und Kollegen,\n\nanbei erhalten Sie die Excel-Datei mit den Unterstützungsleistungen für den {Regionalverband}. Wir bitten Sie, je Unterstützungsleistung anzugeben, wie Sie die Bereitstellung sicherstellen: selbst, über einen anderen Regionalverband, über einen externen Dienstleister oder gar nicht. Soweit relevant, ergänzen Sie bitte Termin bzw. Zeitraum, Bemerkungen und Ansprechpartner.\n\nBitte füllen Sie die gelb markierten Felder aus und senden Sie die Datei bis zum TT.MM.JJJJ an uns zurück.\n\nBei Fragen melden Sie sich gern.\n\nVielen Dank und viele Grüße",
    ];
}

/** Gespeicherter E-Mail-Text; der fachlich falsche Vorschlag aus 0.1.67 (Abfrage der Rolloutschwerpunkte) wird durch die aktuelle Vorgabe ersetzt. */
function rv_mail_setting(PDO $db, string $field): string {
    $outdated = ['subject' => 'Abfrage Rolloutschwerpunkte {Jahr} – {Regionalverband}', 'body' => "Guten Tag {Ansprechpartner},\n\nanbei erhalten Sie die Excel-Datei zur Abfrage der Rolloutschwerpunkte {Jahr} für den {Regionalverband}.\n\nBitte ergänzen Sie die gelb markierten Felder – vor allem die Bereitstellungsart je Rolloutleistung – und senden Sie die Datei bis zum TT.MM.JJJJ an uns zurück.\n\nBei Fragen melden Sie sich gern.\n\nVielen Dank und viele Grüße"];
    $default = rv_mail_defaults()[$field];
    $value = app_setting($db, 'rv_mail_' . $field, $default);
    return $value === $outdated[$field] ? $default : $value;
}

/** „Nachname, Vorname“ wird für die Anrede zu „Vorname Nachname“; Funktionspostfächer bleiben wie sie sind. */
function contact_display_name(string $name): string {
    $parts = array_map('trim', explode(',', $name));
    return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' ? $parts[1] . ' ' . $parts[0] : trim($name);
}

function mail_header_word(string $text): string {
    return preg_match('/[^\x20-\x7E]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

/** Ungesendeter E-Mail-Entwurf (.eml, X-Unsent) mit Text- und HTML-Teil sowie Anhängen; Outlook öffnet ihn zum Bearbeiten und Senden. */
function build_draft_eml(array $recipients, string $subject, string $body, array $attachments): string {
    $to = [];
    foreach ($recipients as $recipient) {
        $email = trim((string)($recipient['email'] ?? ''));
        if ($email === '') continue;
        $name = trim((string)($recipient['name'] ?? ''));
        $to[] = $name === '' ? '<' . $email . '>' : (preg_match('/[^\x20-\x7E]/', $name) ? mail_header_word($name) : '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"') . ' <' . $email . '>';
    }
    $mixed = 'mixed_' . bin2hex(random_bytes(8));
    $alt = 'alt_' . bin2hex(random_bytes(8));
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $html = '<html><head><meta charset="utf-8"></head><body style="font-family:Calibri,Arial,sans-serif;font-size:11pt">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</body></html>';
    $chunk = static fn(string $data): string => rtrim(chunk_split(base64_encode($data), 76, "\r\n"));
    $lines = [
        'X-Unsent: 1',
        'To: ' . implode(', ', $to),
        'Subject: ' . mail_header_word($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $mixed . '"',
        '',
        '--' . $mixed,
        'Content-Type: multipart/alternative; boundary="' . $alt . '"',
        '',
        '--' . $alt,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        $chunk(str_replace("\n", "\r\n", $body)),
        '--' . $alt,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        $chunk($html),
        '--' . $alt . '--',
    ];
    foreach ($attachments as $filename => $data) {
        $encoded = mail_header_word((string)$filename);
        $lines[] = '--' . $mixed;
        $lines[] = 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name="' . $encoded . '"';
        $lines[] = 'Content-Disposition: attachment; filename="' . $encoded . '"';
        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = '';
        $lines[] = $chunk((string)$data);
    }
    $lines[] = '--' . $mixed . '--';
    $lines[] = '';
    return implode("\r\n", $lines);
}
