/* global wp, jQuery */
(function ($) {
	'use strict';

	// Media uploader for company logo
	var mediaUploader;
	$('#irixfsl-logo-btn').on('click', function (e) {
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
		var row = '<tr class="irixfsl-carrier-row">' +
			'<td><input type="text" name="irixfsl[carriers][' + carrierIndex + '][name]" value="" class="regular-text" placeholder="e.g. My Courier"></td>' +
			'<td><input type="text" name="irixfsl[carriers][' + carrierIndex + '][url]" value="" class="large-text" placeholder="https://track.example.com/{number}"></td>' +
			'<td><button type="button" class="button irixfsl-remove-carrier">Remove</button></td>' +
			'</tr>';
		$('#irixfsl-carriers-table tbody').append(row);
		carrierIndex++;
	});

	$(document).on('click', '.irixfsl-remove-carrier', function () {
		$(this).closest('tr').remove();
	});
})(jQuery);
