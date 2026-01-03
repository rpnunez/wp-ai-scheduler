/**
 * Admin Templates Component
 * 
 * Handles all template-related functionality including CRUD operations,
 * testing, running templates, search, filtering, and modal interactions
 * for the Templates admin page.
 * 
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * Templates Component
	 * 
	 * Manages template operations including create, edit, delete, test, run now,
	 * search, filtering, and viewing posts generated from templates.
	 */
	AIPS.Templates = {
		/**
		 * Initialize the Templates component.
		 * 
		 * Binds all template-related event handlers.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind template-specific event handlers.
		 */
		bindEvents: function() {
			// Template CRUD operations
			$(document).on('click', '.aips-add-template-btn', this.openTemplateModal);
			$(document).on('click', '.aips-edit-template', this.editTemplate);
			$(document).on('click', '.aips-delete-template', this.deleteTemplate);
			$(document).on('click', '.aips-save-template', this.saveTemplate);
			$(document).on('click', '.aips-test-template', this.testTemplate);
			$(document).on('click', '.aips-run-now', this.runNow);
			$(document).on('change', '#generate_featured_image', this.toggleImagePrompt);

			// Template search and filtering
			$(document).on('keyup search', '#aips-template-search', this.filterTemplates);
			$(document).on('click', '#aips-template-search-clear', this.clearTemplateSearch);
			$(document).on('click', '.aips-clear-search-btn', this.clearTemplateSearch);

			// Template posts modal
			$(document).on('click', '.aips-view-template-posts', this.openTemplatePostsModal);
			$(document).on('click', '.aips-modal-page', this.paginateTemplatePosts);
		},

		/**
		 * Open the template modal for creating a new template.
		 * 
		 * Resets the form and displays the modal with "Add New Template" title.
		 * 
		 * @param {Event} e - The click event.
		 */
		openTemplateModal: function(e) {
			e.preventDefault();
			$('#aips-template-form')[0].reset();
			$('#template_id').val('');
			$('#aips-modal-title').text('Add New Template');
			$('#aips-template-modal').show();
		},

		/**
		 * Edit an existing template.
		 * 
		 * Fetches template data via AJAX and populates the edit modal.
		 * 
		 * @param {Event} e - The click event.
		 */
		editTemplate: function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			var $btn = $(this);
			
			$btn.prop('disabled', true);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_template',
					nonce: aipsAjax.nonce,
					template_id: id
				},
				success: function(response) {
					if (response.success) {
						var t = response.data.template;
						$('#template_id').val(t.id);
						$('#template_name').val(t.name);
						$('#prompt_template').val(t.prompt_template);
						$('#title_prompt').val(t.title_prompt);
						$('#post_quantity').val(t.post_quantity || 1);
						$('#generate_featured_image').prop('checked', t.generate_featured_image == 1);
						$('#image_prompt').val(t.image_prompt || '').prop('disabled', t.generate_featured_image != 1);
						$('#post_status').val(t.post_status);
						$('#post_category').val(t.post_category);
						$('#post_tags').val(t.post_tags);
						$('#post_author').val(t.post_author);
						$('#is_active').prop('checked', t.is_active == 1);
						$('#aips-modal-title').text('Edit Template');
						$('#aips-template-modal').show();
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Delete a template.
		 * 
		 * Uses a soft confirm pattern (click twice to confirm) and deletes via AJAX.
		 * 
		 * @param {Event} e - The click event.
		 */
		deleteTemplate: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var id = $btn.data('id');
			var $row = $btn.closest('tr');

			// Soft Confirm Pattern
			if (!$btn.data('is-confirming')) {
				$btn.data('original-text', $btn.text());
				$btn.text('Click again to confirm');
				$btn.addClass('aips-confirm-delete');
				$btn.data('is-confirming', true);

				// Reset after 3 seconds
				setTimeout(function() {
					$btn.text($btn.data('original-text'));
					$btn.removeClass('aips-confirm-delete');
					$btn.data('is-confirming', false);
				}, 3000);
				return;
			}

			// Confirmed, proceed with deletion
			$btn.prop('disabled', true).text('Deleting...');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_delete_template',
					nonce: aipsAjax.nonce,
					template_id: id
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message);
						// Reset button state on error
						$btn.text($btn.data('original-text'));
						$btn.removeClass('aips-confirm-delete');
						$btn.data('is-confirming', false);
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					// Reset button state on error
					$btn.text($btn.data('original-text'));
					$btn.removeClass('aips-confirm-delete');
					$btn.data('is-confirming', false);
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Save a template (create or update).
		 * 
		 * Validates the form and submits via AJAX.
		 * Reloads the page on success to reflect changes.
		 * 
		 * @param {Event} e - The click event.
		 */
		saveTemplate: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $form = $('#aips-template-form');

			if (!$form[0].checkValidity()) {
				$form[0].reportValidity();
				return;
			}

			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_save_template',
					nonce: aipsAjax.nonce,
					template_id: $('#template_id').val(),
					name: $('#template_name').val(),
					prompt_template: $('#prompt_template').val(),
					title_prompt: $('#title_prompt').val(),
					voice_id: $('#voice_id').val(),
					post_quantity: $('#post_quantity').val(),
					generate_featured_image: $('#generate_featured_image').is(':checked') ? 1 : 0,
					image_prompt: $('#image_prompt').val(),
					post_status: $('#post_status').val(),
					post_category: $('#post_category').val(),
					post_tags: $('#post_tags').val(),
					post_author: $('#post_author').val(),
					is_active: $('#is_active').is(':checked') ? 1 : 0
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Save Template');
				}
			});
		},

		/**
		 * Test a template by generating sample content.
		 * 
		 * Validates the prompt template and generates test content via AJAX.
		 * Displays the result in a modal.
		 * 
		 * @param {Event} e - The click event.
		 */
		testTemplate: function(e) {
			e.preventDefault();
			var prompt = $('#prompt_template').val();
			
			if (!prompt) {
				alert('Please enter a prompt template first.');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Generating...');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_test_template',
					nonce: aipsAjax.nonce,
					prompt_template: prompt
				},
				success: function(response) {
					if (response.success) {
						$('#aips-test-content').text(response.data.content);
						$('#aips-test-result-modal').show();
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Test Generate');
				}
			});
		},

		/**
		 * Run a template immediately to generate a post.
		 * 
		 * Triggers immediate post generation for the template via AJAX.
		 * Opens the edit URL in a new tab on success and reloads the page.
		 * 
		 * @param {Event} e - The click event.
		 */
		runNow: function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			var $btn = $(this);

			$btn.prop('disabled', true).text('Generating...');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_run_now',
					nonce: aipsAjax.nonce,
					template_id: id
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						if (response.data.edit_url) {
							window.open(response.data.edit_url, '_blank');
						}
						location.reload();
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Run Now');
				}
			});
		},

		/**
		 * Toggle the image prompt field based on featured image checkbox.
		 * 
		 * Enables/disables the image prompt textarea based on whether
		 * featured image generation is enabled.
		 * 
		 * @param {Event} e - The change event.
		 */
		toggleImagePrompt: function(e) {
			var isChecked = $(this).is(':checked');
			$('#image_prompt').prop('disabled', !isChecked);
		},

		/**
		 * Filter templates by search term.
		 * 
		 * Filters the templates table on the Templates page based on name and category.
		 * Shows/hides table rows and displays a "no results" message if needed.
		 */
		filterTemplates: function() {
			var term = $('#aips-template-search').val().toLowerCase().trim();
			var $rows = $('.aips-templates-list tbody tr');
			var $noResults = $('#aips-template-search-no-results');
			var $table = $('.aips-templates-list table');
			var $clearBtn = $('#aips-template-search-clear');
			var hasVisible = false;

			if (term.length > 0) {
				$clearBtn.show();
			} else {
				$clearBtn.hide();
			}

			$rows.each(function() {
				var $row = $(this);
				var name = $row.find('.column-name').text().toLowerCase();
				var category = $row.find('.column-category').text().toLowerCase();

				if (name.indexOf(term) > -1 || category.indexOf(term) > -1) {
					$row.show();
					hasVisible = true;
				} else {
					$row.hide();
				}
			});

			if (!hasVisible && term.length > 0) {
				$table.hide();
				$noResults.show();
			} else {
				$table.show();
				$noResults.hide();
			}
		},

		/**
		 * Clear the template search field.
		 * 
		 * Resets the search input and triggers the filter to show all templates.
		 * 
		 * @param {Event} e - The click event.
		 */
		clearTemplateSearch: function(e) {
			e.preventDefault();
			$('#aips-template-search').val('').trigger('keyup');
		},

		/**
		 * Open the modal to view posts generated from a template.
		 * 
		 * Displays a modal showing all posts created by the template.
		 * 
		 * @param {Event} e - The click event.
		 */
		openTemplatePostsModal: function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			$('#aips-template-posts-modal').data('template-id', id).show();
			AIPS.Templates.loadTemplatePosts(id, 1);
		},

		/**
		 * Paginate through template posts in the modal.
		 * 
		 * Handles pagination clicks within the template posts modal.
		 * 
		 * @param {Event} e - The click event.
		 */
		paginateTemplatePosts: function(e) {
			e.preventDefault();
			var page = $(this).data('page');
			var id = $('#aips-template-posts-modal').data('template-id');
			AIPS.Templates.loadTemplatePosts(id, page);
		},

		/**
		 * Load template posts via AJAX.
		 * 
		 * Fetches and displays posts generated by a specific template.
		 * 
		 * @param {number} id - The template ID.
		 * @param {number} page - The page number for pagination.
		 */
		loadTemplatePosts: function(id, page) {
			$('#aips-template-posts-content').html('<p class="aips-loading">Loading...</p>');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_template_posts',
					nonce: aipsAjax.nonce,
					template_id: id,
					page: page
				},
				success: function(response) {
					if (response.success) {
						$('#aips-template-posts-content').html(response.data.html);
					} else {
						$('#aips-template-posts-content').html('<p class="aips-error-text">' + response.data.message + '</p>');
					}
				},
				error: function() {
					$('#aips-template-posts-content').html('<p class="aips-error-text">An error occurred.</p>');
				}
			});
		}
	};

	// Initialize Templates component when DOM is ready
	$(document).ready(function() {
		AIPS.Templates.init();
	});

})(jQuery);
