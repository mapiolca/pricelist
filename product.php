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

require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/pricelist/class/pricelist.class.php');
dol_include_once('/pricelist/lib/pricelist.lib.php');

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alphanohtml');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'aZ09');
$socid = GETPOSTINT('socid');
$catid = GETPOSTINT('catid');
$catid_propal = GETPOSTINT('catid_propal');
$catid_order = GETPOSTINT('catid_order');
$catid_invoice = GETPOSTINT('catid_invoice');
$catid_contract = GETPOSTINT('catid_contract');
$qty = GETPOST('qty', 'alphanohtml');
$price = GETPOST('price', 'alphanohtml');
$price_ttc = GETPOST('price_ttc', 'alphanohtml');
$price_input_mode = GETPOST('price_input_mode', 'aZ09');
$tx_discount = GETPOST('tx_discount', 'alphanohtml');
$cost_price = GETPOST('cost_price', 'alphanohtml'); // Retrieve cost price field // Récupère le prix de revient
$cost_price_source = GETPOSTISSET('cost_price_source') ? GETPOST('cost_price_source', 'aZ09') : (GETPOSTINT('use_product_cost_price') ? 'product' : 'custom');
$lineid = GETPOSTINT('lineid');
$linesid = GETPOST('linesid', 'array');

// Price list administration is restricted to internal users.
if ($user->socid) {
	accessforbidden();
}

$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');

$result = restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);

$object = new Product($db);
$res = $object->fetch($id, $ref);
if ($res <= 0) {
    accessforbidden($langs->trans('ErrorRecordNotFound'));
}
if (!(getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') > 0 ? ((int) $object->type === 1 ? $user->hasRight('service', 'service_advance', 'read_prices') : $user->hasRight('product', 'product_advance', 'read_prices')) : ((int) $object->type === 1 ? $user->hasRight('service', 'read') : $user->hasRight('product', 'read')))) {
    accessforbidden();
}

if (!in_array((int) $object->entity, array_map('intval', explode(',', getEntity('product'))), true)) {
	accessforbidden();
}

$pricelist = new PriceList($db);

/*
 * Action
 */
include dol_buildpath('/pricelist/includes/actions_addupdatedelete.inc.php');

/*
 * View
 */
$langs->loadLangs(array('products', 'categories', 'pricelist@pricelist'));

$form = new Form($db);

$arrayofjs = array();
if ((int) $object->type === 1 ? $user->hasRight('service', 'creer') : $user->hasRight('produit', 'creer')) {
    $arrayofjs[] = '/pricelist/js/delete.js';
}
$arrayofjs[] = '/pricelist/js/pricelist_ttc.js';

$title = $langs->trans('CardProduct'.$object->type).' '.$object->label;
llxHeader('', $title, '', '', '', '', $arrayofjs);

$head = product_prepare_head($object, $user);
$picto = ($object->type == 1 ? 'service' : 'product');

dol_fiche_head($head, 'pricelist', $title, 0, $picto);
dol_banner_tab($object, 'ref', '', ($user->socid ? 0 : 1), 'ref');
dol_fiche_end();

$list = $pricelist->search($object->id);
include dol_buildpath('/pricelist/includes/view.inc.php');

/*
 * Confirmation
 */
include dol_buildpath('/pricelist/includes/confirms_delete.inc.php');

llxFooter();
