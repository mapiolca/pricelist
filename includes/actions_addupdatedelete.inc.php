<?php

if ($action == 'add_confirm' and !GETPOST('cancel') and ($user->rights->service->creer or $user->rights->produit->creer)) {
    if (empty($qty) or (empty($price) and empty($tx_discount))) {
        setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
    } elseif ($object->element != 'product' and empty($productid)) {
        setEventMessage($langs->trans('AllFieldIsRequired'), 'errors');
    } else {
        $pricelist->product_id = $object->element == 'product' ? $object->id : $productid;

        if ($object->element == 'societe') {
            $pricelist->socid = $object->id;
        } elseif (isset($socid) and $socid > 0) {
            $pricelist->socid = $socid;
        } else {
            $pricelist->socid = null;
        }

        if ($object->element == 'category') {
            $pricelist->catid = $object->id;
        } elseif (isset($catid) and $catid > 0 and empty($pricelist->socid)) {
            $pricelist->catid = $catid;
        } else {
            $pricelist->catid = null;
        }

        $pricelist->from_qty = $qty;
        $pricelist->price = $price;
        $pricelist->tx_discount = $tx_discount;

        $res = $pricelist->create($user);
        if ($res < 0) {
            setEventMessages($pricelist->error, $pricelist->errors, 'errors');
        } else {
            $qty = '';
            $price = '';
            $tx_discount = '';
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

    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
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

    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
    exit;
}
