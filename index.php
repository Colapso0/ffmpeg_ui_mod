<?php
// index.php - Dashboard Streaming para Empresas - HLS/SRT, Opciones Avanzadas, Comparativa y Diagnóstico

// Archivos de configuración y datos
$link_file     = __DIR__ . '/link_list';          // Lista de URLs de streams
$meta_file     = __DIR__ . '/stream_meta.json';    // Metadatos de cada stream (nombre, configs, etc.)
$buffered_file = __DIR__ . '/buffered_streams.json'; // Información de streams actualmente bufferizados
$log_file      = __DIR__ . '/ffmpeg.log';          // Log unificado de comandos FFmpeg

// Cargar datos existentes o inicializar vacíos
$meta     = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
$buffered = file_exists($buffered_file) ? json_decode(file_get_contents($buffered_file), true) : [];

// Variables para la sección de comparación y logs
$current_buffered_url         = '';
$original_stream_to_play      = '';
$original_stream_name_to_play = '';
$logs_for_link                = [];
$ffmpeg_command_executed      = ''; // Para mostrar el comando FFmpeg exacto

// Variables para pre-llenar el formulario en caso de "Modificar"
$form_stream_url       = '';
$form_stream_name      = '';
$form_buffer_type      = 'hls';
$form_audio_codec      = 'copy';
$form_audio_bitrate    = '';
$form_resolution       = 'auto';
$form_fps              = 0;
$form_gop              = 0;
$form_enable_err       = false;
$form_hls_time         = 4;

// Función para sanitizar nombres de directorios (más robusta)
function sanitizeFileName($name) {
    // Reemplaza caracteres no alfanuméricos por guiones bajos, y elimina guiones bajos múltiples
    $name = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $name); // Permite letras Unicode
    $name = preg_replace('/_+/', '_', $name);              // Elimina guiones bajos repetidos
    $name = trim($name, '_-');                             // Elimina guiones al inicio/fin
    return $name;
}

// --- Lógica de Manejo de Solicitudes POST (CRUD y Buffering) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action        = $_POST['action'] ?? '';
    $url           = trim($_POST['stream_url'] ?? '');

    // Parámetros avanzados (siempre recogemos del POST para la acción de guardar/modificar/bufferizar)
    $stream_name   = trim($_POST['stream_name'] ?? '') ?: basename($url);
    $buffer_name   = trim($_POST['buffer_name'] ?? '') ?: basename($url); // Nuevo campo para nombre de buffer
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

    // Acción: Guardar o Modificar (actualiza los metadatos y la lista de links)
    if ($action === 'save' || $action === 'modify') {
        $list = file_exists($link_file) ? file($link_file, FILE_IGNORE_NEW_LINES) : [];
        if (!in_array($url, $list)) {
            $list[] = $url; // Añadir URL si no existe
        }
        file_put_contents($link_file, implode("\n", array_unique(array_filter($list)))); // Limpiar duplicados y vacíos

        // Guardar metadatos avanzados
        $meta[$url] = [
            'stream_name'   => $stream_name,
            'buffer_name'   => $buffer_name, // Guardar el nombre del buffer
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

        // Si es una modificación desde el botón "Modificar", no bufferizamos de inmediato
        if ($action === 'modify') {
             // Redirige para mostrar el formulario pre-llenado
            header('Location: ' . basename(__FILE__) . '?edit=' . urlencode($url));
            exit;
        }
        // Para "save", continúa al buffer si no es una modificación
        $action = 'buffer';
    }

    // Acción: Bufferizar o Testear
    if ($action === 'buffer' || $action === 'test') {
        // Usamos la configuración guardada en $meta
        if (!isset($meta[$url])) {
            echo "<div class='alert alert-warning'>Advertencia: Metadatos no encontrados para la URL. Usando valores predeterminados.</div>";
            $cfg = [
                'stream_name' => $stream_name,
                'buffer_name' => $buffer_name,
                'buffer_type' => $buffer_type,
                'hls_time' => $hls_time,
                'audio_codec' => $audio_codec,
                'audio_bitrate' => $audio_bitrate,
                'resolution' => $resolution,
                'fps' => $fps,
                'gop' => $gop,
                'enable_err' => $enable_err
            ];
        } else {
            $cfg = $meta[$url];
        }

        // Eliminar buffer anterior si existe para esta URL
        if (isset($buffered[$url])) {
            $old_dir = dirname(__DIR__ . '/' . $buffered[$url]['buffered_url']);
            if (is_dir($old_dir)) {
                array_map('unlink', glob("$old_dir/*"));
                @rmdir($old_dir);
            }
            unset($buffered[$url]);
        }

        $timestamp  = time();
        $clean_name = sanitizeFileName($cfg['buffer_name']);
        $dir_name   = "buffer_{$clean_name}_{$timestamp}";
        $outDir     = __DIR__ . "/$dir_name"; // Directorio de buffers centralizado
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        // Opciones de audio FFmpeg
        $audio_opts = $cfg['audio_codec'] !== 'copy'
            ? sprintf('-c:a %s %s', escapeshellarg($cfg['audio_codec']), $cfg['audio_bitrate'] ? sprintf('-b:a %s', escapeshellarg($cfg['audio_bitrate'])) : '')
            : '-c:a copy';

        // Opciones de video FFmpeg
        $video_opts = '-c:v copy';
        if ($cfg['resolution'] !== 'auto' && strpos($cfg['resolution'], 'x') !== false) {
            list($w, $h) = array_map('intval', explode('x', $cfg['resolution']));
            $video_opts = sprintf('-vf scale=%d:%d', $w, $h);
        }
        if ($cfg['fps'] > 0) $video_opts .= sprintf(' -r %d', $cfg['fps']);
        if ($cfg['gop'] > 0) $video_opts .= sprintf(' -g %d', $cfg['gop']);

        // Opciones de detección de errores FFmpeg
        $err_opts = $cfg['enable_err']
            ? '-err_detect aggressive -fflags +discardcorrupt'
            : '';

        $buffered_url_web_path = ''; // URL accesible desde el navegador

        if ($cfg['buffer_type'] === 'hls') {
            $output_file = escapeshellarg("{$outDir}/playlist.m3u8");
            $cmd = sprintf(
                "nohup ffmpeg -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 2 %s -i %s %s %s -f hls -hls_time %d -hls_list_size 5 -hls_flags delete_segments+round_durations -hls_segment_filename '%s/seg_%%03d.ts' %s >> %s 2>&1 &",
                $err_opts,
                escapeshellarg($url),
                $audio_opts,
                $video_opts,
                $cfg['hls_time'],
                escapeshellarg($outDir),
                $output_file,
                escapeshellarg($log_file)
            );
            $buffered_url_web_path = "http://{$_SERVER['HTTP_HOST']}/{$dir_name}/playlist.m3u8";

        } else { // SRT (o TS en este caso para simular SRT para web)
            $output_file = escapeshellarg("{$outDir}/stream.ts"); // Usamos .ts para compatibilidad web si se visualiza
            $cmd = sprintf(
                "nohup ffmpeg -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 2 %s -i %s %s %s -f mpegts %s >> %s 2>&1 &",
                $err_opts,
                escapeshellarg($url),
                $audio_opts,
                $video_opts,
                $output_file,
                escapeshellarg($log_file)
            );
            // Para SRT, si es para web, aún necesitamos un archivo HTTP, por eso .ts
            $buffered_url_web_path = "http://{$_SERVER['HTTP_HOST']}/{$dir_name}/stream.ts";

        }

        // Ejecutar el comando FFmpeg
        shell_exec($cmd);
        $ffmpeg_command_executed = $cmd; // Guarda el comando para mostrarlo

        // Guardar información del buffer en buffered_streams.json
        $buffered[$url] = [
            'buffered_url' => str_replace(__DIR__ . '/', '', "{$outDir}/playlist.m3u8"), // Path relativo al script para HLS
            'timestamp'    => $timestamp,
            'buffer_type'  => $cfg['buffer_type'],
            'web_url'      => $buffered_url_web_path // URL accesible desde el navegador
        ];
        file_put_contents($buffered_file, json_encode($buffered, JSON_PRETTY_PRINT));

        // Redirección o mostrar resultados
        header('Location: ' . basename(__FILE__) . '?buffer=' . urlencode($url));
        exit;
    }

    // Acción: Eliminar
    if ($action === 'delete') {
        if (isset($buffered[$url])) {
            $buffer_info = $buffered[$url];
            $dir_to_delete = dirname(__DIR__ . '/' . $buffer_info['buffered_url']);
            if (is_dir($dir_to_delete)) {
                array_map('unlink', glob("$dir_to_delete/*")); // Eliminar archivos
                @rmdir($dir_to_delete); // Eliminar directorio (silenciosamente si falla, e.g., no vacío)
            }
        }
        unset($meta[$url], $buffered[$url]); // Eliminar de ambos registros

        // Actualizar archivos JSON
        file_put_contents($meta_file, json_encode($meta, JSON_PRETTY_PRINT));
        file_put_contents($buffered_file, json_encode($buffered, JSON_PRETTY_PRINT));

        // Eliminar de la lista principal (link_list)
        $list = array_filter(file($link_file, FILE_IGNORE_NEW_LINES) ?: [], fn($l) => $l !== $url);
        file_put_contents($link_file, implode("\n", $list));

        header('Location: ' . basename(__FILE__));
        exit;
    }
}

// --- Lógica para pre-llenar el formulario en modo "Modificar" o "Editar" ---
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

// --- Lógica para mostrar la sección de comparación y logs ---
if (isset($_GET['buffer']) && isset($buffered[$_GET['buffer']])) {
    $u = $_GET['buffer'];
    $original_stream_to_play      = $u;
    $buffered_info                = $buffered[$u];
    $current_buffered_url         = $buffered_info['web_url']; // Usamos la URL web
    $original_stream_name_to_play = $meta[$u]['stream_name'] ?? basename($u);

    // Filtrar logs de FFmpeg para esta URL
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        // Buscar líneas que contengan la URL original o el nombre del directorio del buffer
	$url_pattern    = preg_quote($u, '/');
	$dir_name       = basename(dirname($buffered_info['buffered_url']));
 	$dir_pattern    = preg_quote($dir_name, '/');
 	$search_patterns = [
 	    $url_pattern,
            $dir_pattern
        ];
        foreach (explode("\n", $log_content) as $line) {
            foreach ($search_patterns as $pattern) {
                if (preg_match("/$pattern/i", $line)) {
                    $logs_for_link[] = htmlspecialchars($line);
                    break; // Una vez que coincide, pasa a la siguiente línea del log
                }
            }
        }
        $logs_for_link = array_slice($logs_for_link, -200); // Mostrar las últimas 200 líneas relevantes
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Dashboard Streaming - FFmpeg Empresarial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <style>
        body { font-size: 0.9rem; }
        .container { max-width: 1200px; }
        .form-inline .form-control, .form-inline .btn, .form-inline .form-check { margin-bottom: 0.5rem; margin-right: 0.5rem; }
        .table-sm td, .table-sm th { padding: 0.3rem; }
        .table thead th { vertical-align: middle; }
        .text-truncate { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        [data-toggle="tooltip"] { cursor: help; }
        .copy-btn { cursor: pointer; color: #007bff; margin-left: 5px; }
        .copy-btn:hover { text-decoration: underline; }
        pre { background: #f8f9fa; padding: 1rem; max-height: 250px; overflow: auto; border: 1px solid #e9ecef; border-radius: .25rem; font-size: 0.8rem; }
        .player-container { border: 1px solid #dee2e6; border-radius: .25rem; padding: 10px; background-color: #fff; }
        .video-js { margin-top: 10px; }
    </style>
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>🚀 Dashboard de Gestión de Streaming</h3>
        <button class="btn btn-info" data-toggle="collapse" data-target="#helpPanel" aria-expanded="false" aria-controls="helpPanel" title="Ver ayuda">?</button>
    </div>

    <div class="collapse mb-4" id="helpPanel">
        <div class="card card-body bg-light border-info">
            <h5>Guía Rápida de Uso</h5>
            <ul>
                <li><strong>URL Original:</strong> La dirección del stream que deseas procesar (ej. RTMP, HLS, etc.).</li>
                <li><strong>Nombre Stream:</strong> Un nombre amigable para identificar tu stream en la lista.</li>
                <li><strong>Nombre Buffer:</strong> Un nombre único para el directorio de tu buffer. Si está vacío, se usa el nombre del stream.</li>
                <li><strong>Buffer Type:</strong> El formato de salida. <strong>HLS</strong> (recomendado para web) crea segmentos .ts y una playlist .m3u8. <strong>SRT</strong> genera un flujo MPEG-TS en un archivo (no reproducible directamente en web, para usos avanzados con reproductores SRT).</li>
                <li><strong>Audio Codec:</strong> copy mantiene el audio original (sin recodificar). Elige aac, mp3, opus para recodificar el audio y optimizar el tamaño o la compatibilidad.</li>
                <li><strong>Audio Bitrate:</strong> Velocidad de datos para el audio (ej. 128k, 192k). Solo aplica si no usas copy.</li>
                <li><strong>Resolution:</strong> Resolución de salida para el video. auto mantiene la original. Ejemplos: 1920x1080 (1080p), 1280x720 (720p). Recodifica el video si se cambia.</li>
                <li><strong>FPS:</strong> Frames por segundo. 0 (cero) mantiene el original. Menores valores reducen el uso de CPU.</li>
                <li><strong>GOP (Group of Pictures):</strong> Intervalo entre keyframes. Un valor bajo (ej. 2 o 5) puede reducir la latencia, útil para transmisiones en vivo.</li>
                <li><strong>HLS Time:</strong> Duración de cada segmento HLS en segundos. Valores típicos: 2-6.</li>
                <li><strong>Detectar Errores:</strong> Habilita la detección agresiva de errores y el descarte de paquetes corruptos. Útil para fuentes de stream inestables.</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4 p-3 shadow-sm">
        <h5 class="card-title">Configurar Nuevo Stream / Modificar Existente</h5>
        <form method="post" class="form-row align-items-end">
            <div class="col-md-4 mb-2">
                <label for="stream_url">URL Original <span class="text-danger">*</span></label>
                <input type="url" name="stream_url" id="stream_url" class="form-control" placeholder="Ej: rtmp://ejemplo.com/live/streamkey" required value="<?= htmlspecialchars($form_stream_url) ?>" data-toggle="tooltip" title="La URL de origen de tu stream.">
            </div>
            <div class="col-md-3 mb-2">
                <label for="stream_name">Nombre Stream</label>
                <input type="text" name="stream_name" id="stream_name" class="form-control" placeholder="Ej: Mi Canal HD" value="<?= htmlspecialchars($form_stream_name) ?>" data-toggle="tooltip" title="Nombre descriptivo para este stream.">
            </div>
             <div class="col-md-3 mb-2">
                <label for="buffer_name">Nombre Buffer (opcional)</label>
                <input type="text" name="buffer_name" id="buffer_name" class="form-control" placeholder="Ej: buffer_canal_hd" data-toggle="tooltip" title="Nombre para el directorio de los archivos bufferizados. Se generará automáticamente si se deja vacío.">
            </div>
            <div class="col-md-2 mb-2">
                <label for="buffer_type">Tipo Buffer</label>
                <select name="buffer_type" id="buffer_type" class="form-control" data-toggle="tooltip" title="HLS para web, SRT para flujo de transporte.">
                    <option value="hls" <?= $form_buffer_type === 'hls' ? 'selected' : '' ?>>HLS</option>
                    <option value="srt" <?= $form_buffer_type === 'srt' ? 'selected' : '' ?>>SRT (Archivo)</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label for="audio_codec">Codec Audio</label>
                <select name="audio_codec" id="audio_codec" class="form-control" data-toggle="tooltip" title="Codec de audio de salida. 'copy' evita recodificar.">
                    <option value="copy" <?= $form_audio_codec === 'copy' ? 'selected' : '' ?>>copy</option>
                    <option value="aac" <?= $form_audio_codec === 'aac' ? 'selected' : '' ?>>aac</option>
                    <option value="mp3" <?= $form_audio_codec === 'mp3' ? 'selected' : '' ?>>mp3</option>
                    <option value="opus" <?= $form_audio_codec === 'opus' ? 'selected' : '' ?>>opus</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label for="audio_bitrate">Bitrate Audio</label>
                <input type="text" name="audio_bitrate" id="audio_bitrate" class="form-control" placeholder="Ej: 128k" value="<?= htmlspecialchars($form_audio_bitrate) ?>" data-toggle="tooltip" title="Bitrate del audio si se recodifica (ej. 128k, 192k).">
            </div>
            <div class="col-md-2 mb-2">
                <label for="resolution">Resolución Video</label>
                <select name="resolution" id="resolution" class="form-control" data-toggle="tooltip" title="Resolución de salida. 'auto' mantiene la original.">
                    <option value="auto" <?= $form_resolution === 'auto' ? 'selected' : '' ?>>Auto</option>
                    <option value="1920x1080" <?= $form_resolution === '1920x1080' ? 'selected' : '' ?>>1080p (1920x1080)</option>
                    <option value="1280x720" <?= $form_resolution === '1280x720' ? 'selected' : '' ?>>720p (1280x720)</option>
                    <option value="854x480" <?= $form_resolution === '854x480' ? 'selected' : '' ?>>480p (854x480)</option>
                    <option value="640x360" <?= $form_resolution === '640x360' ? 'selected' : '' ?>>360p (640x360)</option>
                </select>
            </div>
            <div class="col-md-1 mb-2">
                <label for="fps">FPS</label>
                <input type="number" name="fps" id="fps" class="form-control" placeholder="0" value="<?= $form_fps ?>" data-toggle="tooltip" title="Frames por segundo. 0 = original.">
            </div>
            <div class="col-md-1 mb-2">
                <label for="gop">GOP</label>
                <input type="number" name="gop" id="gop" class="form-control" placeholder="0" value="<?= $form_gop ?>" data-toggle="tooltip" title="Intervalo de Keyframes (Group of Pictures). Menor GOP = menor latencia.">
            </div>
            <div class="col-md-1 mb-2">
                <label for="hls_time">HLS Seg.</label>
                <input type="number" name="hls_time" id="hls_time" class="form-control" value="<?= $form_hls_time ?>" data-toggle="tooltip" title="Duración de cada segmento HLS en segundos.">
            </div>
            <div class="col-auto mb-2 align-self-center">
                <div class="form-check">
                    <input type="checkbox" name="enable_err_detect" id="errDetect" class="form-check-input" <?= $form_enable_err ? 'checked' : '' ?> data-toggle="tooltip" title="Habilita detección agresiva de errores y descarte de paquetes corruptos.">
                    <label class="form-check-label" for="errDetect">Detectar Errores</label>
                </div>
            </div>
            <div class="col-auto mb-2">
                <button type="submit" name="action" value="save" class="btn btn-primary btn-block">Guardar & Buffer</button>
            </div>
        </form>
    </div>

    <h4 class="mt-4 mb-3">Streams Configurados</h4>
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-sm">
            <thead class="thead-dark text-center">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">URL Original</th>
                    <th style="width: 30%;">Opciones de Buffering</th>
                    <th style="width: 20%;">URL Bufferizada</th>
                    <th style="width: 20%;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $links_in_file = file_exists($link_file) ? file($link_file, FILE_IGNORE_NEW_LINES) : [];
                $valid_links = array_unique(array_filter($links_in_file)); // Asegura que solo haya URLs válidas y únicas
                $i = 0;
                foreach ($valid_links as $url):
                    $i++;
                    $d = $meta[$url] ?? []; // Configuración
                    $b = $buffered[$url] ?? []; // Información del buffer activo
                ?>
                <tr>
                    <td class="text-center"><?= $i ?></td>
                    <td>
                        <small class="d-block font-weight-bold" title="Nombre: <?= htmlspecialchars($d['stream_name'] ?? basename($url)) ?>">
                            <?= htmlspecialchars($d['stream_name'] ?? basename($url)) ?>
                        </small>
                        <small class="text-truncate d-block" style="max-width: 250px;" title="<?= htmlspecialchars($url) ?>">
                            <?= htmlspecialchars($url) ?>
                        </small>
                    </td>
                    <td>
                        <small class="d-block">
                            Tipo: <strong><?= strtoupper($d['buffer_type'] ?? 'HLS') ?></strong>,
                            Audio: <strong><?= $d['audio_codec'] ?? 'copy' ?></strong> (<?= $d['audio_bitrate'] ?? '-' ?>),<br>
                            Video: <strong><?= $d['resolution'] ?? 'auto' ?></strong>,
                            FPS: <strong><?= $d['fps'] ?? '-' ?></strong>,
                            GOP: <strong><?= $d['gop'] ?? '-' ?></strong>,
                            Errores: <strong><?= ($d['enable_err'] ?? false) ? 'ON' : 'OFF' ?></strong>,
                            HLS Time: <strong><?= $d['hls_time'] ?? '-' ?>s</strong>
                        </small>
                    </td>
                    <td>
                        <?php if (!empty($b['web_url'])): ?>
                            <small class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($b['web_url']) ?>">
                                <?= htmlspecialchars(basename($b['web_url'])) ?>
                            </small>
                            <span class="copy-btn" data-url="<?= htmlspecialchars($b['web_url']) ?>" title="Copiar URL">📋</span>
                            <br>
                            <small class="text-muted">Iniciado: <?= date('Y-m-d H:i:s', $b['timestamp']) ?></small>
                        <?php else: ?>
                            <small class="text-muted">No bufferizado</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-info btn-sm mb-1" onclick="location='?buffer=<?= urlencode($url) ?>'" title="Ver comparación y logs">Test</button>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="stream_url" value="<?= htmlspecialchars($url) ?>">
                            <button type="submit" name="action" value="buffer" class="btn btn-success btn-sm mb-1" title="Reiniciar/iniciar buffering con la configuración actual">Buffer Now</button>
                        </form>
                        <button class="btn btn-warning btn-sm mb-1" onclick="location='?edit=<?= urlencode($url) ?>'" title="Cargar configuración al formulario para modificar">Modificar</button>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este stream y sus buffers asociados?');">
                            <input type="hidden" name="stream_url" value="<?= htmlspecialchars($url) ?>">
                            <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm mb-1" title="Eliminar stream y sus archivos bufferizados">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($current_buffered_url): ?>
        <hr>
        <h4 class="mt-4 mb-3">🛠️ Diagnóstico y Comparativa de Stream</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="player-container">
                    <h5 class="text-center">Stream Original (<?= htmlspecialchars($original_stream_name_to_play) ?>)</h5>
                    <video-js id="originalVideo" class="video-js vjs-fluid" controls preload="auto">
                        <source src="<?= htmlspecialchars($original_stream_to_play) ?>" type="application/x-mpegURL">
                        <p class="vjs-no-js">Para ver este video, habilita JavaScript y considera actualizar tu navegador a uno que <a href="https://videojs.com/html5-video-support/" target="_blank">soporte video HTML5</a></p>
                    </video-js>
                    <div class="alert alert-info mt-2">
                        <strong>URL Original:</strong> <small><?= htmlspecialchars($original_stream_to_play) ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="player-container">
                    <h5 class="text-center">Stream Bufferizado (<?= strtoupper($buffered_info['buffer_type']) ?>)</h5>
                    <video-js id="bufferedVideo" class="video-js vjs-fluid" controls preload="auto">
                        <?php if ($buffered_info['buffer_type'] === 'hls'): ?>
                            <source src="<?= htmlspecialchars($current_buffered_url) ?>" type="application/x-mpegURL">
                        <?php else: ?>
                            <p class="vjs-no-js">El tipo SRT genera un archivo de transporte (<?= htmlspecialchars(basename($current_buffered_url)) ?>). No es directamente reproducible en este reproductor web. Requiere un cliente SRT.</p>
                        <?php endif; ?>
                        <p class="vjs-no-js">Para ver este video, habilita JavaScript y considera actualizar tu navegador a uno que <a href="https://videojs.com/html5-video-support/" target="_blank">soporte video HTML5</a></p>
                    </video-js>
                    <div class="alert alert-info mt-2">
                        <strong>URL Bufferizada:</strong> <small><?= htmlspecialchars($current_buffered_url) ?></small>
                        <span class="copy-btn" data-url="<?= htmlspecialchars($current_buffered_url) ?>" title="Copiar URL">📋</span>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="mt-4">Logs de FFmpeg para este Stream</h5>
        <pre class="bg-dark text-white"><?= $logs_for_link ? implode("\n", $logs_for_link) : "No se encontraron entradas de log relevantes para este stream. Asegúrate de que FFmpeg se ejecutó y escribió en el log." ?></pre>
        <?php if (!empty($ffmpeg_command_executed)): ?>
            <h5 class="mt-4">Último Comando FFmpeg Ejecutado:</h5>
            <pre class="bg-light text-dark border"><?= htmlspecialchars($ffmpeg_command_executed) ?></pre>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
<script>
    $(function(){
        // Inicializar tooltips
        $('[data-toggle="tooltip"]').tooltip({boundary:'window', trigger: 'hover'});

        // Manejar el botón de copiar
        $('.copy-btn').click(function(){
            var urlToCopy = $(this).data('url');
            navigator.clipboard.writeText(urlToCopy).then(function() {
                // Cambiar tooltip temporalmente
                var originalTitle = $(this).attr('data-original-title');
                $(this).attr('data-original-title', '¡Copiado!').tooltip('show');
                var $self = $(this);
                setTimeout(function() {
                    $self.tooltip('hide').attr('data-original-title', originalTitle);
                }, 1500);
            }.bind(this)).catch(function(err) {
                console.error('Error al copiar: ', err);
                alert('No se pudo copiar la URL. Por favor, cópiala manualmente: ' + urlToCopy);
            });
        });

        // Asegurarse de que los players de Video.js se reinicien al cambiar la URL (si es el caso)
        if (typeof videojs !== 'undefined') {
            var originalPlayer = videojs('originalVideo');
            var bufferedPlayer = videojs('bufferedVideo');

            // Cargar nueva fuente si es necesario (útil si la URL se actualiza sin recarga completa)
            originalPlayer.ready(function() {
                var currentSrc = originalPlayer.currentSrc();
                if (currentSrc && currentSrc !== '<?= htmlspecialchars($original_stream_to_play) ?>') {
                    originalPlayer.src({
                        src: '<?= htmlspecialchars($original_stream_to_play) ?>',
                        type: 'application/x-mpegURL'
                    });
                    originalPlayer.load();
                    originalPlayer.play();
                }
            });

            bufferedPlayer.ready(function() {
                var currentSrc = bufferedPlayer.currentSrc();
                if (currentSrc && currentSrc !== '<?= htmlspecialchars($current_buffered_url) ?>') {
                    bufferedPlayer.src({
                        src: '<?= htmlspecialchars($current_buffered_url) ?>',
                        type: 'application/x-mpegURL'
                    });
                    bufferedPlayer.load();
                    bufferedPlayer.play();
                }
            });
        }
    });
</script>
</body>
</html>
