# PHPでHTTP入門

Apacheとか`php -S`を使わずにPHP自身で簡易的なHTTPサーバになるやつ．

## 01-simple-client-server

HTTPはまだ使わずにTCPでやりとりするだけです．

### server.php

TCP接続を受理し，「Hello World [`N`]」のように1秒ごとにクライアントに送信します．  
合計3回繰り返されます．**1人ずつ順番に処理されます．**

### client.php

TCP接続を要求し，サーバからデータを受信します．

### exec.sh

server.php と client.php をいい感じに自動実行＆自動終了してくれるシェルスクリプトです．  
クライアントは2人分実行します．別にこれに頼らず手動で各PHPスクリプトを実行しても構いません．

## 02-fork-tcp-client-server

01-simple-client-serverの同時処理版です．  

### server.php

TCP接続を受理し，「Hello World [`N`]」のように1秒ごとにクライアントに送信します．  
合計3回繰り返されます．プロセスを`fork`するため，**複数人同時に処理されます．**

### client.php

TCP接続を要求し，サーバからデータを受信します．

### exec.sh

server.php と client.php をいい感じに自動実行＆自動終了してくれるシェルスクリプトです．  
クライアントは2人分実行します．別にこれに頼らず手動で各PHPスクリプトを実行しても構いません．

## 03-simple-http-server (server.php)

HTTP/1.0に対応したサーバで，assets にあるファイルを返します．  
**TCPコネクションを1ファイルごとに毎回生成します．**

## 04-keepalive-http-server (server.php)

HTTP/1.1に対応したサーバで，assets にあるファイルを返します．  
**TCPコネクションを再利用します．**

## 05-streaming-http-server (server.php)

HTTP/1.1に対応したサーバで，assets にあるファイルを返します．  
HTTP/1.1の `Transfer-Encoding: chunked` を利用してストリーミングを実現します．  
最近はWebSocketを使うほうが主流ですが，依然としてこちらの方法も利用できます．

## 06-php-http-server (server.php)

HTTP/1.0に対応したサーバで，assets にあるファイルを返します．  
簡易的にPHPを実行します．但し`header`関数などはサポートされていません．  
`Content-Type: text/html` 固定です．

## 07-websocket-http-server (server.php)

HTTP/1.1およびWebSocketに対応したサーバです．  
どのURLにアクセスしても手抜きチャット(chat.html)を表示します．  
名前も決められないぐらい手抜きです．
