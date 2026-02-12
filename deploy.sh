#!/bin/bash
set -e

echo "=== Building flashcards v2 ==="
npm run build

echo "=== Deploying to nextcloud container ==="
docker cp js nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/
docker cp css/nextcloud-flashcards.css nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/css/nextcloud-flashcards.css

echo "=== Clearing cache ==="
docker exec nextcloud-aio-nextcloud bash -c 'rm -rf /var/www/html/data/appdata_*/js/* /var/www/html/data/appdata_*/css/* && php occ maintenance:mode --on && php occ maintenance:mode --off'

echo "=== Deploy complete! ==="
