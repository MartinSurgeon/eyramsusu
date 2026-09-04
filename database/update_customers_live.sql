-- ========================================================
-- EYRAM SUSU: Customer Database Sync & Account Correction
-- Generated: 2026-09-04 11:06:18
-- Total Customers: 111 (Account #1 to #111)
-- ========================================================

-- --------------------------------------------------------
-- Step 0: Ensure 'gender' Column Exists in Customers Table
-- --------------------------------------------------------
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `gender` VARCHAR(10) DEFAULT NULL AFTER `full_name`;

START TRANSACTION;

-- --------------------------------------------------------
-- Step 1: Update Existing Customers (Preserving IDs & History)
-- --------------------------------------------------------
UPDATE `customers` SET `account_number` = '4', `full_name` = 'Deku Wonder', `gender` = 'F', `phone` = '0249771299', `location` = 'Adaklu Waya, Roundabout' WHERE `id` = 7 OR `account_number` = '4';
UPDATE `customers` SET `account_number` = '5', `full_name` = 'Kudi Lucky', `gender` = 'M', `phone` = '0545482671', `location` = 'Adaklu Waya, Roundabout' WHERE `id` = 3 OR `account_number` = '5';
UPDATE `customers` SET `account_number` = '21', `full_name` = 'Kpedo Bismark', `gender` = 'M', `phone` = '0546249032', `location` = 'Adaklu Waya, Roundabout' WHERE `id` = 5 OR `account_number` = '21';
UPDATE `customers` SET `account_number` = '22', `full_name` = 'Wase Yaovi', `gender` = 'M', `phone` = '0241164340', `location` = 'Adaklu Waya, Roundabout' WHERE `id` = 4 OR `account_number` = '22';
UPDATE `customers` SET `account_number` = '35', `full_name` = 'Kottoh Patience', `gender` = 'F', `phone` = '0242057910', `location` = 'Adaklu Waya, Roundabout' WHERE `id` = 1 OR `account_number` = '35';
UPDATE `customers` SET `account_number` = '36', `full_name` = 'Soglo Vivian', `gender` = 'F', `phone` = '0592663701', `location` = 'Adaklu Waya, Roundabout' WHERE `id` = 2 OR `account_number` = '36';
UPDATE `customers` SET `account_number` = '43', `full_name` = 'Anyadi Emmanuel', `gender` = 'M', `phone` = '0597515726', `location` = 'Adaklu Waya' WHERE `id` = 6 OR `account_number` = '43';

-- --------------------------------------------------------
-- Step 2: Insert Remaining Customers (Assigned to Kuddy Peggy / Collector ID 2)
-- --------------------------------------------------------
INSERT INTO `customers` (`account_number`, `full_name`, `gender`, `phone`, `location`, `assigned_collector_id`, `change_balance`, `is_active`) VALUES
('1', 'Agbenyenuse Philomena', 'F', '0245832311', 'Adaklu Waya, Round About', 2, 0.00, 1),
('2', 'Zuh William', 'M', '0246703454', 'Adaklu Waya, Round About', 2, 0.00, 1),
('3', 'Kettey Delight', 'F', '0240054489', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('6', 'Amuzu Sefakor', 'F', '0535866971', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('7', 'Dzodanu Irene', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('8', 'Teli Charlotte', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('9', 'Salu Sedinam', 'F', '0545262985', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('10', 'Ketteh Delasi', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('11', 'Agbo Divine Elikem', 'M', '0557739951', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('12', 'Afedo Akorfa', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('13', 'Doe Patrick', 'M', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('14', 'Aklika Dodzi', 'M', '0247200062', 'Adaklu Waya, Market', 2, 0.00, 1),
('15', 'Agbenyegah Patricia', 'F', '0599876237', 'Adaklu Waya, Market', 2, 0.00, 1),
('16', 'Sunday', 'M', '0241038417', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('17', 'Adzator Bolgah', 'M', '0559140151', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('18', 'Razak Vulga', 'M', '0247200098', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('19', 'Akpakra Prince', 'M', '0508186059', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('20', 'Kufe Edem', 'F', '0540673172', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('23', 'Dzordzorme Peace', 'F', '0241774219', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('24', 'Aglah Rose', 'F', '0240431020', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('25', 'Agbeve Faith', 'F', '0247632534', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('26', 'Agbeve Judith', 'F', '0558884157', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('27', 'Anyormi Samuel', 'M', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('28', 'Mrs. Klutse Stella', 'F', '0249274249', 'Adaklu Waya, E.P Church', 2, 0.00, 1),
('29', 'Ali Fitter', 'M', '0594087133', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('30', 'Man Greato', 'M', '0553985681', 'Gbagbadeve', 2, 0.00, 1),
('31', 'Goka Colins', 'M', '', 'Adaklu Waya, Roadleader', 2, 0.00, 1),
('32', 'Donkor Daniel', 'M', '0246618331', 'Adaklu Waya, Market', 2, 0.00, 1),
('33', 'Kudzrame Stephen', 'M', '0552159918', 'Adaklu Waya, Market', 2, 0.00, 1),
('34', 'Donkor Enyonam', 'F', '0534821113', 'Adklu Waya, Market', 2, 0.00, 1),
('37', 'Master Kekeli', 'M', '0532421931', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('38', 'Tanti', 'F', '', 'Adaklu Waya, Awudu House', 2, 0.00, 1),
('39', 'Blewusi Emil', 'M', '0242005979', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('40', 'Mohammed Aisha', 'F', '0245590270', 'Adaklu Waya, Market', 2, 0.00, 1),
('41', 'Wordi Gifty', 'F', '0548528682', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('42', 'Segbefia Vivian', 'F', '', 'Adaklu Waya, Adesec', 2, 0.00, 1),
('44', 'Dawuda Mary', 'F', '0547762651', 'Adaklu Waya, E.P Church', 2, 0.00, 1),
('45', 'Agbenyenuse Bless', 'M', '0558056197', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('46', 'Akpabla Mawusi', 'F', '0549108393', 'Adaklu Waya, Xalavia', 2, 0.00, 1),
('47', 'Deku Jemima', 'F', '0551484002', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('48', 'Addu-Danquah Ivy', 'F', '0247338421', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('49', 'Aheto Hannah', 'F', '0551273482', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('50', 'Amedzro Patricia', 'F', '0249666393', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('51', 'Ametor Saviour', 'F', '0552805709', 'Adaklu Waya', 2, 0.00, 1),
('52', 'Agbenyenuse Daniel', 'M', '0599035189', 'Adaklu Waya, Yellow House', 2, 0.00, 1),
('53', 'Koboe Emmanuel', 'M', '0556130550', 'Adaklu Waya, Abaya', 2, 0.00, 1),
('54', 'Akumani Dzigbordi', 'F', '0541439375', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('55', 'Agbo Fanuel', 'M', '0247219844', 'Adaklu Waya, Agbo Feme', 2, 0.00, 1),
('56', 'Amadu Believe', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('57', 'Agbi Harriet', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('58', 'Donkor Norvinyo', 'M', '0240213409', 'Adaklu Waya, Round About', 2, 0.00, 1),
('59', 'Anyagli Beauty', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('60', 'Dzah Mable', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('61', 'Kartey Emmanuella', 'F', '0539104457', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('62', 'Gati Pearl Afi Ntifafa', 'F', '0597167794', 'Adaklu Waya, Abaya', 2, 0.00, 1),
('63', 'Agbogah Augusta', 'F', '5553384947', 'Adaklu Waya, Round About', 2, 0.00, 1),
('64', 'Dzah Pearl', 'F', '', 'Adaklu Waya, E.P Church', 2, 0.00, 1),
('65', 'Asinyo Bernice', 'F', '0546602557', 'Adaklu Waya, E.P Church', 2, 0.00, 1),
('66', 'Master Awudu', 'M', '0247292081', 'Adaklu Waya, Awudu House', 2, 0.00, 1),
('67', 'Dufe Mawufemor', 'F', '0591739243', 'Adklu Waya, Round About', 2, 0.00, 1),
('68', 'Soti Precious', 'F', '0599224654', 'Adaklu Anfoe', 2, 0.00, 1),
('69', 'Agbotey Kofi Richard', 'M', '', 'Adaklu Waya', 2, 0.00, 1),
('70', 'Amegboe Juliet', 'F', '0246238501', 'Adaklu Waya, Xalavia', 2, 0.00, 1),
('71', 'Kugbeadzor Sena', 'M', '0244961386', 'Adaklu Waya, E.P Church', 2, 0.00, 1),
('72', 'Dzah Vicentia', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('73', 'Bansah Abigail', 'F', '0536406284', 'Adaklu Waya, Round About', 2, 0.00, 1),
('74', 'Avor Kobby', 'M', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('75', 'Deku Sewa', 'F', '0538853655', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('76', 'Sokpah Eunice', 'F', '0535920034', 'Adaklu Waya, Awudu House', 2, 0.00, 1),
('77', 'Tsigbey Evelyn', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1),
('78', 'Adzalo Norvinyo', 'F', '', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('79', 'Ametefe Comfort', 'F', '0543476887', 'Adaklu Waya, Kpota', 2, 0.00, 1),
('80', 'Akpakpa Christiana', 'F', '0539119858', 'Adaklu Sofa', 2, 0.00, 1),
('81', 'Aklamanu Jenet', 'F', '0540307079', 'Adaklu Waya, Kpota', 2, 0.00, 1),
('82', 'Morti Faustine', 'F', '', 'Adaklu Sofa', 2, 0.00, 1),
('83', 'Adanu Venunye', 'F', '0257176465', 'Adaklu Sofa', 2, 0.00, 1),
('84', 'Ofori Ernest', 'M', '', 'Adklu Waya, Roundabout', 2, 0.00, 1),
('85', 'Dzah Gina', 'F', '0257992243', 'Adklu Waya, Round About', 2, 0.00, 1),
('86', 'Kpedo Mawuli', 'M', '0257933252', 'Adaklu Waya, Opp. Dzidzorkporkpor', 2, 0.00, 1),
('87', 'Anyormi Comfort', 'F', '0550373213', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('88', 'Deku Irene', 'F', '', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('89', 'Deku Agness', 'F', '0538510114', 'Adaklu Waya, Yellow House', 2, 0.00, 1),
('90', 'Tormeti Freda', 'F', '0534237824', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('91', 'Akpatsa Dasha', 'F', '0247632534', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('92', 'Kpedo Gifty', 'F', '0558095854', 'Adaklu Waya, Roundabout', 2, 0.00, 1),
('93', 'Misrowoda Joanita', 'F', '', 'Adaklu Waya, Agbo Feme', 2, 0.00, 1),
('94', 'Avor Linda', 'F', '0599448224', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('95', 'Adanu Diana', 'F', '0543741439', 'Adaklu Sofa', 2, 0.00, 1),
('96', 'Galah Joyce', 'F', '0548441093', 'Adaklu Sofa', 2, 0.00, 1),
('97', 'Soglo Believe', 'F', '', 'Adaklu Sofa', 2, 0.00, 1),
('98', 'Mexatsor Rebecca', 'F', '0530596534', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('99', 'Dotsey Worlasi', 'F', '0543522969', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('100', 'Amafu Noah', 'F', '', 'Adaklu Waya, Xalavia', 2, 0.00, 1),
('101', 'Seidu Mariam', 'F', '0591015459', 'Adaklu Waya, Round About', 2, 0.00, 1),
('102', 'Geh Sylvia', 'F', '', 'Adaklu Kpodzi', 2, 0.00, 1),
('103', 'Aboni Dogbeda', 'M', '0544539611', 'Adaklu Waya, Xalavia', 2, 0.00, 1),
('104', 'Amegboe Sabastian', 'M', '0548423264', 'Adaklu Waya, Xalavia', 2, 0.00, 1),
('105', 'Dzah Flourence', 'F', '0543066010', 'Adaklu Waya, Market', 2, 0.00, 1),
('106', 'Kugbeadzor Angela', 'F', '0559193436', 'Adaklu Waya, Agedzi', 2, 0.00, 1),
('107', 'Dzah Wisdom', 'M', '0240220204', 'Adaklu Waya, E.P Church', 2, 0.00, 1),
('108', 'Klu Martinos', 'M', '', 'Adaklu Waya, Old Ablorme', 2, 0.00, 1),
('109', 'Agbenyenuse Sampsom', 'M', '0596640303', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1),
('110', 'Avor Esther', 'F', '0248531636', 'Adaklu Waya, Old Ablorme', 2, 0.00, 1),
('111', 'Akpabla Beauty', 'F', '0240143994', 'Adaklu Waya Roundabout', 2, 0.00, 1)
ON DUPLICATE KEY UPDATE
    `full_name` = VALUES(`full_name`),
    `gender` = VALUES(`gender`),
    `phone` = IF(VALUES(`phone`) != '', VALUES(`phone`), `phone`),
    `location` = VALUES(`location`);

COMMIT;
