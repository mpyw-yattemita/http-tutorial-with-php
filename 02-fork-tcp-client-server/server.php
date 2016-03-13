<?php

// 時間無制限
set_time_limit(0);

// 子プロセスが終了したときの後始末を登録する
// (難しいので気にしなくていいです)
declare(ticks = 1);
pcntl_signal(SIGCHLD, function ($sig) {
    if ($sig !== SIGCHLD) return;
    $ignore = null;
    while (0 < $rc = pcntl_waitpid(-1, $ignore, WNOHANG));
});

// TCPサーバソケットを生成
$srv = @stream_socket_server('tcp://localhost:8080');
if (!$srv) {
    fwrite(STDERR, error_get_last()['message'] . "\n");
    exit(1);
}
echo "Listening TCP connection on localhost:8080...\n";

// TCPクライアントソケットを受け入れる
while ($con = @stream_socket_accept($srv, -1)) {
    // プロセスを分岐する
    // 親プロセスは直ちに次の stream_socket_accept の待機に戻る
    // 子プロセスはそのまま下の for に続く
    if (pcntl_fork()) {
        continue;
    }
    // 3回繰り返してメッセージを送信
    // 複数人同時に処理される
    for ($i = 0; $i < 3; ++$i) {
        $msg = "Hello, World [$i]\n";
        echo "Sent: $msg";
        fwrite($con, $msg);
        sleep(1);
    }
    // TCPコネクションを切断
    fclose($con);
    // 子プロセスを終了
    exit(0);
}
