<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
<script>
    $(function(){
        // Inicializar tooltips
        $('[data-toggle="tooltip"]').tooltip({boundary:'window', trigger: 'hover'});

        // Manejar el botón de copiar
        $('.copy-btn').click(function(){
            var urlToCopy = $(this).data('url');
            navigator.clipboard.writeText(urlToCopy).then(function() {
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

            originalPlayer.ready(function() {
                originalPlayer.load();
                originalPlayer.play();
            });

            bufferedPlayer.ready(function() {
                bufferedPlayer.load();
                bufferedPlayer.play();
            });
        }

        // Función para actualizar el monitoreo
        function updateMonitoringData() {
            var urlParam = new URLSearchParams(window.location.search).get('buffer');
            if (urlParam) {
                $.get('include/monitor_data.php?stream=' + encodeURIComponent(urlParam), function(data) {
                    if (data && data.uptime) {
                        $('#stream-uptime').text(data.uptime);
                        $('#stream-traffic').text(data.traffic);
                        $('#stream-logs').text(data.logs);
                    }
                });
            }
        }
        
        // Ejecutar la actualización al cargar y cada 5 segundos si el parámetro 'buffer' existe
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('buffer')) {
             updateMonitoringData(); // Actualizar inmediatamente
             setInterval(updateMonitoringData, 5000);
        }
    });
</script>
</body>
</html>
