TREND STORE: sayt (PHP + MySQL)

1. XAMPP da Apache va MySQL ni yoqing.
2. Bu papkani C:\xampp\htdocs\trendstore ichiga joylang.
3. phpMyAdmin da "brendstore" bazasi bor (bo'sh bo'lsa ham yetadi).
4. Brauzerda oching: http://localhost/trendstore/setup.php
   Jadvallar va 18 ta mahsulot avtomatik yaratiladi (yoki database.sql ni phpMyAdmin > Import orqali yuklang).
5. Sayt: http://localhost/trendstore/index.html
   Admin: telefon 901234567, parol admin123
6. Baza sozlamalari config.php da (standart: root, parolsiz, brendstore).
7. Tayyor bo'lgach setup.php ni o'chiring va admin parolini almashtiring.
Promokodlar: VIP10 (-10%), SALE20000 (-20 000 so'm)

--- v4: yangi imkoniyatlar ---
Yangi fayl: extras.js. Sozlamalar uning boshidagi CFG da: free (bepul yetkazish chegarasi), tg (Telegram admin username), phone (qo'ng'iroq raqami), warrantyDays.
Bazaga "reviews" jadvali qo'shildi (api.php o'zi ham yaratadi, database.sql da ham bor).
Til: index.html?lang=uz|ru|en yoki tepadagi UZ/RU/EN tanlagich.

--- v5 ---
extras2.js boshidagi CF2: brendlar ro'yxati (marquee), video, Click/Payme kalitlari. config.php: TG_TOKEN va TG_CHAT (Telegram xabarnoma).
Video: video/promo.webm va promo.mp4 (o'zingiznikiga almashtiring). Sayt birinchi bosilganda chiqadi (sessiyada bir marta).
PWA: manifest.json, sw.js, icon.svg (http/https da ishlaydi).
