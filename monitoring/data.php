<?php
declare(strict_types=1);
define('HADIRIN_AJAX', true);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();

$eventId = input_int($_GET, 'event_id', 0);
if ($eventId <= 0) {
    $defaultEvent = $db->query(
        "SELECT id FROM events WHERE status = 'active' ORDER BY event_date ASC, id ASC LIMIT 1"
    )->fetch();
    $eventId = $defaultEvent ? (int) $defaultEvent['id'] : 0;
}

$search = trim((string) ($_GET['search'] ?? ''));
$filterSessionId = (int) ($_GET['session_id'] ?? 0);
$filterStatus = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));

$sessions = [];
if ($eventId > 0) {
    $sessionsStmt = $db->prepare(
        'SELECT id, name FROM presensi_sessions WHERE event_id = :event_id ORDER BY sort_order ASC, id ASC'
    );
    $sessionsStmt->execute(['event_id' => $eventId]);
    $sessions = $sessionsStmt->fetchAll();
}

$where = ['gg.event_id = :event_id'];
$params = ['event_id' => $eventId];

if ($search !== '') {
    $where[] = '(gg.group_name LIKE :search OR gg.contact_name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($filterSessionId > 0 && in_array($filterStatus, ['present', 'absent'], true)) {
    if ($filterStatus === 'present') {
        $where[] = 'EXISTS (SELECT 1 FROM session_attendance sa2
                            WHERE sa2.group_id = gg.id AND sa2.presensi_session_id = :filter_session_id
                            AND sa2.checked_in_at IS NOT NULL)';
    } else {
        $where[] = 'NOT EXISTS (SELECT 1 FROM session_attendance sa2
                                WHERE sa2.group_id = gg.id AND sa2.presensi_session_id = :filter_session_id
                                AND sa2.checked_in_at IS NOT NULL)';
    }
    $params['filter_session_id'] = $filterSessionId;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM guest_groups gg {$whereSql}");
$countStmt->execute($params);
$totalGroups = (int) $countStmt->fetchColumn();

$pagination = paginate_calc($totalGroups, $page, 15);

$sql = "SELECT gg.id, gg.group_name, gg.contact_name, gg.quota
        FROM guest_groups gg
        {$whereSql}
        ORDER BY gg.group_name ASC
        LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$groups = $stmt->fetchAll();

$attendanceMap = [];
if ($groups) {
    $groupIds = array_map(fn($g) => (int) $g['id'], $groups);
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $attStmt = $db->prepare(
        "SELECT group_id, presensi_session_id, checked_in_at FROM session_attendance WHERE group_id IN ({$placeholders})"
    );
    $attStmt->execute($groupIds);
    foreach ($attStmt->fetchAll() as $row) {
        $attendanceMap[(int) $row['group_id']][(int) $row['presensi_session_id']] = $row['checked_in_at'];
    }
}

$totalSessionCount = count($sessions);
$rows = array_map(function ($g) use ($attendanceMap, $sessions, $totalSessionCount) {
    $groupAttendance = $attendanceMap[(int) $g['id']] ?? [];
    $present = 0;
    $sessionStatus = [];
    foreach ($sessions as $s) {
        $checkedAt = $groupAttendance[(int) $s['id']] ?? null;
        if ($checkedAt !== null) {
            $present++;
        }
        $sessionStatus[] = [
            'session_id'    => (int) $s['id'],
            'checked_in_at' => $checkedAt ? format_datetime($checkedAt) : null,
        ];
    }
    return [
        'group_id'       => (int) $g['id'],
        'group_name'     => $g['group_name'],
        'contact_name'   => $g['contact_name'],
        'quota'          => (int) $g['quota'],
        'sessions'       => $sessionStatus,
        'total_present'  => $present,
        'total_sessions' => $totalSessionCount,
    ];
}, $groups);

$statsStmt = $db->prepare(
    "SELECT COUNT(*) AS total_slots, SUM(CASE WHEN sa.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS total_checked
     FROM session_attendance sa
     JOIN presensi_sessions ps ON ps.id = sa.presensi_session_id
     WHERE ps.event_id = :event_id"
);
$statsStmt->execute(['event_id' => $eventId]);
$statsRow = $statsStmt->fetch();
$totalSlots = (int) ($statsRow['total_slots'] ?? 0);
$totalChecked = (int) ($statsRow['total_checked'] ?? 0);

$perSessionStmt = $db->prepare(
    "SELECT ps.id, ps.name, COUNT(sa.id) AS total, SUM(CASE WHEN sa.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in
     FROM presensi_sessions ps
     LEFT JOIN session_attendance sa ON sa.presensi_session_id = ps.id
     WHERE ps.event_id = :event_id
     GROUP BY ps.id, ps.name, ps.sort_order
     ORDER BY ps.sort_order ASC, ps.id ASC"
);
$perSessionStmt->execute(['event_id' => $eventId]);
$perSessionStats = $perSessionStmt->fetchAll();

json_response([
    'success'  => true,
    'event_id' => $eventId,
    'sessions' => array_map(fn($s) => ['id' => (int) $s['id'], 'name' => $s['name']], $sessions),
    'rows'     => $rows,
    'pagination' => [
        'page'        => $pagination['page'],
        'per_page'    => $pagination['per_page'],
        'total'       => $pagination['total'],
        'total_pages' => $pagination['total_pages'],
    ],
    'stats' => [
        'total_groups'      => $totalGroups,
        'total_slots'       => $totalSlots,
        'total_checked'     => $totalChecked,
        'total_not_checked' => $totalSlots - $totalChecked,
    ],
    'per_session_stats' => array_map(fn($s) => [
        'name'       => $s['name'],
        'total'      => (int) $s['total'],
        'checked_in' => (int) $s['checked_in'],
    ], $perSessionStats),
]);
