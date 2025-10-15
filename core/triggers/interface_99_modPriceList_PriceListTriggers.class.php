<?php
/* Copyright (C) 2019 Garcia MICHEL <garcia@soamichel.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/pricelist/class/pricelist.class.php');

/**
 *  Class of triggers for PriceList module
 */
class InterfacePriceListTriggers extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "products";
        $this->description = "PriceList triggers.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'pricelist@pricelist';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string 		$action 	Event action code
     * @param CommonObject 	$object 	Object
     * @param User 			$user 		Object user
     * @param Translate 	$langs 		Object langs
     * @param Conf 			$conf 		Object conf
     * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($conf->pricelist->enabled)) {
            return 0;
        } // If module is not enabled, we do nothing

        $langs->load('pricelist@pricelist');

        switch ($action) {
            // Companies
            case 'COMPANY_DELETE':
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."pricelist";
                $sql.= " WHERE fk_soc = ".$object->id;
                $resql = $this->db->query($sql);
                if ($resql) {
                    return 1;
                } else {
                    $object->errors[] = $langs->trans('CanNotDeletePriceList', $this->db->lasterror());
                    return -1;
                }
                break;

            // Products
            case 'PRODUCT_CREATE':
                if (empty($conf->global->PRICELIST_CLONE_ON_CLONE_PRODUCT) or !isset($object->context['createfromclone'])) {
                    return 0;
                }

                if (version_compare(DOL_VERSION, '18.0.0') >= 0) { // Dolibarr 18+
                    $originalId = GETPOST('id');
                } else {
                    global $originalId;
                }

                if (empty($originalId)) {
                    return 0;
                }

                $pricelist = new Pricelist($this->db);
                $list = $pricelist->search($originalId);
                if (!is_array($list)) {
                    $object->error = $pricelist->error;
                    return -1;
                }

                foreach ($list as $pricelist) {
                    $pricelist->id = null;
                    $pricelist->product_id = $object->id;
                    $pricelist->user_creation_id = $user->id;

                    $res = $pricelist->create($user);
                    if ($res <= 0) {
                        $object->error = $pricelist->error;
                        return -1;
                    }
                }
                return 1;
                break;

            case 'PRODUCT_DELETE':
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."pricelist";
                $sql.= " WHERE fk_product = ".$object->id;
                $resql = $this->db->query($sql);
                if ($resql) {
                    return 1;
                } else {
                    $object->errors[] = $langs->trans('CanNotDeletePriceList', $this->db->lasterror());
                    return -1;
                }
                break;

            // Customer orders
            case 'LINEORDER_INSERT':
            case 'LINEORDER_UPDATE':
                break;

            // Proposals
            case 'LINEPROPAL_INSERT':
            case 'LINEPROPAL_UPDATE':
                break;

            // Bills
            case 'LINEBILL_INSERT':
            case 'LINEBILL_UPDATE':
                break;

            // Categories
            case 'CATEGORY_DELETE':
                if ($object->type != 2 and $object->type != $object::TYPE_CUSTOMER) {
                    return 0;
                }

                $sql = "DELETE FROM ".MAIN_DB_PREFIX."pricelist";
                $sql.= " WHERE fk_cat = ".$object->id;
                $resql = $this->db->query($sql);
                if ($resql) {
                    return 1;
                } else {
                    $object->errors[] = $langs->trans('CanNotDeletePriceList', $this->db->lasterror());
                    return -1;
                }
                break;
                break;
        }

        return 0;
    }
}
