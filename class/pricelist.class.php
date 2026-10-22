<?php
/* Copyright (C) 2024 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2014	   Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2016-2021 Garcia MICHEL <garcia@soamichel.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/../lib/pricelist.lib.php';
require_once __DIR__.'/pricelistcompatibility.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

/**
 * Manage price lists.
 */
class PriceList extends CommonObject
{
	public $db;
	public $error;
	public $errors = array();
	public $element = 'pricelist';
	public $TRIGGER_PREFIX = 'PRICELIST';
	public $table_element = 'pricelist';
	public $ismultientitymanaged = 1;

	public $id;
	public $entity;
	public $product_id;
	public $socid;
	public $catid;
	public $catid_propal;
	public $catid_order;
	public $catid_invoice;
	public $catid_contract;
	public $from_qty;
	public $price;
	public $tx_discount;
	public $cost_price;
	/** @var int|null Legacy input only; derived from cost_price_source on persistence. */
	public $use_product_cost_price;
	/** @var string|null Null only for legacy input before normalization. */
	public $cost_price_source;
	/** @var int|null Runtime cost entity; never persisted. */
	public $cost_context_entity;
	/** @var string|null Document element associated with cost_context_entity. */
	public $cost_context_element;
	/** @var string Translation key for an unavailable cost. */
	public $cost_price_warning = '';
	public $user_creation_id;
	public $oldcopy;

	/**
	 * Constructor.
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		return 1;
	}

	/**
	 * Create object into database.
	 *
	 * @param User $user User that creates
	 * @param int $notrigger Disable triggers during native import simulation
	 * @return int <0 if KO, id of created object if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf, $langs;

		if ($this->validatePriceListValues($langs, $user) < 0) {
			return -1;
		}

		$this->entity = (int) $conf->entity;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "entity,";
		$sql .= "fk_product,";
		$sql .= "fk_soc,";
		$sql .= "fk_cat,";
		$sql .= "fk_cat_propal,";
		$sql .= "fk_cat_order,";
		$sql .= "fk_cat_invoice,";
		$sql .= "fk_cat_contract,";
		$sql .= "from_qty,";
		$sql .= "price,";
		$sql .= "tx_discount,";
		$sql .= "cost_price,";
		$sql .= "cost_price_source,use_product_cost_price,";
		$sql .= "fk_user_creation, import_key";
		$sql .= ") VALUES (";
		$sql .= " ".((int) $this->entity).",";
		$sql .= " ".((int) $this->product_id).",";
		$sql .= " ".$this->formatNullableInt($this->socid).",";
		$sql .= " ".$this->formatNullableInt($this->catid).",";
		$sql .= " ".$this->formatNullableInt($this->catid_propal).",";
		$sql .= " ".$this->formatNullableInt($this->catid_order).",";
		$sql .= " ".$this->formatNullableInt($this->catid_invoice).",";
		$sql .= " ".$this->formatNullableInt($this->catid_contract).",";
		$sql .= " ".price2num($this->from_qty).",";
		$sql .= " ".$this->formatNullablePrice($this->price).",";
		$sql .= " ".$this->formatNullablePrice($this->tx_discount).",";
		$sql .= " ".$this->formatNullablePrice($this->cost_price).",";
		$sql .= " '".$this->db->escape($this->cost_price_source)."',".((int) $this->use_product_cost_price).",";
		$sql .= " ".((int) ($this->user_creation_id ?: $user->id)).", ".(isset($this->import_key) ? "'".$this->db->escape($this->import_key)."'" : "null");
		$sql .= ")";

		$this->db->begin();

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();
			$this->error = "Error ".$this->db->lasterror();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

		$res = $notrigger ? 0 : $this->call_trigger('PRICELIST_CREATE', $user);
		if ($res < 0) {
			$this->db->rollback();
			return -1;
		}

		$res = $this->insertHistory($user, 'CREATE');
		if ($res < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load object in memory from the database.
	 *
	 * @param int $id Object id
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetch($id)
	{
		global $conf;
		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.entity,";
		$sql .= " t.fk_product,";
		$sql .= " t.fk_soc,";
		$sql .= " t.fk_cat,";
		$sql .= " t.fk_cat_propal,";
		$sql .= " t.fk_cat_order,";
		$sql .= " t.fk_cat_invoice,";
		$sql .= " t.fk_cat_contract,";
		$sql .= " t.from_qty,";
		$sql .= " t.price,";
		$sql .= " t.tx_discount,";
		$sql .= " t.cost_price,";
		$sql .= " t.cost_price_source, t.use_product_cost_price,";
		$sql .= " t.fk_user_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t WHERE t.rowid = ".((int) $id);
		$sql .= " AND t.entity = ".((int) $conf->entity);

		dol_syslog(get_class($this)."::fetch");
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);
				$this->setVarsFromFetchObject($obj);
				$this->db->free($resql);
				return 1;
			}

			$this->db->free($resql);
			return 0;
		}

		$this->error = "Error ".$this->db->lasterror();
		return -1;
	}

	/**
	 * Search list objects into database.
	 *
	 * @param int    $product_id   Product id
	 * @param int    $socid        Thirdparty id
	 * @param int    $catid        Category id
	 * @param string $categorytype Category scope: customer, propal, contract
	 * @param int    $entity       Entity id
	 * @return ?array<int,PriceList> Null if error else array
	 */
	public function search($product_id = 0, $socid = 0, $catid = 0, $categorytype = 'customer', $entity = 0)
	{
		global $conf, $user;
		$entity = $this->resolveEntity(null, $entity);
		if ($entity !== (int) $conf->entity) {
			return array();
		}
		$categoryfield = $this->getCategoryFieldForType($categorytype);

		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.entity,";
		$sql .= " t.fk_product,";
		$sql .= " t.fk_soc,";
		$sql .= " t.fk_cat,";
		$sql .= " t.fk_cat_propal,";
		$sql .= " t.fk_cat_order,";
		$sql .= " t.fk_cat_invoice,";
		$sql .= " t.fk_cat_contract,";
		$sql .= " t.from_qty,";
		$sql .= " t.price,";
		$sql .= " t.tx_discount,";
		$sql .= " t.cost_price,";
		$sql .= " t.cost_price_source, t.use_product_cost_price,";
		$sql .= " t.fk_user_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";

		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = t.fk_product";
		$readProduct = getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') > 0 ? $user->hasRight('product', 'product_advance', 'read_prices') : $user->hasRight('product', 'read');
		$readService = getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') > 0 ? $user->hasRight('service', 'service_advance', 'read_prices') : $user->hasRight('service', 'read');
		$where = array("t.entity = ".((int) $entity), "p.entity IN (".$this->db->sanitize(getEntity('product')).")", "((p.fk_product_type = 0 AND ".((int) $readProduct)." = 1) OR (p.fk_product_type = 1 AND ".((int) $readService)." = 1))");
		if (!$user->hasRight('societe', 'lire')) {
			$where[] = "t.fk_soc IS NULL";
		} else {
			$customerScope = "SELECT 1 FROM ".MAIN_DB_PREFIX."societe AS s WHERE s.rowid = t.fk_soc AND s.entity IN (".$this->db->sanitize(getEntity('societe')).")";
			if (!empty($user->socid)) {
				$customerScope .= " AND s.rowid = ".((int) $user->socid);
			} elseif (!$user->hasRight('societe', 'client', 'voir')) {
				$customerScope .= " AND EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."societe_commerciaux AS sc WHERE sc.fk_soc = s.rowid AND sc.fk_user = ".((int) $user->id).")";
			}
			$where[] = "(t.fk_soc IS NULL OR EXISTS (".$customerScope."))";
		}
		foreach (array('fk_cat', 'fk_cat_propal', 'fk_cat_order', 'fk_cat_invoice', 'fk_cat_contract') as $categoryField) {
			$where[] = !$user->hasRight('categorie', 'lire') ? "t.".$categoryField." IS NULL"
				: "(t.".$categoryField." IS NULL OR EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."categorie AS c WHERE c.rowid = t.".$categoryField." AND c.entity IN (".$this->db->sanitize(getEntity('categorie')).")))";
		}
		if ($product_id) {
			$where[] = "t.fk_product = ".((int) $product_id);
		}
		if ($socid) {
			$where[] = "t.fk_soc = ".((int) $socid);
		}
		if ($catid) {
			$where[] = "t.".$categoryfield." = ".((int) $catid);
		}
		if ($where) {
			$sql .= " WHERE ".implode(" AND ", $where);
		}
		$sql .= " ORDER BY t.fk_product, t.fk_soc, t.fk_cat, t.fk_cat_propal, t.fk_cat_order, t.fk_cat_invoice, t.fk_cat_contract, t.from_qty";

		dol_syslog(get_class($this)."::search");
		$resql = $this->db->query($sql);
		if ($resql) {
			$list = array();
			while ($obj = $this->db->fetch_object($resql)) {
				$object = new PriceList($this->db);
				$object->setVarsFromFetchObject($obj);
				$list[] = $object;
			}
			$this->db->free($resql);
			return $list;
		}

		$this->error = "Error ".$this->db->lasterror();
		return null;
	}

	/**
	 * Update object into database.
	 *
	 * @param User $user User that modifies
	 * @param int $notrigger Disable triggers during native import simulation
	 * @return int <0 if KO, >0 if OK
	 */
	public function update($user, $notrigger = 0)
	{
		global $langs;

		if ($this->validatePriceListValues($langs, $user) < 0) {
			return -1;
		}

		$error = 0;
		$oldcopy = new PriceList($this->db);
		$oldcopyloaded = false;
		if (!empty($this->id) && $oldcopy->fetch((int) $this->id) > 0) {
			$oldcopyloaded = true;
			$this->oldcopy = clone $oldcopy;
		}

		if (!$oldcopyloaded) {
			$this->error = $langs->trans('ErrorRecordNotFound');
			return -1;
		}
		$oldProduct = new Product($this->db);
		if ($oldProduct->fetch((int) $oldcopy->product_id) <= 0
			|| !((int) $oldProduct->type === 1 ? $user->hasRight('service', 'creer') : $user->hasRight('produit', 'creer'))) {
			$this->error = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		$this->entity = $oldcopy->entity;

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " fk_product=".((int) $this->product_id).",";
		$sql .= " fk_soc=".$this->formatNullableInt($this->socid).",";
		$sql .= " fk_cat=".$this->formatNullableInt($this->catid).",";
		$sql .= " fk_cat_propal=".$this->formatNullableInt($this->catid_propal).",";
		$sql .= " fk_cat_order=".$this->formatNullableInt($this->catid_order).",";
		$sql .= " fk_cat_invoice=".$this->formatNullableInt($this->catid_invoice).",";
		$sql .= " fk_cat_contract=".$this->formatNullableInt($this->catid_contract).",";
		$sql .= " from_qty=".price2num($this->from_qty).",";
		$sql .= " price=".$this->formatNullablePrice($this->price).",";
		$sql .= " tx_discount=".$this->formatNullablePrice($this->tx_discount).",";
		$sql .= " cost_price=".$this->formatNullablePrice($this->cost_price).",";
		$sql .= " cost_price_source='".$this->db->escape($this->cost_price_source)."', use_product_cost_price=".((int) $this->use_product_cost_price);
		if (isset($this->import_key) && $this->import_key !== '') {
			$sql .= ", import_key='".$this->db->escape($this->import_key)."'";
		}
		$sql .= " WHERE rowid=".((int) $this->id);
		if (!empty($this->entity)) {
			$sql .= " AND entity=".((int) $this->entity);
		}

		$this->db->begin();

		dol_syslog(__METHOD__);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$result = $notrigger ? 0 : $this->call_trigger('PRICELIST_UPDATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error && (!$oldcopyloaded || $this->hasChangedComparedTo($oldcopy))) {
			$result = $this->insertHistory($user, 'UPDATE');
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__." ".$errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', '.$errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Delete object in database.
	 *
	 * @param User $user User that deletes
	 * @return int <0 if KO, >0 if OK
	 */
	public function delete($user)
	{
		global $conf, $langs;
		$product = new Product($this->db);
		if (!isModEnabled('pricelist') || (int) $this->entity !== (int) $conf->entity || $product->fetch((int) $this->product_id) <= 0
			|| !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)
			|| !((int) $product->type === 1 ? $user->hasRight('service', 'creer') : $user->hasRight('produit', 'creer'))) {
			$this->error = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		$error = 0;

		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE rowid=".((int) $this->id);
		if (!empty($this->entity)) {
			$sql .= " AND entity=".((int) $this->entity);
		}

		dol_syslog(__METHOD__);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$result = $this->call_trigger('PRICELIST_DELETE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__." ".$errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', '.$errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Search the price of a product for a customer, quantity and optional source object.
	 *
	 * Priority: source object category, exact customer, customer category, generic product price.
	 *
	 * @param int         $idproduct    Product id
	 * @param Societe     $soc          Customer object
	 * @param float|int   $qty          Quantity
	 * @param CommonObject|null $sourceObject Propal or contract object
	 * @param array{unit_price?:float|string|null,base_type?:string,tva_tx?:float|string,remise_percent?:float|string,pqp?:int} $priceContext Actual line price context
	 * @return int|stdClass 0 if not found, -1 on error, price row when found
	 */
	public function get_price($idproduct, $soc, $qty, $sourceObject = null, $priceContext = array())
	{
		global $user, $conf;
		$product = new Product($this->db);
		$res = $product->fetch((int) $idproduct);
		if ($res <= 0) {
			$this->error = 'Failed to fetch product';
			return -1;
		}

		$permission = (int) $product->type === 1 ? 'service' : 'product';
		$canReadPrice = getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') > 0 ? $user->hasRight($permission, $permission.'_advance', 'read_prices') : $user->hasRight($permission, 'read');
		if (!isModEnabled('pricelist') || !$canReadPrice
			|| !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)
			|| !checkUserAccessToObject($user, array($permission), $product, 'product&product')) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}
		$costEntity = $this->resolveEntity($sourceObject);
		$costElement = is_object($sourceObject) && isset($sourceObject->element) ? $sourceObject->element : '';
		if ($costEntity !== (int) $conf->entity
			&& (!in_array($costElement, array('propal', 'commande', 'facture', 'facturerec', 'contrat'), true)
				|| !in_array($costEntity, array_map('intval', explode(',', getEntity($costElement))), true))) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}

		// PriceList follows native sale-price sharing, never product sharing alone.
		$entities = array($costEntity);
		if ($costEntity === (int) $conf->entity) {
			$entities = $this->normalizeIdList(array_merge($entities, explode(',', getEntity('productprice'))));
		}
		// A shared document keeps its owner's context; the current sharing list
		// cannot describe that other entity's outbound price-sharing settings.
		$sourceCategory = $this->getSourceObjectCategoryDefinition($sourceObject);
		$socid = $this->getObjectId($soc);
		if ($socid > 0) {
			$customer = new Societe($this->db);
			if (!$user->hasRight('societe', 'lire') || $customer->fetch($socid) <= 0
				|| !in_array((int) $customer->entity, array_map('intval', explode(',', getEntity('societe'))), true)
				|| !checkUserAccessToObject($user, array('societe'), $customer, 'societe')) {
				$this->error = 'NotEnoughPermissions';
				return -1;
			}
		}
		if ($socid > 0) {
			$soc = $customer;
		}
		$customerCategories = $this->getCustomerCategoryIds($socid);
		$prioritySteps = pricelistGetDocumentCategoryPriority()
			? array('document_category', 'thirdparty', 'customer_category')
			: array('thirdparty', 'customer_category', 'document_category');

		foreach ($prioritySteps as $priorityStep) {
			$result = 0;
			if ($priorityStep === 'document_category' && !empty($sourceCategory['field']) && !empty($sourceCategory['ids'])) {
				$result = $this->fetchBestPriceByCategory((int) $idproduct, $qty, $sourceCategory['field'], $sourceCategory['ids'], $entities);
			} elseif ($priorityStep === 'thirdparty' && $socid > 0) {
				$result = $this->fetchBestPrice(
					(int) $idproduct,
					$qty,
					array("t.fk_soc = ".$socid),
					$entities,
					"t.from_qty DESC",
					"fk_soc"
				);
			} elseif ($priorityStep === 'customer_category' && !empty($customerCategories)) {
				$result = $this->fetchBestPriceByCategory((int) $idproduct, $qty, 'fk_cat', $customerCategories, $entities);
			}

			if (is_object($result)) {
				$result->cost_context_entity = $costEntity;
				$result->cost_context_element = $costElement;
				return $this->rejectPriceBelowMinimum($result, $soc, $priceContext);
			}
			if ($result < 0) {
				return -1;
			}
		}

		$result = $this->fetchBestPrice(
			(int) $idproduct,
			$qty,
			array(),
			$entities,
			"t.from_qty DESC"
		);
		if (is_object($result)) {
			$result->cost_context_entity = $costEntity;
			$result->cost_context_element = $costElement;
			return $this->rejectPriceBelowMinimum($result, $soc, $priceContext);
		}
		if ($result < 0) {
			return -1;
		}

		return 0;
	}

	/**
	 * Does this tariff belong to the product, customer or category being edited?
	 * @param object $object Displayed parent
	 * @param string $type Category type
	 * @return bool
	 */
	public function matchesContext($object, $type = '')
	{
		if ($object->element === 'product') {
			return (int) $object->id === (int) $this->product_id;
		}
		if ($object->element === 'societe') {
			return (int) $object->id === (int) $this->socid;
		}
		if ($object->element === 'category') {
			$fields = array('' => 'catid', 'customer' => 'catid', 'propal' => 'catid_propal', 'order' => 'catid_order', 'invoice' => 'catid_invoice', 'contract' => 'catid_contract');
			return isset($fields[$type]) && (int) $object->id === (int) $this->{$fields[$type]};
		}
		return false;
	}

	/**
	 * Fill object properties from a SQL row.
	 *
	 * @param stdClass $obj SQL row
	 * @return void
	 */
	private function setVarsFromFetchObject($obj)
	{
		$this->id = $obj->rowid;
		$this->entity = $obj->entity;
		$this->product_id = $obj->fk_product;
		$this->socid = $obj->fk_soc;
		$this->catid = $obj->fk_cat;
		$this->catid_propal = $obj->fk_cat_propal;
		$this->catid_order = $obj->fk_cat_order;
		$this->catid_invoice = $obj->fk_cat_invoice;
		$this->catid_contract = $obj->fk_cat_contract;
		$this->from_qty = $obj->from_qty;
		$this->price = $obj->price;
		$this->tx_discount = $obj->tx_discount;
		$this->cost_price = $obj->cost_price;
		$this->cost_price_source = self::getCostPriceSourceForRow($obj);
		$this->use_product_cost_price = $this->cost_price_source === 'product' ? 1 : 0;
		$this->user_creation_id = $obj->fk_user_creation;
	}

	/**
	 * Validate price list values before writing.
	 *
	 * @param Translate $langs Translation handler
	 * @param User $user Acting user
	 * @return int
	 */
	private function validatePriceListValues($langs, $user)
	{
		global $conf;
		$langs->load('pricelist@pricelist');
		if (!isModEnabled('pricelist') || (!empty($this->entity) && (int) $this->entity !== (int) $conf->entity)) {
			$this->error = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		$product = new Product($this->db);
		if ($product->fetch((int) $this->product_id) <= 0
			|| !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)) {
			$this->error = $langs->trans('ErrorRecordNotFound');
			return -1;
		}
		$canWrite = (int) $product->type === 1 ? $user->hasRight('service', 'creer') : $user->hasRight('produit', 'creer');
		if (!$canWrite || !checkUserAccessToObject($user, array((int) $product->type === 1 ? 'service' : 'product'), $product, 'product&product')) {
			$this->error = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		if (!is_numeric(price2num($this->from_qty)) || (float) price2num($this->from_qty) <= 0) {
			$this->error = $langs->trans('AllFieldIsRequired');
			return -1;
		}

		if ((int) $this->socid > 0) {
			$thirdparty = new Societe($this->db);
			if (!$user->hasRight('societe', 'lire') || $thirdparty->fetch((int) $this->socid) <= 0
				|| !in_array((int) $thirdparty->entity, array_map('intval', explode(',', getEntity('societe'))), true)
				|| !checkUserAccessToObject($user, array('societe'), $thirdparty, 'societe')) {
				$this->error = $langs->trans('NotEnoughPermissions');
				return -1;
			}
		}
		foreach (array('catid' => 2, 'catid_propal' => 23, 'catid_order' => 16, 'catid_invoice' => 17, 'catid_contract' => 450022) as $property => $categoryType) {
			if ((int) $this->$property > 0) {
				$category = new Categorie($this->db);
				if (!$user->hasRight('categorie', 'lire') || $category->fetch((int) $this->$property) <= 0
					|| (int) $category->type !== $categoryType
					|| !in_array((int) $category->entity, array_map('intval', explode(',', getEntity('categorie'))), true)) {
					$this->error = $langs->trans('NotEnoughPermissions');
					return -1;
				}
			}
		}
		if ($this->normalizeCostPriceMode() < 0) {
			return -1;
		}
		if ($this->cost_price_source === 'dynamicprices') {
			$availability = PriceListCompatibility::getDynamicPricesAvailability();
			if (!$availability['available'] || !$user->hasRight('dynamicsprices', 'cost', 'read')) {
				$this->error = $langs->trans($availability['available'] ? 'PriceListDynamicCostForbidden' : $availability['reason']);
				return -1;
			}
		}

		$priceFilled = dol_strlen($this->price);
		$discountFilled = dol_strlen($this->tx_discount);
		$costFilled = dol_strlen($this->cost_price) || $this->cost_price_source !== 'custom';

		if ((!$priceFilled && !$discountFilled && !$costFilled) || ($priceFilled && $discountFilled)) {
			$this->error = $langs->trans('FillPriceOrDiscountField');
			return -1;
		}

		if (!pricelistIsPropalCategoryAvailable() && ((int) $this->catid_propal > 0)) {
			$this->error = $langs->trans('RequiresDolibarr23Php80');
			return -1;
		}

		if (!self::isDolibarrVersionAtLeast('22.0.0') && (((int) $this->catid_order > 0) || ((int) $this->catid_invoice > 0))) {
			$this->error = $langs->trans('RequiresDolibarr22Php80');
			return -1;
		}

		if (!pricelistIsContractCategoryAvailable() && ((int) $this->catid_contract > 0)) {
			$this->error = $langs->trans('RequiresPriceListContractCategoriesOption');
			return -1;
		}

		$hasThirdparty = ((int) $this->socid > 0);
		$hasCustomerCategory = ((int) $this->catid > 0);
		$hasDocumentCategory = false;
		foreach ($this->getDocumentCategoryProperties() as $field) {
			if ((int) $this->$field > 0) {
				$hasDocumentCategory = true;
				break;
			}
		}

		$exclusiveScopes = 0;
		if ($hasThirdparty) {
			$exclusiveScopes++;
		}
		if ($hasCustomerCategory) {
			$exclusiveScopes++;
		}
		if ($hasDocumentCategory) {
			$exclusiveScopes++;
		}

		if ($exclusiveScopes > 1) {
			$this->error = $langs->trans('PriceListSingleScopeRequired');
			return -1;
		}

		$minimumViolation = $this->getMinimumPriceViolation();
		if ($this->error) {
			$this->error = $langs->trans($this->error);
			return -1;
		}
		if (is_array($minimumViolation)) {
			$this->error = $langs->trans(
				'PriceListBelowMinimumPrice',
				price($minimumViolation['current']),
				price($minimumViolation['minimum'])
			);
			return -1;
		}

		return 1;
	}

	/**
	 * Normalize the cost price mode before persistence.
	 *
	 * @return int 1 on success, -1 on invalid input
	 */
	private function normalizeCostPriceMode()
	{
		global $langs;
		$this->cost_price_source = self::getCostPriceSourceForRow($this);
		if (!in_array($this->cost_price_source, array('custom', 'product', 'dynamicprices'), true)) {
			$this->error = $langs->trans('PriceListInvalidCostSource');
			return -1;
		}
		// The legacy column is a compatibility projection, never an alternative authority.
		$this->use_product_cost_price = $this->cost_price_source === 'product' ? 1 : 0;
		if ($this->cost_price_source !== 'custom') {
			$this->cost_price = null;
		} elseif ($this->cost_price !== null && $this->cost_price !== '') {
			$value = price2num($this->cost_price, 'MU', 2);
			if (!is_numeric($value) || !is_finite((float) $value)) {
				$this->error = $langs->trans('PriceListInvalidCostPrice');
				return -1;
			}
			$this->cost_price = $value;
		}
		return 1;
	}

	/**
	 * Resolve the explicit source, converting a legacy row only when no source exists.
	 * @param stdClass|PriceList $row Price row
	 * @return string
	 */
	public static function getCostPriceSourceForRow($row)
	{
		if (isset($row->cost_price_source)) {
			return (string) $row->cost_price_source;
		}
		return !empty($row->use_product_cost_price) ? 'product' : 'custom';
	}

	/**
	 * Return a minimum price violation for the current row.
	 *
	 * @return array{current:float,minimum:float}|null
	 */
	public function getMinimumPriceViolation()
	{
		$row = new stdClass();
		$row->fk_product = (int) $this->product_id;
		$row->price = $this->price;
		$row->tx_discount = $this->tx_discount;

		$soc = null;
		if ((int) $this->socid > 0) {
			dol_include_once('/societe/class/societe.class.php');
			if (class_exists('Societe')) {
				$soc = new Societe($this->db);
				if ($soc->fetch((int) $this->socid) <= 0) {
					$this->error = 'PriceListMinimumPriceUnavailable';
					return null;
				}
			}
		}

		if ((int) $this->socid > 0 && !is_object($soc)) {
			$this->error = 'PriceListMinimumPriceUnavailable';
			return null;
		}
		return $this->getMinimumPriceViolationForRow($row, $soc);
	}

	/**
	 * Return a minimum price violation for a fetched row.
	 *
	 * @param stdClass    $row Price row
	 * @param object|null $soc Thirdparty context
	 * @param array{unit_price?:float|string|null,base_type?:string,tva_tx?:float|string,remise_percent?:float|string,pqp?:int} $priceContext Actual line price context
	 * @return array{current:float,minimum:float}|null
	 */
	public function getMinimumPriceViolationForRow($row, $soc = null, $priceContext = array())
	{
		$this->error = '';
		// A cost-only enrichment does not change the sale price.
		if ((!isset($row->price) || !dol_strlen($row->price))
			&& (!isset($row->tx_discount) || !dol_strlen($row->tx_discount))) {
			return null;
		}
		try {
			if (getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES') && version_compare(DOL_VERSION, '21.0.0', '<')) {
				throw new RuntimeException('Combined customer pricing requires Dolibarr 21');
			}
			$product = $this->fetchProductForMinimumCheck($this->getRowProductId($row));
			if (!is_object($product)) {
				throw new RuntimeException('Product unavailable');
			}
			// A shared rule cannot be rejected for a customer that is not yet known.
			if (!is_object($soc) && $this->hasContextualSalePrices()) {
				return null;
			}
			$prices = $this->resolveMinimumPriceContext($product, $soc, $priceContext);
			return $this->compareMinimumPrice($row, $prices, $priceContext);
		} catch (Throwable $e) {
			$this->error = 'PriceListMinimumPriceUnavailable';
			dol_syslog(__METHOD__.': sale price context unavailable', LOG_WARNING);
			return null;
		}
	}

	/**
	 * Whether a generic rule needs a customer or a selected quantity price.
	 * @return bool
	 */
	private function hasContextualSalePrices()
	{
		return getDolGlobalInt('PRODUIT_MULTIPRICES') > 0
			|| getDolGlobalInt('PRODUIT_CUSTOMER_PRICES') > 0
			|| getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES') > 0
			|| getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_BY_QTY') > 0
			|| getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES') > 0;
	}

	/**
	 * Resolve native prices and validate the selected native quantity row.
	 * @param Product $product Loaded, accessible product
	 * @param object|null $soc Buyer (null in administration)
	 * @param array{unit_price?:float|string|null,base_type?:string,tva_tx?:float|string,remise_percent?:float|string,pqp?:int} $context Line context
	 * @return array{pu_ht:float,price_min:float}
	 * @throws RuntimeException When the context cannot be checked
	 */
	private function resolveMinimumPriceContext($product, $soc, $context)
	{
		global $mysoc, $user, $hookmanager;
		if (is_object($soc) && (int) $soc->id > 0
			&& (!$user->hasRight('societe', 'lire')
				|| !in_array((int) $soc->entity, array_map('intval', explode(',', getEntity('societe'))), true)
				|| !checkUserAccessToObject($user, array('societe'), $soc, 'societe'))) {
			throw new RuntimeException('Buyer unavailable');
		}
		$level = is_object($soc) && isset($soc->price_level) ? (int) $soc->price_level : 0;
		$quantityLevels = getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES') > 0;
		$levels = $quantityLevels || getDolGlobalInt('PRODUIT_MULTIPRICES') > 0
			|| getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES') > 0;
		$pqp = isset($context['pqp']) ? (int) $context['pqp'] : 0;
		if ($pqp > 0) {
			if (!getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_BY_QTY') && !$quantityLevels) {
				throw new RuntimeException('Quantity pricing disabled');
			}
			$quantityLevel = $quantityLevels ? $level : 0;
			$found = false;
			foreach ($product->prices_by_qty_list[$quantityLevel] ?? array() as $quantityRow) {
				if (isset($quantityRow['rowid']) && (int) $quantityRow['rowid'] === $pqp) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				throw new RuntimeException('Invalid quantity price');
			}
		}
		if (is_object($soc)) {
			if (!is_object($mysoc)) {
				throw new RuntimeException('Seller unavailable');
			}
			$customerPriceFound = false;
			if (getDolGlobalInt('PRODUIT_CUSTOMER_PRICES') || getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')) {
				require_once DOL_DOCUMENT_ROOT.'/product/class/productcustomerprice.class.php';
				$customerPrices = new ProductCustomerPrice($this->db);
				// getSellPrice() does not propagate a negative fetchAll() result.
				// Check the native read separately so an SQL error is never a fallback.
				$result = $customerPrices->fetchAll('', '', 0, 0, array('t.fk_product' => (string) $product->id, 't.fk_soc' => (string) $soc->id));
				if ($result < 0) {
					throw new RuntimeException('Customer price read failed');
				}
				$day = (int) floor(dol_now() / 86400) * 86400;
				foreach ($customerPrices->lines as $customerPrice) {
					if (version_compare(DOL_VERSION, '22.0.0', '<')
						|| ($customerPrice->date_begin <= $day && (!$customerPrice->date_end || $day <= $customerPrice->date_end))) {
						$customerPriceFound = true;
						break;
					}
				}
			}
			if ($levels && $level > 0 && !$customerPriceFound
				&& (!isset($product->multiprices_min[$level]) || !isset($product->multiprices[$level]))) {
				throw new RuntimeException('Price level unavailable');
			}
			$prices = $product->getSellPrice($mysoc, $soc, $pqp);
			if ($this->db->error() !== '') {
				throw new RuntimeException('Native price read failed');
			}
		} else {
			$prices = array('pu_ht' => $product->price, 'price_min' => $product->price_min);
		}
		if (!is_array($prices)) {
			throw new RuntimeException('Invalid native price result');
		}
		// The native commercial cards select the level minimum in this experimental mode.
		// getSellPrice() in v20-v24 does not select that minimum itself.
		$nativeHookReplacement = is_object($hookmanager) && isset($hookmanager->resArray['price_min'])
			&& isset($hookmanager->resArray['pu_ht']) && $prices === $hookmanager->resArray;
		if ($quantityLevels && $level > 0 && !$nativeHookReplacement) {
			$prices['price_min'] = $product->multiprices_min[$level] ?? null;
			if ($pqp === 0) {
				$prices['pu_ht'] = $product->multiprices[$level] ?? null;
			}
		} elseif ($levels && $level > 0 && !isset($product->multiprices_min[$level])
			&& !getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')) {
			throw new RuntimeException('Price level unavailable');
		}
		// Native quantity pricing changes only the selected base (HT or TTC).
		if ($pqp > 0 && !$nativeHookReplacement && isset($quantityRow) && $quantityRow['price_base_type'] === 'TTC') {
			$prices['pu_ht'] = $this->lineUnitPriceHT(array(
				'unit_price' => $quantityRow['unitprice'], 'base_type' => 'TTC',
				'tva_tx' => $prices['tva_tx'] ?? $product->tva_tx,
			));
		}
		foreach (array('pu_ht', 'price_min') as $field) {
			if (!array_key_exists($field, $prices) || $prices[$field] === null || $prices[$field] === ''
				|| !is_numeric(price2num($prices[$field])) || !is_finite((float) price2num($prices[$field]))
				|| ($field === 'price_min' && (float) price2num($prices[$field]) < 0)) {
				throw new RuntimeException('Native price field unavailable');
			}
		}
		return array('pu_ht' => (float) price2num($prices['pu_ht'], 'MU'), 'price_min' => (float) price2num($prices['price_min'], 'MU'));
	}

	/**
	 * Convert a line unit price with the native commercial calculation.
	 * @param array{unit_price:float|string|null,base_type?:string,tva_tx?:float|string} $context Line context
	 * @return float
	 */
	private function lineUnitPriceHT($context)
	{
		if ($context['unit_price'] === null || $context['unit_price'] === '' || !is_numeric(price2num($context['unit_price'])) || !is_finite((float) price2num($context['unit_price']))) {
			throw new RuntimeException('Invalid line price');
		}
		$base = $context['base_type'] ?? 'HT';
		if ($base === 'HT') {
			return (float) price2num($context['unit_price'], 'MU');
		}
		if ($base !== 'TTC' || !isset($context['tva_tx'])) {
			throw new RuntimeException('Invalid line price base');
		}
		require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
		$vat = preg_replace('/\s*\(.*\)/', '', (string) $context['tva_tx']);
		if (!is_numeric(price2num($vat))) {
			throw new RuntimeException('Invalid VAT rate');
		}
		$amounts = calcul_price_total(1, $context['unit_price'], 0, (float) price2num($vat), 0, 0, 0, 'TTC', 0, 0);
		return (float) price2num($amounts[3], 'MU');
	}

	/**
	 * Compare the actual final HT unit price, including the retained discount.
	 * @param stdClass $row Rule
	 * @param array{pu_ht:float,price_min:float} $prices Native context
	 * @param array{unit_price?:float|string|null,base_type?:string,tva_tx?:float|string,remise_percent?:float|string} $context Line context
	 * @return array{current:float,minimum:float}|null
	 */
	private function compareMinimumPrice($row, $prices, $context)
	{
		$fixed = isset($row->price) && dol_strlen($row->price);
		$unit = $fixed ? $this->lineUnitPriceHT(array('unit_price' => $row->price))
			: (array_key_exists('unit_price', $context) ? $this->lineUnitPriceHT($context) : $prices['pu_ht']);
		$discount = $fixed ? ($context['remise_percent'] ?? 0) : $row->tx_discount;
		if ($discount === '') {
			$discount = 0;
		}
		if (!is_numeric(price2num($discount)) || !is_finite((float) price2num($discount))) {
			throw new RuntimeException('Invalid discount');
		}
		$current = (float) price2num($unit * (1 - (float) price2num($discount) / 100), 'MU');
		return $current < $prices['price_min']
			? array('current' => $current, 'minimum' => $prices['price_min']) : null;
	}

	/**
	 * Describe generic administration checks without inventing a customer.
	 * @param stdClass $row Rule
	 * @return list<array{current?:float,minimum?:float,label:string,reason?:string}>
	 */
	public function getMinimumPriceWarningsForRow($row)
	{
		$this->error = '';
		$warnings = array();
		$product = $this->fetchProductForMinimumCheck($this->getRowProductId($row));
		if (!is_object($product)) {
			return array(array('label' => '', 'reason' => 'PriceListMinimumPriceUnavailable'));
		}
		if ((!isset($row->price) || !dol_strlen($row->price)) && (!isset($row->tx_discount) || !dol_strlen($row->tx_discount))) {
			return array();
		}
		$levels = getDolGlobalInt('PRODUIT_MULTIPRICES') || getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')
			|| getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES');
		if ($levels) {
			for ($level = 1; $level <= getDolGlobalInt('PRODUIT_MULTIPRICES_LIMIT', 5); $level++) {
				$label = getDolGlobalString('PRODUIT_MULTIPRICES_LABEL'.$level, (string) $level);
				if (!isset($product->multiprices_min[$level], $product->multiprices[$level])
					|| $product->multiprices_min[$level] === '' || $product->multiprices[$level] === ''
					|| !is_numeric(price2num($product->multiprices_min[$level])) || !is_numeric(price2num($product->multiprices[$level]))
					|| !is_finite((float) price2num($product->multiprices_min[$level])) || !is_finite((float) price2num($product->multiprices[$level]))
					|| (float) price2num($product->multiprices_min[$level]) < 0) {
					$warnings[] = array('label' => $label, 'reason' => 'PriceListMinimumPriceUnavailable');
					continue;
				}
				try {
					$violation = $this->compareMinimumPrice($row, array(
						'pu_ht' => (float) price2num($product->multiprices[$level], 'MU'),
						'price_min' => (float) price2num($product->multiprices_min[$level], 'MU'),
					), array());
				} catch (RuntimeException $e) {
					$warnings[] = array('label' => $label, 'reason' => 'PriceListMinimumPriceUnavailable');
					continue;
				}
				if (is_array($violation)) {
					$violation['label'] = $label;
					$warnings[] = $violation;
				}
			}
		} elseif (!getDolGlobalInt('PRODUIT_CUSTOMER_PRICES')) {
			$violation = $this->getMinimumPriceViolationForRow($row);
			if (is_array($violation)) {
				$violation['label'] = '';
				$warnings[] = $violation;
			} elseif ($this->error) {
				$warnings[] = array('label' => '', 'reason' => $this->error);
			}
		}
		if (getDolGlobalInt('PRODUIT_CUSTOMER_PRICES') || getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')
			|| getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_BY_QTY') || getDolGlobalInt('PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES')) {
			$warnings[] = array('label' => '', 'reason' => 'PriceListMinimumPriceContextRequired');
		}
		return $warnings;
	}

	/**
	 * Return the effective cost price for a row.
	 *
	 * @param stdClass|PriceList $row Price row
	 * @return float|null
	 */
	public function getEffectiveCostPriceForRow($row)
	{
		global $conf, $user;
		$this->cost_price_warning = '';
		$source = self::getCostPriceSourceForRow($row);
		$productId = $this->getRowProductId($row);
		if ($source === 'custom') {
			if (!isset($row->cost_price) || $row->cost_price === '') {
				return null;
			}
			$value = price2num($row->cost_price, 'MU', 2);
			return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
		}
		if ($source === 'product') {
			return $this->getProductCostPrice($productId);
		}
		if ($source !== 'dynamicprices') {
			$this->cost_price_warning = 'PriceListInvalidCostSource';
			return null;
		}
		$this->cost_price_warning = 'PriceListDynamicCostUnavailable';
		$entity = isset($row->cost_context_entity) ? (int) $row->cost_context_entity : (int) $conf->entity;
		$element = isset($row->cost_context_element) ? $row->cost_context_element : '';
		if ($entity <= 0 || ($entity !== (int) $conf->entity && (!in_array($element, array('propal', 'commande', 'facture', 'facturerec', 'contrat'), true) || !in_array($entity, array_map('intval', explode(',', getEntity($element))), true)))) {
			return null;
		}
		$availability = PriceListCompatibility::getDynamicPricesAvailability($this->db, $entity);
		if (!$availability['available']) {
			$this->cost_price_warning = $availability['reason'];
			return null;
		}
		if (!$user->hasRight('dynamicsprices', 'cost', 'read')) {
			$this->cost_price_warning = 'PriceListDynamicCostForbidden';
			return null;
		}
		$product = new Product($this->db);
		if ($productId <= 0 || $product->fetch($productId) <= 0
			|| !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)) {
			return null;
		}
		$permission = (int) $product->type === 1 ? 'service' : 'product';
		if (!$user->hasRight($permission, 'read')
			|| !checkUserAccessToObject($user, array($permission), $product, 'product&product')) {
			return null;
		}
		try {
			$service = new DynamicPricesCostService($this->db);
			$value = $service->getDynamicCostPrice($productId, $entity, array('require_success' => true));
		} catch (Throwable $exception) {
			dol_syslog(__METHOD__.' DynamicPrices cost unavailable', LOG_WARNING);
			return null;
		}
		if ($value === null || !is_numeric($value) || !is_finite((float) $value)) {
			return null;
		}
		$this->cost_price_warning = '';
		return (float) price2num($value, 'MU');
	}

	/**
	 * Return the native product cost price.
	 *
	 * @param int $productId Product id
	 * @return float|null
	 */
	public function getProductCostPrice($productId)
	{
		global $user;
		$product = new Product($this->db);
		if ($productId <= 0 || $product->fetch($productId) <= 0
			|| !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)) {
			return null;
		}
		$permission = (int) $product->type === 1 ? 'service' : 'product';
		if (!$user->hasRight($permission, 'read') || !checkUserAccessToObject($user, array($permission), $product, 'product&product')
			|| !isset($product->cost_price) || $product->cost_price === '') {
			return null;
		}
		$value = price2num($product->cost_price, 'MU', 2);
		return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
	}

	/**
	 * Return the product id carried by a row object.
	 *
	 * @param stdClass|PriceList $row Price row
	 * @return int
	 */
	private function getRowProductId($row)
	{
		if (isset($row->fk_product)) {
			return (int) $row->fk_product;
		}
		if (isset($row->product_id)) {
			return (int) $row->product_id;
		}

		return 0;
	}

	/**
	 * Fetch the product used for minimum price checks.
	 *
	 * @param int $productId Product id
	 * @return Product|null
	 */
	private function fetchProductForMinimumCheck($productId)
	{
		global $user;
		$product = new Product($this->db);
		if ($productId <= 0 || $product->fetch($productId) <= 0) {
			return null;
		}

		$permission = (int) $product->type === 1 ? 'service' : 'product';
		$allowed = getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') ? $user->hasRight($permission, $permission.'_advance', 'read_prices') : $user->hasRight($permission, 'read');
		if (!$allowed || !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)
			|| !checkUserAccessToObject($user, array($permission), $product, 'product&product')) {
			return null;
		}
		return $product;
	}

	/**
	 * Reject a fetched row when it violates the native product minimum sale price.
	 *
	 * @param stdClass    $row Price row
	 * @param object|null $soc Thirdparty context
	 * @param array{unit_price?:float|string|null,base_type?:string,tva_tx?:float|string,remise_percent?:float|string,pqp?:int} $priceContext Actual line price context
	 * @return int|stdClass
	 */
	private function rejectPriceBelowMinimum($row, $soc = null, $priceContext = array())
	{
		$violation = $this->getMinimumPriceViolationForRow($row, $soc, $priceContext);
		if ($this->error) {
			return -1;
		}
		if (is_array($violation)) {
			$this->error = 'PriceListBelowMinimumPriceNotApplied';
			return -1;
		}

		return $row;
	}

	/**
	 * Return SQL for a nullable integer.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function formatNullableInt($value)
	{
		return ((int) $value > 0) ? (string) ((int) $value) : "null";
	}

	/**
	 * Return SQL for a nullable price-like value.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function formatNullablePrice($value)
	{
		return dol_strlen($value) ? (string) price2num($value) : "null";
	}

	/**
	 * Check Dolibarr version without depending on the compatibility helper.
	 *
	 * @param string $version Version
	 * @return bool
	 */
	private static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Resolve the entity used for price list queries.
	 *
	 * @param ?object $sourceObject Source object
	 * @param int     $fallbackEntity Forced fallback entity
	 * @return int
	 */
	private function resolveEntity($sourceObject = null, $fallbackEntity = 0)
	{
		global $conf;

		if (is_object($sourceObject) && !empty($sourceObject->entity)) {
			return (int) $sourceObject->entity;
		}
		if ($fallbackEntity > 0) {
			return (int) $fallbackEntity;
		}
		if (!empty($conf->entity)) {
			return (int) $conf->entity;
		}

		return 1;
	}

	/**
	 * Return the category field for a given category type.
	 *
	 * @param string $categorytype Category type
	 * @return string
	 */
	private function getCategoryFieldForType($categorytype)
	{
		if ($categorytype === 'propal') {
			return 'fk_cat_propal';
		}
		if ($categorytype === 'order' || $categorytype === 'commande') {
			return 'fk_cat_order';
		}
		if ($categorytype === 'invoice' || $categorytype === 'facture') {
			return 'fk_cat_invoice';
		}
		if ($categorytype === 'contract' || $categorytype === 'contrat') {
			return 'fk_cat_contract';
		}

		return 'fk_cat';
	}

	/**
	 * Return the numeric id of an object.
	 *
	 * @param ?object $object Object
	 * @return int
	 */
	private function getObjectId($object)
	{
		if (!is_object($object)) {
			return 0;
		}
		if (!empty($object->id)) {
			return (int) $object->id;
		}
		if (!empty($object->rowid)) {
			return (int) $object->rowid;
		}

		return 0;
	}

	/**
	 * Return source object categories and target field.
	 *
	 * @param ?object $sourceObject Source object
	 * @return array{field:string,ids:array<int,int>}
	 */
	private function getSourceObjectCategoryDefinition($sourceObject)
	{
		$definition = array('field' => '', 'ids' => array());
		if (!is_object($sourceObject) || $this->getObjectId($sourceObject) <= 0) {
			return $definition;
		}

		$element = '';
		if (!empty($sourceObject->element)) {
			$element = (string) $sourceObject->element;
		} elseif (!empty($sourceObject->table_element)) {
			$element = (string) $sourceObject->table_element;
		}

		if ($element === 'propal' && pricelistIsPropalCategoryAvailable()) {
			$definition['field'] = 'fk_cat_propal';
			$definition['ids'] = $this->getObjectCategoryIds($this->getObjectId($sourceObject), 'categorie_propal', 'fk_propal', 23);
		} elseif (($element === 'commande' || $element === 'order') && self::isDolibarrVersionAtLeast('22.0.0')) {
			$definition['field'] = 'fk_cat_order';
			$definition['ids'] = $this->getObjectCategoryIds($this->getObjectId($sourceObject), 'categorie_order', 'fk_order', 16);
		} elseif (($element === 'facture' || $element === 'invoice') && self::isDolibarrVersionAtLeast('22.0.0')) {
			$definition['field'] = 'fk_cat_invoice';
			$definition['ids'] = $this->getObjectCategoryIds($this->getObjectId($sourceObject), 'categorie_invoice', 'fk_invoice', 17);
		} elseif (($element === 'contrat' || $element === 'contract') && pricelistIsContractCategoryAvailable()) {
			$definition['field'] = 'fk_cat_contract';
			$definition['ids'] = $this->getObjectCategoryIds($this->getObjectId($sourceObject), 'categorie_contract', 'fk_contract', 450022);
		}

		return $definition;
	}

	/**
	 * Return category ids linked to an object through a category relation table.
	 *
	 * @param int    $objectId          Object id
	 * @param string $tableElement      Link table without prefix
	 * @param string $objectField       Object foreign key
	 * @param int    $categoryType      Category type
	 * @return array<int,int>
	 */
	private function getObjectCategoryIds($objectId, $tableElement, $objectField, $categoryType)
	{
		$ids = array();
		if (!$this->tableExists($tableElement)) {
			return $ids;
		}

		$sql = "SELECT ct.fk_categorie";
		$sql .= " FROM ".MAIN_DB_PREFIX.$tableElement." as ct";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."categorie as c ON c.rowid = ct.fk_categorie";
		$sql .= " WHERE ct.".$objectField." = ".((int) $objectId);
		$sql .= " AND c.type = ".((int) $categoryType);
		$sql .= " AND c.entity IN (".$this->getCategoryEntityScope().")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = "Error ".$this->db->lasterror();
			return $ids;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$ids[] = (int) $obj->fk_categorie;
		}
		$this->db->free($resql);

		return array_values(array_unique($ids));
	}

	/**
	 * Return customer categories.
	 *
	 * @param int $socid Thirdparty id
	 * @return array<int,int>
	 */
	private function getCustomerCategoryIds($socid)
	{
		if ($socid <= 0) {
			return array();
		}

		$objcat = new Categorie($this->db);
		$type = defined('Categorie::TYPE_CUSTOMER') ? Categorie::TYPE_CUSTOMER : 2;
		$cats = $objcat->containing($socid, $type);
		if (!is_array($cats) || empty($cats)) {
			return array();
		}

		$ids = array();
		foreach ($cats as $cat) {
			if (!empty($cat->id)) {
				$ids[] = (int) $cat->id;
			}
		}

		return array_values(array_unique($ids));
	}

	/**
	 * Fetch the best price matching a category field.
	 *
	 * @param int        $idproduct Product id
	 * @param float|int  $qty       Quantity
	 * @param string     $field     Category field
	 * @param array<int> $ids       Category ids
	 * @param array<int,int> $entities Entity ids, ordered by priority
	 * @return int|stdClass
	 */
	private function fetchBestPriceByCategory($idproduct, $qty, $field, $ids, $entities)
	{
		$ids = $this->normalizeIdList($ids);
		if (empty($ids)) {
			return 0;
		}

		return $this->fetchBestPrice(
			$idproduct,
			$qty,
			array("t.".$field." IN (".implode(',', $ids).")"),
			$entities,
			"t.from_qty DESC, t.price ASC",
			$field
		);
	}

	/**
	 * Fetch the best price for additional where clauses.
	 *
	 * @param int           $idproduct   Product id
	 * @param float|int     $qty         Quantity
	 * @param array<string> $where       Additional where clauses
	 * @param array<int,int> $entities   Entity ids, ordered by priority
	 * @param string        $order       SQL order
	 * @param string        $exceptField Target field allowed to be filled
	 * @return int|stdClass
	 */
	private function fetchBestPrice($idproduct, $qty, $where, $entities, $order, $exceptField = '')
	{
		foreach ($this->normalizeIdList($entities) as $entity) {
			$result = $this->fetchBestPriceForEntity($idproduct, $qty, $where, $entity, $order, $exceptField);
			if ($result) {
				return $result;
			}
			if ($result < 0) {
				return -1;
			}
		}

		return 0;
	}

	/**
	 * Fetch the best price for one entity and additional where clauses.
	 *
	 * @param int           $idproduct   Product id
	 * @param float|int     $qty         Quantity
	 * @param array<string> $where       Additional where clauses
	 * @param int           $entity      Entity id
	 * @param string        $order       SQL order
	 * @param string        $exceptField Target field allowed to be filled
	 * @return int|stdClass
	 */
	private function fetchBestPriceForEntity($idproduct, $qty, $where, $entity, $order, $exceptField = '')
	{
		$sql = "SELECT rowid, fk_product, price, tx_discount, cost_price, cost_price_source, use_product_cost_price, from_qty";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		$sql .= " WHERE t.entity = ".((int) $entity);
		$sql .= " AND t.fk_product = ".((int) $idproduct);
		$sql .= " AND t.from_qty <= ".price2num($qty);
		foreach ($this->getEmptyTargetWhere($exceptField) as $emptyTargetWhere) {
			$sql .= " AND ".$emptyTargetWhere;
		}
		foreach ($where as $wherePart) {
			$sql .= " AND ".$wherePart;
		}
		$sql .= " ORDER BY ".$order;

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				return $this->db->fetch_object($resql);
			}

			$this->db->free($resql);
			return 0;
		}

		$this->error = "Error ".$this->db->lasterror();
		return -1;
	}

	/**
	 * Return target fields that must stay empty for a scope.
	 *
	 * @param string $exceptField Field allowed to be filled
	 * @return array<string>
	 */
	private function getEmptyTargetWhere($exceptField = '')
	{
		$where = array();
		$fields = array_merge(array('fk_soc', 'fk_cat'), $this->getDocumentCategoryFields());
		$allowedDocumentFields = $this->isDocumentCategoryField($exceptField) ? $this->getDocumentCategoryFields() : array();
		foreach ($fields as $field) {
			if ($field === $exceptField) {
				continue;
			}
			if (in_array($field, $allowedDocumentFields, true)) {
				continue;
			}
			$where[] = "(t.".$field." IS NULL OR t.".$field." < 1)";
		}

		return $where;
	}

	/**
	 * Return history rows for this price list line.
	 *
	 * @return ?array<int,stdClass> Null on error
	 */
	public function getHistory()
	{
		if (empty($this->id)) {
			return array();
		}
		if (!$this->tableExists('pricelist_log')) {
			return array();
		}

		$sql = "SELECT";
		$sql .= " l.rowid,";
		$sql .= " l.entity,";
		$sql .= " l.fk_pricelist,";
		$sql .= " l.datec,";
		$sql .= " l.fk_user,";
		$sql .= " l.change_type,";
		$sql .= " l.fk_product,";
		$sql .= " l.fk_soc,";
		$sql .= " l.fk_cat,";
		$sql .= " l.fk_cat_propal,";
		$sql .= " l.fk_cat_order,";
		$sql .= " l.fk_cat_invoice,";
		$sql .= " l.fk_cat_contract,";
		$sql .= " l.from_qty,";
		$sql .= " l.price,";
		$sql .= " l.tx_discount,";
		$sql .= " l.cost_price,";
		$sql .= " l.cost_price_source, l.use_product_cost_price,";
		$sql .= " u.login";
		$sql .= " FROM ".MAIN_DB_PREFIX."pricelist_log as l";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = l.fk_user";
		$sql .= " WHERE l.fk_pricelist = ".((int) $this->id);
		$sql .= " AND l.entity = ".((int) $this->entity);
		$sql .= " ORDER BY l.datec ASC, l.rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = "Error ".$this->db->lasterror();
			return null;
		}

		$history = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$history[] = $obj;
		}
		$this->db->free($resql);

		return $history;
	}

	/**
	 * Insert a history snapshot for the current line.
	 *
	 * @param User   $user       User
	 * @param string $changeType Change type
	 * @return int
	 */
	private function insertHistory($user, $changeType)
	{
		global $conf;

		if (empty($this->id) || !$this->tableExists('pricelist_log')) {
			return 1;
		}

		$entity = !empty($this->entity) ? (int) $this->entity : (!empty($conf->entity) ? (int) $conf->entity : 1);
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."pricelist_log (";
		$sql .= "entity, fk_pricelist, datec, fk_user, change_type,";
		$sql .= " fk_product, fk_soc, fk_cat, fk_cat_propal, fk_cat_order, fk_cat_invoice, fk_cat_contract,";
		$sql .= " from_qty, price, tx_discount, cost_price, cost_price_source, use_product_cost_price";
		$sql .= ") VALUES (";
		$sql .= ((int) $entity).",";
		$sql .= ((int) $this->id).",";
		$sql .= "'".$this->db->idate(dol_now())."',";
		$sql .= (!empty($user->id) ? (int) $user->id : "null").",";
		$sql .= "'".$this->db->escape($changeType)."',";
		$sql .= ((int) $this->product_id).",";
		$sql .= $this->formatNullableInt($this->socid).",";
		$sql .= $this->formatNullableInt($this->catid).",";
		$sql .= $this->formatNullableInt($this->catid_propal).",";
		$sql .= $this->formatNullableInt($this->catid_order).",";
		$sql .= $this->formatNullableInt($this->catid_invoice).",";
		$sql .= $this->formatNullableInt($this->catid_contract).",";
		$sql .= price2num($this->from_qty).",";
		$sql .= $this->formatNullablePrice($this->price).",";
		$sql .= $this->formatNullablePrice($this->tx_discount).",";
		$sql .= $this->formatNullablePrice($this->cost_price).",";
		$sql .= "'".$this->db->escape($this->cost_price_source)."',".((int) $this->use_product_cost_price);
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = "Error ".$this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Check if current values differ from a previous snapshot.
	 *
	 * @param PriceList $oldcopy Previous object
	 * @return bool
	 */
	private function hasChangedComparedTo($oldcopy)
	{
		if (self::getCostPriceSourceForRow($this) !== self::getCostPriceSourceForRow($oldcopy)) {
			return true;
		}
		foreach (array('product_id', 'socid', 'catid', 'catid_propal', 'catid_order', 'catid_invoice', 'catid_contract') as $property) {
			if ((int) $this->$property !== (int) $oldcopy->$property) {
				return true;
			}
		}

		foreach (array('from_qty', 'price', 'tx_discount', 'cost_price') as $property) {
			$current = dol_strlen($this->$property) ? price2num($this->$property) : null;
			$previous = dol_strlen($oldcopy->$property) ? price2num($oldcopy->$property) : null;
			if ($current !== $previous) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return document category properties that may be combined on one price row.
	 *
	 * @return array<int,string>
	 */
	private function getDocumentCategoryProperties()
	{
		return array('catid_propal', 'catid_order', 'catid_invoice', 'catid_contract');
	}

	/**
	 * Return document category SQL fields that may be combined on one price row.
	 *
	 * @return array<int,string>
	 */
	private function getDocumentCategoryFields()
	{
		return array('fk_cat_propal', 'fk_cat_order', 'fk_cat_invoice', 'fk_cat_contract');
	}

	/**
	 * Check if a SQL field is a document category target.
	 *
	 * @param string $field SQL field
	 * @return bool
	 */
	private function isDocumentCategoryField($field)
	{
		return in_array($field, array('fk_cat_propal', 'fk_cat_order', 'fk_cat_invoice', 'fk_cat_contract'), true);
	}

	/**
	 * Normalize a list of integer ids.
	 *
	 * @param array<int,mixed> $ids Id list
	 * @return array<int,int>
	 */
	private function normalizeIdList($ids)
	{
		$normalized = array();
		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$normalized[] = $id;
			}
		}

		return array_values(array_unique($normalized));
	}

	/**
	 * Return category entity scope.
	 *
	 * @return string
	 */
	private function getCategoryEntityScope()
	{
		global $conf;

		if (function_exists('getEntity')) {
			return $this->db->sanitize(getEntity('category'));
		}

		return !empty($conf->entity) ? (string) ((int) $conf->entity) : '1';
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $tableElement Table without prefix
	 * @return bool
	 */
	private function tableExists($tableElement)
	{
		$sql = "SHOW TABLES LIKE '".$this->db->escape(MAIN_DB_PREFIX.$tableElement)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}
}
