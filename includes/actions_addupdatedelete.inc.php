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

if ($action == 'add_confirm' and !GETPOST('cancel') and ($user->rights->service->creer or $user->rights->produit->creer)) {
	$priceFilled = dol_strlen($price);
	$discountFilled = dol_strlen($tx_discount);
	$costFilled = dol_strlen($cost_price);

	// Ensure one monetary field is provided // S'assure qu'un champ monétaire est renseigné
	if (empty($qty)) {
		setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
	} elseif (!$priceFilled and !$discountFilled and !$costFilled) {
		// Require at least one pricing information // Exige au moins une information tarifaire
		setEventMessage($langs->trans('FillPriceOrDiscountField'), 'errors');
	} elseif ($priceFilled and $discountFilled) {
		// Prevent using price and discount together // Empêche l'utilisation simultanée du prix et de la remise
		setEventMessage($langs->trans('FillPriceOrDiscountField'), 'errors');
	} elseif ($object->element != 'product' and empty($productid)) {
		setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
	} else {
		$pricelist->product_id = $object->element == 'product' ? $object->id : $productid;
		$targetCount = 0;
		$pricelist->catid = null;
		$pricelist->catid_propal = null;
		$pricelist->catid_contract = null;

		if ($object->element == 'societe') {
			$pricelist->socid = $object->id;
		} elseif (isset($socid) and $socid > 0) {
			$pricelist->socid = $socid;
		} else {
			$pricelist->socid = null;
		}
		if (!empty($pricelist->socid)) {
			$targetCount++;
		}

		if ($object->element == 'category' && (empty($type) || $type == 'customer')) {
			$pricelist->catid = $object->id;
		} elseif ($object->element == 'category' && $type == 'propal') {
			$pricelist->catid_propal = $object->id;
		} elseif ($object->element == 'category' && $type == 'contract') {
			$pricelist->catid_contract = $object->id;
		} else {
			if (isset($catid) and $catid > 0) {
				$pricelist->catid = $catid;
			}
			if (isset($catid_propal) and $catid_propal > 0) {
				$pricelist->catid_propal = $catid_propal;
			}
			if (isset($catid_contract) and $catid_contract > 0) {
				$pricelist->catid_contract = $catid_contract;
			}
		}
		foreach (array($pricelist->catid, $pricelist->catid_propal, $pricelist->catid_contract) as $selectedCategory) {
			if (!empty($selectedCategory)) {
				$targetCount++;
			}
		}

		if ($targetCount > 1) {
			setEventMessage($langs->trans('PriceListSingleScopeRequired'), 'errors');
		} else {
			$pricelist->from_qty = $qty;
			$pricelist->price = $price;
			$pricelist->tx_discount = $tx_discount;
			$pricelist->cost_price = $cost_price;

			$res = $pricelist->create($user);
			if ($res < 0) {
				setEventMessages($pricelist->error, $pricelist->errors, 'errors');
			} else {
				$qty = '';
				$price = '';
				$tx_discount = '';
				$cost_price = '';
			}
		}
	}

    $action = 'add';
}

if ($action == 'confirm_delete_price' and $confirm == 'yes' and ($user->rights->produit->supprimer or $user->rights->service->supprimer)) {
    $pricelist->fetch($lineid);
    $res = $pricelist->delete($user);
    if ($res < 0) {
        setEventMessages($pricelist->error, $pricelist->errors, 'errors');
    }

	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.(isset($type) && $type ? '&type='.$type : ''));
	exit;
}

if ($action == 'confirm_delete_prices' and $confirm == 'yes' and ($user->rights->produit->supprimer or $user->rights->service->supprimer)) {
    foreach ($linesid as $lineid) {
        $pricelist->fetch($lineid);
        $res = $pricelist->delete($user);
        if ($res < 0) {
            setEventMessages($pricelist->error, $pricelist->errors, 'errors');
        }
    }

	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.(isset($type) && $type ? '&type='.$type : ''));
	exit;
}
