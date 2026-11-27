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
function tgNotify($t){if(!defined('TG_TOKEN')||!TG_TOKEN||!TG_CHAT)return;@file_get_contents('https://api.telegram.org/bot'.TG_TOKEN.'/sendMessage?'.http_build_query(['chat_id'=>TG_CHAT,'text'=>$t]),false,stream_context_create(['http'=>['timeout'=>3]]));}
function xt(){global $pdo;static $d=0;if($d)return;$d=1;
 $pdo->exec('CREATE TABLE IF NOT EXISTS user_data(user_id INT PRIMARY KEY,wish TEXT,cart TEXT) CHARACTER SET utf8mb4');
 $pdo->exec('CREATE TABLE IF NOT EXISTS loyalty(user_id INT PRIMARY KEY,pts INT NOT NULL DEFAULT 0) CHARACTER SET utf8mb4');
 $pdo->exec('CREATE TABLE IF NOT EXISTS notify(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,phone VARCHAR(20) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY pp(product_id,phone)) CHARACTER SET utf8mb4');}
function pts($uid){global $pdo;xt();$s=$pdo->prepare('SELECT pts FROM loyalty WHERE user_id=?');$s->execute([$uid]);return (int)$s->fetchColumn();}
$staff=['admin','seller'];
switch($a){
case 'live':
  $n=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE() AND status<>'cancel'")->fetchColumn();
  out(['today'=>$n]);
case 'track':
  $q=trim((string)($in['q']??''));$d=preg_replace('/\D/','',$q);if($d==='')fail('Buyurtma kodi yoki telefon raqamini kiriting');
  if(strlen($d)>=9){$s=$pdo->prepare('SELECT id,status,total,created_at FROM orders WHERE phone LIKE ? ORDER BY id DESC LIMIT 5');$s->execute(['%'.substr($d,-9)]);}
  else{$s=$pdo->prepare('SELECT id,status,total,created_at FROM orders WHERE id=?');$s->execute([(int)$d]);}
  $r=$s->fetchAll();if(!$r)fail('Buyurtma topilmadi',404);
  foreach($r as &$o){$o['id']=(int)$o['id'];$o['total']=(int)$o['total'];}
  out($r);
case 'quick_order':
  $nm=trim((string)($in['name']??''));$tel=ph($in['phone']??'');if($nm==='')fail('Ismingizni kiriting');if(strlen($tel)!=9)fail("Telefon raqam 9 ta raqamdan iborat bo'lishi kerak");
  $q=max(1,(int)($in['qty']??1));
  $pdo->beginTransaction();
  try{
    $s=$pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');$s->execute([(int)($in['id']??0)]);$p=$s->fetch();
    if(!$p||$p['stock']<$q)throw new Exception(($p['name']??'Mahsulot').': zaxira yetarli emas');
    $u=me();$tot=$p['price']*$q;
    $pdo->prepare('INSERT INTO orders(user_id,name,phone,address,total,discount,promo) VALUES(?,?,?,?,?,0,NULL)')->execute([$u?$u['id']:0,$nm,'998'.$tel,trim((string)($in['address']??'')),$tot]);
    $oid=$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO order_items(order_id,product_id,title,size,color,qty,price) VALUES(?,?,?,?,?,?,?)')->execute([$oid,$p['id'],$p['name'],(string)($in['size']??''),(string)($in['color']??''),$q,$p['price']]);
    $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?')->execute([$q,$p['id']]);
    $pdo->commit();tgNotify("Tezkor buyurtma #$oid\n$nm · +998$tel\n".$p['name']." × $q = ".number_format($tot,0,'',' ')." so'm");out(['id'=>(int)$oid,'total'=>$tot]);
  }catch(Exception $e){$pdo->rollBack();fail($e->getMessage());}
case 'ratings':
  $pdo->exec('CREATE TABLE IF NOT EXISTS reviews(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,user_id INT NOT NULL,name VARCHAR(100),rating TINYINT NOT NULL,text VARCHAR(400) DEFAULT \'\',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY pu(product_id,user_id)) CHARACTER SET utf8mb4');
  $r=$pdo->query('SELECT product_id,ROUND(AVG(rating),1) avg,COUNT(*) n FROM reviews GROUP BY product_id')->fetchAll();$m=[];
  foreach($r as $x)$m[(int)$x['product_id']]=['avg'=>(float)$x['avg'],'n'=>(int)$x['n']];
  out($m);
case 'reviews':
  $pdo->exec('CREATE TABLE IF NOT EXISTS reviews(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,user_id INT NOT NULL,name VARCHAR(100),rating TINYINT NOT NULL,text VARCHAR(400) DEFAULT \'\',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY pu(product_id,user_id)) CHARACTER SET utf8mb4');
  $s=$pdo->prepare('SELECT name,rating,text,created_at FROM reviews WHERE product_id=? ORDER BY id DESC LIMIT 20');$s->execute([(int)($in['id']??0)]);
  out($s->fetchAll());
case 'review_save':
  $u=me();if(!$u)fail('Sharh yozish uchun avval tizimga kiring',401);
  $pid=(int)($in['id']??0);$rt=(int)($in['rating']??0);if($rt<1||$rt>5)fail('Bahoni tanlang (1–5 yulduz)');
  $tx=mb_substr(trim((string)($in['text']??'')),0,400);
  $pdo->exec('CREATE TABLE IF NOT EXISTS reviews(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,user_id INT NOT NULL,name VARCHAR(100),rating TINYINT NOT NULL,text VARCHAR(400) DEFAULT \'\',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY pu(product_id,user_id)) CHARACTER SET utf8mb4');
  $pdo->prepare('INSERT INTO reviews(product_id,user_id,name,rating,text) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),text=VALUES(text),name=VALUES(name)')->execute([$pid,$u['id'],$u['name'],$rt,$tx]);
  out(['ok'=>1]);
case 'sync_get':
  $u=me();if(!$u)out(['wish'=>[],'cart'=>[],'pts'=>0]);xt();
  $s=$pdo->prepare('SELECT wish,cart FROM user_data WHERE user_id=?');$s->execute([$u['id']]);$r=$s->fetch();
  out(['wish'=>json_decode($r['wish']??'[]',true)?:[],'cart'=>json_decode($r['cart']??'[]',true)?:[],'pts'=>pts($u['id'])]);
case 'sync_set':
  $u=me();if(!$u)out(['ok'=>0]);xt();
  $w=json_encode(array_values(array_map('intval',(array)($in['wish']??[]))));$c=json_encode(array_slice((array)($in['cart']??[]),0,60));
  $pdo->prepare('INSERT INTO user_data(user_id,wish,cart) VALUES(?,?,?) ON DUPLICATE KEY UPDATE wish=VALUES(wish),cart=VALUES(cart)')->execute([$u['id'],$w,$c]);out(['ok'=>1]);
case 'profile_save':
  $u=me();if(!$u)fail('Avval tizimga kiring',401);$n=trim((string)($in['name']??''));if($n==='')fail('Ismni kiriting');
  $pdo->prepare('UPDATE users SET name=?,address=? WHERE id=?')->execute([mb_substr($n,0,100),mb_substr(trim((string)($in['address']??'')),0,255),$u['id']]);out(['user'=>me()]);
case 'pass_change':
  $u=me();if(!$u)fail('Avval tizimga kiring',401);$new=(string)($in['new']??'');if(strlen($new)<6)fail("Yangi parol kamida 6 belgi bo'lsin");
  $s=$pdo->prepare('SELECT pass FROM users WHERE id=?');$s->execute([$u['id']]);if(!password_verify((string)($in['old']??''),$s->fetchColumn()))fail("Joriy parol noto'g'ri");
  $pdo->prepare('UPDATE users SET pass=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$u['id']]);out(['ok'=>1]);
case 'notify_save':
  xt();$tel=ph($in['phone']??'');if(strlen($tel)!=9)fail("Telefon raqam 9 ta raqamdan iborat bo'lishi kerak");
  $pdo->prepare('INSERT IGNORE INTO notify(product_id,phone) VALUES(?,?)')->execute([(int)($in['id']??0),'998'.$tel]);out(['ok'=>1]);
case 'stats':
  need(...$staff);xt();
  $days=$pdo->query("SELECT DATE(created_at) d,COUNT(*) n,SUM(total) t FROM orders WHERE status<>'cancel' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY d ORDER BY d")->fetchAll();
  $mon=$pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') m,COUNT(*) n,SUM(total) t FROM orders WHERE status<>'cancel' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY m ORDER BY m")->fetchAll();
  $top=$pdo->query("SELECT i.title,SUM(i.qty) q,SUM(i.qty*i.price) t FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.status<>'cancel' GROUP BY i.title ORDER BY q DESC LIMIT 8")->fetchAll();
  $nt=$pdo->query("SELECT p.name,COUNT(*) n FROM notify n JOIN products p ON p.id=n.product_id GROUP BY n.product_id ORDER BY n DESC LIMIT 10")->fetchAll();
  out(['days'=>$days,'months'=>$mon,'top'=>$top,'notify'=>$nt]);
case 'product_import':
  need('admin');$n=0;
  foreach((array)($in['rows']??[]) as $r){$nm=trim((string)($r['name']??''));if($nm===''||(int)($r['price']??0)<=0)continue;
    $s=$pdo->prepare('SELECT id FROM products WHERE name=?');$s->execute([$nm]);$id=$s->fetchColumn();
    $v=[trim((string)($r['category']??''))?:'Boshqa',(int)$r['price'],(int)($r['cost']??0),max(0,(int)($r['stock']??0)),(string)($r['sizes']??''),(string)($r['colors']??''),(string)($r['descr']??''),(int)($r['old_price']??0),in_array($r['badge']??'',['new','hit'])?$r['badge']:''];
    if($id)$pdo->prepare('UPDATE products SET category=?,price=?,cost=?,stock=?,sizes=?,colors=?,descr=?,old_price=?,badge=? WHERE id=?')->execute([...$v,$id]);
    else $pdo->prepare("INSERT INTO products(category,price,cost,stock,sizes,colors,descr,old_price,badge,name,images) VALUES(?,?,?,?,?,?,?,?,?,?,'')")->execute([...$v,$nm]);$n++;}
  out(['n'=>$n]);
case 'me': out(['user'=>me()]);
case 'products':
  $u=me();$st=$u&&in_array($u['role'],$staff);
  $r=$pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
  foreach($r as &$p){foreach(['id','price','cost','stock','old_price'] as $k)$p[$k]=(int)$p[$k];$p['sizes']=lst($p['sizes']);$p['colors']=lst($p['colors']);$p['images']=lst($p['images']);$p['descr']=(string)$p['descr'];if(!$st)unset($p['cost']);}
  out($r);
case 'register':
  $n=trim($in['name']??'');$ph=ph($in['phone']??'');$pw=$in['pass']??'';
  if(!$n||strlen($ph)<9||strlen($pw)<4)fail("Ism, telefon (9+ raqam) va parol (4+ belgi) kerak");
  try{$pdo->prepare("INSERT INTO users(name,phone,address,pass,role) VALUES(?,?,?,?,'customer')")->execute([$n,$ph,trim($in['address']??''),password_hash($pw,PASSWORD_DEFAULT)]);}catch(PDOException $e){fail('Bu telefon raqam band');}
  $_SESSION['uid']=(int)$pdo->lastInsertId();out(['user'=>me()]);
case 'login':
  $ph=ph($in['phone']??'');$s=$pdo->prepare('SELECT * FROM users WHERE phone=?');$s->execute([$ph]);$u=$s->fetch();
  if(!$u||!password_verify($in['pass']??'',$u['pass']))fail("Telefon yoki parol noto'g'ri",401);
  if($u['blocked'])fail('Hisob bloklangan',403);
  $_SESSION['uid']=(int)$u['id'];out(['user'=>me()]);
case 'logout': session_destroy();out(['ok'=>1]);
case 'promo':
  $c=strtoupper(trim($in['code']??''));if(!isset(PROMOS[$c]))fail("Promokod topilmadi");out(['code'=>$c,'type'=>PROMOS[$c][0],'val'=>PROMOS[$c][1]]);
case 'order':
  $u=me();if(!$u)fail('Avval tizimga kiring',401);
  $items=$in['items']??[];if(!$items)fail("Savat bo'sh");
  $pdo->beginTransaction();
  try{
    $sub=0;$rows=[];
    foreach($items as $it){$s=$pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');$s->execute([(int)$it['id']]);$p=$s->fetch();$q=max(1,(int)$it['qty']);
      if(!$p||$p['stock']<$q)throw new Exception(($p['name']??'Mahsulot').': zaxira yetarli emas');
      $sub+=$p['price']*$q;$rows[]=[$p,$it,$q];}
    $pc=strtoupper(trim($in['promo']??''));$d=disc($sub,$pc);$pu=0;if(!empty($in['pts'])){$have=pts($u['id']);$pu=min($have,intdiv(max(0,$sub-$d),500));$d+=$pu*500;}
    $pdo->prepare('INSERT INTO orders(user_id,name,phone,address,total,discount,promo) VALUES(?,?,?,?,?,?,?)')->execute([$u['id'],trim($in['name']??'')?:$u['name'],trim($in['phone']??'')?:$u['phone'],trim($in['address']??''),$sub-$d,$d,$d?$pc:null]);
    $oid=$pdo->lastInsertId();
    foreach($rows as [$p,$it,$q]){
      $pdo->prepare('INSERT INTO order_items(order_id,product_id,title,size,color,qty,price) VALUES(?,?,?,?,?,?,?)')->execute([$oid,$p['id'],$p['name'],$it['size']??'',$it['color']??'',$q,$p['price']]);
      $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?')->execute([$q,$p['id']]);}
    xt();$earn=intdiv($sub-$d,10000);$pdo->prepare('INSERT INTO loyalty(user_id,pts) VALUES(?,?) ON DUPLICATE KEY UPDATE pts=GREATEST(0,pts-?+?)')->execute([$u['id'],max(0,$earn-$pu),$pu,$earn]);
    $pdo->commit();tgNotify("Yangi buyurtma #$oid\n".(trim($in['name']??'')?:$u['name']).' · '.(trim($in['phone']??'')?:$u['phone'])."\nJami: ".number_format($sub-$d,0,'',' ')." so'm");out(['id'=>(int)$oid,'total'=>$sub-$d,'earned'=>$earn]);
  }catch(Exception $e){$pdo->rollBack();fail($e->getMessage());}
case 'orders':
  $u=need('admin','seller','customer');
  if(in_array($u['role'],$staff))$o=$pdo->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
  else{$s=$pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC');$s->execute([$u['id']]);$o=$s->fetchAll();}
  foreach($o as &$r){$r['id']=(int)$r['id'];$r['total']=(int)$r['total'];$r['discount']=(int)$r['discount'];$s=$pdo->prepare('SELECT title,size,color,qty,price FROM order_items WHERE order_id=?');$s->execute([$r['id']]);$r['items']=$s->fetchAll();}
  out($o);
case 'order_status':
  need(...$staff);$id=(int)$in['id'];$st=$in['status']??'';
  if(!in_array($st,['new','shipping','done','cancel']))fail('Holat xato');
  $s=$pdo->prepare('SELECT status FROM orders WHERE id=?');$s->execute([$id]);$cur=$s->fetchColumn();
  if(!$cur)fail('Buyurtma topilmadi',404);if($cur=='cancel')fail('Bekor qilingan buyurtmani o\'zgartirib bo\'lmaydi');
  if($st=='cancel'){$s=$pdo->prepare('SELECT product_id,qty FROM order_items WHERE order_id=?');$s->execute([$id]);foreach($s->fetchAll() as $i)$pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([$i['qty'],$i['product_id']]);}
  $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$st,$id]);out(['ok'=>1]);
case 'stock':
  need(...$staff);$pdo->prepare('UPDATE products SET stock=GREATEST(0,stock+?) WHERE id=?')->execute([(int)$in['d'],(int)$in['id']]);out(['ok'=>1]);
case 'product_save':
  need('admin');$id=(int)($_POST['id']??0);
  $n=trim($_POST['name']??'');if(!$n||(int)($_POST['price']??0)<=0)fail('Nomi va narx kerak');
  $img='';if($id){$s=$pdo->prepare('SELECT images FROM products WHERE id=?');$s->execute([$id]);$img=(string)$s->fetchColumn();}
  if(!empty($_FILES['image']['tmp_name'])){$e=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
    if(!in_array($e,['jpg','jpeg','png','webp']))fail('Rasm: jpg, png yoki webp');
    $f='uploads/u'.time().rand(100,999).'.'.$e;move_uploaded_file($_FILES['image']['tmp_name'],__DIR__.'/img/'.$f);$img=trim($img.','.$f,',');}
  $bd=in_array($_POST['badge']??'',['new','hit'])?$_POST['badge']:'';
  $v=[$n,trim($_POST['category']??'')?:'Boshqa',(int)$_POST['price'],(int)($_POST['cost']??0),max(0,(int)($_POST['stock']??0)),trim($_POST['sizes']??''),trim($_POST['colors']??''),trim($_POST['descr']??''),$img,max(0,(int)($_POST['old_price']??0)),$bd];
  if($id){$pdo->prepare('UPDATE products SET name=?,category=?,price=?,cost=?,stock=?,sizes=?,colors=?,descr=?,images=?,old_price=?,badge=? WHERE id=?')->execute([...$v,$id]);}
  else $pdo->prepare('INSERT INTO products(name,category,price,cost,stock,sizes,colors,descr,images,old_price,badge) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute($v);
  out(['ok'=>1]);
case 'product_delete': need('admin');$pdo->prepare('DELETE FROM products WHERE id=?')->execute([(int)$in['id']]);out(['ok'=>1]);
case 'users': need('admin');out($pdo->query("SELECT id,name,phone,role,blocked FROM users WHERE role IN('admin','seller') ORDER BY id")->fetchAll());
case 'user_save':
  need('admin');$n=trim($in['name']??'');$ph=ph($in['phone']??'');$pw=$in['pass']??'';
  if(!$n||strlen($ph)<9||strlen($pw)<4)fail('Ism, telefon va parol (4+) kerak');
  try{$pdo->prepare("INSERT INTO users(name,phone,pass,role) VALUES(?,?,?,'seller')")->execute([$n,$ph,password_hash($pw,PASSWORD_DEFAULT)]);}catch(PDOException $e){fail('Bu telefon raqam band');}out(['ok'=>1]);
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
  $pdo->prepare('INSERT INTO banners(title,subtitle,cta,badge,category,image,grad,sort) VALUES(?,?,?,?,?,?,?,(SELECT COALESCE(MAX(sort),0)+1 FROM banners b))')->execute([$t,trim($in['subtitle']??''),trim($in['cta']??'')?:"Ko'rish",trim($in['badge']??''),trim($in['category']??'')?:'Barchasi',$im,$g]);out(['ok'=>1]);
case 'banner_toggle': need('admin');$pdo->prepare('UPDATE banners SET active=1-active WHERE id=?')->execute([(int)$in['id']]);out(['ok'=>1]);
case 'banner_delete': need('admin');$pdo->prepare('DELETE FROM banners WHERE id=?')->execute([(int)$in['id']]);out(['ok'=>1]);
default: fail('Noma\'lum amal',404);
}
