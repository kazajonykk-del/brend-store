<?php
// XAMPP standart sozlamalari: phpMyAdmin dagi "brendstore" bazasi
$DB=['host'=>'127.0.0.1','name'=>'brendstore','user'=>'root','pass'=>''];
date_default_timezone_set('Asia/Tashkent');
session_start();
try{
  $pdo=new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4",$DB['user'],$DB['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}catch(Exception $e){
  http_response_code(500);header('Content-Type: application/json; charset=utf-8');
  die(json_encode(['error'=>"Bazaga ulanib bo'lmadi: ".$e->getMessage()],JSON_UNESCAPED_UNICODE));
}
const PROMOS=['VIP10'=>['pct',10],'SALE20000'=>['fix',20000]];
// ---- Sozlamalar (o'zingizga moslab o'zgartiring) ----
const CONTACT=['tg'=>'trendstore_admin','phone'=>'+998901234567'];
// hudud => usul => [narx, minimal kun, maksimal kun]; bepul yetkazish chegarasi: Aksiyalar bo'limida
const DELIVERY=[
 'tashkent'=>['courier'=>[20000,1,1],'pickup'=>[0,0,1]],
 'tashkent_obl'=>['courier'=>[30000,1,2],'pickup'=>[0,1,1]],
 'other'=>['courier'=>[40000,2,4],'pickup'=>[0,2,3]]];
// kategoriya => qaytarish (kun) va kafolat (oy); '*' = hammasi uchun
const WARRANTY=['*'=>['return'=>14],'Soatlar'=>['warranty'=>12,'return'=>14]];

// ================= ONLAYN TO'LOV (Click / Payme) =================
// Bo'sh qoldirilgan usul saytda ko'rinmaydi. Kalitlarni Click/Payme kabinetidan olasiz (README.txt ga qarang).
const SITE_URL='http://localhost/backend/trendstore';   // saytingiz manzili (oxirida / belgisiz). Hostingda: https://sizning-domen.uz
const PAYME_ID='';          // Payme: Merchant ID (kassa ID)
const PAYME_KEY='';         // Payme: ishchi (production) kalit
const PAYME_TEST_KEY='';    // Payme: test kaliti
const PAYME_TEST=true;      // true = test rejimi (test.paycom.uz); jonli ishga tushirishda false qiling
const CLICK_SERVICE_ID='';  // Click: service_id
const CLICK_MERCHANT_ID=''; // Click: merchant_id
const CLICK_USER_ID='';     // Click: merchant_user_id (kabinetda ko'rsatiladi)
const CLICK_SECRET='';      // Click: secret_key
// ================= TELEGRAM XABARNOMA =================
const TG_TOKEN='';          // @BotFather dan olingan bot tokeni
const TG_CHAT='';           // sizning chat ID (yoki guruh ID) — @userinfobot orqali bilib olasiz

function payEnabled(){return ['click'=>CLICK_SERVICE_ID!==''&&CLICK_MERCHANT_ID!==''&&CLICK_SECRET!=='','payme'=>PAYME_ID!==''&&(PAYME_TEST?PAYME_TEST_KEY:PAYME_KEY)!==''];}
function payUrl($oid,$pm){
  global $pdo;$s=$pdo->prepare('SELECT total FROM orders WHERE id=?');$s->execute([$oid]);$tot=(int)$s->fetchColumn();$ret=SITE_URL.'/index.html#orders';
  if($pm=='click')return 'https://my.click.uz/services/pay?'.http_build_query(['service_id'=>CLICK_SERVICE_ID,'merchant_id'=>CLICK_MERCHANT_ID,'amount'=>$tot,'transaction_param'=>$oid,'return_url'=>$ret]);
  if($pm=='payme'){$p='m='.PAYME_ID.';ac.order_id='.$oid.';a='.($tot*100).';l=uz;c='.$ret;return (PAYME_TEST?'https://test.paycom.uz/':'https://checkout.paycom.uz/').base64_encode($p);}
  return '';
}
function tg($text){
  if(TG_TOKEN===''||TG_CHAT==='')return;
  try{
    $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query(['chat_id'=>TG_CHAT,'text'=>$text]),'timeout'=>2,'ignore_errors'=>true]]);
    @file_get_contents('https://api.telegram.org/bot'.TG_TOKEN.'/sendMessage',false,$ctx);
  }catch(Throwable $e){}
}
function orderMsg($oid,$head){
  global $pdo;$s=$pdo->prepare('SELECT * FROM orders WHERE id=?');$s->execute([$oid]);$o=$s->fetch();if(!$o)return '';
  $s=$pdo->prepare('SELECT title,size,color,qty FROM order_items WHERE order_id=?');$s->execute([$oid]);$l='';
  foreach($s->fetchAll() as $i)$l.='• '.$i['title'].($i['size']?' ('.$i['size'].')':'').' × '.$i['qty']."\n";
  $pay=$o['pay_method']=='cash'?"Naqd / yetkazilganda":strtoupper($o['pay_method']).($o['pay_status']=='paid'?" (to'langan ✅)":" (to'lanmagan)");
  return $head.' #'.$oid."\n👤 ".$o['name'].' · '.$o['phone']."\n📍 ".$o['address']."\n".$l.'💰 '.number_format((int)$o['total'],0,'',' ')." so'm\n💳 ".$pay;
}
function markPaid($oid,$m,$tr){
  global $pdo;$s=$pdo->prepare("UPDATE orders SET pay_status='paid',pay_method=?,pay_trans=?,pay_time=NOW(),status=IF(status='new','confirmed',status) WHERE id=? AND pay_status<>'paid'");$s->execute([$m,$tr,$oid]);
  if($s->rowCount())tg(orderMsg($oid,"✅ To'lov qabul qilindi — buyurtma"));
}
function restockOrder($id){
  global $pdo;$s=$pdo->prepare('SELECT product_id,size,qty FROM order_items WHERE order_id=?');$s->execute([$id]);
  foreach($s->fetchAll() as $i){$g=$pdo->prepare('SELECT variants FROM products WHERE id=?');$g->execute([$i['product_id']]);$var=json_decode((string)$g->fetchColumn(),true)?:[];
    if(isset($var['sizes'][$i['size']]))$var['sizes'][$i['size']]['stock']+=$i['qty'];
    $pdo->prepare('UPDATE products SET stock=stock+?,variants=? WHERE id=?')->execute([$i['qty'],$var?json_encode($var):null,$i['product_id']]);}
}
// to'lanmagan onlayn buyurtmalar 30 daqiqadan keyin bekor qilinadi va zaxira qaytariladi
function expireUnpaid(){
  global $pdo;
  try{
  $ids=$pdo->query("SELECT id FROM orders WHERE pay_method IN ('click','payme') AND pay_status='unpaid' AND status='new' AND created_at<(NOW()-INTERVAL 30 MINUTE) AND id NOT IN (SELECT order_id FROM payme_tx WHERE state=1)")->fetchAll(PDO::FETCH_COLUMN);
  foreach($ids as $id){$pdo->beginTransaction();try{$pdo->prepare("UPDATE orders SET status='cancel',pay_status='cancelled' WHERE id=? AND status='new'")->execute([$id]);restockOrder($id);$pdo->commit();}catch(Exception $e){if($pdo->inTransaction())$pdo->rollBack();}}
  }catch(Throwable $e){}
}
function notifyBack($pid){
  global $pdo;$s=$pdo->prepare('SELECT name,stock FROM products WHERE id=?');$s->execute([$pid]);$p=$s->fetch();if(!$p||(int)$p['stock']<1)return;
  $s=$pdo->prepare('SELECT phone FROM notify_me WHERE product_id=?');$s->execute([$pid]);$ph=$s->fetchAll(PDO::FETCH_COLUMN);if(!$ph)return;
  tg('🔔 «'.$p['name'].'» yana omborda ('.$p['stock']." dona).\nKutayotgan mijozlar: ".count($ph)."\n+998".implode("\n+998",$ph));
}
