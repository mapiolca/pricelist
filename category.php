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

$id=GETPOST('id');
$label=GETPOST('label', 'alpha');
$action = GETPOST('action');
$confirm = GETPOST('confirm');
$productid = GETPOST('productid');
$qty = GETPOST('qty');
$price = GETPOST('price');
$tx_discount = GETPOST('tx_discount');
$cost_price = GETPOST('cost_price'); // Retrieve cost price field // Récupère le prix de revient
$lineid = GETPOST('lineid');
$linesid = GETPOST('linesid', 'array');

$pricelist = new PriceList($db);
$object = new Categorie($db);
$object->fetch($id, $label);

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

if ($user->rights->produit->supprimer or $user->rights->service->supprimer) {
    $arrayofjs = array('/pricelist/js/delete.js');
} else {
    $arrayofjs = '';
}

llxHeader('', $langs->trans('Categories'), '', '', '', '', $arrayofjs);

$title = $langs->trans("CustomersCategoryShort");

$head = categories_prepare_head($object, 'customer');
dol_fiche_head($head, 'pricelist', $title, 0, 'category');

$object->next_prev_filter = ' type = '.$object->type;
$object->ref = $object->label;
$morehtmlref = '<br><div class="refidno"><a href="'.DOL_URL_ROOT.'/categories/index.php?leftmenu=cat&type=customer">'.$langs->trans("Root").'</a> >> ';
$ways = $object->print_all_ways(" &gt;&gt; ", '', 1);
foreach ($ways as $way) {
    $morehtmlref .= $way."<br>\n";
}
$morehtmlref .= '</div>';

dol_banner_tab($object, 'label', '', ($user->socid ? 0 : 1), 'label', 'label', $morehtmlref, '&type=customer');

dol_fiche_end();

$list = $pricelist->search(0, 0, $id);
include dol_buildpath('/pricelist/includes/view.inc.php');

/*
 * Confirmation
 */
include dol_buildpath('/pricelist/includes/confirms_delete.inc.php');

llxFooter();
