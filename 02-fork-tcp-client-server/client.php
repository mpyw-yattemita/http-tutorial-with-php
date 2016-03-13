<?php

// TCPクライアントソケットを生成
$con = @stream_socket_client('tcp://localhost:8080');
if (!$con) {
    fwrite(STDERR, error_get_last()['message'] . "\n");
    exit(1);
}

// 終端まで読み出す
while (false !== $line = fgets($con)) {
    echo "Received: $line";
}

// TCPコネクションを切断する
fclose($con);
