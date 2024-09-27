
#!/bin/bash

HLS_PATH="/mnt/streaming/data"
DESTINATION_SERVER="http://44.216.74.109/live_stream/public/api/check/copyright"

inotifywait -m -e create --format "%f" "$HLS_PATH" |
while read FILENAME
do
    echo "New file created: $FILENAME"
    # Send an HTTP request to the other server with the file name
    curl -X POST -d "filename=$FILENAME" "$DESTINATION_SERVER"
done
