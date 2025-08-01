<?php
// forms.php - Formulario para agregar/modificar streams
?>
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
            <input type="text" name="buffer_name" id="buffer_name" class="form-control" placeholder="Ej: buffer_canal_hd" value="<?= htmlspecialchars($form_buffer_name ?? '') ?>" data-toggle="tooltip" title="Nombre para el directorio de los archivos bufferizados. Se generará automáticamente si se deja vacío.">
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
