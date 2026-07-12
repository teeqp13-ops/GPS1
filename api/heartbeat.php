<?php
require_once dirname(__DIR__).'/bootstrap.php'; api_auth(); $d=json_input(); require_fields($d,['code','device_uuid']);
$code=clean_code((string)$d['code']);$uuid=trim((string)$d['device_uuid']);$q=db()->prepare('SELECT * FROM licenses WHERE code=?');$q->execute([$code]);$l=$q->fetch();
if(!$l||$l['status']!=='active'||!$l['device_uuid']||!hash_equals((string)$l['device_uuid'],$uuid)||!$l['expires_at']||strtotime($l['expires_at'])<=time()){log_api('heartbeat',$code,$uuid,403,'invalid');out(['status'=>'error','message'=>'license_invalid'],403);} 
db()->prepare('UPDATE licenses SET last_seen_at=? WHERE id=?')->execute([now(),$l['id']]);log_api('heartbeat',$code,$uuid,200,'ok');out(['status'=>'success','message'=>'ok','server_time'=>now(),'expires_at'=>$l['expires_at']]);
