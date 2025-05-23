-- Database Schema untuk POS Ecommerce Somay Distributor
-- Target: B2C, Guest Checkout, Area Tangerang Selatan

CREATE DATABASE pos_somay_ecommerce;
USE pos_somay_ecommerce;

-- Table: Admin/User Management
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('super_admin', 'admin', 'operator') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: Kategori Produk
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: Produk Somay
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(12,2) NOT NULL,
    discount_price DECIMAL(12,2) NULL,
    weight DECIMAL(8,2) DEFAULT 0, -- dalam gram
    stock_quantity INT DEFAULT 0,
    min_stock INT DEFAULT 5, -- minimum stock alert
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(200),
    meta_description VARCHAR(300),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured)
);

-- Table: Gambar Produk
CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(200),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
);

-- Table: Area Pengiriman (Tangerang Selatan)
CREATE TABLE shipping_areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    area_name VARCHAR(100) NOT NULL, -- Contoh: Serpong, BSD, Pondok Aren
    postal_code VARCHAR(10),
    shipping_cost DECIMAL(10,2) NOT NULL,
    estimated_delivery VARCHAR(50), -- Contoh: "1-2 hari kerja"
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: Orders/Transaksi
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(20) UNIQUE NOT NULL, -- Format: ORD240523001
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(100),
    shipping_address TEXT NOT NULL,
    shipping_area_id INT,
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cod', 'transfer') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    admin_notes TEXT, -- Internal notes
    payment_proof VARCHAR(255), -- Bukti transfer
    confirmed_at TIMESTAMP NULL,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shipping_area_id) REFERENCES shipping_areas(id) ON DELETE SET NULL,
    INDEX idx_order_number (order_number),
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_status (order_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_date (created_at)
);

-- Table: Detail Order
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL, -- Simpan nama produk saat order
    product_sku VARCHAR(50) NOT NULL,
    price DECIMAL(12,2) NOT NULL, -- Harga saat order
    quantity INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
);

-- Table: Stock Movement/History
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL, -- Positif untuk masuk, negatif untuk keluar
    previous_stock INT NOT NULL,
    current_stock INT NOT NULL,
    reference_type ENUM('purchase', 'sale', 'adjustment', 'return') NOT NULL,
    reference_id INT, -- ID order jika dari penjualan
    notes VARCHAR(500),
    admin_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_product (product_id),
    INDEX idx_type (movement_type),
    INDEX idx_date (created_at)
);

-- Table: Settings Aplikasi
CREATE TABLE app_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    is_editable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: Contact Messages (Opsional untuk customer service)
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    admin_reply TEXT,
    replied_at TIMESTAMP NULL,
    replied_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (replied_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_date (created_at)
);

-- INSERT DATA AWAL

-- Insert Admin Default
INSERT INTO admins (username, email, password, full_name, phone, role) VALUES
('admin', 'admin@somay.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', '081234567890', 'super_admin');

-- Insert Kategori Default
INSERT INTO categories (name, slug, description) VALUES
('Somay Original', 'somay-original', 'Somay dengan isian daging dan sayuran segar'),
('Somay Special', 'somay-special', 'Somay dengan isian premium'),
('Siomay Ayam', 'siomay-ayam', 'Siomay dengan isian daging ayam pilihan'),
('Siomay Udang', 'siomay-udang', 'Siomay dengan isian udang segar'),
('Bumbu & Saus', 'bumbu-saus', 'Bumbu kacang dan saus pelengkap');

-- Insert Area Pengiriman Tangerang Selatan
INSERT INTO shipping_areas (area_name, shipping_cost, estimated_delivery) VALUES
('Serpong', 10000, '1-2 jam'),
('BSD City', 10000, '1-2 jam'),
('Pondok Aren', 12000, '2-3 jam'),
('Ciputat', 15000, '2-4 jam'),
('Pamulang', 15000, '2-4 jam'),
('Setu', 18000, '3-5 jam'),
('Pondok Betung', 12000, '2-3 jam');

-- Insert Settings Default
INSERT INTO app_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'Somay Ecommerce', 'string', 'Nama website'),
('site_phone', '081234567890', 'string', 'Nomor telepon toko'),
('site_email', 'info@somay.com', 'string', 'Email toko'),
('site_address', 'Tangerang Selatan, Banten', 'string', 'Alamat toko'),
('min_order_amount', '25000', 'number', 'Minimal pembelian'),
('free_shipping_min', '100000', 'number', 'Minimal gratis ongkir'),
('order_prefix', 'ORD', 'string', 'Prefix nomor order'),
('whatsapp_number', '6281234567890', 'string', 'Nomor WhatsApp untuk customer service');

-- Insert Produk Sample
INSERT INTO products (category_id, sku, name, slug, description, price, stock_quantity, is_featured) VALUES
(1, 'SMY001', 'Somay Original Isi 10', 'somay-original-isi-10', 'Somay original dengan isian daging sapi dan sayuran segar, isi 10 buah', 25000, 50, TRUE),
(1, 'SMY002', 'Somay Original Isi 20', 'somay-original-isi-20', 'Somay original dengan isian daging sapi dan sayuran segar, isi 20 buah', 45000, 30, TRUE),
(2, 'SMYS001', 'Somay Special Mix Isi 15', 'somay-special-mix-isi-15', 'Somay special dengan variasi isian premium, isi 15 buah', 40000, 25, TRUE),
(3, 'SMYA001', 'Siomay Ayam Isi 12', 'siomay-ayam-isi-12', 'Siomay dengan isian daging ayam pilihan, isi 12 buah', 32000, 40, FALSE),
(4, 'SMYU001', 'Siomay Udang Isi 8', 'siomay-udang-isi-8', 'Siomay dengan isian udang segar, isi 8 buah', 35000, 20, TRUE),
(5, 'SAUS001', 'Bumbu Kacang Original 250ml', 'bumbu-kacang-original-250ml', 'Bumbu kacang original khas somay, kemasan 250ml', 15000, 100, FALSE);

-- Create Views untuk Dashboard Analytics
CREATE VIEW daily_sales AS
SELECT 
    DATE(created_at) as sale_date,
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_order_value
FROM orders 
WHERE order_status NOT IN ('cancelled')
GROUP BY DATE(created_at)
ORDER BY sale_date DESC;

CREATE VIEW top_products AS
SELECT 
    p.id,
    p.name,
    p.sku,
    SUM(oi.quantity) as total_sold,
    SUM(oi.subtotal) as total_revenue
FROM products p
JOIN order_items oi ON p.id = oi.product_id
JOIN orders o ON oi.order_id = o.id
WHERE o.order_status NOT IN ('cancelled')
GROUP BY p.id, p.name, p.sku
ORDER BY total_sold DESC;

CREATE VIEW sales_by_area AS
SELECT 
    sa.area_name,
    COUNT(o.id) as total_orders,
    SUM(o.total_amount) as total_revenue
FROM shipping_areas sa
JOIN orders o ON sa.id = o.shipping_area_id
WHERE o.order_status NOT IN ('cancelled')
GROUP BY sa.area_name
ORDER BY total_revenue DESC;