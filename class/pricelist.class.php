<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2014	   Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2016-2021 Garcia MICHEL <garcia@soamichel.fr>
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

/**
 *  \file       dev/skeletons/pricelist.class.php
 *  \ingroup    mymodule othermodule1 othermodule2
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2016-06-19 19:40
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

/**
 *	Manage pricelist
 */
class Pricelist extends CommonObject
{
    public $db;
    public $error;
    public $errors=array();
    public $element='pricelist';
    public $table_element='pricelist';

    public $id;

    public $product_id;
    public $socid;
    public $catid;
    public $from_qty;
    public $price;
    public $tx_discount;
    public $user_creation_id;

    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        return 1;
    }

    /**
     *  Create object into database
     *
     *  @param	User	$user        User that creates
     *  @return int      		   	 <0 if KO, Id of created object if OK
     */
    public function create($user)
    {
        global $conf, $langs;

        if (
            (empty($this->price) and empty($this->tx_discount)) or
            (!empty($this->price) and !empty($this->tx_discount))
        ) {
            $this->error = $langs->trans('FillPriceOrDiscountField');
            return -1;
        }

        // Insert request
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";

        $sql.= "fk_product,";
        $sql.= "fk_soc,";
        $sql.= "fk_cat,";
        $sql.= "from_qty,";
        $sql.= "price,";
        $sql.= "tx_discount,";
        $sql.= "fk_user_creation";

        $sql.= ") VALUES (";

        $sql.= " ".$this->product_id.",";
        $sql.= " ".($this->socid?$this->socid:"null").",";
        $sql.= " ".($this->catid?$this->catid:"null").",";
        $sql.= " ".price2num($this->from_qty).",";
        $sql.= " ".($this->price?price2num($this->price):"null").",";
        $sql.= " ".($this->tx_discount?price2num($this->tx_discount):"null").",";
        $sql.= " ".$user->id;

        $sql.= ")";

        $this->db->begin();

        dol_syslog(__METHOD__, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if (!$resql) {
            $this->db->rollback();
            $this->error = "Error ".$this->db->lasterror();
            return -1;
        }

        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

        $res = $this->call_trigger('PRICELIST_CREATE', $user);
        if ($res < 0) {
            $this->db->rollback();
            return -1;
        }

        $this->db->commit();
        return $this->id;
    }

    /**
     *  Load object in memory from the database
     *
     *  @param	int		$id    	Id object
     *  @return int          	<0 if KO, >0 if OK
     */
    public function fetch($id)
    {
        global $langs;
        $sql = "SELECT";
        $sql.= " t.rowid,";

        $sql.= " t.fk_product,";
        $sql.= " t.fk_soc,";
        $sql.= " t.from_qty,";
        $sql.= " t.price,";
        $sql.= " t.tx_discount,";
        $sql.= " t.fk_user_creation";

        $sql.= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t WHERE t.rowid = ".$id;

        dol_syslog(get_class($this)."::fetch");
        $resql=$this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;

                $this->product_id = $obj->fk_product;
                $this->socid = $obj->fk_soc;
                $this->from_qty = $obj->from_qty;
                $this->price = $obj->price;
                $this->tx_discount = $obj->tx_discount;
                $this->user_creation_id = $obj->fk_user_creation;

                $this->db->free($resql);

                return 1;
            } else {
                $this->db->free($resql);
                return 0;
            }
        }

        $this->error="Error ".$this->db->lasterror();
        return -1;
    }

    /**
     * Search list object into database
     *
     * @param int product_id
     * @param int socid
     * @return null if error else array
     */
    public function search($product_id=0, $socid=0, $catid=0)
    {
        $sql = "SELECT";
        $sql.= " t.rowid,";

        $sql.= " t.fk_product,";
        $sql.= " t.fk_soc,";
        $sql.= " t.fk_cat,";
        $sql.= " t.from_qty,";
        $sql.= " t.price,";
        $sql.= " t.tx_discount,";
        $sql.= " t.fk_user_creation";

        $sql.= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";

        $where = array();
        if ($product_id) {
            $where[] = "fk_product = ".$product_id;
        }
        if ($socid) {
            $where[] = "fk_soc = ".$socid;
        }
        if ($catid) {
            $where[] = "fk_cat = ".$catid;
        }
        if ($where) {
            $sql.= " WHERE ";
            foreach ($where as $key => $w) {
                $sql.= $w;

                end($where);
                if ($key != key($where)) {
                    $sql.= " AND ";
                }
            }
        }
        $sql.= " ORDER BY fk_product, fk_soc, fk_cat, from_qty";

        dol_syslog(get_class($this)."::fetch");
        $resql=$this->db->query($sql);
        if ($resql) {
            $list = array();
            $num = $this->db->num_rows($resql);
            if ($num) {
                $i = 0;
                while ($i < $num) {
                    $object = new PriceList($this->db);
                    $obj = $this->db->fetch_object($resql);

                    $object->id = $obj->rowid;
                    $object->product_id = $obj->fk_product;
                    $object->socid = $obj->fk_soc;
                    $object->catid = $obj->fk_cat;
                    $object->from_qty = $obj->from_qty;
                    $object->price = $obj->price;
                    $object->tx_discount = $obj->tx_discount;
                    $object->user_creation_id = $obj->fk_user_creation;

                    $list[$i] = $object;
                    $i++;
                }
            }

            return $list;
        }

        $this->error="Error ".$this->db->lasterror();
        return null;
    }

    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modifies
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    public function update($user)
    {
        global $conf, $langs;

        if (
            (empty($this->price) and empty($this->tx_discount)) or
            (!empty($this->price) and !empty($this->tx_discount))
        ) {
            $this->error = $langs->trans('FillPriceOrDiscountField');
            return -1;
        }

        $error=0;

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";

        $sql.= " fk_product=".$this->product_id.",";
        $sql.= " fk_soc=".($this->socid?$this->socid:"null").",";
        $sql.= " fk_cat=".($this->catid?$this->catid:"null").",";
        $sql.= " from_qty=".price2num($this->from_qty).",";
        $sql.= " price=".($this->price?price2num($this->price):"null").",";
        $sql.= " tx_discount=".($this->tx_discount?price2num($this->tx_discount):"null");

        $sql.= " WHERE rowid=".$this->id;

        $this->db->begin();

        dol_syslog(__METHOD__);
        $resql = $this->db->query($sql);
        if (! $resql) {
            $error++;
            $this->errors[]="Error ".$this->db->lasterror();
        }

        if (!$error) {
            $result = $this->call_trigger('PRICELIST_UPDATE', $user);
            if ($result < 0) {
                $error++;
            }
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(__METHOD__." ".$errmsg, LOG_ERR);
                $this->error.=($this->error?', '.$errmsg:$errmsg);
            }
            $this->db->rollback();
            return -1*$error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     *  Delete object in database
     *
     *	@param  User	$user        User that deletes
     *  @return	int					 <0 if KO, >0 if OK
     */
    public function delete($user)
    {
        global $conf, $langs;
        $error=0;

        $this->db->begin();

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql.= " WHERE rowid=".$this->id;

        dol_syslog(__METHOD__);
        $resql = $this->db->query($sql);
        if (! $resql) {
            $error++;
            $this->errors[]="Error ".$this->db->lasterror();
        }

        if (!$error) {
            $result = $this->call_trigger('PRICELIST_DELETE', $user);
            if ($result < 0) {
                $error++;
            }
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(__METHOD__." ".$errmsg, LOG_ERR);
                $this->error.=($this->error?', '.$errmsg:$errmsg);
            }
            $this->db->rollback();
            return -1*$error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * Recherche le tarif du produit pour le client donné par rapport à la quantité
     *
     * Recherche dans l'ordre : tarif lié au client, tarif le plus bas aux catégories du client, tarif du produit sans client
     */
    public function get_price($idproduct, $soc, $qty)
    {
        $product = new Product($this->db);
        $res = $product->fetch($idproduct);
        if ($res <= 0) {
            $this->error = 'Failed to fetch product';
            return -1;
        }

        /**
         * tarif lié au client
         */
        $sql = "SELECT price, tx_discount, from_qty FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
        $sql.= " WHERE fk_product = ".$idproduct." AND fk_soc = ".$soc->id." AND from_qty <= ".price2num($qty);
        $sql.= " ORDER BY from_qty DESC";

        $resql=$this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                return $this->db->fetch_object($resql);
            }
        } else {
            $this->error="Error ".$this->db->lasterror();
            return -1;
        }

        /**
         * tarif le plus bas aux catégories du client
         */
        $objcat = new Categorie($this->db);
        $cats = $objcat->containing($soc->id, 2);

        if (count($cats) > 0) {
            $in = "(";
            foreach ($cats as $key => $cat) {
                $in.= $cat->id;
                end($cats);
                if ($key != key($cats)) {
                    $in.= ",";
                }
            }
            $in.= ")";

            $sql = "SELECT price, tx_discount, from_qty FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
            $sql.= " WHERE fk_product = ".$idproduct." AND fk_cat IN ".$in." AND from_qty <= ".price2num($qty);
            $sql.= " ORDER BY from_qty DESC, price ASC";

            $resql=$this->db->query($sql);
            if ($resql) {
                if ($this->db->num_rows($resql)) {
                    return $this->db->fetch_object($resql);
                }
            } else {
                $this->error="Error ".$this->db->lasterror();
                return -1;
            }
        }

        /**
         * tarif du produit lié à aucun client
         */
        $sql = "SELECT price, tx_discount, from_qty FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
        $sql.= " WHERE fk_product = ".$idproduct." AND (fk_soc IS NULL OR fk_soc < 1) AND (fk_cat IS NULL OR fk_cat < 1) AND from_qty <= ".price2num($qty);
        $sql.= " ORDER BY from_qty DESC";

        $resql=$this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                return $this->db->fetch_object($resql);
            }
        } else {
            $this->error="Error ".$this->db->lasterror();
            return -1;
        }

        // no tarif found
        return 0;
    }
}
