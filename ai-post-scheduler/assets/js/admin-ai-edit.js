/**
 * AI Edit Modal JavaScript
 *
 * Handles the AI Edit modal functionality for regenerating individual post components.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

(function($) {
	'use strict';
	
	// Ensure AIPS object exists
	window.AIPS = window.AIPS || {};
	
	// AI Edit state
	var aiEditState = {
		postId: null,
		historyId: null,
		components: {},
		changedComponents: new Set(),
		originalValues: {},
		currentSources: {},
		nonManualSnapshots: {},
		pendingRegenerations: {},
		activeTab: 'overview'
	};
	
	// Extend AIPS with AI Edit functionality
	Object.assign(window.AIPS, {
		
		/**
		 * Initialize AI Edit functionality
		 */
		initAIEdit: function() {
			this.bindAIEditEvents();
		},
		
		/**
		 * Bind AI Edit event handlers
		 */
		bindAIEditEvents: function() {
			$(document).on('click', '.aips-ai-edit-btn', window.AIPS.openAIEditModal);
			$(document).on('click', '#aips-ai-edit-cancel, #aips-ai-edit-close', window.AIPS.closeAIEditModal);
			$(document).on('click', '.aips-regenerate-btn', window.AIPS.regenerateComponent);
			$(document).on('click', '#aips-ai-edit-regenerate-all', window.AIPS.regenerateAllComponents);
			$(document).on('click', '#aips-ai-edit-save', window.AIPS.saveAIEditChanges);
			$(document).on('click', '.aips-modal-overlay', window.AIPS.closeAIEditModal);

			// Tab and Navigation events
			$(document).on('click', '.aips-ai-edit-tab', function(e) {
				e.preventDefault();
				window.AIPS.switchTab($(this).data('tab'));
			});
			$(document).on('click', '.aips-nav-to-tab', function(e) {
				e.preventDefault();
				window.AIPS.switchTab($(this).data('target-tab'));
			});

			// Side-by-side comparison actions
			$(document).on('click', '.aips-accept-diff-btn', function(e) {
				e.preventDefault();
				window.AIPS.acceptComponentRegeneration($(this).data('component'));
			});
			$(document).on('click', '.aips-discard-diff-btn', function(e) {
				e.preventDefault();
				window.AIPS.discardComponentRegeneration($(this).data('component'));
			});
			$(document).on('click', '#aips-ai-edit-accept-all', window.AIPS.acceptAllRegenerations);
			
			// Revision viewer events
			$(document).on('click', '.aips-view-revisions-btn', window.AIPS.toggleRevisionViewer);
			$(document).on('click', '.aips-restore-revision-btn', window.AIPS.restoreRevision);
			
			// Track changes in input fields
			$(document).on('input', '.aips-component-input, .aips-component-textarea', window.AIPS.onAIEditComponentChange);
			
			// Keyboard shortcuts
			$(document).on('keydown', window.AIPS.handleAIEditKeyboard);
		},
		
		/**
		 * Open the AI Edit modal
		 */
		openAIEditModal: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			
			aiEditState.postId = $btn.data('post-id');
			aiEditState.historyId = $btn.data('history-id');
			
			$('#aips-ai-edit-modal').show();
			$('body').addClass('aips-modal-open');
			window.AIPS.loadAIEditComponents();
		},
		
		/**
		 * Load post components via AJAX
		 */
		loadAIEditComponents: function() {
			$('.aips-ai-edit-loading').show();
			$('.aips-ai-edit-content').hide();
			
			$.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_post_components',
					post_id: aiEditState.postId,
					history_id: aiEditState.historyId,
					nonce: aipsAIEditL10n.nonce
				},
				success: window.AIPS.onAIEditComponentsLoaded,
				error: window.AIPS.onAIEditLoadError
			});
		},
		
		/**
		 * Handle successful load of post components
		 */
		onAIEditComponentsLoaded: function(response) {
			if (response.success) {
				aiEditState.components = response.data.components;
				window.AIPS.populateAIEditModal(response.data);
			} else {
				window.AIPS.showAIEditNotice(response.data.message || aipsAIEditL10n.loadError, 'error');
				window.AIPS.closeAIEditModal();
			}
		},
		
		/**
		 * Handle load error
		 */
		onAIEditLoadError: function() {
			window.AIPS.showAIEditNotice(aipsAIEditL10n.loadError, 'error');
			window.AIPS.closeAIEditModal();
		},
		
		/**
		 * Switch active tab in AI Edit modal
		 */
		switchTab: function(tabName) {
			aiEditState.activeTab = tabName;
			$('.aips-ai-edit-tab').removeClass('is-active').attr('aria-selected', 'false');
			$('.aips-ai-edit-tab[data-tab="' + tabName + '"]').addClass('is-active').attr('aria-selected', 'true');
			
			$('.aips-ai-edit-tab-panel').removeClass('is-active');
			$('.aips-ai-edit-tab-panel[data-panel="' + tabName + '"]').addClass('is-active');
		},

		/**
		 * Update status badges across tabs and overview summary cards
		 */
		updateTabBadges: function() {
			var components = ['title', 'slug', 'excerpt', 'content', 'featured_image'];
			components.forEach(function(comp) {
				var $badge = $('.aips-tab-badge[data-badge="' + comp + '"]');
				var $summaryStatus = $('#aips-summary-status-' + comp);
				
				if (aiEditState.pendingRegenerations && aiEditState.pendingRegenerations[comp] !== undefined) {
					$badge.removeClass('badge-modified').addClass('badge-regenerated').text(aipsAIEditL10n.regenerated || 'Regenerated').show();
					if ($summaryStatus.length) {
						$summaryStatus.addClass('has-changes').text(aipsAIEditL10n.regenerated || 'Regenerated');
					}
				} else if (aiEditState.changedComponents.has(comp)) {
					$badge.removeClass('badge-regenerated').addClass('badge-modified').text(aipsAIEditL10n.modified || 'Modified').show();
					if ($summaryStatus.length) {
						$summaryStatus.addClass('has-changes').text(aipsAIEditL10n.modified || 'Modified');
					}
				} else {
					$badge.hide().removeClass('badge-regenerated badge-modified').text('');
					if ($summaryStatus.length) {
						$summaryStatus.removeClass('has-changes').text('Ready');
					}
				}
			});
		},

		/**
		 * Populate modal with post data
		 */
		populateAIEditModal: function(data) {
			if (data.history_id) {
				aiEditState.historyId = data.history_id;
			}

			// Populate context info
			$('#aips-context-template').text((data.context && data.context.template_name) ? data.context.template_name : '—');
			$('#aips-context-author').text((data.context && data.context.author_name) ? data.context.author_name : '—');
			$('#aips-context-topic').text((data.context && data.context.topic_title) ? data.context.topic_title : '—');
			
			// Subtitle with post title
			var postTitle = (data.components && data.components.title && data.components.title.value) ? data.components.title.value : '';
			$('#aips-ai-edit-post-subtitle').text(postTitle ? '“' + postTitle + '”' : '');

			// Reset tabs and badges
			window.AIPS.switchTab('overview');
			window.AIPS.updateTabBadges();
			
			// Populate components and store original values
			$('#aips-component-title').val(data.components.title.value);
			aiEditState.originalValues.title = data.components.title.value;
			window.AIPS.setAIEditComponentSource('title', data.components.title.value, 'persisted');

			$('#aips-component-slug').val(data.components.slug.value);
			aiEditState.originalValues.slug = data.components.slug.value;
			window.AIPS.setAIEditComponentSource('slug', data.components.slug.value, 'persisted');
			
			$('#aips-component-excerpt').val(data.components.excerpt.value);
			aiEditState.originalValues.excerpt = data.components.excerpt.value;
			window.AIPS.setAIEditComponentSource('excerpt', data.components.excerpt.value, 'persisted');
			
			$('#aips-component-content').val(data.components.content.value);
			aiEditState.originalValues.content = data.components.content.value;
			window.AIPS.setAIEditComponentSource('content', data.components.content.value, 'persisted');
			aiEditState.originalValues.featured_image = data.components.featured_image;
			window.AIPS.setAIEditComponentSource('featured_image', data.components.featured_image, 'persisted');
			
			if (data.components.featured_image.url) {
				$('#aips-component-image').attr('src', data.components.featured_image.url).show();
				$('#aips-component-image-none').hide();
			} else {
				$('#aips-component-image').hide();
				$('#aips-component-image-none').show();
			}
			
			// Update character counts
			window.AIPS.updateAIEditCharCount('title');
			window.AIPS.updateAIEditCharCount('slug');
			window.AIPS.updateAIEditCharCount('excerpt');
			window.AIPS.updateAIEditCharCount('content');
			
			// Show content, hide loading
			$('.aips-ai-edit-loading').hide();
			$('.aips-ai-edit-content').show();
		},
		
		/**
		 * Regenerate a single component
		 */
		regenerateComponent: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var component = $btn.data('component');
			var currentValue = window.AIPS.getAIEditCurrentComponentValue(component);
			var currentSource = aiEditState.currentSources[component] || 'persisted';
			var currentReason = window.AIPS.shouldCaptureManualRevision(component) ? 'pre_regenerate_manual' : 'pre_regenerate_current';

			var requestData = {
				action: 'aips_regenerate_component',
				post_id: aiEditState.postId,
				history_id: aiEditState.historyId,
				component: component,
				current_value: currentValue,
				current_source: currentSource,
				current_reason: currentReason,
				nonce: aipsAIEditL10n.nonce
			};

			var req = $.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: requestData,
				success: function(response) {
					window.AIPS.onComponentRegenerated($btn, component, response);
				},
				error: function() {
					window.AIPS.onRegenerateError($btn, component);
				}
			});

			window.AIPS.Utilities.withLock($btn, req, {
				loadingText: aipsAIEditL10n.regenerating,
				timeout: 120000
			});
		},

		/**
		 * Build a map of manually edited component values that should be snapshotted.
		 */
		getAIEditManualSnapshots: function(components) {
			var snapshots = {};

			components.forEach(function(component) {
				if (window.AIPS.shouldCaptureManualRevision(component)) {
					snapshots[component] = window.AIPS.getAIEditCurrentComponentValue(component);
				}
			});

			return snapshots;
		},

		/**
		 * Build a map of current component values that should be snapshotted.
		 */
		getAIEditCurrentSnapshots: function(components) {
			var snapshots = {};

			components.forEach(function(component) {
				var val = window.AIPS.getAIEditCurrentComponentValue(component);
				if (val !== null && val !== '') {
					snapshots[component] = val;
				}
			});

			return snapshots;
		},

		/**
		 * Regenerate all supported components in one request.
		 */
		regenerateAllComponents: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var components = ['title', 'slug', 'excerpt', 'content', 'featured_image'];
			var currentSnapshots = window.AIPS.getAIEditCurrentSnapshots(components);
			var manualSnapshots = window.AIPS.getAIEditManualSnapshots(components);

			$('.aips-regenerate-btn').prop('disabled', true);

			var req = $.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_regenerate_all_components',
					post_id: aiEditState.postId,
					history_id: aiEditState.historyId,
					current_snapshots: currentSnapshots,
					manual_snapshots: manualSnapshots,
					nonce: aipsAIEditL10n.nonce
				},
				success: function(response) {
					window.AIPS.onRegenerateAllSuccess($button, response);
				},
				error: function() {
					window.AIPS.onRegenerateAllError($button);
				}
			});

			window.AIPS.Utilities.withLock($button, req, {
				loadingText: aipsAIEditL10n.regeneratingAll,
				timeout: 300000
			});
		},

		/**
		 * Handle Regenerate All success/error payload.
		 */
		onRegenerateAllSuccess: function($button, response) {
			$('.aips-regenerate-btn').prop('disabled', false);

			if (!response.success) {
				window.AIPS.showAIEditNotice(response.data && response.data.message ? response.data.message : aipsAIEditL10n.regenerateAllError, 'error');
				return;
			}

			var regenerated = response.data.regenerated || {};
			var skipped = response.data.skipped || {};
			var errors = response.data.errors || {};
			var regeneratedCount = 0;

			Object.keys(regenerated).forEach(function(component) {
				aiEditState.pendingRegenerations[component] = regenerated[component];
				window.AIPS.showComponentDiff(component, regenerated[component]);
				window.AIPS.showComponentStatus(component, 'success', aipsAIEditL10n.regenerateSuccess);
				window.AIPS.refreshComponentRevisions(component);
				regeneratedCount += 1;
			});

			Object.keys(skipped).forEach(function(component) {
				window.AIPS.showComponentStatus(component, 'info', skipped[component]);
			});

			Object.keys(errors).forEach(function(component) {
				window.AIPS.showComponentStatus(component, 'error', errors[component]);
			});

			window.AIPS.updateTabBadges();

			if (regeneratedCount > 0) {
				$('#aips-ai-edit-accept-all').show();
				window.AIPS.showAIEditNotice(response.data.message || aipsAIEditL10n.regenerateAllSuccess, 'success');
				return;
			}

			window.AIPS.showAIEditNotice(response.data.message || aipsAIEditL10n.regenerateAllError, 'error');
		},

		/**
		 * Handle Regenerate All network error.
		 */
		onRegenerateAllError: function($button) {
			$('.aips-regenerate-btn').prop('disabled', false);
			window.AIPS.showAIEditNotice(aipsAIEditL10n.regenerateAllError, 'error');
		},
		
		/**
		 * Handle successful component regeneration
		 */
		onComponentRegenerated: function($btn, component, response) {
			// Re-enable button
			$btn.prop('disabled', false)
				.removeClass('regenerating')
				.find('.button-text').text(aipsAIEditL10n.regenerate);
			
			if (response.success) {
				aiEditState.pendingRegenerations[component] = response.data.new_value;
				window.AIPS.showComponentDiff(component, response.data.new_value);
				window.AIPS.updateTabBadges();
				$('#aips-ai-edit-accept-all').show();
				window.AIPS.showComponentStatus(component, 'success', aipsAIEditL10n.regenerateSuccess);
				window.AIPS.refreshComponentRevisions(component);
			} else {
				window.AIPS.showComponentStatus(component, 'error', response.data.message || aipsAIEditL10n.regenerateError);
			}
		},

		/**
		 * Render side-by-side comparison for a component
		 */
		showComponentDiff: function(component, newValue) {
			var $wrapper = $('.aips-diff-wrapper[data-component="' + component + '"]');
			if (!$wrapper.length) {
				return;
			}
			
			var currentVal = window.AIPS.getAIEditCurrentComponentValue(component);
			
			if (component === 'featured_image') {
				var curUrl = (currentVal && currentVal.url) ? currentVal.url : '';
				var newUrl = (newValue && newValue.url) ? newValue.url : '';
				
				var curHtml = curUrl ? '<img src="' + curUrl + '" alt="Current" style="max-width:100%; height:auto; max-height:160px; border-radius:4px;" />' : '<em>' + (aipsAIEditL10n.noImage || 'No featured image') + '</em>';
				var newHtml = newUrl ? '<img src="' + newUrl + '" alt="New draft" style="max-width:100%; height:auto; max-height:160px; border-radius:4px;" />' : '<em>' + (aipsAIEditL10n.noImage || 'No featured image') + '</em>';
				
				$('#aips-diff-current-' + component).html(curHtml);
				$('#aips-diff-regenerated-' + component).html(newHtml);
			} else {
				$('#aips-diff-current-' + component).text(currentVal || '(Empty)');
				$('#aips-diff-regenerated-' + component).text(newValue || '(Empty)');
			}
			
			$wrapper.slideDown(200);
		},

		/**
		 * Accept and replace a single component regeneration
		 */
		acceptComponentRegeneration: function(component) {
			if (!aiEditState.pendingRegenerations || aiEditState.pendingRegenerations[component] === undefined) {
				return;
			}
			var val = aiEditState.pendingRegenerations[component];
			delete aiEditState.pendingRegenerations[component];
			
			window.AIPS.updateAIEditComponentValue(component, val, 'ai_generated');
			aiEditState.changedComponents.add(component);
			
			$('[data-component="' + component + '"]').closest('.aips-component-section').addClass('changed');
			$('.aips-diff-wrapper[data-component="' + component + '"]').slideUp(150);
			window.AIPS.refreshComponentRevisions(component);
			window.AIPS.updateTabBadges();
			
			if (Object.keys(aiEditState.pendingRegenerations).length === 0) {
				$('#aips-ai-edit-accept-all').hide();
			}
		},

		/**
		 * Discard a pending component regeneration
		 */
		discardComponentRegeneration: function(component) {
			if (aiEditState.pendingRegenerations) {
				delete aiEditState.pendingRegenerations[component];
			}
			$('.aips-diff-wrapper[data-component="' + component + '"]').slideUp(150);
			window.AIPS.updateTabBadges();
			
			if (Object.keys(aiEditState.pendingRegenerations || {}).length === 0) {
				$('#aips-ai-edit-accept-all').hide();
			}
		},

		/**
		 * Accept all pending regenerations at once
		 */
		acceptAllRegenerations: function(e) {
			if (e) {
				e.preventDefault();
			}
			var components = Object.keys(aiEditState.pendingRegenerations || {});
			components.forEach(function(comp) {
				window.AIPS.acceptComponentRegeneration(comp);
			});
			$('#aips-ai-edit-accept-all').hide();
			window.AIPS.showAIEditNotice(aipsAIEditL10n.stagedForSave || 'All regenerations accepted. Click Save Changes to apply.', 'info');
		},

		/**
		 * Track the current non-manual source/value for a component.
		 */
		setAIEditComponentSource: function(component, value, source) {
			var normalizedValue = window.AIPS.normalizeAIEditComponentValue(component, value);
			aiEditState.currentSources[component] = source;
			aiEditState.nonManualSnapshots[component] = {
				source: source,
				value: normalizedValue
			};
		},

		/**
		 * Normalize a component value for reliable comparisons.
		 */
		normalizeAIEditComponentValue: function(component, value) {
			if (component === 'featured_image') {
				if (!value) {
					return '';
				}
				return JSON.stringify({
					attachment_id: value.attachment_id || 0,
					url: value.url || ''
				});
			}

			return String(value || '');
		},

		/**
		 * Return true when the current component state is a manual edit that would be lost.
		 */
		shouldCaptureManualRevision: function(component) {
			return aiEditState.currentSources[component] === 'manual_edit' && aiEditState.changedComponents.has(component);
		},

		/**
		 * Get the current component value from the modal.
		 */
		getAIEditCurrentComponentValue: function(component) {
			switch (component) {
				case 'title':
					return $('#aips-component-title').val();
				case 'slug':
					return $('#aips-component-slug').val();
				case 'excerpt':
					return $('#aips-component-excerpt').val();
				case 'content':
					return $('#aips-component-content').val();
				case 'featured_image':
					return aiEditState.components.featured_image || null;
				default:
					return null;
			}
		},

		/**
		 * Refresh revisions for a component if its revision panel is currently open
		 */
		refreshComponentRevisions: function(component) {
			var $section = $('.aips-component-section[data-component="' + component + '"]');
			var $revisions = $section.find('.aips-component-revisions');

			if (!$revisions.length) {
				return;
			}

			$revisions.data('loaded', false);

			if ($revisions.is(':visible')) {
				window.AIPS.loadComponentRevisions(component, $section);
			}
		},
		
		/**
		 * Handle regeneration error
		 */
		onRegenerateError: function($btn, component) {
			// Re-enable button
			$btn.prop('disabled', false)
				.removeClass('regenerating')
				.find('.button-text').text(aipsAIEditL10n.regenerate);
			
			window.AIPS.showComponentStatus(component, 'error', aipsAIEditL10n.regenerateError);
		},
		
		/**
		 * Update component value in UI
		 */
		updateAIEditComponentValue: function(component, value, source) {
			switch(component) {
				case 'title':
					$('#aips-component-title').val(value);
					window.AIPS.updateAIEditCharCount('title');
					break;
				case 'slug':
					$('#aips-component-slug').val(value);
					window.AIPS.updateAIEditCharCount('slug');
					break;
				case 'excerpt':
					$('#aips-component-excerpt').val(value);
					window.AIPS.updateAIEditCharCount('excerpt');
					break;
				case 'content':
					$('#aips-component-content').val(value);
					window.AIPS.updateAIEditCharCount('content');
					break;
				case 'featured_image':
					if (value && value.url) {
						$('#aips-component-image').attr('src', value.url).show();
						$('#aips-component-image-none').hide();
						aiEditState.components.featured_image = value;
					} else {
						$('#aips-component-image').attr('src', '').hide();
						$('#aips-component-image-none').show();
						aiEditState.components.featured_image = null;
					}
					break;
			}

			if (source) {
				window.AIPS.setAIEditComponentSource(component, value, source);
			}
		},
		
		/**
		 * Show component status message
		 */
		showComponentStatus: function(component, type, message) {
			var $section = $('[data-component="' + component + '"]').closest('.aips-component-section');
			var $status = $section.find('.aips-component-status');
			
			$status.removeClass('success error')
				.addClass(type)
				.text(message)
				.show();
			
			setTimeout(function() {
				$status.fadeOut();
			}, 3000);
		},
		
		/**
		 * Handle manual changes to component inputs
		 */
		onAIEditComponentChange: function(e) {
			var $input = $(e.currentTarget);
			var component = $input.closest('.aips-component-section').data('component');
			var currentValue = $input.val();
			var originalValue = aiEditState.originalValues[component];
			var normalizedCurrent = window.AIPS.normalizeAIEditComponentValue(component, currentValue);
			var normalizedOriginal = window.AIPS.normalizeAIEditComponentValue(component, originalValue);
			var snapshot = aiEditState.nonManualSnapshots[component];
			
			// Update character count
			window.AIPS.updateAIEditCharCount(component);
			
			// Track if changed from original
			if (normalizedCurrent !== normalizedOriginal) {
				aiEditState.changedComponents.add(component);
				$input.closest('.aips-component-section').addClass('changed');
			} else {
				aiEditState.changedComponents.delete(component);
				$input.closest('.aips-component-section').removeClass('changed');
			}

			if (snapshot && normalizedCurrent === snapshot.value) {
				aiEditState.currentSources[component] = snapshot.source;
			} else if (normalizedCurrent !== normalizedOriginal) {
				aiEditState.currentSources[component] = 'manual_edit';
			} else {
				aiEditState.currentSources[component] = 'persisted';
			}

			window.AIPS.updateTabBadges();
		},
		
		/**
		 * Update character count display
		 */
		updateAIEditCharCount: function(component) {
			var $input = $('#aips-component-' + component);
			var $count = $input.siblings('.aips-component-meta').find('.aips-char-count');
			
			if ($count.length) {
				var charCount = $input.val().length;
				$count.text(charCount + ' characters');
			}
		},
		
		/**
		 * Save all changed components
		 */
		saveAIEditChanges: function(e) {
			e.preventDefault();
			
			if (aiEditState.changedComponents.size === 0) {
				window.AIPS.showAIEditNotice(aipsAIEditL10n.noChanges, 'info');
				return;
			}
			
			var components = {};
			
			aiEditState.changedComponents.forEach(function(component) {
				switch(component) {
					case 'title':
						components.title = $('#aips-component-title').val();
						break;
					case 'slug':
						components.slug = $('#aips-component-slug').val();
						break;
					case 'excerpt':
						components.excerpt = $('#aips-component-excerpt').val();
						break;
					case 'content':
						components.content = $('#aips-component-content').val();
						break;
					case 'featured_image':
						if (aiEditState.components.featured_image) {
							components.featured_image_id = aiEditState.components.featured_image.attachment_id;
						}
						break;
				}
			});
			
			var $saveBtn = $('#aips-ai-edit-save');
			
			var req = $.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_save_post_components',
					post_id: aiEditState.postId,
					components: components,
					nonce: aipsAIEditL10n.nonce
				},
				success: window.AIPS.onAIEditSaveSuccess,
				error: window.AIPS.onAIEditSaveError
			});

			window.AIPS.Utilities.withLock($saveBtn, req, {
				loadingText: aipsAIEditL10n.saving,
				timeout: 30000
			});
		},
		
		/**
		 * Handle successful save
		 */
		onAIEditSaveSuccess: function(response) {
			if (response.success) {
				var msg = (response.data && response.data.message) ? response.data.message : (aipsAIEditL10n.saveSuccess || 'Post updated successfully!');
				if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.showToast === 'function') {
					window.AIPS.Utilities.showToast(msg, 'success');
				} else {
					window.AIPS.showAIEditNotice(msg, 'success');
				}
				
				// Update host row in DOM in-place without page reload
				window.AIPS.updateHostRowInPlace(response.data);

				// Close modal without unsaved-changes prompt
				window.AIPS.closeAIEditModal(null, { skipConfirm: true });
			} else {
				window.AIPS.showAIEditNotice(response.data.message || aipsAIEditL10n.saveError, 'error');
			}
		},

		/**
		 * Dynamically update host page row attributes and titles without full page reload.
		 */
		updateHostRowInPlace: function(data) {
			if (!data || !data.post_id) {
				return;
			}
			var postId = data.post_id;
			var newTitle = data.title;

			// 1. Core WP Posts list table row: tr#post-123
			var $coreRow = $('#post-' + postId);
			if ($coreRow.length) {
				if (newTitle) {
					$coreRow.find('a.row-title').first().text(newTitle);
					$coreRow.find('.post_title strong').first().text(newTitle);
				}
				$coreRow.addClass('aips-row-updated-flash');
				setTimeout(function() { $coreRow.removeClass('aips-row-updated-flash'); }, 3000);
			}

			// 2. AIPS table rows: tr[data-post-id="123"]
			var $aipsRows = $('tr[data-post-id="' + postId + '"]');
			if ($aipsRows.length) {
				if (newTitle) {
					$aipsRows.find('a.cell-primary').first().text(newTitle);
					$aipsRows.find('.aips-history-title').first().text(newTitle);
				}
				$aipsRows.addClass('aips-row-updated-flash');
				setTimeout(function() { $aipsRows.removeClass('aips-row-updated-flash'); }, 3000);
			}

			// 3. Fallback for any row containing a button for this post id
			var $btnRows = $('.aips-ai-edit-btn[data-post-id="' + postId + '"]').closest('tr');
			if ($btnRows.length) {
				if (newTitle) {
					$btnRows.find('a.cell-primary, a.row-title, .aips-history-title').first().text(newTitle);
				}
				$btnRows.addClass('aips-row-updated-flash');
				setTimeout(function() { $btnRows.removeClass('aips-row-updated-flash'); }, 3000);
			}
		},
		
		/**
		 * Handle save error
		 */
		onAIEditSaveError: function() {
			window.AIPS.Utilities.resetButton($('#aips-ai-edit-save'));
			window.AIPS.showAIEditNotice(aipsAIEditL10n.saveError, 'error');
		},
		
		/**
		 * Close the modal
		 */
		closeAIEditModal: function(e, options) {
			options = options || {};
			// Allow closing via overlay, cancel button, or close button
			if (e) {
				var $target = $(e.target);
				var isOverlay = $target.hasClass('aips-modal-overlay');
				// Check for both cancel and close buttons, including child elements
				var isCloseButton = $target.is('#aips-ai-edit-cancel, #aips-ai-edit-close') || 
				                   $target.closest('#aips-ai-edit-close, #aips-ai-edit-cancel').length > 0;
				
				if (!isOverlay && !isCloseButton) {
					return;
				}
			}
			
			if (!options.skipConfirm && aiEditState.changedComponents.size > 0) {
				AIPS.Utilities.confirm(aipsAIEditL10n.confirmClose, 'Notice', [
					{ label: 'No, keep editing', className: 'aips-btn aips-btn-secondary' },
					{ label: 'Yes, discard changes', className: 'aips-btn aips-btn-danger-solid', action: function() {
						$('#aips-ai-edit-modal').hide();
						$('body').removeClass('aips-modal-open');
						window.AIPS.resetAIEditState();
					}}
				]);
				if (e) e.stopPropagation();
				return;
			}

			$('#aips-ai-edit-modal').hide();
			$('body').removeClass('aips-modal-open');
			window.AIPS.resetAIEditState();
		},
		
		/**
		 * Reset modal state
		 */
		resetAIEditState: function() {
			aiEditState.postId = null;
			aiEditState.historyId = null;
			aiEditState.components = {};
			aiEditState.changedComponents = new Set();
			aiEditState.originalValues = {};
			aiEditState.currentSources = {};
			aiEditState.nonManualSnapshots = {};
			aiEditState.pendingRegenerations = {};
			aiEditState.activeTab = 'overview';
			
			// Clear all inputs
			$('#aips-component-title').val('');
			$('#aips-component-slug').val('');
			$('#aips-component-excerpt').val('');
			$('#aips-component-content').val('');
			$('#aips-component-image').attr('src', '').hide();
			$('#aips-component-image-none').show();
			$('#aips-ai-edit-post-subtitle').text('');

			// Hide diff views and reset buttons
			$('.aips-diff-wrapper').hide();
			$('#aips-ai-edit-accept-all').hide();
			
			// Reset tabs to overview
			window.AIPS.switchTab('overview');
			window.AIPS.updateTabBadges();
			
			// Remove changed class
			$('.aips-component-section').removeClass('changed');
			
			// Hide statuses
			$('.aips-component-status').hide();
			
			$('.aips-ai-edit-loading').show();
			$('.aips-ai-edit-content').hide();
		},
		
		/**
		 * Show admin notice
		 */
		showAIEditNotice: function(message, type) {
			type = type || 'info';

			var $p = $('<p>').text(message);
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible">').append($p);
			$('.wrap').first().prepend($notice);
			
			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},
		
		/**
		 * Handle keyboard shortcuts
		 */
		handleAIEditKeyboard: function(e) {
			// Only handle if modal is open
			if (!$('#aips-ai-edit-modal').is(':visible')) {
				return;
			}
			
			// ESC key - close modal
			if (e.keyCode === 27) {
				window.AIPS.closeAIEditModal();
			}
			
			// Ctrl/Cmd + S - save changes
			if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
				e.preventDefault();
				window.AIPS.saveAIEditChanges(e);
			}
		},
		
		/**
		 * Toggle revision viewer
		 */
		toggleRevisionViewer: function(e) {
			e.preventDefault();
			var $button = $(e.currentTarget);
			var componentType = $button.data('component');
			var $section = $button.closest('.aips-component-section');
			var $revisions = $section.find('.aips-component-revisions');
			
			// Toggle visibility
			if ($revisions.is(':visible')) {
				$revisions.slideUp(200);
				$button.removeClass('active');
			} else {
				$revisions.slideDown(200);
				$button.addClass('active');
				
				// Load revisions if not loaded yet
				if (!$revisions.data('loaded')) {
					window.AIPS.loadComponentRevisions(componentType, $section);
				}
			}
		},
		
		/**
		 * Load component revisions via AJAX
		 */
		loadComponentRevisions: function(componentType, $section) {
			var $revisions = $section.find('.aips-component-revisions');
			var $loading = $revisions.find('.aips-revisions-loading');
			var $list = $revisions.find('.aips-revisions-list');
			var $empty = $revisions.find('.aips-revisions-empty');
			var $button = $section.find('.aips-view-revisions-btn');
			
			// Show loading
			$loading.show();
			$list.empty().hide();
			$empty.hide();
			
			// Make AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_component_revisions',
					nonce: aipsAIEditL10n.nonce,
					post_id: aiEditState.postId,
					component: componentType,
					history_id: aiEditState.historyId
				},
				success: function(response) {
					$loading.hide();
					$revisions.data('loaded', true);
					
					if (response.success && response.data.revisions && response.data.revisions.length > 0) {
						var revisions = response.data.revisions;
						
						// Update count badge
						var $count = $button.find('.revision-count');
						if ($count.length === 0) {
							$count = $('<span class="revision-count"></span>');
							$button.find('.button-text').after($count);
						}
						$count.text('(' + revisions.length + ')');
						
						// Render revisions
						revisions.forEach(function(revision) {
							var $item = window.AIPS.renderRevisionItem(revision, componentType);
							$list.append($item);
						});
						
						$list.show();
					} else {
						$empty.show();
					}
				},
				error: function(xhr, status, error) {
					$loading.hide();
					console.error('Failed to load revisions:', error);
					$list.html('<div class="notice notice-error inline"><p>Failed to load revisions. Please try again.</p></div>').show();
				}
			});
		},
		
		/**
		 * Render a revision item
		 */
		renderRevisionItem: function(revision, componentType) {
			var $item = $('<div class="aips-revision-item"></div>');
			$item.data('revision-id', revision.id);
			var timestamp = revision.timestamp || '';
			var revisionLabel = window.AIPS.getAIEditRevisionLabel(revision);
			
			// Content section
			var $content = $('<div class="aips-revision-content"></div>');
			
			// Meta information
			var $meta = $('<div class="aips-revision-meta"></div>');
			$meta.append('<span class="dashicons dashicons-backup"></span>');
			$meta.append('<span class="aips-revision-source">' + window.AIPS.Utilities.escapeHtml(revisionLabel) + '</span>');
			$meta.append('<span class="aips-revision-timestamp">' + window.AIPS.Utilities.escapeHtml(timestamp) + '</span>');
			$content.append($meta);
			
			// Value preview
			var $value = $('<div class="aips-revision-value aips-revision-value-' + componentType + '"></div>');
			
			if (componentType === 'featured_image') {
				if (revision.value && revision.value.url) {
					var sanitizedImageUrl = window.AIPS.Utilities.sanitizeUrl(revision.value.url);
					
					if (sanitizedImageUrl) {
						$value.empty().append(
							$('<img>', {
								src: sanitizedImageUrl,
								alt: 'Revision',
								'class': 'aips-revision-value-image'
							})
						);
					} else {
						$value.text('No image');
					}
				} else {
					$value.text('No image');
				}
			} else {
				$value.text(revision.value || '(empty)');
			}
			
			$content.append($value);
			$item.append($content);
			
			// Actions section
			var $actions = $('<div class="aips-revision-actions"></div>');
			var $restoreBtn = $('<button type="button" class="aips-restore-revision-btn" data-revision-id="' + revision.id + '" data-component="' + componentType + '"></button>');
			$restoreBtn.append('<span class="dashicons dashicons-undo"></span>');
			$restoreBtn.append('<span>Restore</span>');
			$actions.append($restoreBtn);
			$item.append($actions);
			
			return $item;
		},
		
		/**
		 * Restore a revision
		 */
		restoreRevision: function(e) {
			e.preventDefault();
			var $button = $(e.currentTarget);
			var revisionId = $button.data('revision-id');
			var componentType = $button.data('component');
			var $item = $button.closest('.aips-revision-item');
			
			$item.addClass('restoring');
			
			// Make AJAX request
			var req = $.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_restore_component_revision',
					nonce: aipsAIEditL10n.nonce,
					post_id: aiEditState.postId,
					revision_id: revisionId,
					component: componentType,
					current_value: window.AIPS.shouldCaptureManualRevision(componentType) ? window.AIPS.getAIEditCurrentComponentValue(componentType) : '',
					current_source: window.AIPS.shouldCaptureManualRevision(componentType) ? 'manual_edit' : '',
					current_reason: window.AIPS.shouldCaptureManualRevision(componentType) ? 'pre_restore_manual' : ''
				},
				success: function(response) {
					$item.removeClass('restoring');
					
					if (response.success && response.data.value) {
						// Update the component input with restored value
						window.AIPS.updateAIEditComponentValue(componentType, response.data.value, 'restored_revision');
						aiEditState.changedComponents.add(componentType);
						$item.closest('.aips-component-section').addClass('changed');
						window.AIPS.showComponentStatus(componentType, 'success', response.data.message || 'Revision restored successfully!');
						
						// Close revision panel
						var $section = $item.closest('.aips-component-section');
						$section.find('.aips-view-revisions-btn').click();
					} else {
						window.AIPS.showComponentStatus(componentType, 'error', response.data && response.data.message ? response.data.message : 'Failed to restore revision');
					}
				},
				error: function(xhr, status, error) {
					$item.removeClass('restoring');
					console.error('Failed to restore revision:', error);
					window.AIPS.showComponentStatus(componentType, 'error', 'Failed to restore revision. Please try again.');
				}
			});

			window.AIPS.Utilities.withLock($button, req, {
				timeout: 30000
			});
		},

		/**
		 * Resolve a user-facing label for a revision source.
		 */
		getAIEditRevisionLabel: function(revision) {
			var source = revision.source || (revision.context && revision.context.source) || 'ai_generated';

			switch (source) {
				case 'manual_edit':
					return aipsAIEditL10n.revisionManualEdit;
				case 'restored_revision':
					return aipsAIEditL10n.revisionRestored;
				case 'original_version':
				case 'persisted':
				case 'current_version':
					return aipsAIEditL10n.revisionOriginal || aipsAIEditL10n.currentVersion || 'Original Version';
				case 'ai_generated':
				default:
					return aipsAIEditL10n.revisionAiGenerated || aipsAIEditL10n.revisionUnknown;
			}
		},
		
		/**
		 * Update component value after restore
		 */
		updateComponentValue: function(componentType, value) {
			var $input;
			
			switch (componentType) {
				case 'title':
					$input = $('#aips-component-title');
					$input.val(value);
					break;
				case 'slug':
					$input = $('#aips-component-slug');
					$input.val(value);
					break;
				case 'excerpt':
					$input = $('#aips-component-excerpt');
					$input.val(value);
					break;
				case 'content':
					$input = $('#aips-component-content');
					$input.val(value);
					break;
				case 'featured_image':
					if (value && value.url) {
						$('#aips-component-image').attr('src', value.url).show();
						$('#aips-component-image-none').hide();
						aiEditState.components.featured_image = value.id;
					}
					break;
			}
			
			// Trigger input event to update character count
			if ($input && $input.length) {
				$input.trigger('input');
			}
		},
		
	});
	
	// Initialize when document is ready
	$(document).ready(function() {
		window.AIPS.initAIEdit();
	});
	
})(jQuery);
