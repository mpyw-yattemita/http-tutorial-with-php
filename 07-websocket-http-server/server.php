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

// HTTP用，WebSocket用それぞれのTCPクライアントソケットプール
$httpsockets = [];
$websockets = [];

// 無限ループ
while (true) {

    // 持続中のHTTP用TCPクライアントソケットが10個を超えたら古いものから削除
    $httpsockets = array_slice($httpsockets, -10);
    // 持続中のWebSocket用クライアントソケットが100個を超えたら古いものから削除
    $websockets = array_slice($websockets, -100);

    // 監視
    $read = array_merge($httpsockets, $websockets, [$srv]);
    $null = null;

    // 何も動きがなければループの最初に戻る
    if (stream_select($read, $null, $null, 5) <= 0) {
        continue;
    }

    // 動きのあったソケットを列挙
    foreach ($read as $socket) {

        // サーバソケットの場合はクライアントソケットを受け入れてHTTPプールに追加する
        if ($socket === $srv) {
            $con = stream_socket_accept($srv, 0);
            $httpsockets[(string)$con] = $con;
            echo "# New connection has been established! ($con)\r\n\r\n";
            echo "------------------\r\n\r\n";
            continue;
        }

        // Webブラウザが破棄したクライアントソケットは削除する
        if (feof($socket)) {
            if (isset($websockets[(string)$socket])) {
                // もしWebSocket用のものであれば，自分以外の全員に退出を通知する
                unset($websockets[(string)$socket]);
                write_websocket_message($socket, $websockets, json_encode([
                    'data' => sprintf("[System] User #%d exited", $socket),
                ]));
            } else {
                // HTTP用のものである場合
                unset($httpsockets[(string)$socket]);
                echo "# Connection has been expired... ($socket)\r\n\r\n";
                echo "------------------\r\n\r\n";
            }
            fclose($socket);
            continue;
        }

        // WebSocketにアップグレード済みのクライアントソケットの場合
        if (isset($websockets[(string)$socket])) {
            $msg = read_websocket_message($socket);
            if (is_object($msg) && $msg->opcode === 1) {
                // テキストフレームであれば全員に発言を通知
                write_websocket_message($socket, $websockets, json_encode([
                    'data' => sprintf("User #%d: %s", $socket, $msg->content),
                ], JSON_UNESCAPED_UNICODE));
            }
            if (is_object($msg) && $msg->opcode === 8) {
                // 切断通知であれば自分以外の全員に通知
                unset($websockets[(string)$socket]);
                write_websocket_message($socket, $websockets, json_encode([
                    'data' => sprintf("[System] User #%d exited", $socket),
                ]));
                fclose($socket);
            }
            continue;
        }

        // HTTPヘッダを読み取る
        $headers = [];
        while (true) {
            $line = fgets($socket);
            if ($line === false) continue 2; // 更新連打対策
            echo $line; // ターミナルに表示
            $line = rtrim($line);
            if ($line === '') break; // 空行が見つかればそこでヘッダ終了
            list($key, $value) = explode(': ', $line, 2) + ['', ''];
            $headers[$key] = $value;
        }

        // WebSocketへのアップグレード要求があった場合，それに対応する
        if (isset($headers['Upgrade'], $headers['Sec-WebSocket-Key'])) {
            $accept = base64_encode(pack('H*', sha1(
                $headers['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'
            )));
            write_http_message($socket, 'HTTP/1.1 101 Switching Protocol');
            write_http_message($socket, 'Upgrade: websocket');
            write_http_message($socket, 'Connection: Upgrade');
            write_http_message($socket, "Sec-WebSocket-Accept: $accept");
            write_http_message($socket, '');
            $websockets[(string)$socket] = $socket;
            // 入場通知を全員に送信
            write_websocket_message($socket, $websockets, json_encode([
                'data' => sprintf("[System] User #%d joined", $socket),
            ]));
            unset($httpsockets[(string)$socket]);
            continue;
        }

        // 以上のどれにも該当しない場合は chat.html を返す
        $length = filesize(__DIR__ . '/chat.html');
        $content = file_get_contents(__DIR__ . '/chat.html');
        write_http_message($socket, 'HTTP/1.1 200 OK');
        write_http_message($socket, 'Content-Type: text/html; charset=UTF-8');
        write_http_message($socket, "Content-Length: $length");
        write_http_message($socket, '');
        write_http_message($socket, $content);
        echo "----------------\r\n\r\n";
        $httpsockets[(string)$socket] = $socket;

    }

}

/**
 * WebSocketのフレームを受信
 *
 * @param resource $con TCPクライアントソケット
 */
function read_websocket_message($con) {
    $msg = new stdClass;
    // オペコードを読み取る
    $buf = fread($con, 1);
    if (strlen($buf) < 1) {
        return false;
    }
    $msg->opcode = ord($buf) & 15;
    // ペイロード長を読み取る
    $buf = fread($con, 1);
    if (strlen($buf) < 1) {
        return false;
    }
    $length = current(unpack('C', $buf)) & 127;
    // 拡張ペイロード長があればそれに置き換える
    if ($length === 126) {
        $buf = fread($con, 2);
        if (strlen($buf) < 2) {
            return false;
        }
        $length = current(unpack('n', $buf));
    } elseif ($length === 127) {
        $buf = fread($con, 8);
        if (strlen($buf) < 8) {
            return false;
        }
        $length = current(unpack('J', $buf));
    }
    // マスクを読み取る
    $mask = fread($con, 4);
    if (strlen($mask) < 4) {
        return false;
    }
    if ($length === 0) {
        $msg->content = '';
        return $msg;
    }
    // マスクされたデータを取得
    $content = fread($con, $length);
    if (strlen($content) < $length) {
        return false;
    }
    // マスクを解除
    $msg->content = '';
    for ($i = 0; $i < $length; ++$i) {
        $msg->content .= $content[$i] ^ $mask[$i % 4];
    }
    return $msg;
}

/**
 * WebSocketのテキストフレームを送信
 *
 * @param resource $src 送信元TCPクライアントソケット
 * @param array $targets 送信先TCPクライアントソケットの配列
 * @param string $data データ
 */
function write_websocket_message($src, $targets, $data) {
    // FINフラグとテキストフレームは固定
    $header = "\x81";
    $length = strlen($data);
    // ペイロード長を作成
    if ($length <= 125) {
        $header .= chr($length);
    } elseif ($length <= 65535) {
        $header .= pack('n', $length);
    } else {
        $header .= pack('J', $length);
    }
    // 全ユーザに対して送信
    foreach ($targets as $target) {
        printf("User #%d -> User#%d: %s\r\n", $src, $target, $data);
        fwrite($target, "$header$data");
    }
}

/**
 * HTTPメッセージ1行を送信
 *
 * @param resource $con TCPクライアントソケット
 * @param string $data データ
 */
function write_http_message($con, $data) {
    echo "$data\r\n";
    fwrite($con, "$data\r\n");
}
