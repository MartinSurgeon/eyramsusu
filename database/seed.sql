-- seed.sql: Official Starter seed data for Eyram Susu
USE eyramsusu;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE notifications;
TRUNCATE TABLE daily_handovers;
TRUNCATE TABLE payouts;
TRUNCATE TABLE deposits;
TRUNCATE TABLE susu_cards;
TRUNCATE TABLE customers;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Insert Admin (Agbenyenuse Stanley / Eyram / Seyram1) and Collector (Kuddy Peggy / Peggy / Peggy123)
-- Password hashes use PHP password_hash (bcrypt)
INSERT INTO users (id, full_name, username, password_hash, role, phone, is_active) VALUES 
(1, 'Agbenyenuse Stanley', 'Eyram', '$2y$10$wE9iF8Ue13Y2wYxN7B2D2eCj4n3w8P4q7R1s9T0u2V3w4X5y6Z7a8', 'admin', '0553224837', 1),
(2, 'Kuddy Peggy', 'Peggy', '$2y$10$yF8jG9Vf24Z3xZyO8C3E3fDk5o4x9Q5r8S2t0U1v3W4x5Y6z7A8b9', 'collector', '0555495796', 1);

-- 2. Official Registered Customers at Adaklu Waya (Assigned to Kuddy Peggy)
INSERT INTO customers (id, account_number, full_name, phone, location, assigned_collector_id, change_balance, is_active) VALUES
(1, '0035', 'kottoh Patience', '0242057910', 'Adaklu Waya', 2, 0.00, 1),
(2, '0036', 'Soglo Vivian', '0592663701', 'Adaklu Waya', 2, 0.00, 1),
(3, '0005', 'Kudi Lucky', '0545482671', 'Adaklu Waya', 2, 0.00, 1),
(4, '0022', 'Wase Yaovi', '0241164340', 'Adaklu Waya', 2, 0.00, 1),
(5, '0021', 'Kpedo Bismarck', '0546249032', 'Adaklu Waya', 2, 0.00, 1),
(6, '0043', 'Anyadi Emmanuel', '0597515726', 'Adaklu Waya', 2, 0.00, 1),
(7, '0004', 'Deku Wonder', '0249771299', 'Adaklu Waya', 2, 0.00, 1);

-- 3. Active 31-Space Susu Cards (Card #1, 0 spaces filled)
INSERT INTO susu_cards (id, customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) VALUES
(1, 1, 1, 50.00, 31, 0, 0.00, 'active'),
(2, 2, 1, 50.00, 31, 0, 0.00, 'active'),
(3, 3, 1, 100.00, 31, 0, 0.00, 'active'),
(4, 4, 1, 20.00, 31, 0, 0.00, 'active'),
(5, 5, 1, 10.00, 31, 0, 0.00, 'active'),
(6, 6, 1, 10.00, 31, 0, 0.00, 'active'),
(7, 7, 1, 20.00, 31, 0, 0.00, 'active');
