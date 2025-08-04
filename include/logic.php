<?php
// logic.php - Contiene la lógica principal de manejo de peticiones (POST)

// Cargar datos existentes o inicializar vacíos
$meta     = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
$buffered = file_exists($buffered_file) ? json_decode(file_get_contents($buffered_file), true) : [];

// Variables para la sección de comparación y logs
$current_buffered_url         = '';
$original_stream_to_play      = '';
$original_stream_name_to_play = '';
$logs_for_link                = [];
$ffmpeg_command_executed      = '';

// Variables para pre-llenar el formulario en caso de "Modificar"
$form_stream_url    = '';
$form_stream_name   = '';
$form_buffer_type   = 'hls';
$form_audio_codec   = 'copy';
$form_audio_bitrate = '';
$form_resolution    = 'auto';
$form_fps           = 0;
$form_gop           = 0;
$form_enable_err    = false;
$form_hls_time      = 4;

// --- Lógica de Manejo de Solicitudes POST (CRUD y Buffering) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $url     = trim($_POST['stream_url'] ?? '');

    // Parámetros avanzados
    $stream_name   = trim($_POST['stream_name'] ?? '') ?: basename($url);
    $buffer_name   = trim($_POST['buffer_name'] ?? '') ?: sanitizeFileName($stream_name);
    $buffer_type   = ($_POST['buffer_type'] ?? 'hls') === 'srt' ? 'srt' : 'hls';
    $hls_time      = intval($_POST['hls_time'] ?? 4);
    $audio_codec   = $_POST['audio_codec'] ?? 'copy';
    $audio_bitrate = trim($_POST['audio_bitrate'] ?? '') ?: '';
    $resolution    = $_POST['resolution'] ?? 'auto';
    $fps           = intval($_POST['fps'] ?? 0);
    $gop           = intval($_POST['gop'] ?? 0);
    $enable_err    = isset($_POST['enable_err_detect']);

    // Validar URL
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        echo "<div class='alert alert-danger'>Error: La URL del stream es inválida o está vacía.</div>";
        exit;
    }

    // Acción: Guardar o Modificar
    if ($action === 'save' || $action === 'modify') {
        $list = file_exists($link_file) ? file($link_file, FILE_IGNORE_NEW_LINES) : [];
        if (!in_array($url, $list)) {
            $list[] = $url;
        }
        file_put_contents($link_file, implode("\n", array_unique(array_filter($list))));

        $meta[$url] = [
            'stream_name'   => $stream_name,
            'buffer_name'   => $buffer_name,
            'buffer_type'   => $buffer_type,
            'hls_time'      => $hls_time,
            'audio_codec'   => $audio_codec,
            'audio_bitrate' => $audio_bitrate,
            'resolution'    => $resolution,
            'fps'           => $fps,
            'gop'           => $gop,
            'enable_err'    => $enable_err
        ];
        file_put_contents($meta_file, json_encode($meta, JSON_PRETTY_PRINT));

        if ($action === 'modify') {
            header('Location: index.php?edit=' . urlencode($url));
            exit;
        }
        $action = 'buffer';
    }

    // Acción: Bufferizar o Testear
    if ($action === 'buffer' || $action === 'test') {
        if (isset($meta[$url])) {
            $cfg = $meta[$url];
        } else {
            $cfg = [
                'stream_name'   => $stream_name,
                'buffer_name'   => $buffer_name,
                'buffer_type'   => $buffer_type,
                'hls_time'      => $hls_time,
                'audio_codec'   => $audio_codec,
                'audio_bitrate' => $audio_bitrate,
                'resolution'    => $resolution,
                'fps'           => $fps,
                'gop'           => $gop,
                'enable_err'    => $enable_err
            ];
        }

        $cfg['stream_name']   = $cfg['stream_name'] ?? basename($url);
        $cfg['buffer_name']   = $cfg['buffer_name'] ?? sanitizeFileName($cfg['stream_name']);
        $cfg['buffer_type']   = $cfg['buffer_type'] ?? 'hls';
        $cfg['hls_time']      = intval($cfg['hls_time'] ?? 4);
        $cfg['audio_codec']   = $cfg['audio_codec'] ?? 'copy';
        $cfg['audio_bitrate'] = $cfg['audio_bitrate'] ?? '';
        $cfg['resolution']    = $cfg['resolution'] ?? 'auto';
        $cfg['fps']           = intval($cfg['fps'] ?? 0);
        $cfg['gop']           = intval($cfg['gop'] ?? 0);
        $cfg['enable_err']    = boolval($cfg['enable_err'] ?? false);

        // --- Lógica para reutilizar el directorio ---
        $dir_name = null;
        if (isset($buffered[$url])) {
             // Si ya existe un buffer para esta URL, usamos su directorio y matamos el proceso anterior
            $dir_name = basename(dirname(__DIR__ . '/../' . $buffered[$url]['buffered_url']));
            $old_pid_file = dirname(__DIR__ . '/../' . $buffered[$url]['buffered_url']) . '/ffmpeg.pid';
            if (file_exists($old_pid_file)) {
                $pid = (int) file_get_contents($old_pid_file);
                if ($pid > 0) {
                    shell_exec("kill -9 $pid > /dev/null 2>&1");
                }
            }
        } else {
            // Si no, creamos un nuevo nombre con la marca de tiempo
            $timestamp = time();
            $clean_name = sanitizeFileName($cfg['buffer_name']);
            $dir_name = "buffer_{$clean_name}_{$timestamp}";
        }
        
        $outDir = __DIR__ . "/../$dir_name";
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        // Opciones FFmpeg
        $input_options = "-fflags +genpts -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 -rw_timeout 3000000 -thread_queue_size 512 -probesize 5000000 -analyzeduration 5000000";
        $audio_opts = ($cfg['audio_codec'] !== 'copy') ? sprintf('-c:a %s %s', escapeshellarg($cfg['audio_codec']), !empty($cfg['audio_bitrate']) ? sprintf('-b:a %s', escapeshellarg($cfg['audio_bitrate'])) : '') : '-c:a copy';
        $video_opts = '-c:v copy';
        if ($cfg['resolution'] !== 'auto' && strpos($cfg['resolution'], 'x') !== false) {
            list($w, $h) = array_map('intval', explode('x', $cfg['resolution']));
            $video_opts = sprintf('-vf scale=%d:%d', $w, $h);
        }
        if ($cfg['fps'] > 0) $video_opts .= sprintf(' -r %d', $cfg['fps']);
        if ($cfg['gop'] > 0) $video_opts .= sprintf(' -g %d', $cfg['gop']);
        
        $err_opts = ($cfg['enable_err']) ? '-err_detect aggressive -fflags +discardcorrupt' : '';
        $buffered_url_web_path = '';

        if ($cfg['buffer_type'] === 'hls') {
            $output_file = escapeshellarg("{$outDir}/playlist.m3u8");
            $cmd = sprintf(
                "nohup ffmpeg %s %s -i %s %s %s -f hls -hls_time %d -hls_list_size 5 -hls_flags delete_segments+round_durations -hls_segment_filename '%s/seg_%%03d.ts' %s >> %s 2>&1 & echo $! > %s",
                $input_options,
                $err_opts,
                escapeshellarg($url),
                $audio_opts,
                $video_opts,
                $cfg['hls_time'],
                escapeshellarg($outDir),
                $output_file,
                escapeshellarg($log_file),
                escapeshellarg("{$outDir}/ffmpeg.pid")
            );
            $buffered_url_web_path = "http://{$_SERVER['HTTP_HOST']}/{$dir_name}/playlist.m3u8";
        } else {
             $output_file = escapeshellarg("{$outDir}/stream.ts");
             $cmd = sprintf(
                 "nohup ffmpeg %s %s -i %s %s %s -f mpegts %s >> %s 2>&1 & echo $! > %s",
                 $input_options,
                 $err_opts,
                 escapeshellarg($url),
                 $audio_opts,
                 $video_opts,
                 $output_file,
                 escapeshellarg($log_file),
                 escapeshellarg("{$outDir}/ffmpeg.pid")
             );
             $buffered_url_web_path = "http://{$_SERVER['HTTP_HOST']}/{$dir_name}/stream.ts";
        }

        shell_exec($cmd);
        $ffmpeg_command_executed = $cmd;

        $buffered[$url] = [
            'buffered_url' => str_replace(__DIR__ . '/../', '', "{$outDir}/playlist.m3u8"),
            'timestamp'    => time(), // <--- CORRECCIÓN CLAVE
            'buffer_type'  => $cfg['buffer_type'],
            'web_url'      => $buffered_url_web_path,
            'last_command' => $cmd
        ];
        file_put_contents($buffered_file, json_encode($buffered, JSON_PRETTY_PRINT));
        header('Location: index.php?buffer=' . urlencode($url));
        exit;
    }

    // Acción: Eliminar
    if ($action === 'delete') {
        if (isset($buffered[$url])) {
            $buffer_info = $buffered[$url];
            $dir_to_delete = dirname(__DIR__ . '/../' . $buffer_info['buffered_url']);
            // Matar el proceso de ffmpeg antes de borrar la carpeta
            $pid_file = "{$dir_to_delete}/ffmpeg.pid";
            if (file_exists($pid_file)) {
                $pid = (int) file_get_contents($pid_file);
                if ($pid > 0) {
                     shell_exec("kill -9 $pid > /dev/null 2>&1");
                }
            }
            if (is_dir($dir_to_delete)) {
                array_map('unlink', glob("$dir_to_delete/*"));
                @rmdir($dir_to_delete);
            }
        }
        unset($meta[$url], $buffered[$url]);

        file_put_contents($meta_file, json_encode($meta, JSON_PRETTY_PRINT));
        file_put_contents($buffered_file, json_encode($buffered, JSON_PRETTY_PRINT));

        $list = array_filter(file($link_file, FILE_IGNORE_NEW_LINES) ?: [], fn($l) => $l !== $url);
        file_put_contents($link_file, implode("\n", $list));

        header('Location: index.php');
        exit;
    }
}

// Lógica para pre-llenar el formulario en modo "Modificar" o "Editar"
if (isset($_GET['edit']) && isset($meta[$_GET['edit']])) {
    $edit_url = $_GET['edit'];
    $data_to_edit = $meta[$edit_url];
    $form_stream_url    = $edit_url;
    $form_stream_name   = $data_to_edit['stream_name'] ?? '';
    $form_buffer_type   = $data_to_edit['buffer_type'] ?? 'hls';
    $form_audio_codec   = $data_to_edit['audio_codec'] ?? 'copy';
    $form_audio_bitrate = $data_to_edit['audio_bitrate'] ?? '';
    $form_resolution    = $data_to_edit['resolution'] ?? 'auto';
    $form_fps           = $data_to_edit['fps'] ?? 0;
    $form_gop           = $data_to_edit['gop'] ?? 0;
    $form_enable_err    = $data_to_edit['enable_err'] ?? false;
    $form_hls_time      = $data_to_edit['hls_time'] ?? 4;
}

// Lógica para mostrar la sección de comparación y logs
if (isset($_GET['buffer']) && isset($buffered[$_GET['buffer']])) {
    $u = $_GET['buffer'];
    $original_stream_to_play      = $u;
    $buffered_info                = $buffered[$u];
    $current_buffered_url         = $buffered_info['web_url'];
    $original_stream_name_to_play = $meta[$u]['stream_name'] ?? basename($u);

    $buffered_start_time     = $buffered_info['timestamp'] ?? 0;
    $ffmpeg_command_executed = $buffered_info['last_command'] ?? '';

    // Lógica para calcular el tiempo de actividad
    $stream_uptime = 'Iniciando...';
    if ($buffered_start_time > 0) {
        $uptime_seconds = time() - $buffered_start_time;
        $stream_uptime  = format_uptime($uptime_seconds);
    }

    // Lógica para calcular el tráfico total
    $total_traffic_kb = 'No disponible';
    $total_traffic_bytes = get_buffered_directory_size(dirname(__DIR__ . '/../' . $buffered_info['buffered_url']));
    if ($total_traffic_bytes > 0) {
        $total_traffic_kb = number_format($total_traffic_bytes / 1024, 2) . ' KB';
        if ($total_traffic_bytes > 1024 * 1024) {
            $total_traffic_kb = number_format($total_traffic_bytes / (1024 * 1024), 2) . ' MB';
        }
    }
    
    // Filtrar logs de FFmpeg para esta URL
    $logs_for_link = get_stream_logs($u, $buffered_info['buffered_url'], $log_file);
}
?>
