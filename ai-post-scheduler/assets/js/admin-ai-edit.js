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
	
	const AIEditModal = {
		state: {
			postId: null,
			historyId: null,
			components: {},
			changedComponents: new Set(),
			originalValues: {}
		},
		
		/**
		 * Initialize the module
		 */
		init: function() {
			this.bindEvents();
		},
		
		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			$(document).on('click', '.aips-ai-edit-btn', this.openModal.bind(this));
			$(document).on('click', '#aips-ai-edit-cancel', this.closeModal.bind(this));
			$(document).on('click', '.aips-regenerate-btn', this.regenerateComponent.bind(this));
			$(document).on('click', '#aips-ai-edit-save', this.saveChanges.bind(this));
			$(document).on('click', '.aips-modal-overlay', this.closeModal.bind(this));
			
			// Track changes in input fields
			$(document).on('input', '.aips-component-input, .aips-component-textarea', this.onComponentChange.bind(this));
			
			// Keyboard shortcuts
			$(document).on('keydown', this.handleKeyboard.bind(this));
		},
		
		/**
		 * Open the AI Edit modal
		 */
		openModal: function(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			
			this.state.postId = $btn.data('post-id');
			this.state.historyId = $btn.data('history-id');
			
			$('#aips-ai-edit-modal').show();
			$('body').addClass('aips-modal-open');
			this.loadPostComponents();
		},
		
		/**
		 * Load post components via AJAX
		 */
		loadPostComponents: function() {
			$('.aips-ai-edit-loading').show();
			$('.aips-ai-edit-content').hide();
			
			$.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_post_components',
					post_id: this.state.postId,
					history_id: this.state.historyId,
					nonce: aipsAIEditL10n.nonce
				},
				success: this.onComponentsLoaded.bind(this),
				error: this.onLoadError.bind(this)
			});
		},
		
		/**
		 * Handle successful load of post components
		 */
		onComponentsLoaded: function(response) {
			if (response.success) {
				this.state.components = response.data.components;
				this.populateModal(response.data);
			} else {
				this.showNotice(response.data.message || aipsAIEditL10n.loadError, 'error');
				this.closeModal();
			}
		},
		
		/**
		 * Handle load error
		 */
		onLoadError: function() {
			this.showNotice(aipsAIEditL10n.loadError, 'error');
			this.closeModal();
		},
		
		/**
		 * Populate modal with post data
		 */
		populateModal: function(data) {
			// Populate context info
			$('#aips-context-template').text(data.context.template_name);
			$('#aips-context-author').text(data.context.author_name);
			$('#aips-context-topic').text(data.context.topic_title);
			
			// Populate components and store original values
			$('#aips-component-title').val(data.components.title.value);
			this.state.originalValues.title = data.components.title.value;
			
			$('#aips-component-excerpt').val(data.components.excerpt.value);
			this.state.originalValues.excerpt = data.components.excerpt.value;
			
			$('#aips-component-content').val(data.components.content.value);
			this.state.originalValues.content = data.components.content.value;
			
			if (data.components.featured_image.url) {
				$('#aips-component-image').attr('src', data.components.featured_image.url).show();
				$('#aips-component-image-none').hide();
			} else {
				$('#aips-component-image').hide();
				$('#aips-component-image-none').show();
			}
			
			// Update character counts
			this.updateCharCount('title');
			this.updateCharCount('excerpt');
			this.updateCharCount('content');
			
			// Show content, hide loading
			$('.aips-ai-edit-loading').hide();
			$('.aips-ai-edit-content').show();
		},
		
		/**
		 * Regenerate a single component
		 */
		regenerateComponent: function(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const component = $btn.data('component');
			
			// Disable button and show loading state
			$btn.prop('disabled', true)
				.addClass('regenerating')
				.find('.button-text').text(aipsAIEditL10n.regenerating);
			
			$.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_regenerate_component',
					post_id: this.state.postId,
					history_id: this.state.historyId,
					component: component,
					nonce: aipsAIEditL10n.nonce
				},
				success: this.onComponentRegenerated.bind(this, $btn, component),
				error: this.onRegenerateError.bind(this, $btn, component)
			});
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
				this.updateComponentValue(component, response.data.new_value);
				this.state.changedComponents.add(component);
				
				// Mark section as changed
				$('[data-component="' + component + '"]').closest('.aips-component-section').addClass('changed');
				
				// Show success message
				this.showComponentStatus(component, 'success', aipsAIEditL10n.regenerateSuccess);
			} else {
				this.showComponentStatus(component, 'error', response.data.message || aipsAIEditL10n.regenerateError);
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
			
			this.showComponentStatus(component, 'error', aipsAIEditL10n.regenerateError);
		},
		
		/**
		 * Update component value in UI
		 */
		updateComponentValue: function(component, value) {
			switch(component) {
				case 'title':
					$('#aips-component-title').val(value);
					this.updateCharCount('title');
					break;
				case 'excerpt':
					$('#aips-component-excerpt').val(value);
					this.updateCharCount('excerpt');
					break;
				case 'content':
					$('#aips-component-content').val(value);
					this.updateCharCount('content');
					break;
				case 'featured_image':
					if (value.url) {
						$('#aips-component-image').attr('src', value.url).show();
						$('#aips-component-image-none').hide();
						this.state.components.featured_image = value;
					}
					break;
			}
		},
		
		/**
		 * Show component status message
		 */
		showComponentStatus: function(component, type, message) {
			const $section = $('[data-component="' + component + '"]').closest('.aips-component-section');
			const $status = $section.find('.aips-component-status');
			
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
		onComponentChange: function(e) {
			const $input = $(e.currentTarget);
			const component = $input.closest('.aips-component-section').find('[data-component]').data('component');
			const currentValue = $input.val();
			const originalValue = this.state.originalValues[component];
			
			// Update character count
			this.updateCharCount(component);
			
			// Track if changed from original
			if (currentValue !== originalValue) {
				this.state.changedComponents.add(component);
				$input.closest('.aips-component-section').addClass('changed');
			} else {
				this.state.changedComponents.delete(component);
				$input.closest('.aips-component-section').removeClass('changed');
			}
		},
		
		/**
		 * Update character count display
		 */
		updateCharCount: function(component) {
			const $input = $('#aips-component-' + component);
			const $count = $input.siblings('.aips-component-meta').find('.aips-char-count');
			
			if ($count.length) {
				const charCount = $input.val().length;
				$count.text(charCount + ' characters');
			}
		},
		
		/**
		 * Save all changed components
		 */
		saveChanges: function(e) {
			e.preventDefault();
			
			if (this.state.changedComponents.size === 0) {
				this.showNotice(aipsAIEditL10n.noChanges, 'info');
				return;
			}
			
			const components = {};
			const self = this;
			
			this.state.changedComponents.forEach(function(component) {
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
						if (self.state.components.featured_image) {
							components.featured_image_id = self.state.components.featured_image.attachment_id;
						}
						break;
				}
			});
			
			// Disable save button
			$('#aips-ai-edit-save').prop('disabled', true).text(aipsAIEditL10n.saving);
			
			$.ajax({
				url: aipsAIEditL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_save_post_components',
					post_id: this.state.postId,
					components: components,
					nonce: aipsAIEditL10n.nonce
				},
				success: this.onSaveSuccess.bind(this),
				error: this.onSaveError.bind(this)
			});
		},
		
		/**
		 * Handle successful save
		 */
		onSaveSuccess: function(response) {
			$('#aips-ai-edit-save').prop('disabled', false).text(aipsAIEditL10n.save);
			
			if (response.success) {
				this.showNotice(response.data.message, 'success');
				
				// Close modal
				this.closeModal();
				
				// Refresh page to show updated data
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				this.showNotice(response.data.message || aipsAIEditL10n.saveError, 'error');
			}
		},
		
		/**
		 * Handle save error
		 */
		onSaveError: function() {
			$('#aips-ai-edit-save').prop('disabled', false).text(aipsAIEditL10n.save);
			this.showNotice(aipsAIEditL10n.saveError, 'error');
		},
		
		/**
		 * Close the modal
		 */
		closeModal: function(e) {
			// If clicking on close button or overlay
			if (e && e.target !== e.currentTarget && !$(e.currentTarget).is('#aips-ai-edit-cancel')) {
				return;
			}
			
			if (this.state.changedComponents.size > 0) {
				if (!confirm(aipsAIEditL10n.confirmClose)) {
					if (e) e.stopPropagation();
					return;
				}
			}
			
			$('#aips-ai-edit-modal').hide();
			$('body').removeClass('aips-modal-open');
			this.resetState();
		},
		
		/**
		 * Reset modal state
		 */
		resetState: function() {
			this.state = {
				postId: null,
				historyId: null,
				components: {},
				changedComponents: new Set(),
				originalValues: {}
			};
			
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
		showNotice: function(message, type) {
			type = type || 'info';
			
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
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
		handleKeyboard: function(e) {
			// Only handle if modal is open
			if (!$('#aips-ai-edit-modal').is(':visible')) {
				return;
			}
			
			// ESC key - close modal
			if (e.keyCode === 27) {
				this.closeModal();
			}
			
			// Ctrl/Cmd + S - save changes
			if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
				e.preventDefault();
				this.saveChanges(e);
			}
		}
	};
	
	// Initialize when document is ready
	$(document).ready(function() {
		AIEditModal.init();
	});
	
})(jQuery);
