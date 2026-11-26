<?php
// Payme Merchant API (JSON-RPC 2.0). Payme kabinetida "Endpoint URL" ga shu faylning manzilini kiriting:
//   https://sizning-domen.uz/payme.php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
const PM_TIMEOUT=43200000; // 12 soat (ms)
$KEY=PAYME_TEST?PAYME_TEST_KEY:PAYME_KEY;
$req=json_decode(file_get_contents('php://input'),true);
$id=is_array($req)?($req['id']??null):null;
function pm_res($id,$r){echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>$r],JSON_UNESCAPED_UNICODE);exit;}
function pm_err($id,$code,$msg,$data=null){echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>['uz'=>$msg,'ru'=>$msg,'en'=>$msg],'data'=>$data]],JSON_UNESCAPED_UNICODE);exit;}
function pm_now(){return (int)(microtime(true)*1000);}
// ---- avtorizatsiya: Basic base64("Paycom:KALIT") ----
$h=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
if(!$h&&function_exists('getallheaders')){foreach(getallheaders() as $k=>$v)if(strtolower($k)==='authorization')$h=$v;}
$ok=false;
if($KEY!==''){
  if(preg_match('/^Basic\s+(.+)$/i',$h,$m))$ok=hash_equals('Paycom:'.$KEY,(string)base64_decode($m[1]));
  elseif(isset($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']))$ok=hash_equals('Paycom:'.$KEY,$_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW']);
}
if(!$ok)pm_err($id,-32504,'Insufficient privileges to perform this method.');
if(!is_array($req)||empty($req['method']))pm_err(null,-32700,'Parse error');
$method=$req['method'];$p=is_array($req['params']??null)?$req['params']:[];

function pm_order($id,$p){
  global $pdo;$oid=(int)($p['account']['order_id']??0);$s=$pdo->prepare('SELECT * FROM orders WHERE id=?');$s->execute([$oid]);$o=$s->fetch();
  if(!$o)pm_err($id,-31050,'Order not found','order_id');
  if($o['pay_status']==='paid'||$o['status']==='cancel')pm_err($id,-31051,'Order cannot be paid','order_id');
  if(((int)$o['total'])*100!==(int)($p['amount']??-1))pm_err($id,-31001,'Wrong amount');
  return $o;
}
function pm_tx($id,$p){
  global $pdo;$s=$pdo->prepare('SELECT * FROM payme_tx WHERE paycom_id=?');$s->execute([(string)($p['id']??'')]);$t=$s->fetch();
  if(!$t)pm_err($id,-31003,'Transaction not found');return $t;
}
function pm_reason($t){return $t['reason']===null?null:(int)$t['reason'];}

switch($method){
case 'CheckPerformTransaction':
  pm_order($id,$p);pm_res($id,['allow'=>true]);
case 'CreateTransaction':
  $s=$pdo->prepare('SELECT * FROM payme_tx WHERE paycom_id=?');$s->execute([(string)($p['id']??'')]);$t=$s->fetch();
  if($t){
    if((int)$t['state']!==1)pm_err($id,-31008,'Unable to perform operation');
    if(pm_now()-(int)$t['create_time']>PM_TIMEOUT){$pdo->prepare('UPDATE payme_tx SET state=-1,reason=4,cancel_time=? WHERE id=?')->execute([pm_now(),$t['id']]);pm_err($id,-31008,'Transaction timed out');}
    pm_res($id,['create_time'=>(int)$t['create_time'],'transaction'=>(string)$t['id'],'state'=>1]);
  }
  $o=pm_order($id,$p);
  $s=$pdo->prepare('SELECT id FROM payme_tx WHERE order_id=? AND state=1');$s->execute([$o['id']]);
  if($s->fetch())pm_err($id,-31050,'Order has another pending transaction','order_id');
  $now=pm_now();
  $pdo->prepare('INSERT INTO payme_tx(paycom_id,paycom_time,create_time,state,amount,order_id) VALUES(?,?,?,1,?,?)')->execute([(string)$p['id'],(int)($p['time']??$now),$now,(int)$p['amount'],$o['id']]);
  pm_res($id,['create_time'=>$now,'transaction'=>(string)$pdo->lastInsertId(),'state'=>1]);
case 'PerformTransaction':
  $t=pm_tx($id,$p);
  if((int)$t['state']===1){
    if(pm_now()-(int)$t['create_time']>PM_TIMEOUT){$pdo->prepare('UPDATE payme_tx SET state=-1,reason=4,cancel_time=? WHERE id=?')->execute([pm_now(),$t['id']]);pm_err($id,-31008,'Transaction timed out');}
    $now=pm_now();$pdo->prepare('UPDATE payme_tx SET state=2,perform_time=? WHERE id=?')->execute([$now,$t['id']]);
    markPaid((int)$t['order_id'],'payme',(string)$t['paycom_id']);
    pm_res($id,['transaction'=>(string)$t['id'],'perform_time'=>$now,'state'=>2]);
  }
  if((int)$t['state']===2)pm_res($id,['transaction'=>(string)$t['id'],'perform_time'=>(int)$t['perform_time'],'state'=>2]);
  pm_err($id,-31008,'Unable to perform operation');
case 'CancelTransaction':
  $t=pm_tx($id,$p);$now=pm_now();$reason=(int)($p['reason']??0);
  if((int)$t['state']===1){
    $pdo->prepare('UPDATE payme_tx SET state=-1,reason=?,cancel_time=? WHERE id=?')->execute([$reason,$now,$t['id']]);
    pm_res($id,['transaction'=>(string)$t['id'],'cancel_time'=>$now,'state'=>-1]);
  }
  if((int)$t['state']===2){
    $s=$pdo->prepare('SELECT status FROM orders WHERE id=?');$s->execute([$t['order_id']]);$st=$s->fetchColumn();
    if(in_array($st,['shipping','done'],true))pm_err($id,-31007,'Order already completed, cannot cancel');
    $pdo->beginTransaction();
    try{
      $pdo->prepare('UPDATE payme_tx SET state=-2,reason=?,cancel_time=? WHERE id=?')->execute([$reason,$now,$t['id']]);
      if($st!=='cancel'){$pdo->prepare("UPDATE orders SET status='cancel',pay_status='refunded' WHERE id=?")->execute([$t['order_id']]);restockOrder((int)$t['order_id']);}
      else $pdo->prepare("UPDATE orders SET pay_status='refunded' WHERE id=?")->execute([$t['order_id']]);
      $pdo->commit();
    }catch(Exception $e){$pdo->rollBack();pm_err($id,-31008,'Unable to perform operation');}
    tg("↩ Payme to'lovi qaytarildi — buyurtma #".$t['order_id']);
    pm_res($id,['transaction'=>(string)$t['id'],'cancel_time'=>$now,'state'=>-2]);
  }
  pm_res($id,['transaction'=>(string)$t['id'],'cancel_time'=>(int)$t['cancel_time'],'state'=>(int)$t['state']]);
case 'CheckTransaction':
  $t=pm_tx($id,$p);
  pm_res($id,['create_time'=>(int)$t['create_time'],'perform_time'=>(int)$t['perform_time'],'cancel_time'=>(int)$t['cancel_time'],'transaction'=>(string)$t['id'],'state'=>(int)$t['state'],'reason'=>pm_reason($t)]);
case 'GetStatement':
  $s=$pdo->prepare('SELECT * FROM payme_tx WHERE paycom_time>=? AND paycom_time<=? ORDER BY paycom_time');$s->execute([(int)($p['from']??0),(int)($p['to']??0)]);
  $r=[];foreach($s->fetchAll() as $t)$r[]=['id'=>$t['paycom_id'],'time'=>(int)$t['paycom_time'],'amount'=>(int)$t['amount'],'account'=>['order_id'=>(string)$t['order_id']],'create_time'=>(int)$t['create_time'],'perform_time'=>(int)$t['perform_time'],'cancel_time'=>(int)$t['cancel_time'],'transaction'=>(string)$t['id'],'state'=>(int)$t['state'],'reason'=>pm_reason($t)];
  pm_res($id,['transactions'=>$r]);
default:
  pm_err($id,-32601,'Method not found',$method);
}
