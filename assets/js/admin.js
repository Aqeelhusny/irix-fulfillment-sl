/* global wp, jQuery, irixfslAdmin */
(function ($) {
	'use strict';

	var i18n            = (irixfslAdmin && irixfslAdmin.i18n) || {};
	var shippingMethods = (irixfslAdmin && irixfslAdmin.shippingMethods) || {};

	function esc(str) {
		return $('<div>').text(str || '').html();
	}

	function shippingMethodOptions() {
		var options = '<option value="">' + esc(i18n.selectShippingMethod || '— Select shipping method —') + '</option>';
		$.each(shippingMethods, function (id, title) {
			options += '<option value="' + esc(id) + '">' + esc(title) + ' (' + esc(id) + ')</option>';
		});
		return options;
	}

	// Media uploader for company logo
	var mediaUploader;
	$('#irixfsl-logo-btn').on('click', function (e) {
		e.preventDefault();
		if (mediaUploader) {
			mediaUploader.open();
			return;
		}
		mediaUploader = wp.media({
			title:    i18n.selectLogo   || 'Select Company Logo',
			button:   { text: i18n.useThisImage || 'Use this image' },
			multiple: false,
		});
		mediaUploader.on('select', function () {
			var attachment = mediaUploader.state().get('selection').first().toJSON();
			$('#irixfsl-logo-id').val(attachment.id);
			$('#irixfsl-logo-preview').attr('src', attachment.url).show();
			$('#irixfsl-logo-remove').show();
		});
		mediaUploader.open();
	});

	$('#irixfsl-logo-remove').on('click', function () {
		$('#irixfsl-logo-id').val(0);
		$('#irixfsl-logo-preview').attr('src', '').hide();
		$(this).hide();
	});

	// Dynamic carrier rows
	var carrierIndex = $('#irixfsl-carriers-table tbody tr').length;

	$('#irixfsl-add-carrier').on('click', function () {
		var placeholder = i18n.addCarrier || 'e.g. My Courier';
		var row = '<tr class="irixfsl-carrier-row">' +
			'<td><input type="text" name="irixfsl[carriers][' + carrierIndex + '][name]" value="" class="regular-text" placeholder="' + placeholder + '"></td>' +
			'<td><input type="text" name="irixfsl[carriers][' + carrierIndex + '][url]" value="" class="large-text" placeholder="https://track.example.com/{number}"></td>' +
			'<td><button type="button" class="button irixfsl-remove-carrier">' + (i18n.remove || 'Remove') + '</button></td>' +
			'</tr>';
		$('#irixfsl-carriers-table tbody').append(row);
		carrierIndex++;
	});

	$(document).on('click', '.irixfsl-remove-carrier', function () {
		$(this).closest('tr').remove();
	});

	// Dynamic local-delivery-method rows
	var localDeliveryIndex = $('#irixfsl-local-delivery-table tbody tr').length;

	$('#irixfsl-add-local-delivery').on('click', function () {
		var row = '<tr class="irixfsl-local-delivery-row">' +
			'<td><input type="text" name="irixfsl[local_delivery_methods][' + localDeliveryIndex + '][name]" value="" class="regular-text" placeholder="e.g. In-house Rider"></td>' +
			'<td><select name="irixfsl[local_delivery_methods][' + localDeliveryIndex + '][method_id]" class="regular-text">' + shippingMethodOptions() + '</select></td>' +
			'<td><input type="number" step="0.01" min="0" name="irixfsl[local_delivery_methods][' + localDeliveryIndex + '][fee]" value="" placeholder="0.00"></td>' +
			'<td><button type="button" class="button irixfsl-remove-local-delivery">' + (i18n.remove || 'Remove') + '</button></td>' +
			'</tr>';
		$('#irixfsl-local-delivery-table tbody').append(row);
		localDeliveryIndex++;
	});

	$(document).on('click', '.irixfsl-remove-local-delivery', function () {
		$(this).closest('tr').remove();
	});
})(jQuery);
