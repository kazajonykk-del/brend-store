<?php
// Click Merchant API (Prepare / Complete). Click kabinetida:
//   Prepare URL  = https://sizning-domen.uz/click.php
//   Complete URL = https://sizning-domen.uz/click.php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
function ck($err,$note,$extra=[]){echo json_encode(array_merge($extra,['error'=>$err,'error_note'=>$note]),JSON_UNESCAPED_UNICODE);exit;}
$P=$_POST?:$_GET;
$ctid=(string)($P['click_trans_id']??'');$sid=(string)($P['service_id']??'');$mtid=(string)($P['merchant_trans_id']??'');
$mpid=(string)($P['merchant_prepare_id']??'');$amount=(string)($P['amount']??'');$action=(string)($P['action']??'');
$stime=(string)($P['sign_time']??'');$sign=(string)($P['sign_string']??'');$cerr=(int)($P['error']??0);
$base=['click_trans_id'=>$ctid,'merchant_trans_id'=>$mtid];
if(CLICK_SECRET===''||CLICK_SERVICE_ID==='')ck(-1,'Click yoqilmagan',$base);
$mine=md5($ctid.$sid.CLICK_SECRET.$mtid.($action==='1'?$mpid:'').$amount.$action.$stime);
if(!hash_equals($mine,$sign)||$sid!==(string)CLICK_SERVICE_ID)ck(-1,'SIGN CHECK FAILED!',$base);
if($action!=='0'&&$action!=='1')ck(-3,'Action not found',$base);
$s=$pdo->prepare('SELECT * FROM orders WHERE id=?');$s->execute([(int)$mtid]);$o=$s->fetch();
if(!$o)ck(-5,'Order not found',$base);
if(abs((float)$amount-(float)$o['total'])>0.01)ck(-2,'Incorrect parameter amount',$base);
if($o['pay_status']==='paid')ck(-4,'Already paid',$base);
if($o['status']==='cancel')ck(-9,'Transaction cancelled',$base);
if($action==='0'){
  $pdo->prepare('UPDATE orders SET pay_trans=? WHERE id=?')->execute(['click:'.$ctid,$o['id']]);
  ck(0,'Success',$base+['merchant_prepare_id'=>(int)$o['id']]);
}
// action = 1 (Complete)
if($mpid!==(string)$o['id']||$o['pay_trans']!=='click:'.$ctid)ck(-6,'Transaction not found',$base);
if($cerr<0)ck(-9,'Transaction cancelled',$base);
markPaid((int)$o['id'],'click','click:'.$ctid);
ck(0,'Success',$base+['merchant_confirm_id'=>(int)$o['id']]);
