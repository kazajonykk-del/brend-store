<?php
require 'config.php';
$need=['users'=>'phone','products'=>'category','orders'=>'total','order_items'=>'order_id'];
$moved=[];$err='';$n=0;
$seed=[1=>[1190000,''],2=>[0,'hit'],3=>[1150000,''],4=>[1590000,''],5=>[0,'new'],6=>[560000,''],7=>[0,'new'],8=>[0,'new'],9=>[850000,''],10=>[790000,''],11=>[0,'hit'],12=>[0,'new'],13=>[0,'hit'],14=>[0,'hit']];
$banners=[["Qish aksiyasi","Kurtkalarga -25% gacha chegirma","Kurtkalarga o'tish","-25%","Kurtkalar","p00.jpg","#1e3a8a,#0e7490"],["Yangi kolleksiya","Hoodie va sport kiyimlari endi omborda","Ko'rish","Yangi","Hoodielar","p10.jpg","#451a03,#b45309"],["Aksiyadagi mahsulotlar","Eng yaxshi narxlar bir joyda","Aksiyalarni ko'rish","Aksiya","__sale","p15.jpg","#7f1d1d,#e11d48"],["VIP10 promokodi","Birinchi buyurtmaga -10% chegirma, savatda kiriting","Xarid qilish","VIP10","Barchasi","p40.jpg","#3b0764,#2563eb"]];
$reviews=[[1,"Jasur",5,"Juda issiq va sifatli, o'lchami to'g'ri keldi."],[1,"Madina",4,"Rangi rasmdagidek. Yetkazib berish tez bo'ldi."],[5,"Sardor",5,"Mato qalin, kashta chiroyli."],[9,"Bekzod",5,"Teri sifatli, oyoqqa qulay."],[9,"Dilshod",4,"Chiroyli, lekin biroz qattiqroq."],[14,"Aziz",5,"Soat zo'r ko'rinadi, tasma mustahkam."],[12,"Otabek",5,"Kepka sifatli tikilgan."]];
try{
  foreach($need as $t=>$col){
    if($pdo->query("SHOW TABLES LIKE '$t'")->fetch() && !$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$col'")->fetch()){
      $new=$t.'_old_'.date('His');$pdo->exec("RENAME TABLE `$t` TO `$new`");$moved[]="$t → $new";}}
  $stm=array_values(array_filter(array_map('trim',explode(";\n",file_get_contents(__DIR__.'/database.sql')))));
  foreach($stm as $s)if(stripos($s,'CREATE TABLE')===0)$pdo->exec($s);
  try{$pdo->exec("ALTER TABLE orders MODIFY status ENUM('new','confirmed','shipping','done','cancel') NOT NULL DEFAULT 'new'");}catch(Exception $e){}
  $has=fn($t,$c)=>(bool)$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->fetch();
  $add=function($t,$c,$d)use($pdo,$has){if($has($t,$c))return false;$pdo->exec("ALTER TABLE `$t` ADD `$c` $d");return true;};
  $fresh=(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()==0;
  $newSale=$add('products','old_price','INT NOT NULL DEFAULT 0');$add('products','badge',"VARCHAR(20) NOT NULL DEFAULT ''");$newVar=$add('products','variants','TEXT NULL');
  $add('orders','delivery','INT NOT NULL DEFAULT 0');$add('orders','pay_method',"VARCHAR(10) NOT NULL DEFAULT 'cash'");$add('orders','pay_status',"VARCHAR(10) NOT NULL DEFAULT 'unpaid'");$add('orders','pay_time','DATETIME NULL');$add('orders','pay_trans','VARCHAR(64) NULL');$add('orders','region',"VARCHAR(40) DEFAULT ''");$add('orders','method',"VARCHAR(20) DEFAULT ''");
  foreach($stm as $s)if(stripos($s,'INSERT')===0)$pdo->exec($s);
  if($fresh||$newSale)foreach($seed as $id=>[$o,$b])$pdo->prepare('UPDATE products SET old_price=?,badge=? WHERE id=?')->execute([$o,$b,$id]);
  if($fresh||$newVar){
    foreach($pdo->query('SELECT id,stock,sizes FROM products')->fetchAll() as $p){
      $sz=array_values(array_filter(array_map('trim',explode(',',$p['sizes']))));if(!$sz||$sz==['Standart'])continue;
      $b=intdiv($p['stock'],count($sz));$r=$p['stock']-$b*count($sz);$v=[];
      foreach($sz as $i=>$k)$v[$k]=['stock'=>$b+($i<$r?1:0),'add'=>$k=='XL'?10000:0];
      $pdo->prepare('UPDATE products SET variants=? WHERE id=?')->execute([json_encode(['sizes'=>$v]),$p['id']]);}
    $pdo->exec("UPDATE products SET colors='Qora:0,Kulrang:3' WHERE id=12");$pdo->exec("UPDATE products SET colors='Qora:0,Oltin:1' WHERE id=13");}
  $pdo->prepare('INSERT IGNORE INTO settings(k,v) VALUES(?,?)')->execute(['promo_end',date('Y-m-d H:i:s',time()+3*86400+13338)]);
  $pdo->prepare('INSERT IGNORE INTO settings(k,v) VALUES(?,?)')->execute(['free_ship','200000']);
  if(!(int)$pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn()){$i=1;foreach($banners as $b)$pdo->prepare('INSERT INTO banners(title,subtitle,cta,badge,category,image,grad,sort) VALUES(?,?,?,?,?,?,?,?)')->execute([...$b,$i++]);}
  if(!(int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn())foreach($reviews as $r)$pdo->prepare('INSERT INTO reviews(product_id,user_id,name,rating,text) VALUES(?,0,?,?,?)')->execute($r);
  $s=$pdo->prepare('SELECT id FROM users WHERE phone=?');$s->execute(['901234567']);
  if(!$s->fetch())$pdo->prepare("INSERT INTO users(name,phone,pass,role) VALUES('Admin','901234567',?, 'admin')")->execute([password_hash('admin123',PASSWORD_DEFAULT)]);
  $n=$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
}catch(Exception $x){$err=$x->getMessage();}
?><!doctype html><meta charset="utf-8"><title>Setup</title><body style="font:20px sans-serif;background:#090d16;color:#e8edf7;padding:40px">
<?php if($err):?><h2 style="color:#f43f5e">Xatolik</h2><p><?=htmlspecialchars($err)?></p>
<?php else:?><h2>Baza yangilandi ✓</h2><p>Mahsulotlar: <?=$n?></p><p>Admin: <b>901234567</b> / <b>admin123</b></p>
<?php if($moved):?><p style="color:#94a3b8">Eski jadvallar nomi o'zgartirildi: <?=htmlspecialchars(implode(', ',$moved))?></p><?php endif;?>
<p><a style="color:#22d3ee" href="index.html">Saytni ochish →</a></p><p style="color:#94a3b8">Tayyor bo'lgach setup.php ni o'chirib qo'ying.</p><?php endif;?>
