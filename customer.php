<?php
/* Copyright (C) 2016-2019 Garcia MICHEL <garcia@soamichel.fr>
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

$id = GETPOST('id');
$action = GETPOST('action');
$confirm = GETPOST('confirm');
$productid = GETPOST('productid');
$qty = GETPOST('qty');
$price = GETPOST('price');
$tx_discount = GETPOST('tx_discount');
$lineid = GETPOST('lineid');
$linesid = GETPOST('linesid', 'array');

$pricelist = new PriceList($db);
$object = new Societe($db);
$object->fetch($id);

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

if ($user->rights->produit->supprimer or $user->rights->service->supprimer) {
    $arrayofjs = array('/pricelist/js/delete.js');
} else {
    $arrayofjs = '';
}

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
