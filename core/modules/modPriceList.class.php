<?php
/* Copyright (C) 2024 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2016-2019 Garcia MICHEL <garcia@soamichel.fr>
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
 * 	\defgroup   pricelist     Module PriceList
 *  \brief      Example of a module descriptor.
 *				Such a file must be copied into htdocs/pricelist/core/modules directory.
 *  \file       htdocs/pricelist/core/modules/modPriceList.class.php
 *  \ingroup    pricelist
 *  \brief      Description and activation file for module PriceList
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module PriceList
 */
class modPriceList extends DolibarrModules
{
    /**
     *   Constructor. Define names, constants, directories, boxes, permissions
     *
     *   @param      DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        global $langs,$conf;

        $this->db = $db;

        // Id for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 203105;
        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'pricelist';

        // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
        // It is used to group modules in module setup page
        $this->family = "products";
        // Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        // Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
        $this->description = "";
        // Possible values for version are: 'development', 'experimental', 'dolibarr' or version
        $this->version = '2.1.0';
        $this->url_last_version = 'https://dv.sm-2i.fr/pricelist.txt';
        $this->editor_name= 'SM2i';
        $this->editor_url= 'https://www.sm-2i.fr';
        // Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        // Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
        $this->special = 0;
        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        $this->picto='generic';

        // Defined all module parts (triggers, login, substitutions, menus, css, etc...)
        // for default path (eg: /pricelist/core/xxxxx) (0=disable, 1=enable)
        // for specific path of parts (eg: /pricelist/core/modules/barcode)
        // for specific css file (eg: /pricelist/css/pricelist.css.php)
        //$this->module_parts = array(
        //                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
        //							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
        //							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
        //							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
        //							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
        //                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
        //							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
        //							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
        //							'css' => array('/pricelist/css/pricelist.css.php'),	// Set this to relative path of css file if module has its own css file
        //							'js' => array('/pricelist/js/pricelist.js'),          // Set this to relative path of js file if module must load a js on all pages
        //							'hooks' => array('hookcontext1','hookcontext2')  	// Set here all hooks context managed by module
        //							'dir' => array('output' => 'othermodulename'),      // To force the default directories names
        //							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'! empty($conf->module1->enabled) && ! empty($conf->module2->enabled)', 'picto'=>'yourpicto@pricelist')) // Set here all workflow context managed by module
        //                        );
        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array('ordercard', 'propalcard', 'invoicecard', 'invoicereccard', 'productcard', 'thirdpartycard')
        );

        // Data directories to create when module is enabled.
        // Example: this->dirs = array("/pricelist/temp");
        $this->dirs = array();

        // Config pages. Put here list of php page, stored into pricelist/admin directory, to use to setup module.
        $this->config_page_url = array("setup.php@pricelist");

        // Dependencies
        $this->hidden = false;			// A condition to hide module
        $this->depends = array();		// List of modules id that must be enabled if this module is enabled
        $this->requiredby = array();	// List of modules id to disable if this one is disabled
        $this->conflictwith = array();	// List of modules id this module is in conflict with
        $this->phpmin = array(5,0);					// Minimum version of PHP required by module
        $this->need_dolibarr_version = array(6,0);	// Minimum version of Dolibarr required by module
        $this->langfiles = array("pricelist@pricelist");

        // Constants
        // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
        // Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
        //                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
        // );
        $this->const = array(
            0 => array('PRICELIST_CLONE_ON_CLONE_PRODUCT', 'chaine', '0', '', 0),
            1 => array('PRICELIST_SHOW_PRICES_TTC', 'chaine', '0', '', 0),
            2 => array('PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING', 'chaine', '0', '', 0),
        );

        // Array to add new pages in new tabs
        // Example: $this->tabs = array('objecttype:+tabname1:Title1:mylangfile@pricelist:$user->rights->pricelist->read:/pricelist/mynewtab1.php?id=__ID__',  					// To add a new tab identified by code tabname1
        //                              'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@pricelist:$user->rights->othermodule->read:/pricelist/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
        //                              'objecttype:-tabname:NU:conditiontoremove');                                                     										// To remove an existing tab identified by code tabname
        // where objecttype can be
        // 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
        // 'contact'          to add a tab in contact view
        // 'contract'         to add a tab in contract view
        // 'group'            to add a tab in group view
        // 'intervention'     to add a tab in intervention view
        // 'invoice'          to add a tab in customer invoice view
        // 'invoice_supplier' to add a tab in supplier invoice view
        // 'member'           to add a tab in fundation member view
        // 'opensurveypoll'	  to add a tab in opensurvey poll view
        // 'order'            to add a tab in customer order view
        // 'order_supplier'   to add a tab in supplier order view
        // 'payment'		  to add a tab in payment view
        // 'payment_supplier' to add a tab in supplier payment view
        // 'product'          to add a tab in product view
        // 'propal'           to add a tab in propal view
        // 'project'          to add a tab in project view
        // 'stock'            to add a tab in stock view
        // 'thirdparty'       to add a tab in third party view
        // 'user'             to add a tab in user view
        $this->tabs = array(
            'product:+pricelist:PriceLists:pricelist@pricelist:1:/pricelist/product.php?id=__ID__',
            'thirdparty:+pricelist:PriceLists:pricelist@pricelist:$object->client:/pricelist/customer.php?id=__ID__',
            'categories_customer:+pricelist:PriceLists:pricelist@pricelist:1:/pricelist/category.php?id=__ID__'
        );

        // Dictionaries
        if (! isset($conf->pricelist->enabled)) {
            $conf->pricelist=new stdClass();
            $conf->pricelist->enabled=0;
        }
        $this->dictionaries=array();
        /* Example:
        if (! isset($conf->pricelist->enabled)) $conf->pricelist->enabled=0;	// This is to avoid warnings
        $this->dictionaries=array(
            'langs'=>'mylangfile@pricelist',
            'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),		// List of tables we want to see into dictonnary editor
            'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),	// Request to select fields
            'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
            'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionary)
            'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
            'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array($conf->pricelist->enabled,$conf->pricelist->enabled,$conf->pricelist->enabled)												// Condition to show each dictionary
        );
        */

        // Boxes
        // Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes
        // Example:
        //$this->boxes=array(array(0=>array('file'=>'myboxa.php','note'=>'','enabledbydefaulton'=>'Home'),1=>array('file'=>'myboxb.php','note'=>''),2=>array('file'=>'myboxc.php','note'=>'')););

        // Permissions
        $this->rights = array();		// Permission array used by this module
        $r=0;

        // Add here list of permission defined by an id, a label, a boolean and two constant strings.
        // Example:
        // $this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
        // $this->rights[$r][1] = 'Permision label';	// Permission label
        // $this->rights[$r][3] = 1; 					// Permission by default for new user (0/1)
        // $this->rights[$r][4] = 'level1';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
        // $this->rights[$r][5] = 'level2';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
        // $r++;

        // Main menu entries
        $this->menu = array();			// List of menus to add
        $r=0;

        // Add here entries to declare new menus
        //
        // Example to declare a new Top Menu entry and its Left menu entry:
        // $this->menu[$r]=array(	'fk_menu'=>0,			                // Put 0 if this is a top menu
        //							'type'=>'top',			                // This is a Top menu entry
        //							'titre'=>'PriceList top menu',
        //							'mainmenu'=>'pricelist',
        //							'leftmenu'=>'pricelist',
        //							'url'=>'/pricelist/pagetop.php',
        //							'langs'=>'mylangfile@pricelist',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
        //							'position'=>100,
        //							'enabled'=>'$conf->pricelist->enabled',	// Define condition to show or hide menu entry. Use '$conf->pricelist->enabled' if entry must be visible if module is enabled.
        //							'perms'=>'1',			                // Use 'perms'=>'$user->rights->pricelist->level1->level2' if you want your menu with a permission rules
        //							'target'=>'',
        //							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
        // $r++;
        //
        // Example to declare a Left Menu entry into an existing Top menu entry:
        // $this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=xxx',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
        //							'type'=>'left',			                // This is a Left menu entry
        //							'titre'=>'PriceList left menu',
        //							'mainmenu'=>'xxx',
        //							'leftmenu'=>'pricelist',
        //							'url'=>'/pricelist/pagelevel2.php',
        //							'langs'=>'mylangfile@pricelist',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
        //							'position'=>100,
        //							'enabled'=>'$conf->pricelist->enabled',  // Define condition to show or hide menu entry. Use '$conf->pricelist->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
        //							'perms'=>'1',			                // Use 'perms'=>'$user->rights->pricelist->level1->level2' if you want your menu with a permission rules
        //							'target'=>'',
        //							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
        // $r++;

        // Exports
        $r=1;

        $langs->load('categories');

        $this->export_code[$r]=$this->rights_class.'_'.$r;
        $this->export_label[$r]='PriceLists';	// Translation key (used only if key ExportDataset_xxx_z not found)
        $this->export_enabled[$r]='1';                               // Condition to show export in list (ie: '$user->id==3'). Set to 1 to always show when module is enabled.
        $this->export_permission[$r]=array(array('produit', 'export'));
        $this->export_fields_array[$r]=array(
            's.rowid' => 'IdCompany',
            's.nom' => 'CompanyName',
            'cs.rowid' => 'CategId',
            'cs.label' => 'CustomersCategoriesShort',
            'p.rowid' => 'ProductId',
            'p.ref' => 'ProductRef',
            'p.label' => 'ProductLabel',
            'pl.rowid' => 'PriceListId',
            'pl.from_qty' => 'FromQtyLong',
            'pl.price' => 'PriceHT',
            'u.login' => 'User'
        );
        $this->export_entities_array[$r]=array(
            's.rowid' => 'company',
            's.nom' => 'company',
            'cs.rowid' => 'category',
            'cs.label' => 'category',
            'p.rowid' => 'product',
            'p.ref' => 'product',
            'p.label' => 'product',
            'pl.rowid' => 'PriceList',
            'pl.from_qty' => 'PriceList',
            'pl.price' => 'PriceList',
            'u.login' => 'user'
        );
        $this->export_sql_start[$r]='SELECT ';
        $this->export_sql_end[$r]  =' FROM '.MAIN_DB_PREFIX.'pricelist AS pl';
        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = pl.fk_soc';
        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'categorie AS cs ON cs.rowid = pl.fk_cat';
        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'product AS p ON p.rowid = pl.fk_product';
        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = pl.fk_user_creation';
        $this->export_sql_order[$r] =' ORDER BY s.nom';
        $r++;

        // Imports
        $r=1;

        $this->import_code[$r]=$this->rights_class.'_'.$r;
        $this->import_label[$r]='PriceLists';
        $this->import_icon[$r]=$this->picto;
        $this->import_entities_array[$r]=array();
        $this->import_tables_array[$r]=array('p'=>MAIN_DB_PREFIX.'pricelist');
        $this->import_fields_array[$r]=array(
            'p.rowid' => 'PriceListId',
            'p.fk_product' => 'ProductOrService*',
            'p.fk_soc' => 'CompanyName',
            'p.fk_cat' => 'CategId',
            'p.from_qty' => 'FromQtyLong*',
            'p.price' => 'PriceHT*',
            'p.fk_user_creation' => 'User*'
        );
        $this->import_convertvalue_array[$r]=array(
            'p.fk_soc'=>array('rule'=>'fetchidfromref', 'classfile'=>'/societe/class/societe.class.php', 'class'=>'Societe', 'method'=>'fetch', 'element'=>'ThirdParty'),
            'p.fk_product'=>array('rule'=>'fetchidfromref', 'classfile'=>'/product/class/product.class.php', 'class'=>'Product', 'method'=>'fetch', 'element'=>'Product'),
            'p.fk_user_creation'=>array('rule'=>'fetchidfromref', 'classfile'=>'/user/class/user.class.php', 'class'=>'User', 'method'=>'fetch', 'element'=>'User')
        );
        $this->import_updatekeys_array[$r] = array(
            'p.rowid'=>'Id'
        );
        $r++;
    }

    /**
     *		Function called when module is enabled.
     *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     *		It also creates data directories
     *
   *      @param      string	$options    Options when enabling module ('', 'noboxes')
     *      @return     int             	1 if OK, 0 if KO
     */
    public function init($options='')
    {
        $sql = array(
            "UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_soc = NULL WHERE fk_soc IN (0, -1)",
            "UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat = NULL WHERE fk_cat IN (0, -1)"
        );

        $result=$this->_load_tables('/pricelist/sql/');

        return $this->_init($sql, $options);
    }

    /**
     *		Function called when module is disabled.
     *      Remove from database constants, boxes and permissions from Dolibarr database.
     *		Data directories are not deleted
     *
   *      @param      string	$options    Options when enabling module ('', 'noboxes')
     *      @return     int             	1 if OK, 0 if KO
     */
    public function remove($options='')
    {
        $sql = array();

        return $this->_remove($sql, $options);
    }
}
