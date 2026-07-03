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

require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

dol_include_once('/pricelist/class/pricelistcompatibility.class.php');
dol_include_once('/pricelist/lib/pricelist.lib.php');

if (!function_exists('pricelist_is_category_type_available')) {
	/**
	 * Return true when a category type can be used in the current Dolibarr version.
	 *
	 * @param string $categoryType Category type
	 * @return bool
	 */
	function pricelist_is_category_type_available($categoryType)
	{
		return pricelistIsCategoryTypeAvailable($categoryType);
	}
}

if (!function_exists('pricelist_get_category_type_id')) {
	/**
	 * Return the native category type id.
	 *
	 * @param string $categoryType Category type
	 * @return int
	 */
	function pricelist_get_category_type_id($categoryType)
	{
		if ($categoryType === 'propal') {
			return 23;
		}
		if ($categoryType === 'order') {
			return 16;
		}
		if ($categoryType === 'invoice') {
			return 17;
		}
		if ($categoryType === 'contract') {
			return 450022;
		}

		return defined('Categorie::TYPE_CUSTOMER') ? Categorie::TYPE_CUSTOMER : 2;
	}
}

if (!function_exists('pricelist_print_category_cell')) {
	/**
	 * Print a category link cell.
	 *
	 * @param DoliDB $db    Database handler
	 * @param int    $catid Category id
	 * @return void
	 */
	function pricelist_print_category_cell($db, $catid)
	{
		if ($catid > 0) {
			$category = new Categorie($db);
			if ($category->fetch($catid) > 0) {
				print '<td><a href="'.DOL_URL_ROOT.'/categories/viewcat.php?id='.(int) $category->id.'&type='.urlencode($category->type).'" class="classfortooltip">';
				print img_object($category->label, 'category', 'class="classfortooltip"').' '.dol_escape_htmltag($category->label);
				print ' </a></td>';
				return;
			}
		}

		print '<td>-</td>';
	}
}

if (!function_exists('pricelist_get_history_value')) {
	/**
	 * Return display and comparable value for a history row.
	 *
	 * @param stdClass  $row   History row
	 * @param Translate $langs Translation handler
	 * @param DoliDB|null $db  Database handler
	 * @return array{label:string,type:string,value:?float}
	 */
	function pricelist_get_history_value($row, $langs, $db = null)
	{
		$parts = array();
		$type = '';
		$value = null;

		if (dol_strlen($row->price)) {
			$parts[] = $langs->trans('PriceHT').': '.price($row->price);
			$type = 'price';
			$value = (float) price2num($row->price);
		}
		if (dol_strlen($row->tx_discount)) {
			$parts[] = $langs->trans('Discount').': '.price($row->tx_discount).'%';
			if ($type === '') {
				$type = 'discount';
				$value = (float) price2num($row->tx_discount);
			}
		}
		if (dol_strlen($row->cost_price)) {
			$parts[] = $langs->trans('CostPriceHT').': '.price($row->cost_price);
			if ($type === '') {
				$type = 'cost_price';
				$value = (float) price2num($row->cost_price);
			}
		}
		if (!empty($row->use_product_cost_price)) {
			$label = $langs->trans('ProductCostPrice');
			if (is_object($db) && !empty($row->fk_product)) {
				$product = new Product($db);
				if ($product->fetch((int) $row->fk_product) > 0 && dol_strlen($product->cost_price)) {
					$label .= ': '.price($product->cost_price);
					if ($type === '') {
						$type = 'cost_price';
						$value = (float) price2num($product->cost_price);
					}
				}
			}
			$parts[] = $label;
		}

		return array(
			'label' => empty($parts) ? '-' : implode('<br>', $parts),
			'type' => $type,
			'value' => $value,
		);
	}
}

if (!function_exists('pricelist_get_history_tooltip')) {
	/**
	 * Return the HTML tooltip for a price line history.
	 *
	 * @param DoliDB    $db    Database handler
	 * @param PriceList $line  Price line
	 * @param Translate $langs Translation handler
	 * @return string
	 */
	function pricelist_get_history_tooltip($db, $line, $langs)
	{
		$history = $line->getHistory();
		if (!is_array($history) || empty($history)) {
			return $langs->trans('PriceListHistoryEmpty');
		}

		$previousByType = array();
		$diffByRow = array();
		foreach ($history as $row) {
			$valueInfo = pricelist_get_history_value($row, $langs, $db);
			$diffByRow[(int) $row->rowid] = '-';
			if ($valueInfo['type'] !== '' && $valueInfo['value'] !== null && isset($previousByType[$valueInfo['type']]) && (float) $previousByType[$valueInfo['type']] != 0.0) {
				$diff = (((float) $valueInfo['value'] - (float) $previousByType[$valueInfo['type']]) / (float) $previousByType[$valueInfo['type']]) * 100;
				$diffByRow[(int) $row->rowid] = price($diff).'%';
			}
			if ($valueInfo['type'] !== '' && $valueInfo['value'] !== null) {
				$previousByType[$valueInfo['type']] = (float) $valueInfo['value'];
			}
		}

		$html = '<table class=\'nobordernopadding centpercent\'>';
		$html .= '<tr class=\'liste_titre\'><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('PriceListHistoryNewValue').'</td><td>'.$langs->trans('PriceListHistoryDiff').'</td><td>'.$langs->trans('User').'</td></tr>';
		for ($i = count($history) - 1; $i >= 0; $i--) {
			$row = $history[$i];
			$valueInfo = pricelist_get_history_value($row, $langs, $db);
			$date = dol_print_date($db->jdate($row->datec), 'dayhour');
			$userLabel = !empty($row->login) ? $row->login : '-';
			$html .= '<tr class=\'oddeven\'>';
			$html .= '<td>'.$date.'</td>';
			$html .= '<td>'.$valueInfo['label'].'</td>';
			$html .= '<td class=\'right\'>'.$diffByRow[(int) $row->rowid].'</td>';
			$html .= '<td>'.dol_escape_htmltag($userLabel).'</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';

		return $html;
	}
}

if (!function_exists('pricelist_print_status_message')) {
	/**
	 * Print a simple native-looking message.
	 *
	 * @param string $message Message
	 * @param string $class   CSS class
	 * @return void
	 */
	function pricelist_print_status_message($message, $class)
	{
		print '<div class="'.dol_escape_htmltag($class).'">'.$message.'</div>';
	}
}

if (!function_exists('pricelist_get_minimum_price_warnings')) {
	/**
	 * Build minimum price warnings for displayed rows.
	 *
	 * @param DoliDB     $db    Database handler
	 * @param array<int,PriceList> $list Price list rows
	 * @param Translate  $langs Translation handler
	 * @return array<int,string>
	 */
	function pricelist_get_minimum_price_warnings($db, $list, $langs)
	{
		$warnings = array();
		foreach ($list as $line) {
			$soc = null;
			if (!empty($line->socid)) {
				$soc = new Societe($db);
				if ($soc->fetch((int) $line->socid) <= 0) {
					$soc = null;
				}
			}
			$lineRow = new stdClass();
			$lineRow->fk_product = (int) $line->product_id;
			$lineRow->price = $line->price;
			$lineRow->tx_discount = $line->tx_discount;
			$violation = $line->getMinimumPriceViolationForRow($lineRow, $soc);
			if (!is_array($violation)) {
				continue;
			}

			$productLabel = '#'.(int) $line->product_id;
			$product = new Product($db);
			if ($product->fetch((int) $line->product_id) > 0) {
				$productLabel = $product->ref;
			}
			$warnings[] = $langs->trans(
				'PriceListMinimumPriceWarningLine',
				dol_escape_htmltag($productLabel),
				price($line->from_qty),
				price($violation['current']),
				price($violation['minimum'])
			);
		}

		return $warnings;
	}
}

$pricelisttypeparam = (isset($type) && $type ? '&type='.urlencode($type) : '');
$currentProductTypeForRights = ($object->element == 'product' && isset($object->type)) ? (int) $object->type : null;
$canCreatePriceList = pricelistCanWritePrices($user, $currentProductTypeForRights);
$canDeletePriceList = $canCreatePriceList;
$showPropalCategories = pricelistIsPropalCategoryAvailable();
$showOrderInvoiceCategories = pricelistIsOrderInvoiceCategoryAvailable();
$showContractCategories = pricelistIsContractCategoryAvailable();
$linesid = is_array($linesid) ? $linesid : array();
$pricelistToken = newToken();

if ($list !== null) {
	$priorityMessage = pricelistGetDocumentCategoryPriority() ? $langs->trans('PriceListPriorityDocumentFirstBanner') : $langs->trans('PriceListPriorityCustomerFirstBanner');
	pricelist_print_status_message($priorityMessage, 'info');

	$minimumWarnings = pricelist_get_minimum_price_warnings($db, $list, $langs);
	if (!empty($minimumWarnings)) {
		pricelist_print_status_message($langs->trans('PriceListMinimumPriceWarning').'<br>'.implode('<br>', $minimumWarnings), 'warning');
	}

	print '<form id="pricelistform" action="'.$_SERVER['PHP_SELF'].'" method="get">';
	print '<input type="hidden" name="id" value="'.(int) $object->id.'">';
	if (!empty($type)) {
		print '<input type="hidden" name="type" value="'.dol_escape_htmltag($type).'">';
	}
	print '<input type="hidden" name="token" value="'.$pricelistToken.'">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';

	$colspan = 0;
	if ($object->element == 'product') {
		print '<td>'.$langs->trans("ThirdParty").'</td>';
		$colspan++;

		if (isModEnabled('categorie')) {
			print '<td>'.$langs->trans("CustomersCategoriesShort").'</td>';
			$colspan++;
			if ($showPropalCategories) {
				print '<td>'.$langs->trans("PropalCategory").'</td>';
				$colspan++;
			}
			if ($showOrderInvoiceCategories) {
				print '<td>'.$langs->trans("OrderCategory").'</td>';
				print '<td>'.$langs->trans("InvoiceCategory").'</td>';
				$colspan += 2;
			}
			if ($showContractCategories) {
				print '<td>'.$langs->trans("ContractCategory").'</td>';
				$colspan++;
			}
		}
	} else {
		print '<td>'.$langs->trans("ProductsOrServices").'</td>';
		$colspan++;
	}

	print '<td class="right">'.$langs->trans("FromQty").'</td>';
	print '<td class="right">'.$langs->trans("PriceHT").'</td>';
	print '<td class="right">'.$langs->trans("Discount").'</td>';
	print '<td class="right">'.$langs->trans("CostPriceHT").'</td>';
	$colspan += 4;

	if (getDolGlobalInt('PRICELIST_SHOW_PRICES_TTC', 0) > 0) {
		print '<td class="right">'.$langs->trans("PriceTTC").'</td>';
		$colspan++;
	}

	print '<td class="right">'.$langs->trans("AddedBy").'</td>';
	print '<td class="center">'.$langs->trans("PriceListHistory").'</td>';
	$colspan += 2;

	if ($canCreatePriceList || $canDeletePriceList) {
		print '<td class="right">';
		if ($canDeletePriceList) {
			print '<a href="#" id="checkall">'.$langs->trans("All").'</a> / <a href="#" id="checknone">'.$langs->trans("None").'</a>';
		}
		print '</td>';
		$colspan++;
	}

	print '</tr>';

	$userstatic = new User($db);
	if (count($list) == 0) {
		print '<tr class="oddeven"><td colspan="'.((int) $colspan).'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}

	foreach ($list as $obj) {
		print '<tr class="oddeven">';

		if ($object->element == 'product') {
			if ($obj->socid > 0) {
				$societe = new Societe($db);
				$societe->fetch($obj->socid);
				print '<td>'.$societe->getNomUrl(1).'</td>';
			} else {
				print '<td>-</td>';
			}

			if (isModEnabled('categorie')) {
				pricelist_print_category_cell($db, $obj->catid);
				if ($showPropalCategories) {
					pricelist_print_category_cell($db, $obj->catid_propal);
				}
				if ($showOrderInvoiceCategories) {
					pricelist_print_category_cell($db, $obj->catid_order);
					pricelist_print_category_cell($db, $obj->catid_invoice);
				}
				if ($showContractCategories) {
					pricelist_print_category_cell($db, $obj->catid_contract);
				}
			}

			$product = $object;
		} else {
			$product = new Product($db);
			$product->fetch($obj->product_id);

			print '<td>'.$product->getNomUrl(1).' - '.dol_escape_htmltag($product->label).'</td>';
		}

		print '<td class="right">'.price($obj->from_qty).'</td>';
		print '<td class="right">'.(dol_strlen($obj->price) ? price($obj->price) : '-').'</td>';
		print '<td class="right">'.(dol_strlen($obj->tx_discount) ? price($obj->tx_discount) : '-').'</td>';
		$effectiveCostPrice = $obj->getEffectiveCostPriceForRow($obj);
		if (!empty($obj->use_product_cost_price)) {
			print '<td class="right">'.($effectiveCostPrice !== null ? $langs->trans('ProductCostPrice').': '.price($effectiveCostPrice) : $langs->trans('ProductCostPrice')).'</td>';
		} else {
			print '<td class="right">'.($effectiveCostPrice !== null ? price($effectiveCostPrice) : '-').'</td>';
		}

		if (getDolGlobalInt('PRICELIST_SHOW_PRICES_TTC', 0) > 0) {
			$pu = dol_strlen($obj->price) ? $obj->price : $product->price;
			$discountForTtc = dol_strlen($obj->tx_discount) ? $obj->tx_discount : 0;
			$priceTTC = calcul_price_total(1, $pu, $discountForTtc, $product->tva_tx, 0, 0, 0, 'HT', 0, $product->type);

			print '<td class="right">'.price($priceTTC[2]).'</td>';
		}

		$userstatic->fetch($obj->user_creation_id);
		print '<td class="right">'.$userstatic->getLoginUrl(1).'</td>';

		$tooltip = pricelist_get_history_tooltip($db, $obj, $langs);
		print '<td class="center">'.$form->textwithpicto('', $tooltip, 1, 'info', '', 1).'</td>';

		$rowCanWrite = pricelistCanWritePrices($user, is_object($product) && isset($product->type) ? (int) $product->type : null);
		if ($canCreatePriceList || $canDeletePriceList) {
			print '<td class="right nowrap">';
			if ($rowCanWrite) {
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=edit_price&id='.(int) $object->id.$pricelisttypeparam.'&lineid='.(int) $obj->id.'&token='.$pricelistToken.'">';
				print img_edit($langs->trans('EditPriceList'));
				print '</a> ';
			}
			if ($rowCanWrite) {
				print '<input class="flat checkfordelete" type="checkbox" name="linesid[]" value="'.(int) $obj->id.'" '.(in_array($obj->id, $linesid) ? 'checked' : '').'>';
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete_price&id='.(int) $object->id.$pricelisttypeparam.'&lineid='.(int) $obj->id.'&token='.$pricelistToken.'">';
				print img_delete();
				print '</a>';
			}
			print '</td>';
		}

		print '</tr>';
	}

	print '</table><br></form>';
}

if ($action != 'add' && $action != 'edit_price') {
	print '<div class="tabsAction">';

	if ($canCreatePriceList) {
		print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.(int) $object->id.$pricelisttypeparam.'&action=add&token='.$pricelistToken.'">'.$langs->trans('AddPriceList').'</a></div>';
	}

	if ($canDeletePriceList) {
		print '<div class="inline-block divButAction"><a id="btnDeleteSelected" class="butActionRefused" href="#">'.$langs->trans('DeletePriceList').'</a></div>';
	}

	print '</div>';
}

if ($action == 'add' || $action == 'edit_price') {
	if (!$canCreatePriceList) {
		accessforbidden();
	}

	$isEditPriceList = ($action == 'edit_price');
	$title = $isEditPriceList ? $langs->trans("EditPriceList") : $langs->trans("NewPriceOffer");
	$modalId = 'pricelist-line-dialog';
	$useModalDialog = !empty($conf->use_javascript_ajax);
	$cleanUrl = pricelist_get_redirect_url($object, isset($type) ? $type : '');
	if ($useModalDialog) {
		print '<div id="'.dol_escape_htmltag($modalId).'" title="'.dol_escape_htmltag($title).'" style="display:none">';
	} else {
		print_fiche_titre($title, '', '');
	}

	$formProduct = null;
	$formProductId = pricelist_get_requested_product_id($object, $productid);
	if ($object->element == 'product') {
		$formProduct = $object;
	} elseif ($formProductId > 0) {
		$formProduct = new Product($db);
		if ($formProduct->fetch($formProductId) <= 0) {
			$formProduct = null;
		}
	}

	if (getDolGlobalInt('PRICELIST_SHOW_PRICES_TTC', 0) > 0 && is_object($formProduct) && dol_strlen($price) && !dol_strlen($price_ttc)) {
		$formPriceTtc = calcul_price_total(1, $price, 0, $formProduct->tva_tx, 0, 0, 0, 'HT', 0, $formProduct->type);
		if (is_array($formPriceTtc) && isset($formPriceTtc[2])) {
			$price_ttc = $formPriceTtc[2];
		}
	}

	$formTvaTx = is_object($formProduct) ? $formProduct->tva_tx : '';

	print '<form id="pricelist-line-form" action="'.$_SERVER["PHP_SELF"].'?id='.(int) $object->id.$pricelisttypeparam.'" method="post" data-tva-tx="'.dol_escape_htmltag($formTvaTx).'">';
	print '<input type="hidden" name="token" value="'.$pricelistToken.'">';
	print '<input type="hidden" name="action" value="'.($isEditPriceList ? 'update_confirm' : 'add_confirm').'">';
	print '<input type="hidden" name="price_input_mode" value="'.dol_escape_htmltag($price_input_mode).'">';
	if ($isEditPriceList) {
		print '<input type="hidden" name="lineid" value="'.((int) $lineid).'">';
	}
	if (!empty($type)) {
		print '<input type="hidden" name="type" value="'.dol_escape_htmltag($type).'">';
	}

	print '<table class="border centpercent">';

	if ($object->element == 'product') {
		print '<tr>';
		print '<td>'.$langs->trans('ThirdParty').'</td>';
		print '<td colspan="2">';
		print $form->select_company($socid, 'socid', 's.client = 1 OR s.client = 2 OR s.client = 3', 1);
		print ajax_combobox('socid');
		print '</td>';
		print '</tr>';

		if (isModEnabled('categorie')) {
			print '<tr>';
			print '<td>'.$langs->trans('CustomersCategoriesShort').'</td>';
			print '<td colspan="2">'.$form->select_all_categories(pricelist_get_category_type_id('customer'), $catid, 'catid').ajax_combobox('catid').'</td>';
			print '</tr>';

			if ($showPropalCategories) {
				print '<tr>';
				print '<td>'.$langs->trans('PropalCategory').'</td>';
				print '<td colspan="2">'.$form->select_all_categories(pricelist_get_category_type_id('propal'), $catid_propal, 'catid_propal').ajax_combobox('catid_propal').'</td>';
				print '</tr>';
			}

			if ($showOrderInvoiceCategories) {
				print '<tr>';
				print '<td>'.$langs->trans('OrderCategory').'</td>';
				print '<td colspan="2">'.$form->select_all_categories(pricelist_get_category_type_id('order'), $catid_order, 'catid_order').ajax_combobox('catid_order').'</td>';
				print '</tr>';

				print '<tr>';
				print '<td>'.$langs->trans('InvoiceCategory').'</td>';
				print '<td colspan="2">'.$form->select_all_categories(pricelist_get_category_type_id('invoice'), $catid_invoice, 'catid_invoice').ajax_combobox('catid_invoice').'</td>';
				print '</tr>';
			}

			if ($showContractCategories) {
				print '<tr>';
				print '<td>'.$langs->trans('ContractCategory').'</td>';
				print '<td colspan="2">'.$form->select_all_categories(pricelist_get_category_type_id('contract'), $catid_contract, 'catid_contract').ajax_combobox('catid_contract').'</td>';
				print '</tr>';
			}
		}
	} else {
		print '<tr>';
		print '<td class="fieldrequired">'.$langs->trans('ProductOrService').'</td><td>';
		$limit_size = version_compare(DOL_VERSION, '21.0.0') >= 0 ? getDolGlobalInt('PRODUIT_LIMIT_SIZE') : $conf->product->limit_size;
		$form->select_produits($productid, 'productid', '', $limit_size, 0, 1, 2, '', 1);
		print ajax_combobox('productid');
		print '</td></tr>';
	}

	print '<tr>';
	print '<td class="fieldrequired">'.$langs->trans('FromQtyLong').'</td>';
	print '<td colspan="2">';
	if ($isEditPriceList) {
		print '<input type="hidden" name="qty" value="'.dol_escape_htmltag($qty).'">';
		print '<span class="opacitymedium">'.price($qty).'</span>';
	} else {
		print '<input type="text" name="qty" value="'.dol_escape_htmltag($qty).'">';
	}
	print '</td>';
	print '</tr>';

	print '<tr class="fieldrequired">';
	print '<td>'.$langs->trans('PriceOrDiscountRate').'</td>';
	print '<td colspan="2">';
	print '<input class="flat maxwidth100" type="text" name="price" value="'.(dol_strlen($price) ? dol_escape_htmltag($price) : '').'" placeholder="'.$langs->trans('PriceHT').'">';
	if (getDolGlobalInt('PRICELIST_SHOW_PRICES_TTC', 0) > 0) {
		print '<input class="flat maxwidth100" type="text" name="price_ttc" value="'.(dol_strlen($price_ttc) ? dol_escape_htmltag($price_ttc) : '').'" placeholder="'.$langs->trans('PriceTTC').'">';
	}
	print '<input class="flat maxwidth100" type="text" name="tx_discount" value="'.(dol_strlen($tx_discount) ? dol_escape_htmltag($tx_discount) : '').'" placeholder="'.$langs->trans('Discount').'">';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>'.$langs->trans('UseProductCostPrice').'</td>';
	print '<td colspan="2">'.$form->selectyesno('use_product_cost_price', (int) $use_product_cost_price, 1).ajax_combobox('use_product_cost_price').'</td>';
	print '</tr>';

	print '<tr>';
	print '<td>'.$langs->trans('CostPriceHT').'</td>';
	print '<td colspan="2"><input class="flat maxwidth100" type="text" name="cost_price" value="'.(dol_strlen($cost_price) ? dol_escape_htmltag($cost_price) : '').'" placeholder="'.$langs->trans('CostPrice').'"></td>';
	print '</tr>';

	print '</table>';

	print '<div class="center"><br><input type="submit" class="button" value="'.$langs->trans("Save").'">&nbsp;';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'"></div>';

	print '</form>';

	if ($useModalDialog) {
		print '</div>';
		print '<script nonce="'.getNonce().'">';
		print 'jQuery(function($) {';
		print 'var $dialog = $("#'.dol_escape_js($modalId).'");';
		print 'if (!$dialog.length) { return; }';
		print 'if (!$.ui || !$.ui.dialog) { $dialog.show(); return; }';
		print 'var cleanUrl = "'.dol_escape_js($cleanUrl).'";';
		print 'var submitted = false;';
		print '$dialog.find("form").on("submit", function() { submitted = true; });';
		print 'var dialogWidth = Math.min(Math.max($(window).width() - 40, 320), 940);';
		print '$dialog.dialog({autoOpen: true, modal: true, width: dialogWidth, close: function() { if (!submitted) { window.location.href = cleanUrl; } }});';
		print '});';
		print '</script>';
	}
}
