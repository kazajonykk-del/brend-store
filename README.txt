TREND STORE: sayt (PHP + MySQL)

1. XAMPP da Apache va MySQL ni yoqing.
2. Papkani C:\xampp\htdocs\backend\trendstore ga joylang (eskisining ustiga).
3. Brauzerda oching: http://localhost/backend/trendstore/setup.php
   Jadvallar yangilanadi, ma'lumotlaringiz o'chmaydi.
4. Sayt: http://localhost/backend/trendstore/index.html  (til: ?lang=uz | ru | en)
   Admin: telefon 901234567, parol admin123
5. Sozlamalar config.php da: baza, Telegram nomi, telefon, yetkazish narxlari, kafolat.
   Aksiya tugash vaqti va bepul yetkazish chegarasi: admin > Aksiyalar.
6. Namunaviy sharhlarni admin mahsulot oynasida ✕ bilan o'chirishingiz mumkin.
7. Tayyor bo'lgach setup.php ni o'chiring va admin parolini almashtiring.

YANGILIKLAR (v4): kafolat bloklari, qidiruv tarixi, 3/6/12 oylik bo'lib to'lash, "Balki shularni qidirgandirsiz?",
avto/yorug'/tungi rejim (nav dagi ◐ tugma), savatni bo'shatish + Qaytarish, kartada 2-rasmga o'tish,
chap chetda mini-xarita, internet uzilganda ogohlantirish. Faqat app.js, style.css, index.html o'zgargan.

YANGILIKLAR (v7): jonli qidiruv (rasm+nom+narx, ↑↓ Enter Esc), ovozli qidiruv 🎤, '/' yoki Ctrl+K bilan qidiruvni ochish,
mahsulotni ulashish va to'g'ridan-to'g'ri havola (#p12), savatga uchib kirish effekti, buyurtmada konfetti, o'qish chizig'i,
shisha kartalarda sichqoncha nuri, tugmalarda ripple. setup.php ni bir marta qayta ishga tushiring (v6 dan).

============ v8: ONLAYN TO'LOV (Click / Payme) VA BOSHQALAR ============
1) setup.php ni bir marta oching (yangi ustunlar va jadvallar yaratiladi).
2) config.php ni oching va pastdagi qiymatlarni to'ldiring. BO'SH qoldirilgan usul saytda umuman ko'rinmaydi.
   SITE_URL      — saytingiz manzili (lokalda: http://localhost/backend/trendstore, hostingda: https://domeningiz.uz)

   PAYME (kabinet: merchant.paycom.uz)
     PAYME_ID, PAYME_TEST_KEY, PAYME_KEY  — kassa ID va kalitlar
     Kabinetda "Endpoint URL" = SITE_URL/payme.php  ; hisob maydoni (account) nomi: order_id (butun son)
     Avval PAYME_TEST=true da Payme sandbox sinovidan o'ting, keyin false qiling.
   CLICK (kabinet: merchant.click.uz)
     CLICK_SERVICE_ID, CLICK_MERCHANT_ID, CLICK_USER_ID, CLICK_SECRET
     Kabinetda Prepare URL va Complete URL = SITE_URL/click.php
   MUHIM: Payme va Click serveringizga INTERNETDAN murojaat qiladi. localhost ga ular kira olmaydi.
          Sinash uchun saytni hostingga qo'ying (yoki ngrok kabi tunnel ishlating).

   TELEGRAM XABARNOMA: TG_TOKEN (@BotFather), TG_CHAT (chat ID, @userinfobot dan). Yangi buyurtma, to'lov qabul qilindi,
   to'lov qaytarildi va "mahsulot yana omborda" xabarlari shu chatga keladi. (InfinityFree kabi bepul hostinglar tashqi
   so'rovlarni bloklashi mumkin — u holda xabar kelmaydi, sayt esa ishlayveradi.)

TO'LOV TARTIBI: mijoz savatda Naqd / Click / Payme tanlaydi -> buyurtma yaratiladi -> to'lov sahifasiga o'tadi ->
to'lovdan so'ng buyurtma AVTOMATIK "Tasdiqlandi" bo'ladi. To'lanmagan onlayn buyurtma 30 daqiqadan keyin bekor qilinadi
va mahsulot zaxirasi qaytariladi. Buyurtmalar sahifasida "Hozir to'lash" tugmasi bor. Admin/sotuvchi naqd to'lovni
"To'langan deb belgilash" bilan tasdiqlaydi. To'langan buyurtma bekor qilinsa "Pulni qaytarish kerak" belgisi chiqadi
(pulni Click/Payme kabinetidan qaytarasiz).

QOLGAN YANGILIKLAR: 📊 Statistika (admin), 🧾 buyurtma cheki (chop etish), 🔔 "Qaytganda xabar bering" (+ admin ro'yxati),
CSV orqali mahsulotlarni yuklab olish/yuklash (Excel; ustunlar: id;name;category;price;old_price;cost;stock;sizes;colors;badge;descr),
telefonga o'rnatiladigan sayt (PWA) va internet yo'q paytda oxirgi vitrina ma'lumoti.
