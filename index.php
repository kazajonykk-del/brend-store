<?php
// index.php -> index.html (ikkala manzil ham ishlaydi)
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
