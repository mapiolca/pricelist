ALTER TABLE `llx_pricelist` ADD `tx_discount` DOUBLE NULL DEFAULT NULL AFTER `price`;
ALTER TABLE `llx_pricelist` CHANGE `price` `price` DOUBLE NULL DEFAULT NULL;
ALTER TABLE `llx_pricelist` ADD `cost_price` DOUBLE NULL DEFAULT NULL AFTER `tx_discount`;
