$(function () {
	var $form = $('#pricelist-line-form');
	if (!$form.length) {
		return;
	}

	var $useProductCostPrice = $form.find('select[name="use_product_cost_price"]');
	var $costPrice = $form.find('input[name="cost_price"]');
	var syncCostPriceMode = function () {
		var enabled = $useProductCostPrice.val() === '1';
		if (enabled) {
			$costPrice.val('');
		}
		$costPrice.prop('disabled', enabled);
	};
	if ($useProductCostPrice.length && $costPrice.length) {
		$useProductCostPrice.on('change', syncCostPriceMode);
		syncCostPriceMode();
	}

	var vatRate = parseFloat(String($form.data('tva-tx')).replace(',', '.'));
	if (isNaN(vatRate)) {
		return;
	}

	var $priceHt = $form.find('input[name="price"]');
	var $priceTtc = $form.find('input[name="price_ttc"]');
	var $priceInputMode = $form.find('input[name="price_input_mode"]');
	if (!$priceHt.length || !$priceTtc.length || !$priceInputMode.length) {
		return;
	}
	var isSyncing = false;

	var parsePrice = function (value) {
		value = String(value).replace(/\s/g, '').replace(',', '.');
		if (value === '') {
			return null;
		}
		var parsed = parseFloat(value);
		return isNaN(parsed) ? null : parsed;
	};

	var formatPrice = function (value) {
		return Math.round(value * 1000000) / 1000000;
	};

	$priceHt.on('input', function () {
		if (isSyncing) {
			return;
		}
		$priceInputMode.val('HT');
		var ht = parsePrice($priceHt.val());
		if (ht === null) {
			return;
		}
		isSyncing = true;
		$priceTtc.val(formatPrice(ht * (1 + vatRate / 100)));
		isSyncing = false;
	});

	$priceTtc.on('input', function () {
		if (isSyncing) {
			return;
		}
		$priceInputMode.val('TTC');
		var ttc = parsePrice($priceTtc.val());
		if (ttc === null) {
			return;
		}
		isSyncing = true;
		$priceHt.val(formatPrice(ttc / (1 + vatRate / 100)));
		isSyncing = false;
	});
});
