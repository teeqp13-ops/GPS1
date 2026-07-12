<?php
require_once dirname(__DIR__).'/bootstrap.php'; api_auth(); $d=json_input(); require_fields($d,['code','device_uuid']);
$code=clean_code((string)$d['code']);$uuid=trim((string)$d['device_uuid']);$q=db()->prepare('SELECT * FROM licenses WHERE code=?');$q->execute([$code]);$l=$q->fetch();
if(!$l){log_api('check',$code,$uuid,404,'not_found');out(['status'=>'error','message'=>'code_not_found'],404);} 
if($l['status']!=='active'){log_api('check',$code,$uuid,403,$l['status']);out(['status'=>'error','message'=>'license_'.$l['status']],403);} 
if(!$l['device_uuid']||!hash_equals((string)$l['device_uuid'],$uuid)){log_api('check',$code,$uuid,409,'device_mismatch');out(['status'=>'error','message'=>'device_mismatch'],409);} 
if(!$l['expires_at']||strtotime($l['expires_at'])<=time()){db()->prepare("UPDATE licenses SET status='expired' WHERE id=?")->execute([$l['id']]);log_api('check',$code,$uuid,403,'expired');out(['status'=>'error','message'=>'code_expired'],403);} 
db()->prepare('UPDATE licenses SET last_seen_at=? WHERE id=?')->execute([now(),$l['id']]);$q->execute([$code]);$l=$q->fetch();log_api('check',$code,$uuid,200,'active');out(['status'=>'success','message'=>'valid','license'=>license_payload($l)]);
