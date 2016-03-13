<?php

/**
 * ターミナルに読み出しつつ，リクエストヘッダを配列で返す関数
 *
 * @param resource $con TCPクライアントソケット
 * @return array HTTPリクエストヘッダの配列
 */
function read_headers($con) {
    $lines = [];
    while (true) {
        $line = fgets($con);
        echo $line;
        if ($line === "\r\n") break; // 空行が現れたらそこで終わりとみなす
        $lines[] = $line;
    }
    return $lines;
}

/**
 * ターミナルに書き出しつつ，レスポンスヘッダとレスポンスボディを送信して閉じる関数
 *
 * @param resource $con TCPクライアントソケット
 * @param resource|string $body ファイルポインタまたは文字列
 * @param string $status "200 OK" とか "400 Bad Request" とか
 * @param type $type Content-Type の値． "text/plain" とか "text/html" とか
 */
function write_close($con, $body, $status, $type) {
    // 処理用の一時的な関数を作成して変数に代入
    $write = function ($body) use ($con) {
        if (is_resource($body)) {
            // ファイルポインタのときは内容をそのまま移す
            // ターミナルへの表示は省略する
            echo "…データ…\r\n";
            stream_copy_to_stream($body, $con);
            fclose($body);
        } else {
            // 文字列のときは普通に書き込む
            echo $body;
            fwrite($con, $body);
        }
    };
    $write("HTTP/1.0 $status\r\n");
    $write("Content-Type: $type\r\n");
    $write("\r\n");
    $write($body);
    $write("\r\n");
    echo "----------------\r\n\r\n";
    fclose($con);
}

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

    // リクエストヘッダを配列で受け取る
    $lines = read_headers($con);

    // リクエストラインを解析
    list($method, $path, $version) = explode(' ', rtrim($lines[0]), 3) + ['', '', ''];

    // ディレクトリトラバーサル攻撃対策
    if (strpos($path, '..') !== false) {
        $path = '';
    }

    if ($method !== 'GET') {
        // GET以外は拒否
        write_close(
            $con,
            'This server supports only GET request',
            '400 Bad Request',
            'text/plain'
        );
    } elseif (!is_file(__DIR__ . '/../assets' . $path)) {
        // ファイルが見つからない時
        write_close(
            $con,
            'No such file or directory',
            '404 Not Found',
            'text/plain'
        );
    } else {
        // ファイルが見つかった時
        write_close(
            $con,
            fopen(__DIR__ . '/../assets' . $path, 'rb'),
            '200 OK',
            mime_content_type(__DIR__ . '/../assets' . $path)
        );
    }

}
