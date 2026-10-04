#!/usr/bin/env python3
import datetime
from pathlib import Path
import subprocess
import urllib.request

root = Path('/opt/building_manager')
if 'APPLICATION_READY' not in (root/'deploy/bootstrap.log').read_text():
    raise SystemExit('Application bootstrap must finish before publishing the route')
subprocess.run(['docker', 'exec', 'filosafe-playground-caddy-1', 'wget', '-qO', '/dev/null', 'http://building-manager-web/up'], check=True)
caddy = Path('/opt/filosafe/deploy/Caddyfile')
original = caddy.read_text()
if '/building_manager/*' in original:
    raise SystemExit('The building_manager route already exists; inspect it before changing it')
marker = '\t\trespond 404\n'
assert original.count(marker) == 1, 'Could not identify the final Caddy fallback'
snippet = (root/'deploy/caddy.playground.snippet').read_text().split('\n', 1)[1]
addition = '\n'.join('\t\t' + line if line else '' for line in snippet.splitlines()) + '\n\n'
updated = original.replace(marker, addition + marker)
candidate = caddy.with_name('Caddyfile.oikia-candidate')
candidate.write_text(updated)
subprocess.run(['docker', 'exec', 'filosafe-playground-caddy-1', 'caddy', 'validate', '--config', '/var/www/html/deploy/Caddyfile.oikia-candidate', '--adapter', 'caddyfile'], check=True)
backup = caddy.with_name('Caddyfile.before-oikia.' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S'))
backup.write_text(original)
caddy.write_text(updated)
reload_command = ['docker', 'exec', 'filosafe-playground-caddy-1', 'caddy', 'reload', '--config', '/var/www/html/deploy/Caddyfile', '--adapter', 'caddyfile']
try:
    subprocess.run(reload_command, check=True)
    for path in ['/building_manager/', '/building_manager/up', '/filosafe/']:
        with urllib.request.urlopen('http://127.0.0.1' + path, timeout=20) as response:
            assert response.status == 200, path
except Exception:
    caddy.write_text(original)
    subprocess.run(reload_command, check=False)
    raise
print('Route activated: http://167.233.105.254/building_manager/')
print(f'Previous Caddy configuration saved at {backup}')
