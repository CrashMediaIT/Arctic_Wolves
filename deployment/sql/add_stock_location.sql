-- Add stock_location column to merchandise_product_sizes
-- Separates inventory into 'in_store' (POS) and 'warehouse' (online shop)
ALTER TABLE `merchandise_product_sizes` 
    ADD COLUMN `stock_location` ENUM('in_store', 'warehouse') NOT NULL DEFAULT 'in_store' AFTER `quantity`;

-- Drop existing unique key and add new one that includes stock_location
ALTER TABLE `merchandise_product_sizes`
    DROP INDEX `unique_product_size`,
    ADD UNIQUE KEY `unique_product_size` (`product_id`, `size`, `stock_location`);

-- Add index for stock_location queries
ALTER TABLE `merchandise_product_sizes`
    ADD INDEX `idx_stock_location` (`stock_location`);
