CREATE DATABASE IF NOT EXISTS eyramsusu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eyramsusu;

-- 1. App Users (Admins and Collectors)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'collector') NOT NULL DEFAULT 'collector',
    phone VARCHAR(20) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Customers
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    location VARCHAR(255) NULL,
    assigned_collector_id INT NULL,
    change_balance DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_collector_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Susu Cards (31 spaces per card)
CREATE TABLE IF NOT EXISTS susu_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    card_number INT NOT NULL DEFAULT 1,
    daily_amount DECIMAL(10,2) NOT NULL,
    total_spaces INT NOT NULL DEFAULT 31,
    spaces_filled INT NOT NULL DEFAULT 0,
    total_saved DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('active', 'completed', 'closed_early') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Deposits (Each space filled)
CREATE TABLE IF NOT EXISTS deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    customer_id INT NOT NULL,
    collector_id INT NOT NULL,
    space_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    deposit_date DATE NOT NULL,
    handover_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES susu_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (collector_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 5. Payouts (Customer takes money and closes card)
CREATE TABLE IF NOT EXISTS payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    customer_id INT NOT NULL,
    collector_id INT NOT NULL,
    total_saved DECIMAL(10,2) NOT NULL,
    business_fee DECIMAL(10,2) NOT NULL,
    change_refunded DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    customer_payout DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'paid') NOT NULL DEFAULT 'pending',
    reason VARCHAR(255) NULL,
    approved_by INT NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES susu_cards(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (collector_id) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 6. Daily Cash Handovers (Collector gives cash to Admin)
CREATE TABLE IF NOT EXISTS daily_handovers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collector_id INT NOT NULL,
    handover_date DATE NOT NULL,
    expected_cash DECIMAL(10,2) NOT NULL,
    cash_received DECIMAL(10,2) NOT NULL,
    difference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('submitted', 'approved', 'has_difference') NOT NULL DEFAULT 'submitted',
    collector_note TEXT NULL,
    admin_note TEXT NULL,
    approved_by INT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (collector_id) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;
