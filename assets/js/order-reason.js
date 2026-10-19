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
			'<div id="irixfsl-reason-overlay" class="irixfsl-modal-overlay" hidden>' +
				'<div class="irixfsl-modal" role="dialog" aria-modal="true" aria-labelledby="irixfsl-reason-title">' +
					'<h2 id="irixfsl-reason-title">' + esc(i18n.heading || 'Reason required') + '</h2>' +
					'<p class="irixfsl-modal-desc">' + esc(i18n.description || '') + '</p>' +
					'<label class="screen-reader-text" for="irixfsl-reason-textarea">' + esc(i18n.heading || 'Reason required') + '</label>' +
					'<textarea id="irixfsl-reason-textarea" rows="4" placeholder="' + esc(i18n.placeholder || '') + '"></textarea>' +
					'<p id="irixfsl-reason-error" class="irixfsl-modal-error" role="alert" hidden></p>' +
					'<div class="irixfsl-modal-actions">' +
						'<button type="button" class="button" id="irixfsl-reason-cancel">' + esc(i18n.cancel || 'Cancel') + '</button>' +
						'<button type="button" class="button button-primary" id="irixfsl-reason-save">' + esc(i18n.save || 'Save Reason') + '</button>' +
					'</div>' +
				'</div>' +
			'</div>'
		).appendTo('body');

		// Esc cancels, like any WP modal.
		$overlay.on('keydown', function (e) {
			if (e.key === 'Escape') $('#irixfsl-reason-cancel').trigger('click');
		});

		function openModal(onSave, onCancel) {
			$('#irixfsl-reason-textarea').val('');
			$('#irixfsl-reason-error').prop('hidden', true);
			$overlay.prop('hidden', false);
			$('#irixfsl-reason-textarea').trigger('focus');

			$('#irixfsl-reason-save').off('click').on('click', function () {
				var val = $.trim($('#irixfsl-reason-textarea').val());
				if (!val) {
					$('#irixfsl-reason-error').text(i18n.required || 'Please enter a reason.').prop('hidden', false);
					return;
				}
				$overlay.prop('hidden', true);
				onSave(val);
			});
			$('#irixfsl-reason-cancel').off('click').on('click', function () {
				$overlay.prop('hidden', true);
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
