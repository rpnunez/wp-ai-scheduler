/**
 * Ad & Revenue Optimizer Gutenberg Sidebar
 *
 * Provides real-time ad density calculations, commercial intent insights,
 * per-post ad disable/suppress controls, sponsor selection, and quick block insertion.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.element) {
		return;
	}

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	const { createElement: el, useState, useEffect, Fragment } = wp.element;
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost || wp.editor || {};
	const {
		PanelBody,
		Button,
		Spinner,
		Dashicon,
		Notice,
		ToggleControl,
		SelectControl,
		CheckboxControl
	} = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { createBlock } = wp.blocks;
	const apiFetch = wp.apiFetch;
	const __ = wp.i18n ? wp.i18n.__ : function (text) { return text; };

	/**
	 * Main Sidebar Component
	 */
	function MonetizationSidebarComponent() {
		const [slots, setSlots] = useState([]);
		const [campaigns, setCampaigns] = useState([]);
		const [isLoading, setIsLoading] = useState(true);
		const [intentData, setIntentData] = useState(null);
		const [isAnalyzing, setIsAnalyzing] = useState(false);

		// Post meta via core/editor
		const postMeta = useSelect(function (select) {
			const editor = select('core/editor');
			return editor ? (editor.getEditedPostAttribute('meta') || {}) : {};
		}, []);

		const { editPost } = useDispatch('core/editor') || {};
		const { insertBlocks } = useDispatch('core/block-editor') || {};

		const disableAds = postMeta._aips_disable_ads === '1' || postMeta._aips_disable_ads === true;
		const selectedCampaignId = parseInt(postMeta._aips_sponsor_campaign_id || 0, 10);
		const suppressedSlots = Array.isArray(postMeta._aips_suppressed_slots) ? postMeta._aips_suppressed_slots : [];

		// Read current editor content & blocks
		const { blocks, wordCount } = useSelect(function (select) {
			const editor = select('core/editor');
			if (!editor) {
				return { blocks: [], wordCount: 0 };
			}
			const allBlocks = editor.getBlocks() || [];
			const text = editor.getEditedPostContent() || '';
			const words = text.replace(/<[^>]*>/g, ' ').trim().split(/\s+/).filter(Boolean).length;
			return { blocks: allBlocks, wordCount: words };
		}, []);

		// Fetch initial slots and campaigns
		useEffect(function () {
			Promise.all([
				apiFetch({ path: '/aips/v1/monetization/slots' }),
				apiFetch({ path: '/aips/v1/monetization/campaigns' })
			]).then(function (results) {
				if (results[0] && results[0].slots) {
					setSlots(results[0].slots);
				}
				if (results[1] && results[1].campaigns) {
					setCampaigns(results[1].campaigns);
				}
				setIsLoading(false);
			}).catch(function () {
				setIsLoading(false);
			});
		}, []);

		// Analyze intent
		const runIntentAnalysis = function () {
			setIsAnalyzing(true);
			const title = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
			const content = wp.data.select('core/editor').getEditedPostContent() || '';

			apiFetch({
				path: '/aips/v1/monetization/analyze-intent',
				method: 'POST',
				data: { title: title, content: content }
			}).then(function (res) {
				if (res && res.success) {
					setIntentData(res);
				}
				setIsAnalyzing(false);
			}).catch(function () {
				setIsAnalyzing(false);
			});
		};

		// Run intent analysis on first load once content is ready
		useEffect(function () {
			if (wordCount > 50 && !intentData && !isAnalyzing) {
				runIntentAnalysis();
			}
		}, [wordCount]);

		// Helper to update post meta
		const updateMeta = function (key, value) {
			if (!editPost) {
				return;
			}
			const newMeta = Object.assign({}, postMeta);
			newMeta[key] = value;
			editPost({ meta: newMeta });
		};

		// Calculate ad density
		const paragraphBlocksCount = blocks.filter(function (b) { return b.name === 'core/paragraph'; }).length;
		const activeRuntimeSlotsCount = disableAds ? 0 : slots.filter(function (s) {
			return s.status === 'active' && s.position !== 'custom_shortcode' && !suppressedSlots.includes(s.id);
		}).length;

		let densityStatus = 'Optimal';
		let densityColor = '#10b981';
		if (disableAds) {
			densityStatus = 'Disabled';
			densityColor = '#64748b';
		} else if (wordCount < 250) {
			densityStatus = 'Light (Short Post)';
			densityColor = '#3b82f6';
		} else if (activeRuntimeSlotsCount > 4) {
			densityStatus = 'High Ad Density';
			densityColor = '#f59e0b';
		}

		// Insert ad unit block
		const handleInsertAdBlock = function (slotId) {
			if (!insertBlocks) {
				return;
			}
			const slot = slots.find(function (s) { return s.id === slotId; });
			const block = createBlock('aips/ad-unit', {
				slotId: slotId,
				slotName: slot ? slot.name : ''
			});
			insertBlocks(block);
		};

		return el(PluginSidebar, {
			name: 'aips-monetization-sidebar',
			title: __('Ad & Revenue Optimizer', 'ai-post-scheduler'),
			icon: 'money-alt'
		},
			isLoading ? el('div', { className: 'aips-sidebar-loading' }, el(Spinner)) : el(Fragment, {},

				// Panel 1: Revenue & Placement Preview
				el(PanelBody, { title: __('Live Ad Density & Pacing', 'ai-post-scheduler'), initialOpen: true },
					el('div', { className: 'aips-density-widget' },
						el('div', { className: 'aips-density-stat-row' },
							el('span', {}, __('Article Length:', 'ai-post-scheduler')),
							el('strong', {}, wordCount + ' ' + __('words', 'ai-post-scheduler'))
						),
						el('div', { className: 'aips-density-stat-row' },
							el('span', {}, __('Paragraphs:', 'ai-post-scheduler')),
							el('strong', {}, paragraphBlocksCount)
						),
						el('div', { className: 'aips-density-stat-row' },
							el('span', {}, __('Active In-Article Ads:', 'ai-post-scheduler')),
							el('strong', {}, activeRuntimeSlotsCount + ' ' + __('slots', 'ai-post-scheduler'))
						),
						el('div', { className: 'aips-density-badge', style: { backgroundColor: densityColor } },
							densityStatus
						)
					)
				),

				// Panel 2: Commercial Intent & RPM Scorer
				el(PanelBody, { title: __('Commercial Intent & RPM', 'ai-post-scheduler'), initialOpen: true },
					el('p', { className: 'description' },
						__('AI analyzes keyword buying intent to estimate ad value.', 'ai-post-scheduler')
					),
					isAnalyzing ? el(Spinner) : (intentData ? el('div', { className: 'aips-intent-box' },
						el('div', { className: 'aips-intent-badge', style: { backgroundColor: intentData.badge_color } },
							intentData.intent
						),
						el('div', { className: 'aips-rpm-estimate' },
							el('span', {}, __('Est. Ad RPM: ', 'ai-post-scheduler')),
							el('strong', {}, intentData.rpm_estimate)
						)
					) : null),
					el(Button, {
						isSecondary: true,
						isSmall: true,
						onClick: runIntentAnalysis,
						disabled: isAnalyzing,
						className: 'aips-reanalyze-btn'
					}, __('Re-analyze Content', 'ai-post-scheduler'))
				),

				// Panel 3: Post Overrides
				el(PanelBody, { title: __('Ad Suppression & Overrides', 'ai-post-scheduler'), initialOpen: false },
					el(ToggleControl, {
						label: __('Disable All Ads on this Post', 'ai-post-scheduler'),
						checked: disableAds,
						onChange: function (val) {
							updateMeta('_aips_disable_ads', val ? '1' : '0');
						}
					}),
					!disableAds && slots.length > 0 && el(Fragment, {},
						el('h4', { className: 'aips-subheading' }, __('Suppress Specific Slots', 'ai-post-scheduler')),
						slots.map(function (s) {
							const isSuppressed = suppressedSlots.includes(s.id);
							return el(CheckboxControl, {
								key: s.id,
								label: s.name + ' (' + s.position + ')',
								checked: isSuppressed,
								onChange: function (checked) {
									let updated = suppressedSlots.slice();
									if (checked) {
										if (!updated.includes(s.id)) { updated.push(s.id); }
									} else {
										updated = updated.filter(function (id) { return id !== s.id; });
									}
									updateMeta('_aips_suppressed_slots', updated);
								}
							});
						})
					)
				),

				// Panel 4: Sponsor Campaign Assignment
				el(PanelBody, { title: __('Sponsor & FTC Disclosure', 'ai-post-scheduler'), initialOpen: false },
					el(SelectControl, {
						label: __('Assign Sponsor Campaign', 'ai-post-scheduler'),
						value: selectedCampaignId,
						options: [{ label: __('Auto-Detect by Topic / None', 'ai-post-scheduler'), value: 0 }].concat(
							campaigns.map(function (c) {
								return { label: c.brand_name, value: c.id };
							})
						),
						onChange: function (val) {
							updateMeta('_aips_sponsor_campaign_id', parseInt(val, 10));
						}
					}),
					selectedCampaignId > 0 && el('div', { className: 'aips-sponsor-assigned-notice' },
						el(Dashicon, { icon: 'yes-alt' }),
						el('span', {}, __('FTC Disclosure banner will be automatically injected at top of post.', 'ai-post-scheduler'))
					)
				),

				// Panel 5: Quick Ad Unit Block Inserter
				el(PanelBody, { title: __('One-Click Ad Block Inserter', 'ai-post-scheduler'), initialOpen: false },
					el('p', { className: 'description' },
						__('Click to drop a specific ad block into your post content.', 'ai-post-scheduler')
					),
					slots.map(function (s) {
						return el('div', { key: s.id, className: 'aips-slot-inserter-row' },
							el('span', {}, s.name),
							el(Button, {
								isSmall: true,
								isPrimary: true,
								onClick: function () { handleInsertAdBlock(s.id); }
							}, __('Insert', 'ai-post-scheduler'))
						);
					})
				)
			)
		);
	}

	/**
	 * @namespace AIPS.EditorMonetization
	 */
	AIPS.EditorMonetization = {
		init: function () {
			registerPlugin('aips-monetization-sidebar', {
				render: function () {
					return el(Fragment, {},
						el(PluginSidebarMoreMenuItem, {
							target: 'aips-monetization-sidebar',
							icon: 'money-alt'
						}, __('Ad & Revenue Optimizer', 'ai-post-scheduler')),
						el(MonetizationSidebarComponent, {})
					);
				},
				icon: 'money-alt'
			});
		}
	};

	AIPS.EditorMonetization.init();

})(window.wp);
