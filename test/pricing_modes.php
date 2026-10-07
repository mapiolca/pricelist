<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Included by run.php: native getSellPrice/fetchAll/calcul_price_total, simulated DB/session.
$commercialLine = clone $line;
$modes = array('PRODUIT_MULTIPRICES', 'PRODUIT_CUSTOMER_PRICES', 'PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES', 'PRODUIT_CUSTOMER_PRICES_BY_QTY', 'PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES');
function saleMode($mode) {
	foreach ($GLOBALS['modes'] as $key) { $GLOBALS['settings'][$key] = $key === $mode ? 1 : 0; }
}
$settings['PRODUIT_MULTIPRICES_LIMIT'] = 2;
$settings['PRODUIT_MULTIPRICES_LABEL1'] = 'Standard';
$settings['PRODUIT_MULTIPRICES_LABEL2'] = 'Professionnel';
$user->allowed = array('product.read', 'produit.creer', 'societe.lire', 'service.read', 'service.creer');
$settings['MAIN_USE_ADVANCED_PERMS'] = 0;
Product::$data[10] = array('entity' => 1, 'type' => 0, 'cost_price' => 220, 'price' => 440, 'price_min' => 400,
	'multiprices' => array(1 => 440, 2 => 500), 'multiprices_min' => array(1 => 275, 2 => 400),
	'multiprices_ttc' => array(1 => 528, 2 => 600), 'multiprices_min_ttc' => array(1 => 330, 2 => 480),
	'multiprices_base_type' => array(1 => 'HT', 2 => 'HT'),
	'prices_by_qty' => array(0 => 1, 1 => 1, 2 => 1),
	'prices_by_qty_list' => array(0 => array(array('rowid' => 10, 'price_base_type' => 'HT', 'unitprice' => 420)),
		1 => array(array('rowid' => 11, 'price_base_type' => 'HT', 'unitprice' => 360)),
		2 => array(array('rowid' => 12, 'price_base_type' => 'TTC', 'unitprice' => 600))));
$buyer = new Societe($db); $buyer->price_level = 1;
$saleRule = (object) array('fk_product' => 10, 'price' => 350, 'tx_discount' => null);
$pl = new PriceList($db);
saleMode('');
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer)['minimum'] === 400.0, 'unique price retains global minimum');
saleMode('PRODUIT_MULTIPRICES');
check($saleRule->price < Product::$data[10]['price_min'], 'reproduction: old scalar check rejects 350 against 400');
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) === null && $pl->error === '', 'Standard 275 accepts 350');
$buyer->price_level = 2;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer)['minimum'] === 400.0, 'other level correctly rejects 350');
$warnings = $pl->getMinimumPriceWarningsForRow($saleRule);
check(count($warnings) === 1 && $warnings[0]['label'] === 'Professionnel', 'generic warning names only incompatible level');
$pl->product_id = 10; $pl->from_qty = 1; $pl->price = 350; $pl->tx_discount = null; $pl->cost_price_source = 'custom';
check($pl->create($user) > 0, 'generic rule can be saved despite an incompatible level');
$buyer->price_level = 1;
Product::$data[10]['multiprices_min'][1] = 0;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) === null && $pl->error === '', 'zero minimum is valid, no fallback to 400');
Product::$data[10]['multiprices_min'][1] = 275;
$buyer->price_level = 9;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) === null && $pl->error === 'PriceListMinimumPriceUnavailable', 'missing level is an error, not zero');
$buyer->price_level = 1;
$saleRule->price = null; $saleRule->tx_discount = 10;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('unit_price' => 300))['current'] === 270.0, 'discount checks actual manual price');
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('unit_price' => 350)) === null, 'valid manual price preserved');
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('unit_price' => 360, 'base_type' => 'TTC', 'tva_tx' => 20))['current'] === 270.0, 'native TTC conversion before discount');
$saleRule->price = 350; $saleRule->tx_discount = null;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('remise_percent' => 30))['current'] === 245.0, 'fixed price checks retained discount');
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('unit_price' => 0)) === null, 'fixed rule replaces the unit price');
$hookmanager->replacement = array('pu_ht' => 440, 'price_min' => 390);
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer)['minimum'] === 390.0, 'native price hook replacement honored');
$hookmanager->replacement = array();
foreach (array('PRODUIT_CUSTOMER_PRICES_BY_QTY', 'PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES') as $mode) {
	saleMode($mode);
	$context = array('pqp' => $mode === 'PRODUIT_CUSTOMER_PRICES_BY_QTY' ? 10 : 11);
	$saleRule->price = null; $saleRule->tx_discount = 10;
	$violation = $pl->getMinimumPriceViolationForRow($saleRule, $buyer, $context);
	check($mode === 'PRODUIT_CUSTOMER_PRICES_BY_QTY' ? $violation['current'] === 378.0 : $violation === null, 'native selected quantity base '.$mode);
	$pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('pqp' => 12));
	check($pl->error === 'PriceListMinimumPriceUnavailable', 'foreign level quantity row rejected '.$mode);
	$pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('pqp' => 999));
	check($pl->error !== '', 'unknown quantity row rejected '.$mode);
}
$buyer->price_level = 2;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('pqp' => 12)) === null, 'native TTC quantity base converted');
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) === null && $pl->error === '', 'quantity-level fallback uses the native level price');
$buyer->price_level = 1;
$saleRule->price = 350; $saleRule->tx_discount = null;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) === null, 'quantity-level minimum 275 instead of scalar 400');
$hookmanager->replacement = array('pu_ht' => 400, 'price_min' => 390);
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer)['minimum'] === 390.0, 'quantity-level native hook override preserved');
$hookmanager->replacement = array();
$day = (int) floor(dol_now() / 86400) * 86400;
$customerRow = (object) array_fill_keys(array('rowid','entity','datec','tms','fk_product','fk_soc','ref_customer','price','price_ttc','price_min','price_min_ttc','price_base_type','default_vat_code','tva_tx','recuperableonly','localtax1_tx','localtax2_tx','localtax1_type','localtax2_type','discount_percent','date_begin','date_end','fk_user','price_label','import_key','socname','prodref'), 0);
$customerRow->price = 430; $customerRow->price_min = 375; $customerRow->price_ttc = 516; $customerRow->price_min_ttc = 450;
$customerRow->price_base_type = 'HT'; $customerRow->date_begin = $day - 86400; $customerRow->tva_tx = 20;
foreach (array('PRODUIT_CUSTOMER_PRICES', 'PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES') as $mode) {
	saleMode($mode);
	if ($mode === 'PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES' && version_compare(DOL_VERSION, '21.0.0', '<')) {
		$pl->getMinimumPriceViolationForRow($saleRule, $buyer);
		check($pl->error !== '', 'combined mode unavailable on v20'); continue;
	}
	$db->customerPriceRows = array(clone $customerRow);
	check($pl->getMinimumPriceViolationForRow($saleRule, $buyer)['minimum'] === 375.0, 'active customer minimum wins '.$mode);
	if (version_compare(DOL_VERSION, '22.0.0', '>=')) {
	$db->customerPriceRows[0]->date_end = $day - 1;
	$violation = $pl->getMinimumPriceViolationForRow($saleRule, $buyer);
	check($mode === 'PRODUIT_CUSTOMER_PRICES' ? $violation['minimum'] === 400.0 : $violation === null, 'expired customer native fallback '.$mode);
	$db->customerPriceRows[0]->date_end = 0; $db->customerPriceRows[0]->date_begin = $day + 86400;
	$violation = $pl->getMinimumPriceViolationForRow($saleRule, $buyer);
	check($mode === 'PRODUIT_CUSTOMER_PRICES' ? $violation['minimum'] === 400.0 : $violation === null, 'future customer native fallback '.$mode);
	}
	$db->failCustomerPrices = true;
	$pl->getMinimumPriceViolationForRow($saleRule, $buyer);
	check($pl->error === 'PriceListMinimumPriceUnavailable', 'native customer SQL error is not fallback '.$mode);
	$db->failCustomerPrices = false;
	$db->customerReadCount = 0; $db->failCustomerReadNumber = 2;
	$pl->getMinimumPriceViolationForRow($saleRule, $buyer);
	check($pl->error === 'PriceListMinimumPriceUnavailable', 'error in native second customer read is not fallback '.$mode);
	$db->failCustomerReadNumber = 0; $db->currentError = '';
	check(count($pl->getMinimumPriceWarningsForRow($saleRule)) > 0, 'generic customer context warning '.$mode);
}
$db->customerPriceRows = array(); saleMode('PRODUIT_MULTIPRICES');
$saleRule->price = null; $saleRule->tx_discount = 10;
$_POST = array('price_ht' => '300', 'remise_percent' => '0');
Societe::$data[7] = array('entity' => 1, 'price_level' => 1);
$buyer->id = 7;
$source = (object) array('element' => 'propal', 'entity' => 2, 'id' => 0);
$line = (object) array('subprice' => 350, 'tva_tx' => 20, 'remise_percent' => 5);
$context = invoke($hooks, 'getSubmittedPriceContext', $line);
check($context['unit_price'] === '300' && $context['remise_percent'] === '0', 'submitted edit values take precedence, including zero discount');
$_POST = array('price_ht' => '0');
check(invoke($hooks, 'getSubmittedPriceContext', $line)['unit_price'] === '0', 'explicit zero unit price is not absence');
$_POST = array();
check(invoke($hooks, 'getSubmittedPriceContext', $line)['unit_price'] === 350, 'discount-only edit retains persisted unit price');
check(invoke($hooks, 'getLineMinimumPriceContext', $line)['unit_price'] === 350, 'mass refresh retains actual unit price');
$saleRule->price = 350; $saleRule->tx_discount = null;
$user->allowed = array('societe.lire');
$pl->getMinimumPriceViolationForRow($saleRule, $buyer);
check($pl->error !== '', 'product price read permission required');
$user->allowed = array('product.read');
$pl->getMinimumPriceViolationForRow($saleRule, $buyer);
check($pl->error !== '', 'customer read permission required');
$user->allowed[] = 'societe.lire';
$scopes['product'] = '2';
$pl->getMinimumPriceViolationForRow($saleRule, $buyer);
check($pl->error !== '', 'unshared product blocked in minimum check');
$scopes['product'] = '1,2';
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) === null && $pl->error === '', 'shared product readable in its owner entity');
$settings['MAIN_MAX_DECIMALS_UNIT'] = 2;
$saleRule->price = 274.9999;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) === null, 'unit minimum comparison uses native precision');
$settings['MAIN_MAX_DECIMALS_UNIT'] = 4;
check($pl->getMinimumPriceViolationForRow($saleRule, $buyer) !== null, 'unit minimum comparison follows changed native precision');

// Exercise the shared check through real module hook methods, not just the comparator.
$db->rulePrice = 350; $db->ruleCostSource = 'product';
$_POST = array('price_ht' => '440', 'remise_percent' => '0');
invoke($hooks, 'applyPriceToPostFromPriceList', 10, $buyer, 1, $source);
check((float) $_POST['price_ht'] === 350.0, 'Standard fixed price applied at addline');
$_POST = array('price_ht' => '440', 'remise_percent' => '30');
invoke($hooks, 'applyPriceToPostFromPriceList', 10, $buyer, 1, $source);
check($_POST['price_ht'] === '440', 'retained discount below minimum leaves native addline untouched');
$db->rulePrice = null; $db->ruleDiscount = 10;
$_POST = array('price_ht' => '300', 'remise_percent' => '0');
invoke($hooks, 'applyPriceToPostFromPriceList', 10, $buyer, 1, $source);
check($_POST['remise_percent'] === '0', 'invalid discount enrichment omitted before POST writes');
$_POST = array('price_ht' => '350', 'remise_percent' => '0');
invoke($hooks, 'applyPriceToPostFromPriceList', 10, $buyer, 1, $source, null, $commercialLine);
check($_POST['price_ht'] === '350' && (float) $_POST['remise_percent'] === 10.0, 'edit preserves manual price and applies only discount');
$commercialLine->subprice = 350;
$_POST = array('elprice' => '350', 'elremise_percent' => '0');
invoke($hooks, 'applyContractLinePriceToPostFromPriceList', 10, $buyer, 1, $source, null, $commercialLine);
check($_POST['elprice'] === '350' && (float) $_POST['elremise_percent'] === 10.0, 'contract edit field names preserve manual price');
$_POST = array();
foreach (array('commande' => 'updateOrderLines', 'propal' => 'updatePropalLines', 'facture' => 'updateInvoiceLines', 'facturerec' => 'updateInvoiceLines', 'contrat' => 'updateContractLines') as $element => $method) {
	$document = new CommercialDocument(); $document->element = $element;
	$document->lines = array(clone $commercialLine);
	invoke($hooks, $method, $document, $buyer);
	check(count($document->calls) === 1 && (float) $document->calls[0][$element === 'propal' ? 1 : 2] === 350.0, 'discount refresh preserves line price '.$element);
	$document->calls = array(); $document->lines[0]->subprice = 300;
	invoke($hooks, $method, $document, $buyer);
	check($document->calls === array(), 'discount below minimum prevents enrichment '.$element);
}

$pl->price = 350; $pl->tx_discount = null; $pl->socid = 7;
$user->allowed = array('product.read', 'produit.creer', 'societe.lire');
check($pl->create($user) > 0, 'specific customer Standard rule uses its minimum at save');
Societe::$data[7]['price_level'] = 2;
check($pl->create($user) < 0, 'specific customer incompatible level refused at save');
Societe::$data[7]['price_level'] = 1;
$saleRule->price = null; $saleRule->tx_discount = 10;
$pl->getMinimumPriceViolationForRow($saleRule, $buyer, array('unit_price' => null));
check($pl->error === 'PriceListMinimumPriceUnavailable', 'missing actual line price is not zero');
