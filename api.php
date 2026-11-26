<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
$a=$_GET['a']??'';
$in=json_decode(file_get_contents('php://input'),true); if(!is_array($in))$in=$_POST;
function out($d){echo json_encode($d,JSON_UNESCAPED_UNICODE);exit;}
function fail($m,$c=400){http_response_code($c);out(['error'=>$m]);}
function me(){global $pdo;if(empty($_SESSION['uid']))return null;$s=$pdo->prepare('SELECT id,name,phone,address,role,blocked FROM users WHERE id=?');$s->execute([$_SESSION['uid']]);$u=$s->fetch();return($u&&!$u['blocked'])?$u:null;}
function need(...$r){$u=me();if(!$u||!in_array($u['role'],$r))fail("Ruxsat yo'q",403);return $u;}
function ph($s){$d=preg_replace('/\D/','',(string)$s);if(strlen($d)==12&&substr($d,0,3)=='998')$d=substr($d,3);return $d;}
function lst($s){return array_values(array_filter(array_map('trim',explode(',',(string)$s))));}
function disc($sub,$c){if(!$c||!isset(PROMOS[$c]))return 0;[$t,$v]=PROMOS[$c];return min($sub,$t=='pct'?intdiv($sub*$v,100):$v);}
function setting($k,$d=null){global $pdo;$s=$pdo->prepare('SELECT v FROM settings WHERE k=?');$s->execute([$k]);$v=$s->fetchColumn();return $v===false?$d:$v;}
function ship($amt,$reg,$m){$r=DELIVERY[$reg][$m]??null;if(!$r)return 0;if($m=='courier'&&$amt>=(int)setting('free_ship',200000))return 0;return $r[0];}
function placeOrder($uid,$items,$promo,$name,$phone,$addr,$reg,$m,$pm='cash'){
  global $pdo;$pdo->beginTransaction();
  try{
    $sub=0;$rows=[];
    foreach($items as $it){$s=$pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');$s->execute([(int)$it['id']]);$p=$s->fetch();$q=max(1,(int)($it['qty']??1));$sz=$it['size']??'';
      if(!$p)throw new Exception('Mahsulot topilmadi');$var=json_decode((string)$p['variants'],true)?:[];$price=(int)$p['price'];
      if(isset($var['sizes'][$sz])){if($var['sizes'][$sz]['stock']<$q)throw new Exception($p['name'].' ('.$sz.'): zaxira yetarli emas');$price+=(int)$var['sizes'][$sz]['add'];}
      elseif($p['stock']<$q)throw new Exception($p['name'].': zaxira yetarli emas');
      $sub+=$price*$q;$rows[]=[$p,$it,$q,$price,$var,$sz];}
    $pc=strtoupper(trim((string)$promo));$d=disc($sub,$pc);$dl=ship($sub-$d,$reg,$m);
    $pdo->prepare('INSERT INTO orders(user_id,name,phone,address,total,discount,promo,delivery,region,method,pay_method) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$uid,$name,$phone,$addr,$sub-$d+$dl,$d,$d?$pc:null,$dl,$reg,$m,$pm]);
    $oid=$pdo->lastInsertId();
    foreach($rows as [$p,$it,$q,$price,$var,$sz]){
      $pdo->prepare('INSERT INTO order_items(order_id,product_id,title,size,color,qty,price) VALUES(?,?,?,?,?,?,?)')->execute([$oid,$p['id'],$p['name'],$sz,$it['color']??'',$q,$price]);
      if(isset($var['sizes'][$sz])){$var['sizes'][$sz]['stock']-=$q;$pdo->prepare('UPDATE products SET stock=GREATEST(0,stock-?),variants=? WHERE id=?')->execute([$q,json_encode($var),$p['id']]);}
      else $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?')->execute([$q,$p['id']]);}
    $pdo->commit();
    if($pm=='cash'){try{tg(orderMsg((int)$oid,'🛒 Yangi buyurtma'));}catch(Throwable $e){}}
    return (int)$oid;
  }catch(Exception $e){$pdo->rollBack();fail($e->getMessage());}
}
$staff=['admin','seller'];
if(in_array($a,['products','orders','settings','order']))expireUnpaid();
switch($a){
case 'me': out(['user'=>me()]);
case 'settings':
  $end=setting('promo_end');
  out(['free_ship'=>(int)setting('free_ship',200000),'promo_end'=>$end?strtotime($end)*1000:null,'now'=>time()*1000,'delivery'=>DELIVERY,'warranty'=>WARRANTY,'contact'=>CONTACT,'pay'=>payEnabled()]);
case 'setting_save':
  need('admin');$pe=trim($in['promo_end']??'');$fs=(int)($in['free_ship']??200000);
  if($pe&&!strtotime($pe))fail('Sana noto\'g\'ri');
  $pdo->prepare('REPLACE INTO settings(k,v) VALUES(?,?)')->execute(['promo_end',$pe?date('Y-m-d H:i:s',strtotime($pe)):'']);
  $pdo->prepare('REPLACE INTO settings(k,v) VALUES(?,?)')->execute(['free_ship',max(0,$fs)]);out(['ok'=>1]);
case 'products':
  $u=me();$st=$u&&in_array($u['role'],$staff);
  $r=$pdo->query('SELECT p.*,COALESCE(r.a,0) rating,COALESCE(r.c,0) rcount FROM products p LEFT JOIN (SELECT product_id,ROUND(AVG(rating),1) a,COUNT(*) c FROM reviews GROUP BY product_id) r ON r.product_id=p.id ORDER BY p.id')->fetchAll();
  foreach($r as &$p){foreach(['id','price','cost','stock','old_price','rcount'] as $k)$p[$k]=(int)$p[$k];$p['rating']=(float)$p['rating'];$p['sizes']=lst($p['sizes']);$p['images']=lst($p['images']);$p['descr']=(string)$p['descr'];
    $p['colors_raw']=(string)$p['colors'];$p['colors']=[];$p['cimg']=new stdClass;foreach(lst($p['colors_raw']) as $c){$x=explode(':',$c);$p['colors'][]=$x[0];if(isset($x[1]))$p['cimg']->{$x[0]}=(int)$x[1];}
    $v=json_decode((string)$p['variants'],true);$p['variants']=$v['sizes']??null;if($p['variants'])$p['sizes']=array_keys($p['variants']);if(!$st)unset($p['cost']);}
  out($r);
case 'register':
  $n=trim($in['name']??'');$p=ph($in['phone']??'');$pw=$in['pass']??'';
  if(!$n||strlen($p)<9||strlen($pw)<4)fail("Ism, telefon (9+ raqam) va parol (4+ belgi) kerak");
  try{$pdo->prepare("INSERT INTO users(name,phone,address,pass,role) VALUES(?,?,?,?,'customer')")->execute([$n,$p,trim($in['address']??''),password_hash($pw,PASSWORD_DEFAULT)]);}catch(PDOException $e){fail('Bu telefon raqam band');}
  $_SESSION['uid']=(int)$pdo->lastInsertId();out(['user'=>me()]);
case 'login':
  $p=ph($in['phone']??'');$s=$pdo->prepare('SELECT * FROM users WHERE phone=?');$s->execute([$p]);$u=$s->fetch();
  if(!$u||!password_verify($in['pass']??'',$u['pass']))fail("Telefon yoki parol noto'g'ri",401);
  if($u['blocked'])fail('Hisob bloklangan',403);
  $_SESSION['uid']=(int)$u['id'];out(['user'=>me()]);
case 'logout': session_destroy();out(['ok'=>1]);
case 'promo':
  $c=strtoupper(trim($in['code']??''));if(!isset(PROMOS[$c]))fail("Promokod topilmadi");out(['code'=>$c,'type'=>PROMOS[$c][0],'val'=>PROMOS[$c][1]]);
case 'order':
  $u=me();if(!$u)fail('Avval tizimga kiring',401);if(!($in['items']??[]))fail("Savat bo'sh");
  $pm=$in['pay']??'cash';$en=payEnabled();if(!in_array($pm,['click','payme'],true)||empty($en[$pm]))$pm='cash';
  $oid=placeOrder($u['id'],$in['items'],$in['promo']??'',trim($in['name']??'')?:$u['name'],trim($in['phone']??'')?:$u['phone'],trim($in['address']??''),$in['region']??'',$in['method']??'',$pm);
  out(['id'=>$oid,'pay_url'=>$pm=='cash'?'':payUrl($oid,$pm)]);
case 'quick_order':
  $u=me();$n=trim($in['name']??'');$p=ph($in['phone']??'');if(!$n||strlen($p)!=9)fail('Ism va telefon (9 raqam) kerak');
  out(['id'=>placeOrder($u?$u['id']:0,[['id'=>(int)$in['id'],'size'=>$in['size']??'','color'=>$in['color']??'','qty'=>max(1,(int)($in['qty']??1))]],'',$n,$p,trim($in['address']??''),$in['region']??'',$in['method']??'')]);
case 'track':
  $q=preg_replace('/\D/','',$in['q']??'');if(!$q)fail('Kod yoki telefon kiriting');
  if(strlen($q)<=7){$s=$pdo->prepare('SELECT * FROM orders WHERE id=?');$s->execute([(int)$q]);$o=$s->fetchAll();}
  else{$o=array_values(array_filter($pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 500')->fetchAll(),fn($r)=>ph($r['phone'])==ph($q)));}
  $r=[];foreach(array_slice($o,0,5) as $x){$s=$pdo->prepare('SELECT title,size,qty FROM order_items WHERE order_id=?');$s->execute([$x['id']]);$r[]=['id'=>(int)$x['id'],'status'=>$x['status'],'created_at'=>$x['created_at'],'total'=>(int)$x['total'],'items'=>$s->fetchAll()];}
  out($r);
case 'view':
  $id=(int)($in['id']??0);if(!session_id())session_start();$sid=session_id();
  $pdo->prepare('REPLACE INTO views(sid,product_id,ts) VALUES(?,?,?)')->execute([$sid,$id,time()]);$pdo->prepare('DELETE FROM views WHERE ts<?')->execute([time()-86400]);
  $s=$pdo->prepare('SELECT COUNT(*) FROM views WHERE product_id=? AND ts>?');$s->execute([$id,time()-120]);$v=(int)$s->fetchColumn();
  $s=$pdo->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items i ON i.order_id=o.id WHERE i.product_id=? AND DATE(o.created_at)=CURDATE() AND o.status!='cancel'");$s->execute([$id]);
  out(['viewing'=>max(1,$v),'today'=>(int)$s->fetchColumn()]);
case 'reviews':
  $s=$pdo->prepare('SELECT id,name,rating,text,created_at FROM reviews WHERE product_id=? ORDER BY id DESC LIMIT 20');$s->execute([(int)($in['id']??0)]);out($s->fetchAll());
case 'review_add':
  $u=me();if(!$u)fail('Sharh uchun tizimga kiring',401);$r=(int)($in['rating']??0);$t=trim($in['text']??'');if($r<1||$r>5)fail('Bahoni tanlang');
  $pdo->prepare('DELETE FROM reviews WHERE product_id=? AND user_id=?')->execute([(int)$in['id'],$u['id']]);
  $pdo->prepare('INSERT INTO reviews(product_id,user_id,name,rating,text) VALUES(?,?,?,?,?)')->execute([(int)$in['id'],$u['id'],$u['name'],$r,mb_substr($t,0,400)]);out(['ok'=>1]);
case 'review_delete': need('admin');$pdo->prepare('DELETE FROM reviews WHERE id=?')->execute([(int)$in['id']]);out(['ok'=>1]);
case 'orders':
  $u=need('admin','seller','customer');
  if(in_array($u['role'],$staff))$o=$pdo->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
  else{$s=$pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC');$s->execute([$u['id']]);$o=$s->fetchAll();}
  foreach($o as &$r){$r['id']=(int)$r['id'];$r['total']=(int)$r['total'];$r['discount']=(int)$r['discount'];$r['delivery']=(int)$r['delivery'];$s=$pdo->prepare('SELECT title,size,color,qty,price FROM order_items WHERE order_id=?');$s->execute([$r['id']]);$r['items']=$s->fetchAll();}
  out($o);
case 'order_status':
  need(...$staff);$id=(int)$in['id'];$st=$in['status']??'';
  if(!in_array($st,['new','confirmed','shipping','done','cancel']))fail('Holat xato');
  $s=$pdo->prepare('SELECT status FROM orders WHERE id=?');$s->execute([$id]);$cur=$s->fetchColumn();
  if(!$cur)fail('Buyurtma topilmadi',404);if($cur=='cancel')fail("Bekor qilingan buyurtmani o'zgartirib bo'lmaydi");
  if($st=='cancel'){$s=$pdo->prepare('SELECT product_id,size,qty FROM order_items WHERE order_id=?');$s->execute([$id]);
    foreach($s->fetchAll() as $i){$g=$pdo->prepare('SELECT variants FROM products WHERE id=?');$g->execute([$i['product_id']]);$var=json_decode((string)$g->fetchColumn(),true)?:[];
      if(isset($var['sizes'][$i['size']]))$var['sizes'][$i['size']]['stock']+=$i['qty'];
      $pdo->prepare('UPDATE products SET stock=stock+?,variants=? WHERE id=?')->execute([$i['qty'],$var?json_encode($var):null,$i['product_id']]);}}
  $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$st,$id]);
  if($st=='cancel')$pdo->prepare("UPDATE orders SET pay_status=IF(pay_status='paid','refund','cancelled') WHERE id=?")->execute([$id]);
  out(['ok'=>1]);
case 'stock':
  need(...$staff);$pdo->prepare('UPDATE products SET stock=GREATEST(0,stock+?) WHERE id=? AND (variants IS NULL OR variants="")')->execute([(int)$in['d'],(int)$in['id']]);notifyBack((int)$in['id']);out(['ok'=>1]);
case 'product_save':
  need('admin');$id=(int)($_POST['id']??0);
  $n=trim($_POST['name']??'');if(!$n||(int)($_POST['price']??0)<=0)fail('Nomi va narx kerak');
  $img='';if($id){$s=$pdo->prepare('SELECT images FROM products WHERE id=?');$s->execute([$id]);$img=(string)$s->fetchColumn();}
  if(!empty($_FILES['image']['tmp_name'])){$e=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
    if(!in_array($e,['jpg','jpeg','png','webp']))fail('Rasm: jpg, png yoki webp');
    $f='uploads/u'.time().rand(100,999).'.'.$e;move_uploaded_file($_FILES['image']['tmp_name'],__DIR__.'/img/'.$f);$img=trim($img.','.$f,',');}
  $bd=in_array($_POST['badge']??'',['new','hit'])?$_POST['badge']:'';
  $var=[];$sizes=[];$tot=0;foreach(preg_split('/\R/',trim($_POST['variants']??'')) as $ln){$x=array_map('trim',explode('|',$ln));if($x[0]==='')continue;$st=max(0,(int)($x[1]??0));$var[$x[0]]=['stock'=>$st,'add'=>(int)($x[2]??0)];$tot+=$st;}
  $stock=$var?$tot:max(0,(int)($_POST['stock']??0));$sz=$var?implode(',',array_keys($var)):trim($_POST['sizes']??'');
  $v=[$n,trim($_POST['category']??'')?:'Boshqa',(int)$_POST['price'],(int)($_POST['cost']??0),$stock,$sz,trim($_POST['colors']??''),trim($_POST['descr']??''),$img,max(0,(int)($_POST['old_price']??0)),$bd,$var?json_encode(['sizes'=>$var]):null];
  if($id){$pdo->prepare('UPDATE products SET name=?,category=?,price=?,cost=?,stock=?,sizes=?,colors=?,descr=?,images=?,old_price=?,badge=?,variants=? WHERE id=?')->execute([...$v,$id]);}
  else $pdo->prepare('INSERT INTO products(name,category,price,cost,stock,sizes,colors,descr,images,old_price,badge,variants) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute($v);
  if($id)notifyBack($id);
  out(['ok'=>1]);
case 'product_delete': need('admin');$pdo->prepare('DELETE FROM products WHERE id=?')->execute([(int)$in['id']]);out(['ok'=>1]);
case 'users': need('admin');out($pdo->query("SELECT id,name,phone,role,blocked FROM users WHERE role IN('admin','seller') ORDER BY id")->fetchAll());
case 'user_save':
  need('admin');$n=trim($in['name']??'');$p=ph($in['phone']??'');$pw=$in['pass']??'';
  if(!$n||strlen($p)<9||strlen($pw)<4)fail('Ism, telefon va parol (4+) kerak');
  try{$pdo->prepare("INSERT INTO users(name,phone,pass,role) VALUES(?,?,?,'seller')")->execute([$n,$p,password_hash($pw,PASSWORD_DEFAULT)]);}catch(PDOException $e){fail('Bu telefon raqam band');}out(['ok'=>1]);
case 'user_role':
  $u=need('admin');$id=(int)$in['id'];if($id==$u['id'])fail("O'zingizning rolingizni o'zgartirib bo'lmaydi");
  $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([($in['role']??'')=='admin'?'admin':'seller',$id]);out(['ok'=>1]);
case 'user_block':
  $u=need('admin');$id=(int)$in['id'];if($id==$u['id'])fail("O'zingizni bloklab bo'lmaydi");
  $pdo->prepare('UPDATE users SET blocked=1-blocked WHERE id=?')->execute([$id]);out(['ok'=>1]);
case 'banners':
  $u=me();$all=$u&&$u['role']=='admin';
  $r=$pdo->query('SELECT * FROM banners '.($all?'':'WHERE active=1 ').'ORDER BY sort,id')->fetchAll();foreach($r as &$b){$b['id']=(int)$b['id'];$b['active']=(int)$b['active'];}out($r);
case 'banner_save':
  need('admin');$t=trim($in['title']??'');if(!$t)fail('Sarlavha kerak');$im=preg_match('/^[\w\-\/\.]+$/',$in['image']??'')?$in['image']:'';
  $g=preg_match('/^#[0-9a-f]{6},#[0-9a-f]{6}$/i',$in['grad']??'')?$in['grad']:'#1e3a8a,#0e7490';
  $pdo->prepare('INSERT INTO banners(title,subtitle,cta,badge,category,image,grad,sort) VALUES(?,?,?,?,?,?,?,?)')->execute([$t,trim($in['subtitle']??''),trim($in['cta']??'')?:"Ko'rish",trim($in['badge']??''),trim($in['category']??'')?:'Barchasi',$im,$g,(int)$pdo->query('SELECT COALESCE(MAX(sort),0)+1 FROM banners')->fetchColumn()]);out(['ok'=>1]);
case 'banner_toggle': need('admin');$pdo->prepare('UPDATE banners SET active=1-active WHERE id=?')->execute([(int)$in['id']]);out(['ok'=>1]);
case 'banner_delete': need('admin');$pdo->prepare('DELETE FROM banners WHERE id=?')->execute([(int)$in['id']]);out(['ok'=>1]);
case 'profile_save':
  $u=need('admin','seller','customer');$n=trim($in['name']??'');$p=ph($in['phone']??'');
  if($n==='')fail('Ismingizni kiriting');
  if(strlen($p)!=9)fail("Telefon raqam 9 ta raqamdan iborat bo'lishi kerak");
  $s=$pdo->prepare('SELECT id FROM users WHERE phone=? AND id<>?');$s->execute([$p,$u['id']]);if($s->fetch())fail('Bu telefon raqam band');
  $pdo->prepare('UPDATE users SET name=?,phone=?,address=? WHERE id=?')->execute([mb_substr($n,0,100),$p,mb_substr(trim($in['address']??''),0,255),$u['id']]);
  out(['user'=>me()]);
case 'pass_change':
  $u=need('admin','seller','customer');$old=(string)($in['old']??'');$new=(string)($in['new']??'');
  $s=$pdo->prepare('SELECT pass FROM users WHERE id=?');$s->execute([$u['id']]);
  if(!password_verify($old,(string)$s->fetchColumn()))fail("Eski parol noto'g'ri");
  if(strlen($new)<6)fail("Yangi parol kamida 6 belgi bo'lsin");
  if($new===$old)fail('Yangi parol eskisidan farq qilsin');
  $pdo->prepare('UPDATE users SET pass=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$u['id']]);
  session_regenerate_id(true);out(['ok'=>1]);
case 'sync':
  // mode: get = serverdagini olish; merge = mahalliy + server birlashtiriladi; replace = serverni mahalliy bilan almashtirish
  $u=need('admin','seller','customer');$uid=(int)$u['id'];$mode=$in['mode']??'get';
  $ck=function($it){return (int)$it['id'].'|'.$it['size'].'|'.$it['color'];};
  if($mode!='get'){
    $lc=[];$lw=[];
    if($mode=='merge'){
      $s=$pdo->prepare('SELECT product_id id,size,color,qty FROM cart_items WHERE user_id=? ORDER BY id');$s->execute([$uid]);
      foreach($s->fetchAll() as $r){$lc[$ck($r)]=['id'=>(int)$r['id'],'size'=>$r['size'],'color'=>$r['color'],'qty'=>(int)$r['qty']];}
      $s=$pdo->prepare('SELECT product_id FROM wishlist WHERE user_id=? ORDER BY added');$s->execute([$uid]);
      foreach($s->fetchAll(PDO::FETCH_COLUMN) as $w)$lw[(int)$w]=(int)$w;
    }
    foreach((array)($in['cart']??[]) as $it){if(!is_array($it))continue;$id=(int)($it['id']??0);if($id<1)continue;
      $it=['id'=>$id,'size'=>mb_substr((string)($it['size']??''),0,40),'color'=>mb_substr((string)($it['color']??''),0,40),'qty'=>max(1,min(99,(int)($it['qty']??1)))];
      $k=$ck($it);$it['qty']=max($it['qty'],$lc[$k]['qty']??0);$lc[$k]=$it;}
    foreach((array)($in['wish']??[]) as $w){$w=(int)$w;if($w>0)$lw[$w]=$w;}
    $ex=array_flip(array_map('intval',$pdo->query('SELECT id FROM products')->fetchAll(PDO::FETCH_COLUMN)));
    $pdo->beginTransaction();
    try{
      $pdo->prepare('DELETE FROM cart_items WHERE user_id=?')->execute([$uid]);$pdo->prepare('DELETE FROM wishlist WHERE user_id=?')->execute([$uid]);
      $i1=$pdo->prepare('INSERT IGNORE INTO cart_items(user_id,product_id,size,color,qty) VALUES(?,?,?,?,?)');
      foreach($lc as $it)if(isset($ex[$it['id']]))$i1->execute([$uid,$it['id'],$it['size'],$it['color'],$it['qty']]);
      $i2=$pdo->prepare('INSERT IGNORE INTO wishlist(user_id,product_id) VALUES(?,?)');
      foreach($lw as $w)if(isset($ex[$w]))$i2->execute([$uid,$w]);
      $pdo->commit();
    }catch(Exception $e){$pdo->rollBack();fail('Sinxronlashda xatolik');}
  }
  $s=$pdo->prepare('SELECT c.product_id id,c.size,c.color,c.qty FROM cart_items c JOIN products p ON p.id=c.product_id WHERE c.user_id=? ORDER BY c.id');$s->execute([$uid]);
  $cart=array_map(fn($r)=>['id'=>(int)$r['id'],'size'=>$r['size'],'color'=>$r['color'],'qty'=>(int)$r['qty']],$s->fetchAll());
  $s=$pdo->prepare('SELECT w.product_id FROM wishlist w JOIN products p ON p.id=w.product_id WHERE w.user_id=? ORDER BY w.added,w.product_id');$s->execute([$uid]);
  out(['cart'=>$cart,'wish'=>array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN))]);
case 'pay_link':
  $u=need('admin','seller','customer');$id=(int)($in['id']??0);$pm=$in['pay']??'';$en=payEnabled();
  if(!in_array($pm,['click','payme'],true)||empty($en[$pm]))fail("Bu to'lov usuli yoqilmagan");
  $s=$pdo->prepare('SELECT * FROM orders WHERE id=?');$s->execute([$id]);$o=$s->fetch();
  if(!$o||($o['user_id']!=$u['id']&&!in_array($u['role'],$staff)))fail('Buyurtma topilmadi',404);
  if($o['pay_status']=='paid')fail("Bu buyurtma allaqachon to'langan");if($o['status']=='cancel')fail('Buyurtma bekor qilingan');
  out(['pay_url'=>payUrl($id,$pm)]);
case 'order_paid':
  need(...$staff);$id=(int)($in['id']??0);$s=$pdo->prepare('SELECT pay_method,status FROM orders WHERE id=?');$s->execute([$id]);$o=$s->fetch();
  if(!$o)fail('Buyurtma topilmadi',404);if($o['status']=='cancel')fail('Buyurtma bekor qilingan');
  markPaid($id,$o['pay_method']?:'cash','manual');out(['ok'=>1]);
case 'notify_add':
  $pid=(int)($in['id']??0);$p=ph($in['phone']??'');if(strlen($p)!=9)fail("Telefon raqam 9 ta raqamdan iborat bo'lsin");
  $s=$pdo->prepare('SELECT id FROM products WHERE id=?');$s->execute([$pid]);if(!$s->fetch())fail('Mahsulot topilmadi',404);
  $pdo->prepare('INSERT IGNORE INTO notify_me(product_id,phone,size) VALUES(?,?,?)')->execute([$pid,$p,mb_substr((string)($in['size']??''),0,40)]);out(['ok'=>1]);
case 'notify_list':
  need(...$staff);out($pdo->query('SELECT n.product_id,n.phone,n.size,n.created,p.name FROM notify_me n JOIN products p ON p.id=n.product_id ORDER BY n.product_id,n.id')->fetchAll());
case 'notify_done':
  need(...$staff);$pdo->prepare('DELETE FROM notify_me WHERE product_id=?')->execute([(int)($in['id']??0)]);out(['ok'=>1]);
case 'stats':
  need('admin');$days=[];for($i=29;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i day"));$days[$d]=['d'=>$d,'n'=>0,'rev'=>0];}
  foreach($pdo->query("SELECT DATE(created_at) d,COUNT(*) n,SUM(total) r FROM orders WHERE status<>'cancel' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(created_at)")->fetchAll() as $r){if(isset($days[$r['d']])){$days[$r['d']]['n']=(int)$r['n'];$days[$r['d']]['rev']=(int)$r['r'];}}
  $t=$pdo->query("SELECT COUNT(*) n,COALESCE(SUM(total),0) r FROM orders WHERE status<>'cancel'")->fetch();
  $today=(int)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancel' AND DATE(created_at)=CURDATE()")->fetchColumn();
  $month=(int)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancel' AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
  $profit=(int)$pdo->query("SELECT COALESCE(SUM(i.qty*(i.price-p.cost)),0) FROM order_items i JOIN orders o ON o.id=i.order_id JOIN products p ON p.id=i.product_id WHERE o.status<>'cancel'")->fetchColumn();
  $top=$pdo->query("SELECT i.title,SUM(i.qty) q,SUM(i.qty*i.price) r FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.status<>'cancel' GROUP BY i.product_id,i.title ORDER BY q DESC LIMIT 5")->fetchAll();
  $cats=$pdo->query("SELECT p.category c,SUM(i.qty*i.price) r FROM order_items i JOIN orders o ON o.id=i.order_id JOIN products p ON p.id=i.product_id WHERE o.status<>'cancel' GROUP BY p.category ORDER BY r DESC")->fetchAll();
  $pay=$pdo->query("SELECT pay_method m,COUNT(*) n,SUM(total) r FROM orders WHERE status<>'cancel' GROUP BY pay_method")->fetchAll();
  $low=$pdo->query('SELECT id,name,stock FROM products WHERE stock<5 ORDER BY stock,name LIMIT 8')->fetchAll();
  $wait=$pdo->query('SELECT p.name,COUNT(*) c FROM notify_me n JOIN products p ON p.id=n.product_id GROUP BY n.product_id,p.name ORDER BY c DESC LIMIT 5')->fetchAll();
  $n=(int)$t['n'];$rev=(int)$t['r'];
  out(['days'=>array_values($days),'today'=>$today,'month'=>$month,'total'=>$rev,'orders'=>$n,'avg'=>$n?intdiv($rev,$n):0,'profit'=>$profit,
    'top'=>array_map(fn($x)=>['title'=>$x['title'],'q'=>(int)$x['q'],'r'=>(int)$x['r']],$top),'cats'=>array_map(fn($x)=>['c'=>$x['c'],'r'=>(int)$x['r']],$cats),
    'pay'=>array_map(fn($x)=>['m'=>$x['m'],'n'=>(int)$x['n'],'r'=>(int)$x['r']],$pay),'low'=>array_map(fn($x)=>['id'=>(int)$x['id'],'name'=>$x['name'],'stock'=>(int)$x['stock']],$low),
    'wait'=>array_map(fn($x)=>['name'=>$x['name'],'c'=>(int)$x['c']],$wait)]);
case 'product_import':
  need('admin');$rows=$in['rows']??[];if(!is_array($rows)||!$rows)fail("Fayl bo'sh");if(count($rows)>2000)fail('Bir martada 2000 tagacha qator yuklash mumkin');
  $add=0;$upd=0;$bad=0;$pdo->beginTransaction();
  try{
    foreach($rows as $r){if(!is_array($r)){$bad++;continue;}
      $n=trim((string)($r['name']??''));$price=(int)preg_replace('/\D/','',(string)($r['price']??''));if($n===''||$price<=0){$bad++;continue;}
      $cat=trim((string)($r['category']??''))?:'Boshqa';$cost=(int)preg_replace('/\D/','',(string)($r['cost']??''));$old=(int)preg_replace('/\D/','',(string)($r['old_price']??''));
      $stock=max(0,(int)preg_replace('/\D/','',(string)($r['stock']??'')));$sizes=trim((string)($r['sizes']??''))?:'Standart';$colors=trim((string)($r['colors']??''));
      $bd=in_array($r['badge']??'',['new','hit'],true)?$r['badge']:'';$descr=trim((string)($r['descr']??''));$id=(int)($r['id']??0);
      if($id){$s=$pdo->prepare('SELECT id FROM products WHERE id=?');$s->execute([$id]);if(!$s->fetch())$id=0;}
      if(!$id){$s=$pdo->prepare('SELECT id FROM products WHERE name=? LIMIT 1');$s->execute([$n]);$id=(int)$s->fetchColumn();}
      if($id){
        $s=$pdo->prepare('SELECT variants FROM products WHERE id=?');$s->execute([$id]);$hv=trim((string)$s->fetchColumn())!=='';
        if($hv)$pdo->prepare('UPDATE products SET name=?,category=?,price=?,cost=?,colors=?,descr=?,old_price=?,badge=? WHERE id=?')->execute([$n,$cat,$price,$cost,$colors,$descr,$old,$bd,$id]);
        else $pdo->prepare('UPDATE products SET name=?,category=?,price=?,cost=?,stock=?,sizes=?,colors=?,descr=?,old_price=?,badge=? WHERE id=?')->execute([$n,$cat,$price,$cost,$stock,$sizes,$colors,$descr,$old,$bd,$id]);
        $upd++;
      }else{
        $pdo->prepare('INSERT INTO products(name,category,price,cost,stock,sizes,colors,descr,images,old_price,badge,variants) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$n,$cat,$price,$cost,$stock,$sizes,$colors,$descr,'',$old,$bd,null]);$add++;
      }
    }
    $pdo->commit();
  }catch(Exception $e){$pdo->rollBack();fail('Import xatosi: '.$e->getMessage());}
  out(['added'=>$add,'updated'=>$upd,'skipped'=>$bad]);
default: fail("Noma'lum amal",404);
}
