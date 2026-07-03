ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_entity (entity);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_pricelist (fk_pricelist);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_datec (datec);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_user (fk_user);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_product (fk_product);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_categorie (fk_cat);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_categorie_propal (fk_cat_propal);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_categorie_order (fk_cat_order);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_categorie_invoice (fk_cat_invoice);
ALTER TABLE llx_pricelist_log ADD INDEX idx_pricelist_log_categorie_contract (fk_cat_contract);

