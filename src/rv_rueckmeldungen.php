<?php
declare(strict_types=1);

/*
 * Rückmeldungen der Regionalverbände ohne Excel-Datei:
 *
 * 1. Importstand: kleine Liste je Regionalverband, ob seine Rückmeldung schon übernommen ist
 *    (Häkchen, Datum, von wem erhalten, Weg, Bemerkung). Gespeichert in app_settings unter
 *    „rv_import_status“ als JSON, damit kein neues Datenbankschema nötig ist. Der Excel-Import
 *    einer RV-Rückmeldung setzt den Eintrag automatisch.
 * 2. RV-Sicht: alle Rolloutleistungen, zu denen ein Regionalverband gefragt wird, über alle Projekte
 *    in einer Tabelle. Gespeichert wird in dieselben Tabellen wie beim Import der RV-Datei
 *    (project_support_matrix, project_provider_contacts, neue Leistungen wie „Neue Rolloutleistung“).
 */

const RV_IMPORT_STATUS_KEY = 'rv_import_status';

// ---------------------------------------------------------------------------------------------
// Importstand
// ---------------------------------------------------------------------------------------------

/** Gespeicherter Importstand je Regionalverband-ID. */
function rv_import_status_all(PDO $db): array {
    $data = json_decode(app_setting($db, RV_IMPORT_STATUS_KEY, '{}'), true);
    return is_array($data) ? $data : [];
}

function rv_import_status_save_all(PDO $db, array $status): void {
    set_app_setting($db, RV_IMPORT_STATUS_KEY, json_encode($status, JSON_UNESCAPED_UNICODE));
}

/** Aktive Regionalverbände mit ihrem Eintrag (done, date, from, way, note). */
function rv_import_status_rows(PDO $db): array {
    $status = rv_import_status_all($db);
    $rows = [];
    foreach ($db->query("SELECT id,name FROM providers WHERE type='RV' AND active=1 ORDER BY sort_order,name")->fetchAll() as $provider) {
        $entry = (array)($status[(string)$provider['id']] ?? []);
        $rows[] = [
            'id' => (int)$provider['id'],
            'name' => (string)$provider['name'],
            'done' => !empty($entry['done']),
            'date' => (string)($entry['date'] ?? ''),
            'from' => (string)($entry['from'] ?? ''),
            'way' => (string)($entry['way'] ?? ''),
            'note' => (string)($entry['note'] ?? ''),
        ];
    }
    return $rows;
}

/** Eintrag eines Regionalverbands als übernommen vermerken (Datum heute); „von wem“ bleibt erhalten, wenn leer. */
function rv_import_status_mark(PDO $db, int $providerId, string $way, string $from = ''): void {
    $status = rv_import_status_all($db);
    $entry = (array)($status[(string)$providerId] ?? []);
    $entry['done'] = 1;
    $entry['date'] = date('Y-m-d');
    $entry['way'] = $way;
    if (trim($from) !== '') $entry['from'] = mb_substr(trim($from), 0, 120);
    $status[(string)$providerId] = $entry;
    rv_import_status_save_all($db, $status);
}

/** Nach dem Import von Rückmeldedateien: jede einem Regionalverband zugeordnete Datei vermerken. */
function rv_import_status_mark_files(PDO $db, array $files): void {
    $rvIds = array_map('intval', $db->query("SELECT id FROM providers WHERE type='RV'")->fetchAll(PDO::FETCH_COLUMN));
    foreach ($files as $file) {
        $providerId = (int)($file['provider_id'] ?? 0);
        if (in_array($providerId, $rvIds, true)) rv_import_status_mark($db, $providerId, 'Excel-Import (' . mb_substr((string)($file['name'] ?? ''), 0, 80) . ')');
    }
}

/** Formular der Importübersicht speichern. */
function rv_import_status_save_posted(PDO $db, array $posted): void {
    $validIds = array_map('intval', $db->query("SELECT id FROM providers WHERE type='RV'")->fetchAll(PDO::FETCH_COLUMN));
    $status = rv_import_status_all($db);
    foreach ($posted as $providerId => $values) {
        $providerId = (int)$providerId;
        if (!in_array($providerId, $validIds, true) || !is_array($values)) continue;
        $done = !empty($values['done']);
        $date = trim((string)($values['date'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('Bitte ein gültiges Datum eingeben.');
        if ($done && $date === '') $date = date('Y-m-d');
        $entry = [
            'done' => $done ? 1 : 0,
            'date' => $date,
            'from' => mb_substr(trim((string)($values['from'] ?? '')), 0, 120),
            'way' => mb_substr(trim((string)($values['way'] ?? '')), 0, 120),
            'note' => mb_substr(trim((string)($values['note'] ?? '')), 0, 500),
        ];
        if (!$done && $entry['date'] === '' && $entry['from'] === '' && $entry['way'] === '' && $entry['note'] === '') unset($status[(string)$providerId]);
        else $status[(string)$providerId] = $entry;
    }
    rv_import_status_save_all($db, $status);
}

function render_rv_import_status(PDO $db): void {
    $rows = rv_import_status_rows($db);
    $doneCount = count(array_filter($rows, static fn(array $row): bool => $row['done']));
    $ways = ['Excel-Import', 'RV-Sicht (von Hand)', 'Termin / Gespräch', 'E-Mail (pauschal)'];
    ?>
<section class="card p-4" id="rv-stand">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <span class="badge text-bg-dark mb-2">Phase 3</span>
      <h2 class="h5 mb-1">Stand der Rückmeldungen der Regionalverbände</h2>
      <p class="text-secondary small mb-0">Welche Rückmeldungen sind schon in der Liste? Der Import einer RV-Datei setzt das Häkchen automatisch; Angaben, die Sie selbst gepflegt haben, vermerken Sie hier oder beim Speichern in der RV-Sicht.</p>
    </div>
    <span class="badge rounded-pill <?=$doneCount === count($rows) && $rows ? 'text-bg-success' : 'text-bg-light border'?> fs-6"><?=$doneCount?> von <?=count($rows)?> übernommen</span>
  </div>
  <?php if (!$rows): ?>
    <div class="alert alert-light border small mb-0">Es sind keine aktiven Regionalverbände angelegt.</div>
  <?php else: ?>
  <form method="post" data-track-dirty>
    <?=csrf_field()?>
    <input type="hidden" name="action" value="save_rv_import_status">
    <datalist id="rv-import-ways"><?php foreach ($ways as $way): ?><option value="<?=e($way)?>"><?php endforeach; ?></datalist>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-3 rv-status-table">
        <thead><tr><th>Regionalverband</th><th class="text-center">Übernommen</th><th>Datum</th><th>Erhalten von</th><th>Weg</th><th>Bemerkung</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): $field = 'rv_status[' . $row['id'] . ']'; ?>
          <tr class="<?=$row['done'] ? 'table-success' : ''?>" data-rv-status-row>
            <td class="fw-semibold text-nowrap"><?=e($row['name'])?></td>
            <td class="text-center"><input class="form-check-input matrix-check" type="checkbox" name="<?=$field?>[done]" value="1" <?=$row['done'] ? 'checked' : ''?> data-rv-status-done aria-label="<?=e('Rückmeldung ' . $row['name'] . ' übernommen')?>"></td>
            <td><input class="form-control form-control-sm" type="date" name="<?=$field?>[date]" value="<?=e($row['date'])?>" data-rv-status-date aria-label="Datum"></td>
            <td><input class="form-control form-control-sm" name="<?=$field?>[from]" value="<?=e($row['from'])?>" placeholder="Name" maxlength="120" aria-label="Erhalten von"></td>
            <td><input class="form-control form-control-sm" name="<?=$field?>[way]" value="<?=e($row['way'])?>" list="rv-import-ways" placeholder="z. B. Excel-Import" maxlength="120" aria-label="Weg"></td>
            <td><input class="form-control form-control-sm" name="<?=$field?>[note]" value="<?=e($row['note'])?>" maxlength="500" aria-label="Bemerkung"></td>
            <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="?page=rv-view&provider_id=<?=$row['id']?>">In RV-Sicht erfassen</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-primary">Stand speichern</button>
      <button class="btn btn-outline-secondary" name="reset" value="1" data-confirm="Alle Häkchen, Daten und Bemerkungen der Liste löschen (z. B. für eine neue Abfragerunde)? Die Angaben der Regionalverbände selbst bleiben erhalten.">Liste zurücksetzen</button>
    </div>
  </form>
  <script>
  document.querySelectorAll('[data-rv-status-done]').forEach(box => box.addEventListener('change', () => {
    const row = box.closest('[data-rv-status-row]');
    const date = row.querySelector('[data-rv-status-date]');
    if (box.checked && !date.value) date.value = new Date().toISOString().slice(0, 10);
    row.classList.toggle('table-success', box.checked);
  }));
  </script>
  <?php endif; ?>
</section>
<?php
}

// ---------------------------------------------------------------------------------------------
// RV-Sicht: Daten
// ---------------------------------------------------------------------------------------------

/** Höchste Klasse je Bereich über alle Rolloutobjekte eines Projekts (wie in der RV-Datei). */
function rv_view_project_class_object(PDO $db, int $projectId): array {
    $scope = project_rollout_class_scope($db, $projectId);
    $last = static fn(array $list) => $list ? $list[count($list) - 1] : null;
    return ['functional_class' => $last($scope['functional']), 'fi_class_code' => $last($scope['technical']), 'dsv_class_code' => $last($scope['dsv'])];
}

/**
 * Alle Projekte mit den Rolloutleistungen, zu denen der Regionalverband gefragt wird –
 * dieselbe Auswahl wie die Eingabezeilen der RV-Datei (nicht „Nur Information“).
 * Rückgabe je Projekt-ID: project, contact, objects, services (ID => Leistung mit Zellwerten), catalog (ergänzbare Katalogleistungen).
 */
function rv_view_data(PDO $db, array $provider): array {
    sync_always_included_services($db);
    $providerId = (int)$provider['id'];
    $contactStmt = $db->prepare('SELECT contact_name,contact_phone FROM project_provider_contacts WHERE project_id=? AND provider_id=?');
    $serviceStmt = $db->prepare("SELECT s.*,m.offered cell_offered,m.delivery_mode cell_mode,m.delivery_partner cell_partner,m.schedule cell_schedule,m.note cell_note,m.origin cell_origin,m.origin_at cell_origin_at,m.provider_id cell_exists
        FROM project_services ps JOIN support_services s ON s.id=ps.support_service_id
        LEFT JOIN project_support_matrix m ON m.project_id=ps.project_id AND m.support_service_id=s.id AND m.provider_id=?
        WHERE ps.project_id=? AND s.active=1 ORDER BY s.name COLLATE NOCASE");
    $objectStmt = $db->prepare('SELECT * FROM rollout_objects WHERE project_id=? ORDER BY start_date,name');
    $standardServices = enrich_support_services($db, $db->query("SELECT * FROM support_services WHERE is_standard=1 AND active=1 ORDER BY name COLLATE NOCASE")->fetchAll());

    $result = [];
    foreach ($db->query('SELECT * FROM projects ORDER BY name COLLATE NOCASE')->fetchAll() as $project) {
        $projectId = (int)$project['id'];
        $classObject = rv_view_project_class_object($db, $projectId);
        $contactStmt->execute([$projectId, $providerId]);
        $contact = $contactStmt->fetch() ?: ['contact_name' => '', 'contact_phone' => ''];
        $objectStmt->execute([$projectId]);
        $objects = [];
        foreach (enrich_rollout_objects($db, $objectStmt->fetchAll()) as $object) $objects[(int)$object['id']] = $object;

        $serviceStmt->execute([$providerId, $projectId]);
        $projectServices = enrich_support_services($db, $serviceStmt->fetchAll());
        $deliveryMap = project_service_delivery_map($db, $projectId, $projectServices);
        $services = [];
        foreach ($projectServices as $service) {
            $delivery = $deliveryMap[(int)$service['id']] ?? ['regional' => [], 'central' => [], 'rv_required' => true];
            if (!service_applies_to_provider($service, $provider, $classObject) || !$delivery['rv_required']) continue;
            $service['regional_objects'] = array_values(array_filter(array_map(static fn($id) => $objects[$id]['name'] ?? null, $delivery['regional'])));
            $service['central_objects'] = array_values(array_filter(array_map(static fn($id) => $objects[$id]['name'] ?? null, $delivery['central'])));
            $services[(int)$service['id']] = $service;
        }
        uasort($services, static fn($a, $b) => strnatcmp(export_class_rank($a, 'RV'), export_class_rank($b, 'RV')) ?: strnatcasecmp((string)$a['name'], (string)$b['name']));

        // Ergänzbar: regionale Katalogleistungen, die zur Projektklasse passen und noch nicht im Projekt sind
        $inProject = [];
        foreach ($projectServices as $service) $inProject[(int)$service['id']] = true;
        $catalog = [];
        foreach ($standardServices as $service) {
            if (isset($inProject[(int)$service['id']]) || !service_regional_capable($service) || ($service['default_delivery'] ?? 'regional') === 'central') continue;
            if (!service_applies_to_provider($service, $provider, $classObject)) continue;
            $catalog[(int)$service['id']] = $service;
        }
        uasort($catalog, static fn($a, $b) => strnatcmp(export_class_rank($a, 'RV'), export_class_rank($b, 'RV')) ?: strnatcasecmp((string)$a['name'], (string)$b['name']));

        $result[$projectId] = ['project' => $project, 'contact' => $contact, 'objects' => $objects, 'services' => $services, 'catalog' => $catalog];
    }
    return $result;
}

/** Werte einer Zeile prüfen: [offered, delivery_mode, delivery_partner, schedule, note]. */
function rv_view_cell_values(array $cell, array $provider, array $service, array $rvNames, string $context): array {
    $note = mb_substr(trim((string)($cell['note'] ?? '')), 0, 2000);
    $schedule = !empty($service['schedule_relevant']) ? mb_substr(trim((string)($cell['schedule'] ?? '')), 0, 200) : '';
    if (empty($service['requires_delivery_source'])) return [!empty($cell['offered']) ? 1 : 0, '', '', $schedule, $note];
    $mode = normalize_delivery_mode((string)($cell['mode'] ?? ''));
    $partner = '';
    if ($mode === 'other_rv') {
        $partner = trim((string)($cell['partner_rv'] ?? ''));
        if ($partner === '' || !in_array($partner, $rvNames, true) || $partner === $provider['name']) throw new RuntimeException($context . ': Bei „Anderer Regionalverband“ bitte einen anderen Regionalverband auswählen.');
    } elseif ($mode === 'external') {
        $partner = mb_substr(trim((string)($cell['partner_external'] ?? '')), 0, 200);
        if ($partner === '') throw new RuntimeException($context . ': Bei „Externer Dienstleister“ bitte den Dienstleister angeben.');
    }
    $offered = in_array($mode, ['self', 'other_rv', 'external'], true) ? 1 : 0;
    return [$offered, $mode, $partner, $schedule, $note];
}

/** Angabe des Regionalverbands zu einer Rolloutleistung schreiben (leere Angabe = keine Zeile). */
function rv_view_write_cell(PDO $db, int $projectId, int $serviceId, int $providerId, array $values): void {
    $db->prepare('DELETE FROM project_support_matrix WHERE project_id=? AND support_service_id=? AND provider_id=?')->execute([$projectId, $serviceId, $providerId]);
    [$offered, $mode, $partner, $schedule, $note] = $values;
    if (!$offered && $mode === '' && $partner === '' && $schedule === '' && $note === '') return;
    $db->prepare('INSERT INTO project_support_matrix(project_id,support_service_id,provider_id,offered,delivery_mode,delivery_partner,schedule,note) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$projectId, $serviceId, $providerId, $offered, $mode, $partner, $schedule, $note]);
}

/**
 * Neue Zeile der RV-Sicht übernehmen: Katalogleistung ins Projekt holen oder eine zusätzliche
 * (nicht standardisierte) Rolloutleistung anlegen – wie „Neue Rolloutleistung“ in der RV-Datei.
 * Ohne ausgewählte Rolloutobjekte gilt die Leistung für alle passenden Rolloutobjekte.
 */
function rv_view_add_entry(PDO $db, array $projectData, array $provider, array $entry, array $rvNames): bool {
    $projectId = (int)$projectData['project']['id'];
    $projectName = (string)$projectData['project']['name'];
    $choice = (string)($entry['service'] ?? '');
    $name = mb_substr(trim((string)($entry['name'] ?? '')), 0, 200);
    $hasValues = $name !== '' || trim((string)($entry['description'] ?? '')) !== '' || normalize_delivery_mode((string)($entry['mode'] ?? '')) !== ''
        || trim((string)($entry['schedule'] ?? '')) !== '' || trim((string)($entry['note'] ?? '')) !== '';
    if ($choice === '' || ($choice === 'custom' && !$hasValues)) return false; // leere Zeile
    $classified = array_filter($projectData['objects'], static fn(array $object): bool => in_array($object['functional_class'] ?? null, ['A', 'B', 'C', 'D'], true));
    if (!$classified) throw new RuntimeException('Projekt „' . $projectName . '“: Ohne Rolloutobjekt mit Rolloutklasse Bankfachlich kann keine Leistung ergänzt werden.');
    $chosen = array_values(array_intersect_key($classified, array_flip(array_map('intval', (array)($entry['objects'] ?? [])))));

    if ($choice === 'custom') {
        if ($name === '') throw new RuntimeException('Projekt „' . $projectName . '“: Bitte eine Bezeichnung für die zusätzliche Leistung eingeben.');
        $targets = $chosen ?: array_values($classified);
        $stmt = $db->prepare('SELECT * FROM support_services WHERE is_standard=0 AND project_id=? AND name=?');
        $stmt->execute([$projectId, $name]);
        $existing = $stmt->fetch();
        if ($existing) {
            $service = enrich_support_services($db, [$existing])[0];
            if (!in_array('RV', (array)$service['provider_targets'], true)) throw new RuntimeException('Projekt „' . $projectName . '“: Die zusätzliche Leistung „' . $name . '“ gibt es schon, aber nicht für die Regionalverbände.');
        } else {
            // Niedrigste Klasse der gewählten Rolloutobjekte, damit die Leistung an allen gilt (Klassen sind kumulativ)
            $classes = array_map(static fn(array $object): string => (string)$object['functional_class'], $targets);
            sort($classes);
            $description = trim((string)($entry['description'] ?? '')) ?: 'In der RV-Sicht ergänzt (' . $provider['name'] . ').';
            $db->prepare("INSERT INTO support_services(name,description,is_standard,project_id,default_delivery,schedule_relevant,active,sort_order) VALUES(?,?,0,?,'regional',1,1,0)")
                ->execute([$name, mb_substr($description, 0, 2000), $projectId]);
            $serviceId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO support_service_classes(support_service_id,dimension,class_code) VALUES(?,'functional',?)")->execute([$serviceId, $classes[0]]);
            $db->prepare("INSERT INTO support_service_provider_targets(support_service_id,provider_type) VALUES(?,'RV')")->execute([$serviceId]);
            $stmt = $db->prepare('SELECT * FROM support_services WHERE id=?');
            $stmt->execute([$serviceId]);
            $service = enrich_support_services($db, [$stmt->fetch()])[0];
        }
    } else {
        $service = $projectData['catalog'][(int)$choice] ?? null;
        if (!$service) throw new RuntimeException('Projekt „' . $projectName . '“: Die gewählte Katalogleistung passt nicht zum Projekt.');
        $targets = $chosen ?: array_values($classified);
    }

    $serviceId = (int)$service['id'];
    $targets = array_values(array_filter($targets, static fn(array $object): bool => service_applies_to_rollout_object($service, $object)));
    if (!$targets) throw new RuntimeException('Projekt „' . $projectName . '“: „' . $service['name'] . '“ passt zu keinem der gewählten Rolloutobjekte (Rolloutklasse prüfen).');
    $db->prepare('INSERT OR IGNORE INTO project_services(project_id,support_service_id) VALUES(?,?)')->execute([$projectId, $serviceId]);
    $usage = $db->prepare('INSERT OR IGNORE INTO project_service_rollout_objects(project_id,support_service_id,rollout_object_id) VALUES(?,?,?)');
    foreach ($targets as $object) $usage->execute([$projectId, $serviceId, (int)$object['id']]);
    $values = rv_view_cell_values($entry, $provider, $service, $rvNames, 'Projekt „' . $projectName . '“, „' . $service['name'] . '“');
    rv_view_write_cell($db, $projectId, $serviceId, (int)$provider['id'], $values);
    return true;
}

/** Speichern der RV-Sicht. $payload kommt als JSON aus dem Formular. Rückgabe: [geänderte Projekte, neue Leistungen]. */
function rv_view_save(PDO $db, int $providerId, array $payload): array {
    $stmt = $db->prepare("SELECT * FROM providers WHERE id=? AND type='RV' AND active=1");
    $stmt->execute([$providerId]);
    $provider = $stmt->fetch();
    if (!$provider) throw new RuntimeException('Der Regionalverband wurde nicht gefunden oder ist inaktiv.');
    $rvNames = array_map('strval', $db->query("SELECT name FROM providers WHERE type='RV' AND active=1")->fetchAll(PDO::FETCH_COLUMN));
    $data = rv_view_data($db, $provider);

    $db->beginTransaction();
    $originBefore = origin_snapshot($db);
    $contactUpsert = $db->prepare('INSERT INTO project_provider_contacts(project_id,provider_id,contact_name,contact_phone) VALUES(?,?,?,?) ON CONFLICT(project_id,provider_id) DO UPDATE SET contact_name=excluded.contact_name,contact_phone=excluded.contact_phone,updated_at=CURRENT_TIMESTAMP');
    $contactDelete = $db->prepare('DELETE FROM project_provider_contacts WHERE project_id=? AND provider_id=?');
    $added = 0;
    foreach ((array)($payload['projects'] ?? []) as $projectId => $projectPayload) {
        $projectId = (int)$projectId;
        if (!isset($data[$projectId]) || !is_array($projectPayload)) continue;
        $projectData = $data[$projectId];
        if (array_key_exists('contact_name', $projectPayload) || array_key_exists('contact_phone', $projectPayload)) {
            $contactName = mb_substr(trim((string)($projectPayload['contact_name'] ?? '')), 0, 200);
            $contactPhone = mb_substr(trim((string)($projectPayload['contact_phone'] ?? '')), 0, 100);
            if ($contactName === '' && $contactPhone === '') $contactDelete->execute([$projectId, $providerId]);
            else $contactUpsert->execute([$projectId, $providerId, $contactName, $contactPhone]);
        }
        foreach ((array)($projectPayload['cells'] ?? []) as $serviceId => $cell) {
            $service = $projectData['services'][(int)$serviceId] ?? null;
            if (!$service || !is_array($cell)) continue;
            $values = rv_view_cell_values($cell, $provider, $service, $rvNames, 'Projekt „' . $projectData['project']['name'] . '“, „' . $service['name'] . '“');
            rv_view_write_cell($db, $projectId, (int)$serviceId, $providerId, $values);
        }
        $projectAdded = 0;
        foreach ((array)($projectPayload['new'] ?? []) as $entry) if (is_array($entry) && rv_view_add_entry($db, $projectData, $provider, $entry, $rvNames)) $projectAdded++;
        if ($projectAdded) sync_always_included_services($db, null, $projectId);
        $added += $projectAdded;
    }
    apply_origins($db, $originBefore, static fn(): string => 'RV-Sicht ' . $provider['name']);
    if (!empty($payload['mark_done'])) rv_import_status_mark($db, $providerId, 'RV-Sicht (von Hand)', (string)($payload['received_from'] ?? ''));
    $db->commit();
    return [$provider, $added];
}

// ---------------------------------------------------------------------------------------------
// RV-Sicht: Darstellung
// ---------------------------------------------------------------------------------------------

/** Auswahl Bereitstellungsart mit Partnerfeldern; Felder tragen data-f statt name (Übertragung als JSON). */
function render_rv_view_delivery_fields(array $provider, array $rvProviders, string $mode, string $partner): void {
    $modes = ['' => 'Noch offen', 'self' => 'Selbst', 'other_rv' => 'Anderer RV', 'external' => 'Externer DL', 'none' => 'Keine Bereitstellung'];
    ?>
    <select class="form-select form-select-sm delivery-mode" data-f="mode" aria-label="Bereitstellungsart">
      <?php foreach ($modes as $value => $label): ?><option value="<?=$value?>" <?=$mode === $value ? 'selected' : ''?>><?=e($label)?></option><?php endforeach; ?>
    </select>
    <div class="mt-1" data-partner="other_rv" <?=$mode === 'other_rv' ? '' : 'hidden'?>>
      <select class="form-select form-select-sm" data-f="partner_rv" aria-label="Welcher Regionalverband?">
        <option value="">– Welcher RV? –</option>
        <?php foreach ($rvProviders as $rv): if ((int)$rv['id'] === (int)$provider['id']) continue; ?><option value="<?=e($rv['name'])?>" <?=$mode === 'other_rv' && $partner === $rv['name'] ? 'selected' : ''?>><?=e($rv['name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="mt-1" data-partner="external" <?=$mode === 'external' ? '' : 'hidden'?>>
      <input class="form-control form-control-sm" data-f="partner_external" value="<?=$mode === 'external' ? e($partner) : ''?>" placeholder="Welcher Dienstleister?" aria-label="Externer Dienstleister">
    </div>
<?php
}

function render_rv_view(PDO $db, int $providerId): void {
    $rvProviders = $db->query("SELECT * FROM providers WHERE type='RV' AND active=1 ORDER BY sort_order,name")->fetchAll();
    if (!$rvProviders) { echo '<div class="alert alert-light border">Es sind keine aktiven Regionalverbände angelegt.</div>'; return; }
    $provider = null;
    foreach ($rvProviders as $rv) if ((int)$rv['id'] === $providerId) $provider = $rv;
    $provider ??= $rvProviders[0];
    $data = rv_view_data($db, $provider);
    $status = null;
    foreach (rv_import_status_rows($db) as $row) if ($row['id'] === (int)$provider['id']) $status = $row;
    $serviceCount = array_sum(array_map(static fn(array $p): int => count($p['services']), $data));
    $openCount = 0;
    foreach ($data as $projectData) foreach ($projectData['services'] as $service) if (!empty($service['requires_delivery_source']) && normalize_delivery_mode((string)$service['cell_mode']) === '') $openCount++;
    ?>
<div class="project-matrix-page rv-view-page">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
      <h1 class="page-title h2 mb-1">RV-Sicht: <?=e($provider['name'])?></h1>
      <p class="text-secondary mb-0">Alle Rolloutleistungen, zu denen der Regionalverband gefragt wird – über alle Projekte. Für Antworten aus einem Termin, per E-Mail oder pauschal für alles, ohne die Excel-Datei zu befüllen. Gespeichert wird wie beim Import der RV-Datei.</p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2" data-rv-switch>
      <input type="hidden" name="page" value="rv-view">
      <label class="small text-secondary text-nowrap" for="rv-switch">Regionalverband</label>
      <select class="form-select" id="rv-switch" name="provider_id">
        <?php foreach ($rvProviders as $rv): ?><option value="<?=$rv['id']?>" <?=(int)$rv['id'] === (int)$provider['id'] ? 'selected' : ''?>><?=e($rv['name'])?></option><?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="alert <?=$status && $status['done'] ? 'alert-success' : 'alert-light border'?> small d-flex flex-wrap gap-2 align-items-center">
    <?php if ($status && $status['done']): ?>
      <strong>Rückmeldung übernommen</strong><span><?=e(trim(format_date($status['date'] ?: null, '') . ($status['from'] !== '' ? ' · von ' . $status['from'] : '') . ($status['way'] !== '' ? ' · ' . $status['way'] : ''), ' ·'))?></span>
    <?php else: ?>
      <strong>Rückmeldung noch nicht als übernommen vermerkt.</strong>
    <?php endif; ?>
    <a class="ms-auto" href="?page=import#rv-stand">Stand der Rückmeldungen</a>
  </div>

  <div class="card p-3 mb-3 rv-view-toolbar">
    <div class="row g-3 align-items-end">
      <div class="col-md-4 col-xl-3">
        <label class="form-label small text-secondary mb-1" for="rv-filter-project">Projekt</label>
        <select class="form-select form-select-sm" id="rv-filter-project">
          <option value="">Alle Projekte (<?=count($data)?>)</option>
          <?php foreach ($data as $projectId => $projectData): ?><option value="<?=$projectId?>"><?=e($projectData['project']['name'])?> (<?=count($projectData['services'])?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-xl-2">
        <label class="form-check form-switch mb-1 small"><input class="form-check-input" type="checkbox" id="rv-filter-open"><span class="form-check-label">Nur offene Leistungen</span></label>
        <label class="form-check form-switch mb-0 small"><input class="form-check-input" type="checkbox" id="rv-filter-empty" checked><span class="form-check-label">Projekte ohne Leistungen ausblenden</span></label>
      </div>
      <div class="col-md-5 col-xl-7">
        <label class="form-label small text-secondary mb-1">Pauschale Antwort für alle angezeigten, noch offenen Leistungen</label>
        <div class="d-flex flex-wrap gap-2">
          <div style="min-width:12rem" data-bulk-fields><?php render_rv_view_delivery_fields($provider, $rvProviders, 'self', ''); ?></div>
          <input class="form-control form-control-sm" style="max-width:14rem" id="rv-bulk-schedule" placeholder="Termin / Zeitraum (optional)">
          <button type="button" class="btn btn-sm btn-outline-dark align-self-start" id="rv-bulk-apply">Auf offene anwenden</button>
        </div>
      </div>
    </div>
    <div class="small text-secondary mt-2"><?=$serviceCount?> Leistungen, davon <span id="rv-open-count"><?=$openCount?></span> noch offen. „Pauschal“ füllt nur Felder, die noch leer sind; gespeichert wird erst mit „Speichern“.</div>
  </div>
  <div class="delivery-legend mb-3"><span class="delivery-key delivery-self">Selbst</span><span class="delivery-key delivery-other">Anderer RV</span><span class="delivery-key delivery-external">Externer DL</span><span class="delivery-key delivery-none">Keine Bereitstellung</span><span class="delivery-key delivery-open">Noch offen</span></div>

  <form method="post" id="rv-view-form">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="save_rv_view">
    <input type="hidden" name="provider_id" value="<?=(int)$provider['id']?>">
    <input type="hidden" name="payload" id="rv-view-payload">
    <div class="vstack gap-3">
    <?php foreach ($data as $projectId => $projectData):
        $project = $projectData['project'];
        $classified = array_filter($projectData['objects'], static fn(array $o): bool => in_array($o['functional_class'] ?? null, ['A', 'B', 'C', 'D'], true));
    ?>
      <section class="card p-3 rv-project" data-project="<?=$projectId?>" data-service-count="<?=count($projectData['services'])?>">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-2">
          <div>
            <h2 class="h5 mb-1"><?=e($project['name'])?></h2>
            <div class="small text-secondary">
              <?php if ($project['project_lead'] !== ''): ?>Projektleitung: <?=e($project['project_lead'])?> · <?php endif; ?>
              <?php if ($project['rollout_lead'] !== ''): ?>TPL Rollout: <?=e($project['rollout_lead'])?> · <?php endif; ?>
              Rolloutobjekte: <?=$projectData['objects'] ? e(implode(', ', array_map(static fn(array $o): string => $o['name'] . ($o['functional_class'] ? ' (' . $o['functional_class'] . ')' : ''), $projectData['objects']))) : 'keine'?>
            </div>
          </div>
          <div class="d-flex gap-2 align-items-end" data-contact>
            <div><label class="form-label small text-secondary mb-0">Ansprechpartner <?=e($provider['name'])?></label><input class="form-control form-control-sm" data-f="contact_name" value="<?=e($projectData['contact']['contact_name'])?>"></div>
            <div><label class="form-label small text-secondary mb-0">Telefonnummer</label><input class="form-control form-control-sm" data-f="contact_phone" value="<?=e($projectData['contact']['contact_phone'])?>"></div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle mb-2 rv-view-table">
            <thead><tr><th>Rolloutleistung</th><th>Genutzt für</th><th>Bereitstellung</th><th>Termin / Zeitraum</th><th>Bemerkung</th></tr></thead>
            <tbody data-rows>
            <?php if (!$projectData['services']): ?>
              <tr data-empty><td colspan="5" class="small text-secondary">Keine Leistung, zu der <?=e($provider['name'])?> gefragt wird.</td></tr>
            <?php endif; ?>
            <?php foreach ($projectData['services'] as $serviceId => $service):
                $mode = normalize_delivery_mode((string)$service['cell_mode']);
                $useDelivery = !empty($service['requires_delivery_source']);
                $origin = origin_text($service['cell_origin'], $service['cell_origin_at']);
            ?>
              <tr data-cell="<?=$serviceId?>" data-open="<?=$useDelivery && $mode === '' ? '1' : '0'?>">
                <td>
                  <strong><?=e($service['name'])?></strong>
                  <span class="badge rounded-pill text-bg-<?=$service['is_standard'] ? 'secondary' : 'warning'?>"><?=$service['is_standard'] ? 'Standard' : 'zusätzlich'?></span>
                  <div class="service-class-badges mt-1"><?=service_class_badges($service)?></div>
                  <?php if ($service['description'] !== ''): ?><div class="small text-secondary mt-1"><?=e($service['description'])?></div><?php endif; ?>
                  <?php if ($origin !== ''): ?><div class="small text-secondary mt-1 fst-italic"><?=e($origin)?></div><?php endif; ?>
                </td>
                <td class="small"><?=$service['regional_objects'] ? e(implode(', ', $service['regional_objects'])) : '<span class="text-secondary">alle (noch keinem Rolloutobjekt zugeordnet)</span>'?>
                  <?php if ($service['central_objects']): ?><div class="text-secondary">zentral an: <?=e(implode(', ', $service['central_objects']))?></div><?php endif; ?></td>
                <td class="<?=$useDelivery ? 'delivery-cell' : ''?>" <?=$useDelivery ? 'data-delivery-cell data-delivery-state="' . e($mode ?: 'open') . '"' : ''?>>
                  <?php if ($useDelivery): render_rv_view_delivery_fields($provider, $rvProviders, $mode, (string)$service['cell_partner']);
                  else: ?><label class="form-check mb-0"><input class="form-check-input matrix-check" type="checkbox" data-f="offered" value="1" <?=$service['cell_offered'] ? 'checked' : ''?>><span class="form-check-label small">angeboten</span></label><?php endif; ?>
                </td>
                <td><?php if (!empty($service['schedule_relevant'])): ?><input class="form-control form-control-sm" data-f="schedule" value="<?=e((string)$service['cell_schedule'])?>" placeholder="z. B. Oktober 2026" aria-label="Termin / Zeitraum"><?php else: ?><span class="small text-secondary">nicht relevant</span><?php endif; ?></td>
                <td><textarea class="form-control form-control-sm" rows="1" data-f="note" aria-label="Bemerkung"><?=e((string)$service['cell_note'])?></textarea></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tbody data-new-rows></tbody>
          </table>
        </div>
        <?php if ($classified): ?>
          <button type="button" class="btn btn-sm btn-outline-dark align-self-start" data-add-row>+ Weitere Leistung ergänzen</button>
          <template data-new-template>
            <tr data-new>
              <td>
                <select class="form-select form-select-sm mb-1" data-f="service" aria-label="Leistung">
                  <option value="custom">Eigene Leistung (nicht im Standard)</option>
                  <?php if ($projectData['catalog']): ?><optgroup label="Aus dem Standard-Leistungskatalog">
                    <?php foreach ($projectData['catalog'] as $catalogId => $catalogService): ?><option value="<?=$catalogId?>"><?=e(export_class_rank($catalogService, 'RV') . ' · ' . $catalogService['name'])?></option><?php endforeach; ?>
                  </optgroup><?php endif; ?>
                </select>
                <div data-custom-fields>
                  <input class="form-control form-control-sm mb-1" data-f="name" placeholder="Bezeichnung der Leistung" maxlength="200" aria-label="Bezeichnung">
                  <textarea class="form-control form-control-sm" rows="1" data-f="description" placeholder="Beschreibung (optional)" aria-label="Beschreibung"></textarea>
                </div>
                <button type="button" class="btn btn-link btn-sm text-danger p-0 mt-1" data-remove-row>Zeile entfernen</button>
              </td>
              <td class="small">
                <?php foreach ($classified as $object): ?><label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-object="<?=$object['id']?>"><span class="form-check-label"><?=e($object['name'])?> (<?=e($object['functional_class'])?>)</span></label><?php endforeach; ?>
                <div class="text-secondary mt-1">Keins angehakt = alle passenden</div>
              </td>
              <td class="delivery-cell" data-delivery-cell data-delivery-state="open"><?php render_rv_view_delivery_fields($provider, $rvProviders, '', ''); ?></td>
              <td><input class="form-control form-control-sm" data-f="schedule" placeholder="z. B. Oktober 2026" aria-label="Termin / Zeitraum"></td>
              <td><textarea class="form-control form-control-sm" rows="1" data-f="note" aria-label="Bemerkung"></textarea></td>
            </tr>
          </template>
        <?php else: ?>
          <div class="small text-secondary">Weitere Leistungen lassen sich ergänzen, sobald ein Rolloutobjekt eine Rolloutklasse Bankfachlich hat.</div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
    </div>

    <div class="card p-3 mt-3 rv-view-save">
      <div class="d-flex flex-wrap gap-3 align-items-center">
        <button class="btn btn-primary">Speichern</button>
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="rv-mark-done" <?=$status && $status['done'] ? '' : 'checked'?>><span class="form-check-label">Im Stand der Rückmeldungen als übernommen vermerken (Datum heute)</span></label>
        <input class="form-control form-control-sm" style="max-width:16rem" id="rv-received-from" value="<?=e($status['from'] ?? '')?>" placeholder="Erhalten von (optional)" aria-label="Erhalten von">
      </div>
      <div class="small text-secondary mt-2">Gespeichert werden alle Projekte dieses Regionalverbands, auch ausgefilterte. Leere neue Zeilen werden ignoriert.</div>
    </div>
  </form>
</div>
<script>
(() => {
  const form = document.getElementById('rv-view-form');
  const switcher = document.querySelector('[data-rv-switch] select');
  switcher.addEventListener('change', () => {
    if (form.dataset.dirty === '1' && !confirm('Es gibt ungespeicherte Änderungen. Trotzdem wechseln?')) { switcher.form.reset(); return; }
    switcher.form.submit();
  });

  // Bereitstellungsart: Partnerfeld und Farbe
  const updateDelivery = container => {
    const mode = container.querySelector('[data-f="mode"]');
    if (!mode) return;
    container.querySelectorAll('[data-partner]').forEach(el => { el.hidden = el.dataset.partner !== mode.value; });
    const cell = container.closest('[data-delivery-cell]');
    if (cell) cell.dataset.deliveryState = mode.value || 'open';
  };
  document.addEventListener('change', event => {
    if (event.target.matches('[data-f="mode"]')) updateDelivery(event.target.closest('[data-delivery-cell], [data-bulk-fields]'));
    if (event.target.matches('[data-new] [data-f="service"]')) toggleCustom(event.target.closest('[data-new]'));
  });
  const toggleCustom = row => { row.querySelector('[data-custom-fields]').hidden = row.querySelector('[data-f="service"]').value !== 'custom'; };

  // Filter
  const filterProject = document.getElementById('rv-filter-project');
  const filterOpen = document.getElementById('rv-filter-open');
  const filterEmpty = document.getElementById('rv-filter-empty');
  const isOpen = row => { const mode = row.querySelector('[data-f="mode"]'); return !!mode && mode.value === ''; };
  const refresh = () => {
    document.getElementById('rv-open-count').textContent = [...form.querySelectorAll('tr[data-cell]')].filter(isOpen).length;
    form.querySelectorAll('.rv-project').forEach(section => {
      let visibleRows = 0;
      section.querySelectorAll('tr[data-cell]').forEach(row => {
        row.hidden = filterOpen.checked && !isOpen(row) && row.dataset.open !== '1';
        if (!row.hidden) visibleRows++;
      });
      const wanted = !filterProject.value || filterProject.value === section.dataset.project;
      const hasContent = visibleRows > 0 || section.querySelector('tr[data-new]');
      section.hidden = !wanted || (filterEmpty.checked && !hasContent && !filterProject.value);
    });
  };
  [filterProject, filterOpen, filterEmpty].forEach(el => el.addEventListener('change', () => {
    // „Nur offene“ merkt sich beim Einschalten, welche Zeilen offen sind, damit sie beim Ausfüllen nicht verschwinden
    if (el === filterOpen) form.querySelectorAll('tr[data-cell]').forEach(row => { row.dataset.open = isOpen(row) ? '1' : '0'; });
    refresh();
  }));
  form.addEventListener('change', () => {
    form.dataset.dirty = '1';
    document.getElementById('rv-open-count').textContent = [...form.querySelectorAll('tr[data-cell]')].filter(isOpen).length;
  });

  // Pauschale Antwort für alle angezeigten offenen Leistungen
  document.getElementById('rv-bulk-apply').addEventListener('click', () => {
    const bulk = document.querySelector('[data-bulk-fields]');
    const mode = bulk.querySelector('[data-f="mode"]').value;
    const partnerRv = bulk.querySelector('[data-f="partner_rv"]').value;
    const partnerExternal = bulk.querySelector('[data-f="partner_external"]').value.trim();
    const schedule = document.getElementById('rv-bulk-schedule').value.trim();
    if (mode === 'other_rv' && !partnerRv) { alert('Bitte den anderen Regionalverband auswählen.'); return; }
    if (mode === 'external' && !partnerExternal) { alert('Bitte den externen Dienstleister angeben.'); return; }
    let changed = 0;
    form.querySelectorAll('.rv-project:not([hidden]) tr[data-cell]:not([hidden])').forEach(row => {
      const select = row.querySelector('[data-f="mode"]');
      if (!select || select.value !== '') return;
      select.value = mode;
      if (mode === 'other_rv') row.querySelector('[data-f="partner_rv"]').value = partnerRv;
      if (mode === 'external') row.querySelector('[data-f="partner_external"]').value = partnerExternal;
      const scheduleField = row.querySelector('[data-f="schedule"]');
      if (schedule && scheduleField && scheduleField.value.trim() === '') scheduleField.value = schedule;
      updateDelivery(select.closest('[data-delivery-cell]'));
      changed++;
    });
    if (changed) form.dataset.dirty = '1';
    refresh();
    alert(changed ? changed + ' offene Leistung(en) ausgefüllt. Bitte noch speichern.' : 'Keine offene Leistung in der aktuellen Anzeige.');
  });

  // Neue Zeilen
  form.querySelectorAll('[data-add-row]').forEach(button => button.addEventListener('click', () => {
    const section = button.closest('.rv-project');
    const row = section.querySelector('[data-new-template]').content.firstElementChild.cloneNode(true);
    section.querySelector('[data-new-rows]').appendChild(row);
    toggleCustom(row);
    row.querySelector('[data-f="name"]').focus();
    form.dataset.dirty = '1';
  }));
  form.addEventListener('click', event => {
    if (event.target.matches('[data-remove-row]')) { event.target.closest('tr').remove(); refresh(); }
  });

  // Übertragung als ein JSON-Feld (viele Eingabefelder würden sonst an PHP-Grenzen stoßen)
  const fieldValues = (element, keys) => {
    const values = {};
    element.querySelectorAll('[data-f]').forEach(field => {
      const key = field.dataset.f;
      if (keys && !keys.includes(key)) return;
      values[key] = field.type === 'checkbox' ? (field.checked ? 1 : 0) : field.value;
    });
    return values;
  };
  // Vorab prüfen, damit bei einem Fehler keine Eingaben verloren gehen
  const problem = () => {
    for (const row of form.querySelectorAll('tr[data-cell], tr[data-new]')) {
      const mode = row.querySelector('[data-f="mode"]');
      const name = row.querySelector('[data-f="name"]');
      const service = row.querySelector('[data-f="service"]');
      if (mode && mode.value === 'other_rv' && !row.querySelector('[data-f="partner_rv"]').value) return [row.querySelector('[data-f="partner_rv"]'), 'Bitte bei „Anderer RV“ den Regionalverband auswählen.'];
      if (mode && mode.value === 'external' && !row.querySelector('[data-f="partner_external"]').value.trim()) return [row.querySelector('[data-f="partner_external"]'), 'Bitte bei „Externer DL“ den Dienstleister angeben.'];
      if (service && service.value === 'custom' && !name.value.trim() && [...row.querySelectorAll('[data-f]')].some(f => f !== service && f !== name && f.value.trim() !== '' && !(f.matches('select') && f.value === ''))) return [name, 'Bitte eine Bezeichnung für die ergänzte Leistung eingeben (oder die Zeile entfernen).'];
    }
    return null;
  };
  form.addEventListener('submit', event => {
    const found = problem();
    if (found) {
      event.preventDefault();
      const section = found[0].closest('.rv-project');
      filterProject.value = ''; filterOpen.checked = false; section.hidden = false; found[0].closest('tr').hidden = false;
      refresh();
      found[0].scrollIntoView({ block: 'center' }); found[0].focus();
      alert(found[1]);
      return;
    }
    const payload = { projects: {}, mark_done: document.getElementById('rv-mark-done').checked ? 1 : 0, received_from: document.getElementById('rv-received-from').value };
    form.querySelectorAll('.rv-project').forEach(section => {
      const project = fieldValues(section.querySelector('[data-contact]'));
      project.cells = {};
      section.querySelectorAll('tr[data-cell]').forEach(row => { project.cells[row.dataset.cell] = fieldValues(row); });
      project.new = [...section.querySelectorAll('tr[data-new]')].map(row => {
        const entry = fieldValues(row);
        entry.objects = [...row.querySelectorAll('[data-object]:checked')].map(box => box.dataset.object);
        return entry;
      });
      payload.projects[section.dataset.project] = project;
    });
    document.getElementById('rv-view-payload').value = JSON.stringify(payload);
    form.dataset.dirty = '';
  });
  window.addEventListener('beforeunload', event => { if (form.dataset.dirty === '1') { event.preventDefault(); event.returnValue = ''; } });

  refresh();
})();
</script>
<?php
}
