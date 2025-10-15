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

dol_include_once('/pricelist/class/pricelist.class.php');

class ActionsPriceList
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user, $confirm, $conf;

        $langs->load('pricelist@pricelist');

        if (version_compare(DOL_VERSION, '4.0.0') >= 0) {
            $object->fetch_thirdparty();
            $client = $object->thirdparty;
        } else {
            $client = $object->client;
        }

        $context = $parameters['currentcontext'];
        if ($context == 'ordercard' and $user->rights->commande->creer) { /* COMMANDE */
            if ($action == 'addline') {
                if (GETPOST('prod_entry_mode') == 'free') {
                    return;
                }

                if (!empty($conf->global->PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING) and GETPOSTINT('price_ht') != 0) {
                    return;
                }

                $idprod = GETPOST('idprod');
                $qty = GETPOST('qty');

                $pricelist = new PriceList($this->db);
                $obj = $pricelist->get_price($idprod, $client, $qty);
                if (!is_int($obj)) {
                    if ($obj->price) {
                        $_POST['price_ht'] = price($obj->price);
                    } else {
                        $_POST['remise_percent'] = price($obj->tx_discount);
                    }

                    setEventMessage($langs->trans('PriceListInsert'));
                }
            } elseif (in_array($action, array('updateligne', 'updateline'))) {
                $idprod = GETPOST('productid');
                if (!$idprod) {
                    return;
                }

                $lineid = GETPOST('lineid');
                $qty = GETPOST('qty');

                $line = new OrderLine($this->db);
                $line->fetch($lineid);
                if (floatval($line->qty) != floatval($qty)) { // qty updated
                    $pricelist = new PriceList($this->db);
                    $obj = $pricelist->get_price($idprod, $client, $qty);
                    if (!is_int($obj)) {
                        if ($obj->price) {
                            $_POST['price_ht'] = price($obj->price);
                        } else {
                            $_POST['remise_percent'] = price($obj->tx_discount);
                        }

                        setEventMessage($langs->trans('PriceListInsert'));
                    }
                }
            } elseif ($action == 'altaupdatelines') {
                $pricelist = new PriceList($this->db);
                $updatedLines = 0;

                foreach ($object->lines as $line) {
                    if (empty($line->fk_product)) {
                        continue;
                    }

                    $obj = $pricelist->get_price($line->fk_product, $client, $line->qty);
                    if (!is_int($obj)) {
                        if ($obj->price) {
                            $pu = price($obj->price);
                            $remise_percent = $line->remise_percent;
                        } else {
                            $pu = $line->subprice;
                            $remise_percent = price($obj->tx_discount);
                        }

                        $res = $object->updateline(
                            $line->id,
                            $line->description,
                            $pu,
                            $line->qty,
                            $remise_percent,
                            $line->tva_tx,
                            $line->localtax1_tx,
                            $line->localtax2_tx,
                            'HT',
                            $line->info_bits,
                            $line->date_start,
                            $line->date_end,
                            $line->product_type,
                            $line->fk_parent_line,
                            0,
                            $line->fk_fournprice,
                            $line->pa_ht,
                            $line->label,
                            $line->special_code,
                            0,
                            $line->fk_unit,
                            $line->multicurrency_subprice
                        );

                        if ($res > 0) {
                            $updatedLines++;
                        } else {
                            setEventMessages($object->error, $object->errors, 'errors');
                        }
                    }
                }

                if ($updatedLines > 0) {
                    setEventMessage($langs->trans('PriceListInsert'));
                }
            }
        } elseif ($context == 'propalcard' and $user->rights->propal->creer) { /* PROPAL */
            if ($action == 'addline') {
                if (GETPOST('prod_entry_mode') == 'free') {
                    return;
                }

                if (!empty($conf->global->PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING) and GETPOSTINT('price_ht') != 0) {
                    return;
                }

                $idprod = GETPOST('idprod');
                $qty = GETPOST('qty');

                $pricelist = new PriceList($this->db);
                $obj = $pricelist->get_price($idprod, $client, $qty);
                if (!is_int($obj)) {
                    if ($obj->price) {
                        $_POST['price_ht'] = price($obj->price);
                    } else {
                        $_POST['remise_percent'] = price($obj->tx_discount);
                    }

                    setEventMessage($langs->trans('PriceListInsert'));
                }
            } elseif (in_array($action, array('updateligne', 'updateline'))) {
                $idprod = GETPOST('productid');
                if (!$idprod) {
                    return;
                }

                $lineid = GETPOST('lineid');
                $qty = GETPOST('qty');

                $line = new PropaleLigne($this->db);
                $line->fetch($lineid);
                if (floatval($line->qty) != floatval($qty)) { // qty updated
                    $pricelist = new PriceList($this->db);
                    $obj = $pricelist->get_price($idprod, $client, $qty);
                    if (!is_int($obj)) {
                        if ($obj->price) {
                            $_POST['price_ht'] = price($obj->price);
                        } else {
                            $_POST['remise_percent'] = price($obj->tx_discount);
                        }

                        setEventMessage($langs->trans('PriceListInsert'));
                    }
                }
            } elseif ($action == 'altaupdatelines') {
                $pricelist = new PriceList($this->db);
                $updatedLines = 0;

                foreach ($object->lines as $line) {
                    if (empty($line->fk_product)) {
                        continue;
                    }

                    $obj = $pricelist->get_price($line->fk_product, $client, $line->qty);
                    if (!is_int($obj)) {
                        if ($obj->price) {
                            $pu = price($obj->price);
                            $remise_percent = $line->remise_percent;
                        } else {
                            $pu = $line->subprice;
                            $remise_percent = price($obj->tx_discount);
                        }

                        $res = $object->updateline(
                            $line->id,
                            $pu,
                            $line->qty,
                            $remise_percent,
                            $line->tva_tx,
                            $line->localtax1_tx,
                            $line->localtax2_tx,
                            $line->desc,
                            'HT',
                            $line->info_bits,
                            $line->special_code,
                            $line->fk_parent_line,
                            0,
                            $line->fk_fournprice,
                            $line->pa_ht,
                            $line->label,
                            $line->product_type,
                            $line->date_start,
                            $line->date_end,
                            0,
                            $line->fk_unit,
                            $line->multicurrency_subprice
                        );

                        if ($res > 0) {
                            $updatedLines++;
                        } else {
                            setEventMessages($object->error, $object->errors, 'errors');
                        }
                    }
                }

                if ($updatedLines > 0) {
                    setEventMessage($langs->trans('PriceListInsert'));
                }
            }
        } elseif (in_array($context, array('invoicecard', 'invoicereccard')) and $user->rights->facture->creer) { /* FACTURE */
            if ($action == 'addline') {
                if (GETPOST('prod_entry_mode') == 'free') {
                    return;
                }

                if (!empty($conf->global->PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING) and GETPOSTINT('price_ht') != 0) {
                    return;
                }

                $idprod = GETPOST('idprod');
                $qty = GETPOST('qty');

                $pricelist = new PriceList($this->db);
                $obj = $pricelist->get_price($idprod, $client, $qty);
                if (!is_int($obj)) {
                    if ($obj->price) {
                        $_POST['price_ht'] = price($obj->price);
                    } else {
                        $_POST['remise_percent'] = price($obj->tx_discount);
                    }

                    setEventMessage($langs->trans('PriceListInsert'));
                }
            } elseif (in_array($action, array('updateligne', 'updateline'))) {
                $idprod = GETPOST('productid');
                if (!$idprod) {
                    return;
                }

                $lineid = GETPOST('lineid');
                $qty = GETPOST('qty');

                $line = new FactureLigne($this->db);
                $line->fetch($lineid);
                if (floatval($line->qty) != floatval($qty)) { // qty updated
                    $pricelist = new PriceList($this->db);
                    $obj = $pricelist->get_price($idprod, $client, $qty);
                    if (!is_int($obj)) {
                        if ($obj->price) {
                            $_POST['price_ht'] = price($obj->price);
                        } else {
                            $_POST['remise_percent'] = price($obj->tx_discount);
                        }

                        setEventMessage($langs->trans('PriceListInsert'));
                    }
                }
            } elseif ($action == 'altaupdatelines') {
                $pricelist = new PriceList($this->db);
                $updatedLines = 0;

                foreach ($object->lines as $line) {
                    if (empty($line->fk_product)) {
                        continue;
                    }

                    $obj = $pricelist->get_price($line->fk_product, $client, $line->qty);
                    if (!is_int($obj)) {
                        if ($obj->price) {
                            $pu = price($obj->price);
                            $remise_percent = $line->remise_percent;
                        } else {
                            $pu = $line->subprice;
                            $remise_percent = price($obj->tx_discount);
                        }

                        $res = $object->updateline(
                            $line->id,
                            $line->desc,
                            $pu,
                            $line->qty,
                            $remise_percent,
                            $line->date_start,
                            $line->date_end,
                            $line->tva_tx,
                            $line->localtax1_tx,
                            $line->localtax2_tx,
                            'HT',
                            $line->info_bits,
                            $line->product_type,
                            $line->fk_parent_line,
                            0,
                            $line->fk_fournprice,
                            $line->pa_ht,
                            $line->label,
                            $line->special_code,
                            0,
                            $line->situation_percent,
                            $line->fk_unit,
                            $line->multicurrency_subprice
                        );

                        if ($res > 0) {
                            $updatedLines++;
                        } else {
                            setEventMessages($object->error, $object->errors, 'errors');
                        }
                    }
                }

                if ($updatedLines > 0) {
                    setEventMessage($langs->trans('PriceListInsert'));
                }
            }
        }
    }

    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;

        $context = $parameters['currentcontext'];
        if (
            ($context == 'ordercard' and $user->rights->commande->creer) /* COMMANDE */
            or ($context == 'propalcard' and $user->rights->propal->creer) /* PROPAL */
            or (in_array($context, array('invoicecard', 'invoicereccard')) and $user->rights->facture->creer) /* FACTURE */
        ) {
            print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=altaupdatelines&token='.newToken().'">'.$langs->trans('PriceListUpdate').'</a>';
        }
    }
}
