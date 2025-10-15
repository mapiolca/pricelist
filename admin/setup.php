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

require_once '../require.php';
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";

$langs->loadLangs(array('admin', 'pricelist@pricelist'));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$arrayofparameters = array(
    'PRICELIST_CLONE_ON_CLONE_PRODUCT' => array(
        'type' => 'yesno',
    ),
    'PRICELIST_SHOW_PRICES_TTC' => array(
        'type' => 'yesno',
    ),
    'PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING' => array(
        'type' => 'yesno',
    ),
);

if ($action == 'confirm_purge') {
    $resql = $db->query("DELETE FROM ".MAIN_DB_PREFIX.'pricelist');
    if ($resql) {
        setEventMessage($langs->trans('AllPriceListRemoved'));
    } else {
        setEventMessage('Fail to remove all price list : '.$db->lasterror(), 'errors');
    }

    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

/*
 * Actions
 */
include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */
$form = new Form($db);

llxHeader('', $langs->trans('PriceListSetup'));

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans('PriceListSetup'), $linkback);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefieldmiddle">'.$langs->trans("Parameter").'</td><td class="right" width="60">'.$langs->trans("Value").'</td></tr>';

foreach ($arrayofparameters as $key => $val) {
    print '<tr class="oddeven"><td>'.(isset($val['label']) ? $val['label'] : $langs->trans($key)).'</td><td class="right" width="60">';

    if (!isset($val['type'])) {
        print '<input name="'.$key.'" class="flat minwidth200" value="'.$conf->global->$key.'">';
    } elseif ($val['type'] == 'yesno') {
        print $form->selectyesno($key, $conf->global->$key, 1);
    }

    print '</td></tr>';
}

print '</table>';

print '<br><div class="center">';
print '<input class="button" type="submit" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print load_fiche_titre($langs->trans("Other"), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefieldmiddle">'.$langs->trans("Parameter").'</td><td class="right" width="60">'.$langs->trans("Value").'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('PurgePriceList').'</td><td class="right" width="60">';
print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"] . '?&action=purge">'.$langs->trans('PurgeAll').'</a></div>';
print '</td></tr>';

print '</table>';

if ($action == 'purge') {
    print $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('PurgeAll'), $langs->trans('ConfirmPurgePriceList'), 'confirm_purge', '', 0, 1);
}

llxFooter();
