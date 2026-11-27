<?php
// XAMPP standart sozlamalari: phpMyAdmin dagi "brendstore" bazasi
$DB=['host'=>'127.0.0.1','name'=>'brendstore','user'=>'root','pass'=>''];
session_start();
try{
  $pdo=new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4",$DB['user'],$DB['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}catch(Exception $e){
  http_response_code(500);header('Content-Type: application/json; charset=utf-8');
  die(json_encode(['error'=>"Bazaga ulanib bo'lmadi: ".$e->getMessage()],JSON_UNESCAPED_UNICODE));
}
const PROMOS=['VIP10'=>['pct',10],'SALE20000'=>['fix',20000]];

// Telegram xabarnoma: @BotFather dan token, chat_id ni o'zingiznikini yozing (bo'sh qolsa ishlamaydi)
const TG_TOKEN='';
const TG_CHAT='';
