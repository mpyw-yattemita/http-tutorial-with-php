<?php

// TCPソケットに使う設定を取得
// デフォルト値は「localhost:8080」
$conf = getenv('HTTPTUTE_TCP_SOCK') ?: 'localhost:8080';

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
$srv = @stream_socket_server("tcp://$conf");
if (!$srv) {
    fwrite(STDERR, error_get_last()['message'] . "\n");
    exit(1);
}
echo "Listening TCP connection on $conf...\n";

// TCPクライアントソケットを受け入れる
// stream_socket_acceptが失敗することがあるので while から do if ... while に変更しました
do if ($con = @stream_socket_accept($srv, -1)) {
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
} while (true);
