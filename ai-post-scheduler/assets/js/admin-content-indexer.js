/**
 * Content Indexer & Interactive Semantic Graph Visualizer
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	var ContentIndexer = {
		isIndexing: false,
		isPaused: false,
		lastPostId: 0,
		batchSize: 8,
		activePostId: null,
		graphData: null,
		simulation: null,
		zoomScale: 1.0,
		panX: 0,
		panY: 0,
		isPanning: false,
		startPanX: 0,
		startPanY: 0,
		minZoom: 0.3,
		maxZoom: 3.0,

		/**
		 * Initialize component.
		 */
		init: function () {
			this.bindEvents();
			this.initTabs();
			this.loadInitialGraph();
		},

		/**
		 * Bind DOM events.
		 */
		bindEvents: function () {
			var self = this;

			// Indexing Controls
			$('#aips-start-indexing-btn').on('click', this.handleStartIndexing.bind(this));
			$('#aips-pause-indexing-btn').on('click', this.handlePauseIndexing.bind(this));
			$('#aips-clear-index-btn').on('click', this.handleClearIndex.bind(this));

			// Graph Controls
			$('#aips-graph-sim-threshold').on('input', function () {
				var val = parseFloat($(this).val());
				$('#aips-sim-val').text(Math.round(val * 100) + '%');
			});
			$('#aips-graph-sim-threshold').on('change', function () {
				self.reloadGraph();
			});

			$('#aips-graph-max-nodes').on('input', function () {
				var val = parseInt($(this).val(), 10);
				$('#aips-nodes-val').text(val);
			});
			$('#aips-graph-max-nodes').on('change', function () {
				self.reloadGraph();
			});

			$('#aips-refresh-graph-btn').on('click', function () {
				self.reloadGraph();
			});

			// Floating Zoom Toolbar Controls
			$('#aips-zoom-in').on('click', function () {
				var svg = document.getElementById('aips-graph-svg');
				var width = svg ? (svg.clientWidth || 900) : 900;
				var height = 560;
				self.setZoom(self.zoomScale * 1.25, width / 2, height / 2);
			});

			$('#aips-zoom-out').on('click', function () {
				var svg = document.getElementById('aips-graph-svg');
				var width = svg ? (svg.clientWidth || 900) : 900;
				var height = 560;
				self.setZoom(self.zoomScale * 0.8, width / 2, height / 2);
			});

			$('#aips-zoom-reset').on('click', function () {
				self.resetZoom();
			});

			// Mousewheel Zoom & Pan on SVG Canvas
			var $svg = $('#aips-graph-svg');

			$svg.on('wheel', function (e) {
				e.preventDefault();
				var svgEl = this;
				var rect = svgEl.getBoundingClientRect();
				var mouseX = e.clientX - rect.left;
				var mouseY = e.clientY - rect.top;
				var delta = (e.originalEvent.deltaY || e.originalEvent.wheelDelta) < 0 ? 1.15 : 0.87;
				self.setZoom(self.zoomScale * delta, mouseX, mouseY);
			});

			$svg.on('mousedown', function (e) {
				if ($(e.target).closest('.graph-node, .edge-pill, .aips-zoom-btn').length) {
					return;
				}
				self.isPanning = true;
				self.startPanX = e.clientX - self.panX;
				self.startPanY = e.clientY - self.panY;
				$svg.addClass('is-dragging');
			});

			$(document).on('mousemove', function (e) {
				if (self.isPanning) {
					self.panX = e.clientX - self.startPanX;
					self.panY = e.clientY - self.startPanY;
					self.applyTransform();
				}
			});

			$(document).on('mouseup', function () {
				if (self.isPanning) {
					self.isPanning = false;
					$svg.removeClass('is-dragging');
				}
			});

			// Autocomplete search
			var searchTimer = null;
			$('#aips-graph-post-search').on('input', function () {
				var query = $(this).val();
				if (query.length > 0) {
					$('#aips-graph-search-clear').removeClass('aips-hidden').show();
				} else {
					$('#aips-graph-search-clear').addClass('aips-hidden').hide();
					$('#aips-graph-post-dropdown').addClass('aips-hidden').hide().empty();
				}

				clearTimeout(searchTimer);
				if (query.length < 2) {
					return;
				}
				searchTimer = setTimeout(function () {
					self.searchPosts(query);
				}, 250);
			});

			$('#aips-graph-search-clear').on('click', function () {
				$('#aips-graph-post-search').val('');
				$(this).addClass('aips-hidden').hide();
				$('#aips-graph-post-dropdown').addClass('aips-hidden').hide().empty();
			});

			$('#aips-active-post-clear').on('click', function () {
				$('#aips-graph-selected-post-id').val('');
				$('#aips-graph-post-search').val('');
				$('#aips-graph-search-clear').addClass('aips-hidden').hide();
				$('#aips-active-post-bar').addClass('aips-hidden').hide();
				self.loadGraphForPost(0);
			});

			$(document).on('click', '.aips-autocomplete-item', function () {
				var postId = $(this).data('id');
				var rawTitle = $(this).data('title') || $(this).find('.aips-autocomplete-title').text();
				var postType = $(this).data('type') || 'post';
				var isIndexed = $(this).data('indexed') === 1 || $(this).data('indexed') === '1' || $(this).data('indexed') === true;

				$('#aips-graph-selected-post-id').val(postId);
				$('#aips-graph-post-search').val(rawTitle);
				$('#aips-graph-search-clear').removeClass('aips-hidden').show();
				$('#aips-graph-post-dropdown').addClass('aips-hidden').hide().empty();

				// Update active post banner
				$('#aips-active-post-title').text(rawTitle);
				$('#aips-active-post-meta').text(postType.toUpperCase() + ' #' + postId + (isIndexed ? ' • Indexed' : ' • Pending Indexing'));
				$('#aips-active-post-bar').removeClass('aips-hidden').show();

				if (!isIndexed) {
					AIPS.Utilities && AIPS.Utilities.showNotice('Selected post has not been indexed yet. Backfill indexing will create its embeddings.', 'warning');
				}

				self.loadGraphForPost(postId);
			});

			$(document).on('click', function (e) {
				if (!$(e.target).closest('.aips-visualizer-search-wrap').length) {
					$('#aips-graph-post-dropdown').addClass('aips-hidden').hide();
				}
			});

			// Drawer Controls
			$('#aips-drawer-close').on('click', function () {
				$('#aips-node-drawer').addClass('aips-hidden').hide();
			});

			$('#aips-drawer-focus-btn').on('click', function () {
				var targetId = $(this).data('raw-id');
				if (targetId) {
					$('#aips-node-drawer').addClass('aips-hidden').hide();
					self.loadGraphForPost(targetId);
				}
			});

			// Cannibalization Audit
			$('#aips-run-audit-btn').on('click', this.runCannibalizationAudit.bind(this));

			// Dimension Mismatch Re-Index Button
			$('#aips-reindex-dimension-btn').on('click', function () {
				if (!confirm('This will clear stored vector embeddings and re-index all content using the active environment and model. Proceed?')) {
					return;
				}
				self.handleClearIndex();
				$('.aips-tab-link[data-tab="scanner"]').trigger('click');
				setTimeout(function () {
					self.handleStartIndexing();
				}, 600);
			});

			// Meow Environment Discovery
			$('#aips-fetch-meow-envs-btn').on('click', this.fetchMeowEnvironments.bind(this));
			$('#aips-meow-envs-select').on('change', function () {
				var selected = $(this).find(':selected');
				if (!selected.val()) {
					return;
				}
				$('#aips_embeddings_env_id').val(selected.val());
				if (selected.data('model')) {
					$('#aips_embeddings_model').val(selected.data('model'));
				}
				if (selected.data('dimensions')) {
					$('#aips_embeddings_dimensions').val(selected.data('dimensions'));
				}
			});

			// Settings Form Save
			$('#aips-indexer-settings-form').on('submit', this.handleSaveSettings.bind(this));
		},

		/**
		 * Apply SVG Viewport Transform (Pan & Zoom).
		 */
		applyTransform: function () {
			var viewport = document.getElementById('aips-graph-viewport');
			if (viewport) {
				viewport.setAttribute('transform', 'translate(' + this.panX + ',' + this.panY + ') scale(' + this.zoomScale + ')');
			}
			$('#aips-zoom-level').text(Math.round(this.zoomScale * 100) + '%');
		},

		/**
		 * Set zoom scale centered on a pivot coordinate.
		 */
		setZoom: function (newScale, pivotX, pivotY) {
			newScale = Math.max(this.minZoom, Math.min(this.maxZoom, newScale));
			if (pivotX !== undefined && pivotY !== undefined) {
				var ratio = newScale / this.zoomScale;
				this.panX = pivotX - (pivotX - this.panX) * ratio;
				this.panY = pivotY - (pivotY - this.panY) * ratio;
			}
			this.zoomScale = newScale;
			this.applyTransform();
		},

		/**
		 * Reset pan & zoom to default centered view.
		 */
		resetZoom: function () {
			this.zoomScale = 1.0;
			this.panX = 0;
			this.panY = 0;
			this.applyTransform();
		},

		/**
		 * Tab switching.
		 */
		initTabs: function () {
			$('.aips-tab-link').on('click', function (e) {
				e.preventDefault();
				var tab = $(this).data('tab');

				$('.aips-tab-link').removeClass('active');
				$('.aips-tab-content').removeClass('active');

				$(this).addClass('active');
				$('#' + tab + '-tab').addClass('active');

				if (tab === 'visualizer' && window.AIPS.ContentIndexer.graphData) {
					window.AIPS.ContentIndexer.renderSvgGraph(window.AIPS.ContentIndexer.graphData);
				}
			});
		},

		/**
		 * Start / Resume backfill indexing.
		 */
		handleStartIndexing: function () {
			if (this.isIndexing && !this.isPaused) {
				return;
			}

			this.isIndexing = true;
			this.isPaused = false;

			$('#aips-start-indexing-btn').hide();
			$('#aips-pause-indexing-btn').show();
			$('#aips-indexer-live-banner').slideDown(200);

			this.processNextBatch();
		},

		/**
		 * Pause indexing.
		 */
		handlePauseIndexing: function () {
			this.isPaused = true;
			this.isIndexing = false;

			$('#aips-pause-indexing-btn').hide();
			$('#aips-start-indexing-btn').show().find('.btn-text').text(aipsContentIndexerL10n.resumeScan || 'Resume Scan');
			$('#aips-indexer-banner-title').text(aipsContentIndexerL10n.indexingPaused || 'Indexing Paused');
		},

		/**
		 * Process batch slice via AJAX.
		 */
		processNextBatch: function () {
			var self = this;

			if (!this.isIndexing || this.isPaused) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_process_batch',
					nonce: aipsContentIndexerL10n.nonce,
					batch_size: self.batchSize,
					last_post_id: self.lastPostId
				},
				success: function (res) {
					if (!res.success) {
						AIPS.Utilities && AIPS.Utilities.showNotice(res.data.message || 'Indexing error.', 'error');
						self.handlePauseIndexing();
						return;
					}

					var data = res.data;
					self.lastPostId = data.last_post_id;

					// Update DOM metrics
					$('#aips-stat-indexed').text(data.total_indexed);
					$('#aips-stat-total').text(data.total_posts);
					$('#aips-stat-percent').text(data.percent + '%');
					$('#aips-index-progress-bar').css('width', data.percent + '%');
					$('#aips-stat-unindexed').text(Math.max(0, data.total_posts - data.total_indexed));
					$('#aips-indexer-slice-count').text(data.total_indexed + ' / ' + data.total_posts);

					// Update live rate limit meters if returned
					if (data.rate_limits) {
						var rl = data.rate_limits;
						var dCnt = rl.daily_count || 0, dLim = rl.daily_limit || 0;
						var wCnt = rl.weekly_count || 0, wLim = rl.weekly_limit || 0;
						var mCnt = rl.monthly_count || 0, mLim = rl.monthly_limit || 0;
						$('#aips-meter-daily-count').html('<strong>' + dCnt + '</strong> / ' + (dLim > 0 ? dLim : '∞'));
						$('#aips-meter-weekly-count').html('<strong>' + wCnt + '</strong> / ' + (wLim > 0 ? wLim : '∞'));
						$('#aips-meter-monthly-count').html('<strong>' + mCnt + '</strong> / ' + (mLim > 0 ? mLim : '∞'));
						if (dLim > 0) $('#aips-meter-daily-bar').css('width', Math.min(100, Math.round((dCnt / dLim) * 100)) + '%');
						if (wLim > 0) $('#aips-meter-weekly-bar').css('width', Math.min(100, Math.round((wCnt / wLim) * 100)) + '%');
						if (mLim > 0) $('#aips-meter-monthly-bar').css('width', Math.min(100, Math.round((mCnt / mLim) * 100)) + '%');
					}

					if (data.rate_limit_exceeded) {
						self.isIndexing = false;
						self.isPaused = false;
						$('#aips-pause-indexing-btn').hide();
						$('#aips-start-indexing-btn').show().find('.btn-text').text(aipsContentIndexerL10n.startScan || 'Start Backfill Scan');
						$('#aips-indexer-live-banner').slideUp(200);

						var errorMsg = (data.rate_limit_error && data.rate_limit_error.message) ? data.rate_limit_error.message : 'Embedding rate limit reached. Indexing paused.';
						$('#aips-rate-limit-warning-msg').text(errorMsg);
						$('#aips-rate-limit-warning-banner').removeClass('aips-hidden').slideDown(200);
						AIPS.Utilities && AIPS.Utilities.showNotice(errorMsg, 'warning');
						return;
					}

					if (data.done) {
						self.isIndexing = false;
						$('#aips-pause-indexing-btn').hide();
						$('#aips-start-indexing-btn').show().find('.btn-text').text(aipsContentIndexerL10n.startScan || 'Start Backfill Scan');
						$('#aips-indexer-live-banner').slideUp(200);
						AIPS.Utilities && AIPS.Utilities.showNotice(aipsContentIndexerL10n.indexingComplete || 'Content indexing complete!', 'success');
					} else if (self.isIndexing && !self.isPaused) {
						// Continue next slice immediately
						setTimeout(function () {
							self.processNextBatch();
						}, 300);
					}
				},
				error: function () {
					self.handlePauseIndexing();
					AIPS.Utilities && AIPS.Utilities.showNotice('Network error during indexing batch. Paused.', 'error');
				}
			});
		},

		/**
		 * Clear entire index.
		 */
		handleClearIndex: function () {
			if (!confirm(aipsContentIndexerL10n.confirmClear || 'Are you sure you want to clear all semantic embeddings and relationships? This will reset indexing coverage.')) {
				return;
			}

			var self = this;
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_clear_index',
					nonce: aipsContentIndexerL10n.nonce
				},
				success: function (res) {
					if (res.success) {
						self.lastPostId = 0;
						$('#aips-stat-indexed').text('0');
						$('#aips-stat-percent').text('0%');
						$('#aips-index-progress-bar').css('width', '0%');
						$('#aips-stat-topics').text('0');
						AIPS.Utilities && AIPS.Utilities.showNotice(res.data.message || 'Index cleared.', 'success');
						self.reloadGraph();
					}
				}
			});
		},

		/**
		 * Search posts for autocomplete with indexed status badges.
		 */
		searchPosts: function (q) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_search_posts',
					nonce: aipsContentIndexerL10n.nonce,
					q: q
				},
				success: function (res) {
					if (res.success && res.data.results) {
						var items = res.data.results;
						var $dropdown = $('#aips-graph-post-dropdown').empty();

						if (!items.length) {
							$dropdown.addClass('aips-hidden').hide();
							return;
						}

						items.forEach(function (it) {
							var isIndexed = !!it.is_indexed;
							var badgeText = isIndexed
								? (aipsContentIndexerL10n.indexed || 'Indexed')
								: (aipsContentIndexerL10n.pendingIndex || 'Pending Index');
							var badgeClass = isIndexed ? 'aips-badge-indexed' : 'aips-badge-unindexed';
							var rawTitle = it.title || '';
							var pType = it.post_type || 'post';

							var $item = $('<div>')
								.addClass('aips-autocomplete-item')
								.data('id', it.id)
								.data('title', rawTitle)
								.data('type', pType)
								.data('indexed', isIndexed ? 1 : 0);

							var $titleSpan = $('<span>')
								.addClass('aips-autocomplete-title')
								.text(rawTitle)
								.append($('<small>').css('color', '#64748b').text(' (' + pType + ' #' + it.id + ')'));

							var $badgeSpan = $('<span>')
								.addClass(badgeClass)
								.text(badgeText);

							$item.append($titleSpan).append($badgeSpan);
							$dropdown.append($item);
						});

						$dropdown.removeClass('aips-hidden').show();
					}
				}
			});
		},

		/**
		 * Load initial graph for first indexed post.
		 */
		loadInitialGraph: function () {
			this.loadGraphForPost(0);
		},

		/**
		 * Reload currently active graph with updated thresholds.
		 */
		reloadGraph: function () {
			this.loadGraphForPost(this.activePostId || 0, true);
		},

		/**
		 * Load graph data for a specific post.
		 */
		loadGraphForPost: function (postId, preserveView) {
			var self = this;
			var simThreshold = parseFloat($('#aips-graph-sim-threshold').val()) || 0.60;
			var maxNodes = parseInt($('#aips-graph-max-nodes').val(), 10) || 15;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_get_graph',
					nonce: aipsContentIndexerL10n.nonce,
					post_id: postId,
					min_similarity: simThreshold,
					limit: maxNodes
				},
				success: function (res) {
					if (res.success && res.data.graph) {
						self.activePostId = res.data.post_id;
						self.graphData = res.data.graph;
						if (!preserveView) {
							self.resetZoom();
						}
						self.renderSvgGraph(res.data.graph);
					} else {
						$('#aips-graph-svg').empty();
						$('#aips-graph-empty').removeClass('aips-hidden').show();
						$('#aips-active-post-bar').addClass('aips-hidden').hide();
					}
				}
			});
		},

		/**
		 * Render the Interactive SVG Node-Link Graph with Viewport Zoom/Pan, Edge Pills, Tooltips, and Highlighting.
		 */
		renderSvgGraph: function (graph) {
			var self = this;
			var svg = document.getElementById('aips-graph-svg');
			if (!svg) {
				return;
			}

			var width = svg.clientWidth || 900;
			var height = 560;
			svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
			$(svg).empty();

			if (!graph.nodes || graph.nodes.length === 0) {
				$('#aips-graph-empty').removeClass('aips-hidden').show();
				return;
			}
			$('#aips-graph-empty').addClass('aips-hidden').hide();

			var centerX = width / 2;
			var centerY = height / 2;
			var nodes = graph.nodes;
			var edges = graph.edges;

			// Position nodes in a radial constellation
			var centerNode = nodes.find(function (n) { return n.is_center; }) || nodes[0];
			centerNode.x = centerX;
			centerNode.y = centerY;

			// Update Active Post Bar and sync Search Input
			if (centerNode) {
				$('#aips-active-post-title').text(centerNode.label);
				$('#aips-active-post-meta').text((centerNode.type || 'post').toUpperCase() + ' #' + (centerNode.raw_id || centerNode.id));
				$('#aips-active-post-bar').removeClass('aips-hidden').show();

				// Keep search input and selection in sync with the active center post
				$('#aips-graph-selected-post-id').val(centerNode.raw_id || centerNode.id);
				$('#aips-graph-post-search').val(centerNode.label);
				$('#aips-graph-search-clear').removeClass('aips-hidden').show();
			}

			var neighbors = nodes.filter(function (n) { return !n.is_center; });
			var angleStep = (2 * Math.PI) / Math.max(1, neighbors.length);
			var radius = Math.min(centerX, centerY) * 0.68;

			neighbors.forEach(function (node, i) {
				var angle = i * angleStep;
				// Distance inversely proportional to similarity (closer = more similar)
				var dist = radius * (1.1 - (node.similarity || 0.6) * 0.35);
				node.x = centerX + dist * Math.cos(angle);
				node.y = centerY + dist * Math.sin(angle);
			});

			var nodeMap = {};
			nodes.forEach(function (n) { nodeMap[n.id] = n; });

			// Create Viewport container for pan & zoom transforms
			var gViewport = document.createElementNS('http://www.w3.org/2000/svg', 'g');
			gViewport.setAttribute('id', 'aips-graph-viewport');

			// 1. Draw Edges & Edge Pills
			var gEdges = document.createElementNS('http://www.w3.org/2000/svg', 'g');
			gEdges.setAttribute('class', 'edges-group');

			edges.forEach(function (edge) {
				var src = nodeMap[edge.source];
				var tgt = nodeMap[edge.target];
				if (!src || !tgt) return;

				var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
				line.setAttribute('x1', src.x);
				line.setAttribute('y1', src.y);
				line.setAttribute('x2', tgt.x);
				line.setAttribute('y2', tgt.y);
				line.setAttribute('data-source', edge.source);
				line.setAttribute('data-target', edge.target);

				var weight = edge.weight || 0.6;
				var edgeClass = 'graph-edge';
				if (weight >= 0.80) edgeClass += ' edge-high';
				else if (weight >= 0.65) edgeClass += ' edge-med';
				else edgeClass += ' edge-low';

				line.setAttribute('class', edgeClass);
				line.setAttribute('stroke-width', Math.max(1.5, weight * 3.5));
				gEdges.appendChild(line);

				// Edge Label Pill (Contrast Background + Text)
				var midX = (src.x + tgt.x) / 2;
				var midY = (src.y + tgt.y) / 2;
				var pillG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				pillG.setAttribute('class', 'edge-pill');
				pillG.setAttribute('data-source', edge.source);
				pillG.setAttribute('data-target', edge.target);

				var pillBg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
				pillBg.setAttribute('class', 'edge-pill-bg');
				pillBg.setAttribute('x', midX - 20);
				pillBg.setAttribute('y', midY - 10);
				pillBg.setAttribute('width', 40);
				pillBg.setAttribute('height', 20);
				pillBg.setAttribute('rx', 4);
				pillBg.setAttribute('ry', 4);
				pillG.appendChild(pillBg);

				var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
				text.setAttribute('x', midX);
				text.setAttribute('y', midY);
				text.setAttribute('class', 'edge-pill-text');
				text.textContent = edge.label;
				pillG.appendChild(text);

				gEdges.appendChild(pillG);
			});
			gViewport.appendChild(gEdges);

			// Helper for smart tooltip boundary positioning
			function updateTooltipPosition(e) {
				var $container = $('.aips-graph-viewport-container');
				var containerOffset = $container.offset();
				if (!containerOffset) return;

				var containerWidth = $container.width() || 900;
				var rawX = e.pageX - containerOffset.left;
				var rawY = e.pageY - containerOffset.top;

				// Clamp X within container bounds
				var clampedX = Math.max(140, Math.min(containerWidth - 140, rawX));

				// If hovering near top edge, flip below cursor
				var isTopClipped = rawY < 130;
				var translateY = isTopClipped ? '20px' : '-120%';

				$('#aips-graph-tooltip').css({
					left: clampedX + 'px',
					top: rawY + 'px',
					transform: 'translate(-50%, ' + translateY + ')'
				});
			}

			// 2. Draw Nodes
			var gNodes = document.createElementNS('http://www.w3.org/2000/svg', 'g');
			gNodes.setAttribute('class', 'nodes-group');

			nodes.forEach(function (node) {
				var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				var nodeClass = 'graph-node';
				if (node.is_center) nodeClass += ' node-center';
				else {
					nodeClass += ' node-neighbor';
					if (node.similarity >= 0.80) nodeClass += ' node-high';
					else if (node.similarity < 0.65) nodeClass += ' node-low';
				}
				g.setAttribute('class', nodeClass);
				g.setAttribute('data-id', node.id);
				g.setAttribute('transform', 'translate(' + node.x + ',' + node.y + ')');

				var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
				circle.setAttribute('r', node.is_center ? 24 : 16);
				g.appendChild(circle);

				var label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
				label.setAttribute('y', node.is_center ? 38 : 28);
				label.setAttribute('text-anchor', 'middle');
				var truncated = node.label.length > 22 ? node.label.substring(0, 20) + '…' : node.label;
				label.textContent = truncated;
				g.appendChild(label);

				// Hover Connection Highlighting & Dimming + Tooltip
				$(g).on('mouseenter', function (e) {
					var nodeId = node.id;
					
					// Find connected node IDs
					var connectedNodeIds = {};
					connectedNodeIds[nodeId] = true;
					edges.forEach(function (ed) {
						if (ed.source === nodeId) connectedNodeIds[ed.target] = true;
						if (ed.target === nodeId) connectedNodeIds[ed.source] = true;
					});

					// Dim unrelated nodes and edges
					$('.graph-node').each(function () {
						var nid = $(this).attr('data-id');
						if (connectedNodeIds[nid]) {
							$(this).addClass('is-highlighted').removeClass('is-dimmed');
						} else {
							$(this).addClass('is-dimmed').removeClass('is-highlighted');
						}
					});

					$('.graph-edge, .edge-pill').each(function () {
						var src = $(this).attr('data-source');
						var tgt = $(this).attr('data-target');
						if (src === nodeId || tgt === nodeId) {
							$(this).addClass('is-highlighted').removeClass('is-dimmed');
						} else {
							$(this).addClass('is-dimmed').removeClass('is-highlighted');
						}
					});

					// Show Rich Tooltip
					var $tooltip = $('#aips-graph-tooltip');
					var badgeClass = 'badge-med';
					var badgeText = 'Semantic Match';

					if (node.is_center) {
						badgeClass = 'badge-center';
						badgeText = 'Inspected Post (Center)';
					} else if (node.similarity !== undefined) {
						var simPercent = Math.round(node.similarity * 100);
						if (simPercent >= 80) {
							badgeClass = 'badge-high';
							badgeText = simPercent + '% Strong Match';
						} else if (simPercent < 65) {
							badgeClass = 'badge-low';
							badgeText = simPercent + '% Related Topic';
						} else {
							badgeText = simPercent + '% Semantic Match';
						}
					}

					$('#aips-tooltip-badge').attr('class', 'aips-tooltip-badge ' + badgeClass).text(badgeText);
					$('#aips-tooltip-title').text(node.label);
					$('#aips-tooltip-meta').text('Type: ' + (node.type || 'post') + ' • ID: #' + (node.raw_id || node.id));

					updateTooltipPosition(e);
					$tooltip.removeClass('aips-hidden').show();
				});

				$(g).on('mousemove', function (e) {
					updateTooltipPosition(e);
				});

				$(g).on('mouseleave', function () {
					$('.graph-node, .graph-edge, .edge-pill').removeClass('is-dimmed is-highlighted');
					$('#aips-graph-tooltip').addClass('aips-hidden').hide();
				});

				// Click handler for node flyout
				$(g).on('click', function () {
					self.openNodeDrawer(node);
				});

				gNodes.appendChild(g);
			});
			gViewport.appendChild(gNodes);
			svg.appendChild(gViewport);

			// Re-apply active pan & zoom
			self.applyTransform();
		},

		/**
		 * Open node detail drawer flyout.
		 */
		openNodeDrawer: function (node) {
			$('#aips-drawer-title').text(node.label);
			$('#aips-drawer-type').text(node.type || 'post');
			$('#aips-drawer-id').text(node.raw_id);

			if (node.similarity !== undefined) {
				$('#aips-drawer-sim-text').text(Math.round(node.similarity * 100) + '% Similarity');
				$('#aips-drawer-sim-badge').show();
			} else {
				$('#aips-drawer-sim-badge').hide();
			}

			if (node.url) {
				$('#aips-drawer-edit-link').attr('href', node.url).show();
			} else {
				$('#aips-drawer-edit-link').hide();
			}

			if (node.view_url) {
				$('#aips-drawer-view-link').attr('href', node.view_url).show();
			} else {
				$('#aips-drawer-view-link').hide();
			}

			$('#aips-drawer-focus-btn').data('raw-id', node.raw_id);
			$('#aips-node-drawer').removeClass('aips-hidden').show();
		},

		/**
		 * Run Cannibalization & Duplicate Audit scan.
		 */
		runCannibalizationAudit: function () {
			var $btn = $('#aips-run-audit-btn');
			var $tbody = $('#aips-cannibalization-tbody');
			var $loading = $('#aips-audit-loading');

			$btn.prop('disabled', true);
			$tbody.empty();
			$loading.show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_run_cannibalization_audit',
					nonce: aipsContentIndexerL10n.nonce,
					threshold: 0.75,
					limit: 50
				},
				success: function (res) {
					$btn.prop('disabled', false);
					$loading.hide();

					if (!res.success || !res.data.clusters || res.data.clusters.length === 0) {
						$tbody.html('<tr><td colspan="5" style="text-align:center;padding:32px;color:#00a32a;"><strong>No high-similarity duplicate or cannibalizing clusters found. Great job!</strong></td></tr>');
						return;
					}

					var clusters = res.data.clusters;
					var html = '';

					clusters.forEach(function (c) {
						var riskClass = 'aips-risk-low';
						var riskLabel = 'Low Risk';
						if (c.similarity >= 0.88) {
							riskClass = 'aips-risk-high';
							riskLabel = 'High Cannibalization';
						} else if (c.similarity >= 0.80) {
							riskClass = 'aips-risk-medium';
							riskLabel = 'Moderate';
						}

						html += '<tr>';
						html += '<td><strong>' + $('<div>').text(c.source_title).html() + '</strong><br><small style="color:#888;">' + c.source_post_type + ' #' + c.source_id + ' (' + c.source_date + ')</small></td>';
						html += '<td><strong>' + $('<div>').text(c.target_title).html() + '</strong><br><small style="color:#888;">' + c.target_post_type + ' #' + c.target_id + ' (' + c.target_date + ')</small></td>';
						html += '<td><strong style="color:#2271b1;font-size:15px;">' + c.similarity_pct + '%</strong></td>';
						html += '<td><span class="aips-risk-badge ' + riskClass + '">' + riskLabel + '</span></td>';
						html += '<td>';
						if (c.source_edit_url) {
							html += '<a href="' + c.source_edit_url + '" class="button button-small" target="_blank" style="margin-right:4px;">Edit Post A</a>';
						}
						if (c.target_edit_url) {
							html += '<a href="' + c.target_edit_url + '" class="button button-small" target="_blank">Edit Post B</a>';
						}
						html += '</td>';
						html += '</tr>';
					});

					$tbody.html(html);
				},
				error: function () {
					$btn.prop('disabled', false);
					$loading.hide();
					AIPS.Utilities && AIPS.Utilities.showNotice('Error running cannibalization audit.', 'error');
				}
			});
		},

		/**
		 * Discover and populate configured Meow AI Engine embedding environments.
		 */
		fetchMeowEnvironments: function () {
			var $btn = $('#aips-fetch-meow-envs-btn');
			var originalHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> Discovering…');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_fetch_meow_environments',
					nonce: aipsContentIndexerL10n.nonce
				},
				success: function (res) {
					$btn.prop('disabled', false).html(originalHtml);
					if (res.success && res.data.environments) {
						var envs = res.data.environments;
						var $select = $('#aips-meow-envs-select');
						$select.empty().append('<option value="">— Select a discovered environment —</option>');

						if (envs.length === 0) {
							AIPS.Utilities && AIPS.Utilities.showNotice('No custom embedding environments found in Meow Apps AI Engine. Default OpenAI configuration will be used.', 'info');
							return;
						}

						envs.forEach(function (env) {
							var label = env.name + ' (' + (env.serverType || 'default') + ' / ' + env.model + ' - ' + env.dimensions + 'd)';
							var $opt = $('<option>')
								.val(env.id)
								.text(label)
								.attr('data-model', env.model)
								.attr('data-dimensions', env.dimensions);
							$select.append($opt);
						});

						$('#aips-meow-envs-dropdown-container').slideDown(150);
						AIPS.Utilities && AIPS.Utilities.showNotice('Discovered ' + envs.length + ' environment(s) from Meow Apps AI Engine.', 'success');
					} else {
						var msg = (res.data && res.data.message) ? res.data.message : 'Could not retrieve environments from Meow Apps AI Engine.';
						AIPS.Utilities && AIPS.Utilities.showNotice(msg, 'warning');
					}
				},
				error: function () {
					$btn.prop('disabled', false).html(originalHtml);
					AIPS.Utilities && AIPS.Utilities.showNotice('Failed to connect to Meow Apps AI Engine.', 'error');
				}
			});
		},

		/**
		 * Save settings form via AJAX.
		 */
		handleSaveSettings: function (e) {
			e.preventDefault();
			var $form = $(e.target);
			var formData = $form.serializeArray();
			var payload = {
				action: 'aips_indexer_save_settings',
				nonce: aipsContentIndexerL10n.nonce,
				post_types: [],
				embeddings_enabled: $form.find('input[name="embeddings_enabled"]').is(':checked') ? 1 : 0,
				auto_index_on_publish: $form.find('input[name="auto_index_on_publish"]').is(':checked') ? 1 : 0,
				related_posts_enabled: $form.find('input[name="related_posts_enabled"]').is(':checked') ? 1 : 0,
				related_posts_auto_append: $form.find('input[name="related_posts_auto_append"]').is(':checked') ? 1 : 0
			};

			formData.forEach(function (item) {
				if (item.name === 'post_types[]') {
					payload.post_types.push(item.value);
				} else if (
					item.name !== 'embeddings_enabled' &&
					item.name !== 'auto_index_on_publish' &&
					item.name !== 'related_posts_enabled' &&
					item.name !== 'related_posts_auto_append'
				) {
					payload[item.name] = item.value;
				}
			});

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: payload,
				success: function (res) {
					if (res.success) {
						AIPS.Utilities && AIPS.Utilities.showNotice(res.data.message || 'Settings saved successfully.', 'success');
					}
				}
			});
		}
	};

	$(document).ready(function () {
		if ($('.aips-indexer-page').length) {
			ContentIndexer.init();
			window.AIPS.ContentIndexer = ContentIndexer;
		}
	});

})(jQuery);
