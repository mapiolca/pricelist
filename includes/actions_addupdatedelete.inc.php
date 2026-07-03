<?php
/* Copyright (C) 2024 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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

dol_include_once('/pricelist/lib/pricelist.lib.php');

if (!function_exists('pricelist_get_redirect_url')) {
	/**
	 * Return the current price list tab URL.
	 *
	 * @param object $object Current object
	 * @param string $type   Category type
	 * @return string
	 */
	function pricelist_get_redirect_url($object, $type)
	{
		return $_SERVER['PHP_SELF'].'?id='.(int) $object->id.(dol_strlen($type) ? '&type='.urlencode($type) : '');
	}
}

if (!function_exists('pricelist_get_requested_product_id')) {
	/**
	 * Return the product id for the submitted row.
	 *
	 * @param object $object    Current object
	 * @param int    $productid Submitted product id
	 * @return int
	 */
	function pricelist_get_requested_product_id($object, $productid)
	{
		return ($object->element == 'product') ? (int) $object->id : (int) $productid;
	}
}

if (!function_exists('pricelist_get_product_type_for_rights')) {
	/**
	 * Return a product type for rights checks.
	 *
	 * @param DoliDB $db        Database handler
	 * @param int    $productid Product id
	 * @return int|null
	 */
	function pricelist_get_product_type_for_rights($db, $productid)
	{
		if ($productid <= 0) {
			return null;
		}

		$product = new Product($db);
		if ($product->fetch($productid) <= 0) {
			return null;
		}

		return isset($product->type) ? (int) $product->type : 0;
	}
}

if (!function_exists('pricelist_check_write_right_for_product')) {
	/**
	 * Check write rights for the submitted product/service.
	 *
	 * @param DoliDB $db        Database handler
	 * @param User   $user      User
	 * @param int    $productid Product id
	 * @return bool
	 */
	function pricelist_check_write_right_for_product($db, $user, $productid)
	{
		$productType = pricelist_get_product_type_for_rights($db, $productid);
		return pricelistCanWritePrices($user, $productType);
	}
}

if (!function_exists('pricelist_normalize_price_inputs')) {
	/**
	 * Normalize HT/TTC input before validation and storage.
	 *
	 * @param DoliDB $db             Database handler
	 * @param int    $productid      Product id
	 * @param mixed  $price          HT price, updated by reference
	 * @param mixed  $priceTtc       TTC price, updated by reference
	 * @param string $priceInputMode Last edited price input mode
	 * @return int <0 if KO, >=0 if OK
	 */
	function pricelist_normalize_price_inputs($db, $productid, &$price, &$priceTtc, $priceInputMode)
	{
		if (getDolGlobalInt('PRICELIST_SHOW_PRICES_TTC', 0) <= 0) {
			return 0;
		}
		if (!dol_strlen($priceTtc) && !dol_strlen($price)) {
			return 0;
		}
		if ($priceInputMode === 'TTC' && !dol_strlen($priceTtc)) {
			return 0;
		}
		if ($priceInputMode !== 'TTC' && (dol_strlen($price) || !dol_strlen($priceTtc))) {
			return 0;
		}

		$product = new Product($db);
		if ($productid <= 0 || $product->fetch($productid) <= 0) {
			return -1;
		}

		$totals = calcul_price_total(1, price2num($priceTtc), 0, $product->tva_tx, 0, 0, 0, 'TTC', 0, $product->type);
		if (is_array($totals) && isset($totals[0])) {
			$price = $totals[0];
		}

		return 1;
	}
}

if (!function_exists('pricelist_apply_request_to_line')) {
	/**
	 * Apply submitted values and page context to a price list line.
	 *
	 * @param PriceList $line          Price list line
	 * @param object    $object        Current object
	 * @param string    $type          Category type
	 * @param int       $productid     Product id
	 * @param int       $socid         Thirdparty id
	 * @param int       $catid         Customer category id
	 * @param int       $catidPropal   Proposal category id
	 * @param int       $catidOrder    Order category id
	 * @param int       $catidInvoice  Invoice category id
	 * @param int       $catidContract Contract category id
	 * @param mixed     $qty           Quantity
	 * @param mixed     $price         HT price
	 * @param mixed     $txDiscount    Discount percent
	 * @param mixed     $costPrice     Cost price
	 * @param int       $useProductCostPrice Use product native cost price
	 * @return void
	 */
	function pricelist_apply_request_to_line($line, $object, $type, $productid, $socid, $catid, $catidPropal, $catidOrder, $catidInvoice, $catidContract, $qty, $price, $txDiscount, $costPrice, $useProductCostPrice)
	{
		$existingCatidPropal = !empty($line->catid_propal) ? (int) $line->catid_propal : null;
		$existingCatidOrder = !empty($line->catid_order) ? (int) $line->catid_order : null;
		$existingCatidInvoice = !empty($line->catid_invoice) ? (int) $line->catid_invoice : null;
		$existingCatidContract = !empty($line->catid_contract) ? (int) $line->catid_contract : null;

		$line->product_id = pricelist_get_requested_product_id($object, $productid);
		$line->socid = null;
		$line->catid = null;
		$line->catid_propal = null;
		$line->catid_order = null;
		$line->catid_invoice = null;
		$line->catid_contract = null;

		if ($object->element == 'societe') {
			$line->socid = (int) $object->id;
		} elseif ($object->element == 'category') {
			if (empty($type) || $type == 'customer') {
				$line->catid = (int) $object->id;
			} else {
				$line->catid_propal = $existingCatidPropal;
				$line->catid_order = $existingCatidOrder;
				$line->catid_invoice = $existingCatidInvoice;
				$line->catid_contract = $existingCatidContract;
				if ($type == 'propal') {
					$line->catid_propal = (int) $object->id;
				} elseif ($type == 'order') {
					$line->catid_order = (int) $object->id;
				} elseif ($type == 'invoice') {
					$line->catid_invoice = (int) $object->id;
				} elseif ($type == 'contract') {
					$line->catid_contract = (int) $object->id;
				}
			}
		} else {
			$line->socid = $socid > 0 ? $socid : null;
			$line->catid = $catid > 0 ? $catid : null;
			$line->catid_propal = $catidPropal > 0 ? $catidPropal : null;
			$line->catid_order = $catidOrder > 0 ? $catidOrder : null;
			$line->catid_invoice = $catidInvoice > 0 ? $catidInvoice : null;
			$line->catid_contract = $catidContract > 0 ? $catidContract : null;
		}

		$line->from_qty = $qty;
		$line->price = $price;
		$line->tx_discount = $txDiscount;
		$line->use_product_cost_price = !empty($useProductCostPrice) ? 1 : 0;
		$line->cost_price = !empty($line->use_product_cost_price) ? null : $costPrice;
	}
}

$editpricelist = null;
$lineid = (int) $lineid;
$productid = (int) $productid;
$socid = isset($socid) ? (int) $socid : 0;
$catid = isset($catid) ? (int) $catid : 0;
$catid_propal = isset($catid_propal) ? (int) $catid_propal : 0;
$catid_order = isset($catid_order) ? (int) $catid_order : 0;
$catid_invoice = isset($catid_invoice) ? (int) $catid_invoice : 0;
$catid_contract = isset($catid_contract) ? (int) $catid_contract : 0;
$price_input_mode = isset($price_input_mode) ? $price_input_mode : '';
$price_ttc = isset($price_ttc) ? $price_ttc : '';
$use_product_cost_price = isset($use_product_cost_price) ? (int) $use_product_cost_price : 0;

if (in_array($action, array('add_confirm', 'update_confirm')) && GETPOST('cancel')) {
	header('Location: '.pricelist_get_redirect_url($object, isset($type) ? $type : ''));
	exit;
}

if ($action == 'add_confirm') {
	$submittedProductId = pricelist_get_requested_product_id($object, $productid);
	if (!pricelist_check_write_right_for_product($db, $user, $submittedProductId)) {
		accessforbidden();
	} elseif (pricelist_normalize_price_inputs($db, $submittedProductId, $price, $price_ttc, $price_input_mode) < 0) {
		setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
	} elseif (empty($qty)) {
		setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
	} elseif ($object->element != 'product' && empty($productid)) {
		setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
	} else {
		pricelist_apply_request_to_line($pricelist, $object, isset($type) ? $type : '', $productid, $socid, $catid, $catid_propal, $catid_order, $catid_invoice, $catid_contract, $qty, $price, $tx_discount, $cost_price, $use_product_cost_price);

		$res = $pricelist->create($user);
		if ($res < 0) {
			setEventMessages($pricelist->error, $pricelist->errors, 'errors');
		} else {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.pricelist_get_redirect_url($object, isset($type) ? $type : ''));
			exit;
		}
	}

	$action = 'add';
}

if ($action == 'update_confirm') {
	$editpricelist = new PriceList($db);
	$fetchres = $lineid > 0 ? $editpricelist->fetch($lineid) : 0;
	if ($fetchres <= 0) {
		setEventMessage($langs->trans('ErrorRecordNotFound'), 'errors');
		$action = 'edit_price';
	} else {
		$qty = $editpricelist->from_qty;
		$submittedProductId = pricelist_get_requested_product_id($object, $productid);
		if (!pricelist_check_write_right_for_product($db, $user, $submittedProductId)) {
			accessforbidden();
		} elseif (pricelist_normalize_price_inputs($db, $submittedProductId, $price, $price_ttc, $price_input_mode) < 0) {
			setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
			$action = 'edit_price';
		} elseif (empty($qty)) {
			setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
			$action = 'edit_price';
		} elseif ($object->element != 'product' && empty($productid)) {
			setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
			$action = 'edit_price';
		} else {
			pricelist_apply_request_to_line($editpricelist, $object, isset($type) ? $type : '', $productid, $socid, $catid, $catid_propal, $catid_order, $catid_invoice, $catid_contract, $qty, $price, $tx_discount, $cost_price, $use_product_cost_price);
			$res = $editpricelist->update($user);
			if ($res < 0) {
				setEventMessages($editpricelist->error, $editpricelist->errors, 'errors');
				$action = 'edit_price';
			} else {
				header('Location: '.pricelist_get_redirect_url($object, isset($type) ? $type : ''));
				exit;
			}
		}
	}
}

if ($action == 'confirm_delete_price' && $confirm == 'yes') {
	if ($lineid > 0 && $pricelist->fetch($lineid) > 0) {
		if (!pricelist_check_write_right_for_product($db, $user, (int) $pricelist->product_id)) {
			accessforbidden();
		}
		$res = $pricelist->delete($user);
		if ($res < 0) {
			setEventMessages($pricelist->error, $pricelist->errors, 'errors');
		}
	}

	header('Location: '.pricelist_get_redirect_url($object, isset($type) ? $type : ''));
	exit;
}

if ($action == 'confirm_delete_prices' && $confirm == 'yes') {
	foreach ($linesid as $lineid) {
		$lineid = (int) $lineid;
		if ($lineid > 0 && $pricelist->fetch($lineid) > 0) {
			if (!pricelist_check_write_right_for_product($db, $user, (int) $pricelist->product_id)) {
				accessforbidden();
			}
			$res = $pricelist->delete($user);
			if ($res < 0) {
				setEventMessages($pricelist->error, $pricelist->errors, 'errors');
			}
		}
	}

	header('Location: '.pricelist_get_redirect_url($object, isset($type) ? $type : ''));
	exit;
}

if ($action == 'edit_price') {
	if (!is_object($editpricelist)) {
		$editpricelist = new PriceList($db);
		if ($lineid <= 0 || $editpricelist->fetch($lineid) <= 0) {
			setEventMessage($langs->trans('ErrorRecordNotFound'), 'errors');
			$action = '';
		}
	}

	if ($action == 'edit_price' && is_object($editpricelist)) {
		$productid = (int) $editpricelist->product_id;
		$socid = (int) $editpricelist->socid;
		$catid = (int) $editpricelist->catid;
		$catid_propal = (int) $editpricelist->catid_propal;
		$catid_order = (int) $editpricelist->catid_order;
		$catid_invoice = (int) $editpricelist->catid_invoice;
		$catid_contract = (int) $editpricelist->catid_contract;
		$qty = $editpricelist->from_qty;
		$price = $editpricelist->price;
		$tx_discount = $editpricelist->tx_discount;
		$cost_price = $editpricelist->cost_price;
		$use_product_cost_price = (int) $editpricelist->use_product_cost_price;
	}
}
