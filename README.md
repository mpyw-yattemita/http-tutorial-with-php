# PHPでTCPコネクション使ってHTTPサーバ書いたやつ

**注意事項: 簡略化するためセキュリティ対策などは全く行っておりません，あくまで実験用です**

## 01-simple-client-server

一番シンプルなやつ．HTTPはまだ使わずにTCPでやりとりするだけ．

### server.php

TCP接続を受理し，「Hello World [`N`]」のように1秒ごとにクライアントに送信，計3回  
**1人ずつ順番に処理されます**

### client.php

TCP接続を要求し，サーバから送信されたデータを全部ターミナルに垂れ流す

### exec.sh

server.php と client.php をいい感じに自動実行＆自動終了してくれるシェルスクリプト  
クライアントは2人分実行します  
別にこれに頼らず手動で各PHPスクリプトを実行しても構いません

## 02-fork-tcp-client-server

01-simple-client-serverの同時処理版．  

### server.php

TCP接続を受理し，「Hello World [`N`]」のように1秒ごとにクライアントに送信，計3回  
**複数人同時に処理されます**

### client.php

TCP接続を要求し，サーバから送信されたデータを全部ターミナルに垂れ流す

### exec.sh

server.php と client.php をいい感じに自動実行＆自動終了してくれるシェルスクリプト  
クライアントは2人分実行します  
別にこれに頼らず手動で各PHPスクリプトを実行しても構いません

## 03-http-10-server

HTTP/1.0の最もシンプルなサーバ．assets にあるファイルを返します．  
**TCPコネクションを1ファイルごとに毎回生成します．**

## 04-http-11-server

HTTP/1.1に対応したサーバ．  
**TCPコネクションを再利用します．**

<!--

## 05-http-11-streaming-server

HTTP/1.1の`Transfer-Encoding: chunked`に対応したサーバ．  

-->
