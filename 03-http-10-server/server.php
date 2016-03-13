<?php

// ターミナルに読み出しつつ配列で返す関数
function readlines($fp) {
    $lines = [];
    do {
        $lines[] = $line = fgets($fp);
        echo $line;
    } while ("\r\n" !== $line);
    return $lines;
}

// ターミナルに書きつつ相手にも返信する関数
function write($fp, $body) {
    if (is_resource($body)) {
        // ファイルポインタのときは内容を移す
        echo "…データ…\r\n";
        stream_copy_to_stream($body, $fp);
        fclose($body);
    } else {
        // 文字列のときは普通に書き込む
        echo $body;
        fwrite($fp, $body);
    }
}

// レスポンスを返信して閉じる関数
function write_close($fp, $body, $status, $type) {
    write($fp, "HTTP/1.0 $status\r\n");
    write($fp, "Content-Type: $type\r\n");
    write($fp, "\r\n");
    write($fp, $body);
    write($fp, "\r\n");
    echo "----------------\r\n\r\n";
    fclose($fp);
}

// 時間無制限
set_time_limit(0);

$srv = stream_socket_server('tcp://localhost:8080');
while ($fp = stream_socket_accept($srv, -1)) {

    // リクエストヘッダを配列で受け取る
    $lines = readlines($fp);

    // 1行目をスペースで分割
    $request = explode(' ', $lines[0]);

    if ($request[0] !== 'GET') {
        // GET以外は拒否
        write_close(
            $fp,
            'This server supports only GET request',
            '400 Bad Request',
            'text/plain'
        );
    } elseif (!is_file(__DIR__ . '/../assets' . $request[1])) {
        // ファイルが見つからない時
        write_close(
            $fp,
            'No such file or directory',
            '404 Not Found',
            'text/plain'
        );
    } else {
        // ファイルが見つかった時
        write_close(
            $fp,
            fopen(__DIR__ . '/../assets' . $request[1], 'rb'),
            '200 OK',
            mime_content_type(__DIR__ . '/../assets' . $request[1])
        );
    }
}
