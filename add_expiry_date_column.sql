ALTER TABLE `ptgates_billing_history` ADD COLUMN `expiry_date` DATETIME NULL COMMENT '멤버십/상품 만료일' AFTER `transaction_date`;
