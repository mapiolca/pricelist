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
dol_include_once('/pricelist/lib/pricelist.lib.php');

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
	public $catid_order;
	public $catid_invoice;
	public $catid_contract;
	public $from_qty;
	public $price;
	public $tx_discount;
	public $cost_price;
	public $use_product_cost_price;
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
		$sql .= "fk_cat_order,";
		$sql .= "fk_cat_invoice,";
		$sql .= "fk_cat_contract,";
		$sql .= "from_qty,";
		$sql .= "price,";
		$sql .= "tx_discount,";
		$sql .= "cost_price,";
		$sql .= "use_product_cost_price,";
		$sql .= "fk_user_creation";
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
		$sql .= " ".((int) $this->use_product_cost_price).",";
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
		$sql .= " t.use_product_cost_price,";
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
		$sql .= " t.fk_cat_order,";
		$sql .= " t.fk_cat_invoice,";
		$sql .= " t.fk_cat_contract,";
		$sql .= " t.from_qty,";
		$sql .= " t.price,";
		$sql .= " t.tx_discount,";
		$sql .= " t.cost_price,";
		$sql .= " t.use_product_cost_price,";
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
	 * @return int <0 if KO, >0 if OK
	 */
	public function update($user)
	{
		global $langs;

		if ($this->validatePriceListValues($langs) < 0) {
			return -1;
		}

		$error = 0;
		$oldcopy = new PriceList($this->db);
		$oldcopyloaded = false;
		if (!empty($this->id) && $oldcopy->fetch((int) $this->id) > 0) {
			$oldcopyloaded = true;
			$this->oldcopy = clone $oldcopy;
		}

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
		$sql .= " use_product_cost_price=".((int) $this->use_product_cost_price);
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

		$entities = $this->getPriceEntityCandidates($sourceObject, $product);
		$sourceCategory = $this->getSourceObjectCategoryDefinition($sourceObject);
		$socid = $this->getObjectId($soc);
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

			if ($result) {
				return $this->rejectPriceBelowMinimum($result, $soc);
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
		if ($result) {
			return $this->rejectPriceBelowMinimum($result, $soc);
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
		$this->catid_order = $obj->fk_cat_order;
		$this->catid_invoice = $obj->fk_cat_invoice;
		$this->catid_contract = $obj->fk_cat_contract;
		$this->from_qty = $obj->from_qty;
		$this->price = $obj->price;
		$this->tx_discount = $obj->tx_discount;
		$this->cost_price = $obj->cost_price;
		$this->use_product_cost_price = !empty($obj->use_product_cost_price) ? (int) $obj->use_product_cost_price : 0;
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
		$this->normalizeCostPriceMode();

		$priceFilled = dol_strlen($this->price);
		$discountFilled = dol_strlen($this->tx_discount);
		$costFilled = dol_strlen($this->cost_price) || !empty($this->use_product_cost_price);

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
	 * @return void
	 */
	private function normalizeCostPriceMode()
	{
		$this->use_product_cost_price = !empty($this->use_product_cost_price) ? 1 : 0;
		if (!empty($this->use_product_cost_price)) {
			$this->cost_price = null;
		}
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
					$soc = null;
				}
			}
		}

		return $this->getMinimumPriceViolationForRow($row, $soc);
	}

	/**
	 * Return a minimum price violation for a fetched row.
	 *
	 * @param stdClass    $row Price row
	 * @param object|null $soc Thirdparty context
	 * @return array{current:float,minimum:float}|null
	 */
	public function getMinimumPriceViolationForRow($row, $soc = null)
	{
		$productId = $this->getRowProductId($row);
		if ($productId <= 0) {
			return null;
		}

		$product = $this->fetchProductForMinimumCheck($productId);
		if (!is_object($product)) {
			return null;
		}

		$minimum = (isset($product->price_min) && dol_strlen($product->price_min)) ? (float) price2num($product->price_min) : 0.0;
		if ($minimum <= 0) {
			return null;
		}

		$current = null;
		if (isset($row->price) && dol_strlen($row->price)) {
			$current = (float) price2num($row->price);
		} elseif (isset($row->tx_discount) && dol_strlen($row->tx_discount)) {
			$basePrice = $this->getMinimumCheckBasePrice($product, $soc);
			if ($basePrice === null) {
				return null;
			}
			$current = (float) $basePrice * (1 - ((float) price2num($row->tx_discount) / 100));
		}

		if ($current === null) {
			return null;
		}

		if ((float) price2num($current) < (float) price2num($minimum)) {
			return array(
				'current' => (float) price2num($current),
				'minimum' => (float) price2num($minimum),
			);
		}

		return null;
	}

	/**
	 * Return the effective cost price for a row.
	 *
	 * @param stdClass|PriceList $row Price row
	 * @return float|null
	 */
	public function getEffectiveCostPriceForRow($row)
	{
		if (!empty($row->use_product_cost_price)) {
			$productId = $this->getRowProductId($row);
			if ($productId <= 0) {
				return null;
			}

			return $this->getProductCostPrice($productId);
		}
		if (isset($row->cost_price) && dol_strlen($row->cost_price)) {
			return (float) price2num($row->cost_price);
		}

		return null;
	}

	/**
	 * Return the native product cost price.
	 *
	 * @param int $productId Product id
	 * @return float|null
	 */
	public function getProductCostPrice($productId)
	{
		$product = new Product($this->db);
		if ($productId <= 0 || $product->fetch($productId) <= 0) {
			return null;
		}
		if (!isset($product->cost_price) || !dol_strlen($product->cost_price)) {
			return null;
		}

		return (float) price2num($product->cost_price);
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
		$product = new Product($this->db);
		if ($productId <= 0 || $product->fetch($productId) <= 0) {
			return null;
		}

		return $product;
	}

	/**
	 * Return the base sale price used to test a discount against the minimum price.
	 *
	 * @param Product     $product Product object
	 * @param object|null $soc     Thirdparty context
	 * @return float|null
	 */
	private function getMinimumCheckBasePrice($product, $soc = null)
	{
		if (is_object($soc) && method_exists($product, 'getSellPrice')) {
			try {
				$product->getSellPrice($soc);
			} catch (Throwable $e) {
				dol_syslog(__METHOD__.' '.$e->getMessage(), LOG_DEBUG);
			}
		}
		if (isset($product->price) && dol_strlen($product->price)) {
			return (float) price2num($product->price);
		}

		return null;
	}

	/**
	 * Reject a fetched row when it violates the native product minimum sale price.
	 *
	 * @param stdClass    $row Price row
	 * @param object|null $soc Thirdparty context
	 * @return int|stdClass
	 */
	private function rejectPriceBelowMinimum($row, $soc = null)
	{
		$violation = $this->getMinimumPriceViolationForRow($row, $soc);
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
	 * Return entity candidates used to resolve a price rule.
	 *
	 * The source object entity stays first. The product entity is used as a
	 * fallback for shared products whose price list rows belong to the owner
	 * entity.
	 *
	 * @param ?object $sourceObject Source object
	 * @param Product $product      Product object
	 * @return array<int,int>
	 */
	private function getPriceEntityCandidates($sourceObject, $product)
	{
		$entities = array($this->resolveEntity($sourceObject));
		if (!empty($product->entity)) {
			$entities[] = (int) $product->entity;
		}

		return $this->normalizeIdList($entities);
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
		$sql = "SELECT rowid, fk_product, price, tx_discount, cost_price, use_product_cost_price, from_qty";
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
		$sql .= " l.use_product_cost_price,";
		$sql .= " u.login";
		$sql .= " FROM ".MAIN_DB_PREFIX."pricelist_log as l";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = l.fk_user";
		$sql .= " WHERE l.fk_pricelist = ".((int) $this->id);
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
		$sql .= " from_qty, price, tx_discount, cost_price, use_product_cost_price";
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
		$sql .= ((int) $this->use_product_cost_price);
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
		foreach (array('product_id', 'socid', 'catid', 'catid_propal', 'catid_order', 'catid_invoice', 'catid_contract', 'use_product_cost_price') as $property) {
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
