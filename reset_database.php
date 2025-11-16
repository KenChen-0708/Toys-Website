<?php
/**
 * RESET DATABASE SCRIPT
 * ---------------------
 * This script will:
 * 1. DROP the online_store database
 * 2. RECREATE it
 * 3. CREATE all tables
 * 4. INSERT all data
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'online_store';

// Connect to MySQL without selecting a database yet
try {
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed (host only): " . $e->getMessage());
}

// SQL SCRIPT (DROP, CREATE, INSERT)
$sql = <<<'SQL'

DROP DATABASE IF EXISTS online_store;
CREATE DATABASE online_store;
USE online_store;

-- ADMIN TABLE
CREATE TABLE Admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL
);

-- MEMBER TABLE
CREATE TABLE Member (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    register_date DATETIME NOT NULL
);

-- PRODUCT TABLE
CREATE TABLE Product (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    stock INT NOT NULL,
    status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available',
    main_image_path VARCHAR(255),
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Product_Sub_Image (
    sub_image_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    sub_image_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES Product(product_id) ON DELETE CASCADE
);

-- CART TABLE
CREATE TABLE Cart (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    FOREIGN KEY (member_id) REFERENCES Member(member_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- CART ITEM TABLE
CREATE TABLE Cart_Item (
    cart_item_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    cart_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES Product(product_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (cart_id) REFERENCES Cart(cart_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ORDER TABLE
CREATE TABLE `Order` (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    shipping_address TEXT,
    FOREIGN KEY (member_id) REFERENCES Member(member_id)
);

-- ORDER ITEM TABLE
CREATE TABLE Order_Item (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES `Order`(order_id),
    FOREIGN KEY (product_id) REFERENCES Product(product_id)
);

-- PAYMENT TABLE
CREATE TABLE Payment (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNIQUE,
    payment_method ENUM('Credit Card','Debit Card','Online Banking','E-Wallet','Cash on Delivery') NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
    FOREIGN KEY (order_id) REFERENCES `Order`(order_id)
);

-- WISHLIST
CREATE TABLE Wishlist (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL UNIQUE,
    FOREIGN KEY (member_id) REFERENCES Member(member_id)
);

CREATE TABLE Wishlist_Item (
    wishlist_item_id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    product_id INT NOT NULL,
    UNIQUE (wishlist_id, product_id),
    FOREIGN KEY (wishlist_id) REFERENCES Wishlist(wishlist_id),
    FOREIGN KEY (product_id) REFERENCES Product(product_id)
);

-- PASSWORD RESET
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL
);

-- REVIEW TABLE
CREATE TABLE Review (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    member_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES Product(product_id),
    FOREIGN KEY (member_id) REFERENCES Member(member_id)
);

-- FEEDBACK TABLE
CREATE TABLE IF NOT EXISTS `Feedback` (
  feedback_id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  subject VARCHAR(255) NOT NULL,
  category ENUM('product_quality','customer_service','website','delivery','pricing','suggestion','complaint','other') NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  message TEXT NOT NULL,
  status ENUM('pending','reviewed','resolved') DEFAULT 'pending',
  admin_response TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_member_id (member_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (member_id) REFERENCES Member(member_id) ON DELETE CASCADE
);

-- FEEDBACK VIEW
CREATE OR REPLACE VIEW feedback_statistics AS
SELECT 
    category,
    COUNT(*) total_feedback,
    AVG(rating) average_rating,
    COUNT(CASE WHEN status='pending' THEN 1 END) pending_count,
    COUNT(CASE WHEN status='reviewed' THEN 1 END) reviewed_count,
    COUNT(CASE WHEN status='resolved' THEN 1 END) resolved_count
FROM Feedback
GROUP BY category;

-- INSERT DATA
INSERT INTO Product (name, price, description, category, stock, status, main_image_path) VALUES
('Ninja Lego Set', 25.00, 'A small ninja-themed Lego kit.', 'Lego', 10, 'Available', '../assets/img/product_6917146a4c05f.jpg'),
('Ninja Turtle Blue', 480.00, 'Blue turtle action figure.', 'Ninja Turtle', 5, 'Available', '../assets/img/product_691714da857ba.jpg'),
('Star War Blaster', 55.00, 'Replica toy blaster from Star War series.', 'Star War', 15, 'Available', '../assets/img/default.jpg'),
('Robot Builder Kit', 90.00, 'STEM kit for building robots.', 'Lego', 30, 'Available', '../assets/img/default.jpg'),
('Super Ninja Mask', 35.00, 'High quality ninja mask for kids.', 'Ninja Turtle', 12, 'Available', '../assets/img/product_6917146a4c05f.jpg'),
('Star War Droid', 75.00, 'Mini droid figure with sound effects.', 'Star War', 18, 'Available', '../assets/img/product_691714da857ba.jpg'),
('Lego City Car', 40.00, 'Lego city racing car set.', 'Lego', 22, 'Available', '../assets/img/default.jpg'),
('Ninja Turtle Bike', 110.00, 'Motorbike for ninja turtle action figure.', 'Ninja Turtle', 6, 'Available', '../assets/img/product_6917146a4c05f.jpg'),
('Star War Spaceship', 150.00, 'Medium-size spaceship model.', 'Star War', 9, 'Available', '../assets/img/default.jpg'),
('Lego Monster Truck', 60.00, 'Big monster truck Lego set.', 'Lego', 14, 'Available', '../assets/img/product_691714da857ba.jpg'),
('Ninja Turtle Helmet', 45.00, 'Protective helmet for ninja action play.', 'Ninja Turtle', 20, 'Available', '../assets/img/default.jpg'),
('Star War Saber Blue', 85.00, 'Blue light saber toy.', 'Star War', 19, 'Available', '../assets/img/product_6917146a4c05f.jpg'),
('Lego Pirate Ship', 120.00, 'Classic pirate ship building kit.', 'Lego', 7, 'Available', '../assets/img/default.jpg'),
('Ninja Training Set', 58.00, 'Training gear bundle for ninja roleplay.', 'Ninja Turtle', 9, 'Available', '../assets/img/product_691714da857ba.jpg'),
('Star War Jetpack', 98.00, 'Wearable jetpack toy from Star War.', 'Star War', 16, 'Available', '../assets/img/default.jpg'),
('Lego Police Patrol', 48.00, 'Police car and station mini-set.', 'Lego', 25, 'Available', '../assets/img/product_6917146a4c05f.jpg'),
('Ninja Turtle Staff', 33.00, 'Turtle warrior combat staff.', 'Ninja Turtle', 11, 'Available', '../assets/img/default.jpg'),
('Star War Clone Figure', 29.00, 'Clone trooper mini figure.', 'Star War', 40, 'Available', '../assets/img/product_691714da857ba.jpg'),
('Lego Space Explorer', 88.00, 'Space exploration building kit.', 'Lego', 13, 'Available', '../assets/img/default.jpg'),
('Ninja Turtle Van', 160.00, 'The iconic turtle van toy.', 'Ninja Turtle', 4, 'Available', '../assets/img/product_6917146a4c05f.jpg');

INSERT INTO Member (username, password, email, phone_number, address, register_date) VALUES
('Ahmad', 'pass1', 'ahmad@example.com', '012-3456789', '123 Jalan A, KL', '2023-01-15 10:00:00'),
('Bella', 'pass2', 'bella@example.com', '013-9876543', '456 Jalan B, Penang', '2024-03-22 14:30:00'),
('Chris', 'pass3', 'chris@example.com', '014-5556677', '789 Jalan C, Johor', '2024-07-05 09:15:00'),
('Diana', 'pass4', 'diana@example.com', '015-4445566', '321 Jalan D, KL', '2023-12-18 16:45:00'),
('Eric', 'pass5', 'eric@example.com', '016-3334455', '654 Jalan E, Penang', '2025-02-01 11:20:00'),
('Fiona', 'pass6', 'fiona@example.com', '017-2223344', '987 Jalan F, Selangor', '2025-05-19 08:50:00'),
('Gavin', 'pass7', 'gavin@example.com', '018-1112233', '111 Jalan G, KL', '2023-08-25 12:10:00'),
('Hannah', 'pass8', 'hannah@example.com', '019-0001122', '222 Jalan H, Johor', '2024-11-30 17:35:00'),
('Ian', 'pass9', 'ian@example.com', '010-9998877', '333 Jalan I, Penang', '2025-06-07 13:00:00'),
('Jenny', 'pass10', 'jenny@example.com', '011-8887766', '444 Jalan J, Selangor', '2023-04-12 15:45:00'),
('max', 'pass10', 'max@example.com', '012-8887766', '444 Jalan J, Selangor', '2023-04-12 15:45:00');

INSERT INTO admin (admin_id, username, password, email) VALUES
(1, 'admin', '$2y$10$niY7iWha/w7NhJCyzZeoIuSeybKJlTbHPePryWgtYNMnMLQ4gRa9W', 'admin@example.com');

INSERT INTO Cart (member_id) VALUES (1),(2),(3),(4),(5),(6),(7),(8),(9),(10);

INSERT INTO Cart_Item (product_id, cart_id, quantity) VALUES
(1, 1, 1),(1,1,1),(2,1,2),(5,1,3),(7,1,1),(1,4,2),(5,4,1),(8,4,3),(12,4,1),
(3,6,1),(7,7,3),(9,8,2),(11,9,1),(13,10,2),(15,1,3),(2,1,2),(3,2,1),(4,3,1),
(5,4,3),(6,5,1),(7,6,5),(8,7,2),(9,8,1),(9,9,1);

INSERT INTO `Order` (member_id, order_date, total_amount, status, shipping_address) VALUES
(1,'2023-02-10 10:00:00',5650.00,'Delivered','123 Jalan A, KL'),
(2,'2024-04-15 14:30:00',3300.00,'Shipped','456 Jalan B, Penang'),
(3,'2024-08-20 09:15:00',3200.00,'Processing','789 Jalan C, Johor'),
(4,'2023-12-22 16:45:00',580.00,'Delivered','321 Jalan D, KL'),
(5,'2025-02-05 11:20:00',450.00,'Shipped','654 Jalan E, Penang'),
(6,'2025-05-21 08:50:00',155.00,'Pending','987 Jalan F, Selangor'),
(7,'2023-09-01 12:10:00',120.00,'Delivered','111 Jalan G, KL'),
(8,'2024-12-05 17:35:00',220.00,'Processing','222 Jalan H, Johor'),
(9,'2025-06-10 13:00:00',650.00,'Shipped','333 Jalan I, Penang'),
(10,'2023-04-15 15:45:00',80.00,'Cancelled','444 Jalan J, Selangor');

INSERT INTO Payment (order_id, payment_method, payment_date, amount, status) VALUES
(1,'Credit Card','2023-02-10 10:15:00',5650.00,'Completed'),
(2,'E-Wallet','2024-04-15 14:40:00',3300.00,'Completed'),
(3,'Debit Card','2024-08-20 09:20:00',3200.00,'Pending'),
(4,'Credit Card','2023-12-22 16:50:00',580.00,'Completed'),
(5,'Online Banking','2025-02-05 11:25:00',450.00,'Completed'),
(6,'Cash on Delivery','2025-05-21 08:50:00',155.00,'Pending'),
(7,'E-Wallet','2023-09-01 12:15:00',120.00,'Completed'),
(8,'Credit Card','2024-12-05 17:40:00',220.00,'Pending'),
(9,'Debit Card','2025-06-10 13:05:00',650.00,'Completed'),
(10,'Credit Card','2023-04-15 15:50:00',80.00,'Failed');

INSERT INTO Order_Item (order_id, product_id, quantity, price) VALUES
(1,1,1,5500.00),(1,2,1,150.00),(2,3,1,3200.00),(3,3,1,3200.00),
(4,4,1,450.00),(4,5,1,130.00),(5,4,1,450.00),(6,7,5,35.00),
(7,8,1,120.00),(9,10,1,650.00);

INSERT INTO Review (product_id, member_id, rating, review_text, created_date) VALUES
(1,1,5,'Great quality Lego set, my son loved it!','2024-01-12 10:30:00'),
(2,3,4,'Nice action figure, but the paint could be better.','2024-05-18 14:45:00'),
(5,2,5,'The ninja mask fits perfectly and looks awesome.','2024-03-22 09:10:00'),
(7,4,3,'Decent Lego car, but smaller than expected.','2024-11-10 16:20:00'),
(10,6,4,'Monster truck Lego set was fun to build.','2025-06-01 12:00:00'),
(12,5,5,'The blue saber is super bright and durable.','2025-02-17 18:35:00'),
(14,7,4,'Good training set, my kids enjoyed playing with it.','2023-09-05 11:40:00'),
(16,8,5,'Police patrol Lego kit has great details!','2024-12-08 19:10:00'),
(18,9,3,'Clone figure looks fine but plastic quality feels cheap.','2025-06-11 13:50:00'),
(20,10,5,'The turtle van is amazing! Great collectible item.','2023-04-20 17:25:00');

SQL;

// Execute all SQL
try {
    $pdo->exec($sql);
    echo "----- Database reset successfully! -----";
} catch (PDOException $e) {
    echo "----- Error running SQL:" . nl2br($e->getMessage(). " -----");
}
?>
