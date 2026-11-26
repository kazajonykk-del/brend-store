CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,phone VARCHAR(20) NOT NULL UNIQUE,address VARCHAR(255) DEFAULT '',pass VARCHAR(255) NOT NULL,role ENUM('admin','seller','customer') NOT NULL DEFAULT 'customer',blocked TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS products(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,category VARCHAR(80) NOT NULL,price INT NOT NULL,cost INT NOT NULL DEFAULT 0,stock INT NOT NULL DEFAULT 0,sizes VARCHAR(255) DEFAULT '',colors VARCHAR(255) DEFAULT '',descr TEXT,images TEXT,old_price INT NOT NULL DEFAULT 0,badge VARCHAR(20) NOT NULL DEFAULT '',variants TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS orders(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,name VARCHAR(100),phone VARCHAR(30),address VARCHAR(255),total INT NOT NULL,discount INT NOT NULL DEFAULT 0,promo VARCHAR(30) NULL,delivery INT NOT NULL DEFAULT 0,region VARCHAR(40) DEFAULT '',method VARCHAR(20) DEFAULT '',status ENUM('new','confirmed','shipping','done','cancel') NOT NULL DEFAULT 'new',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS order_items(id INT AUTO_INCREMENT PRIMARY KEY,order_id INT NOT NULL,product_id INT NOT NULL,title VARCHAR(150),size VARCHAR(30),color VARCHAR(40),qty INT NOT NULL,price INT NOT NULL,INDEX(order_id)) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS banners(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(120) NOT NULL,subtitle VARCHAR(255) DEFAULT '',cta VARCHAR(60) DEFAULT 'Ko''rish',badge VARCHAR(30) DEFAULT '',category VARCHAR(80) DEFAULT 'Barchasi',image VARCHAR(120) DEFAULT '',grad VARCHAR(60) DEFAULT '#1e3a8a,#0e7490',active TINYINT(1) NOT NULL DEFAULT 1,sort INT NOT NULL DEFAULT 0) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS settings(k VARCHAR(40) PRIMARY KEY,v TEXT) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS reviews(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,user_id INT NOT NULL DEFAULT 0,name VARCHAR(80),rating TINYINT NOT NULL,text VARCHAR(400),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(product_id)) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS views(sid VARCHAR(64) PRIMARY KEY,product_id INT NOT NULL,ts INT NOT NULL,INDEX(product_id)) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS wishlist(user_id INT NOT NULL,product_id INT NOT NULL,added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,product_id)) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS cart_items(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,product_id INT NOT NULL,size VARCHAR(40) NOT NULL DEFAULT '',color VARCHAR(40) NOT NULL DEFAULT '',qty INT NOT NULL DEFAULT 1,UNIQUE KEY u(user_id,product_id,size,color)) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS payme_tx(id INT AUTO_INCREMENT PRIMARY KEY,paycom_id VARCHAR(32) NOT NULL UNIQUE,paycom_time BIGINT NOT NULL,create_time BIGINT NOT NULL,perform_time BIGINT NOT NULL DEFAULT 0,cancel_time BIGINT NOT NULL DEFAULT 0,state INT NOT NULL,reason INT NULL,amount BIGINT NOT NULL,order_id INT NOT NULL,INDEX(order_id)) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS notify_me(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,phone VARCHAR(20) NOT NULL,size VARCHAR(40) NOT NULL DEFAULT '',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY u(product_id,phone,size)) CHARACTER SET utf8mb4;
INSERT IGNORE INTO products(id,name,category,price,cost,stock,sizes,colors,descr,images) VALUES
(1,'Yashil puffer kurtka','Kurtkalar',890000,578500,14,'S,M,L,XL','Yashil','Issiq puffer kurtka, qalin to''ldirgich va kapyushon.','p00.jpg'),
(2,'Kulrang bomber kurtka','Kurtkalar',760000,494000,8,'S,M,L,XL','Kulrang','Yengil bomber, bahor va kuz uchun.','p01.jpg'),
(3,'Bej puffer kurtka','Kurtkalar',920000,598000,16,'S,M,L,XL','Bej','Oversize kesimdagi bej puffer.','p02.jpg'),
(4,'Kulrang palto','Kurtkalar',1250000,812500,24,'S,M,L,XL','Kulrang','Yuqori yoqali klassik palto.','p08.jpg'),
(5,'Monte Carlo hoodie','Hoodielar',480000,312000,5,'S,M,L,XL','Jigarrang','Qalin fleece, orqasida va ko''kragida kashta.','p10.jpg,p11.jpg,p12.jpg,p14.jpg,p18.jpg'),
(6,'Camo cargo shim','Shimlar',420000,273000,6,'30,32,34,36','Camo','Keng kesimli cargo, ko''p cho''ntakli.','p13.jpg'),
(7,'Japan sport kurtka','Sport kiyimlari',540000,351000,21,'S,M,L,XL','Oq-qizil','Retro uslubdagi zip sport kurtka.','p15.jpg,p16.jpg'),
(8,'Batman futbolka','Futbolkalar',190000,123500,7,'S,M,L,XL','Qora','Paxtali futbolka, old tomonida print.','p36.jpg'),
(9,'Teri loafer tufli','Poyabzal',680000,442000,15,'40,41,42,43,44','Jigarrang','Tabiiy ko''rinishdagi teri loafer.','p03.jpg,p05.jpg,p06.jpg,p07.jpg,p09.jpg'),
(10,'Uggi etik','Poyabzal',590000,383500,22,'40,41,42,43,44','Qora','Ichi mo''ynali issiq etik.','p25.jpg,p26.jpg,p28.jpg,p29.jpg,p30.jpg'),
(11,'Crocs sandal','Poyabzal',260000,169000,5,'40,41,42,43,44','Oq','Yengil sandal, jibbitz belgilari bilan.','p31.jpg,p32.jpg,p33.jpg,p34.jpg'),
(12,'Cathatre kepka','Kepkalar',150000,97500,20,'55,57,59,61','Qora,Kulrang','Kashtali beysbol kepka.','p17.jpg,p19.jpg,p20.jpg,p21.jpg,p22.jpg'),
(13,'Sport ko''zoynak','Ko''zoynaklar',230000,149500,10,'Standart','Qora,Oltin','UV himoyali sport ko''zoynak.','p23.jpg,p24.jpg,p27.jpg'),
(14,'Erkaklar soati','Soatlar',850000,552500,5,'Standart','Kumush','Po''lat tasmali klassik soat.','p40.jpg,p41.jpg,p43.jpg'),
(15,'Zanjirli bilaguzuk','Bilaguzuklar',210000,136500,6,'17,18,19,20','Kumush','Po''latdan zanjirli bilaguzuk.','p37.jpg,p39.jpg,p42.jpg'),
(16,'Uzuklar to''plami','Uzuklar',180000,117000,17,'17,18,19,20','Kumush','Erkaklar uchun uzuklar to''plami.','p35.jpg,p44.jpg'),
(17,'Jibbitz belgilari','Aksessuarlar',60000,39000,17,'Standart','Aralash','Crocs uchun bezak belgilari.','p38.jpg'),
(18,'Jun sharf','Aksessuarlar',190000,123500,6,'Standart','Katak,Kulrang,Qora','Yumshoq jun sharf, uch xil rang.','p04.jpg');
