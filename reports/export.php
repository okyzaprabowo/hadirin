<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();

$groupId = input_int($_GET, 'group_id', 0);
$eventFilter = input_int($_GET, 'event_id', 0);
$status = input_str($_GET, 'status');
$dateFrom = input_str($_GET, 'date_from');
$dateTo = input_str($_GET, 'date_to');

$where = [];
$params = [];
if ($groupId > 0) {
    $where[] = 'g.group_id = :group_id';
    $params['group_id'] = $groupId;
}
if ($eventFilter > 0) {
    $where[] = 'ev.id = :event_id';
    $params['event_id'] = $eventFilter;
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
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT g.full_name, g.phone, gg.group_name, ev.event_name, g.checkin_at,
               (SELECT u.full_name FROM checkin_logs cl JOIN users u ON u.id = cl.scanned_by
                WHERE cl.guest_id = g.id AND cl.action = 'checkin' ORDER BY cl.scanned_at DESC LIMIT 1) AS officer_name
        FROM guests g
        JOIN guest_groups gg ON gg.id = g.group_id
        JOIN events ev ON ev.id = gg.event_id
        {$whereSql}
        ORDER BY ev.event_name ASC, gg.group_name ASC, g.full_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

audit_log($db, (int) current_user()['id'], 'export_csv', 'report', null, 'Export laporan kehadiran ke CSV (' . count($rows) . ' baris)');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="laporan_kehadiran_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders correctly

fputcsv($output, ['Nama Tamu', 'No. HP', 'Nama Event', 'Nama Grup', 'Status', 'Waktu Check-in', 'Petugas']);

foreach ($rows as $r) {
    fputcsv($output, [
        csv_safe_field($r['full_name']),
        csv_safe_field($r['phone'] ?? ''),
        csv_safe_field($r['event_name']),
        csv_safe_field($r['group_name']),
        $r['checkin_at'] ? 'Sudah Hadir' : 'Belum Hadir',
        csv_safe_field($r['checkin_at'] ? format_datetime($r['checkin_at'], 'Y-m-d H:i:s') : ''),
        csv_safe_field($r['officer_name'] ?? ''),
    ]);
}

fclose($output);
exit;
