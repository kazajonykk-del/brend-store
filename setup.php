<?php
require 'config.php';
$need=['users'=>'phone','products'=>'category','orders'=>'total','order_items'=>'order_id'];
$moved=[];$err='';$n=0;
$seed=[1=>[1190000,''],2=>[0,'hit'],3=>[1150000,''],4=>[1590000,''],5=>[0,'new'],6=>[560000,''],7=>[0,'new'],8=>[0,'new'],9=>[850000,''],10=>[790000,''],11=>[0,'hit'],12=>[0,'new'],13=>[0,'hit'],14=>[0,'hit']];
$banners=[["Qish aksiyasi","Kurtkalarga -25% gacha chegirma","Kurtkalarga o'tish","-25%","Kurtkalar","p00.jpg","#1e3a8a,#0e7490"],["Yangi kolleksiya","Hoodie va sport kiyimlari endi omborda","Ko'rish","Yangi","Hoodielar","p10.jpg","#451a03,#b45309"],["Aksiyadagi mahsulotlar","Eng yaxshi narxlar bir joyda","Aksiyalarni ko'rish","Aksiya","__sale","p15.jpg","#7f1d1d,#e11d48"],["VIP10 promokodi","Birinchi buyurtmaga -10% chegirma, savatda kiriting","Xarid qilish","VIP10","Barchasi","p40.jpg","#3b0764,#2563eb"]];
try{
  foreach($need as $t=>$col){
    if($pdo->query("SHOW TABLES LIKE '$t'")->fetch() && !$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$col'")->fetch()){
      $new=$t.'_old_'.date('His');$pdo->exec("RENAME TABLE `$t` TO `$new`");$moved[]="$t → $new";}}
  $stm=array_values(array_filter(array_map('trim',explode(";\n",file_get_contents(__DIR__.'/database.sql')))));
  foreach($stm as $s)if(stripos($s,'CREATE TABLE')===0)$pdo->exec($s);
  $has=fn($t,$c)=>(bool)$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->fetch();
  $fresh=(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()==0;$added=false;
  foreach(['old_price'=>'INT NOT NULL DEFAULT 0','badge'=>"VARCHAR(20) NOT NULL DEFAULT ''"] as $c=>$d)if(!$has('products',$c)){$pdo->exec("ALTER TABLE products ADD `$c` $d");$added=true;}
  foreach($stm as $s)if(stripos($s,'INSERT')===0)$pdo->exec($s);
  if($fresh||$added)foreach($seed as $id=>[$o,$b])$pdo->prepare('UPDATE products SET old_price=?,badge=? WHERE id=?')->execute([$o,$b,$id]);
  if(!(int)$pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn()){$i=1;foreach($banners as $b)$pdo->prepare('INSERT INTO banners(title,subtitle,cta,badge,category,image,grad,sort) VALUES(?,?,?,?,?,?,?,?)')->execute([...$b,$i++]);}
  $s=$pdo->prepare('SELECT id FROM users WHERE phone=?');$s->execute(['901234567']);
  if(!$s->fetch())$pdo->prepare("INSERT INTO users(name,phone,pass,role) VALUES('Admin','901234567',?, 'admin')")->execute([password_hash('admin123',PASSWORD_DEFAULT)]);
  $n=$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
}catch(Exception $x){$err=$x->getMessage();}
?><!doctype html><meta charset="utf-8"><title>Setup</title><body style="font:20px sans-serif;background:#090d16;color:#e8edf7;padding:40px">
<?php if($err):?><h2 style="color:#f43f5e">Xatolik</h2><p><?=htmlspecialchars($err)?></p>
<?php else:?><h2>Baza yangilandi ✓</h2><p>Mahsulotlar: <?=$n?></p><p>Admin: <b>901234567</b> / <b>admin123</b></p>
<?php if($moved):?><p style="color:#94a3b8">Eski jadvallar nomi o'zgartirildi: <?=htmlspecialchars(implode(', ',$moved))?></p><?php endif;?>
<p><a style="color:#22d3ee" href="index.html">Saytni ochish →</a></p><p style="color:#94a3b8">Tayyor bo'lgach setup.php ni o'chirib qo'ying.</p><?php endif;?>
