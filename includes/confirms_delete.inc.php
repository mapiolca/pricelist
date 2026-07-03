<?php

dol_include_once('/pricelist/lib/pricelist.lib.php');

$canDeletePriceListConfirm = isset($canDeletePriceList) ? (bool) $canDeletePriceList : pricelistCanWritePrices($user);

if ($action == 'delete_price' && $canDeletePriceListConfirm) {
	$typeparam = (isset($type) && $type ? '&type='.urlencode($type) : '');
    print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id.$typeparam.'&lineid='.$lineid, $langs->trans('DeletePriceList'), $langs->trans('ConfirmDeletePriceList'), 'confirm_delete_price', '', 0, 1);
}

if ($action == 'delete_prices' && $canDeletePriceListConfirm) {
	$typeparam = (isset($type) && $type ? '&type='.urlencode($type) : '');
    $params = '';
    foreach ($linesid as $lineid) {
        $params .= '&linesid[]='.$lineid;
    }
    print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id.$typeparam.$params, $langs->trans('DeletePriceList'), $langs->trans('ConfirmDeletePriceList'), 'confirm_delete_prices', '', 0, 1);
}
