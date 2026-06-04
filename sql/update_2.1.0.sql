ALTER TABLE llx_pricelist ADD COLUMN IF NOT EXISTS entity integer DEFAULT 1 NOT NULL AFTER rowid;
ALTER TABLE llx_pricelist ADD COLUMN IF NOT EXISTS fk_cat_propal integer DEFAULT NULL AFTER fk_cat;
ALTER TABLE llx_pricelist ADD COLUMN IF NOT EXISTS fk_cat_contract integer DEFAULT NULL AFTER fk_cat_propal;

UPDATE llx_pricelist SET entity = 1 WHERE entity IS NULL OR entity < 1;
UPDATE llx_pricelist SET fk_soc = NULL WHERE fk_soc IN (0, -1);
UPDATE llx_pricelist SET fk_cat = NULL WHERE fk_cat IN (0, -1);
UPDATE llx_pricelist SET fk_cat_propal = NULL WHERE fk_cat_propal IN (0, -1);
UPDATE llx_pricelist SET fk_cat_contract = NULL WHERE fk_cat_contract IN (0, -1);

CREATE TABLE IF NOT EXISTS llx_categorie_contract(
	fk_categorie integer NOT NULL,
	fk_contract integer NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
