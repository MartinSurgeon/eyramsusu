-- database/reset_all_cards.sql
-- ════════════════════════════════════════════════════════════════════
-- FULL CARD DATA RESET — Run once to start all customers afresh
-- Deletes ALL transaction data but keeps customer profiles intact.
-- ⚠  This is IRREVERSIBLE. Run on both LOCAL and LIVE databases.
-- ════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Clear all deposit transaction rows
TRUNCATE TABLE deposits;

-- 2. Clear all daily handover records
TRUNCATE TABLE daily_handovers;

-- 3. Clear all payout requests
TRUNCATE TABLE payouts;

-- 4. Clear all susu card records (each customer now has no card)
TRUNCATE TABLE susu_cards;

-- 5. Clear all in-app notifications (fresh slate)
TRUNCATE TABLE notifications;

-- 6. Reset customer change_balance to 0.00 (no leftover float)
UPDATE customers SET change_balance = 0.00;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Verification ──────────────────────────────────────────────────
SELECT 'customers'      AS tbl, COUNT(*) AS rows FROM customers
UNION ALL
SELECT 'susu_cards',     COUNT(*) FROM susu_cards
UNION ALL
SELECT 'deposits',       COUNT(*) FROM deposits
UNION ALL
SELECT 'payouts',        COUNT(*) FROM payouts
UNION ALL
SELECT 'daily_handovers',COUNT(*) FROM daily_handovers
UNION ALL
SELECT 'notifications',  COUNT(*) FROM notifications;
