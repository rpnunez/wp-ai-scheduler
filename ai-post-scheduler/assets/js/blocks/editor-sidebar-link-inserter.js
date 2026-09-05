/**
 * Semantic Link Inserter & Anchor Suggestion Gutenberg Sidebar
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.element) {
		return;
	}

	const { createElement: el, useState, useEffect, useRef, useCallback, Fragment } = wp.element;
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost || wp.editor || {};
	const {
		PanelBody,
		Button,
		Spinner,
		Dashicon,
		Notice,
		Tooltip,
		RangeControl,
		SelectControl,
		TextControl,
		Modal
	} = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const apiFetch = wp.apiFetch;
	const __ = wp.i18n ? wp.i18n.__ : function (text) { return text; };

	const settings = window.aipsEditorSettings || {
		restUrl: '/wp-json/aips/v1/editor/',
		nonce: '',
		postId: 0,
		similarityMin: 0.60,
		maxSuggestions: 5,
		postTypes: [{ label: 'All Post Types', value: '' }],
		linkGraphConfig: {
			showPostMetrics: true,
			showCardBadges: true,
			enableOpportunitySort: true,
			enableVisualModal: false
		},
		i18n: {}
	};

	const linkGraphConfig = settings.linkGraphConfig || {
		showPostMetrics: true,
		showCardBadges: true,
		enableOpportunitySort: true,
		enableVisualModal: false
	};

	const t = function (key, defaultText) {
		return (settings.i18n && settings.i18n[key]) ? settings.i18n[key] : defaultText;
	};

	/**
	 * Main Sidebar Component
	 */
	function SemanticLinkInserterSidebar() {
		const [suggestions, setSuggestions] = useState([]);
		const [isLoading, setIsLoading] = useState(false);
		const [errorMessage, setErrorMessage] = useState('');
		const [expandedPostId, setExpandedPostId] = useState(null);
		const [anchorsState, setAnchorsState] = useState({});
		const [insertedAnchors, setInsertedAnchors] = useState({});

		// Filter & search options state
		const [similarityThreshold, setSimilarityThreshold] = useState(settings.similarityMin || 0.60);
		const [maxSuggestions, setMaxSuggestions] = useState(settings.maxSuggestions || 5);
		const [selectedPostType, setSelectedPostType] = useState('');
		const [searchQuery, setSearchQuery] = useState('');
		const [sortBy, setSortBy] = useState('similarity');

		// SEO Graph metrics state
		const [postSeoMetrics, setPostSeoMetrics] = useState({
			inbound_count: 0,
			outbound_count: 0,
			depth_level: 0,
			is_orphan: false,
			equity_tier: 'low'
		});

		// Visual graph modal state
		const [isGraphModalOpen, setIsGraphModalOpen] = useState(false);
		const [modalGraphData, setModalGraphData] = useState({ nodes: [], links: [] });
		const [isGraphLoading, setIsGraphLoading] = useState(false);

		const debounceTimerRef = useRef(null);

		// Get current editor state
		const { postId, postContent, activeBlock, allBlocks } = useSelect(function (select) {
			const editorSelect = select('core/editor');
			const blockSelect  = select('core/block-editor');

			return {
				postId: editorSelect ? editorSelect.getCurrentPostId() : settings.postId,
				postContent: editorSelect ? editorSelect.getEditedPostContent() : '',
				activeBlock: blockSelect ? blockSelect.getSelectedBlock() : null,
				allBlocks: blockSelect ? blockSelect.getBlocks() : []
			};
		}, []);

		const { updateBlockAttributes } = useDispatch('core/block-editor');
		const { createNotice } = useDispatch('core/notices');

		// Fetch SEO metrics for current post
		useEffect(function () {
			if (postId > 0 && linkGraphConfig.showPostMetrics !== false) {
				apiFetch({
					path: '/aips/v1/editor/post-seo-metrics?post_id=' + postId,
					method: 'GET'
				})
				.then(function (res) {
					if (res && res.success && res.metrics) {
						setPostSeoMetrics(res.metrics);
					}
				})
				.catch(function () {});
			}
		}, [postId]);

		/**
		 * Fetch link suggestions based on current content & filters
		 */
		const fetchSuggestions = useCallback(function (forceContent) {
			const contentToScan = typeof forceContent === 'string' ? forceContent : postContent;
			const hasQuery = searchQuery && searchQuery.trim().length >= 2;

			if (!hasQuery && (!contentToScan || contentToScan.replace(/<[^>]*>/g, '').trim().length < 15)) {
				setSuggestions([]);
				return;
			}

			setIsLoading(true);
			setErrorMessage('');

			apiFetch({
				path: '/aips/v1/editor/link-suggestions',
				method: 'POST',
				data: {
					post_id: postId || 0,
					content: contentToScan,
					query: searchQuery ? searchQuery.trim() : '',
					target_post_type: selectedPostType || '',
					sort_by: sortBy || 'similarity',
					limit: maxSuggestions || 5,
					min_similarity: similarityThreshold || 0.60
				}
			})
			.then(function (response) {
				setIsLoading(false);
				if (response && response.success && Array.isArray(response.suggestions)) {
					setSuggestions(response.suggestions);
				} else {
					setSuggestions([]);
				}
			})
			.catch(function (error) {
				setIsLoading(false);
				setErrorMessage((error && error.message) ? error.message : 'Error fetching link suggestions.');
			});
		}, [postId, postContent, searchQuery, selectedPostType, sortBy, maxSuggestions, similarityThreshold]);

		// Debounce suggestions fetch on content or filter changes
		useEffect(function () {
			if (debounceTimerRef.current) {
				clearTimeout(debounceTimerRef.current);
			}

			debounceTimerRef.current = setTimeout(function () {
				fetchSuggestions();
			}, 600);

			return function () {
				if (debounceTimerRef.current) {
					clearTimeout(debounceTimerRef.current);
				}
			};
		}, [postContent, searchQuery, selectedPostType, sortBy, maxSuggestions, similarityThreshold, fetchSuggestions]);

		/**
		 * Fetch AI Anchor locations for a specific target post
		 */
		const fetchAnchorsForPost = function (targetPostId, targetTitle) {
			const targetId = parseInt(targetPostId, 10);
			if (!targetId) {
				return;
			}

			const contentToScan = (activeBlock && activeBlock.attributes && activeBlock.attributes.content)
				? activeBlock.attributes.content
				: postContent;

			if (!contentToScan || contentToScan.replace(/<[^>]*>/g, '').trim().length < 10) {
				setAnchorsState(function (prev) {
					const next = Object.assign({}, prev);
					next[targetId] = {
						loading: false,
						locations: [],
						error: 'Write a little more content in your draft before generating anchor insertion points.'
					};
					return next;
				});
				return;
			}

			setAnchorsState(function (prev) {
				const next = Object.assign({}, prev);
				next[targetId] = { loading: true, locations: [], error: '' };
				return next;
			});

			apiFetch({
				path: '/aips/v1/editor/find-anchors',
				method: 'POST',
				data: {
					source_content: contentToScan,
					target_post_id: targetId,
					post_id: postId || 0,
					limit: 3
				}
			})
			.then(function (response) {
				if (response && response.success && response.data && Array.isArray(response.data.locations)) {
					setAnchorsState(function (prev) {
						const next = Object.assign({}, prev);
						next[targetId] = {
							loading: false,
							locations: response.data.locations,
							error: response.data.locations.length === 0 ? t('noAnchorsFound', 'No natural anchor points found in draft text.') : ''
						};
						return next;
					});
				} else {
					setAnchorsState(function (prev) {
						const next = Object.assign({}, prev);
						next[targetId] = {
							loading: false,
							locations: [],
							error: 'Failed to retrieve anchor opportunities.'
						};
						return next;
					});
				}
			})
			.catch(function (error) {
				setAnchorsState(function (prev) {
					const next = Object.assign({}, prev);
					next[targetId] = {
						loading: false,
						locations: [],
						error: (error && error.message) ? error.message : 'Error extracting anchor locations.'
					};
					return next;
				});
			});
		};

		/**
		 * Open Mini Link Graph Modal
		 */
		const openLinkGraphModal = function () {
			setIsGraphModalOpen(true);
			setIsGraphLoading(true);

			const targetIds = suggestions.map(function (s) { return s.id; }).join(',');
			apiFetch({
				path: '/aips/v1/editor/link-graph-modal-data?post_id=' + (postId || 0) + '&target_ids=' + targetIds,
				method: 'GET'
			})
			.then(function (res) {
				setIsGraphLoading(false);
				if (res && res.success && res.data) {
					setModalGraphData(res.data);
				}
			})
			.catch(function () {
				setIsGraphLoading(false);
			});
		};

		/**
		 * 1-Click Link Insertion into active/matching paragraph block
		 */
		const insertLinkIntoBlock = function (item, loc, anchorCardKey) {
			const anchorPhrase = loc.anchor_phrase;
			const targetUrl    = item.url;
			const matchSnippet = loc.match_snippet;

			if (!anchorPhrase || !targetUrl) {
				return;
			}

			const anchorLinkHtml = '<a href="' + encodeURI(targetUrl) + '">' + anchorPhrase + '</a>';
			const targetReplacementHtml = matchSnippet
				? matchSnippet.replace('[[' + anchorPhrase + ']]', anchorLinkHtml)
				: anchorLinkHtml;

			let insertionApplied = false;

			const decodeEntities = function (str) {
				if (!str) return '';
				const textarea = document.createElement('textarea');
				textarea.innerHTML = str;
				return textarea.value;
			};

			const tryReplaceInContent = function (content) {
				if (!content) return null;
				if (content.indexOf(matchSnippet) !== -1) {
					return content.replace(matchSnippet, targetReplacementHtml);
				}
				const decodedMatch = decodeEntities(matchSnippet);
				if (decodedMatch && content.indexOf(decodedMatch) !== -1) {
					return content.replace(decodedMatch, targetReplacementHtml);
				}
				if (content.indexOf(anchorPhrase) !== -1) {
					return content.replace(anchorPhrase, anchorLinkHtml);
				}
				const decodedAnchor = decodeEntities(anchorPhrase);
				if (decodedAnchor && content.indexOf(decodedAnchor) !== -1) {
					return content.replace(decodedAnchor, anchorLinkHtml);
				}
				return null;
			};

			// Step 1: Check active block first
			if (activeBlock && activeBlock.name === 'core/paragraph' && activeBlock.attributes && activeBlock.attributes.content) {
				const blockHtml = activeBlock.attributes.content;
				const replacedHtml = tryReplaceInContent(blockHtml);

				if (replacedHtml !== null) {
					updateBlockAttributes(activeBlock.clientId, { content: replacedHtml });
					insertionApplied = true;
				}
			}

			// Step 2: Fallback to searching all paragraph blocks in the document
			if (!insertionApplied && Array.isArray(allBlocks)) {
				for (let i = 0; i < allBlocks.length; i++) {
					const block = allBlocks[i];
					if (block.name === 'core/paragraph' && block.attributes && block.attributes.content) {
						const blockHtml = block.attributes.content;
						const replacedHtml = tryReplaceInContent(blockHtml);

						if (replacedHtml !== null) {
							updateBlockAttributes(block.clientId, { content: replacedHtml });
							insertionApplied = true;
							break;
						}
					}
				}
			}

			if (insertionApplied) {
				setInsertedAnchors(function (prev) {
					const next = Object.assign({}, prev);
					next[anchorCardKey] = true;
					return next;
				});

				if (createNotice) {
					createNotice('success', t('linkInserted', 'Link inserted successfully!'), {
						type: 'snackbar',
						isDismissible: true
					});
				}
			} else {
				if (createNotice) {
					createNotice('warning', 'Could not locate matching text in paragraph blocks. Please highlight or place cursor in the target paragraph.', {
						type: 'snackbar',
						isDismissible: true
					});
				}
			}
		};

		/**
		 * Render highlighted snippet with [[anchor]] styled
		 */
		const renderSnippet = function (snippet) {
			if (!snippet) {
				return null;
			}

			const parts = snippet.split(/(\[\[.*?\]\])/);
			return parts.map(function (part, idx) {
				if (part.startsWith('[[') && part.endsWith(']]')) {
					const cleanText = part.slice(2, -2);
					return el('span', { key: idx, className: 'aips-anchor-highlight' }, cleanText);
				}
				return part;
			});
		};

		/**
		 * Get CSS class for similarity badge
		 */
		const getSimilarityClass = function (pct) {
			if (pct >= 80) {
				return 'is-high';
			}
			if (pct >= 68) {
				return 'is-medium';
			}
			return 'is-low';
		};

		return el(
			'div',
			{ className: 'aips-editor-sidebar-panel' },
			el('div', { className: 'aips-sidebar-toolbar' },
				el('span', { className: 'aips-status-pill' },
					el(Dashicon, { icon: 'admin-links', size: 14 }),
					suggestions.length + ' ' + t('similarity', 'Matches')
				),
				el('div', { className: 'aips-toolbar-btns' },
					linkGraphConfig.enableVisualModal && el(Button, {
						isSmall: true,
						variant: 'tertiary',
						icon: 'networking',
						label: t('viewLinkGraph', 'View Mini Link Graph'),
						onClick: openLinkGraphModal
					}),
					el(Button, {
						isSmall: true,
						variant: 'tertiary',
						icon: 'update',
						label: t('refresh', 'Refresh Suggestions'),
						onClick: function () { fetchSuggestions(); }
					})
				)
			),

			// Active Post SEO Metric Bar
			linkGraphConfig.showPostMetrics !== false && el('div', { className: 'aips-seo-metrics-bar' },
				el('div', { className: 'aips-metric-badge', title: t('inboundLinks', 'Inbound Links') },
					el('span', { className: 'aips-metric-val' }, '📥 ' + postSeoMetrics.inbound_count),
					el('span', { className: 'aips-metric-label' }, t('inboundLinks', 'Inbound'))
				),
				el('div', { className: 'aips-metric-badge', title: t('outboundLinks', 'Outbound Links') },
					el('span', { className: 'aips-metric-val' }, '📤 ' + postSeoMetrics.outbound_count),
					el('span', { className: 'aips-metric-label' }, t('outboundLinks', 'Outbound'))
				),
				el('div', { className: 'aips-metric-badge', title: t('crawlDepth', 'Crawl Depth from Root') },
					el('span', { className: 'aips-metric-val' }, (postSeoMetrics.depth_level === 99 ? '∞' : ('L' + postSeoMetrics.depth_level))),
					el('span', { className: 'aips-metric-label' }, t('crawlDepth', 'Depth'))
				),
				postSeoMetrics.is_orphan && el('div', { className: 'aips-orphan-alert' },
					el(Dashicon, { icon: 'warning', size: 14 }),
					el('span', {}, t('orphanPostAlert', 'Orphan Post: 0 inbound links pointing here!'))
				)
			),

			el('p', { className: 'aips-sidebar-intro' },
				t('activeBlockNote', 'Context-aware internal link recommendations powered by semantic vector graph.')
			),

			// Collapsible Filters & Custom Search Panel
			el(PanelBody, {
				title: t('filtersTitle', 'Filters & Custom Search'),
				initialOpen: false
			},
				el(TextControl, {
					label: t('searchLabel', 'Topic / Keyword Search'),
					value: searchQuery,
					placeholder: t('searchPlaceholder', 'e.g. Docker caching, vector store...'),
					onChange: function (val) { setSearchQuery(val); }
				}),
				linkGraphConfig.enableOpportunitySort !== false && el(SelectControl, {
					label: t('sortByLabel', 'Sort Suggestions By:'),
					value: sortBy,
					options: [
						{ label: t('sortSimilarity', 'Relevance / Similarity'), value: 'similarity' },
						{ label: t('sortOpportunity', 'SEO Opportunity (Under-linked First)'), value: 'seo_opportunity' }
					],
					onChange: function (val) { setSortBy(val); }
				}),
				el(RangeControl, {
					label: t('similarityThresholdLabel', 'Min Similarity (%):'),
					value: Math.round(similarityThreshold * 100),
					min: 40,
					max: 90,
					step: 5,
					onChange: function (val) { setSimilarityThreshold(val / 100); }
				}),
				el(RangeControl, {
					label: t('maxSuggestionsLabel', 'Max Suggestions:'),
					value: maxSuggestions,
					min: 1,
					max: 15,
					step: 1,
					onChange: function (val) { setMaxSuggestions(val); }
				}),
				el(SelectControl, {
					label: t('postTypeLabel', 'Target Post Type:'),
					value: selectedPostType,
					options: settings.postTypes || [{ label: 'All Post Types', value: '' }],
					onChange: function (val) { setSelectedPostType(val); }
				}),
				el(Button, {
					isSecondary: true,
					isSmall: true,
					onClick: function () {
						setSearchQuery('');
						setSelectedPostType('');
						setSortBy('similarity');
						setSimilarityThreshold(settings.similarityMin || 0.60);
						setMaxSuggestions(settings.maxSuggestions || 5);
					}
				}, t('resetFilters', 'Reset Filters'))
			),

			errorMessage && el(Notice, {
				status: 'error',
				isDismissible: true,
				onDismiss: function () { setErrorMessage(''); }
			}, errorMessage),

			isLoading && el('div', { className: 'aips-loading-box' },
				el(Spinner, {}),
				el('span', {}, t('searching', 'Scanning semantic graph for relevant articles...'))
			),

			!isLoading && suggestions.length === 0 && el('div', { className: 'aips-empty-box' },
				el('p', {}, t('noSuggestions', 'No semantic link suggestions found yet. Keep writing or check back once more articles are indexed.'))
			),

			!isLoading && suggestions.length > 0 && el('div', { className: 'aips-suggestions-list' },
				suggestions.map(function (item) {
					const isExpanded  = expandedPostId === item.id;
					const anchorData  = anchorsState[item.id] || { loading: false, locations: [], error: '' };
					const simClass    = getSimilarityClass(item.similarity_pct);

					return el(
						'div',
						{
							key: item.id,
							className: 'aips-suggestion-card' + (isExpanded ? ' is-expanded' : '')
						},
						el('div', { className: 'aips-card-header' },
							el('h4', { className: 'aips-card-title' },
								el('a', {
									href: item.url,
									target: '_blank',
									rel: 'noopener noreferrer'
								}, item.title)
							),
							el('span', {
								className: 'aips-similarity-badge ' + simClass,
								title: item.is_precomputed ? t('precomputed', 'Precomputed') : t('realtime', 'Real-Time')
							}, item.similarity_pct + '%')
						),

						// SEO Card Badges
						linkGraphConfig.showCardBadges !== false && el('div', { className: 'aips-card-seo-row' },
							el('span', { className: 'aips-seo-pill' }, '📥 ' + (item.inbound_count || 0) + ' ' + t('inboundLinks', 'inbound')),
							item.is_orphan && el('span', { className: 'aips-seo-pill is-orphan' }, '⚠️ ' + t('highOpportunityBadge', 'High Opportunity')),
							item.is_already_linked && el('span', { className: 'aips-seo-pill is-linked' }, '🔗 ' + t('alreadyLinkedBadge', 'Already Linked')),
							(item.cross_link && item.cross_link.is_two_hop) && el('span', { className: 'aips-seo-pill is-crosslink' }, '2-Hop Cross Link')
						),

						item.excerpt && el('p', { className: 'aips-card-excerpt' }, item.excerpt),

						el('div', { className: 'aips-card-actions' },
							el('span', { className: 'aips-card-type-tag' }, item.post_type || 'post'),
							el(Button, {
								isSmall: true,
								variant: 'tertiary',
								title: t('copyLink', 'Copy URL to clipboard'),
								onClick: function () {
									if (navigator.clipboard && item.url) {
										navigator.clipboard.writeText(item.url);
										if (createNotice) {
											createNotice('info', t('urlCopied', 'Link copied to clipboard!'), {
												type: 'snackbar',
												isDismissible: true
											});
										}
									}
								}
							}, '📋 ' + t('copyLinkShort', 'Copy')),
							el(Button, {
								isSmall: true,
								variant: 'secondary',
								title: t('insertDirectTitle', 'Insert link into active paragraph'),
								onClick: function () {
									if (activeBlock && activeBlock.name === 'core/paragraph') {
										const currentHtml = (activeBlock.attributes && activeBlock.attributes.content) ? activeBlock.attributes.content : '';
										const linkTag = '<a href="' + encodeURI(item.url) + '">' + item.title + '</a>';
										const newHtml = currentHtml ? (currentHtml + ' ' + linkTag) : linkTag;
										updateBlockAttributes(activeBlock.clientId, { content: newHtml });
										if (createNotice) {
											createNotice('success', t('linkInserted', 'Link inserted successfully!'), {
												type: 'snackbar',
												isDismissible: true
											});
										}
									} else if (createNotice) {
										createNotice('warning', t('selectParagraphFirst', 'Please click inside a paragraph block to insert link.'), {
											type: 'snackbar',
											isDismissible: true
										});
									}
								}
							}, '🔗 ' + t('insertDirect', 'Direct Link')),
							el(Button, {
								isSmall: true,
								variant: isExpanded ? 'primary' : 'secondary',
								'aria-expanded': isExpanded,
								'aria-label': (isExpanded ? 'Hide' : 'Find') + ' anchor opportunities for ' + item.title,
								onClick: function () {
									if (isExpanded) {
										setExpandedPostId(null);
									} else {
										setExpandedPostId(item.id);
										if (!anchorsState[item.id] || (!anchorsState[item.id].locations.length && !anchorsState[item.id].loading)) {
											fetchAnchorsForPost(item.id, item.title);
										}
									}
								}
							}, isExpanded ? __('Hide Anchors', 'ai-post-scheduler') : t('findAnchors', 'Find Anchors'))
						),

						// Expandable Anchors Panel
						isExpanded && el(
							'div',
							{ className: 'aips-anchors-container' },
							anchorData.loading && el('div', { className: 'aips-loading-box' },
								el(Spinner, {}),
								el('span', {}, t('findingAnchors', 'Analyzing content for anchor points...'))
							),

							anchorData.error && el(Notice, {
								status: 'warning',
								isDismissible: false
							}, anchorData.error),

							!anchorData.loading && anchorData.locations && anchorData.locations.length > 0 && el(
								Fragment,
								{},
								el('div', { className: 'aips-anchors-title' },
									__('Available Anchor Opportunities:', 'ai-post-scheduler')
								),
								anchorData.locations.map(function (loc, locIdx) {
									const cardKey = item.id + '_' + locIdx;
									const isInserted = !!insertedAnchors[cardKey];

									return el(
										'div',
										{ key: locIdx, className: 'aips-anchor-card' },
										loc.match_snippet && el('div', { className: 'aips-anchor-snippet' },
											renderSnippet(loc.match_snippet)
										),
										loc.anchor_phrase && el('div', { className: 'aips-anchor-reason' },
											el('strong', {}, t('recommendedAnchor', 'Anchor: ')),
											loc.anchor_phrase
										),
										loc.reason && el('div', { className: 'aips-anchor-reason' },
											el('em', {}, loc.reason)
										),
										el(Button, {
											isPrimary: !isInserted,
											isSecondary: isInserted,
											isSmall: true,
											disabled: isInserted,
											onClick: function () {
												insertLinkIntoBlock(item, loc, cardKey);
											}
										}, isInserted ? __('✓ Link Inserted', 'ai-post-scheduler') : t('insertLink', 'Insert Link'))
									);
								})
							)
						)
					);
				})
			),

			// Interactive Mini Link Graph Modal
			isGraphModalOpen && el(Modal, {
				title: t('linkGraphModalTitle', 'Cross-Link Topology Graph'),
				onRequestClose: function () { setIsGraphModalOpen(false); },
				className: 'aips-link-graph-modal'
			},
				isGraphLoading && el('div', { className: 'aips-loading-box' },
					el(Spinner, {}),
					el('span', {}, __('Building micro-topology graph...', 'ai-post-scheduler'))
				),
				!isGraphLoading && el('div', { className: 'aips-graph-viz-container' },
					el('p', { className: 'aips-graph-viz-intro' },
						__('Visualizing local cross-link paths between current draft and suggested posts.', 'ai-post-scheduler')
					),
					el('div', { className: 'aips-graph-nodes-list' },
						modalGraphData.nodes.map(function (n) {
							return el('div', {
								key: n.id,
								className: 'aips-graph-node-pill' + (n.is_source ? ' is-source' : '')
							}, (n.is_source ? '★ Current: ' : '📄 ') + n.title);
						})
					),
					el('div', { className: 'aips-graph-edges-list' },
						modalGraphData.links.length === 0 && el('p', {}, __('No direct internal link paths established between these posts yet.', 'ai-post-scheduler')),
						modalGraphData.links.map(function (l, lIdx) {
							return el('div', { key: lIdx, className: 'aips-graph-edge-item' },
								'Post #' + l.source + ' ➔ Post #' + l.target
							);
						})
					)
				)
			)
		);
	}

	// Register Plugin in Gutenberg
	registerPlugin('aips-semantic-link-inserter', {
		render: function () {
			return el(
				Fragment,
				{},
				el(PluginSidebarMoreMenuItem, {
					target: 'aips-semantic-link-inserter-sidebar',
					icon: 'admin-links'
				}, t('title', 'AI Link Inserter')),
				el(PluginSidebar, {
					name: 'aips-semantic-link-inserter-sidebar',
					title: t('panelTitle', 'Semantic Link & Anchor Suggestions'),
					icon: 'admin-links'
				}, el(SemanticLinkInserterSidebar, {}))
			);
		}
	});

})(window.wp);
