<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once '../require.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/pricelist/class/pricelistcompatibility.class.php');
dol_include_once('/pricelist/lib/pricelist.lib.php');

$langs->loadLangs(array('admin', 'pricelist@pricelist'));

if (empty($user->admin)) {
	accessforbidden();
}

$backtopage = GETPOST('backtopage', 'alpha');

llxHeader('', $langs->trans('PriceListCompatibility'));

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('PriceListCompatibility'), $linkback);
print dol_get_fiche_head(pricelistAdminPrepareHead(), 'compatibility', $langs->trans('PriceListSetup'), -1, 'currency');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedPHPVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedDolibarrVersion').'</td><td>'.dol_escape_htmltag(DOL_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumPHPVersion').'</td><td>8.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumDolibarrVersion').'</td><td>20.0</td></tr>';
print '</table>';

print '<br>';
print load_fiche_titre($langs->trans('CompatibilityFeatures'), '', '');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Code').'</td>';
print '<td>'.$langs->trans('Feature').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td>'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('Reason').'</td>';
print '</tr>';

foreach (PriceListCompatibility::getFeatures() as $code => $feature) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($code).'</td>';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.$langs->trans($feature['description']).'</td>';
	print '<td>'.(!empty($feature['available']) ? $langs->trans('Available') : $langs->trans('Unavailable')).'</td>';
	print '<td>'.(!empty($feature['reason']) ? $langs->trans($feature['reason']) : '-').'</td>';
	print '</tr>';
}

print '</table>';

print dol_get_fiche_end();

llxFooter();
