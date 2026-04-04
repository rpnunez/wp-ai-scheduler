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
		nonManualSnapshots: {}
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
		 * Populate modal with post data
		 */
		populateAIEditModal: function(data) {
			// Populate context info
			$('#aips-context-template').text(data.context.template_name);
			$('#aips-context-author').text(data.context.author_name);
			$('#aips-context-topic').text(data.context.topic_title);
			
			// Populate components and store original values
			$('#aips-component-title').val(data.components.title.value);
			aiEditState.originalValues.title = data.components.title.value;
			window.AIPS.setAIEditComponentSource('title', data.components.title.value, 'persisted');
			
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
			var requestData = {
				action: 'aips_regenerate_component',
				post_id: aiEditState.postId,
				history_id: aiEditState.historyId,
				component: component,
				nonce: aipsAIEditL10n.nonce
			};

			if (window.AIPS.shouldCaptureManualRevision(component)) {
				requestData.current_value = window.AIPS.getAIEditCurrentComponentValue(component);
				requestData.current_source = 'manual_edit';
				requestData.current_reason = 'pre_regenerate_manual';
			}
			
			// Disable button and show loading state
			$btn.prop('disabled', true)
				.addClass('regenerating')
				.find('.button-text').text(aipsAIEditL10n.regenerating);
			
			$.ajax({
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
		 * Regenerate all supported components in one request.
		 */
		regenerateAllComponents: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var manualSnapshots = window.AIPS.getAIEditManualSnapshots(['title', 'excerpt', 'content', 'featured_image']);

			window.AIPS.Utilities.setButtonLoading($button, aipsAIEditL10n.regeneratingAll);
			$('.aips-regenerate-btn').prop('disabled', true);

			$.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_regenerate_all_components',
					post_id: aiEditState.postId,
					history_id: aiEditState.historyId,
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
		},

		/**
		 * Handle Regenerate All success/error payload.
		 */
		onRegenerateAllSuccess: function($button, response) {
			window.AIPS.Utilities.resetButton($button);
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
				window.AIPS.updateAIEditComponentValue(component, regenerated[component], 'ai_generated');
				aiEditState.changedComponents.add(component);
				$('.aips-component-section[data-component="' + component + '"]').addClass('changed');
				window.AIPS.showComponentStatus(component, 'success', aipsAIEditL10n.regenerateSuccess);
				window.AIPS.refreshComponentRevisions(component);
				regeneratedCount += 1;
			});

			Object.keys(skipped).forEach(function(component) {
				window.AIPS.showComponentStatus(component, 'success', skipped[component]);
			});

			Object.keys(errors).forEach(function(component) {
				window.AIPS.showComponentStatus(component, 'error', errors[component]);
			});

			if (regeneratedCount > 0) {
				window.AIPS.showAIEditNotice(response.data.message || aipsAIEditL10n.regenerateAllSuccess, 'success');
				return;
			}

			window.AIPS.showAIEditNotice(response.data.message || aipsAIEditL10n.regenerateAllError, 'error');
		},

		/**
		 * Handle Regenerate All network error.
		 */
		onRegenerateAllError: function($button) {
			window.AIPS.Utilities.resetButton($button);
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
				// Update component value
				window.AIPS.updateAIEditComponentValue(component, response.data.new_value, 'ai_generated');
				aiEditState.changedComponents.add(component);
				
				// Mark section as changed
				$('[data-component="' + component + '"]').closest('.aips-component-section').addClass('changed');
				
				// Show success message
				window.AIPS.showComponentStatus(component, 'success', aipsAIEditL10n.regenerateSuccess);

				// Ensure revision list reflects the new regeneration snapshot if panel is open.
				window.AIPS.refreshComponentRevisions(component);
			} else {
				window.AIPS.showComponentStatus(component, 'error', response.data.message || aipsAIEditL10n.regenerateError);
			}
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
			
			// Disable save button
			window.AIPS.Utilities.setButtonLoading($('#aips-ai-edit-save'), aipsAIEditL10n.saving);
			
			$.ajax({
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
		},
		
		/**
		 * Handle successful save
		 */
		onAIEditSaveSuccess: function(response) {
			window.AIPS.Utilities.resetButton($('#aips-ai-edit-save'));
			
			if (response.success) {
				window.AIPS.showAIEditNotice(response.data.message, 'success');
				
				// Close modal without unsaved-changes prompt
				window.AIPS.closeAIEditModal(null, { skipConfirm: true });
				
				// Refresh page to show updated data
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				window.AIPS.showAIEditNotice(response.data.message || aipsAIEditL10n.saveError, 'error');
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
					{ label: 'No, keep editing', className: 'aips-btn aips-btn-primary' },
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
			
			// Clear all inputs
			$('#aips-component-title').val('');
			$('#aips-component-excerpt').val('');
			$('#aips-component-content').val('');
			$('#aips-component-image').attr('src', '').hide();
			$('#aips-component-image-none').show();
			
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
			$meta.append('<span class="aips-revision-source">' + window.AIPS.escapeHtml(revisionLabel) + '</span>');
			$meta.append('<span class="aips-revision-timestamp">' + window.AIPS.escapeHtml(timestamp) + '</span>');
			$content.append($meta);
			
			// Value preview
			var $value = $('<div class="aips-revision-value aips-revision-value-' + componentType + '"></div>');
			
			if (componentType === 'featured_image') {
				if (revision.value && revision.value.url) {
					$value.html('<img src="' + window.AIPS.escapeHtml(revision.value.url) + '" alt="Revision" class="aips-revision-value-image" />');
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
			
			// Disable button and show loading
			$button.prop('disabled', true);
			$item.addClass('restoring');
			
			// Make AJAX request
			$.ajax({
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
					$button.prop('disabled', false);
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
					$button.prop('disabled', false);
					$item.removeClass('restoring');
					console.error('Failed to restore revision:', error);
					window.AIPS.showComponentStatus(componentType, 'error', 'Failed to restore revision. Please try again.');
				}
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
		
		/**
		 * Escape HTML for safe rendering
		 */
		escapeHtml: function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
		}
		
	});
	
	// Initialize when document is ready
	$(document).ready(function() {
		window.AIPS.initAIEdit();
	});
	
})(jQuery);
