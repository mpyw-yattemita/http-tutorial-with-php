<?php

// TCPソケットに使う設定を取得
// デフォルト値は「localhost:8080」
$conf = getenv('HTTPTUTE_TCP_SOCK') ?: 'localhost:8080';

// 時間無制限
set_time_limit(0);

// TCPサーバソケットを生成
$srv = @stream_socket_server("tcp://$conf");
if (!$srv) {
    fwrite(STDERR, error_get_last()['message'] . "\n");
    exit(1);
}
echo "Listening TCP connection on $conf...\n";

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
