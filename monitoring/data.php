<?php
declare(strict_types=1);
define('HADIRIN_AJAX', true);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();

function monitoring_build_query(array $get): array
{
    $where = [];
    $params = [];

    $eventId = (int) ($get['event_id'] ?? 0);
    $groupName = trim((string) ($get['group_name'] ?? ''));
    $guestName = trim((string) ($get['guest_name'] ?? ''));
    $status = trim((string) ($get['status'] ?? ''));
    $dateFrom = trim((string) ($get['date_from'] ?? ''));
    $dateTo = trim((string) ($get['date_to'] ?? ''));

    if ($eventId > 0) {
        $where[] = 'ev.id = :event_id';
        $params['event_id'] = $eventId;
    }
    if ($groupName !== '') {
        $where[] = 'gg.group_name LIKE :group_name';
        $params['group_name'] = '%' . $groupName . '%';
    }
    if ($guestName !== '') {
        $where[] = 'g.full_name LIKE :guest_name';
        $params['guest_name'] = '%' . $guestName . '%';
    }
    if ($status === 'present') {
        $where[] = 'g.checkin_at IS NOT NULL';
    } elseif ($status === 'absent') {
        $where[] = 'g.checkin_at IS NULL';
    }
    if ($dateFrom !== '') {
        $where[] = 'g.checkin_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = 'g.checkin_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    return [$where, $params];
}

[$where, $params] = monitoring_build_query($_GET);
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$statsSql = "SELECT
        COUNT(*) AS total_guests,
        SUM(CASE WHEN g.checkin_at IS NOT NULL THEN 1 ELSE 0 END) AS total_present
    FROM guests g
    JOIN guest_groups gg ON gg.id = g.group_id
    JOIN events ev ON ev.id = gg.event_id
    {$whereSql}";
$statsStmt = $db->prepare($statsSql);
$statsStmt->execute($params);
$statsRow = $statsStmt->fetch();
$totalGuests = (int) $statsRow['total_guests'];
$totalPresent = (int) $statsRow['total_present'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$pagination = paginate_calc($totalGuests, $page, 15);

$sql = "SELECT g.id, g.full_name, g.checkin_at, gg.group_name, ev.event_name
        FROM guests g
        JOIN guest_groups gg ON gg.id = g.group_id
        JOIN events ev ON ev.id = gg.event_id
        {$whereSql}
        ORDER BY (g.checkin_at IS NULL), g.checkin_at DESC, g.id ASC
        LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

json_response([
    'success' => true,
    'stats' => [
        'total_guests'  => $totalGuests,
        'total_present' => $totalPresent,
        'total_absent'  => $totalGuests - $totalPresent,
    ],
    'pagination' => [
        'page'        => $pagination['page'],
        'per_page'    => $pagination['per_page'],
        'total'       => $pagination['total'],
        'total_pages' => $pagination['total_pages'],
    ],
    'rows' => array_map(function ($r) {
        return [
            'full_name'  => $r['full_name'],
            'event_name' => $r['event_name'],
            'group_name' => $r['group_name'],
            'checkin_at' => $r['checkin_at'] ? format_datetime($r['checkin_at']) : null,
            'status'     => $r['checkin_at'] ? 'Sudah Hadir' : 'Belum Hadir',
        ];
    }, $rows),
]);
