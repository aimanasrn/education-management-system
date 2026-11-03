#!/bin/sh
set -eu
umask 077
case "${REDIS_PASSWORD:-}" in
  ''|*[!a-zA-Z0-9_+=/-]*) echo 'Set a Redis password containing safe ASCII characters.' >&2; exit 1 ;;
esac
cat > /tmp/ems-redis.conf <<EOF
bind 0.0.0.0
protected-mode yes
port 6379
dir /data
appendonly yes
appendfsync everysec
maxmemory 256mb
maxmemory-policy noeviction
requirepass "$REDIS_PASSWORD"
EOF
exec redis-server /tmp/ems-redis.conf

