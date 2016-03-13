<?php

// ターミナルに読み出しつつ配列で返す関数
function readlines($fp) {
    $lines = [];
    do {
        $lines[] = $line = fgets($fp);
        if ($line === false) {
            // ブラウザ更新連打してると終端チェックしててもここでfalseになることがある
            return [];
        }
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

// レスポンスを返信するが，fcloseで閉じない
function write_keep($fp, $body, $status, $type) {
    // 長さを調べる
    // (接続を再利用するのでキッチリ長さを通知して
    //  レスポンスの終わりを相手に知らせる必要がる)
    if (is_resource($body)) {
        $length = filesize(stream_get_meta_data($body)['uri']);
    } else {
        $length = strlen($body);
    }
    write($fp, "HTTP/1.1 $status\r\n");
    write($fp, "Content-Type: $type\r\n");
    write($fp, "Content-Length: $length\r\n");
    // ↓ HTTP/1.1では実はこっちがデフォルトなので省略していい
    //   HTTP/1.0で再利用をやりたいときは必須
    // write($fp, "Connection: keep-alive\r\n");
    write($fp, "\r\n");
    write($fp, $body);
    echo "----------------\r\n\r\n";
}

// 新たな接続を受け付けつつ，既存の接続も管理し，
// 相手からリクエストヘッダが送信されてきた接続を無限に列挙する
// …というジェネレータを返す関数
function accept($srv) {
    $fps = [];
    // 無限ループ
    while (true) {
        // 古い接続が10個を超えたら古いものから削除
        $fps = array_slice($fps, -10);
        // 読み出せる接続を監視
        $read = array_merge($fps, [$srv]);
        $null = null;
        if (stream_select($read, $null, $null, 5) > 0) {
            foreach ($read as $i => $fp) {
                // サーバソケットの場合はクライアントソケットを受け入れる
                if ($fp === $srv) {
                    $fp = stream_socket_accept($srv, 0);
                    echo "# New connection has been established! ($fp)\r\n\r\n";
                    echo "------------------\r\n\r\n";
                    $fps[] = $fp;
                    continue;
                }
                // 既に終端に達しているクライアントソケットは削除する
                if (stream_get_meta_data($fp)['eof']) {
                    echo "# Connection has been expired... ($fp)\r\n\r\n";
                    echo "------------------\r\n\r\n";
                    fclose($fp);
                    unset($fps[$i]);
                    continue;
                }
                // 有効なクライアントソケットは foreach の値として列挙する
                yield $fp;
            }
        }
    }
}

// 時間無制限
set_time_limit(0);

$srv = stream_socket_server('tcp://localhost:8080');
foreach (accept($srv) as $fp) {

    // リクエストヘッダを配列で受け取る
    if (!$lines = readlines($fp)) {
        // 正常に読み取りきれなかったら無視
        continue;
    }

    // 1行目をスペースで分割
    $request = explode(' ', $lines[0]);

    if ($request[0] !== 'GET') {
        // GET以外は拒否
        write_keep(
            $fp,
            'This server supports only GET request',
            '400 Bad Request',
            'text/plain'
        );
    } elseif (!is_file(__DIR__ . '/../assets' . $request[1])) {
        // ファイルが見つからない時
        write_keep(
            $fp,
            'No such file or directory',
            '404 Not Found',
            'text/plain'
        );
    } else {
        // ファイルが見つかった時
        write_keep(
            $fp,
            fopen(__DIR__ . '/../assets' . $request[1], 'rb'),
            '200 OK',
            mime_content_type(__DIR__ . '/../assets' . $request[1])
        );
    }
}
