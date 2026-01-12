#!/bin/bash

echo "=== 環境確認スクリプト ==="
echo ""

echo "1. Dockerの確認:"
if command -v docker &> /dev/null; then
    echo "  ✓ Dockerがインストールされています"
    docker --version
else
    echo "  ✗ Dockerがインストールされていません"
fi

echo ""
echo "2. Docker Composeの確認:"
if command -v docker-compose &> /dev/null || docker compose version &> /dev/null; then
    echo "  ✓ Docker Composeが利用可能です"
    docker-compose --version 2>/dev/null || docker compose version
else
    echo "  ✗ Docker Composeが利用可能ではありません"
fi

echo ""
echo "3. Redisサーバーの確認:"
if command -v redis-server &> /dev/null; then
    echo "  ✓ Redisサーバーがインストールされています"
    redis-server --version
else
    echo "  ✗ Redisサーバーがインストールされていません"
fi

echo ""
echo "4. PHP Redis拡張の確認:"
if php -m | grep -i redis &> /dev/null; then
    echo "  ✓ PHP Redis拡張がインストールされています"
    php -m | grep -i redis
else
    echo "  ✗ PHP Redis拡張がインストールされていません"
fi

echo ""
echo "=== 推奨実装方法 ==="
if command -v docker &> /dev/null; then
    echo "→ 方法1: Docker Composeを使用（推奨）"
else
    echo "→ 方法2: システムサービスとしてRedisをインストール"
fi

