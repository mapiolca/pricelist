<?php
/*
 * Copyright (C) 2024 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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

dol_include_once('/pricelist/class/pricelist.class.php');

/**
 * Hooks for pricelist.
 */
class ActionsPriceList
{
	public $db;
	public $error = '';
	public $errors = array();
	public $results = array();
	public $resprints = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Apply prices before Dolibarr writes sales object lines.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param object             $object     Current object
	 * @param string             $action     Current action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user, $conf;

		$langs->load('pricelist@pricelist');

		$context = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
		$client = $this->getObjectThirdparty($object);
		if (!is_object($client)) {
			return 0;
		}

		if ($context == 'ordercard' && $this->hasRight($user, 'commande', 'creer')) {
			$this->handleAddOrUpdateLine($object, $action, $client, null, 'OrderLine', '/commande/class/commande.class.php');
			if ($action == 'altaupdatelines') {
				$this->updateOrderLines($object, $client);
			}
		} elseif ($context == 'propalcard' && $this->hasRight($user, 'propal', 'creer')) {
			$this->handleAddOrUpdateLine($object, $action, $client, $object, 'PropaleLigne', '/comm/propal/class/propal.class.php');
			if ($action == 'altaupdatelines') {
				$this->updatePropalLines($object, $client);
			}
		} elseif (in_array($context, array('invoicecard', 'invoicereccard')) && $this->hasRight($user, 'facture', 'creer')) {
			$this->handleAddOrUpdateLine($object, $action, $client, null, 'FactureLigne', '/compta/facture/class/facture.class.php');
			if ($action == 'altaupdatelines') {
				$this->updateInvoiceLines($object, $client);
			}
		} elseif ($context == 'contractcard' && $this->hasRight($user, 'contrat', 'creer')) {
			$this->handleContractAddOrUpdateLine($object, $action, $client);
			if ($action == 'altaupdatelines') {
				$this->updateContractLines($object, $client);
			}
		}

		return 0;
	}

	/**
	 * Add the native category type used by contracts when lmdbzoning does not do it.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param Categorie          $object     Category object
	 * @param string             $action     Current action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function constructCategory($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;

		if ((function_exists('isModEnabled') && isModEnabled('lmdbzoning')) || (!function_exists('isModEnabled') && !empty($conf->lmdbzoning->enabled))) {
			return 0;
		}

		$langs->load('pricelist@pricelist');
		$this->results = array(
			array(
				'id' => 450022,
				'code' => 'contract',
				'cat_fk' => 'contract',
				'cat_table' => 'contract',
				'obj_class' => 'Contrat',
				'obj_table' => 'contrat',
				'label' => 'Contract',
			),
		);
		$hookmanager->resArray = $this->results;

		return 0;
	}

	/**
	 * Add mass price refresh button.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param object             $object     Current object
	 * @param string             $action     Current action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		$context = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
		if (
			($context == 'ordercard' && $this->hasRight($user, 'commande', 'creer'))
			|| ($context == 'propalcard' && $this->hasRight($user, 'propal', 'creer'))
			|| ($context == 'contractcard' && $this->hasRight($user, 'contrat', 'creer'))
			|| (in_array($context, array('invoicecard', 'invoicereccard')) && $this->hasRight($user, 'facture', 'creer'))
		) {
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=altaupdatelines&token='.newToken().'">'.$langs->trans('PriceListUpdate').'</a>';
		}

		return 0;
	}

	/**
	 * Handle addline and updateline actions.
	 *
	 * @param object      $object       Current object
	 * @param string      $action       Current action
	 * @param object      $client       Thirdparty
	 * @param ?object     $sourceObject Source object
	 * @param string      $lineClass    Line class
	 * @param string      $classFile    Class file
	 * @return void
	 */
	private function handleAddOrUpdateLine($object, $action, $client, $sourceObject, $lineClass, $classFile)
	{
		global $conf;

		if ($action == 'addline') {
			if (GETPOST('prod_entry_mode') == 'free') {
				return;
			}
			if (!empty($conf->global->PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING) && GETPOSTINT('price_ht') != 0) {
				return;
			}

			$this->applyPriceToPostFromPriceList(GETPOSTINT('idprod'), $client, GETPOST('qty'), $sourceObject);
			return;
		}

		if (!in_array($action, array('updateligne', 'updateline'))) {
			return;
		}

		$lineid = GETPOSTINT('lineid');
		if ($lineid <= 0) {
			return;
		}
		dol_include_once($classFile);
		if (!class_exists($lineClass)) {
			return;
		}

		$line = new $lineClass($this->db);
		if ($line->fetch($lineid) <= 0) {
			return;
		}

		$qty = GETPOST('qty');
		if ((float) $line->qty == (float) $qty) {
			return;
		}

		$idprod = GETPOSTINT('productid');
		if ($idprod <= 0 && !empty($line->fk_product)) {
			$idprod = (int) $line->fk_product;
		}
		if ($idprod <= 0) {
			return;
		}

		$this->applyPriceToPostFromPriceList($idprod, $client, $qty, $sourceObject);
	}

	/**
	 * Handle contract addline and updateline actions.
	 *
	 * @param object $object Current contract
	 * @param string $action Current action
	 * @param object $client Thirdparty
	 * @return void
	 */
	private function handleContractAddOrUpdateLine($object, $action, $client)
	{
		global $conf;

		if ($action == 'addline') {
			if (GETPOST('prod_entry_mode') == 'free') {
				return;
			}
			if (!empty($conf->global->PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING) && GETPOSTINT('price_ht') != 0) {
				return;
			}

			$this->applyPriceToPostFromPriceList(GETPOSTINT('idprod'), $client, GETPOST('qty'), $object);
			return;
		}

		if (!in_array($action, array('updateline', 'updateligne'))) {
			return;
		}

		$lineid = GETPOSTINT('elrowid');
		if ($lineid <= 0) {
			$lineid = GETPOSTINT('rowid');
		}
		if ($lineid <= 0) {
			$lineid = GETPOSTINT('lineid');
		}
		if ($lineid <= 0) {
			return;
		}

		dol_include_once('/contrat/class/contrat.class.php');
		if (!class_exists('ContratLigne')) {
			return;
		}

		$line = new ContratLigne($this->db);
		if ($line->fetch($lineid) <= 0) {
			return;
		}

		$qty = GETPOST('elqty');
		if (!dol_strlen($qty)) {
			$qty = $line->qty;
		}

		$idprod = GETPOSTINT('idprod');
		if ($idprod <= 0 && !empty($line->fk_product)) {
			$idprod = (int) $line->fk_product;
		}
		if ($idprod <= 0) {
			return;
		}

		$this->applyContractLinePriceToPostFromPriceList($idprod, $client, $qty, $object);
	}

	/**
	 * Apply price list values to POST before Dolibarr handles the line action.
	 *
	 * @param int     $idprod       Product id
	 * @param object  $client       Thirdparty
	 * @param mixed   $qty          Quantity
	 * @param ?object $sourceObject Source object
	 * @return void
	 */
	private function applyPriceToPostFromPriceList($idprod, $client, $qty, $sourceObject)
	{
		global $langs;

		if ($idprod <= 0) {
			return;
		}

		$pricelist = new PriceList($this->db);
		$obj = $pricelist->get_price($idprod, $client, $qty, $sourceObject);
		if (!is_int($obj) && $this->applyPriceToPost($obj)) {
			setEventMessage($langs->trans('PriceListInsert'));
		}
	}

	/**
	 * Apply price list values to the contract line edit POST fields.
	 *
	 * @param int    $idprod Product id
	 * @param object $client Thirdparty
	 * @param mixed  $qty    Quantity
	 * @param object $object Contract object
	 * @return void
	 */
	private function applyContractLinePriceToPostFromPriceList($idprod, $client, $qty, $object)
	{
		global $langs;

		if ($idprod <= 0) {
			return;
		}

		$pricelist = new PriceList($this->db);
		$obj = $pricelist->get_price($idprod, $client, $qty, $object);
		if (!is_int($obj) && $this->applyPriceToContractLinePost($obj)) {
			setEventMessage($langs->trans('PriceListInsert'));
		}
	}

	/**
	 * Apply a price row to POST.
	 *
	 * @param stdClass $obj Price row
	 * @return bool
	 */
	private function applyPriceToPost($obj)
	{
		if (dol_strlen($obj->price)) {
			$_POST['price_ht'] = price($obj->price);
		} elseif (dol_strlen($obj->tx_discount)) {
			$_POST['remise_percent'] = price($obj->tx_discount);
		}

		if (dol_strlen($obj->cost_price)) {
			$costPrice = price($obj->cost_price);
			$_POST['buying_price'] = $costPrice;
			$_POST['pa_ht'] = $costPrice;
		}

		return true;
	}

	/**
	 * Apply a price row to Dolibarr contract line edit fields.
	 *
	 * @param stdClass $obj Price row
	 * @return bool
	 */
	private function applyPriceToContractLinePost($obj)
	{
		if (dol_strlen($obj->price)) {
			$_POST['elprice'] = price($obj->price);
		} elseif (dol_strlen($obj->tx_discount)) {
			$_POST['elremise_percent'] = price($obj->tx_discount);
		}

		if (dol_strlen($obj->cost_price)) {
			$_POST['buying_price'] = price($obj->cost_price);
		}

		return true;
	}

	/**
	 * Update order lines.
	 *
	 * @param object $object Order
	 * @param object $client Thirdparty
	 * @return void
	 */
	private function updateOrderLines($object, $client)
	{
		global $langs;

		$pricelist = new PriceList($this->db);
		$updatedLines = 0;
		foreach ($object->lines as $line) {
			if (empty($line->fk_product)) {
				continue;
			}

			$obj = $pricelist->get_price($line->fk_product, $client, $line->qty);
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line);
			$res = $object->updateline(
				$line->id,
				$line->description,
				$values['pu'],
				$line->qty,
				$values['remise_percent'],
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
				$values['pa_ht'],
				$line->label,
				$line->special_code,
				0,
				$line->fk_unit,
				$line->multicurrency_subprice
			);
			$updatedLines += $this->countUpdatedLine($res, $object);
		}

		if ($updatedLines > 0) {
			setEventMessage($langs->trans('PriceListInsert'));
		}
	}

	/**
	 * Update proposal lines.
	 *
	 * @param object $object Proposal
	 * @param object $client Thirdparty
	 * @return void
	 */
	private function updatePropalLines($object, $client)
	{
		global $langs;

		$pricelist = new PriceList($this->db);
		$updatedLines = 0;
		foreach ($object->lines as $line) {
			if (empty($line->fk_product)) {
				continue;
			}

			$obj = $pricelist->get_price($line->fk_product, $client, $line->qty, $object);
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line);
			$res = $object->updateline(
				$line->id,
				$values['pu'],
				$line->qty,
				$values['remise_percent'],
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
				$values['pa_ht'],
				$line->label,
				$line->product_type,
				$line->date_start,
				$line->date_end,
				0,
				$line->fk_unit,
				$line->multicurrency_subprice
			);
			$updatedLines += $this->countUpdatedLine($res, $object);
		}

		if ($updatedLines > 0) {
			setEventMessage($langs->trans('PriceListInsert'));
		}
	}

	/**
	 * Update invoice lines.
	 *
	 * @param object $object Invoice
	 * @param object $client Thirdparty
	 * @return void
	 */
	private function updateInvoiceLines($object, $client)
	{
		global $langs;

		$pricelist = new PriceList($this->db);
		$updatedLines = 0;
		foreach ($object->lines as $line) {
			if (empty($line->fk_product)) {
				continue;
			}

			$obj = $pricelist->get_price($line->fk_product, $client, $line->qty);
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line);
			$res = $object->updateline(
				$line->id,
				$line->desc,
				$values['pu'],
				$line->qty,
				$values['remise_percent'],
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
				$values['pa_ht'],
				$line->label,
				$line->special_code,
				0,
				$line->situation_percent,
				$line->fk_unit,
				$line->multicurrency_subprice
			);
			$updatedLines += $this->countUpdatedLine($res, $object);
		}

		if ($updatedLines > 0) {
			setEventMessage($langs->trans('PriceListInsert'));
		}
	}

	/**
	 * Update contract lines.
	 *
	 * @param object $object Contract
	 * @param object $client Thirdparty
	 * @return void
	 */
	private function updateContractLines($object, $client)
	{
		global $langs;

		$pricelist = new PriceList($this->db);
		$updatedLines = 0;
		foreach ($object->lines as $line) {
			if (empty($line->fk_product)) {
				continue;
			}

			$obj = $pricelist->get_price($line->fk_product, $client, $line->qty, $object);
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line);
			$res = $object->updateline(
				$line->id,
				$this->getLineDescription($line),
				$values['pu'],
				$line->qty,
				$values['remise_percent'],
				$line->date_start,
				$line->date_end,
				$line->tva_tx,
				$line->localtax1_tx,
				$line->localtax2_tx,
				isset($line->date_start_real) ? $line->date_start_real : '',
				isset($line->date_end_real) ? $line->date_end_real : '',
				'HT',
				$line->info_bits,
				$line->fk_fournprice,
				$values['pa_ht'],
				isset($line->array_options) ? $line->array_options : array(),
				$line->fk_unit,
				isset($line->rang) ? $line->rang : 0
			);
			$updatedLines += $this->countUpdatedLine($res, $object);
		}

		if ($updatedLines > 0) {
			setEventMessage($langs->trans('PriceListInsert'));
		}
	}

	/**
	 * Return line price values.
	 *
	 * @param stdClass $obj  Price row
	 * @param object   $line Line object
	 * @return array<string,mixed>
	 */
	private function getLinePriceValues($obj, $line)
	{
		$pu = $this->getLineSubprice($line);
		$remisePercent = isset($line->remise_percent) ? $line->remise_percent : 0;
		if (dol_strlen($obj->price)) {
			$pu = price($obj->price);
		} elseif (dol_strlen($obj->tx_discount)) {
			$remisePercent = price($obj->tx_discount);
		}

		$pa = isset($line->pa_ht) ? $line->pa_ht : null;
		if (dol_strlen($obj->cost_price)) {
			$pa = price($obj->cost_price);
		}

		return array(
			'pu' => $pu,
			'remise_percent' => $remisePercent,
			'pa_ht' => $pa,
		);
	}

	/**
	 * Count an updated line or display errors.
	 *
	 * @param int    $res    Update result
	 * @param object $object Updated object
	 * @return int
	 */
	private function countUpdatedLine($res, $object)
	{
		if ($res > 0) {
			return 1;
		}

		setEventMessages($object->error, $object->errors, 'errors');
		return 0;
	}

	/**
	 * Return line subprice.
	 *
	 * @param object $line Line object
	 * @return mixed
	 */
	private function getLineSubprice($line)
	{
		if (isset($line->subprice)) {
			return $line->subprice;
		}
		if (isset($line->price_ht)) {
			return $line->price_ht;
		}

		return 0;
	}

	/**
	 * Return line description.
	 *
	 * @param object $line Line object
	 * @return string
	 */
	private function getLineDescription($line)
	{
		if (isset($line->desc)) {
			return $line->desc;
		}
		if (isset($line->description)) {
			return $line->description;
		}

		return '';
	}

	/**
	 * Return thirdparty linked to the current object.
	 *
	 * @param object $object Current object
	 * @return ?object
	 */
	private function getObjectThirdparty($object)
	{
		if (method_exists($object, 'fetch_thirdparty')) {
			$object->fetch_thirdparty();
		}
		if (!empty($object->thirdparty) && is_object($object->thirdparty)) {
			return $object->thirdparty;
		}
		if (!empty($object->client) && is_object($object->client)) {
			return $object->client;
		}

		$socid = 0;
		if (!empty($object->socid)) {
			$socid = (int) $object->socid;
		} elseif (!empty($object->fk_soc)) {
			$socid = (int) $object->fk_soc;
		}
		if ($socid <= 0) {
			return null;
		}

		dol_include_once('/societe/class/societe.class.php');
		if (!class_exists('Societe')) {
			return null;
		}
		$soc = new Societe($this->db);
		if ($soc->fetch($socid) <= 0) {
			return null;
		}

		return $soc;
	}

	/**
	 * Check a Dolibarr right with old and new APIs.
	 *
	 * @param User   $user   User
	 * @param string $module Module key
	 * @param string $right  Right key
	 * @return bool
	 */
	private function hasRight($user, $module, $right)
	{
		if (method_exists($user, 'hasRight')) {
			return (bool) $user->hasRight($module, $right);
		}

		return !empty($user->rights->$module->$right);
	}
}
