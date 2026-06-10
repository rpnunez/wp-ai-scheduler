import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Onboarding Wizard View Controller
 */
export const OnboardingView = Backbone.View.extend({
	el: 'body',

	events: {
		'submit #aips-onboarding-strategy-form': 'saveStrategy',
		'submit #aips-onboarding-author-form': 'createAuthor',
		'submit #aips-onboarding-template-form': 'createTemplate',
		'click #aips-onboarding-generate-topics': 'generateTopics',
		'click #aips-onboarding-generate-post': 'generatePost',
		'click #aips-onboarding-complete': 'completeOnboarding',
		'click #aips-onboarding-skip': 'skipOnboarding',
		'click #aips-onboarding-reset': 'resetOnboarding'
	},

	initialize() {
		this.l10n = window.aipsOnboardingL10n || {};
		this.adminL10n = window.aipsAdminL10n || {};
	},

	showNotice(type, message) {
		const $wrap = this.$('#aips-onboarding-notice');
		if (!$wrap.length) return;

		const cls = type === 'success' ? 'notice notice-success' : type === 'warning' ? 'notice notice-warning' : 'notice notice-error';
		$wrap.html('<div class="' + cls + '"><p>' + message + '</p></div>');
	},

	ajax(action, data, onDone) {
		let payload;
		const nonce = (window.aipsAjax && window.aipsAjax.nonce) || '';
		const ajaxUrl = (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl;

		if (typeof data === 'string') {
			payload = data + '&action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce);
		} else {
			payload = $.extend(
				{
					action: action,
					nonce: nonce
				},
				data || {}
			);
		}

		$.ajax({
			url: ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: payload
		})
			.done((resp) => {
				if (resp && resp.success) {
					onDone(null, resp.data || {});
				} else {
					const msg = resp && resp.data && resp.data.message ? resp.data.message : (this.adminL10n.errorTryAgain || 'An error occurred.');
					onDone(new Error(msg));
				}
			})
			.fail(() => {
				onDone(new Error(this.adminL10n.errorTryAgain || 'An error occurred.'));
			});
	},

	saveStrategy(e) {
		e.preventDefault();
		const data = $(e.currentTarget).serialize();
		this.ajax('aips_onboarding_save_strategy', data, (err, out) => {
			if (err) {
				this.showNotice('error', err.message);
				return;
			}
			this.showNotice('success', out.message || 'Saved.');
			window.location.reload();
		});
	},

	createAuthor(e) {
		e.preventDefault();
		const data = $(e.currentTarget).serialize();
		this.ajax('aips_onboarding_create_author', data, (err, out) => {
			if (err) {
				this.showNotice('error', err.message);
				return;
			}
			this.showNotice('success', out.message || 'Author created.');
			window.location.reload();
		});
	},

	createTemplate(e) {
		e.preventDefault();
		const data = $(e.currentTarget).serialize();
		this.ajax('aips_onboarding_create_template', data, (err, out) => {
			if (err) {
				this.showNotice('error', err.message);
				return;
			}
			this.showNotice('success', out.message || 'Template created.');
			window.location.reload();
		});
	},

	generateTopics(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const $spinner = this.$('#aips-onboarding-topics-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		this.showNotice('success', this.adminL10n.generating || 'Generating...');

		this.ajax('aips_onboarding_generate_topics', {}, (err, out) => {
			$spinner.removeClass('is-active');
			if (err) {
				$btn.prop('disabled', false);
				this.showNotice('error', err.message);
				return;
			}

			const titles = out.titles || [];
			if (titles.length) {
				let html = '<h4 style="margin-top: 16px;">Generated Topics</h4><ul style="margin-left: 18px;">';
				titles.forEach((t) => {
					html += '<li>' + $('<div>').text(t).html() + '</li>';
				});
				html += '</ul>';
				this.$('#aips-onboarding-topics-preview').html(html).show();
			}

			this.showNotice('success', out.message || 'Topics generated.');
			window.location.reload();
		});
	},

	generatePost(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const $spinner = this.$('#aips-onboarding-post-spinner');
		const topic = this.$('#aips-onboarding-topic').val() || '';

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		this.showNotice('success', this.adminL10n.generating || 'Generating...');

		this.ajax('aips_onboarding_generate_post', { topic: topic }, (err, out) => {
			$spinner.removeClass('is-active');
			if (err) {
				$btn.prop('disabled', false);
				this.showNotice('error', err.message);
				return;
			}

			this.showNotice('success', out.message || 'Post generated.');
			window.location.reload();
		});
	},

	completeOnboarding(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		$btn.prop('disabled', true);

		this.ajax('aips_onboarding_complete', {}, (err, out) => {
			if (err) {
				$btn.prop('disabled', false);
				this.showNotice('error', err.message);
				return;
			}
			this.showNotice('success', out.message || 'Completed.');
			if (out.dashboard_url) {
				window.location.href = out.dashboard_url;
			}
		});
	},

	skipOnboarding(e) {
		e.preventDefault();
		const confirmMsg = this.l10n.confirmSkipOnboarding || 'Skip the Onboarding Wizard? You can restart it later from System Status.';
		if (!window.confirm(confirmMsg)) {
			return;
		}

		this.ajax('aips_onboarding_skip', {}, (err, out) => {
			if (err) {
				this.showNotice('error', err.message);
				return;
			}
			this.showNotice('success', out.message || 'Onboarding skipped.');
			if (out.dashboard_url) {
				window.location.href = out.dashboard_url;
			}
		});
	},

	resetOnboarding(e) {
		e.preventDefault();
		if (!window.confirm('Restart the onboarding wizard? This clears the wizard progress flags (it does not delete authors/templates/posts already created).')) {
			return;
		}

		this.ajax('aips_onboarding_reset', {}, (err, out) => {
			if (err) {
				this.showNotice('error', err.message);
				return;
			}
			this.showNotice('success', out.message || 'Reset.');
			window.location.reload();
		});
	}
});

export default OnboardingView;
