jQuery(document).ready(function ($) {
	function showNotice(type, message) {
		var $wrap = $('#aips-onboarding-notice');
		var cls = type === 'success' ? 'notice notice-success' : type === 'warning' ? 'notice notice-warning' : 'notice notice-error';
		$wrap.html('<div class="' + cls + '"><p>' + message + '</p></div>');
	}

	function ajax(action, data, onDone) {
		var payload;
		if (typeof data === 'string') {
			payload = data + '&action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(aipsAjax.nonce);
		} else {
			payload = $.extend(
				{
					action: action,
					nonce: aipsAjax.nonce,
				},
				data || {}
			);
		}

		$.ajax({
			url: aipsAjax.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: payload,
		})
			.done(function (resp) {
				if (resp && resp.success) {
					onDone(null, resp.data || {});
				} else {
					var msg = resp && resp.data && resp.data.message ? resp.data.message : aipsAdminL10n.errorTryAgain || 'An error occurred.';
					onDone(new Error(msg));
				}
			})
			.fail(function () {
				onDone(new Error(aipsAdminL10n.errorTryAgain || 'An error occurred.'));
			});
	}

	$('#aips-onboarding-strategy-form').on('submit', function (e) {
		e.preventDefault();
		var data = $(this).serialize();
		ajax('aips_onboarding_save_strategy', data, function (err, out) {
			if (err) {
				showNotice('error', err.message);
				return;
			}
			showNotice('success', out.message || 'Saved.');
			window.location.reload();
		});
	});

	$('#aips-onboarding-author-form').on('submit', function (e) {
		e.preventDefault();
		var data = $(this).serialize();
		ajax('aips_onboarding_create_author', data, function (err, out) {
			if (err) {
				showNotice('error', err.message);
				return;
			}
			showNotice('success', out.message || 'Author created.');
			window.location.reload();
		});
	});

	$('#aips-onboarding-template-form').on('submit', function (e) {
		e.preventDefault();
		var data = $(this).serialize();
		ajax('aips_onboarding_create_template', data, function (err, out) {
			if (err) {
				showNotice('error', err.message);
				return;
			}
			showNotice('success', out.message || 'Template created.');
			window.location.reload();
		});
	});

	$('#aips-onboarding-generate-topics').on('click', function () {
		var $btn = $(this);
		var $spinner = $('#aips-onboarding-topics-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		showNotice('success', aipsAdminL10n.generating || 'Generating...');

		ajax('aips_onboarding_generate_topics', {}, function (err, out) {
			$spinner.removeClass('is-active');
			if (err) {
				$btn.prop('disabled', false);
				showNotice('error', err.message);
				return;
			}

			var titles = out.titles || [];
			if (titles.length) {
				var html = '<h4 style="margin-top: 16px;">Generated Topics</h4><ul style="margin-left: 18px;">';
				titles.forEach(function (t) {
					html += '<li>' + $('<div>').text(t).html() + '</li>';
				});
				html += '</ul>';
				$('#aips-onboarding-topics-preview').html(html).show();
			}

			showNotice('success', out.message || 'Topics generated.');
			window.location.reload();
		});
	});

	$('#aips-onboarding-generate-post').on('click', function () {
		var $btn = $(this);
		var $spinner = $('#aips-onboarding-post-spinner');
		var topic = $('#aips-onboarding-topic').val() || '';

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		showNotice('success', aipsAdminL10n.generating || 'Generating...');

		ajax('aips_onboarding_generate_post', { topic: topic }, function (err, out) {
			$spinner.removeClass('is-active');
			if (err) {
				$btn.prop('disabled', false);
				showNotice('error', err.message);
				return;
			}

			showNotice('success', out.message || 'Post generated.');
			window.location.reload();
		});
	});

	$('#aips-onboarding-complete').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);

		ajax('aips_onboarding_complete', {}, function (err, out) {
			if (err) {
				$btn.prop('disabled', false);
				showNotice('error', err.message);
				return;
			}
			showNotice('success', out.message || 'Completed.');
		});
	});

	$('#aips-onboarding-reset').on('click', function () {
		if (!window.confirm('Restart the onboarding wizard? This clears the wizard progress flags (it does not delete authors/templates/posts already created).')) {
			return;
		}
		ajax('aips_onboarding_reset', {}, function (err, out) {
			if (err) {
				showNotice('error', err.message);
				return;
			}
			showNotice('success', out.message || 'Reset.');
			window.location.reload();
		});
	});
});
