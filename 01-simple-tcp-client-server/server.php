<?php

// 時間無制限
set_time_limit(0);

$srv = stream_socket_server('tcp://localhost:8080');
while ($fp = stream_socket_accept($srv, -1)) {
    // 3回繰り返してメッセージを表示
    // 1人ずつ順番に処理される
    for ($i = 0; $i < 3; ++$i) {
        fwrite($fp, "Hello, World [$i]\n");
        sleep(1);
    }
    fclose($fp);
}
