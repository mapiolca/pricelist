CREATE TABLE IF NOT EXISTS llx_categorie_contract(
	fk_categorie integer NOT NULL,
	fk_contract integer NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
