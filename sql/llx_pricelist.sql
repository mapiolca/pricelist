create table llx_pricelist
(
    rowid               integer AUTO_INCREMENT PRIMARY KEY,
    fk_product          integer NOT NULL,
    fk_soc              integer DEFAULT NULL,
    fk_cat              integer DEFAULT NULL,
    from_qty            double NOT NULL,
    price               double DEFAULT NULL,
    tx_discount         double DEFAULT NULL,
    fk_user_creation    integer NOT NULL,
    import_key          varchar(14) DEFAULT NULL
) ENGINE=innodb;
