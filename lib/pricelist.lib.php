<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!function_exists('pricelistIsDolibarrVersionAtLeast')) {
	/**
	 * Check Dolibarr version.
	 *
	 * @param string $version Version
	 * @return bool
	 */
	function pricelistIsDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}
}

if (!function_exists('pricelistIsPropalCategoryAvailable')) {
	/**
	 * Return true when proposal categories are available in Dolibarr.
	 *
	 * @return bool
	 */
	function pricelistIsPropalCategoryAvailable()
	{
		return pricelistIsDolibarrVersionAtLeast('23.0.0');
	}
}

if (!function_exists('pricelistIsOrderInvoiceCategoryAvailable')) {
	/**
	 * Return true when order and invoice categories are available in Dolibarr.
	 *
	 * @return bool
	 */
	function pricelistIsOrderInvoiceCategoryAvailable()
	{
		return pricelistIsDolibarrVersionAtLeast('22.0.0');
	}
}

if (!function_exists('pricelistIsContractCategoryAvailable')) {
	/**
	 * Return true when contract categories are enabled for PriceList.
	 *
	 * @return bool
	 */
	function pricelistIsContractCategoryAvailable()
	{
		return getDolGlobalInt('PRICELIST_ENABLE_CONTRACT_CATEGORIES', 0) > 0;
	}
}

if (!function_exists('pricelistIsCategoryTypeAvailable')) {
	/**
	 * Return true when a category type can be used in the current environment.
	 *
	 * @param string $categoryType Category type
	 * @return bool
	 */
	function pricelistIsCategoryTypeAvailable($categoryType)
	{
		if ($categoryType === 'propal') {
			return pricelistIsPropalCategoryAvailable();
		}
		if ($categoryType === 'order' || $categoryType === 'invoice') {
			return pricelistIsOrderInvoiceCategoryAvailable();
		}
		if ($categoryType === 'contract') {
			return pricelistIsContractCategoryAvailable();
		}

		return true;
	}
}

if (!function_exists('pricelistGetDocumentCategoryPriority')) {
	/**
	 * Return true when document object categories have priority over thirdparty/customer categories.
	 *
	 * @return bool
	 */
	function pricelistGetDocumentCategoryPriority()
	{
		return getDolGlobalInt('PRICELIST_DOCUMENT_CATEGORY_PRIORITY', 1) > 0;
	}
}

if (!function_exists('pricelistCanReadPrices')) {
	/**
	 * Check if a user can read product/service prices.
	 *
	 * @param User     $user        User
	 * @param int|null $productType Product type: 0 product, 1 service, null unknown
	 * @return bool
	 */
	function pricelistCanReadPrices($user, $productType = null)
	{
		if (!empty($user->admin)) {
			return true;
		}

		if (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS', 0) > 0) {
			if ($productType === 1) {
				return $user->hasRight('service', 'service_advance', 'read_prices');
			}
			if ($productType === 0) {
				return $user->hasRight('product', 'product_advance', 'read_prices');
			}

			return $user->hasRight('product', 'product_advance', 'read_prices') || $user->hasRight('service', 'service_advance', 'read_prices');
		}

		if ($productType === 1) {
			return $user->hasRight('service', 'read') || $user->hasRight('service', 'lire');
		}
		if ($productType === 0) {
			return $user->hasRight('product', 'read') || $user->hasRight('produit', 'lire');
		}

		return $user->hasRight('product', 'read') || $user->hasRight('produit', 'lire') || $user->hasRight('service', 'read') || $user->hasRight('service', 'lire');
	}
}

if (!function_exists('pricelistCanWritePrices')) {
	/**
	 * Check if a user can create, update or delete a price list row.
	 *
	 * @param User     $user        User
	 * @param int|null $productType Product type: 0 product, 1 service, null unknown
	 * @return bool
	 */
	function pricelistCanWritePrices($user, $productType = null)
	{
		if (!empty($user->admin)) {
			return true;
		}
		if ($productType === 1) {
			return $user->hasRight('service', 'creer');
		}
		if ($productType === 0) {
			return $user->hasRight('produit', 'creer');
		}

		return $user->hasRight('produit', 'creer') || $user->hasRight('service', 'creer');
	}
}

if (!function_exists('pricelistEnsureObjectHeadTab')) {
	/**
	 * Ensure the price list tab exists when the current page is already the price list tab.
	 *
	 * @param array<int,array<int,string>> $head       Existing object head tabs
	 * @param string                       $objectType Object type: product or thirdparty
	 * @param int                          $objectId   Object id
	 * @return array<int,array<int,string>>
	 */
	function pricelistEnsureObjectHeadTab($head, $objectType, $objectId)
	{
		global $langs;

		if (!is_array($head)) {
			$head = array();
		}

		foreach ($head as $tab) {
			if (isset($tab[2]) && $tab[2] === 'pricelist') {
				return $head;
			}
		}

		$url = '';
		if ($objectType === 'product') {
			$url = dol_buildpath('/pricelist/product.php', 1).'?id='.(int) $objectId;
		} elseif ($objectType === 'thirdparty') {
			$url = dol_buildpath('/pricelist/customer.php', 1).'?id='.(int) $objectId;
		}

		if ($url !== '') {
			$head[] = array($url, $langs->trans('PriceLists'), 'pricelist');
		}

		return $head;
	}
}

/**
 * Prepare admin tabs.
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function pricelistAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('admin', 'pricelist@pricelist'));

	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/pricelist/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/pricelist/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	return $head;
}
