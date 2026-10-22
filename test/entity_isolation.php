<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Included by run.php: real module methods, simulated SQL and access scopes.
class EntityIsolationDB extends TestDB
{
	public $target = '';
	public function query($sql)
	{
		if (strpos($sql, 'SELECT ct.fk_categorie') === 0) {
			$this->queries[] = $sql;
			return new Result(array((object) array('fk_categorie' => 77)));
		}
		if (strpos($sql, 'SELECT rowid, fk_product') === 0 && $this->target !== ''
			&& !preg_match('/t\.'.$this->target.' (?:=|IN \()/', $sql)) {
			$this->queries[] = $sql;
			return new Result();
		}
		return parent::query($sql);
	}
}

$isolationDB = new EntityIsolationDB();
$isolation = new PriceList($isolationDB);
$isolationHooks = new ActionsPriceList($isolationDB);
$buyer = new Societe($isolationDB);
Product::$data[10] = array('entity' => 1, 'type' => 0, 'price_min' => 200);
$user->allowed = array('product.read', 'societe.lire');
$conf->entity = 2;
foreach (array('product', 'propal', 'commande', 'facture', 'facturerec', 'contrat') as $scope) { $scopes[$scope] = '1,2'; }

foreach (array('propal' => 'updatePropalLines', 'commande' => 'updateOrderLines', 'facture' => 'updateInvoiceLines',
	'facturerec' => 'updateInvoiceLines', 'contrat' => 'updateContractLines') as $element => $refresh) {
	$document = new CommercialDocument(); $document->element = $element;
	$document->lines = array(clone $line);
	$isolation->error = '';
	check($isolation->get_price(10, $buyer, 3, $document) === 0 && $isolation->error === '', 'foreign tariff below minimum cannot block '.$element);
	$posted = array('price_ht' => '350', 'remise_percent' => '5', 'elprice' => '350', 'elremise_percent' => '5');
	foreach (array('applyPriceToPostFromPriceList', 'applyContractLinePriceToPostFromPriceList') as $apply) {
		$_POST = $posted;
		invoke($isolationHooks, $apply, 10, $buyer, 3, $document);
		check($_POST === $posted, 'foreign tariff leaves add/edit inputs unchanged '.$element.' '.$apply);
	}
	invoke($isolationHooks, $refresh, $document, $buyer);
	check($document->calls === array(), 'foreign tariff never updates '.$element);
}
check(count(array_filter($isolationDB->queries, static function ($sql) {
	return strpos($sql, 'SELECT rowid, fk_product') === 0 && strpos($sql, 't.entity = 1') !== false;
})) === 0, 'shared product owner is never queried for entity 2 rules');
check($isolation->get_price(10, $buyer, 3) === 0, 'legacy caller without document uses current entity only');

Product::$data[10]['price_min'] = 0;
$isolationDB->bestEntity = 2;
$localRule = $isolation->get_price(10, $buyer, 3);
check(is_object($localRule) && $localRule->cost_context_entity === 2, 'own tariff applies despite shared product owner');
$sharedDocument = (object) array('element' => 'propal', 'entity' => 1, 'id' => 0);
check($isolation->get_price(10, $buyer, 3, $sharedDocument) === 0, 'shared document does not inherit consultation entity tariffs');
$isolationDB->bestEntity = 1;
$ownerRule = $isolation->get_price(10, $buyer, 3, $sharedDocument);
check(is_object($ownerRule) && $ownerRule->cost_context_entity === 1, 'accessible shared document retains its owner tariffs');
$conf->entity = 1;
check(is_object($isolation->get_price(10, $buyer, 3)), 'owner entity retains its own tariff');
$conf->entity = 2;

Societe::$data[7] = 1; $buyer->id = 7;
Categorie::$customerCategories = array((object) array('id' => 77));
$targets = array('fk_soc' => null, 'fk_cat' => null);
if (pricelistIsPropalCategoryAvailable()) { $targets['fk_cat_propal'] = 'propal'; }
if (version_compare(DOL_VERSION, '22.0.0', '>=')) {
	$targets['fk_cat_order'] = 'commande'; $targets['fk_cat_invoice'] = 'facture';
}
if (pricelistIsContractCategoryAvailable()) { $targets['fk_cat_contract'] = 'contrat'; }
foreach (array(0, 1) as $priority) {
	$settings['PRICELIST_DOCUMENT_CATEGORY_PRIORITY'] = $priority;
	foreach ($targets as $target => $element) {
		$isolationDB->target = $target; $isolationDB->bestEntity = 1;
		$context = $element ? (object) array('element' => $element, 'entity' => 2, 'id' => 12) : null;
		check($isolation->get_price(10, $buyer, 3, $context) === 0, 'foreign targeted tariff isolated '.$target.' priority '.$priority);
		$isolationDB->bestEntity = 2;
		check(is_object($isolation->get_price(10, $buyer, 3, $context)), 'local targeted tariff retained '.$target.' priority '.$priority);
	}
}
$scopes['product'] = '2';
check($isolation->get_price(10, $buyer, 3) === -1, 'unshared product still forbidden');
$scopes['product'] = '1,2'; $user->allowed = array();
check($isolation->get_price(10, $buyer, 3) === -1, 'missing product right still forbidden');
$user->allowed = array('product.read', 'societe.lire');
$scopes['propal'] = '2';
check($isolation->get_price(10, $buyer, 3, $sharedDocument) === -1, 'inaccessible document owner still forbidden');
check($conf->entity === 2, 'rule lookup never switches authenticated entity');

// Sale-price sharing is independent from product sharing and remains directional.
$scopes['propal'] = '1,2'; $isolationDB->target = '';
$buyer->id = 0; Categorie::$customerCategories = array();
$document = (object) array('element' => 'propal', 'entity' => 2, 'id' => 0);
$scopes['productprice'] = '2'; $isolationDB->bestEntity = 1;
check($isolation->get_price(10, $buyer, 3, $document) === 0, 'product sharing alone never shares tariffs');
$scopes['productprice'] = '1,2';
$sharedRule = $isolation->get_price(10, $buyer, 3, $document);
check(is_object($sharedRule) && $sharedRule->cost_context_entity === 2, 'native sale-price sharing enables foreign tariff without changing cost entity');
check(is_object($isolation->get_price(10, $buyer, 3)), 'legacy caller uses native sale-price sharing');
$isolationDB->queries = array(); $isolationDB->bestEntity = 2;
check(is_object($isolation->get_price(10, $buyer, 3, $document)), 'local tariff still applies when sharing enabled');
check(count(array_filter($isolationDB->queries, static function ($sql) {
	return strpos($sql, 'SELECT rowid, fk_product') === 0 && strpos($sql, 't.entity = 1') !== false;
})) === 0, 'local generic tariff takes precedence over shared generic tariff');
$scopes['productprice'] = '2,3'; $isolationDB->bestEntity = 3;
check(is_object($isolation->get_price(10, $buyer, 3, $document)), 'authorized price entity need not own the product');
$isolationDB->bestEntity = 1;
check($isolation->get_price(10, $buyer, 3, $document) === 0, 'price entity excluded from native sharing is never queried');
$conf->entity = 1; $scopes['productprice'] = '1'; $isolationDB->bestEntity = 2;
check($isolation->get_price(10, $buyer, 3) === 0, 'native sharing direction is respected');
$conf->entity = 2; $scopes['productprice'] = '1,2';
check($isolation->get_price(10, $buyer, 3, $sharedDocument) === 0, 'shared document cannot inherit consultation entity sale-price scope');
$isolationDB->bestEntity = 1;
check(is_object($isolation->get_price(10, $buyer, 3, $sharedDocument)), 'shared document owner tariff preserved with price sharing enabled');
$scopes['productprice'] = '2';
check($isolation->get_price(10, $buyer, 3, $document) === 0, 'disabling sale-price sharing immediately isolates tariffs');

$scopes['productprice'] = '1,2'; Societe::$data[7] = 1; $buyer->id = 7;
Categorie::$customerCategories = array((object) array('id' => 77));
foreach ($targets as $target => $element) {
	$isolationDB->target = $target;
	$context = $element ? (object) array('element' => $element, 'entity' => 2, 'id' => 12) : null;
	check(is_object($isolation->get_price(10, $buyer, 3, $context)), 'native sale-price sharing applies targeted tariff '.$target);
}
$isolationDB->target = ''; $buyer->id = 0;
foreach (array('propal' => 'updatePropalLines', 'commande' => 'updateOrderLines', 'facture' => 'updateInvoiceLines',
	'facturerec' => 'updateInvoiceLines', 'contrat' => 'updateContractLines') as $element => $refresh) {
	$document = new CommercialDocument(); $document->element = $element; $document->lines = array(clone $line);
	$_POST = array('price_ht' => '350');
	invoke($isolationHooks, 'applyPriceToPostFromPriceList', 10, $buyer, 3, $document);
	check((float) $_POST['price_ht'] === 80.0, 'shared sale tariff applied at add/edit '.$element);
	invoke($isolationHooks, $refresh, $document, $buyer);
	check(count($document->calls) === 1, 'shared sale tariff applied at refresh '.$element);
}
check($conf->entity === 2, 'sale-price sharing never switches authenticated entity');
