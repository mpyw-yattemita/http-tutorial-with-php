<?php

/**
 * ターミナルに読み出しつつ，リクエストヘッダを配列で返す関数
 *
 * @param resource $con TCPクライアントソケット
 * @return array|bool HTTPリクエストヘッダの配列，または更新連打のエラー時にfalse
 */
function read_headers($con) {
    $lines = [];
    while (true) {
        $line = fgets($con);
        if ($line === false) return []; // ブラウザ更新連打対策
        echo $line;
        if ($line === "\r\n") break; // 空行が現れたらそこで終わりとみなす
        $lines[] = $line;
    }
    return $lines;
}

/**
 * ターミナルに書き出しつつ，レスポンスヘッダとレスポンスボディを送信する関数
 * 接続は再利用するため閉じない
 *
 * @param resource $con TCPクライアントソケット
 * @param resource|string $body ファイルポインタまたは文字列
 * @param string $status "200 OK" とか "400 Bad Request" とか
 * @param type $type Content-Type の値． "text/plain" とか "text/html" とか
 */
function write_keep($con, $body, $status, $type) {
    // $body の長さを調べる
    // (接続を再利用するのでキッチリ長さを通知して
    //  レスポンスの終わりを相手に知らせる必要がる)
    if (is_resource($body)) {
        $length = filesize(stream_get_meta_data($body)['uri']);
    } else {
        $length = strlen($body);
    }
    // 処理用の一時的な関数を作成して変数に代入
    $write = function ($data) use ($con) {
        if (is_resource($data)) {
            // ファイルポインタのときは内容をそのまま移す
            // ターミナルへの表示は省略する
            echo "…データ…\r\n";
            stream_copy_to_stream($data, $con);
            fwrite($con, "\r\n");
            fclose($data);
        } else {
            // 文字列のときは普通に書き込む
            echo "$data\r\n";
            fwrite($con, "$data\r\n");
        }
    };
    $write("HTTP/1.1 $status");
    $write("Content-Type: $type");
    $write("Content-Length: $length");
    $write('');
    $write($body);
    echo "----------------\r\n\r\n";
}

/**
 * 新たな接続を受け付けつつ，既存の接続も管理し，
 * リクエストが送信されてきたTCPクライアントソケットを列挙するジェネレータ関数
 *
 * @param resource $srv TCPサーバソケット
 * @return Generator
 */
function accept($srv) {
    // TCPクライアントソケットのプール
    $cons = [];
    // 無限ループする
    // (ジェネレータの yield を使っているので呼び出し元の foreach は進む)
    while (true) {
        // 持続中のTCPクライアントソケットが10個を超えたら古いものから削除
        $cons = array_slice($cons, -10);
        // リクエストが送信されてきた，あるいはWebブラウザが破棄した
        // TCPクライアントソケットがあるかどうか監視する
        $read = array_merge($cons, [$srv]);
        $null = null;
        if (stream_select($read, $null, $null, 5) > 0) {
            // 監視対象として見つかったものだけが $read の配列に残る
            foreach ($read as $i => $socket) {
                // サーバソケットの場合はクライアントソケットを受け入れてプールに追加する
                if ($socket === $srv) {
                    $con = stream_socket_accept($srv, 0);
                    echo "# New connection has been established! ($con)\r\n\r\n";
                    echo "------------------\r\n\r\n";
                    $cons[] = $con;
                    continue;
                }
                // Webブラウザが破棄したクライアントソケットは削除する
                if (stream_get_meta_data($socket)['eof']) {
                    echo "# Connection has been expired... ($socket)\r\n\r\n";
                    echo "------------------\r\n\r\n";
                    fclose($socket);
                    unset($cons[$i]);
                    continue;
                }
                // 有効なクライアントソケットは foreach の値として列挙する
                yield $socket;
            }
        }
    }
}

// 時間無制限
set_time_limit(0);

// TCPサーバソケットを生成
$srv = @stream_socket_server('tcp://localhost:8080');
if (!$srv) {
    fwrite(STDERR, error_get_last()['message'] . "\n");
    exit(1);
}
echo "Listening HTTP connection on http://localhost:8080...\n";

// TCPサーバソケットからジェネレータを作成し，
// 何か動きのあったTCPクライアントソケットの列挙を受け取る
foreach (accept($srv) as $con) {

    // リクエストヘッダを配列で受け取る
    if (!$lines = read_headers($con)) {
        // 正常に読み取りきれなかったら無視
        continue;
    }

    // リクエストラインを解析
    list($method, $path, $version) = explode(' ', rtrim($lines[0]), 3) + ['', '', ''];

    // ディレクトリトラバーサル攻撃対策
    if (strpos($path, '..') !== false) {
        $path = '';
    }

    if ($method !== 'GET') {
        // GET以外は拒否
        write_keep(
            $con,
            'This server supports only GET request',
            '400 Bad Request',
            'text/plain'
        );
    } elseif (!is_file(__DIR__ . '/../assets' . $path)) {
        // ファイルが見つからない時
        write_keep(
            $con,
            'No such file or directory',
            '404 Not Found',
            'text/plain'
        );
    } else {
        // ファイルが見つかった時
        write_keep(
            $con,
            fopen(__DIR__ . '/../assets' . $path, 'rb'),
            '200 OK',
            mime_content_type(__DIR__ . '/../assets' . $path)
        );
    }

}
