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

			// Autocomplete search
			var searchTimer = null;
			$('#aips-graph-post-search').on('input', function () {
				var query = $(this).val();
				clearTimeout(searchTimer);
				if (query.length < 2) {
					$('#aips-graph-post-dropdown').hide().empty();
					return;
				}
				searchTimer = setTimeout(function () {
					self.searchPosts(query);
				}, 250);
			});

			$(document).on('click', '.aips-autocomplete-item', function () {
				var postId = $(this).data('id');
				var title = $(this).text();
				$('#aips-graph-selected-post-id').val(postId);
				$('#aips-graph-post-search').val(title);
				$('#aips-graph-post-dropdown').hide().empty();
				self.loadGraphForPost(postId);
			});

			$(document).on('click', function (e) {
				if (!$(e.target).closest('.aips-visualizer-search-wrap').length) {
					$('#aips-graph-post-dropdown').hide();
				}
			});

			// Drawer Controls
			$('#aips-drawer-close').on('click', function () {
				$('#aips-node-drawer').hide();
			});

			$('#aips-drawer-focus-btn').on('click', function () {
				var targetId = $(this).data('raw-id');
				if (targetId) {
					$('#aips-node-drawer').hide();
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
		 * Tab switching.
		 */
		initTabs: function () {
			var self = this;

			// Check for direct URL hash on load
			var initialHash = window.location.hash ? window.location.hash.replace('#', '') : '';
			if (initialHash && $('#' + initialHash + '-tab').length) {
				self.switchTab(initialHash);
			}

			$('.aips-tab-link').on('click', function (e) {
				e.preventDefault();
				var tab = $(this).data('tab');
				if (tab) {
					self.switchTab(tab);
					if (history.replaceState) {
						history.replaceState(null, null, '#' + tab);
					} else {
						window.location.hash = '#' + tab;
					}
				}
			});
		},

		/**
		 * Switch to a specific tab by key.
		 *
		 * @param {string} tab Tab identifier.
		 */
		switchTab: function (tab) {
			if (!tab || !$('#' + tab + '-tab').length) {
				return;
			}

			$('.aips-tab-link').removeClass('active');
			$('.aips-tab-link[data-tab="' + tab + '"]').addClass('active');

			$('.aips-indexer-page .aips-tab-content').removeClass('active').hide().attr('aria-hidden', 'true');
			$('#' + tab + '-tab').addClass('active').show().attr('aria-hidden', 'false');

			if (tab === 'visualizer' && this.graphData) {
				this.renderSvgGraph(this.graphData);
			}
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
		 * Search posts for autocomplete.
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
						var html = '';
						items.forEach(function (it) {
							html += '<div class="aips-autocomplete-item" data-id="' + it.id + '">' + $('<div>').text(it.title).html() + '</div>';
						});
						if (items.length) {
							$('#aips-graph-post-dropdown').html(html).show();
						} else {
							$('#aips-graph-post-dropdown').hide().empty();
						}
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
			this.loadGraphForPost(this.activePostId || 0);
		},

		/**
		 * Load graph data for a specific post.
		 */
		loadGraphForPost: function (postId) {
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
						self.renderSvgGraph(res.data.graph);
					} else {
						$('#aips-graph-svg').empty();
						$('#aips-graph-empty').show();
					}
				}
			});
		},

		/**
		 * Render the Interactive SVG Node-Link Graph.
		 */
		renderSvgGraph: function (graph) {
			var svg = document.getElementById('aips-graph-svg');
			if (!svg) {
				return;
			}

			var width = svg.clientWidth || 900;
			var height = 560;
			svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
			$(svg).empty();

			if (!graph.nodes || graph.nodes.length === 0) {
				$('#aips-graph-empty').show();
				return;
			}
			$('#aips-graph-empty').hide();

			var centerX = width / 2;
			var centerY = height / 2;
			var nodes = graph.nodes;
			var edges = graph.edges;

			// Position nodes in a radial constellation
			var centerNode = nodes.find(function (n) { return n.is_center; }) || nodes[0];
			centerNode.x = centerX;
			centerNode.y = centerY;

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

			// 1. Draw Edges
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

				var weight = edge.weight || 0.6;
				var edgeClass = 'graph-edge';
				if (weight >= 0.80) edgeClass += ' edge-high';
				else if (weight >= 0.65) edgeClass += ' edge-med';
				else edgeClass += ' edge-low';

				line.setAttribute('class', edgeClass);
				line.setAttribute('stroke-width', Math.max(1.5, weight * 3.5));
				gEdges.appendChild(line);

				// Edge weight label
				var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
				text.setAttribute('x', (src.x + tgt.x) / 2);
				text.setAttribute('y', (src.y + tgt.y) / 2 - 4);
				text.setAttribute('class', 'graph-edge-text');
				text.textContent = edge.label;
				gEdges.appendChild(text);
			});
			svg.appendChild(gEdges);

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

				// Click handler for node flyout
				$(g).on('click', function () {
					ContentIndexer.openNodeDrawer(node);
				});

				gNodes.appendChild(g);
			});
			svg.appendChild(gNodes);
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

			// Internal Link Graph Metrics
			if (node.inbound_count !== undefined) {
				$('#aips-drawer-inbound-cnt').text(node.inbound_count);
				$('#aips-drawer-outbound-cnt').text(node.outbound_count || 0);

				if (node.is_orphan) {
					$('#aips-drawer-orphan-pill').show();
					$('#aips-drawer-hub-pill').hide();
				} else if (node.inbound_count >= 5) {
					$('#aips-drawer-hub-pill').show();
					$('#aips-drawer-orphan-pill').hide();
				} else {
					$('#aips-drawer-orphan-pill').hide();
					$('#aips-drawer-hub-pill').hide();
				}

				$('#aips-drawer-link-metrics').show();
			} else {
				$('#aips-drawer-link-metrics').hide();
			}

			$('#aips-drawer-focus-btn').data('raw-id', node.raw_id);
			$('#aips-node-drawer').show();
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
			var formData = $(e.target).serializeArray();
			var payload = {
				action: 'aips_indexer_save_settings',
				nonce: aipsContentIndexerL10n.nonce,
				post_types: []
			};

			formData.forEach(function (item) {
				if (item.name === 'post_types[]') {
					payload.post_types.push(item.value);
				} else {
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
