<?php

// 時間無制限
set_time_limit(0);

// TCPサーバソケットを生成
$srv = @stream_socket_server('tcp://localhost:8080');
if (!$srv) {
    fwrite(STDERR, error_get_last()['message'] . "\n");
    exit(1);
}
echo "Listening HTTP connection on http://localhost:8080...\n";

// TCPクライアントソケットを受け入れる
while ($con = stream_socket_accept($srv, -1)) {

    // リクエストヘッダを配列で受け取る
    if (!$lines = read_headers($con)) {
        // 正常に読み取りきれなかったら無視
        continue;
    }

    // リクエストラインを解析
    list($method, $path, $version) = explode(' ', rtrim($lines[0]), 3) + ['', '', ''];

    // クエリを $_GET に格納してパスと分離する
    parse_str(parse_url($path, PHP_URL_QUERY), $_GET);
    $path = parse_url($path, PHP_URL_PATH);
    $_SERVER['REQUEST_METHOD'] = $method;

    // Content-Length ヘッダがあれば $_POST に格納
    if (preg_match('@^Content-Length: (\d+)@m', implode("\n", $lines), $m)) {
        parse_str(fread($con, $m[1]), $_POST);
    }

    // ディレクトリトラバーサル攻撃対策
    if (strpos($path, '..') !== false) {
        $path = '';
    }

    if ($method !== 'GET' && $method !== 'POST') {
        // GETとPOST以外は拒否
        write_close(
            $con,
            'This server supports only GET or POST request',
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
    } elseif (pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION) === 'php') {
        // PHPファイルが見つかった時
        write_php_close($con, __DIR__ . '/../assets' . $path, $lines);
    } else {
        // その他のファイルが見つかった時
        write_close(
            $con,
            fopen(__DIR__ . '/../assets' . $path, 'rb'),
            '200 OK',
            mime_content_type(__DIR__ . '/../assets' . $path)
        );
    }

}

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
        if ($line === false) return []; // ブラウザ更新連打対策
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
    $write("HTTP/1.0 $status");
    $write("Content-Type: $type");
    $write('');
    $write($body);
    echo "----------------\r\n\r\n";
    fclose($con);
}

/**
 * PHPコードを評価した結果をレスポンスボディとして送信して閉じる関数
 *
 * @param resource $con TCPクライアントソケット
 * @param string $filename PHPファイル名
 */
function write_php_close($con, $filename, array $headers) {
    // PHPスクリプトを実行して出力を取得
    ob_start();
    require $filename;
    $output = ob_get_clean();
    // 処理用の一時的な関数を作成して変数に代入
    $write = function ($data, $omit = false) use ($con) {
        echo !$omit ? "$data\r\n" : "...PHP処理後のデータ...\r\n";
        fwrite($con, "$data\r\n");
    };
    $write('HTTP/1.0 200 OK');
    $write('Content-Type: text/html');
    $write('');
    $write($output, true);
    echo "----------------\r\n\r\n";
    fclose($con);
}
