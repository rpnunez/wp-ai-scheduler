jQuery(document).ready(function ($) {
	function showNotice(type, message) {
		var $wrap = $('#aips-onboarding-notice');
		var cls = type === 'success' ? 'notice notice-success' : type === 'warning' ? 'notice notice-warning' : 'notice notice-error';
		$wrap.html('<div class="' + cls + '"><p>' + message + '</p></div>');
	}

	function ajax(action, data, onDone) {
		var payload = data;

		if (typeof data === 'string') {
			payload = {};
			new URLSearchParams(data).forEach(function (value, key) {
				payload[key] = value;
			});
		}

		AIPS.Core.Http.ajaxRequest({
			action: action,
			data: payload,
			toastOnError: false,
			errorFallback: aipsAdminL10n.errorTryAgain || 'An error occurred.',
			onSuccess: function (respData) {
				onDone(null, respData);
			},
			onError: function (message) {
				onDone(new Error(message));
			}
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
			AIPS.refreshPageSection('.aips-page-container');
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
			AIPS.refreshPageSection('.aips-page-container');
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
			AIPS.refreshPageSection('.aips-page-container');
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
			AIPS.refreshPageSection('.aips-page-container');
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
			AIPS.refreshPageSection('.aips-page-container');
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

	$('#aips-onboarding-skip').on('click', function () {
		if (!window.confirm(aipsOnboardingL10n.confirmSkipOnboarding || 'Skip the Onboarding Wizard? You can restart it later from System Status.')) {
			return;
		}
		ajax('aips_onboarding_skip', {}, function (err, out) {
			if (err) {
				showNotice('error', err.message);
				return;
			}
			showNotice('success', out.message || 'Onboarding skipped.');
			if (out.dashboard_url) {
				window.location.href = out.dashboard_url;
			}
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
			AIPS.refreshPageSection('.aips-page-container');
		});
	});
});
