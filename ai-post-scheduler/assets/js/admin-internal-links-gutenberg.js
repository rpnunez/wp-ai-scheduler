/**
 * Block Editor "Internal Links" document panel.
 *
 * Shows the post's link counts and linking posts, and lets editors find,
 * insert, dismiss and undo inbound link suggestions. Uses the Link Report
 * AJAX endpoints; messages appear as editor notices.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.5
 */
(function (wp, $) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var l10n = window.aipsLinkPanelL10n || {};

	function notify(message, type) {
		if (message && wp.data && wp.data.dispatch('core/notices')) {
			wp.data.dispatch('core/notices').createNotice(type || 'success', message, { type: 'snackbar', isDismissible: true });
		}
	}

	function request(action, extra) {
		return $.post(l10n.ajaxurl, $.extend({
			action: action,
			nonce: l10n.nonce,
			post_id: l10n.postId
		}, extra || {}));
	}

	function errorMessage(response) {
		return (response && response.data && response.data.message) || l10n.actionError;
	}

	function Count(props) {
		return el('div', { className: 'aips-link-panel-count' + (props.alert ? ' aips-link-panel-broken' : '') },
			el('strong', null, props.value),
			el('span', null, props.label)
		);
	}

	function SuggestionRow(props) {
		var item = props.item;
		var inserted = item.status === 'inserted';

		return el('li', { className: 'aips-link-panel-suggestion aips-link-suggestion-' + item.status },
			el('a', { href: item.source_edit }, item.source_title),
			el('span', { className: 'aips-link-panel-confidence' }, item.confidence + '%'),
			el('span', { className: 'aips-link-panel-context' }, item.context || l10n.noAnchor),
			el('div', { className: 'aips-link-panel-actions' },
				inserted
					? [
						el('span', { key: 'done', className: 'aips-link-panel-inserted' }, l10n.inserted),
						el(Button, { key: 'undo', variant: 'secondary', isSmall: true, disabled: props.busy, onClick: function () { props.onAction('aips_link_report_revert_suggestion', item.id); } }, l10n.undoBtn)
					]
					: [
						el(Button, { key: 'apply', variant: 'primary', isSmall: true, disabled: props.busy || !item.anchor, onClick: function () { props.onAction('aips_link_report_apply_suggestion', item.id); } }, l10n.insertBtn),
						el(Button, { key: 'dismiss', variant: 'tertiary', isSmall: true, disabled: props.busy, onClick: function () { props.onAction('aips_link_report_dismiss_suggestion', item.id); } }, l10n.dismissBtn)
					]
			)
		);
	}

	function InternalLinksPanel() {
		var dataState = useState(null);
		var data = dataState[0];
		var setData = dataState[1];

		var busyState = useState(false);
		var busy = busyState[0];
		var setBusy = busyState[1];

		var errorState = useState('');
		var error = errorState[0];
		var setError = errorState[1];

		function load() {
			request('aips_link_report_get_post_panel').done(function (response) {
				if (response && response.success) {
					setData(response.data);
					setError('');
				} else {
					setError(errorMessage(response));
				}
			}).fail(function () {
				setError(l10n.loadError);
			});
		}

		useEffect(load, []);

		function suggest() {
			setBusy(true);
			request('aips_link_report_suggest').done(function (response) {
				if (!response || !response.success) {
					notify(errorMessage(response), 'error');
					return;
				}
				setData($.extend({}, data, { suggestions: response.data.suggestions }));
				if (!response.data.suggestions.length) {
					notify(l10n.noSuggestions, 'info');
				}
			}).fail(function () {
				notify(l10n.actionError, 'error');
			}).always(function () {
				setBusy(false);
			});
		}

		function onAction(action, id) {
			setBusy(true);
			request(action, { suggestion_id: id }).done(function (response) {
				if (!response || !response.success) {
					notify(errorMessage(response), 'error');
					return;
				}
				notify(response.data.message, 'success');
				load();
			}).fail(function () {
				notify(l10n.actionError, 'error');
			}).always(function () {
				setBusy(false);
			});
		}

		var body;

		if (error) {
			body = el('p', null, error);
		} else if (!data) {
			body = el(Spinner, null);
		} else if (!data.in_scope) {
			body = el('p', { className: 'description' }, l10n.notPublished);
		} else {
			body = [
				!data.indexed && el('p', { key: 'unindexed', className: 'description' }, l10n.notIndexed),
				el('div', { key: 'counts', className: 'aips-link-panel-counts' },
					el(Count, { value: data.counts.inbound, label: l10n.inbound }),
					el(Count, { value: data.counts.outbound, label: l10n.outbound }),
					el(Count, { value: data.counts.external, label: l10n.external }),
					el(Count, { value: data.counts.broken, label: l10n.broken, alert: data.counts.broken > 0 })
				),
				data.is_orphan && el('p', { key: 'orphan', className: 'aips-link-panel-orphan' }, l10n.orphan),
				data.sources.length > 0 && el('p', { key: 'sources-h', className: 'aips-link-panel-heading' }, l10n.linkedFrom),
				data.sources.length > 0 && el('ul', { key: 'sources', className: 'aips-link-panel-sources' },
					data.sources.map(function (source, i) {
						return el('li', { key: i },
							el('a', { href: source.edit }, source.title),
							' ',
							el('span', { className: 'description' }, '“' + source.anchor + '”')
						);
					})
				),
				el('p', { key: 'sugg-h', className: 'aips-link-panel-heading' }, l10n.suggestions),
				!data.can_suggest && el('p', { key: 'well', className: 'description' }, l10n.wellLinked),
				data.suggestions.length > 0 && el('ul', { key: 'suggestions', className: 'aips-link-panel-suggestions' },
					data.suggestions.map(function (item) {
						return el(SuggestionRow, { key: item.id, item: item, busy: busy, onAction: onAction });
					})
				),
				el('div', { key: 'actions', className: 'aips-link-panel-footer' },
					data.can_suggest && el(Button, { variant: 'secondary', isBusy: busy, disabled: busy, onClick: suggest },
						data.suggestions.length ? l10n.suggestAgainBtn : l10n.suggestBtn
					),
					el('a', { href: data.report_url, className: 'aips-link-panel-report' }, l10n.openReport)
				)
			];
		}

		return el(PluginDocumentSettingPanel, {
			name: 'aips-internal-links-panel',
			title: l10n.panelTitle,
			icon: 'admin-links',
			className: 'aips-link-panel aips-gutenberg-panel'
		}, body);
	}

	wp.plugins.registerPlugin('aips-internal-links', {
		render: InternalLinksPanel
	});
})(window.wp, window.jQuery);
