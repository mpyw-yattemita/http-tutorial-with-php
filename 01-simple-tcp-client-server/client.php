<?php

$fp = stream_socket_client('tcp://localhost:8080');
while (false !== $line = fgets($fp)) {
    echo $line;
}
fclose($fp);
