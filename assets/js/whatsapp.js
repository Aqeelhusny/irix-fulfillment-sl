/* global jQuery, irixfslWhatsapp, ajaxurl */
(function ($) {
	'use strict';

	var i18n = (irixfslWhatsapp && irixfslWhatsapp.i18n) || {};

	$('#irixfsl-resend-whatsapp').on('click', function () {
		var btn = $(this);
		btn.prop('disabled', true).text(i18n.sending || 'Sending…');
		$.post(ajaxurl, {
			action:   'irixfsl_resend_whatsapp',
			order_id: btn.data('order'),
			nonce:    btn.data('nonce'),
		})
		.done(function (res) {
			if (res.success) {
				btn.text(i18n.sent || 'Message Sent!');
			} else {
				btn.prop('disabled', false).text(i18n.retry || 'Retry');
			}
		})
		.fail(function () {
			btn.prop('disabled', false).text(i18n.retry || 'Retry');
		});
	});
})(jQuery);
