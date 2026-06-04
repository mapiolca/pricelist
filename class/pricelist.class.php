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

/**
 * Manage price lists.
 */
class PriceList extends CommonObject
{
	public $db;
	public $error;
	public $errors = array();
	public $element = 'pricelist';
	public $table_element = 'pricelist';
	public $ismultientitymanaged = 1;

	public $id;
	public $entity;
	public $product_id;
	public $socid;
	public $catid;
	public $catid_propal;
	public $catid_contract;
	public $from_qty;
	public $price;
	public $tx_discount;
	public $cost_price;
	public $user_creation_id;

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
	 * @return int <0 if KO, id of created object if OK
	 */
	public function create($user)
	{
		global $conf, $langs;

		if ($this->validatePriceListValues($langs) < 0) {
			return -1;
		}

		$this->entity = !empty($this->entity) ? (int) $this->entity : (!empty($conf->entity) ? (int) $conf->entity : 1);

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "entity,";
		$sql .= "fk_product,";
		$sql .= "fk_soc,";
		$sql .= "fk_cat,";
		$sql .= "fk_cat_propal,";
		$sql .= "fk_cat_contract,";
		$sql .= "from_qty,";
		$sql .= "price,";
		$sql .= "tx_discount,";
		$sql .= "cost_price,";
		$sql .= "fk_user_creation";
		$sql .= ") VALUES (";
		$sql .= " ".((int) $this->entity).",";
		$sql .= " ".((int) $this->product_id).",";
		$sql .= " ".$this->formatNullableInt($this->socid).",";
		$sql .= " ".$this->formatNullableInt($this->catid).",";
		$sql .= " ".$this->formatNullableInt($this->catid_propal).",";
		$sql .= " ".$this->formatNullableInt($this->catid_contract).",";
		$sql .= " ".price2num($this->from_qty).",";
		$sql .= " ".$this->formatNullablePrice($this->price).",";
		$sql .= " ".$this->formatNullablePrice($this->tx_discount).",";
		$sql .= " ".$this->formatNullablePrice($this->cost_price).",";
		$sql .= " ".((int) $user->id);
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

		$res = $this->call_trigger('PRICELIST_CREATE', $user);
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
		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.entity,";
		$sql .= " t.fk_product,";
		$sql .= " t.fk_soc,";
		$sql .= " t.fk_cat,";
		$sql .= " t.fk_cat_propal,";
		$sql .= " t.fk_cat_contract,";
		$sql .= " t.from_qty,";
		$sql .= " t.price,";
		$sql .= " t.tx_discount,";
		$sql .= " t.cost_price,";
		$sql .= " t.fk_user_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t WHERE t.rowid = ".((int) $id);

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
		$entity = $this->resolveEntity(null, $entity);
		$categoryfield = $this->getCategoryFieldForType($categorytype);

		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.entity,";
		$sql .= " t.fk_product,";
		$sql .= " t.fk_soc,";
		$sql .= " t.fk_cat,";
		$sql .= " t.fk_cat_propal,";
		$sql .= " t.fk_cat_contract,";
		$sql .= " t.from_qty,";
		$sql .= " t.price,";
		$sql .= " t.tx_discount,";
		$sql .= " t.cost_price,";
		$sql .= " t.fk_user_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";

		$where = array("t.entity = ".((int) $entity));
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
		$sql .= " ORDER BY t.fk_product, t.fk_soc, t.fk_cat, t.fk_cat_propal, t.fk_cat_contract, t.from_qty";

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
	 * @return int <0 if KO, >0 if OK
	 */
	public function update($user)
	{
		global $langs;

		if ($this->validatePriceListValues($langs) < 0) {
			return -1;
		}

		$error = 0;
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " fk_product=".((int) $this->product_id).",";
		$sql .= " fk_soc=".$this->formatNullableInt($this->socid).",";
		$sql .= " fk_cat=".$this->formatNullableInt($this->catid).",";
		$sql .= " fk_cat_propal=".$this->formatNullableInt($this->catid_propal).",";
		$sql .= " fk_cat_contract=".$this->formatNullableInt($this->catid_contract).",";
		$sql .= " from_qty=".price2num($this->from_qty).",";
		$sql .= " price=".$this->formatNullablePrice($this->price).",";
		$sql .= " tx_discount=".$this->formatNullablePrice($this->tx_discount).",";
		$sql .= " cost_price=".$this->formatNullablePrice($this->cost_price);
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
			$result = $this->call_trigger('PRICELIST_UPDATE', $user);
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
	 * @return int|stdClass 0 if not found, -1 on error, price row when found
	 */
	public function get_price($idproduct, $soc, $qty, $sourceObject = null)
	{
		$product = new Product($this->db);
		$res = $product->fetch((int) $idproduct);
		if ($res <= 0) {
			$this->error = 'Failed to fetch product';
			return -1;
		}

		$entity = $this->resolveEntity($sourceObject);
		$sourceCategory = $this->getSourceObjectCategoryDefinition($sourceObject);
		if (!empty($sourceCategory['field']) && !empty($sourceCategory['ids'])) {
			$result = $this->fetchBestPriceByCategory((int) $idproduct, $qty, $sourceCategory['field'], $sourceCategory['ids'], $entity);
			if ($result) {
				return $result;
			}
			if ($result < 0) {
				return -1;
			}
		}

		$socid = $this->getObjectId($soc);
		if ($socid > 0) {
			$result = $this->fetchBestPrice(
				(int) $idproduct,
				$qty,
				array("t.fk_soc = ".$socid),
				$entity,
				"t.from_qty DESC",
				"fk_soc"
			);
			if ($result) {
				return $result;
			}
			if ($result < 0) {
				return -1;
			}
		}

		$customerCategories = $this->getCustomerCategoryIds($socid);
		if (!empty($customerCategories)) {
			$result = $this->fetchBestPriceByCategory((int) $idproduct, $qty, 'fk_cat', $customerCategories, $entity);
			if ($result) {
				return $result;
			}
			if ($result < 0) {
				return -1;
			}
		}

		$result = $this->fetchBestPrice(
			(int) $idproduct,
			$qty,
			array(),
			$entity,
			"t.from_qty DESC"
		);
		if ($result) {
			return $result;
		}
		if ($result < 0) {
			return -1;
		}

		return 0;
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
		$this->catid_contract = $obj->fk_cat_contract;
		$this->from_qty = $obj->from_qty;
		$this->price = $obj->price;
		$this->tx_discount = $obj->tx_discount;
		$this->cost_price = $obj->cost_price;
		$this->user_creation_id = $obj->fk_user_creation;
	}

	/**
	 * Validate price list values before writing.
	 *
	 * @param Translate $langs Translation handler
	 * @return int
	 */
	private function validatePriceListValues($langs)
	{
		$priceFilled = dol_strlen($this->price);
		$discountFilled = dol_strlen($this->tx_discount);
		$costFilled = dol_strlen($this->cost_price);

		if ((!$priceFilled && !$discountFilled && !$costFilled) || ($priceFilled && $discountFilled)) {
			$this->error = $langs->trans('FillPriceOrDiscountField');
			return -1;
		}

		$targetCount = 0;
		foreach (array($this->socid, $this->catid, $this->catid_propal, $this->catid_contract) as $target) {
			if ((int) $target > 0) {
				$targetCount++;
			}
		}
		if ($targetCount > 1) {
			$this->error = $langs->trans('PriceListSingleScopeRequired');
			return -1;
		}

		return 1;
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

		if ($element === 'propal') {
			$definition['field'] = 'fk_cat_propal';
			$definition['ids'] = $this->getObjectCategoryIds($this->getObjectId($sourceObject), 'categorie_propal', 'fk_propal', 23);
		} elseif ($element === 'contrat' || $element === 'contract') {
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
	 * @param int        $entity    Entity id
	 * @return int|stdClass
	 */
	private function fetchBestPriceByCategory($idproduct, $qty, $field, $ids, $entity)
	{
		$ids = $this->normalizeIdList($ids);
		if (empty($ids)) {
			return 0;
		}

		return $this->fetchBestPrice(
			$idproduct,
			$qty,
			array("t.".$field." IN (".implode(',', $ids).")"),
			$entity,
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
	 * @param int           $entity      Entity id
	 * @param string        $order       SQL order
	 * @param string        $exceptField Target field allowed to be filled
	 * @return int|stdClass
	 */
	private function fetchBestPrice($idproduct, $qty, $where, $entity, $order, $exceptField = '')
	{
		$sql = "SELECT price, tx_discount, cost_price, from_qty";
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
		foreach (array('fk_soc', 'fk_cat', 'fk_cat_propal', 'fk_cat_contract') as $field) {
			if ($field === $exceptField) {
				continue;
			}
			$where[] = "(t.".$field." IS NULL OR t.".$field." < 1)";
		}

		return $where;
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
