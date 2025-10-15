<?php

if ($action == 'delete_price' and ($user->rights->produit->supprimer or $user->rights->service->supprimer)) {
    print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeletePriceList'), $langs->trans('ConfirmDeletePriceList'), 'confirm_delete_price', '', 0, 1);
}

if ($action == 'delete_prices' and ($user->rights->produit->supprimer or $user->rights->service->supprimer)) {
    $params = '';
    foreach ($linesid as $lineid) {
        $params .= '&linesid[]='.$lineid;
    }
    print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id.$params, $langs->trans('DeletePriceList'), $langs->trans('ConfirmDeletePriceList'), 'confirm_delete_prices', '', 0, 1);
}
