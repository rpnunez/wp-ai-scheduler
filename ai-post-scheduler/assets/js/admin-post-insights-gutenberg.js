/**
 * Post Insights Gutenberg Sidebar Integration.
 *
 * Registers a native Document Setting Panel in the WordPress Block Editor sidebar
 * displaying vector status, duplicate risk, post cluster, and generation history.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.7
 */

(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.element) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var Button = wp.components.Button;

	var l10n = window.aipsPostInsightsL10n || {};
	var insights = l10n.insights || {};
	var postId = l10n.postId || 0;

	/**
	 * AI Insights Document Setting Panel Component.
	 */
	function AipsPostInsightsPanel() {
		var stateData = useState(insights);
		var data = stateData[0];
		var setData = stateData[1];

		var stateLoading = useState(false);
		var isLoading = stateLoading[0];
		var setIsLoading = stateLoading[1];

		var stateMsg = useState('');
		var message = stateMsg[0];
		var setMessage = stateMsg[1];

		var embedding = data.embedding || {};
		var isIndexed = !!embedding.is_indexed;
		var cluster = data.cluster || null;
		var duplicates = data.top_duplicates || [];
		var maxSim = data.max_similarity_pct || 0;
		var overallRisk = data.overall_risk || 'clean';
		var overallLabel = data.overall_label || (l10n.cleanLabel || 'Clean');
		var history = data.history || null;

		// Handler: Re-index post
		var handleReindex = function () {
			setIsLoading(true);
			setMessage('');

			jQuery.ajax({
				url: l10n.ajaxurl || ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_reindex_single_post',
					nonce: l10n.nonce,
					post_id: postId
				},
				success: function (res) {
					setIsLoading(false);
					if (res.success) {
						setData(res.data);
						setMessage(res.data.message || (l10n.reindexedSuccess || 'Vector re-indexed successfully.'));
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Re-indexing failed.');
					}
				},
				error: function () {
					setIsLoading(false);
					alert('Error connecting to server.');
				}
			});
		};

		// Handler: Toggle pillar
		var handleTogglePillar = function () {
			if (!cluster || !cluster.cluster_id) {
				return;
			}
			setIsLoading(true);

			jQuery.ajax({
				url: l10n.ajaxurl || ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_toggle_single_pillar',
					nonce: l10n.nonce,
					post_id: postId,
					cluster_id: cluster.cluster_id
				},
				success: function (res) {
					setIsLoading(false);
					if (res.success) {
						setData(res.data);
					}
				},
				error: function () {
					setIsLoading(false);
				}
			});
		};

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'aips-post-insights-panel',
				title: l10n.panelTitle || 'AI Insights & Duplication',
				icon: 'networking',
				className: 'aips-gutenberg-panel'
			},
			// Top Risk Status
			el(
				'div',
				{ className: 'aips-gutenberg-row' },
				el(
					'span',
					{ className: 'aips-risk-pill aips-risk-' + overallRisk },
					overallLabel + (maxSim > 0 ? ' (' + maxSim + '%)' : '')
				),
				cluster && cluster.is_pillar && el(
					'span',
					{ className: 'aips-pillar-tag' },
					'★ ' + (l10n.pillarLabel || 'Pillar Post')
				)
			),
			message && el('p', { className: 'aips-text-success', style: { fontSize: '12px', margin: '4px 0 8px 0' } }, message),

			// Section: Embedding Status
			el(
				'div',
				{ className: 'aips-gutenberg-section' },
				el('div', { className: 'aips-gutenberg-section-title' }, l10n.vectorTitle || 'Vector Embedding'),
				el(
					'p',
					{ style: { fontSize: '12px', margin: '0 0 6px 0' } },
					isIndexed
						? el('span', null, el('strong', { className: 'aips-text-success' }, 'Indexed '), '(' + embedding.dimensions + ' dims, ' + embedding.indexed_str + ')')
						: el('span', { className: 'aips-text-muted' }, 'Not yet indexed.')
				),
				el(
					Button,
					{
						isSecondary: true,
						isSmall: true,
						isBusy: isLoading,
						disabled: isLoading,
						onClick: handleReindex
					},
					isIndexed ? (l10n.reindexBtn || 'Re-Index Vector') : (l10n.indexNowBtn || 'Index Now')
				)
			),

			// Section: Post Cluster
			cluster && el(
				'div',
				{ className: 'aips-gutenberg-section' },
				el('div', { className: 'aips-gutenberg-section-title' }, l10n.clusterTitle || 'Post Cluster'),
				el(
					'p',
					{ style: { fontSize: '12px', margin: '0 0 6px 0' } },
					el('strong', null, cluster.name),
					' (' + cluster.post_count + ' posts)'
				),
				el(
					Button,
					{
						isSecondary: true,
						isSmall: true,
						isBusy: isLoading,
						disabled: isLoading,
						onClick: handleTogglePillar
					},
					cluster.is_pillar ? '★ ' + (l10n.designatedPillar || 'Designated Pillar') : (l10n.setAsPillar || 'Set as Pillar')
				)
			),

			// Section: Semantic Duplicates
			el(
				'div',
				{ className: 'aips-gutenberg-section' },
				el('div', { className: 'aips-gutenberg-section-title' }, l10n.duplicatesTitle || 'Semantic Duplicate Risk'),
				duplicates.length > 0
					? duplicates.map(function (dup, i) {
						return el(
							'div',
							{ key: i, style: { display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', marginBottom: '4px' } },
							el('span', { className: 'aips-risk-badge aips-risk-' + dup.risk_level }, dup.similarity_pct + '%'),
							el('a', { href: dup.edit_url, target: '_blank', style: { textDecoration: 'none', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, dup.title)
						);
					})
					: el('p', { className: 'aips-text-muted', style: { fontSize: '12px', margin: 0 } }, l10n.noDuplicates || 'No conflicting duplicates detected.')
			),

			// Section: Generation Context
			history && el(
				'div',
				{ className: 'aips-gutenberg-section' },
				el('div', { className: 'aips-gutenberg-section-title' }, l10n.contextTitle || 'AI Generation Context'),
				history.author_name && el('div', { style: { fontSize: '12px', display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } },
					el('span', { className: 'aips-text-muted' }, 'Author:'),
					el('strong', null, history.author_name)
				),
				history.template_title && el('div', { style: { fontSize: '12px', display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } },
					el('span', { className: 'aips-text-muted' }, 'Template:'),
					el('span', null, history.template_title)
				),
				history.topic_title && el('div', { style: { fontSize: '12px', display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } },
					el('span', { className: 'aips-text-muted' }, 'Topic:'),
					el('span', null, history.topic_title)
				),
				el('div', { style: { fontSize: '12px', display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } },
					el('span', { className: 'aips-text-muted' }, 'Generated:'),
					el('span', null, history.created_str)
				),
				history.tokens_used > 0 && el('div', { style: { fontSize: '12px', display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } },
					el('span', { className: 'aips-text-muted' }, 'Tokens:'),
					el('span', null, history.tokens_used)
				),
				history.history_url && el(
					'div',
					{ style: { marginTop: '10px' } },
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							href: history.history_url,
							target: '_blank'
						},
						l10n.viewHistoryBtn || 'View AI Generation History'
					)
				)
			)
		);
	}

	registerPlugin('aips-post-insights', {
		render: AipsPostInsightsPanel
	});

})(window.wp);
