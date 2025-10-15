ALTER TABLE llx_pricelist ADD INDEX idx_pricelist_product (fk_product);
ALTER TABLE llx_pricelist ADD INDEX idx_pricelist_societe (fk_soc);
ALTER TABLE llx_pricelist ADD INDEX idx_pricelist_categorie (fk_categorie);
ALTER TABLE llx_pricelist ADD INDEX idx_pricelist_user_creation (fk_user_creation);

ALTER TABLE llx_pricelist ADD CONSTRAINT fk_pricelist_fk_user_creation FOREIGN KEY (fk_user_creation) REFERENCES llx_user (rowid);
