<?php
// monitor_data.php - Archivo para obtener datos de monitoreo en tiempo real
header('Content-Type: application/json');

// Incluir archivos de configuración y funciones
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$url = $_GET['stream'] ?? null;

if (!$url) {
    echo json_encode(['error' => 'URL no especificada.']);
    exit;
}

$meta     = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
$buffered = file_exists($buffered_file) ? json_decode(file_get_contents($buffered_file), true) : [];

if (!isset($buffered[$url])) {
    echo json_encode(['error' => 'Stream no encontrado.']);
    exit;
}

$buffered_info = $buffered[$url];
$buffered_start_time = $buffered_info['timestamp'] ?? 0;
$stream_name = $meta[$url]['stream_name'] ?? basename($url);

// Calcular tiempo de actividad
$uptime_seconds = time() - $buffered_start_time;
$stream_uptime  = format_uptime($uptime_seconds);

// Calcular tráfico total
$total_traffic_bytes = get_buffered_directory_size(dirname(__DIR__ . '/../' . $buffered_info['buffered_url']));
$total_traffic_kb    = number_format($total_traffic_bytes / 1024, 2) . ' KB';
if ($total_traffic_bytes > 1024 * 1024) {
    $total_traffic_kb = number_format($total_traffic_bytes / (1024 * 1024), 2) . ' MB';
}

// Obtener los logs filtrados
$logs_for_link = get_stream_logs($url, $buffered_info['buffered_url'], $log_file);
$logs_output = implode("\n", $logs_for_link);

echo json_encode([
    'uptime'  => $stream_uptime,
    'traffic' => $total_traffic_kb,
    'logs'    => $logs_output
]);
?>
