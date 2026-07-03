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
		// EN: Align module identifiers with Les Métiers du Bâtiment. FR: Aligner les identifiants du module avec Les Métiers du Bâtiment.
		$this->numero = 450008;
        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'pricelist';

        // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
        // It is used to group modules in module setup page
		// EN: Group the module under the Les Métiers du Bâtiment family. FR: Regrouper le module sous la famille Les Métiers du Bâtiment.
		$this->family = "Les Métiers du Bâtiment";
        // Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        // Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		// EN: Provide a bilingual module description. FR: Fournir une description bilingue du module.
		$this->description = "Manage selling and cost price lists / Gestion des tarifs de vente et de revient";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
			$this->version = '2.2.0';
		$this->url_last_version = 'https://dv.sm-2i.fr/pricelist.txt';
		// EN: Reference the new editor information. FR: Référencer les nouvelles informations de l'éditeur.
		$this->editor_name= 'Les Métiers du Bâtiment';
		$this->editor_url= 'https://lesmetiersdubatiment.fr';
        // Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        // Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
        $this->special = 0;
        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// EN: Use the currency pictogram to match pricing features. FR: Utiliser le pictogramme monnaie pour refléter les fonctionnalités tarifaires.
		$this->picto='currency';

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
        //							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'isModEnabled("module1") && isModEnabled("module2")', 'picto'=>'yourpicto@pricelist')) // Set here all workflow context managed by module
        //                        );
	        $this->module_parts = array(
	            'triggers' => 1,
	            'hooks' => array('category', 'ordercard', 'propalcard', 'contractcard', 'invoicecard', 'invoicereccard', 'productcard', 'thirdpartycard')
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
	        $this->phpmin = array(8,0);					// Minimum version of PHP required by module
	        $this->need_dolibarr_version = array(20,0);	// Minimum version of Dolibarr required by module
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
            3 => array('PRICELIST_DOCUMENT_CATEGORY_PRIORITY', 'chaine', '1', '', 0),
            4 => array('PRICELIST_ENABLE_CONTRACT_CATEGORIES', 'chaine', '0', '', 0),
        );

        // Array to add new pages in new tabs
        // Example: $this->tabs = array('objecttype:+tabname1:Title1:mylangfile@pricelist:$user->hasRight("pricelist", "read"):/pricelist/mynewtab1.php?id=__ID__',  					// To add a new tab identified by code tabname1
        //                              'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@pricelist:$user->hasRight("othermodule", "read"):/pricelist/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
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
		$readProductPricesCondition = '((getDolGlobalInt("MAIN_USE_ADVANCED_PERMS", 0) > 0 && $user->hasRight("product", "product_advance", "read_prices")) || (getDolGlobalInt("MAIN_USE_ADVANCED_PERMS", 0) <= 0 && $user->hasRight("product", "read")))';
		$readServicePricesCondition = '((getDolGlobalInt("MAIN_USE_ADVANCED_PERMS", 0) > 0 && $user->hasRight("service", "service_advance", "read_prices")) || (getDolGlobalInt("MAIN_USE_ADVANCED_PERMS", 0) <= 0 && $user->hasRight("service", "read")))';
		$readPricesCondition = '($user->admin || '.$readProductPricesCondition.' || '.$readServicePricesCondition.')';
		$contractCategoryCondition = '(getDolGlobalInt("PRICELIST_ENABLE_CONTRACT_CATEGORIES", 0) > 0)';
		$this->tabs = array(
			'product:+pricelist:PriceLists:pricelist@pricelist:'.$readPricesCondition.':/pricelist/product.php?id=__ID__',
			'thirdparty:+pricelist:PriceLists:pricelist@pricelist:($object->client && '.$readPricesCondition.'):/pricelist/customer.php?id=__ID__',
			'categories_customer:+pricelist:PriceLists:pricelist@pricelist:'.$readPricesCondition.':/pricelist/category.php?id=__ID__&type=customer',
			'categories_propal:+pricelist:PriceLists:pricelist@pricelist:(version_compare(DOL_VERSION, \'23.0.0\', \'>=\') && '.$readPricesCondition.'):/pricelist/category.php?id=__ID__&type=propal',
			'categories_order:+pricelist:PriceLists:pricelist@pricelist:(version_compare(DOL_VERSION, \'22.0.0\', \'>=\') && '.$readPricesCondition.'):/pricelist/category.php?id=__ID__&type=order',
			'categories_invoice:+pricelist:PriceLists:pricelist@pricelist:(version_compare(DOL_VERSION, \'22.0.0\', \'>=\') && '.$readPricesCondition.'):/pricelist/category.php?id=__ID__&type=invoice',
			'categories_contract:+pricelist:PriceLists:pricelist@pricelist:('.$contractCategoryCondition.' && '.$readPricesCondition.'):/pricelist/category.php?id=__ID__&type=contract'
		);

        // Dictionaries
        $this->dictionaries=array();
        /* Example:
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
            'tabcond'=>array(isModEnabled('pricelist'),isModEnabled('pricelist'),isModEnabled('pricelist'))												// Condition to show each dictionary
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
        // $this->rights[$r][4] = 'level1';				// In php code, permission will be checked by test if ($user->hasRight('permkey', 'level1', 'level2'))
        // $this->rights[$r][5] = 'level2';				// In php code, permission will be checked by test if ($user->hasRight('permkey', 'level1', 'level2'))
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
        //							'enabled'=>'isModEnabled("pricelist")',	// Define condition to show or hide menu entry. Use 'isModEnabled("pricelist")' if entry must be visible if module is enabled.
        //							'perms'=>'1',			                // Use 'perms'=>'$user->hasRight("pricelist", "level1", "level2")' if you want your menu with a permission rules
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
        //							'enabled'=>'isModEnabled("pricelist")',  // Define condition to show or hide menu entry. Use 'isModEnabled("pricelist")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
        //							'perms'=>'1',			                // Use 'perms'=>'$user->hasRight("pricelist", "level1", "level2")' if you want your menu with a permission rules
        //							'target'=>'',
        //							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
        // $r++;

        // Exports
        $r=1;

        $langs->load('categories');
        $hasPropalCategories = version_compare(DOL_VERSION, '23.0.0', '>=');
        $hasOrderInvoiceCategories = version_compare(DOL_VERSION, '22.0.0', '>=');
        $hasContractCategories = getDolGlobalInt('PRICELIST_ENABLE_CONTRACT_CATEGORIES', 0) > 0;

        $this->export_code[$r]=$this->rights_class.'_'.$r;
        $this->export_label[$r]='PriceLists';	// Translation key (used only if key ExportDataset_xxx_z not found)
        $this->export_enabled[$r]='1';                               // Condition to show export in list (ie: '$user->id==3'). Set to 1 to always show when module is enabled.
        $this->export_permission[$r]=array(array('produit', 'export'));
	        $this->export_fields_array[$r]=array(
	            'pl.entity' => 'Entity',
	            's.rowid' => 'IdCompany',
	            's.nom' => 'CompanyName',
	            'cs.rowid' => 'CategId',
	            'cs.label' => 'CustomersCategoriesShort',
	            'csp.rowid' => 'PropalCategoryId',
	            'csp.label' => 'PropalCategory',
	            'cso.rowid' => 'OrderCategoryId',
	            'cso.label' => 'OrderCategory',
	            'csi.rowid' => 'InvoiceCategoryId',
	            'csi.label' => 'InvoiceCategory',
	            'csc.rowid' => 'ContractCategoryId',
	            'csc.label' => 'ContractCategory',
	            'p.rowid' => 'ProductId',
            'p.ref' => 'ProductRef',
            'p.label' => 'ProductLabel',
            'pl.rowid' => 'PriceListId',
            'pl.from_qty' => 'FromQtyLong',
            'pl.price' => 'PriceHT',
            'pl.tx_discount' => 'Discount',
            'pl.cost_price' => 'CostPriceHT',
            'pl.use_product_cost_price' => 'UseProductCostPrice',
            'u.login' => 'User'
        );
	        $this->export_entities_array[$r]=array(
	            'pl.entity' => 'PriceList',
	            's.rowid' => 'company',
            's.nom' => 'company',
	            'cs.rowid' => 'category',
	            'cs.label' => 'category',
	            'csp.rowid' => 'category',
	            'csp.label' => 'category',
	            'cso.rowid' => 'category',
	            'cso.label' => 'category',
	            'csi.rowid' => 'category',
	            'csi.label' => 'category',
	            'csc.rowid' => 'category',
	            'csc.label' => 'category',
            'p.rowid' => 'product',
            'p.ref' => 'product',
            'p.label' => 'product',
            'pl.rowid' => 'PriceList',
            'pl.from_qty' => 'PriceList',
            'pl.price' => 'PriceList',
            'pl.tx_discount' => 'PriceList',
            'pl.cost_price' => 'PriceList',
            'pl.use_product_cost_price' => 'PriceList',
            'u.login' => 'user'
        );
		if (!$hasPropalCategories) {
			unset($this->export_fields_array[$r]['csp.rowid'], $this->export_fields_array[$r]['csp.label']);
			unset($this->export_entities_array[$r]['csp.rowid'], $this->export_entities_array[$r]['csp.label']);
		}
		if (!$hasOrderInvoiceCategories) {
			unset($this->export_fields_array[$r]['cso.rowid'], $this->export_fields_array[$r]['cso.label'], $this->export_fields_array[$r]['csi.rowid'], $this->export_fields_array[$r]['csi.label']);
			unset($this->export_entities_array[$r]['cso.rowid'], $this->export_entities_array[$r]['cso.label'], $this->export_entities_array[$r]['csi.rowid'], $this->export_entities_array[$r]['csi.label']);
		}
		if (!$hasContractCategories) {
			unset($this->export_fields_array[$r]['csc.rowid'], $this->export_fields_array[$r]['csc.label']);
			unset($this->export_entities_array[$r]['csc.rowid'], $this->export_entities_array[$r]['csc.label']);
		}
        $this->export_sql_start[$r]='SELECT ';
	        $this->export_sql_end[$r]  =' FROM '.MAIN_DB_PREFIX.'pricelist AS pl';
	        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = pl.fk_soc';
	        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'categorie AS cs ON cs.rowid = pl.fk_cat';
		if ($hasPropalCategories) {
	        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'categorie AS csp ON csp.rowid = pl.fk_cat_propal';
		}
		if ($hasOrderInvoiceCategories) {
	        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'categorie AS cso ON cso.rowid = pl.fk_cat_order';
	        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'categorie AS csi ON csi.rowid = pl.fk_cat_invoice';
		}
		if ($hasContractCategories) {
	        $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'categorie AS csc ON csc.rowid = pl.fk_cat_contract';
		}
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
	            'p.entity' => 'Entity',
	            'p.fk_product' => 'ProductOrService*',
	            'p.fk_soc' => 'CompanyName',
	            'p.fk_cat' => 'CategId',
	            'p.fk_cat_propal' => 'PropalCategoryId',
	            'p.fk_cat_order' => 'OrderCategoryId',
	            'p.fk_cat_invoice' => 'InvoiceCategoryId',
	            'p.fk_cat_contract' => 'ContractCategoryId',
	            'p.from_qty' => 'FromQtyLong*',
            'p.price' => 'PriceHT',
            'p.tx_discount' => 'Discount',
            'p.cost_price' => 'CostPriceHT',
            'p.use_product_cost_price' => 'UseProductCostPrice',
            'p.fk_user_creation' => 'User*'
        );
		if (!$hasPropalCategories) {
			unset($this->import_fields_array[$r]['p.fk_cat_propal']);
		}
		if (!$hasOrderInvoiceCategories) {
			unset($this->import_fields_array[$r]['p.fk_cat_order'], $this->import_fields_array[$r]['p.fk_cat_invoice']);
		}
		if (!$hasContractCategories) {
			unset($this->import_fields_array[$r]['p.fk_cat_contract']);
		}
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
	            "UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat = NULL WHERE fk_cat IN (0, -1)",
	            "UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_propal = NULL WHERE fk_cat_propal IN (0, -1)",
	            "UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_order = NULL WHERE fk_cat_order IN (0, -1)",
	            "UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_invoice = NULL WHERE fk_cat_invoice IN (0, -1)",
	            "UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_contract = NULL WHERE fk_cat_contract IN (0, -1)",
	            "UPDATE ".MAIN_DB_PREFIX."pricelist SET entity = 1 WHERE entity IS NULL OR entity < 1"
	        );

	        $result=$this->_load_tables('/pricelist/sql/');
			$this->syncPriceListSchema();

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

	/**
	 * Keep PriceList schema, indexes and history table synchronized for existing installations.
	 *
	 * @return void
	 */
	private function syncPriceListSchema()
	{
		if ($this->tableExists('pricelist')) {
			$this->addColumnIfMissing('pricelist', 'fk_cat_order', 'fk_cat_order integer DEFAULT NULL AFTER fk_cat_propal');
			$this->addColumnIfMissing('pricelist', 'fk_cat_invoice', 'fk_cat_invoice integer DEFAULT NULL AFTER fk_cat_order');
			$this->addColumnIfMissing('pricelist', 'use_product_cost_price', 'use_product_cost_price tinyint DEFAULT 0 NOT NULL AFTER cost_price');
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_entity', array('entity'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_product', array('fk_product'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_societe', array('fk_soc'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_categorie', array('fk_cat'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_categorie_propal', array('fk_cat_propal'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_categorie_order', array('fk_cat_order'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_categorie_invoice', array('fk_cat_invoice'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_categorie_contract', array('fk_cat_contract'));
			$this->addIndexIfMissing('pricelist', 'idx_pricelist_user_creation', array('fk_user_creation'));
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_soc = NULL WHERE fk_soc IN (0, -1)");
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat = NULL WHERE fk_cat IN (0, -1)");
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_propal = NULL WHERE fk_cat_propal IN (0, -1)");
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_order = NULL WHERE fk_cat_order IN (0, -1)");
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_invoice = NULL WHERE fk_cat_invoice IN (0, -1)");
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."pricelist SET fk_cat_contract = NULL WHERE fk_cat_contract IN (0, -1)");
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."pricelist SET entity = 1 WHERE entity IS NULL OR entity < 1");
		}

		if (!$this->tableExists('pricelist_log')) {
			$this->db->query('CREATE TABLE '.MAIN_DB_PREFIX.'pricelist_log (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, fk_pricelist integer NOT NULL, datec datetime NOT NULL, fk_user integer DEFAULT NULL, change_type varchar(16) NOT NULL, fk_product integer NOT NULL, fk_soc integer DEFAULT NULL, fk_cat integer DEFAULT NULL, fk_cat_propal integer DEFAULT NULL, fk_cat_order integer DEFAULT NULL, fk_cat_invoice integer DEFAULT NULL, fk_cat_contract integer DEFAULT NULL, from_qty double NOT NULL, price double DEFAULT NULL, tx_discount double DEFAULT NULL, cost_price double DEFAULT NULL, use_product_cost_price tinyint DEFAULT 0 NOT NULL, import_key varchar(14) DEFAULT NULL) ENGINE=innodb');
		}
		if ($this->tableExists('pricelist_log')) {
			$this->addColumnIfMissing('pricelist_log', 'use_product_cost_price', 'use_product_cost_price tinyint DEFAULT 0 NOT NULL AFTER cost_price');
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_entity', array('entity'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_pricelist', array('fk_pricelist'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_datec', array('datec'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_user', array('fk_user'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_product', array('fk_product'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_categorie', array('fk_cat'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_categorie_propal', array('fk_cat_propal'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_categorie_order', array('fk_cat_order'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_categorie_invoice', array('fk_cat_invoice'));
			$this->addIndexIfMissing('pricelist_log', 'idx_pricelist_log_categorie_contract', array('fk_cat_contract'));
			$this->seedInitialPriceListHistory();
		}

		if (!$this->tableExists('categorie_contract')) {
			$this->db->query('CREATE TABLE '.MAIN_DB_PREFIX.'categorie_contract (fk_categorie integer NOT NULL, fk_contract integer NOT NULL, import_key varchar(14)) ENGINE=innodb');
		}
		if ($this->tableExists('categorie_contract')) {
			$this->addPrimaryKeyIfMissing('categorie_contract', array('fk_categorie', 'fk_contract'));
			$this->addIndexIfMissing('categorie_contract', 'idx_categorie_contract_fk_categorie', array('fk_categorie'));
			$this->addIndexIfMissing('categorie_contract', 'idx_categorie_contract_fk_contract', array('fk_contract'));
		}
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $tableElement Table without MAIN_DB_PREFIX
	 * @return bool
	 */
	private function tableExists($tableElement)
	{
		$sql = "SHOW TABLES LIKE '".$this->db->escape(MAIN_DB_PREFIX.$tableElement)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Add an index when missing.
	 *
	 * @param string       $tableElement Table without MAIN_DB_PREFIX
	 * @param string       $indexName    Index name
	 * @param array<int,string> $columns Columns
	 * @return void
	 */
	private function addIndexIfMissing($tableElement, $indexName, $columns)
	{
		if ($this->indexExists($tableElement, $indexName)) {
			return;
		}
		$sql = 'ALTER TABLE '.MAIN_DB_PREFIX.$tableElement.' ADD INDEX '.$indexName.' ('.implode(', ', $columns).')';
		$this->db->query($sql);
	}

	/**
	 * Add a column when missing.
	 *
	 * @param string $tableElement     Table without MAIN_DB_PREFIX
	 * @param string $fieldName        Field name
	 * @param string $fieldDeclaration Field declaration
	 * @return void
	 */
	private function addColumnIfMissing($tableElement, $fieldName, $fieldDeclaration)
	{
		if ($this->fieldExists($tableElement, $fieldName)) {
			return;
		}
		$sql = 'ALTER TABLE '.MAIN_DB_PREFIX.$tableElement.' ADD COLUMN '.$fieldDeclaration;
		$this->db->query($sql);
	}

	/**
	 * Add a primary key when missing.
	 *
	 * @param string            $tableElement Table without MAIN_DB_PREFIX
	 * @param array<int,string> $columns      Columns
	 * @return void
	 */
	private function addPrimaryKeyIfMissing($tableElement, $columns)
	{
		if ($this->indexExists($tableElement, 'PRIMARY')) {
			return;
		}
		$sql = 'ALTER TABLE '.MAIN_DB_PREFIX.$tableElement.' ADD PRIMARY KEY ('.implode(', ', $columns).')';
		$this->db->query($sql);
	}

	/**
	 * Check if an index exists.
	 *
	 * @param string $tableElement Table without MAIN_DB_PREFIX
	 * @param string $indexName    Index name
	 * @return bool
	 */
	private function indexExists($tableElement, $indexName)
	{
		$sql = "SHOW INDEX FROM ".MAIN_DB_PREFIX.$tableElement." WHERE Key_name = '".$this->db->escape($indexName)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Check if a field exists.
	 *
	 * @param string $tableElement Table without MAIN_DB_PREFIX
	 * @param string $fieldName    Field name
	 * @return bool
	 */
	private function fieldExists($tableElement, $fieldName)
	{
		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX.$tableElement." LIKE '".$this->db->escape($fieldName)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Seed one INITIAL history row for existing price list lines.
	 *
	 * @return void
	 */
	private function seedInitialPriceListHistory()
	{
		if (!$this->tableExists('pricelist') || !$this->tableExists('pricelist_log')) {
			return;
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."pricelist_log (";
		$sql .= "entity, fk_pricelist, datec, fk_user, change_type, fk_product, fk_soc, fk_cat, fk_cat_propal, fk_cat_order, fk_cat_invoice, fk_cat_contract, from_qty, price, tx_discount, cost_price, use_product_cost_price";
		$sql .= ") SELECT";
		$sql .= " p.entity, p.rowid, '".$this->db->idate(dol_now())."', p.fk_user_creation, 'INITIAL', p.fk_product, p.fk_soc, p.fk_cat, p.fk_cat_propal, p.fk_cat_order, p.fk_cat_invoice, p.fk_cat_contract, p.from_qty, p.price, p.tx_discount, p.cost_price, p.use_product_cost_price";
		$sql .= " FROM ".MAIN_DB_PREFIX."pricelist as p";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."pricelist_log as l ON l.fk_pricelist = p.rowid";
		$sql .= " WHERE l.rowid IS NULL";

		$this->db->query($sql);
	}
	}
