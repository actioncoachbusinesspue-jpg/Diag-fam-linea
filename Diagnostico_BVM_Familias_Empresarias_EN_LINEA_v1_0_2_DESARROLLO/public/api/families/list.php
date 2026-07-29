<?php
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('GET');
bvm_require_admin_api(false); // lectura: sesión requerida, sin CSRF

$includeArchived = ($_GET['archivadas'] ?? '') === '1';
$rows = FamilyRepository::listAll($includeArchived);
$base = bvm_base_url();

$families = array_map(function (array $f) use ($base) {
    $expected = $f['expected_participants'] !== null ? (int)$f['expected_participants'] : null;
    $finished = (int)$f['finished_count'];
    $capacity = FamilyRepository::capacity($f, (int)$f['registered_count']);
    return [
        'id' => (int)$f['id'],
        'family_name' => $f['family_name'],
        'public_slug' => $f['public_slug'],
        'status' => $f['status'],
        'expected_participants' => $expected,
        'enforce_participant_limit' => $capacity['enforce_participant_limit'],
        'capacity' => $capacity,
        'registered_count' => (int)$f['registered_count'],
        'finished_count' => $finished,
        'progress_pct' => $expected ? (int)round(100 * $finished / max(1, $expected)) : null,
        'opens_at' => $f['opens_at'],
        'closes_at' => $f['closes_at'],
        'created_at' => $f['created_at'],
        'last_activity_at' => $f['last_activity_at'],
        'invite_url' => $base . '/participar.php?f=' . $f['public_slug'],
    ];
}, $rows);

bvm_json_response(['ok' => true, 'families' => $families]);
