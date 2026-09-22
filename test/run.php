<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Behavioural simulations, with real Dolibarr evaluators/monetary normalization.
// Usage: php test/run.php /path/to/dolibarr [20.0.0|HEAD]
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (error_reporting() & $severity) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	}
	return false;
});
$root = dirname(__DIR__);
$core = $argv[1] ?? dirname($root).'/dolibarr';
$ref = $argv[2] ?? '20.0.0';
$native = shell_exec('git -C '.escapeshellarg($core).' show '.escapeshellarg($ref.':htdocs/core/lib/functions.lib.php'));
if (!$native) {
	throw new RuntimeException('A Dolibarr source checkout with the requested ref is required.');
}
/** Extract a function verbatim in memory; no core files are copied or edited. */
function extractFunction($source, $name)
{
	$tokens = token_get_all($source);
	for ($i = 0; $i < count($tokens); $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
			continue;
		}
		$j = $i + 1;
		while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
		if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) { continue; }
		$body = ''; $depth = 0; $started = false;
		for (; $i < count($tokens); $i++) {
			$token = $tokens[$i];
			$body .= is_array($token) ? $token[1] : $token;
			if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) { $depth++; $started = true; }
			if ($token === '}') { $depth--; if ($started && $depth === 0) { return $body; } }
		}
	}
	throw new RuntimeException('Missing function '.$name);
}
foreach (array('dol_eval', 'verifCond', 'price2num', 'complete_head_from_modules') as $name) {
	eval(extractFunction($native, $name));
}
if (strpos($native, 'function dolBuildUrl(') !== false) { eval(extractFunction($native, 'dolBuildUrl')); }
if (strpos($native, 'function dol_eval_standard(') !== false) { eval(extractFunction($native, 'dol_eval_standard')); }
define('DOL_VERSION', $ref === '20.0.0' ? '20.0.0' : '24.0.1');
define('MAIN_DB_PREFIX', 'test_long_prefix_');
define('DOL_DOCUMENT_ROOT', $core.'/htdocs');
define('DOL_URL_ROOT', '');
class CommonObject {
	public $import_key; public $context = array();
	public static $triggers = array();
	public function call_trigger($code, $user) { self::$triggers[] = $code; return 1; }
}
class DolibarrModules {
	public function __set($key, $value) { $this->properties[$key] = $value; }
	public function __get($key) { return $this->properties[$key] ?? null; }
	public $properties = array();
	public $export_fields_array = array(), $export_entities_array = array(), $export_code = array(), $export_label = array(), $export_enabled = array(), $export_permission = array(), $export_sql_start = array(), $export_sql_end = array(), $export_sql_order = array();
	public $import_fields_array = array(), $import_entities_array = array(), $import_code = array(), $import_label = array(), $import_icon = array(), $import_tables_array = array(), $import_convertvalue_array = array(), $import_updatekeys_array = array();
}
class User {
	public $id = 1, $admin = 0, $socid = 0; public $allowed = array();
	public function __construct($db = null) {}
	public function hasRight(...$path) { return in_array(implode('.', $path), $this->allowed, true); }
	public function fetch($id, $ref = '') { $this->id = $id ?: 1; return 1; }
}
class Product {
	public $id, $entity, $type, $cost_price, $price_min = 0, $price = 100;
	public static $data = array();
	public function __construct($db) {}
	public function fetch($id, $ref = '') {
		if ($ref === 'SHARED') { $id = 10; }
		if (!isset(self::$data[$id])) { return 0; }
		$this->id = $id;
		foreach (self::$data[$id] as $key => $value) { $this->$key = $value; }
		return 1;
	}
}
class Societe { public $id = 0, $entity = 1; public function __construct($db) {} public function fetch($id, $ref = '') { return 0; } }
class Categorie { public function __construct($db) {} }
class Translate {
	public function load($s) {} public function loadLangs($s) {}
	public function trans($s, ...$args) { return $s.($args ? ':'.implode('|', $args) : ''); }
	public function transnoentitiesnoconv($s) { return array('SeparatorDecimal' => '.', 'SeparatorThousand' => ',')[$s] ?? $s; }
}
$settings = array('DYNAMICPRICES_COST_ENABLE' => 1, 'MAIN_MAX_DECIMALS_UNIT' => 4, 'MAIN_MAX_DECIMALS_TOT' => 2);
$modules = array('pricelist' => true, 'dynamicsprices' => true);
$scopes = array('product' => '1,2', 'propal' => '1,2', 'societe' => '1', 'categorie' => '1');
$conf = (object) array('entity' => 2, 'global' => (object) $settings);
$user = new User();
$user->allowed = array('product.read', 'produit.creer', 'service.read', 'service.creer', 'dynamicsprices.cost.read', 'import.run');
$langs = new Translate();
$messages = array();
function getDolGlobalInt($key, $default = 0) { return (int) ($GLOBALS['settings'][$key] ?? $default); }
function getDolGlobalString($key, $default = '') { return (string) ($GLOBALS['settings'][$key] ?? $default); }
function isModEnabled($name) { return $GLOBALS['modules'][$name] ?? false; }
function getEntity($element) { return $GLOBALS['scopes'][$element] ?? (string) $GLOBALS['conf']->entity; }
function checkUserAccessToObject($user, $features, $object, $table) { return empty($GLOBALS['denyObject']); }
function dol_include_once($file) { return true; }
function GETPOSTISSET($name) { return isset($_POST[$name]); }
function GETPOST($name, $type = '') { return $_POST[$name] ?? ''; }
class TestHooks { public $resArray = array(); public function executeHooks($name, $parameters, $object = null) { return 0; } }
$hookmanager = new TestHooks();
function dol_strlen($value) { return strlen((string) $value); }
function dol_syslog($message, $level = 0) {}
function dol_now() { return 1234567890; }
function price($amount) { return (string) $amount; }
function dol_buildpath($path, $mode = 0) { return $path; }
function setEventMessages($message, $errors = null, $level = '') { $GLOBALS['messages'][] = $message; }
function setEventMessage($message, $level = '') { $GLOBALS['messages'][] = $message; }
$providerMode = $argv[3] ?? '';
if ($providerMode === 'old') {
	class DynamicPricesCostService { public function getDynamicCostPrice($product, $entity = 0) {} }
} elseif ($providerMode !== 'missing') {
class DynamicPricesCostService {
	public static $values = array(1 => 12.34567, 2 => 23.45678), $calls = array(), $throw = false;
	public function __construct($db) {}
	public function getDynamicCostPrice($product, $entity = 0, $options = array()) {
		self::$calls[] = array($product, $entity, $options);
		if (self::$throw) { throw new RuntimeException('Provider failure'); }
		return self::$values[$entity] ?? null;
	}
}
}

class Result { public $rows; public function __construct($rows = array()) { $this->rows = $rows; } }
class TestDB {
	public $queries = array(), $records = array(), $costColumns = array(), $migrations = array(), $failMigration = false, $bestEntity = 1;
	public function escape($s) { return str_replace("'", "''", (string) $s); }
	public function sanitize($s) { return $s; }
	public function idate($s) { return '2026-09-22 00:00:00'; }
	public function begin() {} public function commit() {} public function rollback() {}
	public function last_insert_id($table) { return 50; }
	public function lasterror() { return 'simulated SQL failure'; }
	public function num_rows($r) { return count($r->rows); }
	public function fetch_object($r) { return array_shift($r->rows); }
	public function free($r) {}
	public function query($sql) {
		$this->queries[] = $sql;
		if (strpos($sql, 'SHOW TABLES') === 0) { return new Result(array((object) array('table' => 'exists'))); }
		if (preg_match('/SHOW COLUMNS FROM (\w+)/', $sql, $m)) { return new Result(!empty($this->costColumns[$m[1]]) ? array((object) array('Field' => 'cost_price_source')) : array()); }
		if (preg_match('/ALTER TABLE (\w+) ADD COLUMN cost_price_source/', $sql, $m)) { $this->costColumns[$m[1]] = true; return true; }
		if (preg_match('/UPDATE (\w+) SET cost_price_source = CASE/', $sql, $m)) {
			if ($this->failMigration) { return false; }
			foreach ($this->migrations[$m[1]] as &$row) {
				if (!isset($row['cost_price_source'])) { $row['cost_price_source'] = $row['use_product_cost_price'] === 1 ? 'product' : 'custom'; }
			}
			return true;
		}
		if (strpos($sql, 'SELECT name, value') === 0) { return new Result(array((object) array('name' => 'MAIN_MODULE_DYNAMICSPRICES', 'value' => 1))); }
		if (strpos($sql, 'SELECT rowid, fk_product') === 0) {
			return new Result(strpos($sql, 't.entity = '.$this->bestEntity) !== false ? array((object) array('rowid' => 50, 'fk_product' => 10, 'price' => 80, 'tx_discount' => null, 'cost_price' => null, 'cost_price_source' => 'dynamicprices', 'use_product_cost_price' => 0, 'from_qty' => 1)) : array());
		}
		if (strpos($sql, 'SELECT') === 0 && strpos($sql, 't.rowid = ') !== false) {
			return new Result(array_values($this->records));
		}
		if (strpos($sql, 'SELECT') === 0) { return new Result(); }
		return true;
	}
}
/** Load module declarations with Dolibarr entry-point imports supplied above. */
function loadModule($file) {
	$source = file_get_contents($file);
	$source = preg_replace('/^(?:require_once|include_once|dol_include_once)\b[^\n]*\n/m', '', $source);
	eval(substr($source, 5));
}
foreach (array('lib/pricelist.lib.php', 'class/pricelistcompatibility.class.php', 'class/pricelist.class.php', 'core/modules/modPriceList.class.php', 'class/actions_pricelist.class.php') as $file) { loadModule($root.'/'.$file); }
$importSource = file_get_contents($root.'/class/pricelistimport.class.php');
$importSource = preg_replace('/^require_once\b[^\n]*\n/m', '', $importSource);
eval(substr($importSource, 5));
if ($providerMode !== '') {
	$status = PriceListCompatibility::getDynamicPricesAvailability();
	if ($status['available'] || $status['reason'] !== 'PriceListDynamicPricesIncompatible') { throw new RuntimeException('Provider capability was not rejected'); }
	echo 'OK: unavailable provider '.$providerMode.'; native '.$ref."\n";
	exit(0);
}
$tests = 0;
function check($condition, $message) { global $tests; $tests++; if (!$condition) { throw new RuntimeException('FAIL: '.$message); } }
function invoke($object, $method, ...$args) { $r = new ReflectionMethod($object, $method); return $r->invoke($object, ...$args); }
$db = new TestDB();
Product::$data[10] = array('entity' => 1, 'type' => 0, 'cost_price' => 9.87654);
$pl = new PriceList($db);
$row = (object) array('fk_product' => 10, 'cost_price_source' => 'custom', 'cost_price' => 0);
check($pl->getEffectiveCostPriceForRow($row) === 0.0, 'custom zero');
$row->cost_price = '12.34567';
check($pl->getEffectiveCostPriceForRow($row) === 12.3457, 'native MU precision');
$settings['MAIN_MAX_DECIMALS_UNIT'] = 2; $conf->global->MAIN_MAX_DECIMALS_UNIT = 2;
check($pl->getEffectiveCostPriceForRow($row) === 12.35, 'native precision setting changed');
$settings['MAIN_MAX_DECIMALS_UNIT'] = 4; $conf->global->MAIN_MAX_DECIMALS_UNIT = 4;
$row->cost_price_source = 'product';
check($pl->getEffectiveCostPriceForRow($row) === 9.8765, 'native product source');
$row->cost_price_source = 'dynamicprices';
$settings['DYNAMICPRICES_COST_USE_FOR_SALES'] = 0;
check($pl->getEffectiveCostPriceForRow($row) === 23.4568, 'consultation entity, independent of sales automation');
$source = (object) array('element' => 'propal', 'entity' => 2, 'id' => 0);
$priceRow = $pl->get_price(10, new Societe($db), 3, $source);
check(is_object($priceRow) && $priceRow->cost_context_entity === 2, 'fallback rule owner must not replace commercial entity');
check($pl->getEffectiveCostPriceForRow($priceRow) === 23.4568, 'external one-argument resolver retains document entity');
$source->entity = 1;
$priceRow = $pl->get_price(10, new Societe($db), 3, $source);
check($pl->getEffectiveCostPriceForRow($priceRow) === 12.3457, 'shared document uses its entity and configuration');
$row->cost_context_entity = 99;
check($pl->getEffectiveCostPriceForRow($row) === null, 'foreign entity blocked'); unset($row->cost_context_entity);
DynamicPricesCostService::$values[2] = 0;
check($pl->getEffectiveCostPriceForRow($row) === 0.0, 'dynamic zero valid');
foreach (array(null, NAN, INF, 'invalid') as $bad) {
	DynamicPricesCostService::$values[2] = $bad;
	check($pl->getEffectiveCostPriceForRow($row) === null && $pl->cost_price_warning !== '', 'invalid dynamic cost warns');
}
DynamicPricesCostService::$throw = true;
check($pl->getEffectiveCostPriceForRow($row) === null, 'provider exception controlled');
DynamicPricesCostService::$throw = false;
$modules['dynamicsprices'] = false;
check($pl->getEffectiveCostPriceForRow($row) === null && $pl->cost_price_warning === 'PriceListDynamicPricesDisabled', 'disabled provider');
$modules['dynamicsprices'] = true; $settings['DYNAMICPRICES_COST_ENABLE'] = 0;
check($pl->getEffectiveCostPriceForRow($row) === null, 'disabled costs'); $settings['DYNAMICPRICES_COST_ENABLE'] = 1;
$allowed = $user->allowed; $user->allowed = array('product.read'); $user->admin = 1;
check($pl->getEffectiveCostPriceForRow($row) === null && $pl->cost_price_warning === 'PriceListDynamicCostForbidden', 'admin cannot bypass cost right');
$user->allowed = $allowed; $user->admin = 0;
$denyObject = true;
check($pl->getEffectiveCostPriceForRow($row) === null, 'product business access'); $denyObject = false;
$scopes['product'] = '2';
check($pl->getEffectiveCostPriceForRow($row) === null, 'unshared product blocked'); $scopes['product'] = '1,2';
foreach (DynamicPricesCostService::$calls as $call) { check($call[2] === array('require_success' => true), 'only valid current costs requested'); }
// Both old input and an explicit new value: the canonical value always wins.
check(PriceList::getCostPriceSourceForRow((object) array('use_product_cost_price' => 1)) === 'product', 'legacy product conversion');
check(PriceList::getCostPriceSourceForRow((object) array('cost_price_source' => 'dynamicprices', 'use_product_cost_price' => 1)) === 'dynamicprices', 'single authority');
$pl->product_id = 10; $pl->from_qty = 1; $pl->cost_price_source = 'dynamicprices'; $pl->cost_price = 999;
check($pl->create($user) === 50 && $pl->cost_price === null, 'create does not persist live provider amount');
check(count(array_filter($db->queries, static function ($sql) { return strpos($sql, 'pricelist_log') !== false && strpos($sql, "'dynamicprices'") !== false; })) === 1, 'creation history carries source');
$copy = clone $pl; $copy->cost_price_source = 'product';
check(invoke($pl, 'hasChangedComparedTo', $copy), 'source-only edit detected');
$clone = clone $pl; $clone->id = null;
check($clone->create($user) === 50 && $clone->cost_price_source === 'dynamicprices', 'cloning retains source');
$hooks = new ActionsPriceList($db);
$row->price = 75; $row->tx_discount = null;
DynamicPricesCostService::$values[2] = null;
$_POST = array('buying_price' => 7, 'pa_ht' => 7);
invoke($hooks, 'applyPriceToPost', $row, 10);
check($_POST['buying_price'] === 7 && $_POST['price_ht'] === '75', 'add: missing cost preserves inputs and applies sale price');
$_POST = array(); invoke($hooks, 'applyPriceToPost', $row, 10, 42);
check((float) $_POST['buying_price'] === 42.0, 'edit: missing cost preserves existing line cost');
$_POST = array(); invoke($hooks, 'applyPriceToContractLinePost', $row, 10, 42);
check((float) $_POST['buying_price'] === 42.0, 'contract edit preserves cost');
$values = invoke($hooks, 'getLinePriceValues', $row, (object) array('subprice' => 100, 'pa_ht' => 42), 10);
check($values['pa_ht'] === 42 && $values['pu'] === '75', 'mass refresh preserves cost');
check(count($hooks->warnings) === 1, 'warning deduplicated');
DynamicPricesCostService::$values[2] = 0;
$_POST = array('buying_price' => 7); invoke($hooks, 'applyPriceToPost', $row, 10);
check((float) $_POST['buying_price'] === 0.0, 'zero applied to commercial line');
// Descriptor condition is executed by the actual native evaluator, including its security checks.
$descriptor = new modPriceList($db);
$condition = explode(':', $descriptor->tabs[0])[4];
foreach (array(0, 1) as $type) {
	$object = (object) array('type' => $type);
	foreach (array(0, 1) as $advanced) {
		$settings['MAIN_USE_ADVANCED_PERMS'] = $advanced;
		$right = $type ? ($advanced ? 'service.service_advance.read_prices' : 'service.read') : ($advanced ? 'product.product_advance.read_prices' : 'product.read');
		foreach (array(0, 1) as $admin) {
			$user->admin = $admin; $user->allowed = array($right);
			check(verifCond($condition, '2') === true, 'native tab authorized type '.$type.' advanced '.$advanced.' admin '.$admin);
			$user->allowed = array($type ? 'product.read' : 'service.read');
			check(verifCond($condition, '2') === false, 'native tab denied without matching right');
		}
	}
}
$settings['MAIN_USE_ADVANCED_PERMS'] = 0; $user->allowed = $allowed; $user->admin = 0;
check(count($descriptor->config_page_url) === 1 && $descriptor->version === '2.3.0', 'single settings entry and version');
check(array_column(pricelistAdminPrepareHead(), 2) === array('settings', 'compatibility', 'about'), 'admin tab ordering');
// Replaying a partially completed migration must preserve canonical rows and all old amounts.
foreach (array('pricelist', 'pricelist_log') as $table) {
	$db->migrations[MAIN_DB_PREFIX.$table] = array(array('use_product_cost_price' => 1, 'cost_price' => 9), array('use_product_cost_price' => 0, 'cost_price' => 0), array('use_product_cost_price' => 0, 'cost_price_source' => 'dynamicprices', 'cost_price' => null));
}
check(invoke($descriptor, 'migrateCostPriceSources') === 1, 'migration succeeds');
$before = $db->migrations;
check(invoke($descriptor, 'migrateCostPriceSources') === 1 && $db->migrations === $before, 'migration replay is stable');
check($before[MAIN_DB_PREFIX.'pricelist_log'][0]['cost_price_source'] === 'product' && $before[MAIN_DB_PREFIX.'pricelist_log'][0]['cost_price'] === 9, 'history source migration retains recorded amounts');
$db->failMigration = true;
check(invoke($descriptor, 'migrateCostPriceSources') === -1, 'failed migration propagated'); $db->failMigration = false;
$importer = new PriceListImport($db);
$triggers = count(CommonObject::$triggers);
check($importer->importRow(array('fk_product' => 'ref:SHARED', 'from_qty' => '2', 'use_product_cost_price' => '1'), $user, false, '20260922000000', true) === 50, 'legacy native import mapping');
check(count(CommonObject::$triggers) === $triggers, 'simulation suppresses external triggers');
check($importer->importRow(array('fk_product' => '10', 'from_qty' => '2', 'cost_price_source' => 'dynamicprices'), $user, false, '20260922000000', false) === 50, 'dynamic import');
check($importer->importRow(array('fk_product' => '10', 'entity' => '99', 'from_qty' => '1', 'cost_price_source' => 'product'), $user, false, '', false) < 0, 'import entity rejected');
check($importer->importRow(array('fk_product' => '10', 'from_qty' => '1', 'cost_price_source' => 'unknown'), $user, false, '', false) < 0, 'invalid imported source rejected');
$function = extractFunction(file_get_contents($root.'/includes/view.inc.php'), 'pricelist_get_history_value'); eval($function);
$history = pricelist_get_history_value((object) array('price' => null, 'tx_discount' => null, 'cost_price' => 999, 'cost_price_source' => 'dynamicprices'), $langs, $db);
check($history['value'] === null && strpos($history['label'], '999') === false, 'history never shows live or irrelevant cost as a snapshot');
// Load the actual form-action include with a read-only action to detect entry-point regressions.
$object = (object) array('element' => 'product', 'id' => 10, 'type' => 0);
$action = ''; $lineid = 0; $productid = 10; $pricelist = $pl;
require $root.'/includes/actions_addupdatedelete.inc.php';
check(pricelist_get_product_type_for_rights($db, 10) === 0, 'form include works for read-only navigation');
$formRow = new PriceList($db);
pricelist_apply_request_to_line($formRow, $object, '', 10, 0, 0, 0, 0, 0, 0, 1, 80, null, 999, 'dynamicprices');
check($formRow->cost_price === null && $formRow->cost_price_source === 'dynamicprices', 'server ignores posted amount for live source');
check($formRow->matchesContext($object) && !$formRow->matchesContext((object) array('element' => 'product', 'id' => 11)), 'forged row parent is rejected');
$conf->modules_parts = array('tabs' => array('product' => array($descriptor->tabs[0])));
foreach (array(0, 1) as $type) {
	$object->type = $type;
	foreach (array('productcard', 'prices', 'purchaseprices', 'notes', 'documents', 'agenda', 'statistics') as $context) {
		$head = array(array('/card', 'Card', 'card'), array('/note', 'Notes', 'note'), array('/document', 'Files', 'document'), array('/agenda', 'Agenda', 'agenda'));
		$h = count($head);
		complete_head_from_modules($conf, $langs, $object, $head, $h, 'product', 'add', 'core');
		complete_head_from_modules($conf, $langs, $object, $head, $h, 'product', 'add', 'external');
		check(count(array_filter($head, static function ($tab) { return $tab[2] === 'pricelist'; })) === 1 && $head[4][0] === '/pricelist/product.php?id=10', 'single tab after transverse tabs in '.$context.' type '.$type);
	}
}
$user->socid = 20;
check(!verifCond($condition, '2'), 'external product access not advertised'); $user->socid = 0;
// Full update and import update preserve the canonical source and history.
$stored = (object) array('rowid' => 50, 'entity' => 2, 'fk_product' => 10, 'fk_soc' => null, 'fk_cat' => null, 'fk_cat_propal' => null, 'fk_cat_order' => null, 'fk_cat_invoice' => null, 'fk_cat_contract' => null, 'from_qty' => 1, 'price' => 80, 'tx_discount' => null, 'cost_price' => null, 'cost_price_source' => 'dynamicprices', 'use_product_cost_price' => 0, 'fk_user_creation' => 1);
$db->records = array($stored);
$edit = new PriceList($db); check($edit->fetch(50) === 1, 'fetch canonical source');
$edit->cost_price_source = 'custom'; $edit->cost_price = 0;
check($edit->update($user) === 1 && $edit->cost_price === '0', 'edit live to custom zero');
check($importer->importRow(array('rowid' => '50', 'price' => '90'), $user, true, '20260922000000', false) === 50 && $importer->updated, 'import partial update retains omitted source');
$edit->cost_price_source = 'invalid'; check($edit->update($user) < 0, 'invalid source edit rejected');
$user->allowed = array('product.read'); $user->admin = 1;
$edit->cost_price_source = 'custom'; check($edit->update($user) < 0, 'admin without write right denied');
$user->allowed = $allowed; $user->admin = 0;
class CommercialDocument {
	public $element, $entity = 2, $id = 0, $lines = array(), $calls = array();
	public function updateline(...$args) { $this->calls[] = $args; return 1; }
}
$line = (object) array('id' => 10, 'fk_product' => 10, 'qty' => 2, 'desc' => 'Line', 'description' => 'Line', 'subprice' => 100, 'pa_ht' => 42, 'buy_price_ht' => 43, 'remise_percent' => 0, 'tva_tx' => 20, 'localtax1_tx' => 0, 'localtax2_tx' => 0, 'info_bits' => 0, 'date_start' => '', 'date_end' => '', 'product_type' => 0, 'fk_parent_line' => 0, 'fk_fournprice' => 0, 'label' => '', 'special_code' => 0, 'array_options' => array('options_test' => 'keep'), 'fk_unit' => 0, 'multicurrency_subprice' => 100, 'situation_percent' => 100, 'rang' => 1, 'date_start_fill' => 1, 'date_end_fill' => 1, 'fk_product_fournisseur_price' => 0);
foreach (array('commande' => array('updateOrderLines', 16), 'propal' => array('updatePropalLines', 14), 'facture' => array('updateInvoiceLines', 16), 'facturerec' => array('updateInvoiceLines', 23), 'contrat' => array('updateContractLines', 15)) as $element => $definition) {
	$document = new CommercialDocument(); $document->element = $element;
	$document->lines = array(clone $line);
	if ($element === 'facturerec') { unset($document->lines[0]->pa_ht); }
	DynamicPricesCostService::$values[2] = 23.45678;
	invoke($hooks, $definition[0], $document, new Societe($db));
	check(count($document->calls) === 1 && (float) $document->calls[0][$definition[1]] === 23.4568, 'cost applied at native parameter for '.$element);
	$document->calls = array(); DynamicPricesCostService::$values[2] = null;
	invoke($hooks, $definition[0], $document, new Societe($db));
	check((float) $document->calls[0][$definition[1]] === ($element === 'facturerec' ? 43.0 : 42.0), 'unavailable cost preserved for '.$element);
}
check(isset($descriptor->export_fields_array[1]['pl.cost_price_source']) && !isset($descriptor->export_fields_array[1]['pl.use_product_cost_price']), 'export exposes canonical source');
check(strpos($descriptor->export_sql_end[1], 'pl.entity = 2') !== false, 'export uses consultation entity');
check(strpos($descriptor->export_sql_end[1], 'p.entity IN (1,2)') !== false, 'export restricts shared product scope');
check(isset($descriptor->import_fields_array[1]['p.cost_price_source'], $descriptor->import_fields_array[1]['p.use_product_cost_price']), 'native import preserves legacy mapping and accepts canonical source');
echo 'OK: '.$tests.' assertions; native functions '.$ref.'; PHP '.PHP_VERSION."; simulated DB/provider, no live instance.\n";
