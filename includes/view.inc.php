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

if ($list !== null and count($list) > 0) {
    print '<form id="pricelistform" action="'.$_SERVER['PHP_SELF'].'" method="get">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';

    if ($object->element == 'product') {
        print '<td>'.$langs->trans("ThirdParty").'</td>';

        if (!empty($conf->categorie->enabled)) {
            print '<td>'.$langs->trans("CustomersCategoriesShort").'</td>';
        }
    } else {
        print '<td>'.$langs->trans("ProductsOrServices").'</td>';
    }

    print '<td align="right">'.$langs->trans("FromQty").'</td>';
    print '<td align="right">'.$langs->trans("PriceHT").'</td>';
    print '<td align="right">'.$langs->trans("Discount").'</td>';
	print '<td align="right">'.$langs->trans("CostPriceHT").'</td>';

    if (!empty($conf->global->PRICELIST_SHOW_PRICES_TTC)) {
        print '<td align="right">'.$langs->trans("PriceTTC").'</td>';
    }

    print '<td align="right">'.$langs->trans("AddedBy").'</td>';

    if ($user->rights->produit->supprimer or $user->rights->service->supprimer) {
        print '<td align="right">';
        print '<a href="#" id="checkall">'.$langs->trans("All").'</a> / <a href="#" id="checknone">'.$langs->trans("None").'</a>';
        print '</td>';
    }

    print '</tr>';

    $userstatic = new User($db);
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

            if (!empty($conf->categorie->enabled)) {
                if ($obj->catid > 0) {
                    $category = new Categorie($db);
                    $category->fetch($obj->catid);
                    print '<td><a href="'.DOL_URL_ROOT.'/categories/viewcat.php?id='.$category->id.'&type='.$category->type.'" class="classfortooltip">';
                    print img_object($category->label, 'category', 'class="classfortooltip"').' '.$category->label;
                    print ' </a></td>';
                } else {
                    print '<td>-</td>';
                }
            }

            $product = $object;
        } else {
            $product = new Product($db);
            $product->fetch($obj->product_id);

            print '<td>'.$product->getNomUrl(1).' - '.$product->label . '</td>';
        }

        print '<td align="right">'.price($obj->from_qty).'</td>';
        print '<td align="right">'.($obj->price?price($obj->price):'-').'</td>';
        print '<td align="right">'.($obj->tx_discount?price($obj->tx_discount):'-').'</td>';
		// Show cost price for quick comparison // Affiche le prix de revient pour comparaison rapide
		print '<td align="right">'.($obj->cost_price?price($obj->cost_price):'-').'</td>';

        if (!empty($conf->global->PRICELIST_SHOW_PRICES_TTC)) {
            $pu = $obj->price ? $obj->price : $product->price;
            $tx_discount = $obj->tx_discount ? $obj->tx_discount : 0;
            $priceTTC = calcul_price_total(1, $pu, $tx_discount, $product->tva_tx, 0, 0, 0, 'HT', 0, $product->type);

            print '<td align="right">'.price($priceTTC[2]).'</td>';
        }

        $userstatic->fetch($obj->user_creation_id);
        print '<td align="right">';
        print $userstatic->getLoginUrl(1);
        print '</td>';

        if ($user->rights->produit->supprimer or $user->rights->service->supprimer) {
            print '<td align="right">';
            print '<input class="flat checkfordelete" type="checkbox" name="linesid[]" value="'.$obj->id.'" '.(in_array($obj->id, $linesid) ? 'checked' : '').'>';
            print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete_price&id='.$object->id.'&lineid='.$obj->id.'&token='.$_SESSION['newtoken'].'">';
            print img_delete();
            print '</a>';
            print '</td>';
        }

        print '</tr>';
    }

    print '</table><br></form>';
}

/*
 * Btn action
 */
if ($action != 'add') {
    print '<div class="tabsAction">';

    if ($user->rights->service or $user->rights->produit->creer) {
        print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"] . '?id='.$object->id.'&action=add&token='.$_SESSION['newtoken'].'">'.$langs->trans('AddPriceList').'</a></div>';
    }

    if ($user->rights->produit->supprimer or $user->rights->service->supprimer) {
        print '<div class="inline-block divButAction"><a id="btnDeleteSelected" class="butActionRefused" href="#">'.$langs->trans('DeletePriceList').'</a></div>';
    }

    print '</div>';
}

/*
 * Form add
 */
if ($action == 'add') {
    print_fiche_titre($langs->trans("NewPriceOffer"), '', '');

    print '<form action="'.$_SERVER["PHP_SELF"] . '?id='.$object->id.'" method="post">';
    print '<input type="hidden" name="token" value="' . $_SESSION ['newtoken'] . '">';
    print '<input type="hidden" name="action" value="add_confirm">';

    print '<table class="border" width="100%">';

    if ($object->element == 'product') {
        // Customer
        print '<tr>';
        print '<td>'.$langs->trans('ThirdParty').'</td>';
        print '<td colspan="2">';
        print $form->select_company($socid, 'socid', 's.client = 1 OR s.client = 2 OR s.client = 3', 1);
        print '</td>';
        print '</tr>';

        // Categorie
        if (!empty($conf->categorie->enabled)) {
            print '<tr>';
            print '<td>'.$langs->trans('CustomersCategoriesShort').'</td>';
            print '<td colspan="2">';
            print $form->select_all_categories(Categorie::TYPE_CUSTOMER, $catid, 'catid');
            print '</td>';
            print '</tr>';
        }
    } else {
        // Product
        print '<tr>';
        print '<td class="fieldrequired">'.$langs->trans('ProductOrService').'</td><td>';
        $limit_size = version_compare(DOL_VERSION, '21.0.0') >= 0 ? getDolGlobalInt('PRODUIT_LIMIT_SIZE') : $conf->product->limit_size;
        $form->select_produits($productid, 'productid', '', $limit_size, 0, 1, 2, '', 1);
        print '</td></tr>';
    }

    // Quantité
    print '<tr>';
    print '<td class="fieldrequired">'.$langs->trans('FromQtyLong').'</td>';
    print '<td colspan="2"><input type="text" name="qty" value="'.$qty.'"></td>';
    print '</tr>';

    // Price or discount
    print '<tr class="fieldrequired">';
    print '<td>'. $langs->trans('PriceOrDiscountRate') . '</td>';
    print '<td colspan="2">';
    print '<input type="text" name="price" value="'.$price.'" placeholder="'.$langs->trans('Price').'">';
    print '<input type="text" name="tx_discount" value="'.$tx_discount.'" placeholder="'.$langs->trans('Discount').'">';
		// Allow defining the cost price alongside sales data // Permet de définir le prix de revient avec les données de vente
	print '<input type="text" name="cost_price" value="'.$cost_price.'" placeholder="'.$langs->trans('CostPrice').'">';
    print '</td>';
    print '</tr>';

    print '</table>';

    print '<center><br><input type="submit" class="button" value="'.$langs->trans("Save").'">&nbsp;';
    print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'"></center>';

    print '</form>';
}
