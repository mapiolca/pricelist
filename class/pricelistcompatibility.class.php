<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Compatibility helpers for PriceList.
 */
class PriceListCompatibility
{
	/**
	 * Check the optional external cost provider, without recalculating a cost.
	 * @param DoliDB|null $db Database, required for a different commercial entity
	 * @param int $entity Commercial entity (0 means current consultation entity)
	 * @return array{available:bool,reason:string}
	 */
	public static function getDynamicPricesAvailability($db = null, $entity = 0)
	{
		global $conf;
		if (!defined('DOL_VERSION') || version_compare(DOL_VERSION, '20.0.0', '<') || version_compare(PHP_VERSION, '8.0.0', '<')) {
			return array('available' => false, 'reason' => 'RequiresDolibarr20Php80');
		}
		if (($entity <= 0 || $entity === (int) $conf->entity) && !isModEnabled('dynamicsprices')) {
			return array('available' => false, 'reason' => 'PriceListDynamicPricesDisabled');
		}
		if (($entity <= 0 || $entity === (int) $conf->entity) && !getDolGlobalInt('DYNAMICPRICES_COST_ENABLE', 1)) {
			return array('available' => false, 'reason' => 'PriceListDynamicCostDisabled');
		}
		if ($entity > 0 && $entity !== (int) $conf->entity) {
			if (!is_object($db)) {
				return array('available' => false, 'reason' => 'PriceListDynamicCostUnavailable');
			}
			// Global helpers describe the consultation entity. Read the document's
			// configuration separately without switching the global environment.
			$resql = $db->query("SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE entity IN (0, ".((int) $entity).") AND name IN ('MAIN_MODULE_DYNAMICSPRICES', 'DYNAMICPRICES_COST_ENABLE') ORDER BY entity");
			if (!$resql) {
				return array('available' => false, 'reason' => 'PriceListDynamicCostUnavailable');
			}
			$settings = array('MAIN_MODULE_DYNAMICSPRICES' => 0, 'DYNAMICPRICES_COST_ENABLE' => 1);
			while (is_object($row = $db->fetch_object($resql))) {
				$settings[$row->name] = (int) $row->value;
			}
			$db->free($resql);
			if (!$settings['MAIN_MODULE_DYNAMICSPRICES'] || !$settings['DYNAMICPRICES_COST_ENABLE']) {
				return array('available' => false, 'reason' => 'PriceListDynamicCostDisabled');
			}
		}
		dol_include_once('/dynamicsprices/class/dynamicpricescostservice.class.php');
		if (!class_exists('DynamicPricesCostService') || !method_exists('DynamicPricesCostService', 'getDynamicCostPrice')) {
			return array('available' => false, 'reason' => 'PriceListDynamicPricesIncompatible');
		}
		$method = new ReflectionMethod('DynamicPricesCostService', 'getDynamicCostPrice');
		if (!$method->isPublic() || $method->getNumberOfParameters() < 3 || $method->getNumberOfRequiredParameters() > 3) {
			return array('available' => false, 'reason' => 'PriceListDynamicPricesIncompatible');
		}
		return array('available' => true, 'reason' => '');
	}
	/**
	 * Check Dolibarr version.
	 *
	 * @param string $version Version
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Check PHP version.
	 *
	 * @param string $version Version
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return centralized feature compatibility.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getFeatures()
	{
		$baseAvailable = self::isDolibarrVersionAtLeast('20.0.0') && self::isPhpVersionAtLeast('8.0.0');
		$propalAvailable = self::isDolibarrVersionAtLeast('23.0.0') && self::isPhpVersionAtLeast('8.0.0');
		$orderInvoiceAvailable = self::isDolibarrVersionAtLeast('22.0.0') && self::isPhpVersionAtLeast('8.0.0');
		$contractAvailable = $baseAvailable && self::isContractCategoryOptionEnabled();
		$dynamicPrices = self::getDynamicPricesAvailability();

		return array(
			'dynamicprices_cost' => array(
				'label' => 'PriceListDynamicCost',
				'description' => 'PriceListDynamicCostDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dynamicPrices['available'],
				'reason' => $dynamicPrices['reason'],
			),
			'price_by_customer_category' => array(
				'label' => 'PriceByCustomerCategory',
				'description' => 'PriceByCustomerCategoryDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $baseAvailable,
				'reason' => $baseAvailable ? '' : 'RequiresDolibarr20Php80',
			),
			'price_by_propal_category' => array(
				'label' => 'PriceByPropalCategory',
				'description' => 'PriceByPropalCategoryDescription',
				'min_dolibarr' => '23.0.0',
				'core_available_from' => '23.0.0',
				'module_available_from' => '23.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '23.0.0', '>=')",
				'available' => $propalAvailable,
				'reason' => $propalAvailable ? '' : 'RequiresDolibarr23Php80',
			),
			'price_by_contract_category' => array(
				'label' => 'PriceByContractCategory',
				'description' => 'PriceByContractCategoryDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => 'PRICELIST_ENABLE_CONTRACT_CATEGORIES',
				'available' => $contractAvailable,
				'reason' => $contractAvailable ? '' : (!$baseAvailable ? 'RequiresDolibarr20Php80' : 'RequiresPriceListContractCategoriesOption'),
			),
			'price_by_order_category' => array(
				'label' => 'PriceByOrderCategory',
				'description' => 'PriceByOrderCategoryDescription',
				'min_dolibarr' => '22.0.0',
				'core_available_from' => '22.0.0',
				'module_available_from' => '22.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '22.0.0', '>=')",
				'available' => $orderInvoiceAvailable,
				'reason' => $orderInvoiceAvailable ? '' : 'RequiresDolibarr22Php80',
			),
			'price_by_invoice_category' => array(
				'label' => 'PriceByInvoiceCategory',
				'description' => 'PriceByInvoiceCategoryDescription',
				'min_dolibarr' => '22.0.0',
				'core_available_from' => '22.0.0',
				'module_available_from' => '22.0.0',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '22.0.0', '>=')",
				'available' => $orderInvoiceAvailable,
				'reason' => $orderInvoiceAvailable ? '' : 'RequiresDolibarr22Php80',
			),
		);
	}

	/**
	 * Check PriceList contract category option.
	 *
	 * @return bool
	 */
	private static function isContractCategoryOptionEnabled()
	{
		return getDolGlobalInt('PRICELIST_ENABLE_CONTRACT_CATEGORIES', 0) > 0;
	}

	/**
	 * Check if a feature is available.
	 *
	 * @param string $feature Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($feature)
	{
		$features = self::getFeatures();
		return !empty($features[$feature]['available']);
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();
		foreach (self::getFeatures() as $code => $feature) {
			if (empty($feature['available'])) {
				$unavailable[$code] = $feature;
			}
		}

		return $unavailable;
	}
}
