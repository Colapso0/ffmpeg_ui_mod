<?php
// table.php - Tabla de streams configurados
?>
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
            $valid_links = array_unique(array_filter($links_in_file));
            $i = 0;
            foreach ($valid_links as $url):
                $i++;
                $d = $meta[$url] ?? [];
                $b = $buffered[$url] ?? [];
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
