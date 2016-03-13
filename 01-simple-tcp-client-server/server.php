<?php

// 時間無制限
set_time_limit(0);

// TCPサーバソケットを生成
$srv = @stream_socket_server('tcp://localhost:8080');
if (!$srv) {
    fwrite(STDERR, error_get_last()['message'] . "\n");
    exit(1);
}

// TCPクライアントソケットを受け入れる
while ($con = stream_socket_accept($srv, -1)) {
    // 3回繰り返してメッセージを送信
    // 1人ずつ順番に処理される
    for ($i = 0; $i < 3; ++$i) {
        $msg = "Hello, World [$i]\n";
        echo "Sent: $msg";
        fwrite($con, $msg);
        sleep(1);
    }
    // TCPコネクションを切断
    fclose($con);
}
