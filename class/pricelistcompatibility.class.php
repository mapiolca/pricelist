<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Compatibility helpers for PriceList.
 */
class PriceListCompatibility
{
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

		return array(
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
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $baseAvailable,
				'reason' => $baseAvailable ? '' : 'RequiresDolibarr20Php80',
			),
			'price_by_contract_category' => array(
				'label' => 'PriceByContractCategory',
				'description' => 'PriceByContractCategoryDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $baseAvailable,
				'reason' => $baseAvailable ? '' : 'RequiresDolibarr20Php80',
			),
		);
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
