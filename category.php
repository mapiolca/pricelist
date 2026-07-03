<?php
/* Copyright (C) 2024 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * Copyright (C) 2016-2019 Garcia MICHEL <garcia@soamichel.fr>
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

require_once 'require.php';

require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/categories.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/pricelist/class/pricelist.class.php');
dol_include_once('/pricelist/class/pricelistcompatibility.class.php');
dol_include_once('/pricelist/lib/pricelist.lib.php');

$id=GETPOST('id');
$label=GETPOST('label', 'alpha');
$type = GETPOST('type', 'aZ09');
$action = GETPOST('action');
$confirm = GETPOST('confirm');
$productid = GETPOST('productid');
$socid = GETPOST('socid');
$catid = GETPOST('catid');
$catid_propal = GETPOST('catid_propal');
$catid_order = GETPOST('catid_order');
$catid_invoice = GETPOST('catid_invoice');
$catid_contract = GETPOST('catid_contract');
$qty = GETPOST('qty');
$price = GETPOST('price');
$price_ttc = GETPOST('price_ttc');
$price_input_mode = GETPOST('price_input_mode', 'aZ09');
$tx_discount = GETPOST('tx_discount');
$cost_price = GETPOST('cost_price'); // Retrieve cost price field // Récupère le prix de revient
$use_product_cost_price = GETPOSTINT('use_product_cost_price');
$lineid = GETPOST('lineid');
$linesid = GETPOST('linesid', 'array');

$pricelist = new PriceList($db);
$object = new Categorie($db);
$object->fetch($id, $label);
if (!pricelistCanReadPrices($user)) {
	accessforbidden();
}

$categorytypes = array(
	'customer' => array('id' => (defined('Categorie::TYPE_CUSTOMER') ? Categorie::TYPE_CUSTOMER : 2), 'title' => 'CustomersCategoryShort', 'root' => 'customer'),
);
if (pricelistIsPropalCategoryAvailable()) {
	$categorytypes['propal'] = array('id' => 23, 'title' => 'PropalCategory', 'root' => 'propal');
}
if (pricelistIsOrderInvoiceCategoryAvailable()) {
	$categorytypes['order'] = array('id' => 16, 'title' => 'OrderCategory', 'root' => 'order');
	$categorytypes['invoice'] = array('id' => 17, 'title' => 'InvoiceCategory', 'root' => 'invoice');
}
if (pricelistIsContractCategoryAvailable()) {
	$categorytypes['contract'] = array('id' => 450022, 'title' => 'ContractCategory', 'root' => 'contract');
}
if (empty($type)) {
	$type = 'customer';
}
if (empty($categorytypes[$type])) {
	accessforbidden();
}
$objecttypeid = -1;
if (isset($object->type) && is_numeric($object->type)) {
	$objecttypeid = (int) $object->type;
} elseif (isset($object->type) && isset($object->MAP_ID[$object->type])) {
	$objecttypeid = (int) $object->MAP_ID[$object->type];
}
if (!empty($object->type) && $objecttypeid !== (int) $categorytypes[$type]['id']) {
	accessforbidden();
}

$langs->load("categories");
$langs->load("pricelist@pricelist");

/*
 * Actions
 */
include dol_buildpath('/pricelist/includes/actions_addupdatedelete.inc.php');

/*
 * View
 */

$form = new Form($db);

$arrayofjs = array();
if (pricelistCanWritePrices($user)) {
    $arrayofjs[] = '/pricelist/js/delete.js';
}
$arrayofjs[] = '/pricelist/js/pricelist_ttc.js';

llxHeader('', $langs->trans('Categories'), '', '', '', '', $arrayofjs);

$title = $langs->trans($categorytypes[$type]['title']);

$head = categories_prepare_head($object, $categorytypes[$type]['root']);
dol_fiche_head($head, 'pricelist', $title, 0, 'category');

$object->next_prev_filter = ' type = '.$object->type;
$object->ref = $object->label;
$morehtmlref = '<br><div class="refidno"><a href="'.DOL_URL_ROOT.'/categories/index.php?leftmenu=cat&type='.$categorytypes[$type]['root'].'">'.$langs->trans("Root").'</a> >> ';
$ways = $object->print_all_ways(" &gt;&gt; ", '', 1);
foreach ($ways as $way) {
    $morehtmlref .= $way."<br>\n";
}
$morehtmlref .= '</div>';

dol_banner_tab($object, 'label', '', ($user->socid ? 0 : 1), 'label', 'label', $morehtmlref, '&type='.$categorytypes[$type]['root']);

dol_fiche_end();

$list = $pricelist->search(0, 0, $id, $type);
include dol_buildpath('/pricelist/includes/view.inc.php');

/*
 * Confirmation
 */
include dol_buildpath('/pricelist/includes/confirms_delete.inc.php');

llxFooter();
