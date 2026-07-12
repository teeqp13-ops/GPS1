<?php
require_once dirname(__DIR__).'/bootstrap.php'; api_auth(); $d=json_input(); require_fields($d,['code','device_uuid']);
$code=clean_code((string)$d['code']); $uuid=trim((string)$d['device_uuid']);
$q=db()->prepare('SELECT * FROM licenses WHERE code=?'); $q->execute([$code]); $l=$q->fetch();
if(!$l){log_api('activate',$code,$uuid,404,'not_found');out(['status'=>'error','message'=>'code_not_found'],404);} 
if($l['status']==='disabled'){log_api('activate',$code,$uuid,403,'disabled');out(['status'=>'error','message'=>'code_disabled'],403);} 
if($l['device_uuid'] && !hash_equals((string)$l['device_uuid'],$uuid)){log_api('activate',$code,$uuid,409,'device_mismatch');out(['status'=>'error','message'=>'code_used_on_another_device'],409);} 
$activated=$l['activated_at']?:now(); $expires=$l['expires_at']?:date('Y-m-d H:i:s',strtotime($activated.' +'.(int)$l['duration_days'].' days'));
if(strtotime($expires)<=time()){db()->prepare("UPDATE licenses SET status='expired' WHERE id=?")->execute([$l['id']]);log_api('activate',$code,$uuid,403,'expired');out(['status'=>'error','message'=>'code_expired','expires_at'=>$expires],403);} 
$u=db()->prepare("UPDATE licenses SET status='active',device_uuid=?,device_name=?,ios_version=?,app_version=?,bundle_id=?,activated_at=?,expires_at=?,last_seen_at=? WHERE id=?");
$u->execute([$uuid,$d['device_name']??'', $d['ios_version']??'', $d['app_version']??'', $d['bundle_id']??'', $activated,$expires,now(),$l['id']]);
$q->execute([$code]);$l=$q->fetch();log_api('activate',$code,$uuid,200,'active');out(['status'=>'success','message'=>'activated','license'=>license_payload($l)]);
