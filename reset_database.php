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
    email VARCHAR(100) UNIQUE NOT NULL,
    role VARCHAR(50) DEFAULT 'admin'
);

-- ROLE PERMISSIONS TABLE (default permissions for each role)
CREATE TABLE Role_Permission (
    role_permission_id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    resource VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    UNIQUE KEY unique_role_resource_action (role, resource, action)
);

-- ADMIN PERMISSIONS TABLE (for custom permissions set by Super Admin)
CREATE TABLE Admin_Permission (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    resource VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES Admin(admin_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_admin_resource_action (admin_id, resource, action)
);

-- ADMIN PERMISSION DENIED TABLE (to deny permissions that would otherwise be inherited from role)
CREATE TABLE Admin_Permission_Denied (
    denied_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    resource VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES Admin(admin_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_admin_resource_action_denied (admin_id, resource, action)
);

-- MEMBER TABLE
CREATE TABLE Member (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    profile_photo_path VARCHAR(255),
    register_date DATETIME NOT NULL
);

CREATE TABLE Shipping_Address (
    shipping_address_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) NOT NULL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id) REFERENCES Member(member_id)
        ON DELETE CASCADE ON UPDATE CASCADE
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
    shipping_address_line1 VARCHAR(255) NOT NULL,
    shipping_address_line2 VARCHAR(255),
    shipping_city VARCHAR(100) NOT NULL,
    shipping_state VARCHAR(100),
    shipping_postal_code VARCHAR(20),
    shipping_country VARCHAR(100) NOT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Pending',

    FOREIGN KEY (member_id) REFERENCES Member(member_id)
        ON DELETE CASCADE ON UPDATE CASCADE
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
    FOREIGN KEY (order_id) REFERENCES `Order`(order_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- WISHLIST
CREATE TABLE Wishlist (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL UNIQUE,
    FOREIGN KEY (member_id) REFERENCES Member(member_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE Wishlist_Item (
    wishlist_item_id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    product_id INT NOT NULL,
    date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist_id) REFERENCES Wishlist(wishlist_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES Product(product_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE (wishlist_id, product_id)
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

    FOREIGN KEY (product_id) REFERENCES Product(product_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    FOREIGN KEY (member_id) REFERENCES Member(member_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Voucher (
    voucher_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_value DECIMAL(10,2) NOT NULL,
    discount_type ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed',
    min_spend DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2) DEFAULT NULL,
    usage_limit INT NOT NULL DEFAULT 1,
    usage_per_user INT NOT NULL DEFAULT 1,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Voucher_Usage (
    voucher_usage_id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    member_id INT NOT NULL,
    order_id INT NOT NULL,
    redeemed_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (voucher_id) REFERENCES Voucher(voucher_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    FOREIGN KEY (member_id) REFERENCES Member(member_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    FOREIGN KEY (order_id) REFERENCES `Order`(order_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- FEEDBACK TABLE
CREATE TABLE IF NOT EXISTS `Feedback` (
  `feedback_id` INT NOT NULL AUTO_INCREMENT,
  `member_id` INT NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `category` ENUM('product_quality', 'customer_service', 'website', 'delivery', 'pricing', 'suggestion', 'complaint', 'other') NOT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `message` TEXT NOT NULL,
  `status` ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
  `admin_response` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  INDEX `idx_member_id` (`member_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`member_id`) REFERENCES `Member`(`member_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- FEEDBACK VIEW
CREATE OR REPLACE VIEW `feedback_statistics` AS
SELECT 
    category,
    COUNT(*) as total_feedback,
    AVG(rating) as average_rating,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed_count,
    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count
FROM `Feedback`
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

-- password: Password123!
INSERT INTO `member` (`member_id`, `username`, `password`, `email`, `phone_number`, `address`, `profile_photo_path`, `register_date`) VALUES
(1, 'Ken', '$2y$10$eoth.o.tQ99RPZPv/HxUHeyV3PjQDSMb1mD2Sx.lBD1CNzJg.uEvu', 'ken@example.com', '012-3456789', '123 Jalan A, KL', '/assets/img/profile_photo/test.jpg', '2023-01-15 10:00:00'),
(2, 'SiuYee', '$2y$10$QYh/ifao5wHJkDip75hg..fpYkWIvdH2y/mqro6Xv/L7ZJK0u.R7K', 'sy@example.com', '013-9876543', '456 Jalan B, Penang', '/assets/img/profile_photo/member_2_1763459011.png', '2024-03-22 14:30:00'),
(3, 'Ronaldo', '$2y$10$tZm3kToa/ukNH6oc9zm98uSoF8/5gfLda0zSErhSYsVYS1kOiqIUW', 'r@example.com', '014-5556677', '789 Jalan C, Johor', NULL, '2024-07-05 09:15:00'),
(4, 'TzeChuen', '$2y$10$l5arISf17ngsVxyXOvhp4es70pkYXtgbxJ33OizV01MKrGB47oDBW', 'tc@example.com', '015-4445566', '321 Jalan D, KL', NULL, '2023-12-18 16:45:00'),
(5, 'Chris', '$2y$10$Uegr.5wKiXfY6gi9qhgtveK0W/JI0rQ39BS0PFIkApogDmsrwZyb6', 'c@example.com', '016-3334455', '654 Jalan E, Penang', NULL, '2025-02-01 11:20:00'),
(6, 'KherQin', '$2y$10$qraWdzTC/72ZSjtUhOvUeOPx5Sg66EohiHWuvo9xLawZrmHit.bmW', 'kq@example.com', '017-2223344', '987 Jalan F, Selangor', NULL, '2025-05-19 08:50:00'),
(7, 'WenChing', '$2y$10$0ING2O.sQdVXog21.brR8uL3MD3dIzpS2C.bNsypl/Ars1NX/ga2e', 'wc@example.com', '018-1112233', '111 Jalan G, KL', NULL, '2023-08-25 12:10:00'),
(8, 'Isaac', '$2y$10$EPaLeuF8FKcSTu9GE3qbMOHEmDEotDB4pmBUnAJCeYRH0Rn3i.e7u', 'i@example.com', '019-0001122', '222 Jalan H, Johor', NULL, '2024-11-30 17:35:00'),
(9, 'Jun', '$2y$10$V4GifmEJ0OW/8A0dMfa9W.KpeATjJvStzcjOPrIlaoeiMNBdKiC9y', 'j@example.com', '010-9998877', '333 Jalan I, Penang', NULL, '2025-06-07 13:00:00'),
(10, 'Quan', '$2y$10$lFsQiKXW3LA9YB8Wk7QKleI2wwUXkhJiKw00Sg7xxzA6VglelQ6GS', 'q@example.com', '011-8887766', '444 Jalan J, Selangor', NULL, '2023-04-12 15:45:00'),
(11, 'ChaoZe', '$2y$10$28p0m4HDn4y7EiVU66t65OoGnCEcZx3E8h6Uhgglv60ZgbF061O1S', 'cz@example.com', '012-8887766', '444 Jalan J, Selangor', NULL, '2023-04-12 15:45:00');


-- password: Password123!
INSERT INTO admin (admin_id, username, password, email, role) VALUES
(1, 'admin', '$2y$10$EmAKnZxA7KETDmLPgvsFa.zSkd/1RgJwfa9SLZ.qptPOXhDuclVHy', 'admin@example.com', 'super admin'),
(2, 'user_admin', '$2y$10$KCljbye3yfPg/zMPIv4WLOTy8tSWn5ulxh/hakmOLuai5Fwfo.pbu', 'useradmin@example.com', 'user admin'),
(3, 'admin_manager', '$2y$10$/mD2aLkUuqCAFkAYdhNg2u0AnVP44lvSqt9iw7d.tMSWVrnVr2oWi', 'adminmanager@example.com', 'admin manager'),
(4, 'product_admin', '$2y$10$mbBDT8bCihrRZz/bgpaC6OWXMvwDdie1yOTlxkIHOxAjI2GGxHJWS', 'productadmin@example.com', 'product admin'),
(5, 'order_admin', '$2y$10$6fF6eKkraui/jQS9J6sawO9W6R1thiuzEeotWkYUvnWi5su8ot.7C', 'orderadmin@example.com', 'order admin'),
(6, 'support_admin', '$2y$10$1hVVlDAiWVCcA7OQ5k5JqOddpv0i1RarEDjwDChR.EgeprsXBEY..', 'supportadmin@example.com', 'support admin'),
(7, 'analyst', '$2y$10$Ypqa7ilXbQm.LF7.pL5nW.gIxLmlgkARqkcAM/Vl.uklxsnz754nG', 'analyst@example.com', 'analyst');

-- ROLE PERMISSIONS (default permissions for each role)
-- Admin Manager
INSERT INTO Role_Permission (role, resource, action) VALUES
('admin manager', 'admins', 'view'),
('admin manager', 'admins', 'create'),
('admin manager', 'admins', 'update'),
('admin manager', 'admins', 'delete'),
('admin manager', 'dashboard', 'view');

-- User Admin
INSERT INTO Role_Permission (role, resource, action) VALUES
('user admin', 'members', 'view'),
('user admin', 'members', 'create'),
('user admin', 'members', 'update'),
('user admin', 'members', 'delete'),
('user admin', 'feedback', 'view'),
('user admin', 'dashboard', 'view');

-- Product Admin
INSERT INTO Role_Permission (role, resource, action) VALUES
('product admin', 'products', 'view'),
('product admin', 'products', 'create'),
('product admin', 'products', 'update'),
('product admin', 'products', 'delete'),
('product admin', 'orders', 'view'),
('product admin', 'analytics', 'view'),
('product admin', 'dashboard', 'view');

-- Order Admin
INSERT INTO Role_Permission (role, resource, action) VALUES
('order admin', 'orders', 'view'),
('order admin', 'orders', 'create'),
('order admin', 'orders', 'update'),
('order admin', 'orders', 'delete'),
('order admin', 'members', 'view'),
('order admin', 'products', 'view'),
('order admin', 'analytics', 'view'),
('order admin', 'feedback', 'view'),
('order admin', 'dashboard', 'view');

-- Support Admin
INSERT INTO Role_Permission (role, resource, action) VALUES
('support admin', 'feedback', 'view'),
('support admin', 'feedback', 'create'),
('support admin', 'feedback', 'update'),
('support admin', 'feedback', 'delete'),
('support admin', 'members', 'view'),
('support admin', 'orders', 'view'),
('support admin', 'dashboard', 'view');

-- Analyst
INSERT INTO Role_Permission (role, resource, action) VALUES
('analyst', 'dashboard', 'view'),
('analyst', 'analytics', 'view'),
('analyst', 'members', 'view'),
('analyst', 'orders', 'view'),
('analyst', 'products', 'view'),
('analyst', 'feedback', 'view');

-- Sample custom permissions (optional - can be set via manage_permissions.php)
-- Example: Give user_admin additional access to view orders and products
INSERT INTO Admin_Permission (admin_id, resource, action) VALUES
(2, 'orders', 'view'),
(2, 'products', 'view');

INSERT INTO Cart (member_id) VALUES (1),(2),(3),(4),(5),(6),(7),(8),(9),(10);

INSERT INTO Cart_Item (product_id, cart_id, quantity) VALUES
(1, 1, 1),(1,1,1),(2,1,2),(5,1,3),(7,1,1),(1,4,2),(5,4,1),(8,4,3),(12,4,1),
(3,6,1),(7,7,3),(9,8,2),(11,9,1),(13,10,2),(15,1,3),(2,1,2),(3,2,1),(4,3,1),
(5,4,3),(6,5,1),(7,6,5),(8,7,2),(9,8,1),(9,9,1);

INSERT INTO Shipping_Address (member_id, address_line1, address_line2, city, state, postal_code, country)
VALUES
(1, '123 Jalan A', 'Taman Impian', 'Kuala Lumpur', 'Wilayah Persekutuan', '50000', 'Malaysia'),
(1, '456 Jalan B', NULL, 'George Town', 'Penang', '10250', 'Malaysia'),
(3, '789 Jalan C', 'Pangsapuri Ria', 'Johor Bahru', 'Johor', '80000', 'Malaysia'),
(4, '321 Jalan D', NULL, 'Kuala Lumpur', 'Wilayah Persekutuan', '50200', 'Malaysia'),
(5, '654 Jalan E', 'Taman Maju', 'George Town', 'Penang', '10300', 'Malaysia'),
(6, '987 Jalan F', NULL, 'Shah Alam', 'Selangor', '40000', 'Malaysia'),
(7, '111 Jalan G', 'Kampung Baru', 'Kuala Lumpur', 'Wilayah Persekutuan', '50300', 'Malaysia'),
(8, '222 Jalan H', 'Taman Jaya', 'Johor Bahru', 'Johor', '80100', 'Malaysia'),
(9, '333 Jalan I', NULL, 'George Town', 'Penang', '10400', 'Malaysia'),
(10, '444 Jalan J', 'Taman Sentosa', 'Shah Alam', 'Selangor', '40100', 'Malaysia');

INSERT INTO `Order` (member_id, order_date, total_amount, status, shipping_address_line1) VALUES
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

INSERT INTO Wishlist (member_id) VALUES
(1),
(2),
(3),
(4),
(5),
(6),
(7),
(8),
(9),
(10);

INSERT INTO Wishlist_Item (wishlist_id, product_id) VALUES
(1, 1),
(1, 5),
(1, 8),
(2, 3),
(2, 7),
(3, 2),
(3, 10),
(4, 4),
(4, 12),
(5, 6),
(5, 14),
(6, 9),
(6, 15),
(7, 11),
(7, 16),
(8, 13),
(8, 18),
(9, 17),
(9, 19),
(10, 20);

INSERT INTO Voucher (code, discount_value, discount_type, min_spend, max_discount, usage_limit, usage_per_user, start_date, end_date, status)
VALUES
('WELCOME10', 10.00, 'fixed', 50.00, NULL, 100, 1, '2025-11-01 00:00:00', '2025-12-31 23:59:59', 'active'),
('SAVE20', 20.00, 'percent', 100.00, 50.00, 50, 1, '2025-11-10 00:00:00', '2025-12-25 23:59:59', 'active'),
('HOLIDAY15', 15.00, 'fixed', 80.00, NULL, 200, 2, '2025-12-01 00:00:00', '2025-12-31 23:59:59', 'active'),
('BLACKFRIDAY', 25.00, 'percent', 150.00, 75.00, 500, 1, '2025-11-20 00:00:00', '2025-11-30 23:59:59', 'active'),
('NEWYEAR50', 50.00, 'fixed', 200.00, NULL, 100, 1, '2025-12-31 00:00:00', '2026-01-05 23:59:59', 'inactive'),
('FREESHIP', 0.00, 'fixed', 0.00, NULL, 1000, 1, '2025-11-01 00:00:00', '2026-01-31 23:59:59', 'active'),
('MEGADEAL30', 30.00, 'percent', 250.00, 100.00, 50, 1, '2025-11-15 00:00:00', '2025-12-15 23:59:59', 'active'),
('SPRINGSALE', 15.00, 'percent', 50.00, 30.00, 300, 2, '2026-03-01 00:00:00', '2026-03-31 23:59:59', 'active'),
('VIPCUSTOMER', 100.00, 'fixed', 500.00, NULL, 20, 1, '2025-11-01 00:00:00', '2026-01-01 23:59:59', 'active'),
('FLASHDEAL', 50.00, 'percent', 100.00, 50.00, 100, 1, '2025-11-16 12:00:00', '2025-11-16 18:00:00', 'expired');

SQL;

// Execute all SQL
try {
    $pdo->exec($sql);
    echo "----- Database reset successfully! -----";
} catch (PDOException $e) {
    echo "----- Error running SQL:" . nl2br($e->getMessage(). " -----");
}
?>
