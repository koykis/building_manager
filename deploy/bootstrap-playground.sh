#!/bin/sh
set -eu
cd /opt/building_manager
compose() { docker compose --env-file .env.playground -f deploy/compose.playground.yaml "$@"; }
compose config --quiet
compose build app
compose up -d db
attempt=0
until compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -h127.0.0.1 -uoikia building_manager --execute "SELECT 1"' >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    [ "$attempt" -lt 60 ] || { echo 'Database readiness timed out'; exit 1; }
    sleep 2
done
count=$(compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -uoikia --batch --skip-column-names --execute "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''building_manager'\''"')
[ "$count" = 0 ] || { echo 'Refusing to overwrite an existing application database'; exit 1; }
compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -uoikia building_manager' < transfer/database.sql
chown -R 33:33 shared/storage
chmod -R u+rwX,go-rwx shared/storage
compose up -d app web
compose exec -T --user www-data app php artisan package:discover --no-interaction
compose exec -T --user www-data app php artisan migrate --force --no-interaction
compose exec -T --user www-data app php artisan optimize --no-interaction
compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -uoikia --batch building_manager --execute "SELECT status, COUNT(*) AS statement_count FROM statements GROUP BY status; SELECT COUNT(*) AS current_apartment_rows FROM apartment_statement_rows r JOIN statements s ON s.id=r.statement_id WHERE s.status='\''published'\'';"'
python3 - <<'PY'
import hashlib, json
from pathlib import Path
root = Path('/opt/building_manager')
manifest = json.loads((root/'transfer/source-document-hashes.json').read_text())
for name, expected in manifest.items():
    assert hashlib.sha256((root/'shared/storage/app/private'/name).read_bytes()).hexdigest() == expected, name
print(f'All {len(manifest)} private document files verified')
PY
echo 'APPLICATION_READY'
