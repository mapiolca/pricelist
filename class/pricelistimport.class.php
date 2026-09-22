<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/pricelist.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/** Native import adapter: validated rows follow the same persistence/history as forms. */
class PriceListImport
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var bool */
	public $updated = false;

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param array<string,string> $values Mapped native fields, without the p. prefix
	 * @param User $user Acting user
	 * @param bool $update Update by rowid when the native update key was selected
	 * @param string $importId Native import identifier
	 * @param bool $simulation Suppress external trigger effects during simulation
	 * @return int Row id or -1
	 */
	public function importRow(array $values, $user, $update, $importId, $simulation)
	{
		global $conf, $langs;
		$langs->load('pricelist@pricelist');
		$this->error = '';
		$this->updated = false;
		if (!isModEnabled('pricelist') || !$user->hasRight('import', 'run')
			|| (isset($values['entity']) && $values['entity'] !== '' && (int) $values['entity'] !== (int) $conf->entity)) {
			$this->error = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		foreach (array('rowid', 'entity', 'fk_cat', 'fk_cat_propal', 'fk_cat_order', 'fk_cat_invoice', 'fk_cat_contract') as $field) {
			if (isset($values[$field]) && $values[$field] !== '' && !ctype_digit($values[$field])) {
				$this->error = $langs->trans('ErrorBadValueForParameter', $field);
				return -1;
			}
		}
		foreach (array('from_qty', 'price', 'tx_discount', 'cost_price') as $field) {
			if (isset($values[$field]) && $values[$field] !== '' && (!is_numeric(price2num($values[$field], '', 2)) || !is_finite((float) price2num($values[$field], '', 2)))) {
				$this->error = $langs->trans('ErrorBadValueForParameter', $field);
				return -1;
			}
		}
		$row = new PriceList($this->db);
		if ($update && !empty($values['rowid'])) {
			if (!ctype_digit($values['rowid']) || $row->fetch((int) $values['rowid']) <= 0) {
				$this->error = $langs->trans('ErrorRecordNotFound');
				return -1;
			}
			$this->updated = true;
		}
		$references = array('fk_product' => new Product($this->db), 'fk_soc' => new Societe($this->db), 'fk_user_creation' => new User($this->db));
		foreach ($references as $field => $linked) {
			if (!isset($values[$field]) || $values[$field] === '') {
				continue;
			}
			$value = $values[$field];
			$isId = ctype_digit($value) || stripos($value, 'id:') === 0;
			$value = preg_replace('/^(id|ref):/i', '', $value);
			if (($isId && !ctype_digit($value)) || $linked->fetch($isId ? (int) $value : 0, $isId ? '' : $value) <= 0) {
				$this->error = $langs->trans('ErrorRecordNotFound');
				return -1;
			}
			if ($field === 'fk_user_creation' && isset($linked->entity) && !in_array((int) $linked->entity, array_merge(array(0), array_map('intval', explode(',', getEntity('user')))), true)) {
				$this->error = $langs->trans('NotEnoughPermissions');
				return -1;
			}
			$values[$field] = (string) $linked->id;
		}
		$properties = array('fk_product' => 'product_id', 'fk_soc' => 'socid', 'fk_cat' => 'catid', 'fk_cat_propal' => 'catid_propal', 'fk_cat_order' => 'catid_order', 'fk_cat_invoice' => 'catid_invoice', 'fk_cat_contract' => 'catid_contract', 'fk_user_creation' => 'user_creation_id', 'from_qty' => 'from_qty', 'price' => 'price', 'tx_discount' => 'tx_discount', 'cost_price' => 'cost_price');
		foreach ($properties as $field => $property) {
			if (array_key_exists($field, $values)) {
				$row->$property = $values[$field] === '' ? null : $values[$field];
			}
		}
		// A new explicit source wins. Legacy mappings are converted, never read in
		// parallel with the canonical source; omitted fields preserve an update.
		if (isset($values['cost_price_source']) && $values['cost_price_source'] !== '') {
			$row->cost_price_source = $values['cost_price_source'];
		} elseif (array_key_exists('use_product_cost_price', $values)) {
			if (!in_array($values['use_product_cost_price'], array('', '0', '1'), true)) {
				$this->error = $langs->trans('PriceListInvalidCostSource');
				return -1;
			}
			$row->cost_price_source = $values['use_product_cost_price'] === '1' ? 'product' : 'custom';
		} elseif (!$this->updated) {
			$row->cost_price_source = 'custom';
		}
		$row->import_key = $importId;
		$result = $this->updated ? $row->update($user, (int) $simulation) : $row->create($user, (int) $simulation);
		if ($result < 0) {
			$this->error = $row->error;
			return -1;
		}
		return (int) $row->id;
	}
}
