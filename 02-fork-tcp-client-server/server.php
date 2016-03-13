<?php

// 時間無制限
set_time_limit(0);

// 子プロセスが終了したときの後始末を登録する
declare(ticks = 1);
pcntl_signal(SIGCHLD, function ($sig) {
    if ($sig !== SIGCHLD) return;
    $ignore = null;
    while (0 < $rc = pcntl_waitpid(-1, $ignore, WNOHANG));
});

$srv = stream_socket_server('tcp://localhost:8080');
while ($fp = @stream_socket_accept($srv, -1)) {
    // プロセスを分岐する
    // 親プロセスは直ちに次の stream_socket_accept の待機に戻る
    // 子プロセスはそのまま下の for に続く
    if (pcntl_fork()) {
        continue;
    }
    // 3回繰り返してメッセージを表示
    // 複数人同時に処理される
    for ($i = 0; $i < 3; ++$i) {
        fwrite($fp, "Hello, World [$i]\n");
        sleep(1);
    }
    fclose($fp);
    // 子プロセスを終了
    exit(0);
}
