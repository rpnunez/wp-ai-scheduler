/**
 * Content Indexer & Interactive Semantic Graph Visualizer
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

(function ($) {
	'use strict';

	// Ensure the global AIPS namespace is initialized
	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * @namespace AIPS.ContentIndexer
	 */
	AIPS.ContentIndexer = {

		/**
		 * Flag indicating whether a batch backfill indexing scan is actively running.
		 *
		 * @type {boolean}
		 */
		isIndexing: false,

		/**
		 * Flag indicating whether the indexing process is temporarily paused.
		 *
		 * @type {boolean}
		 */
		isPaused: false,

		/**
		 * ID of the last processed post in progressive cursor pagination.
		 *
		 * @type {number}
		 */
		lastPostId: 0,

		/**
		 * Number of posts to process in each sequential AJAX batch slice.
		 *
		 * @type {number}
		 */
		batchSize: 8,

		/**
		 * ID of the post currently centered and inspected in the semantic graph.
		 *
		 * @type {number|null}
		 */
		activePostId: null,

		/**
		 * Current graph payload containing nodes and relationship edges.
		 *
		 * @type {Object|null}
		 */
		graphData: null,

		/**
		 * Force simulation reference if using dynamic layout.
		 *
		 * @type {Object|null}
		 */
		simulation: null,

		/**
		 * Stack of previous node exploration history for drill-down and back navigation.
		 *
		 * @type {Array<Object>}
		 */
		explorationHistory: [],

		/**
		 * Cooldown countdown timer interval handle.
		 *
		 * @type {number|null}
		 */
		cooldownTimer: null,

		/**
		 * Cached payload of post clusters and orphan posts.
		 *
		 * @type {Object|null}
		 */
		clustersData: null,

		/**
		 * Active cluster ID selected for gap idea generation.
		 *
		 * @type {string|null}
		 */
		selectedClusterForGaps: null,

		/**
		 * Current zoom magnification scale for the SVG canvas (1.0 = 100%).
		 *
		 * @type {number}
		 */
		zoomScale: 1.0,

		/**
		 * Horizontal viewport translation offset in pixels.
		 *
		 * @type {number}
		 */
		panX: 0,

		/**
		 * Vertical viewport translation offset in pixels.
		 *
		 * @type {number}
		 */
		panY: 0,

		/**
		 * Flag indicating if canvas drag-panning is actively in progress.
		 *
		 * @type {boolean}
		 */
		isPanning: false,

		/**
		 * Starting client X coordinate when drag-panning begins.
		 *
		 * @type {number}
		 */
		startPanX: 0,

		/**
		 * Starting client Y coordinate when drag-panning begins.
		 *
		 * @type {number}
		 */
		startPanY: 0,

		/**
		 * Minimum allowable zoom scale (30%).
		 *
		 * @type {number}
		 */
		minZoom: 0.3,

		/**
		 * Maximum allowable zoom scale (500%).
		 *
		 * @type {number}
		 */
		maxZoom: 5.0,

		/**
		 * Debounce timer handle for autocomplete search input.
		 *
		 * @type {number|null}
		 */
		_searchTimer: null,

		/**
		 * Bootstrap the Content Indexer admin component.
		 *
		 * Sets up initial tab visibility, binds all UI event listeners,
		 * and loads the initial center post graph.
		 *
		 * @return {void}
		 */
		init: function () {
			// Ensure only the active tab panel is displayed on load
			if ($('.aips-tab-content').length) {
				$('.aips-tab-content:not(.active)').hide();
				$('.aips-tab-content.active').show();
			}

			// Bind all delegated and static DOM events
			this.bindEvents();

			// Initialize Cooldown timer if active
			this.initCooldownTimer();

			// Load the default graph for the first indexed post
			if ($('#aips-graph-svg').length) {
				this.loadInitialGraph();
			}

			// Automatically scan and build clusters when on dedicated clusters page
			if ($('#aips-clusters-accordion').length && !this.clustersData) {
				this.onRefreshClustersClick();
			}
		},

		/**
		 * Bind all UI event listeners to their respective handlers.
		 *
		 * Uses named handler methods instead of anonymous inline callbacks
		 * to maintain separation of concerns, testability, and clarity.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			var self = this;

			// Tab navigation
			$(document).on('click', '.aips-tab-link', this.onTabClick.bind(this));

			// Listen for tab switch events from main admin rail
			$(document).on('aips:tabSwitch', function (e, targetTab) {
				if (targetTab === 'aips-content-indexer' || targetTab === 'visualizer') {
					if (self.graphData) {
						self.renderSvgGraph(self.graphData);
					} else {
						self.loadInitialGraph();
					}
				} else if (targetTab === 'aips-content-clusters' || targetTab === 'clusters') {
					if (!self.clustersData) {
						self.onRefreshClustersClick();
					}
				}
			});

			// Exploration history navigation
			$('#aips-history-back-btn').on('click', this.onHistoryBackClick.bind(this));

			// Drawer details inspection
			$('#aips-drawer-open-btn').on('click', this.onDrawerOpenClick.bind(this));
			$('#aips-drawer-close').on('click', this.onDrawerCloseClick.bind(this));
			$('#aips-drawer-focus-btn').on('click', this.onDrawerFocusClick.bind(this));

			// Batch indexing scan controls
			$('#aips-start-indexing-btn').on('click', this.handleStartIndexing.bind(this));
			$('#aips-pause-indexing-btn').on('click', this.handlePauseIndexing.bind(this));
			$('#aips-clear-index-btn').on('click', this.handleClearIndex.bind(this));

			// Dimension mismatch re-index trigger
			$('#aips-reindex-dimension-btn').on('click', this.onReindexDimensionClick.bind(this));

			// Graph filtering and threshold controls
			$('#aips-graph-sim-threshold').on('input', this.onSimThresholdInput.bind(this));
			$('#aips-graph-sim-threshold').on('change', this.onSimThresholdChange.bind(this));
			$('#aips-graph-max-nodes').on('input', this.onMaxNodesInput.bind(this));
			$('#aips-graph-max-nodes').on('change', this.onMaxNodesChange.bind(this));
			$('#aips-refresh-graph-btn').on('click', this.onRefreshGraphClick.bind(this));

			// Zoom toolbar buttons
			$('#aips-zoom-in').on('click', this.onZoomInClick.bind(this));
			$('#aips-zoom-out').on('click', this.onZoomOutClick.bind(this));
			$('#aips-zoom-reset').on('click', this.onZoomResetClick.bind(this));

			// SVG canvas wheel zoom and drag-pan interactions
			var $svg = $('#aips-graph-svg');
			$svg.on('wheel', this.onSvgWheel.bind(this));
			$svg.on('mousedown', this.onSvgMouseDown.bind(this));
			$(document).on('mousemove', this.onDocumentMouseMove.bind(this));
			$(document).on('mouseup', this.onDocumentMouseUp.bind(this));

			// Autocomplete node search
			$('#aips-graph-post-search').on('input', this.onSearchInput.bind(this));
			$('#aips-graph-search-clear').on('click', this.onSearchClearClick.bind(this));
			$('#aips-active-post-clear').on('click', this.onActivePostClearClick.bind(this));
			$(document).on('click', '.aips-autocomplete-item', this.onAutocompleteItemClick.bind(this));
			$(document).on('click', this.onDocumentClick.bind(this));

			// Breadcrumb exploration trail navigation (delegated)
			$(document).on('click', '.aips-breadcrumb-chip', this.onBreadcrumbClick.bind(this));

			// Cannibalization audit scan trigger
			$('#aips-run-audit-btn').on('click', this.runCannibalizationAudit.bind(this));

			// Cannibalization audit accordion toggles (delegated)
			$(document).on('click', '.aips-audit-group-header, .aips-audit-risk-header', this.onAuditHeaderClick.bind(this));

			// Post Clusters controls & interactions
			$('#aips-refresh-clusters-btn').on('click', this.onRefreshClustersClick.bind(this));
			$('#aips-cluster-sim-threshold').on('input', this.onClusterSimThresholdInput.bind(this));
			$(document).on('click', '.aips-cluster-card-header', this.onClusterCardHeaderClick.bind(this));
			$(document).on('click', '.aips-cluster-rename-btn', this.onClusterRenameClick.bind(this));
			$(document).on('click', '.aips-pillar-toggle-btn', this.onPillarToggleClick.bind(this));
			$(document).on('click', '.aips-cluster-gaps-btn', this.onClusterGapsClick.bind(this));

			// Gap Suggestions Modal
			$('#aips-gap-modal-close, #aips-gap-modal-cancel').on('click', this.onCloseGapModalClick.bind(this));
			$(document).on('change', '.aips-gap-checkbox, #aips-gap-author-select', this.onGapSelectionChange.bind(this));
			$('#aips-commit-gap-topics-btn').on('click', this.onCommitGapTopicsClick.bind(this));

			// Convex Hull Toggle in Graph
			$('#aips-toggle-clusters').on('change', this.onToggleClustersChange.bind(this));

			// Show Author Topics Toggle in Graph
			$('#aips-toggle-topics').on('change', this.onToggleTopicsChange.bind(this));

			// Cannibalization Entity Type Filter
			$('#aips-audit-entity-type').on('change', this.onAuditEntityTypeChange.bind(this));

			// Cooldown Resume Now button
			$('#aips-resume-cooldown-btn').on('click', this.onResumeCooldownClick.bind(this));
		},

		// -----------------------------------------------------------------------
		// Event Handlers
		// -----------------------------------------------------------------------

		/**
		 * Handle switching visible tab panels.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onTabClick: function (e) {
			var tab = $(e.currentTarget).data('tab');
			if (!tab) {
				return;
			}

			e.preventDefault();

			// Deactivate all tab links and panels
			$('.aips-tab-link').removeClass('active');
			$('.aips-tab-content').removeClass('active').hide();

			// Activate target tab link and panel
			$(e.currentTarget).addClass('active');
			$('#' + tab + '-tab').addClass('active').show();

			// If switching back to visualizer, re-render the graph SVG to ensure correct viewport dimensions
			if (tab === 'visualizer' && this.graphData) {
				this.renderSvgGraph(this.graphData);
			}

			// If switching to clusters and data not loaded, automatically fetch
			if (tab === 'clusters' && !this.clustersData) {
				this.onRefreshClustersClick();
			}
		},

		/**
		 * Handle clicking the history back button to step back to the previous center post.
		 *
		 * @return {void}
		 */
		onHistoryBackClick: function () {
			this.popExplorationHistory();
		},

		/**
		 * Handle opening the node details drawer for the currently inspected center post.
		 *
		 * @return {void}
		 */
		onDrawerOpenClick: function () {
			var centerNode = this.getCurrentCenterNode();
			if (centerNode) {
				this.openNodeDrawer(centerNode);
			}
		},

		/**
		 * Handle closing the node details flyout drawer.
		 *
		 * @return {void}
		 */
		onDrawerCloseClick: function () {
			$('#aips-node-drawer').addClass('aips-hidden').hide();
		},

		/**
		 * Handle clicking the "Focus Node" action button inside the details drawer.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onDrawerFocusClick: function (e) {
			var targetId = $(e.currentTarget).data('raw-id');
			if (targetId) {
				$('#aips-node-drawer').addClass('aips-hidden').hide();
				this.loadGraphForPost(targetId);
			}
		},

		/**
		 * Update live label display while dragging similarity threshold slider.
		 *
		 * @param {jQuery.Event} e Input event.
		 * @return {void}
		 */
		onSimThresholdInput: function (e) {
			var val = parseFloat($(e.currentTarget).val());
			$('#aips-sim-val').text(Math.round(val * 100) + '%');
		},

		/**
		 * Reload graph when similarity threshold slider value commits.
		 *
		 * @return {void}
		 */
		onSimThresholdChange: function () {
			this.reloadGraph();
		},

		/**
		 * Update live label display while dragging max nodes slider.
		 *
		 * @param {jQuery.Event} e Input event.
		 * @return {void}
		 */
		onMaxNodesInput: function (e) {
			var val = parseInt($(e.currentTarget).val(), 10);
			$('#aips-nodes-val').text(val);
		},

		/**
		 * Reload graph when max nodes slider value commits.
		 *
		 * @return {void}
		 */
		onMaxNodesChange: function () {
			this.reloadGraph();
		},

		/**
		 * Handle clicking the re-render / refresh graph button.
		 *
		 * @return {void}
		 */
		onRefreshGraphClick: function () {
			this.reloadGraph();
		},

		/**
		 * Handle clicking the Zoom In toolbar button.
		 *
		 * @return {void}
		 */
		onZoomInClick: function () {
			var svg = document.getElementById('aips-graph-svg');
			var width = svg ? (svg.clientWidth || 900) : 900;
			var height = 560;
			this.setZoom(this.zoomScale * 1.25, width / 2, height / 2);
		},

		/**
		 * Handle clicking the Zoom Out toolbar button.
		 *
		 * @return {void}
		 */
		onZoomOutClick: function () {
			var svg = document.getElementById('aips-graph-svg');
			var width = svg ? (svg.clientWidth || 900) : 900;
			var height = 560;
			this.setZoom(this.zoomScale * 0.8, width / 2, height / 2);
		},

		/**
		 * Handle clicking the Zoom Reset toolbar button.
		 *
		 * @return {void}
		 */
		onZoomResetClick: function () {
			this.resetZoom();
		},

		/**
		 * Handle mouse wheel events on the SVG canvas for directional zooming.
		 *
		 * @param {jQuery.Event} e Wheel event.
		 * @return {void}
		 */
		onSvgWheel: function (e) {
			e.preventDefault();
			var svgEl = e.currentTarget;
			var rect = svgEl.getBoundingClientRect();
			var mouseX = e.clientX - rect.left;
			var mouseY = e.clientY - rect.top;
			var delta = (e.originalEvent.deltaY || e.originalEvent.wheelDelta) < 0 ? 1.15 : 0.87;
			this.setZoom(this.zoomScale * delta, mouseX, mouseY);
		},

		/**
		 * Handle mouse down on SVG canvas to initiate panning.
		 *
		 * @param {jQuery.Event} e MouseDown event.
		 * @return {void}
		 */
		onSvgMouseDown: function (e) {
			// Do not initiate pan if user clicked an interactive node or control button
			if ($(e.target).closest('.graph-node, .edge-pill, .aips-zoom-btn').length) {
				return;
			}
			this.isPanning = true;
			this.startPanX = e.clientX - this.panX;
			this.startPanY = e.clientY - this.panY;
			$('#aips-graph-svg').addClass('is-dragging');
		},

		/**
		 * Handle document mousemove during active canvas drag-panning.
		 *
		 * @param {jQuery.Event} e MouseMove event.
		 * @return {void}
		 */
		onDocumentMouseMove: function (e) {
			if (this.isPanning) {
				this.panX = e.clientX - this.startPanX;
				this.panY = e.clientY - this.startPanY;
				this.applyTransform();
			}
		},

		/**
		 * Handle document mouseup to release active drag-panning.
		 *
		 * @return {void}
		 */
		onDocumentMouseUp: function () {
			if (this.isPanning) {
				this.isPanning = false;
				$('#aips-graph-svg').removeClass('is-dragging');
			}
		},

		/**
		 * Handle live input in the autocomplete search box with debouncing.
		 *
		 * @param {jQuery.Event} e Input event.
		 * @return {void}
		 */
		onSearchInput: function (e) {
			var query = $(e.currentTarget).val();
			if (query.length > 0) {
				$('#aips-graph-search-clear').removeClass('aips-hidden').show();
			} else {
				$('#aips-graph-search-clear').addClass('aips-hidden').hide();
				$('#aips-graph-post-dropdown').addClass('aips-hidden').hide().empty();
			}

			clearTimeout(this._searchTimer);
			if (query.length < 2) {
				return;
			}

			var self = this;
			this._searchTimer = setTimeout(function () {
				self.searchPosts(query);
			}, 250);
		},

		/**
		 * Handle clicking the clear button inside the search input.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onSearchClearClick: function (e) {
			$('#aips-graph-post-search').val('');
			$(e.currentTarget).addClass('aips-hidden').hide();
			$('#aips-graph-post-dropdown').addClass('aips-hidden').hide().empty();
		},

		/**
		 * Handle clearing the active center post selection and resetting the graph.
		 *
		 * @return {void}
		 */
		onActivePostClearClick: function () {
			this.clearExplorationHistory();
			$('#aips-graph-selected-post-id').val('');
			$('#aips-graph-post-search').val('');
			$('#aips-graph-search-clear').addClass('aips-hidden').hide();
			$('#aips-active-post-bar').addClass('aips-hidden').hide();
			this.loadGraphForPost(0);
		},

		/**
		 * Handle toggling convex hulls for post clusters in the graph visualizer.
		 *
		 * @return {void}
		 */
		onToggleClustersChange: function () {
			if (this.graphData) {
				this.renderSvgGraph(this.graphData);
			}
		},

		/**
		 * Handle toggling visibility of Author Topics in the graph visualizer.
		 *
		 * @return {void}
		 */
		onToggleTopicsChange: function () {
			if (this.graphData) {
				this.renderSvgGraph(this.graphData);
			}
		},

		/**
		 * Handle filtering duplicate and cannibalization audit results by entity.
		 *
		 * @return {void}
		 */
		onAuditEntityTypeChange: function () {
			this.runCannibalizationAudit();
		},

		/**
		 * Handle selecting an item from the autocomplete dropdown list.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onAutocompleteItemClick: function (e) {
			var $item = $(e.currentTarget);
			var postId = $item.data('id');
			var rawTitle = $item.data('title') || $item.find('.aips-autocomplete-title').text();
			var postType = $item.data('type') || 'post';
			var isIndexed = $item.data('indexed') === 1 || $item.data('indexed') === '1' || $item.data('indexed') === true;

			// Populate search bar with selection
			$('#aips-graph-selected-post-id').val(postId);
			$('#aips-graph-post-search').val(rawTitle);
			$('#aips-graph-search-clear').removeClass('aips-hidden').show();
			$('#aips-graph-post-dropdown').addClass('aips-hidden').hide().empty();

			// Update active post banner header
			$('#aips-active-post-title').text(rawTitle);
			$('#aips-active-post-meta').text(postType.toUpperCase() + ' #' + postId + (isIndexed ? ' • Indexed' : ' • Pending Indexing'));
			$('#aips-active-post-bar').removeClass('aips-hidden').show();

			if (!isIndexed && AIPS.Utilities) {
				AIPS.Utilities.showNotice('Selected post has not been indexed yet. Scanning will create its embeddings.', 'warning');
			}

			// Clear historical trail and focus new post
			this.clearExplorationHistory();
			this.loadGraphForPost(postId);
		},

		/**
		 * Dismiss autocomplete dropdown when clicking outside of the search wrapper.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onDocumentClick: function (e) {
			if (!$(e.target).closest('.aips-visualizer-search-wrap').length) {
				$('#aips-graph-post-dropdown').addClass('aips-hidden').hide();
			}
		},

		/**
		 * Handle clicking a chip in the exploration history breadcrumbs.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onBreadcrumbClick: function (e) {
			var index = parseInt($(e.currentTarget).attr('data-index'), 10);
			var targetId = $(e.currentTarget).attr('data-id');
			if (isNaN(index) || !targetId) {
				return;
			}

			// Truncate history stack up to the clicked breadcrumb
			this.explorationHistory = this.explorationHistory.slice(0, index);
			this.renderBreadcrumbs();

			// Load the target post with transition animation
			$('#aips-graph-canvas-wrap').addClass('is-traversing');
			var self = this;
			setTimeout(function () {
				self.loadGraphForPost(targetId, false);
				$('#aips-graph-canvas-wrap').removeClass('is-traversing');
			}, 120);
		},

		/**
		 * Handle dimension mismatch re-index confirmation and trigger.
		 *
		 * @return {void}
		 */
		onReindexDimensionClick: function () {
			if (!confirm('This will clear stored vector embeddings and re-index all content using the active environment and model. Proceed?')) {
				return;
			}
			this.handleClearIndex();
			$('.aips-tab-link[data-tab="scanner"]').trigger('click');
			var self = this;
			setTimeout(function () {
				self.handleStartIndexing();
			}, 600);
		},

		/**
		 * Handle click on an audit group or risk level accordion header to toggle visibility.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onAuditHeaderClick: function (e) {
			var $header = $(e.currentTarget);
			var targetSelector = $header.attr('data-toggle-target');
			var $icon = $header.find('.aips-group-toggle-icon');
			var $tbody = $('#aips-cannibalization-tbody');
			var $targets = $tbody.find(targetSelector);

			if (!$targets.length) {
				return;
			}

			var isVisible = $targets.is(':visible') && !$targets.first().hasClass('is-collapsed');

			if (isVisible) {
				// Collapsing section
				if ($header.hasClass('aips-audit-group-header')) {
					// Collapse all child risk headers and rows
					$targets.addClass('is-collapsed').hide();
					$targets.filter('.aips-audit-risk-header').find('.aips-group-toggle-icon')
						.removeClass('dashicons-arrow-down-alt2')
						.addClass('dashicons-arrow-right-alt2');
				} else {
					$targets.addClass('is-collapsed').hide();
				}
				$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
			} else {
				// Expanding section
				if ($header.hasClass('aips-audit-group-header')) {
					// Show the risk level headers for this group
					$targets.filter('.aips-audit-risk-header').removeClass('is-collapsed').show();
					// Expand only the first risk level tier by default
					var $firstRisk = $targets.filter('.aips-audit-risk-header').first();
					$firstRisk.find('.aips-group-toggle-icon')
						.removeClass('dashicons-arrow-right-alt2')
						.addClass('dashicons-arrow-down-alt2');
					var firstTargetSelector = $firstRisk.attr('data-toggle-target');
					$tbody.find(firstTargetSelector).removeClass('is-collapsed').show();
				} else {
					$targets.removeClass('is-collapsed').show();
				}
				$icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
			}
		},

		/**
		 * Handle mouseenter on an SVG node element to dim unrelated nodes and show rich tooltip.
		 *
		 * @param {jQuery.Event} e MouseEnter event.
		 * @return {void}
		 */
		onNodeMouseEnter: function (e) {
			var nodeId = $(e.currentTarget).attr('data-id');
			if (!this.graphData || !this.graphData.nodes) {
				return;
			}

			var nodes = this.graphData.nodes;
			var edges = this.graphData.edges || [];
			var node = nodes.find(function (n) { return String(n.id) === String(nodeId); });
			if (!node) {
				return;
			}

			// Map connected node IDs to highlight
			var connectedNodeIds = {};
			connectedNodeIds[nodeId] = true;
			edges.forEach(function (ed) {
				if (ed.source === nodeId) connectedNodeIds[ed.target] = true;
				if (ed.target === nodeId) connectedNodeIds[ed.source] = true;
			});

			// Highlight active connections and dim unrelated nodes
			$('.graph-node').each(function () {
				var nid = $(this).attr('data-id');
				if (connectedNodeIds[nid]) {
					$(this).addClass('is-highlighted').removeClass('is-dimmed');
				} else {
					$(this).addClass('is-dimmed').removeClass('is-highlighted');
				}
			});

			// Highlight active edges and edge pills
			$('.graph-edge, .edge-pill').each(function () {
				var src = $(this).attr('data-source');
				var tgt = $(this).attr('data-target');
				if (src === nodeId || tgt === nodeId) {
					$(this).addClass('is-highlighted').removeClass('is-dimmed');
				} else {
					$(this).addClass('is-dimmed').removeClass('is-highlighted');
				}
			});

			// Populate and position the rich hover tooltip
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

			this.updateTooltipPosition(e);
			$tooltip.removeClass('aips-hidden').show();
		},

		/**
		 * Handle mousemove over a node to keep the tooltip aligned with the cursor.
		 *
		 * @param {jQuery.Event} e MouseMove event.
		 * @return {void}
		 */
		onNodeMouseMove: function (e) {
			this.updateTooltipPosition(e);
		},

		/**
		 * Handle mouseleave from a node to clear dimming and hide the tooltip.
		 *
		 * @return {void}
		 */
		onNodeMouseLeave: function () {
			$('.graph-node, .graph-edge, .edge-pill').removeClass('is-dimmed is-highlighted');
			$('#aips-graph-tooltip').addClass('aips-hidden').hide();
		},

		/**
		 * Handle clicking a node: drill down into neighbor or open drawer for center.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onNodeClick: function (e) {
			e.stopPropagation();
			var nodeId = $(e.currentTarget).attr('data-id');
			if (!this.graphData || !this.graphData.nodes) {
				return;
			}

			var nodes = this.graphData.nodes;
			var node = nodes.find(function (n) { return String(n.id) === String(nodeId); });
			if (!node) {
				return;
			}

			if (node.is_center) {
				// Center node opens the detail flyout drawer
				this.openNodeDrawer(node);
			} else {
				// Neighbor node triggers drill-down and adds current center to exploration history
				var currentCenter = nodes.find(function (n) { return n.is_center; }) || nodes[0];
				if (currentCenter) {
					this.pushExplorationHistory({
						id: currentCenter.raw_id || currentCenter.id,
						label: currentCenter.label,
						type: currentCenter.type
					});
				}

				$('#aips-graph-canvas-wrap').addClass('is-traversing');
				var self = this;
				setTimeout(function () {
					self.loadGraphForPost(node.raw_id || node.id, false);
					$('#aips-graph-canvas-wrap').removeClass('is-traversing');
				}, 120);
			}
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

			if (this.zoomScale >= 1.35) {
				$('#aips-graph-svg').addClass('aips-graph-deep-zoom');
			} else {
				$('#aips-graph-svg').removeClass('aips-graph-deep-zoom');
			}

			var invScale = 1 / this.zoomScale;
			$('.graph-node').each(function () {
				var ox = this.getAttribute('data-orig-x');
				var oy = this.getAttribute('data-orig-y');
				if (ox !== null && oy !== null) {
					this.setAttribute('transform', 'translate(' + ox + ',' + oy + ') scale(' + invScale + ')');
				}
			});

			$('.edge-pill').each(function () {
				var ox = this.getAttribute('data-orig-x');
				var oy = this.getAttribute('data-orig-y');
				if (ox !== null && oy !== null) {
					this.setAttribute('transform', 'translate(' + ox + ',' + oy + ') scale(' + invScale + ')');
				}
			});
		},

		/**
		 * Push a node to the exploration history trail.
		 */
		pushExplorationHistory: function (nodeInfo) {
			if (!nodeInfo || !nodeInfo.id) {
				return;
			}
			var last = this.explorationHistory[this.explorationHistory.length - 1];
			if (last && String(last.id) === String(nodeInfo.id)) {
				return;
			}
			this.explorationHistory.push(nodeInfo);
			this.renderBreadcrumbs();
		},

		/**
		 * Pop previous node from exploration history and return to it.
		 */
		popExplorationHistory: function () {
			if (this.explorationHistory.length === 0) {
				return;
			}
			var prev = this.explorationHistory.pop();
			this.renderBreadcrumbs();
			if (prev && prev.id) {
				$('#aips-graph-canvas-wrap').addClass('is-traversing');
				var self = this;
				setTimeout(function () {
					self.loadGraphForPost(prev.id, false);
					$('#aips-graph-canvas-wrap').removeClass('is-traversing');
				}, 120);
			}
		},

		/**
		 * Clear entire exploration history trail.
		 */
		clearExplorationHistory: function () {
			this.explorationHistory = [];
			this.renderBreadcrumbs();
		},

		/**
		 * Render Breadcrumbs trail in the UI.
		 *
		 * @return {void}
		 */
		renderBreadcrumbs: function () {
			var $wrap = $('#aips-graph-breadcrumbs').empty();
			var $backBtn = $('#aips-history-back-btn');

			if (this.explorationHistory.length === 0) {
				$wrap.addClass('aips-hidden').hide();
				$backBtn.addClass('aips-hidden').hide();
				return;
			}

			$backBtn.removeClass('aips-hidden').show();
			$wrap.removeClass('aips-hidden').show();

			this.explorationHistory.forEach(function (item, index) {
				var itemTitle = item.label || item.title || ('#' + item.id);
				var shortTitle = itemTitle.length > 20 ? itemTitle.substring(0, 18) + '…' : itemTitle;

				var crumbHtml = AIPS.Templates.render('aips-tmpl-indexer-breadcrumb-chip', {
					title: AIPS.Templates.escape(itemTitle),
					shortTitle: AIPS.Templates.escape(shortTitle),
					index: index,
					id: item.id
				});
				var sepHtml = AIPS.Templates.render('aips-tmpl-indexer-breadcrumb-sep');

				$wrap.append(crumbHtml);
				$wrap.append(sepHtml);
			});
		},

		/**
		 * Get the currently active center node object.
		 */
		getCurrentCenterNode: function () {
			if (!this.graphData || !this.graphData.nodes) {
				return null;
			}
			return this.graphData.nodes.find(function (n) { return n.is_center; }) || this.graphData.nodes[0] || null;
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

			var entityScope = $('#aips-scan-entity-scope').val() || 'all';

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_process_batch',
					nonce: aipsContentIndexerL10n.nonce,
					batch_size: self.batchSize,
					last_post_id: self.lastPostId,
					entity_scope: entityScope
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
					if (data.status) {
						var st = data.status;
						$('#aips-stat-indexed').text(st.indexed);
						$('#aips-stat-total').text(st.total_posts);
						$('#aips-stat-percent').text(st.percent + '%');
						$('#aips-index-progress-bar').css('width', st.percent + '%');

						$('#aips-stat-topics-indexed').text(st.indexed_topics || 0);
						$('#aips-stat-topics-total').text(st.total_topics || 0);
						$('#aips-stat-topics-percent').text((st.topics_percent || 0) + '%');
						$('#aips-topics-progress-bar').css('width', (st.topics_percent || 0) + '%');

						var totalUnindexed = (st.unindexed || 0) + (st.unindexed_topics || 0);
						$('#aips-stat-unindexed').text(totalUnindexed);
						$('#aips-stat-unindexed-breakdown').text(st.unindexed + ' posts, ' + st.unindexed_topics + ' topics pending');

						if (entityScope === 'topics') {
							$('#aips-indexer-slice-count').text((st.indexed_topics || 0) + ' / ' + (st.total_topics || 0));
						} else {
							$('#aips-indexer-slice-count').text(st.indexed + ' / ' + st.total_posts);
						}
					} else {
						$('#aips-stat-indexed').text(data.total_indexed);
						$('#aips-stat-total').text(data.total_posts);
						$('#aips-stat-percent').text(data.percent + '%');
						$('#aips-index-progress-bar').css('width', data.percent + '%');
						$('#aips-stat-unindexed').text(Math.max(0, data.total_posts - data.total_indexed));
						$('#aips-indexer-slice-count').text(data.total_indexed + ' / ' + data.total_posts);
					}

					// Update live rate limit meters if returned
					if (data.rate_limits) {
						var rl = data.rate_limits;
						var dCnt = rl.daily_count || 0, dLim = rl.daily_limit || 0;
						var wCnt = rl.weekly_count || 0, wLim = rl.weekly_limit || 0;
						var mCnt = rl.monthly_count || 0, mLim = rl.monthly_limit || 0;
						var dLimitText = dLim > 0 ? String(dLim) : '∞';
						var wLimitText = wLim > 0 ? String(wLim) : '∞';
						var mLimitText = mLim > 0 ? String(mLim) : '∞';

						if (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-indexer-meter-count')) {
							$('#aips-meter-daily-count').html(AIPS.Templates.render('aips-tmpl-indexer-meter-count', { count: dCnt, limit: dLimitText }));
							$('#aips-meter-weekly-count').html(AIPS.Templates.render('aips-tmpl-indexer-meter-count', { count: wCnt, limit: wLimitText }));
							$('#aips-meter-monthly-count').html(AIPS.Templates.render('aips-tmpl-indexer-meter-count', { count: mCnt, limit: mLimitText }));
						} else {
							$('#aips-meter-daily-count').html('<strong>' + dCnt + '</strong> / ' + dLimitText);
							$('#aips-meter-weekly-count').html('<strong>' + wCnt + '</strong> / ' + wLimitText);
							$('#aips-meter-monthly-count').html('<strong>' + mCnt + '</strong> / ' + mLimitText);
						}
						if (dLim > 0) $('#aips-meter-daily-bar').css('width', Math.min(100, Math.round((dCnt / dLim) * 100)) + '%');
						if (wLim > 0) $('#aips-meter-weekly-bar').css('width', Math.min(100, Math.round((wCnt / wLim) * 100)) + '%');
						if (mLim > 0) $('#aips-meter-monthly-bar').css('width', Math.min(100, Math.round((mCnt / mLim) * 100)) + '%');
					}

					if (data.cooldown && data.cooldown.active) {
						self.isIndexing = false;
						self.isPaused = false;
						$('#aips-pause-indexing-btn').hide();
						$('#aips-start-indexing-btn').show().find('.btn-text').text(aipsContentIndexerL10n.startScan || 'Start Scan');
						$('#aips-indexer-live-banner').slideUp(200);

						self.showCooldownBanner(data.cooldown);
						AIPS.Utilities && AIPS.Utilities.showNotice(data.cooldown.reason || 'Embedding auto-cooldown activated. Indexing paused.', 'warning');
						return;
					}

					if (data.rate_limit_exceeded) {
						self.isIndexing = false;
						self.isPaused = false;
						$('#aips-pause-indexing-btn').hide();
						$('#aips-start-indexing-btn').show().find('.btn-text').text(aipsContentIndexerL10n.startScan || 'Start Scan');
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
						$('#aips-start-indexing-btn').show().find('.btn-text').text(aipsContentIndexerL10n.startScan || 'Start Scan');
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

							var itemHtml = AIPS.Templates.render('aips-tmpl-indexer-autocomplete-item', {
								id: it.id,
								title: AIPS.Templates.escape(rawTitle),
								type: AIPS.Templates.escape(pType),
								indexed: isIndexed ? 1 : 0,
								badgeClass: badgeClass,
								badgeText: AIPS.Templates.escape(badgeText)
							});
							$dropdown.append(itemHtml);
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
				},
				error: function () {
					$('#aips-graph-svg').empty();
					$('#aips-graph-empty').removeClass('aips-hidden').show();
					$('#aips-active-post-bar').addClass('aips-hidden').hide();
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

			// Draw Convex Hull Bubbles for Post Clusters if toggled
			if ($('#aips-toggle-clusters').is(':checked')) {
				self.renderClusterHulls(gViewport, nodes, edges);
			}

			// 1. Draw Edges & Edge Pills
			var gEdges = document.createElementNS('http://www.w3.org/2000/svg', 'g');
			gEdges.setAttribute('class', 'edges-group');
			var showTopics = $('#aips-toggle-topics').is(':checked');

			edges.forEach(function (edge) {
				var src = nodeMap[edge.source];
				var tgt = nodeMap[edge.target];
				if (!src || !tgt) return;

				if (!showTopics) {
					if ((src.entity_type === 'topic' && !src.is_center) || (tgt.entity_type === 'topic' && !tgt.is_center)) {
						return;
					}
				}

				var isSpoke = (edge.source === centerNode.id || edge.target === centerNode.id);
				var weight = edge.weight || 0.6;
				var edgeClass = 'graph-edge ' + (isSpoke ? 'edge-spoke' : 'edge-cross');
				if (weight >= 0.80) edgeClass += ' edge-high';
				else if (weight >= 0.65) edgeClass += ' edge-med';
				else edgeClass += ' edge-low';

				var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
				line.setAttribute('x1', src.x);
				line.setAttribute('y1', src.y);
				line.setAttribute('x2', tgt.x);
				line.setAttribute('y2', tgt.y);
				line.setAttribute('data-source', edge.source);
				line.setAttribute('data-target', edge.target);
				line.setAttribute('vector-effect', 'non-scaling-stroke');
				line.setAttribute('class', edgeClass);
				line.setAttribute('stroke-width', Math.max(1.5, weight * 3.5));
				gEdges.appendChild(line);

				// Edge Label Pill (Contrast Background + Text)
				var midX = (src.x + tgt.x) / 2;
				var midY = (src.y + tgt.y) / 2;
				var pillClass = 'edge-pill ' + (isSpoke ? 'edge-pill-spoke' : 'edge-pill-cross');
				if (weight >= 0.80) pillClass += ' edge-pill-high';

				var pillG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				pillG.setAttribute('class', pillClass);
				pillG.setAttribute('data-source', edge.source);
				pillG.setAttribute('data-target', edge.target);
				pillG.setAttribute('data-orig-x', midX);
				pillG.setAttribute('data-orig-y', midY);
				pillG.setAttribute('transform', 'translate(' + midX + ',' + midY + ') scale(' + (1 / self.zoomScale) + ')');

				var pillBg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
				pillBg.setAttribute('class', 'edge-pill-bg');
				pillBg.setAttribute('x', -20);
				pillBg.setAttribute('y', -10);
				pillBg.setAttribute('width', 40);
				pillBg.setAttribute('height', 20);
				pillBg.setAttribute('rx', 4);
				pillBg.setAttribute('ry', 4);
				pillBg.setAttribute('vector-effect', 'non-scaling-stroke');
				pillG.appendChild(pillBg);

				var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
				text.setAttribute('x', 0);
				text.setAttribute('y', 0);
				text.setAttribute('class', 'edge-pill-text');
				text.textContent = edge.label;
				pillG.appendChild(text);

				gEdges.appendChild(pillG);
			});
			gViewport.appendChild(gEdges);

			// 2. Draw Nodes
			var gNodes = document.createElementNS('http://www.w3.org/2000/svg', 'g');
			gNodes.setAttribute('class', 'nodes-group');

			nodes.forEach(function (node) {
				if (!showTopics && node.entity_type === 'topic' && !node.is_center) {
					return;
				}

				var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				var nodeClass = 'graph-node';
				if (node.is_center) nodeClass += ' node-center';
				else {
					nodeClass += ' node-neighbor';
					if (node.similarity >= 0.80) nodeClass += ' node-high';
					else if (node.similarity < 0.65) nodeClass += ' node-low';
				}
				if (node.entity_type === 'topic') {
					nodeClass += ' node-topic';
				}
				g.setAttribute('class', nodeClass);
				g.setAttribute('data-id', node.id);
				g.setAttribute('data-orig-x', node.x);
				g.setAttribute('data-orig-y', node.y);
				g.setAttribute('transform', 'translate(' + node.x + ',' + node.y + ') scale(' + (1 / self.zoomScale) + ')');

				var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
				circle.setAttribute('r', node.is_center ? 24 : 16);
				circle.setAttribute('vector-effect', 'non-scaling-stroke');
				g.appendChild(circle);

				var label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
				label.setAttribute('y', node.is_center ? 38 : 28);
				label.setAttribute('text-anchor', 'middle');
				var truncated = node.label.length > 22 ? node.label.substring(0, 20) + '…' : node.label;
				label.textContent = truncated;
				g.appendChild(label);

				// Attach named event listeners to node group
				$(g).on('mouseenter', self.onNodeMouseEnter.bind(self));
				$(g).on('mousemove', self.onNodeMouseMove.bind(self));
				$(g).on('mouseleave', self.onNodeMouseLeave.bind(self));
				$(g).on('click', self.onNodeClick.bind(self));

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
			var isTopic = (node.entity_type === 'topic' || node.type === 'topic');
			var typeLabel = isTopic ? (aipsContentIndexerL10n.authorTopic || 'Author Topic') : (node.type || 'post');
			$('#aips-drawer-type').text(typeLabel);
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
			var entityType = $('#aips-audit-entity-type').val() || 'all';

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
					limit: 50,
					entity_type: entityType
				},
				success: function (res) {
					$btn.prop('disabled', false);
					$loading.hide();

					if (!res.success || !res.data.clusters || res.data.clusters.length === 0) {
						var emptyMsg = (aipsContentIndexerL10n && aipsContentIndexerL10n.noAuditDuplicates) ? aipsContentIndexerL10n.noAuditDuplicates : 'No high-similarity duplicate or cannibalizing clusters found. Great job!';
						if (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-indexer-audit-clean')) {
							$tbody.html(AIPS.Templates.render('aips-tmpl-indexer-audit-clean', { message: emptyMsg }));
						} else if (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-indexer-audit-empty')) {
							$tbody.html(AIPS.Templates.render('aips-tmpl-indexer-audit-empty', { message: emptyMsg }));
						} else {
							$tbody.html($('<tr>').append($('<td>').attr('colspan', 5).addClass('aips-audit-clean-cell').append($('<strong>').text(emptyMsg))));
						}
						return;
					}

					var clusters = res.data.clusters;
					var sourceGroups = {};

					clusters.forEach(function (c) {
						if (!sourceGroups[c.source_id]) {
							sourceGroups[c.source_id] = {
								source_id: c.source_id,
								source_type: c.source_type || 'post',
								title: c.source_title,
								post_type: c.source_post_type,
								date: c.source_date,
								url: c.source_url,
								audit_type: c.audit_type || 'post_duplicate',
								max_similarity: 0,
								max_risk_class: 'aips-risk-low',
								max_risk_label: 'Low Risk',
								risk_groups: {
									high: [],
									medium: [],
									low: []
								}
							};
						}

						var sg = sourceGroups[c.source_id];
						var riskClass = 'aips-risk-low';
						var riskLabel = 'Low Risk';
						var riskKey = 'low';

						if (c.similarity >= 0.88) {
							riskClass = 'aips-risk-high';
							riskLabel = 'High Cannibalization';
							riskKey = 'high';
						} else if (c.similarity >= 0.80) {
							riskClass = 'aips-risk-medium';
							riskLabel = 'Moderate';
							riskKey = 'medium';
						}

						if (c.similarity > sg.max_similarity) {
							sg.max_similarity = c.similarity;
							sg.max_risk_class = riskClass;
							sg.max_risk_label = riskLabel;
						}

						sg.risk_groups[riskKey].push(c);
					});

					var html = '';
					var groupIndex = 0;
					var hasTemplates = window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-indexer-audit-group-header');

					var sortedGroups = Object.values(sourceGroups).sort(function (a, b) {
						return b.max_similarity - a.max_similarity;
					});

					// 4. Render HTML templates for each group, risk tier, and candidate row
					sortedGroups.forEach(function (sg) {
						groupIndex++;
						var groupId = 'aips-sg-' + groupIndex;

						var sgSourceType = sg.source_type || 'post';
						var entityBadgeType = sgSourceType === 'topic' ? 'topic' : 'post';
						var entityBadgeLabel = sgSourceType === 'topic' ? (aipsContentIndexerL10n.authorTopic || 'Author Topic') : (aipsContentIndexerL10n.post || 'Post');
						var entityBadge = '';
						if (hasTemplates && AIPS.Templates.has('aips-tmpl-indexer-entity-badge')) {
							entityBadge = AIPS.Templates.render('aips-tmpl-indexer-entity-badge', {
								type: entityBadgeType,
								label: entityBadgeLabel
							});
						} else {
							entityBadge = '<span class="aips-entity-badge aips-entity-badge-' + entityBadgeType + '">' + entityBadgeLabel + '</span>';
						}

						// Render Post A Parent Group Header
						if (hasTemplates) {
							html += AIPS.Templates.render('aips-tmpl-indexer-audit-group-header', {
								groupId: groupId,
								title: sg.title || '',
								postType: sg.post_type || 'post',
								sourceId: sg.source_id,
								riskClass: sg.max_risk_class,
								riskLabel: sg.max_risk_label,
								maxSimilarityPct: (sg.max_similarity * 100).toFixed(1),
								entityBadgeHtml: entityBadge,
								auditType: sg.audit_type || 'post_duplicate'
							});
						} else {
							html += '<tr class="aips-audit-group-header" data-toggle-target=".' + groupId + '" data-audit-type="' + (sg.audit_type || 'post_duplicate') + '">';
							html += '<td colspan="5">';
							html += '<span class="dashicons dashicons-arrow-down-alt2 aips-group-toggle-icon aips-audit-group-toggle-icon"></span>';
							html += '<strong class="aips-audit-group-title">' + $('<div>').text(sg.title).html() + '</strong>';
							html += entityBadge;
							html += '<span class="aips-audit-group-meta">' + sg.post_type + ' #' + sg.source_id + '</span>';
							html += '<span class="aips-risk-badge ' + sg.max_risk_class + ' aips-audit-group-badge">Max: ' + sg.max_risk_label + ' (' + (sg.max_similarity * 100).toFixed(1) + '%)</span>';
							html += '</td></tr>';
						}

						var riskOrder = [
							{ key: 'high', label: 'High Cannibalization', class: 'aips-risk-high' },
							{ key: 'medium', label: 'Moderate Risk', class: 'aips-risk-medium' },
							{ key: 'low', label: 'Low Risk', class: 'aips-risk-low' }
						];

						var firstRiskGroup = true;

						// Render each populated risk tier within the group
						riskOrder.forEach(function (riskDef) {
							if (sg.risk_groups[riskDef.key].length === 0) return;

							var riskGroupId = groupId + '-risk-' + riskDef.key;
							var isExpanded = firstRiskGroup;
							firstRiskGroup = false;

							var iconClass = isExpanded ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2';
							var count = sg.risk_groups[riskDef.key].length;

							// Render Risk Tier Header
							if (hasTemplates) {
								html += AIPS.Templates.render('aips-tmpl-indexer-audit-risk-header', {
									groupId: groupId,
									riskGroupId: riskGroupId,
									iconClass: iconClass,
									riskClass: riskDef.class,
									riskLabel: riskDef.label,
									count: count,
									candidateLabel: count === 1 ? 'candidate' : 'candidates'
								});
							} else {
								html += '<tr class="aips-audit-risk-header ' + groupId + '" data-toggle-target=".' + riskGroupId + '">';
								html += '<td colspan="5" class="aips-audit-risk-cell">';
								html += '<span class="dashicons ' + iconClass + ' aips-group-toggle-icon aips-audit-risk-icon"></span>';
								html += '<span class="aips-risk-badge ' + riskDef.class + ' aips-audit-risk-badge">' + riskDef.label + '</span>';
								html += '<span class="aips-audit-risk-count">' + count + ' candidate(s)</span>';
								html += '</td></tr>';
							}

							// Render each duplicate candidate (Entity B)
							sg.risk_groups[riskDef.key].forEach(function (c) {
								var actions = '';
								var editSourceLabel = (aipsContentIndexerL10n && aipsContentIndexerL10n.editSource) ? aipsContentIndexerL10n.editSource : 'Edit Source';
								var editTargetLabel = (aipsContentIndexerL10n && aipsContentIndexerL10n.editTarget) ? aipsContentIndexerL10n.editTarget : 'Edit Target';

								if (c.source_edit_url) {
									if (hasTemplates) {
										actions += AIPS.Templates.render('aips-tmpl-indexer-audit-action-btn', {
											url: c.source_edit_url,
											label: editSourceLabel
										});
									} else {
										actions += '<a href="' + c.source_edit_url + '" class="button button-small aips-audit-action-btn" target="_blank">' + editSourceLabel + '</a>';
									}
								}
								if (c.target_edit_url) {
									if (hasTemplates) {
										actions += AIPS.Templates.render('aips-tmpl-indexer-audit-action-btn', {
											url: c.target_edit_url,
											label: editTargetLabel
										});
									} else {
										actions += '<a href="' + c.target_edit_url + '" class="button button-small aips-audit-action-btn" target="_blank">' + editTargetLabel + '</a>';
									}
								}

								var targetBadgeType = 'post';
								var targetBadgeLabel = aipsContentIndexerL10n.post || 'Post';
								if (c.audit_type === 'cannibalization') {
									targetBadgeType = 'cannibalization';
									targetBadgeLabel = aipsContentIndexerL10n.cannibalizationRisk || 'Cannibalization Risk';
								} else if (c.target_type === 'topic') {
									targetBadgeType = 'topic';
									targetBadgeLabel = aipsContentIndexerL10n.authorTopic || 'Author Topic';
								}

								var targetBadge = '';
								if (hasTemplates && AIPS.Templates.has('aips-tmpl-indexer-entity-badge')) {
									targetBadge = AIPS.Templates.render('aips-tmpl-indexer-entity-badge', {
										type: targetBadgeType,
										label: targetBadgeLabel
									});
								} else {
									targetBadge = '<span class="aips-entity-badge aips-entity-badge-' + targetBadgeType + '">' + targetBadgeLabel + '</span>';
								}

								if (hasTemplates) {
									html += AIPS.Templates.renderRaw('aips-tmpl-indexer-audit-row', {
										groupId: groupId,
										riskGroupId: riskGroupId,
										collapseClass: isExpanded ? '' : 'is-collapsed',
										title: AIPS.Templates.escape(c.target_title || ''),
										postType: AIPS.Templates.escape(c.target_post_type || 'post'),
										targetId: c.target_id,
										date: AIPS.Templates.escape(c.target_date || ''),
										similarityPct: c.similarity_pct,
										riskClass: riskDef.class,
										riskLabel: AIPS.Templates.escape(riskDef.label),
										actions: actions,
										entityBadgeHtml: targetBadge,
										auditType: c.audit_type || 'post_duplicate'
									});
								} else {
									html += '<tr class="aips-audit-row ' + groupId + ' ' + riskGroupId + (isExpanded ? '' : ' is-collapsed') + '" data-audit-type="' + (c.audit_type || 'post_duplicate') + '">';
									html += '<td class="aips-audit-tree-indent">&rdsh;</td>';
									html += '<td><strong>' + $('<div>').text(c.target_title).html() + '</strong>' + targetBadge + '<br><small class="aips-audit-target-meta">' + c.target_post_type + ' #' + c.target_id + ' (' + c.target_date + ')</small></td>';
									html += '<td><strong class="aips-audit-similarity-score">' + c.similarity_pct + '%</strong></td>';
									html += '<td><span class="aips-risk-badge ' + riskDef.class + '">' + riskDef.label + '</span></td>';
									html += '<td>' + actions + '</td>';
									html += '</tr>';
								}
							});
						});
					});

					$tbody.html(html);
				},
				error: function () {
					$btn.prop('disabled', false);
					$loading.hide();
					if (AIPS.Utilities) {
						AIPS.Utilities.showNotice('Error running cannibalization audit.', 'error');
					}
				}
			});
		},

		// -----------------------------------------------------------------------
		// Convex Hull Bubble Overlay (Graph Post Clusters)
		// -----------------------------------------------------------------------

		/**
		 * Render convex hull bubbles around community clusters on the SVG graph.
		 *
		 * @param {Element} gViewport The SVG viewport group.
		 * @param {Array<Object>} nodes Array of node objects.
		 * @param {Array<Object>} edges Array of edge objects.
		 * @return {void}
		 */
		renderClusterHulls: function (gViewport, nodes, edges) {
			if (!nodes || nodes.length < 2) {
				return;
			}

			// Group connected nodes based on high-similarity edges
			var adj = {};
			nodes.forEach(function (n) { adj[n.id] = []; });
			edges.forEach(function (e) {
				if ((e.weight || 0) >= 0.55 && adj[e.source] && adj[e.target]) {
					adj[e.source].push(e.target);
					adj[e.target].push(e.source);
				}
			});

			var visited = {};
			var communities = [];
			var nodeMap = {};
			nodes.forEach(function (n) { nodeMap[n.id] = n; });

			nodes.forEach(function (node) {
				if (visited[node.id]) {
					return;
				}
				var comp = [];
				var queue = [node.id];
				visited[node.id] = true;

				while (queue.length > 0) {
					var currId = queue.shift();
					comp.push(nodeMap[currId]);
					(adj[currId] || []).forEach(function (nbrId) {
						if (!visited[nbrId]) {
							visited[nbrId] = true;
							queue.push(nbrId);
						}
					});
				}

				if (comp.length >= 2) {
					communities.push(comp);
				}
			});

			var gHulls = document.createElementNS('http://www.w3.org/2000/svg', 'g');
			gHulls.setAttribute('class', 'cluster-hulls-group');

			var palette = [
				'hsl(215, 80%, 60%)',
				'hsl(150, 70%, 45%)',
				'hsl(35, 90%, 55%)',
				'hsl(280, 75%, 60%)',
				'hsl(340, 80%, 60%)',
				'hsl(180, 70%, 45%)'
			];

			communities.forEach(function (community, cIdx) {
				var points = community.map(function (n) { return { x: n.x, y: n.y }; });
				var color = palette[cIdx % palette.length];

				var pathData = '';
				if (points.length === 2) {
					pathData = 'M ' + points[0].x + ' ' + points[0].y + ' L ' + points[1].x + ' ' + points[1].y;
				} else {
					var hullPoints = AIPS.ContentIndexer.computeConvexHull(points);
					if (hullPoints.length > 0) {
						pathData = 'M ' + hullPoints.map(function (p) { return p.x + ' ' + p.y; }).join(' L ') + ' Z';
					}
				}

				if (pathData) {
					var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
					path.setAttribute('d', pathData);
					path.setAttribute('class', 'aips-graph-cluster-hull');
					path.setAttribute('fill', color);
					path.setAttribute('stroke', color);
					path.setAttribute('stroke-width', '50');
					path.setAttribute('stroke-linejoin', 'round');
					path.setAttribute('stroke-linecap', 'round');
					gHulls.appendChild(path);
				}
			});

			gViewport.appendChild(gHulls);
		},

		/**
		 * Compute the 2D convex hull of a point set using Monotone Chain algorithm.
		 *
		 * @param {Array<{x: number, y: number}>} points Set of 2D points.
		 * @return {Array<{x: number, y: number}>} Convex hull boundary vertices.
		 */
		computeConvexHull: function (points) {
			if (points.length <= 2) {
				return points;
			}

			var pts = points.slice().sort(function (a, b) {
				return a.x === b.x ? a.y - b.y : a.x - b.x;
			});

			function cross(o, a, b) {
				return (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);
			}

			var lower = [];
			for (var i = 0; i < pts.length; i++) {
				while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], pts[i]) <= 0) {
					lower.pop();
				}
				lower.push(pts[i]);
			}

			var upper = [];
			for (var j = pts.length - 1; j >= 0; j--) {
				while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], pts[j]) <= 0) {
					upper.pop();
				}
				upper.push(pts[j]);
			}

			lower.pop();
			upper.pop();
			return lower.concat(upper);
		},

		// -----------------------------------------------------------------------
		// Rate Limit Auto-Cooldown System
		// -----------------------------------------------------------------------

		/**
		 * Initialize the live countdown timer for active auto-cooldowns.
		 *
		 * @return {void}
		 */
		initCooldownTimer: function () {
			var self = this;
			var $banner = $('#aips-cooldown-banner');
			if (!$banner.length || $banner.hasClass('aips-hidden')) {
				return;
			}

			var until = parseInt($banner.attr('data-until'), 10) || 0;
			var now = Math.floor(Date.now() / 1000);

			if (until <= now) {
				self.hideCooldownBanner();
				return;
			}

			if (self.cooldownTimer) {
				clearInterval(self.cooldownTimer);
			}

			self.updateCooldownDisplay(until - now);

			self.cooldownTimer = setInterval(function () {
				var remaining = until - Math.floor(Date.now() / 1000);
				if (remaining <= 0) {
					clearInterval(self.cooldownTimer);
					self.cooldownTimer = null;
					self.hideCooldownBanner();
					if (AIPS.Utilities) {
						AIPS.Utilities.showNotice('Auto-cooldown has expired. Indexing operations are ready to resume.', 'info');
					}
				} else {
					self.updateCooldownDisplay(remaining);
				}
			}, 1000);
		},

		/**
		 * Update countdown text badge.
		 *
		 * @param {number} remaining Remaining seconds.
		 * @return {void}
		 */
		updateCooldownDisplay: function (remaining) {
			var m = Math.floor(remaining / 60);
			var s = remaining % 60;
			var str = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
			$('#aips-cooldown-countdown').text(str);
		},

		/**
		 * Show the cooldown alert banner with reason and duration.
		 *
		 * @param {Object} cooldown Cooldown status object.
		 * @return {void}
		 */
		showCooldownBanner: function (cooldown) {
			var $banner = $('#aips-cooldown-banner');
			if (!$banner.length) return;

			$banner.attr('data-until', cooldown.until || (Math.floor(Date.now() / 1000) + (cooldown.remaining_seconds || 1800)));
			if (cooldown.reason) {
				$('#aips-cooldown-reason-msg').text(cooldown.reason);
			}
			$banner.removeClass('aips-hidden').slideDown(200);
			this.initCooldownTimer();
		},

		/**
		 * Hide the cooldown alert banner.
		 *
		 * @return {void}
		 */
		hideCooldownBanner: function () {
			var $banner = $('#aips-cooldown-banner');
			if ($banner.length) {
				$banner.slideUp(200, function () {
					$banner.addClass('aips-hidden');
				});
			}
			if (this.cooldownTimer) {
				clearInterval(this.cooldownTimer);
				this.cooldownTimer = null;
			}
		},

		/**
		 * Handle manual click on Resume Now button in cooldown banner.
		 *
		 * @return {void}
		 */
		onResumeCooldownClick: function () {
			var self = this;
			var $btn = $('#aips-resume-cooldown-btn');
			$btn.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_resume_cooldown',
					nonce: aipsContentIndexerL10n.nonce
				},
				success: function (res) {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (res.success) {
						self.hideCooldownBanner();
						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice(res.data.message || 'Cooldown cleared. Operations resumed.', 'success');
						}
					} else {
						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice(res.data.message || 'Failed to clear cooldown.', 'error');
						}
					}
				},
				error: function () {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (AIPS.Utilities) {
						AIPS.Utilities.showNotice('AJAX error while clearing cooldown.', 'error');
					}
				}
			});
		},

		// -----------------------------------------------------------------------
		// Post Clusters & Content Gaps Handlers
		// -----------------------------------------------------------------------

		/**
		 * Handle cluster similarity threshold slider input.
		 *
		 * @param {jQuery.Event} e Input event.
		 * @return {void}
		 */
		onClusterSimThresholdInput: function (e) {
			var val = Math.round(parseFloat($(e.target).val()) * 100);
			$('#aips-cluster-sim-val').text(val + '%');
		},

		/**
		 * Handle scanning and building post clusters.
		 *
		 * @return {void}
		 */
		onRefreshClustersClick: function () {
			var self = this;
			var $btn = $('#aips-refresh-clusters-btn');
			var $loading = $('#aips-clusters-loading');
			var $container = $('#aips-clusters-accordion');
			var threshold = parseFloat($('#aips-cluster-sim-threshold').val()) || 0.65;
			var minSize = parseInt($('#aips-cluster-min-size').val(), 10) || 2;

			$btn.prop('disabled', true);
			$loading.removeClass('aips-hidden').show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_get_post_clusters',
					nonce: aipsContentIndexerL10n.nonce,
					threshold: threshold,
					min_size: minSize
				},
				success: function (res) {
					$btn.prop('disabled', false);
					$loading.hide();

					if (!res.success) {
						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice(res.data.message || 'Failed to generate post clusters.', 'error');
						}
						return;
					}

					var data = res.data;
					self.clustersData = data;

					// Update Summary Metrics
					$('#aips-metric-clusters-count').text(data.stats.total_clusters || 0);
					$('#aips-metric-posts-in-clusters').text(data.stats.clustered_posts || 0);
					$('#aips-metric-avg-cohesion').text((data.stats.avg_cohesion || 0) + '%');
					$('#aips-metric-orphans-count').text(data.stats.orphan_posts || 0);

					// Render Cluster Accordion Cards
					if (!data.clusters || data.clusters.length === 0) {
						$container.html(AIPS.Templates.render('aips-tmpl-indexer-cluster-empty', {
							title: 'No clusters formed at this threshold',
							description: 'Try lowering the Cluster Threshold or Min Size to group more posts together.'
						}));
					} else {
						var cardsHtml = '';
						data.clusters.forEach(function (cluster) {
							var postsHtml = '';
							var pillarId = cluster.pillar_post_id;
							var pillarTitle = '';

							(cluster.posts || []).forEach(function (p) {
								var isPillar = (p.id === pillarId);
								if (isPillar) {
									pillarTitle = p.title;
								}
								var simPct = Math.round((p.similarity_to_centroid || 0) * 100);

								postsHtml += AIPS.Templates.render('aips-tmpl-indexer-cluster-post-row', {
									clusterId: cluster.id,
									postId: p.id,
									title: AIPS.Templates.escape(p.title),
									postType: AIPS.Templates.escape(p.post_type || 'post'),
									date: AIPS.Templates.escape(p.post_date || ''),
									similarityPct: simPct,
									isPillarClass: isPillar ? 'is-pillar' : '',
									starIconClass: isPillar ? 'dashicons-star-filled' : 'dashicons-star-empty',
									starColor: isPillar ? '#f59e0b' : '#94a3b8',
									viewUrl: p.view_url || '#',
									editUrl: p.edit_url || '#'
								});
							});

							var cohesionPct = Math.round((cluster.cohesion_score || 0) * 100);
							var cohesionTier = cohesionPct >= 75 ? 'low' : (cohesionPct >= 60 ? 'medium' : 'high');
							var pillarBadgeHtml = pillarTitle ? AIPS.Templates.render('aips-tmpl-indexer-pillar-tag', {
								title: AIPS.Templates.escape(pillarTitle)
							}) : '';

							cardsHtml += AIPS.Templates.renderRaw('aips-tmpl-indexer-cluster-card', {
								id: cluster.id,
								name: AIPS.Templates.escape(cluster.name),
								postCount: cluster.post_count,
								cohesionPct: cohesionPct,
								cohesionBadgeClass: 'aips-risk-' + cohesionTier,
								pillarBadgeHtml: pillarBadgeHtml,
								postsHtml: postsHtml
							});
						});
						$container.html(cardsHtml);
					}

					// Render Orphan Posts
					var $orphansCard = $('#aips-orphans-card');
					var $orphansTbody = $('#aips-orphans-tbody');
					if (data.orphans && data.orphans.length > 0) {
						var orphanRowsHtml = '';
						data.orphans.forEach(function (orp) {
							var proxPct = Math.round((orp.closest_cluster_similarity || 0) * 100);
							orphanRowsHtml += AIPS.Templates.render('aips-tmpl-indexer-orphan-row', {
								postId: orp.id,
								title: AIPS.Templates.escape(orp.title),
								postType: AIPS.Templates.escape(orp.post_type || 'post'),
								date: AIPS.Templates.escape(orp.post_date || ''),
								closestCluster: AIPS.Templates.escape(orp.closest_cluster_name || 'None'),
								proximityPct: proxPct,
								editUrl: orp.edit_url || '#'
							});
						});
						$orphansTbody.html(orphanRowsHtml);
						$('#aips-orphans-badge').text(data.orphans.length + ' Posts');
						$orphansCard.removeClass('aips-hidden').show();
					} else {
						$orphansCard.addClass('aips-hidden').hide();
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$loading.hide();
					if (AIPS.Utilities) {
						AIPS.Utilities.showNotice('Error communicating with cluster generation service.', 'error');
					}
				}
			});
		},

		/**
		 * Handle clicking on cluster card header to toggle post accordion body.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onClusterCardHeaderClick: function (e) {
			if ($(e.target).closest('.aips-cluster-rename-btn, .aips-cluster-gaps-btn').length) {
				return;
			}
			var $header = $(e.currentTarget);
			var targetSelector = $header.data('toggle-target');
			var $body = $(targetSelector);

			$header.toggleClass('is-collapsed');
			$body.toggleClass('is-collapsed');
		},

		/**
		 * Handle renaming a post cluster.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onClusterRenameClick: function (e) {
			e.stopPropagation();
			var clusterId = $(e.currentTarget).data('cluster-id');
			var currentName = $(e.currentTarget).data('cluster-name') || '';

			var newName = prompt('Enter a new name for this Post Cluster:', currentName);
			if (!newName || newName.trim() === '' || newName.trim() === currentName) {
				return;
			}

			newName = newName.trim();
			var $btn = $(e.currentTarget);
			var $card = $btn.closest('.aips-cluster-card');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_rename_post_cluster',
					nonce: aipsContentIndexerL10n.nonce,
					cluster_id: clusterId,
					custom_name: newName
				},
				success: function (res) {
					if (res.success) {
						$card.find('.aips-cluster-title').first().text(newName);
						$btn.data('cluster-name', newName);
						$card.find('.aips-cluster-gaps-btn').data('cluster-name', newName);
						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice('Post cluster renamed successfully.', 'success');
						}
					} else {
						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice(res.data.message || 'Failed to rename cluster.', 'error');
						}
					}
				}
			});
		},

		/**
		 * Handle designating a Pillar Post for a cluster.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onPillarToggleClick: function (e) {
			var $btn = $(e.currentTarget);
			var clusterId = $btn.data('cluster-id');
			var postId = $btn.data('post-id');
			var $card = $btn.closest('.aips-cluster-card');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_save_pillar',
					nonce: aipsContentIndexerL10n.nonce,
					cluster_id: clusterId,
					pillar_post_id: postId
				},
				success: function (res) {
					if (res.success) {
						// Reset all stars in this cluster
						$card.find('.aips-pillar-toggle-btn').removeClass('is-pillar')
							.find('.dashicons').removeClass('dashicons-star-filled').addClass('dashicons-star-empty')
							.css('color', '#94a3b8');

						// Mark clicked button as pillar
						$btn.addClass('is-pillar')
							.find('.dashicons').removeClass('dashicons-star-empty').addClass('dashicons-star-filled')
							.css('color', '#f59e0b');

						// Update pillar tag in card header
						var postTitle = $btn.closest('tr').find('strong').first().text();
						var $existingTag = $card.find('.aips-pillar-tag');
						var tagHtml = AIPS.Templates.render('aips-tmpl-indexer-pillar-tag', {
							title: AIPS.Templates.escape(postTitle)
						});
						if ($existingTag.length) {
							$existingTag.replaceWith(tagHtml);
						} else {
							$card.find('.aips-cluster-header-left').append(tagHtml);
						}

						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice(res.data.message || 'Pillar post designated successfully.', 'success');
						}
					}
				}
			});
		},

		/**
		 * Open the Content Gap Suggestions modal for a cluster.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onClusterGapsClick: function (e) {
			e.stopPropagation();
			var clusterId = $(e.currentTarget).data('cluster-id');
			var clusterName = $(e.currentTarget).data('cluster-name') || '';

			this.selectedClusterForGaps = clusterId;
			$('#aips-gap-modal-cluster-name').text('"' + clusterName + '"');
			$('#aips-gap-suggestions-list').empty();
			$('#aips-gap-author-select').val('0');
			$('#aips-commit-gap-topics-btn').prop('disabled', true);
			$('#aips-gap-modal').removeClass('aips-hidden').show();
			$('#aips-gap-modal-loading').removeClass('aips-hidden').show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_generate_gap_ideas',
					nonce: aipsContentIndexerL10n.nonce,
					cluster_id: clusterId,
					num_suggestions: 5
				},
				success: function (res) {
					$('#aips-gap-modal-loading').hide();
					if (!res.success) {
						$('#aips-gap-suggestions-list').html(AIPS.Templates.render('aips-tmpl-indexer-gap-msg', {
							msgClass: 'aips-gap-msg-error',
							message: AIPS.Templates.escape(res.data.message || 'Failed to generate gap ideas.')
						}));
						return;
					}

					var suggestions = res.data.suggestions || [];
					if (suggestions.length === 0) {
						$('#aips-gap-suggestions-list').html(AIPS.Templates.render('aips-tmpl-indexer-gap-msg', {
							msgClass: 'aips-gap-msg-empty',
							message: 'No topic gaps identified for this cluster at this time.'
						}));
						return;
					}

					var listHtml = '';
					suggestions.forEach(function (item, idx) {
						listHtml += AIPS.Templates.render('aips-tmpl-indexer-gap-item', {
							index: idx,
							title: AIPS.Templates.escape(item.title || ''),
							rationale: AIPS.Templates.escape(item.rationale || '')
						});
					});
					$('#aips-gap-suggestions-list').html(listHtml);
				},
				error: function () {
					$('#aips-gap-modal-loading').hide();
					$('#aips-gap-suggestions-list').html(AIPS.Templates.render('aips-tmpl-indexer-gap-msg', {
						msgClass: 'aips-gap-msg-error',
						message: 'Error requesting AI topic ideas.'
					}));
				}
			});
		},

		/**
		 * Close the Content Gap Suggestions modal.
		 *
		 * @return {void}
		 */
		onCloseGapModalClick: function () {
			$('#aips-gap-modal').addClass('aips-hidden').hide();
			this.selectedClusterForGaps = null;
		},

		/**
		 * Handle checkbox or author select changes in the Gap Modal to update the commit button.
		 *
		 * @return {void}
		 */
		onGapSelectionChange: function () {
			var authorId = parseInt($('#aips-gap-author-select').val(), 10) || 0;
			var selectedCount = $('.aips-gap-checkbox:checked').length;
			$('#aips-commit-gap-topics-btn').prop('disabled', authorId <= 0 || selectedCount === 0);
		},

		/**
		 * Commit selected gap suggestions into Author Topics pending approval.
		 *
		 * @return {void}
		 */
		onCommitGapTopicsClick: function () {
			var self = this;
			var authorId = parseInt($('#aips-gap-author-select').val(), 10) || 0;
			var selectedTopics = $('.aips-gap-checkbox:checked').map(function () {
				return $(this).val();
			}).get();

			if (authorId <= 0 || selectedTopics.length === 0) {
				return;
			}

			var $btn = $('#aips-commit-gap-topics-btn');
			$btn.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_commit_gap_topics',
					nonce: aipsContentIndexerL10n.nonce,
					author_id: authorId,
					topics: selectedTopics
				},
				success: function (res) {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (res.success) {
						self.onCloseGapModalClick();
						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice(res.data.message || 'Topics committed successfully!', 'success');
						}
					} else {
						if (AIPS.Utilities) {
							AIPS.Utilities.showNotice(res.data.message || 'Failed to commit topics.', 'error');
						}
					}
				},
				error: function () {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (AIPS.Utilities) {
						AIPS.Utilities.showNotice('Error saving topics to author.', 'error');
					}
				}
			});
		}
	};

	// Initialize when the Content Indexer or Intelligence components are present in the DOM
	$(document).ready(function () {
		if ($('.aips-content-intelligence-tab, .aips-content-clusters-tab, .aips-content-cannibalization-tab, #aips-content-indexer-tab, #aips-graph-svg, .aips-indexer-page').length) {
			AIPS.ContentIndexer.init();
		}
	});

})(jQuery);
