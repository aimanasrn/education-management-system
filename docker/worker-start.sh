#!/bin/sh
set -eu
echo $$ > /tmp/ems-worker.pid
exec php artisan queue:work redis --sleep=1 --tries=3 --timeout=90 --max-time=3600 --memory=128

