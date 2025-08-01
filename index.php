<?php
// index.php - Dashboard Streaming para Empresas - HLS/SRT, Opciones Avanzadas, Comparativa y Diagnóstico

// Incluir archivos de configuración y lógica
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/logic.php';

// Cargar datos existentes o inicializar vacíos
$meta       = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
$buffered   = file_exists($buffered_file) ? json_decode(file_get_contents($buffered_file), true) : [];

// Variables para la sección de comparación y logs
$current_buffered_url         = '';
$original_stream_to_play      = '';
$original_stream_name_to_play = '';
$logs_for_link                = [];
$ffmpeg_command_executed      = '';
$buffered_start_time          = 0;
$stream_uptime                = 'No disponible';
$total_traffic_kb             = 'No disponible';

// Lógica para pre-llenar el formulario en modo "Modificar" o "Editar"
if (isset($_GET['edit']) && isset($meta[$_GET['edit']])) {
    $edit_url = $_GET['edit'];
    $data_to_edit = $meta[$edit_url];
    $form_stream_url      = $edit_url;
    $form_stream_name     = $data_to_edit['stream_name'] ?? '';
    $form_buffer_type     = $data_to_edit['buffer_type'] ?? 'hls';
    $form_audio_codec     = $data_to_edit['audio_codec'] ?? 'copy';
    $form_audio_bitrate   = $data_to_edit['audio_bitrate'] ?? '';
    $form_resolution      = $data_to_edit['resolution'] ?? 'auto';
    $form_fps             = $data_to_edit['fps'] ?? 0;
    $form_gop             = $data_to_edit['gop'] ?? 0;
    $form_enable_err      = $data_to_edit['enable_err'] ?? false;
    $form_hls_time        = $data_to_edit['hls_time'] ?? 4;
}

// Lógica para mostrar la sección de comparación y logs (con las nuevas funciones)
if (isset($_GET['buffer']) && isset($buffered[$_GET['buffer']])) {
    $u = $_GET['buffer'];
    $original_stream_to_play      = $u;
    $buffered_info                = $buffered[$u];
    $current_buffered_url         = $buffered_info['web_url'];
    $original_stream_name_to_play = $meta[$u]['stream_name'] ?? basename($u);
    $buffered_start_time          = $buffered_info['timestamp'] ?? 0;
    $ffmpeg_command_executed      = $buffered_info['last_command'] ?? '';

    // Lógica para calcular el tiempo de actividad
    if ($buffered_start_time > 0) {
        $uptime_seconds = time() - $buffered_start_time;
        $stream_uptime  = format_uptime($uptime_seconds);
    }

    // Lógica para calcular el tráfico total
    $total_traffic_bytes = get_buffered_directory_size(dirname(__DIR__ . '/../' . $buffered_info['buffered_url']));
    $total_traffic_kb    = number_format($total_traffic_bytes / 1024, 2) . ' KB';
    if ($total_traffic_bytes > 1024 * 1024) {
        $total_traffic_kb = number_format($total_traffic_bytes / (1024 * 1024), 2) . ' MB';
    }

    // Filtrar logs de FFmpeg para esta URL
    $logs_for_link = get_stream_logs($u, $buffered_info['buffered_url'], $log_file);
}

// Incluir la cabecera HTML
require_once __DIR__ . '/include/head.php';
?>
<body>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>🚀 Dashboard de Gestión de Streaming</h3>
        <button class="btn btn-info" data-toggle="collapse" data-target="#helpPanel" aria-expanded="false" aria-controls="helpPanel" title="Ver ayuda">?</button>
    </div>

    <?php require_once __DIR__ . '/include/help_panel.php'; ?>
    <?php require_once __DIR__ . '/include/forms.php'; ?>
    <?php require_once __DIR__ . '/include/table.php'; ?>

    <?php if ($current_buffered_url): ?>
        <?php require_once __DIR__ . '/include/diagnosis.php'; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/include/footer.php'; ?>
</body>
</html>
