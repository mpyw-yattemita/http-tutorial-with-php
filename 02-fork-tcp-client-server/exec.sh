#!/bin/bash

# このファイルが存在するディレクトリに移動
# (どこからでも呼び出せるようにするため)
cd $(dirname "$0")

# スクリプト終了時にサーバーを強制終了するシグナルハンドラを登録
# そしてサーバを起動し，確実に準備が終わるように0.1秒待機する
trap 'killall php' INT EXIT TERM HUP
php server.php & # & をつけると処理の終了を待たない
sleep 0.1

# クライアントを起動し，次にwaitするためにプロセスIDを集める
pids=()
for ((i = 0; i < 2; i++)) {
    php client.php & # & をつけると処理の終了を待たない
    pids+=( $! )
}

# クライアントが全て終了するまで待つ
for ((i = 0; i < ${#pids[@]}; i++)) {
    wait ${pids[i]}
}
