<?php
// functions.php - Funciones de ayuda
function sanitizeFileName($name) {
    $name = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    $name = trim($name, '_-');
    return $name;
}

function format_uptime($seconds) {
    if ($seconds < 0) return 'Iniciando...';
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    $diff = $dtF->diff($dtT);
    $format = '';
    if ($diff->y > 0) $format .= '%y años, ';
    if ($diff->m > 0) $format .= '%m meses, ';
    if ($diff->d > 0) $format .= '%d días, ';
    if ($diff->h > 0) $format .= '%h horas, ';
    if ($diff->i > 0) $format .= '%i minutos, ';
    $format .= '%s segundos';
    
    return $diff->format(trim($format, ', '));
}

function get_buffered_directory_size($dir) {
    $size = 0;
    if (is_dir($dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $size += $fileinfo->getSize();
        }
    }
    return $size;
}

function get_stream_logs($url, $buffered_url_path, $log_file) {
    $logs = [];
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $lines = explode("\n", $log_content);
        $dir_name = basename(dirname(__DIR__ . '/../' . $buffered_url_path));

        // Filtrar líneas relevantes con palabras clave
        $keywords = ['hls sync failed', 'reconnect', 'error', 'bitrate', 'Active input'];
        foreach ($lines as $line) {
            // Solo procesamos las líneas si son del stream actual
            if (strpos($line, $url) !== false || strpos($line, $dir_name) !== false) {
                foreach ($keywords as $keyword) {
                    if (strpos(strtolower($line), strtolower($keyword)) !== false) {
                        $logs[] = htmlspecialchars($line);
                        break;
                    }
                }
            }
        }
        $logs = array_slice($logs, -100); // Mostrar las últimas 100 líneas relevantes
    }
    return $logs;
}
