<?php
// help_panel.php - Panel de ayuda colapsable
?>
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
