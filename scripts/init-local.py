#!/usr/bin/env python3
"""Prepare local secrets without replacing existing configuration."""
from pathlib import Path
import secrets
root=Path(__file__).resolve().parents[1]
local=root/'.local';local.mkdir(exist_ok=True);local.chmod(0o700)
compose=local/'compose.env'
if not compose.exists():compose.write_text('DB_PASSWORD='+secrets.token_hex(24)+'\nDB_ROOT_PASSWORD='+secrets.token_hex(24)+'\n');compose.chmod(0o600)
values=dict(line.split('=',1) for line in compose.read_text().splitlines())
env=root/'backend/.env'
if not env.exists():env.write_text((root/'backend/.env.example').read_text().replace('DB_PASSWORD=','DB_PASSWORD='+values['DB_PASSWORD']));env.chmod(0o600)
print('Local configuration ready. Existing values were preserved.')
