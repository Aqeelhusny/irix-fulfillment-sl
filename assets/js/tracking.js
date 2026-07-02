/* global jQuery, irixfslTracking, ajaxurl */
(function ($) {
	'use strict';

	var carriers = (irixfslTracking && irixfslTracking.carriers) || {};
	var i18n     = (irixfslTracking && irixfslTracking.i18n)    || {};

	function updateTrackingUrl() {
		var name   = $('#irixfsl_carrier').val();
		var number = $('#irixfsl_tracking_number').val().trim();
		if ( name && number && carriers[ name ] ) {
			$('#irixfsl_tracking_url').val( carriers[ name ].replace( '{number}', number ) );
		}
	}

	$('#irixfsl_carrier, #irixfsl_tracking_number').on( 'change input', updateTrackingUrl );

	$('#irixfsl-resend-tracking').on( 'click', function () {
		var btn = $(this);
		btn.prop( 'disabled', true ).text( i18n.sending || 'Sending…' );
		$.post( ajaxurl, {
			action:   'irixfsl_resend_tracking',
			order_id: btn.data('order'),
			nonce:    btn.data('nonce'),
		} )
		.done( function (res) {
			if ( res.success ) {
				btn.text( i18n.sent || 'Email Sent!' );
			} else {
				btn.prop( 'disabled', false ).text( i18n.retry || 'Retry' );
			}
		} )
		.fail( function () {
			btn.prop( 'disabled', false ).text( i18n.retry || 'Retry' );
		} );
	} );
})(jQuery);
