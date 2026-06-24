/* global wp, jQuery */
(function ($) {
	'use strict';

	// Media uploader for company logo
	var mediaUploader;
	$('#wcfsl-logo-btn').on('click', function (e) {
		e.preventDefault();
		if (mediaUploader) {
			mediaUploader.open();
			return;
		}
		mediaUploader = wp.media({
			title: 'Select Company Logo',
			button: { text: 'Use this image' },
			multiple: false
		});
		mediaUploader.on('select', function () {
			var attachment = mediaUploader.state().get('selection').first().toJSON();
			$('#wcfsl-logo-id').val(attachment.id);
			$('#wcfsl-logo-preview').attr('src', attachment.url).show();
			$('#wcfsl-logo-remove').show();
		});
		mediaUploader.open();
	});

	$('#wcfsl-logo-remove').on('click', function () {
		$('#wcfsl-logo-id').val(0);
		$('#wcfsl-logo-preview').attr('src', '').hide();
		$(this).hide();
	});

	// Dynamic carrier rows
	var carrierIndex = $('#wcfsl-carriers-table tbody tr').length;

	$('#wcfsl-add-carrier').on('click', function () {
		var row = '<tr class="wcfsl-carrier-row">' +
			'<td><input type="text" name="wcfsl[carriers][' + carrierIndex + '][name]" value="" class="regular-text" placeholder="e.g. My Courier"></td>' +
			'<td><input type="text" name="wcfsl[carriers][' + carrierIndex + '][url]" value="" class="large-text" placeholder="https://track.example.com/{number}"></td>' +
			'<td><button type="button" class="button wcfsl-remove-carrier">Remove</button></td>' +
			'</tr>';
		$('#wcfsl-carriers-table tbody').append(row);
		carrierIndex++;
	});

	$(document).on('click', '.wcfsl-remove-carrier', function () {
		$(this).closest('tr').remove();
	});
})(jQuery);
