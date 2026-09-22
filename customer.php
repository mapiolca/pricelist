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

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/pricelist/class/pricelist.class.php');
dol_include_once('/pricelist/lib/pricelist.lib.php');

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'aZ09');
$productid = GETPOSTINT('productid');
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

$pricelist = new PriceList($db);
$object = new Societe($db);
if ($object->fetch($id) <= 0 || !$user->hasRight('societe', 'lire') || !$object->client) {
	accessforbidden();
}
restrictedArea($user, 'societe', $object->id, 'societe');
if (!(getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') > 0 ? ($user->hasRight('product', 'product_advance', 'read_prices') || $user->hasRight('service', 'service_advance', 'read_prices')) : ($user->hasRight('product', 'read') || $user->hasRight('service', 'read')))) {
    accessforbidden();
}

/*
 * Actions
 */
include dol_buildpath('/pricelist/includes/actions_addupdatedelete.inc.php');

/*
 * View
 */
$langs->load('companies');
$langs->load('pricelist@pricelist');

$form = new Form($db);

$arrayofjs = array();
if (($user->hasRight('produit', 'creer') || $user->hasRight('service', 'creer'))) {
    $arrayofjs[] = '/pricelist/js/delete.js';
}
$arrayofjs[] = '/pricelist/js/pricelist_ttc.js';

llxHeader('', $langs->trans('ThirdParty'), '', '', '', '', $arrayofjs);

$head = societe_prepare_head($object);
dol_fiche_head($head, 'pricelist', $langs->trans("ThirdParty"), 0, 'company');
dol_banner_tab($object, 'id', '', ($user->socid ? 0 : 1), 'rowid');
dol_fiche_end();

$list = $pricelist->search(0, $id);
include dol_buildpath('/pricelist/includes/view.inc.php');

/*
 * Confirmation
 */
include dol_buildpath('/pricelist/includes/confirms_delete.inc.php');

llxFooter();
