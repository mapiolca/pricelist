ALTER TABLE llx_pricelist ADD COLUMN IF NOT EXISTS fk_cat_order integer DEFAULT NULL AFTER fk_cat_propal;
ALTER TABLE llx_pricelist ADD COLUMN IF NOT EXISTS fk_cat_invoice integer DEFAULT NULL AFTER fk_cat_order;
ALTER TABLE llx_pricelist ADD COLUMN IF NOT EXISTS use_product_cost_price tinyint DEFAULT 0 NOT NULL AFTER cost_price;

CREATE TABLE IF NOT EXISTS llx_pricelist_log
(
    rowid               integer AUTO_INCREMENT PRIMARY KEY,
    entity              integer DEFAULT 1 NOT NULL,
    fk_pricelist        integer NOT NULL,
    datec               datetime NOT NULL,
    fk_user             integer DEFAULT NULL,
    change_type         varchar(16) NOT NULL,
    fk_product          integer NOT NULL,
    fk_soc              integer DEFAULT NULL,
    fk_cat              integer DEFAULT NULL,
    fk_cat_propal       integer DEFAULT NULL,
    fk_cat_order        integer DEFAULT NULL,
    fk_cat_invoice      integer DEFAULT NULL,
    fk_cat_contract     integer DEFAULT NULL,
    from_qty            double NOT NULL,
    price               double DEFAULT NULL,
    tx_discount         double DEFAULT NULL,
    cost_price          double DEFAULT NULL,
    use_product_cost_price tinyint DEFAULT 0 NOT NULL,
    import_key          varchar(14) DEFAULT NULL
) ENGINE=innodb;

ALTER TABLE llx_pricelist_log ADD COLUMN IF NOT EXISTS use_product_cost_price tinyint DEFAULT 0 NOT NULL AFTER cost_price;

-- Keys and initial history snapshots are synchronized idempotently by modPriceList::syncPriceListSchema().
