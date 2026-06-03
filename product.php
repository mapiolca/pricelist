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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/pricelist/class/pricelist.class.php');

$id = GETPOST('id');
$ref = GETPOST('ref');
$action = GETPOST('action');
$confirm = GETPOST('confirm');
$socid = GETPOST('socid');
$catid = GETPOST('catid');
$qty = GETPOST('qty');
$price = GETPOST('price');
$tx_discount = GETPOST('tx_discount');
$cost_price = GETPOST('cost_price'); // Retrieve cost price field // Récupère le prix de revient
$lineid = GETPOST('lineid');
$linesid = GETPOST('linesid', 'array');

// Security check
if (version_compare(DOL_VERSION, '13.0.0') < 0) {
    if ($user->societe_id) {
        accessforbidden();
    }
} else if ($user->socid) {
    accessforbidden();
}

$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');

$result = restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);

$object = new Product($db);
$res = $object->fetch($id, $ref);
if ($res <= 0) {
    dol_print_error($db);
}

$pricelist = new PriceList($db);

/*
 * Action
 */
include dol_buildpath('/pricelist/includes/actions_addupdatedelete.inc.php');

/*
 * View
 */
$langs->loadLangs(array('products', 'categories'));

$form = new Form($db);

if ($user->rights->produit->supprimer or $user->rights->service->supprimer) {
    $arrayofjs = array('/pricelist/js/delete.js');
} else {
    $arrayofjs = '';
}

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
