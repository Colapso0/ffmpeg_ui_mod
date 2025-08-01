// include/diagnosis.php
<?php
// diagnosis.php - Sección de diagnóstico y logs
?>
<hr>
<h4 class="mt-4 mb-3">🛠️ Diagnóstico y Comparativa de Stream</h4>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stats-card">
            <h6>Tiempo de Funcionamiento</h6>
            <p><?= $stream_uptime ?></p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card">
            <h6>Tráfico (Tamaño de Buffer)</h6>
            <p><?= $total_traffic_kb ?></p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card">
            <h6>Estado del Stream</h6>
            <p>activo</p>
        </div>
    </div>
</div>

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
