<?php

// TCPソケットに使う設定を取得
// デフォルト値は「localhost:8080」
$conf = getenv('HTTPTUTE_TCP_SOCK') ?: 'localhost:8080';

// TCPクライアントソケットを生成
$con = @stream_socket_client("tcp://$conf");
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
