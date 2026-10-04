#!/bin/sh
set -eu
umask 077

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$root"
compose() { docker compose --env-file .env.playground -f deploy/compose.playground.yaml "$@"; }

git diff --quiet
git diff --cached --quiet
compose config --quiet
git fetch origin main
git merge-base --is-ancestor HEAD origin/main

stamp=$(date -u +%Y%m%d-%H%M%S)
backup="$root/backups/deploy-$stamp"
mkdir -p "$backup"
git rev-parse HEAD > "$backup/previous-commit.txt"
compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump -uoikia --single-transaction --no-tablespaces --set-gtid-purged=OFF building_manager' > "$backup/database.sql"
tar -czf "$backup/private-storage.tar.gz" -C "$root" shared
tar --exclude=./.git --exclude=./backups --exclude=./shared --exclude=./transfer --exclude=./.local --exclude=./frontend/node_modules --exclude=./backend/vendor -czf "$backup/application.tar.gz" -C "$root" .
old_image=$(compose images -q app)
docker image tag "$old_image" "building-manager-playground-app:before-$stamp"
printf '%s\n' "building-manager-playground-app:before-$stamp" > "$backup/previous-image.txt"
sha256sum "$backup/database.sql" "$backup/private-storage.tar.gz" "$backup/application.tar.gz" > "$backup/SHA256SUMS"
printf 'Backup saved: %s\n' "$backup"

(umask 022; git merge --ff-only origin/main)
compose build app
docker run --rm --memory=384m --cpus=1 -v "$root/frontend:/app" -w /app node:20-bookworm-slim sh -c 'npm ci --no-audit --no-fund && npm run build -- --base=/building_manager/ --outDir dist-next'

maintenance=0
finish() {
    result=$?
    if [ "$maintenance" = 1 ]; then
        compose exec -T --user www-data app php artisan up --no-interaction || true
    fi
    if [ "$result" != 0 ]; then
        printf 'Deployment failed; recovery backup: %s\n' "$backup" >&2
    fi
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

compose exec -T --user www-data app php artisan down --retry=60 --no-interaction
maintenance=1
compose up -d --no-deps app
compose exec -T --user www-data app php artisan package:discover --no-interaction
compose exec -T --user www-data app php artisan migrate --force --no-interaction
compose exec -T --user www-data app php artisan optimize --no-interaction
# Retain previous hashed assets so open browser tabs can finish using them.
rsync -a --delay-updates frontend/dist-next/ frontend/dist/
# Nginx must be able to read files created under the private shell umask.
chmod -R a+rX frontend/dist
compose exec -T web nginx -t
compose exec -T web nginx -s reload
compose exec -T --user www-data app php artisan up --no-interaction
maintenance=0

for path in /building_manager/ /building_manager/up /filosafe/; do
    curl --fail --silent --show-error --retry 5 --retry-delay 2 --retry-all-errors --output /dev/null "http://127.0.0.1$path"
done
git rev-parse HEAD > "$backup/deployed-commit.txt"
compose ps
printf 'Deployed commit: %s\n' "$(git rev-parse HEAD)"
