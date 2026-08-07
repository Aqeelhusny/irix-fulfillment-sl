(function ($) {
	'use strict';

	var cfg   = window.irixfslOrderReason || {};
	var i18n  = cfg.i18n || {};
	var gated = cfg.statuses || [];

	function isGated(value) {
		return gated.indexOf(value) !== -1;
	}

	function esc(str) {
		return $('<div>').text(str || '').html();
	}

	$(function () {
		var $select = $('#order_status');
		var $reason = $('#irixfsl_status_reason');
		if (!$select.length || !$reason.length) return;

		var previousValue = $select.val();
		// Set right before a Cancel-triggered revert, so restoring the select
		// to its previous value doesn't re-open the modal — even when that
		// previous value is itself a gated status (e.g. Returned -> Refunded,
		// cancelled, reverting back to the already-Returned order).
		var suppressNextChange = false;

		var $overlay = $(
			'<div id="irixfsl-reason-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;">' +
				'<div style="background:#fff;border-radius:4px;max-width:420px;width:90%;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.3);">' +
					'<h3 style="margin-top:0">' + esc(i18n.heading || 'Reason required') + '</h3>' +
					'<p style="color:#555;font-size:13px">' + esc(i18n.description || '') + '</p>' +
					'<textarea id="irixfsl-reason-textarea" rows="4" style="width:100%;box-sizing:border-box" placeholder="' + esc(i18n.placeholder || '') + '"></textarea>' +
					'<p id="irixfsl-reason-error" style="color:#b32d2e;display:none;font-size:12px"></p>' +
					'<p style="text-align:right;margin-bottom:0">' +
						'<button type="button" class="button" id="irixfsl-reason-cancel">' + esc(i18n.cancel || 'Cancel') + '</button> ' +
						'<button type="button" class="button button-primary" id="irixfsl-reason-save">' + esc(i18n.save || 'Save Reason') + '</button>' +
					'</p>' +
				'</div>' +
			'</div>'
		).appendTo('body');

		function openModal(onSave, onCancel) {
			$('#irixfsl-reason-textarea').val('');
			$('#irixfsl-reason-error').hide();
			$overlay.css('display', 'flex');
			$('#irixfsl-reason-textarea').trigger('focus');

			$('#irixfsl-reason-save').off('click').on('click', function () {
				var val = $.trim($('#irixfsl-reason-textarea').val());
				if (!val) {
					$('#irixfsl-reason-error').text(i18n.required || 'Please enter a reason.').show();
					return;
				}
				$overlay.hide();
				onSave(val);
			});
			$('#irixfsl-reason-cancel').off('click').on('click', function () {
				$overlay.hide();
				onCancel();
			});
		}

		$select.on('change', function () {
			if (suppressNextChange) {
				suppressNextChange = false;
				return;
			}

			var val = $(this).val();
			if (!isGated(val)) {
				$reason.val('');
				previousValue = val;
				return;
			}
			openModal(
				function (reasonText) {
					$reason.val(reasonText);
					previousValue = val;
				},
				function () {
					$reason.val('');
					suppressNextChange = true;
					$select.val(previousValue).trigger('change');
				}
			);
		});

		// Belt-and-suspenders: block the actual save if the reason is still
		// empty when the form is submitted (covers non-standard status changes
		// that don't fire a native 'change' event on the select).
		$(document).on('submit', 'form#post, form#order', function (e) {
			var val = $select.val();
			if (isGated(val) && !$.trim($reason.val())) {
				e.preventDefault();
				var form = this;
				openModal(
					function (reasonText) {
						$reason.val(reasonText);
						form.submit();
					},
					function () {}
				);
			}
		});
	});
})(jQuery);
