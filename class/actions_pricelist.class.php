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
dol_include_once('/pricelist/lib/pricelist.lib.php');

/**
 * Hooks for pricelist.
 */
class ActionsPriceList
{
	public $db;
	public $error = '';
	public $errors = array();
	public $warnings = array();
	public $results = array();
	public $resprints = '';

	/**
	 * Preserve native CSV/XLSX mapping, counters and simulation transaction.
	 * @param array<string,mixed> $parameters Native import hook parameters
	 * @param object|null $object Unused native hook object
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0 for other datasets, 1 when handled, -1 on rejected row
	 */
	public function ImportInsert($parameters, &$object, &$action, $hookmanager)
	{
		global $user;
		if (($parameters['datatoimport'] ?? '') !== 'pricelist_1') {
			return 0;
		}
		require_once __DIR__.'/pricelistimport.class.php';
		$values = array();
		foreach ($parameters['array_match_file_to_database'] as $column => $field) {
			if (strpos($field, 'p.') !== 0) {
				continue;
			}
			$cell = $parameters['arrayrecord'][(int) $column - 1] ?? array();
			$values[substr($field, 2)] = isset($cell['val']) && is_scalar($cell['val']) ? trim((string) $cell['val']) : '';
		}
		$importer = new PriceListImport($this->db);
		$result = $importer->importRow($values, $user, in_array('p.rowid', $parameters['updatekeys'], true), (string) $parameters['importid'], (int) $parameters['step'] !== 5);
		if ($result < 0) {
			$this->error = $importer->error;
			return -1;
		}
		$parameters['nbok']++;
		if ($importer->updated) {
			$parameters['obj']->nbupdate++;
		} else {
			$parameters['obj']->nbinsert++;
		}
		return 1;
	}

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
		if (!isModEnabled('pricelist') || !in_array($context, array('ordercard', 'propalcard', 'contractcard', 'invoicecard', 'invoicereccard'), true)) {
			return 0;
		}
		$client = $this->getObjectThirdparty($object);
		if (!is_object($client)) {
			return 0;
		}

		if ($context == 'ordercard' && ($user->hasRight('commande', 'creer'))) {
			$this->handleAddOrUpdateLine($object, $action, $client, $object, 'OrderLine', '/commande/class/commande.class.php');
			if ($action == 'altaupdatelines') {
				$this->updateOrderLines($object, $client);
			}
		} elseif ($context == 'propalcard' && ($user->hasRight('propal', 'creer'))) {
			$this->handleAddOrUpdateLine($object, $action, $client, $object, 'PropaleLigne', '/comm/propal/class/propal.class.php');
			if ($action == 'altaupdatelines') {
				$this->updatePropalLines($object, $client);
			}
		} elseif (in_array($context, array('invoicecard', 'invoicereccard')) && ($user->hasRight('facture', 'creer'))) {
			$this->handleAddOrUpdateLine($object, $action, $client, $object, $context === 'invoicereccard' ? 'FactureLigneRec' : 'FactureLigne', $context === 'invoicereccard' ? '/compta/facture/class/facture-rec.class.php' : '/compta/facture/class/facture.class.php');
			if ($action == 'altaupdatelines') {
				$this->updateInvoiceLines($object, $client);
			}
		} elseif ($context == 'contractcard' && ($user->hasRight('contrat', 'creer'))) {
			$this->handleContractAddOrUpdateLine($object, $action, $client);
			if ($action == 'altaupdatelines') {
				$this->updateContractLines($object, $client);
			}
		}

		return 0;
	}

	/**
	 * Add the contract category type when PriceList contract categories are enabled.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param Categorie          $object     Category object
	 * @param string             $action     Current action
	 * @param HookManager        $hookmanager Hook manager
	 * @return int
	 */
	public function constructCategory($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!pricelistIsContractCategoryAvailable()) {
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
			($context == 'ordercard' && ($user->hasRight('commande', 'creer')))
			|| ($context == 'propalcard' && ($user->hasRight('propal', 'creer')))
			|| ($context == 'contractcard' && ($user->hasRight('contrat', 'creer')))
			|| (in_array($context, array('invoicecard', 'invoicereccard')) && ($user->hasRight('facture', 'creer')))
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
		if ($action == 'addline') {
			if (GETPOST('prod_entry_mode') == 'free') {
				return;
			}
			if (getDolGlobalInt('PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING', 0) > 0 && GETPOSTINT('price_ht') != 0) {
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

		$idprod = GETPOSTINT('productid');
		if ($idprod <= 0 && !empty($line->fk_product)) {
			$idprod = (int) $line->fk_product;
		}
		if ($idprod <= 0) {
			return;
		}

		$this->applyPriceToPostFromPriceList($idprod, $client, $qty, $sourceObject, $line->pa_ht ?? $line->buy_price_ht ?? null);
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
		if ($action == 'addline') {
			if (GETPOST('prod_entry_mode') == 'free') {
				return;
			}
			if (getDolGlobalInt('PRICELIST_DO_NOT_OVERWRITE_PRICE_WHEN_ADDING', 0) > 0 && GETPOSTINT('price_ht') != 0) {
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

		$this->applyContractLinePriceToPostFromPriceList($idprod, $client, $qty, $object, $line->pa_ht ?? $line->buy_price_ht ?? null);
	}

	/**
	 * Apply price list values to POST before Dolibarr handles the line action.
	 *
	 * @param int     $idprod       Product id
	 * @param object  $client       Thirdparty
	 * @param mixed   $qty          Quantity
	 * @param ?object $sourceObject Source object
	 * @param float|string|null $existingCost Existing line cost on edit
	 * @return void
	 */
	private function applyPriceToPostFromPriceList($idprod, $client, $qty, $sourceObject, $existingCost = null)
	{
		global $langs;

		if ($idprod <= 0) {
			return;
		}

		$pricelist = new PriceList($this->db);
		$obj = $pricelist->get_price($idprod, $client, $qty, $sourceObject);
		if ($obj === -1 && $pricelist->error) {
			setEventMessage($langs->trans($pricelist->error), 'errors');
			return;
		}
		if (!is_int($obj) && $this->applyPriceToPost($obj, $idprod, $existingCost)) {
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
	 * @param float|string|null $existingCost Existing line cost on edit
	 * @return void
	 */
	private function applyContractLinePriceToPostFromPriceList($idprod, $client, $qty, $object, $existingCost = null)
	{
		global $langs;

		if ($idprod <= 0) {
			return;
		}

		$pricelist = new PriceList($this->db);
		$obj = $pricelist->get_price($idprod, $client, $qty, $object);
		if ($obj === -1 && $pricelist->error) {
			setEventMessage($langs->trans($pricelist->error), 'errors');
			return;
		}
		if (!is_int($obj) && $this->applyPriceToContractLinePost($obj, $idprod, $existingCost)) {
			setEventMessage($langs->trans('PriceListInsert'));
		}
	}

	/**
	 * Apply a price row to POST.
	 *
	 * @param stdClass $obj    Price row
	 * @param int      $idprod Product id
	 * @param float|string|null $existingCost Existing line cost on edit
	 * @return bool
	 */
	private function applyPriceToPost($obj, $idprod, $existingCost = null)
	{
		if (dol_strlen($obj->price)) {
			$_POST['price_ht'] = price($obj->price);
		} elseif (dol_strlen($obj->tx_discount)) {
			$_POST['remise_percent'] = price($obj->tx_discount);
		}

		$costPrice = $this->resolveCostPriceAndWarn($obj);
		if ($costPrice === null && PriceList::getCostPriceSourceForRow($obj) === 'dynamicprices' && $existingCost !== null) {
			$costPrice = $existingCost;
		}
		if ($costPrice !== null) {
			$costPrice = price2num($costPrice, 'MU');
			$_POST['buying_price'] = $costPrice;
			$_POST['pa_ht'] = $costPrice;
		}

		return true;
	}

	/**
	 * Apply a price row to Dolibarr contract line edit fields.
	 *
	 * @param stdClass $obj    Price row
	 * @param int      $idprod Product id
	 * @param float|string|null $existingCost Existing line cost on edit
	 * @return bool
	 */
	private function applyPriceToContractLinePost($obj, $idprod, $existingCost = null)
	{
		if (dol_strlen($obj->price)) {
			$_POST['elprice'] = price($obj->price);
		} elseif (dol_strlen($obj->tx_discount)) {
			$_POST['elremise_percent'] = price($obj->tx_discount);
		}

		$costPrice = $this->resolveCostPriceAndWarn($obj);
		if ($costPrice === null && PriceList::getCostPriceSourceForRow($obj) === 'dynamicprices' && $existingCost !== null) {
			$costPrice = $existingCost;
		}
		if ($costPrice !== null) {
			$_POST['buying_price'] = price2num($costPrice, 'MU');
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

			$obj = $pricelist->get_price($line->fk_product, $client, $line->qty, $object);
			if ($obj === -1 && $pricelist->error) {
				setEventMessage($langs->trans($pricelist->error), 'errors');
				continue;
			}
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line, (int) $line->fk_product);
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
				$line->array_options ?? array(),
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
			if ($obj === -1 && $pricelist->error) {
				setEventMessage($langs->trans($pricelist->error), 'errors');
				continue;
			}
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line, (int) $line->fk_product);
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
				$line->array_options ?? array(),
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

			$obj = $pricelist->get_price($line->fk_product, $client, $line->qty, $object);
			if ($obj === -1 && $pricelist->error) {
				setEventMessage($langs->trans($pricelist->error), 'errors');
				continue;
			}
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line, (int) $line->fk_product);
			if ($object->element === 'facturerec') {
				$res = $object->updateline(
					$line->id, $this->getLineDescription($line), $values['pu'], $line->qty,
					$line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->fk_product,
					$values['remise_percent'], 'HT', $line->info_bits, $line->fk_remise_except ?? 0,
					0, $line->product_type, $line->rang, $line->special_code, $line->label,
					$line->fk_unit, $line->multicurrency_subprice, 0,
					$line->date_start_fill, $line->date_end_fill,
					$line->fk_product_fournisseur_price, $values['pa_ht'], $line->fk_parent_line
				);
			} else {
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
					$line->array_options ?? array(),
					$line->situation_percent,
					$line->fk_unit,
					$line->multicurrency_subprice
				);
			}
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
			if ($obj === -1 && $pricelist->error) {
				setEventMessage($langs->trans($pricelist->error), 'errors');
				continue;
			}
			if (is_int($obj)) {
				continue;
			}

			$values = $this->getLinePriceValues($obj, $line, (int) $line->fk_product);
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
	 * @param stdClass $obj    Price row
	 * @param object   $line   Line object
	 * @param int      $idprod Product id
	 * @return array<string,mixed>
	 */
	private function getLinePriceValues($obj, $line, $idprod)
	{
		$pu = $this->getLineSubprice($line);
		$remisePercent = isset($line->remise_percent) ? $line->remise_percent : 0;
		if (dol_strlen($obj->price)) {
			$pu = price($obj->price);
		} elseif (dol_strlen($obj->tx_discount)) {
			$remisePercent = price($obj->tx_discount);
		}

		$pa = $line->pa_ht ?? $line->buy_price_ht ?? null;
		$costPrice = $this->resolveCostPriceAndWarn($obj);
		if ($costPrice !== null) {
			$pa = price2num($costPrice, 'MU');
		}

		return array(
			'pu' => $pu,
			'remise_percent' => $remisePercent,
			'pa_ht' => $pa,
		);
	}

	/**
	 * Resolve the selected cost source and report unavailable costs once per request.
	 *
	 * @param stdClass $obj    Price row
	 * @return float|null
	 */
	private function resolveCostPriceAndWarn($obj)
	{
		global $langs;
		$pricelist = new PriceList($this->db);
		$cost = $pricelist->getEffectiveCostPriceForRow($obj);
		if ($pricelist->cost_price_warning !== '') {
			$langs->load('pricelist@pricelist');
			$message = $langs->trans('PriceListCostPreserved', $langs->trans($pricelist->cost_price_warning));
			if (!in_array($message, $this->warnings, true)) {
				$this->warnings[] = $message;
				setEventMessages($message, null, 'warnings');
			}
		}
		return $cost;
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

}
