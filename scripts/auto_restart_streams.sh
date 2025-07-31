#!/bin/bash
# Creamos logs si faltan
mkdir -p /var/www/html/logs

echo "[INFO] Relanzando todos los streams del buffer..."

META_FILE="/var/www/html/stream_meta.json"
if [ ! -f "$META_FILE" ]; then
  echo "[ERROR] No existe $META_FILE"
  exit 1
fi

# Por cada entrada en el JSON
jq -c 'to_entries[]' "$META_FILE" | while read -r entry; do
  URL=$(echo "$entry" | jq -r '.key')
  NAME=$(echo "$entry" | jq -r '.value.buffer_name')
  HLS_TIME=$(echo "$entry" | jq -r '.value.hls_time')

  # Buscamos carpeta exacta con find
  MATCH=$(find /var/www/html -maxdepth 1 -type d -name "buffer_${NAME}_*" | head -n1)

  if [ -n "$MATCH" ]; then
    echo "[INFO] Relanzando buffer '$NAME' en '$MATCH'"
    nohup ffmpeg \
      -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 2 \
      -i "$URL" \
      -c:a copy -c:v copy \
      -f hls \
      -hls_time "${HLS_TIME:-4}" \
      -hls_list_size 5 \
      -hls_flags delete_segments+round_durations \
      -hls_segment_filename "$MATCH/seg_%03d.ts" \
      "$MATCH/playlist.m3u8" \
      >> /var/www/html/logs/${NAME}.log 2>&1 &
  else
    echo "[WARN] No encontré carpeta para buffer '$NAME'"
  fi
done

echo "[INFO] Todos los buffers han sido relanzados."
