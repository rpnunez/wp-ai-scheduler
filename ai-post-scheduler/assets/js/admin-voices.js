/**
 * Admin Voices Component
 * 
 * Handles all voice-related functionality including CRUD operations,
 * search, filtering, and modal interactions for the Voices admin page.
 * 
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * Voices Component
	 * 
	 * Manages voice operations including create, edit, delete, search, and filter.
	 */
	AIPS.Voices = {
		/**
		 * Initialize the Voices component.
		 * 
		 * Binds all voice-related event handlers.
		 */
		init: function() {
			this.bindEvents();
			this.initializeVoiceSearch();
		},

		/**
		 * Bind voice-specific event handlers.
		 */
		bindEvents: function() {
			// Voice CRUD operations
			$(document).on('keyup', '#voice_search', this.searchVoices);
			$(document).on('click', '.aips-add-voice-btn', this.openVoiceModal);
			$(document).on('click', '.aips-edit-voice', this.editVoice);
			$(document).on('click', '.aips-delete-voice', this.deleteVoice);
			$(document).on('click', '.aips-save-voice', this.saveVoice);

			// Voice search and filtering
			$(document).on('keyup search', '#aips-voice-search', this.filterVoices);
			$(document).on('click', '#aips-voice-search-clear', this.clearVoiceSearch);
			$(document).on('click', '.aips-clear-voice-search-btn', this.clearVoiceSearch);
		},

		/**
		 * Initialize voice search dropdown.
		 * 
		 * Loads voices on template page load if the voice search element exists.
		 */
		initializeVoiceSearch: function() {
			if ($('#voice_search').length) {
				this.searchVoices.call($('#voice_search'));
			}
		},

		/**
		 * Search voices via AJAX.
		 * 
		 * Dynamically filters voice dropdown based on search input.
		 * Used in template forms to select a voice.
		 */
		searchVoices: function() {
			var search = $(this).val();
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_search_voices',
					nonce: aipsAjax.nonce,
					search: search
				},
				success: function(response) {
					if (response.success) {
						var $select = $('#voice_id');
						var currentVal = $select.val();
						$select.html('<option value="0">' + 'No Voice (Use Default)' + '</option>');
						$.each(response.data.voices, function(i, voice) {
							$select.append('<option value="' + voice.id + '">' + voice.name + '</option>');
						});
						$select.val(currentVal);
					}
				}
			});
		},

		/**
		 * Open the voice modal for creating a new voice.
		 * 
		 * Resets the form and displays the modal with "Add New Voice" title.
		 * 
		 * @param {Event} e - The click event.
		 */
		openVoiceModal: function(e) {
			e.preventDefault();
			$('#aips-voice-form')[0].reset();
			$('#voice_id').val('');
			$('#aips-voice-modal-title').text('Add New Voice');
			$('#aips-voice-modal').show();
		},

		/**
		 * Edit an existing voice.
		 * 
		 * Fetches voice data via AJAX and populates the edit modal.
		 * 
		 * @param {Event} e - The click event.
		 */
		editVoice: function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_voice',
					nonce: aipsAjax.nonce,
					voice_id: id
				},
				success: function(response) {
					if (response.success) {
						var v = response.data.voice;
						$('#voice_id').val(v.id);
						$('#voice_name').val(v.name);
						$('#voice_title_prompt').val(v.title_prompt);
						$('#voice_content_instructions').val(v.content_instructions);
						$('#voice_excerpt_instructions').val(v.excerpt_instructions || '');
						$('#voice_is_active').prop('checked', v.is_active == 1);
						$('#aips-voice-modal-title').text('Edit Voice');
						$('#aips-voice-modal').show();
					}
				}
			});
		},

		/**
		 * Delete a voice.
		 * 
		 * Prompts for confirmation and deletes the voice via AJAX.
		 * 
		 * @param {Event} e - The click event.
		 */
		deleteVoice: function(e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to delete this voice?')) {
				return;
			}
			var id = $(this).data('id');
			var $row = $(this).closest('tr');
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_delete_voice',
					nonce: aipsAjax.nonce,
					voice_id: id
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() { $(this).remove(); });
					} else {
						alert(response.data.message);
					}
				}
			});
		},

		/**
		 * Save a voice (create or update).
		 * 
		 * Validates the form and submits via AJAX.
		 * Reloads the page on success to reflect changes.
		 * 
		 * @param {Event} e - The click event.
		 */
		saveVoice: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $form = $('#aips-voice-form');
			if (!$form[0].checkValidity()) {
				$form[0].reportValidity();
				return;
			}
			$btn.prop('disabled', true).text('Saving...');
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_save_voice',
					nonce: aipsAjax.nonce,
					voice_id: $('#voice_id').val(),
					name: $('#voice_name').val(),
					title_prompt: $('#voice_title_prompt').val(),
					content_instructions: $('#voice_content_instructions').val(),
					excerpt_instructions: $('#voice_excerpt_instructions').val(),
					is_active: $('#voice_is_active').is(':checked') ? 1 : 0
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
					$btn.prop('disabled', false).text('Save Voice');
				}
			});
		},

		/**
		 * Filter voices by search term.
		 * 
		 * Filters the voices table on the Voices page based on name.
		 * Shows/hides table rows and displays a "no results" message if needed.
		 */
		filterVoices: function() {
			var term = $('#aips-voice-search').val().toLowerCase().trim();
			var $rows = $('.aips-voices-list tbody tr');
			var $noResults = $('#aips-voice-search-no-results');
			var $table = $('.aips-voices-list');
			var $clearBtn = $('#aips-voice-search-clear');
			var hasVisible = false;

			if (term.length > 0) {
				$clearBtn.show();
			} else {
				$clearBtn.hide();
			}

			$rows.each(function() {
				var $row = $(this);
				var name = $row.find('.column-name').text().toLowerCase();

				if (name.indexOf(term) > -1) {
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
		 * Clear the voice search field.
		 * 
		 * Resets the search input and triggers the filter to show all voices.
		 * 
		 * @param {Event} e - The click event.
		 */
		clearVoiceSearch: function(e) {
			e.preventDefault();
			$('#aips-voice-search').val('').trigger('keyup');
		}
	};

	// Initialize Voices component when DOM is ready
	$(document).ready(function() {
		AIPS.Voices.init();
	});

})(jQuery);
