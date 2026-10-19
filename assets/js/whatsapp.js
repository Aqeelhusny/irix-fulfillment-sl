/* global jQuery, irixfslWhatsapp, ajaxurl */
(function ($) {
	'use strict';

	var i18n = (irixfslWhatsapp && irixfslWhatsapp.i18n) || {};

	// Updates only the label span so the button's icon survives.
	function setLabel( btn, text ) {
		var label = btn.find( '.irixfsl-btn-label' );
		( label.length ? label : btn ).text( text );
	}

	$('#irixfsl-resend-whatsapp').on( 'click', function () {
		var btn = $(this);
		btn.prop( 'disabled', true );
		setLabel( btn, i18n.sending || 'Sending…' );
		$.post( ajaxurl, {
			action:   'irixfsl_resend_whatsapp',
			order_id: btn.data('order'),
			nonce:    btn.data('nonce'),
		} )
		.done( function (res) {
			if ( res.success ) {
				setLabel( btn, i18n.sent || 'Message Sent!' );
				btn.closest( '.inside' ).find( '.irixfsl-pill' )
					.attr( 'class', 'irixfsl-pill irixfsl-pill--sent' )
					.html( '<span class="irixfsl-dot" aria-hidden="true"></span>' )
					.append( document.createTextNode( i18n.sentPill || 'Sent' ) );
			} else {
				btn.prop( 'disabled', false );
				setLabel( btn, i18n.retry || 'Retry' );
			}
		} )
		.fail( function () {
			btn.prop( 'disabled', false );
			setLabel( btn, i18n.retry || 'Retry' );
		} );
	} );
})(jQuery);
