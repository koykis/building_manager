#!/usr/bin/env python3
"""Back up the local compose database and private sources; optionally restore to an isolated drill DB."""
import argparse,subprocess,pathlib,datetime,tarfile,hashlib,json,re
root=pathlib.Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('--verify-restore',action='store_true');args=parser.parse_args()
out=root/'.local'/'backups'/datetime.datetime.now().strftime('%Y%m%d-%H%M%S');out.mkdir(parents=True,exist_ok=False);out.chmod(0o700)
compose=['docker','compose','--project-directory',str(root),'--env-file',str(root/'.local/compose.env'),'exec','-T','db']
def sql(query,database=None):
 command=compose+['sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --batch --skip-column-names '+(database or '')]
 return subprocess.run(command,input=query.encode(),stdout=subprocess.PIPE,check=True).stdout
with (out/'database.sql').open('wb') as f:subprocess.run(compose+['sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --no-tablespaces --set-gtid-purged=OFF building_manager'],stdout=f,check=True)
private=root/'backend/storage/app/private'
with tarfile.open(out/'private-documents.tar.gz','w:gz') as archive:archive.add(private,arcname='private')
manifest={str(p.relative_to(private)):hashlib.sha256(p.read_bytes()).hexdigest() for p in private.rglob('*') if p.is_file()}
(out/'document-hashes.json').write_text(json.dumps(manifest,indent=2))
# Preserve application encryption key for an actual recovery, without printing it.
(out/'application.env').write_bytes((root/'backend/.env').read_bytes())
for f in out.iterdir():f.chmod(0o600)
result={'backup':str(out),'documents':len(manifest),'restore_verified':False}
if args.verify_restore:
 name='building_restore_'+datetime.datetime.now().strftime('%Y%m%d%H%M%S');assert re.fullmatch(r'building_restore_\d+',name)
 sql('CREATE DATABASE `'+name+'` CHARACTER SET utf8mb4;')
 with (out/'database.sql').open('rb') as f:subprocess.run(compose+['sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot '+name],stdin=f,check=True)
 tables=sql('SHOW TABLES','building_manager').decode().splitlines()
 counts={}
 for table in tables:
  assert re.fullmatch(r'[a-z_]+',table)
  original=sql('SELECT COUNT(*) FROM `'+table+'`','building_manager').strip();restored=sql('SELECT COUNT(*) FROM `'+table+'`',name).strip();assert original==restored,(table,original,restored);counts[table]=int(restored)
 with tarfile.open(out/'private-documents.tar.gz','r:gz') as archive:
  destination=out/'restore-documents';archive.extractall(destination,filter='data')
 for file,expected in manifest.items():assert hashlib.sha256((destination/'private'/file).read_bytes()).hexdigest()==expected,file
 result.update({'restore_verified':True,'restored_database':name,'row_counts':counts})
 # Keep the isolated drill DB for inspection; never overwrite the live database.
(out/'verification.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
