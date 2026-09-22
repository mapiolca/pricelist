<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../require.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/pricelist.lib.php';
require_once __DIR__.'/../core/modules/modPriceList.class.php';

$langs->loadLangs(array('admin', 'pricelist@pricelist'));
if (empty($user->admin)) {
	accessforbidden();
}

$descriptor = new modPriceList($db);
$title = $langs->trans('About').' — '.$descriptor->name;
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=pricelist">'.$langs->trans('BackToModuleList').'</a>';
llxHeader('', $title);
print load_fiche_titre($title, $linkback, 'info');
print dol_get_fiche_head(pricelistAdminPrepareHead(), 'about', $langs->trans('PriceListSetup'), -1, $descriptor->picto);

$metadata = array(
	'Name' => $descriptor->name,
	'Version' => $descriptor->version,
	'Publisher' => $descriptor->editor_name,
	'Description' => $langs->trans($descriptor->description),
	'MinimumDolibarrVersion' => implode('.', $descriptor->need_dolibarr_version).'+',
	'MinimumPHPVersion' => implode('.', $descriptor->phpmin).'+',
	'PriceListDependencies' => $descriptor->depends ? implode(', ', $descriptor->depends) : $langs->trans('None'),
	'PriceListOptionalModules' => implode(', ', $descriptor->recommended_modules),
	'DolibarrLicense' => $descriptor->license,
);
print '<div class="fichecenter"><div class="fichehalfleft"><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans('Informations').'</th></tr>';
foreach ($metadata as $label => $value) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($label).'</td><td>'.dol_escape_htmltag($value).'</td></tr>';
}
print '</table></div></div>';

print '<div class="fichehalfright"><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans('PriceListUsefulLinks').'</th></tr>';
foreach ($descriptor->useful_links + array('Publisher' => $descriptor->editor_url) as $label => $url) {
	print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td><a href="'.dol_escape_htmltag($url).'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag($url).'</a></td></tr>';
}
print '</table></div><br><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('CompatibilityFeatures').'</th></tr>';
foreach (array('PriceListAboutTiers', 'PriceListAboutSources', 'PriceListAboutDocuments', 'PriceListAboutHistory') as $feature) {
	print '<tr class="oddeven"><td>'.$langs->trans($feature).'</td></tr>';
}
print '</table></div></div></div><div class="clearboth"></div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
