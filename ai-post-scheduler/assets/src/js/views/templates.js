import Backbone from 'backbone';
import $ from 'jquery';
import { TemplateModel } from '../models/template';

/**
 * Templates View Controller
 */
export const TemplatesView = Backbone.View.extend({
	el: 'body',

	events: {
		// Actions
		'click .aips-add-template-btn': 'openTemplateModal',
		'click .aips-edit-template': 'editTemplate',
		'click .aips-clone-template': 'cloneTemplate',
		'click .aips-delete-template': 'deleteTemplate',
		'click .aips-save-template': 'saveTemplate',
		'click .aips-save-draft-template': 'saveDraftTemplate',
		'click .aips-test-template': 'testTemplate',
		'click .aips-run-now': 'runNow',
		'click .aips-quick-preview-post': 'openGeneratedPostPreview',

		// Form field toggles
		'change #generate_featured_image': 'toggleImagePrompt',
		'change #featured_image_source': 'toggleFeaturedImageSourceFields',
		'click #featured_image_media_select': 'openMediaLibrary',
		'click #featured_image_media_clear': 'clearMediaSelection',
		'change #include_sources': 'toggleSourceGroupsSelector',

		// Wizard
		'click .aips-wizard-next': 'wizardNext',
		'click .aips-wizard-back': 'wizardBack',
		'click .aips-wizard-modal .aips-wizard-step': 'wizardStepClick',

		// Search
		'keyup #aips-template-search': 'filterTemplates',
		'search #aips-template-search': 'filterTemplates',
		'click #aips-template-search-clear': 'clearTemplateSearch',
		'click .aips-clear-search-btn': 'clearTemplateSearch'
	},

	initialize() {
		// Bind context
		this.model = new TemplateModel();
	},

	openTemplateModal(e) {
		if (e) e.preventDefault();
		const $form = $('#aips-template-form');
		if ($form.length) $form[0].reset();
		$('#template_id').val('');
		$('#template_campaign_id').val('');
		$('#aips-modal-title').text('Add New Template');
		$('#featured_image_source').val('ai_prompt');
		$('#featured_image_unsplash_keywords').val('');
		this.setMediaSelection([]);
		this.toggleImagePrompt();
		this.updateAIVariablesPanel([]);
		$('.aips-template-source-group-cb').prop('checked', false);
		$('#template-source-groups-selector').hide();
		this.wizardGoToStep(1, $('#aips-template-modal'));
		$('#aips-template-modal').show();
	},

	editTemplate(e) {
		if (e) e.preventDefault();
		const id = $(e.currentTarget).data('id');
		const $btn = $(e.currentTarget);
		
		$btn.prop('disabled', true);

		const template = new TemplateModel({ id: id });
		template.fetch({
			success: (model) => {
				const t = model.toJSON();
				let selectedCategories = [];
				if (Array.isArray(t.post_category)) {
					selectedCategories = t.post_category.map(String);
				} else if (typeof t.post_category === 'string' && t.post_category.length) {
					try {
						const parsed = JSON.parse(t.post_category);
						if (Array.isArray(parsed)) {
							selectedCategories = parsed.map(String);
						} else if (Number.isInteger(parsed) && parsed > 0) {
							selectedCategories = [String(parsed)];
						}
					} catch (err) {
						const parsedVal = parseInt(t.post_category, 10);
						if (!isNaN(parsedVal) && parsedVal > 0) {
							selectedCategories = [String(parsedVal)];
						}
					}
				}

				$('#template_id').val(t.id);
				$('#template_name').val(t.name);
				$('#template_description').val(t.description || '');
				$('#prompt_template').val(t.prompt_template);
				$('#title_prompt').val(t.title_prompt);
				$('#post_quantity').val(t.post_quantity || 1);
				$('#generate_featured_image').prop('checked', t.generate_featured_image == 1);
				$('#image_prompt').val(t.image_prompt || '');
				$('#featured_image_source').val(t.featured_image_source || 'ai_prompt');
				$('#featured_image_unsplash_keywords').val(t.featured_image_unsplash_keywords || '');
				this.setMediaSelection(t.featured_image_media_ids || '');
				$('#post_status').val(t.post_status);
				$('#post_category').val(selectedCategories);
				$('#post_tags').val(t.post_tags);
				$('#post_author').val(t.post_author);
				$('#is_active').prop('checked', t.is_active == 1);
				$('#template_campaign_id').val(t.campaign_id || '');

				this.toggleImagePrompt();
				this.toggleFeaturedImageSourceFields();

				const includeSources = t.include_sources == 1;
				$('#include_sources').prop('checked', includeSources);
				$('#template-source-groups-selector').toggle(includeSources);
				$('.aips-template-source-group-cb').prop('checked', false);

				let sgIds = [];
				try {
					sgIds = JSON.parse(t.source_group_ids || '[]');
				} catch (err) {
					sgIds = [];
				}
				sgIds.forEach((tid) => {
					$('.aips-template-source-group-cb[value="' + tid + '"]').prop('checked', true);
				});

				if (window.AIPS && typeof window.AIPS.initAIVariablesScanner === 'function') {
					window.AIPS.initAIVariablesScanner();
				}

				$('#aips-modal-title').text('Edit Template');
				this.wizardGoToStep(1, $('#aips-template-modal'));
				$('#aips-template-modal').show();
				$btn.prop('disabled', false);
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || 'Error loading template data.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
				$btn.prop('disabled', false);
			}
		});
	},

	cloneTemplate(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		const confirmMsg = 'Are you sure you want to clone this template?';
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Confirm', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, clone', className: 'aips-btn aips-btn-primary', action: () => this._executeClone($btn, id) }
			]);
		}
	},

	_executeClone($btn, id) {
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Cloning...');
		}
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_clone_template',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				template_id: id
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
						window.AIPS.refreshContentPanel('.aips-templates-list', '#aips-template-search-no-results');
					} else {
						window.location.reload();
					}
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'error');
						window.AIPS.Utilities.resetButton($btn);
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Error cloning template.', 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	deleteTemplate(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm('Are you sure you want to delete this template? This cannot be undone.', 'Confirm Delete', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: () => this._executeDelete($btn, id) }
			]);
		}
	},

	_executeDelete($btn, id) {
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Deleting...');
		}
		const template = new TemplateModel({ id: id });
		template.destroy({
			success: () => {
				if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
					window.AIPS.refreshContentPanel('.aips-templates-list', '#aips-template-search-no-results');
				} else {
					window.location.reload();
				}
			},
			error: (model, err) => {
				const msg = (err && err.message) || 'Error deleting template.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	saveTemplate(e) {
		if (e) e.preventDefault();
		this._save(true);
	},

	saveDraftTemplate(e) {
		if (e) e.preventDefault();
		this._save(false);
	},

	_save(isActive) {
		const $btn = $('.aips-save-template');
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Saving...');
		}

		const sgIds = [];
		$('.aips-template-source-group-cb:checked').each(function() {
			sgIds.push($(this).val());
		});

		const categories = $('#post_category').val() || [];

		const data = {
			id: $('#template_id').val(),
			name: $('#template_name').val(),
			description: $('#template_description').val(),
			prompt_template: $('#prompt_template').val(),
			title_prompt: $('#title_prompt').val(),
			post_quantity: $('#post_quantity').val(),
			generate_featured_image: $('#generate_featured_image').is(':checked'),
			image_prompt: $('#image_prompt').val(),
			featured_image_source: $('#featured_image_source').val(),
			featured_image_unsplash_keywords: $('#featured_image_unsplash_keywords').val(),
			featured_image_media_ids: $('#featured_image_media_ids').val(),
			post_status: $('#post_status').val(),
			post_category: categories,
			post_tags: $('#post_tags').val(),
			post_author: $('#post_author').val(),
			is_active: isActive,
			campaign_id: $('#template_campaign_id').val(),
			include_sources: $('#include_sources').is(':checked'),
			source_group_ids: JSON.stringify(sgIds)
		};

		const template = new TemplateModel(data);
		template.save(null, {
			success: (model, resp) => {
				$('#aips-template-modal').hide();
				if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
					window.AIPS.refreshContentPanel('.aips-templates-list', '#aips-template-search-no-results');
				} else {
					window.location.reload();
				}
			},
			error: (model, err) => {
				const msg = (err && err.message) || 'Error saving template.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	testTemplate(e) {
		if (e) e.preventDefault();
		// Test Template logic using ajax
	},

	runNow(e) {
		if (e) e.preventDefault();
		// Run now schedule
	},

	openGeneratedPostPreview(e) {
		if (e) e.preventDefault();
		// Open post preview logic
	},

	toggleImagePrompt() {
		const checked = $('#generate_featured_image').is(':checked');
		$('#template-image-prompt-field').toggle(checked);
	},

	toggleFeaturedImageSourceFields() {
		const source = $('#featured_image_source').val();
		$('.featured-image-field-group').hide();
		if (source === 'ai_prompt') {
			$('#template-image-prompt-field').show();
		} else if (source === 'unsplash') {
			$('#template-unsplash-keywords-field').show();
		} else if (source === 'media_library') {
			$('#template-media-library-field').show();
		}
	},

	setMediaSelection(ids) {
		$('#featured_image_media_ids').val(ids);
		// Update media previews in form
	},

	openMediaLibrary(e) {
		if (e) e.preventDefault();
		// Open WP Media Library frame
	},

	clearMediaSelection(e) {
		if (e) e.preventDefault();
		this.setMediaSelection('');
	},

	toggleSourceGroupsSelector() {
		const checked = $('#include_sources').is(':checked');
		$('#template-source-groups-selector').toggle(checked);
	},

	wizardGoToStep(step, $modal) {
		$modal.find('.aips-wizard-step-content').hide();
		$modal.find('.aips-wizard-step-content[data-step="' + step + '"]').show();
		$modal.find('.aips-wizard-step').removeClass('active');
		$modal.find('.aips-wizard-step[data-step="' + step + '"]').addClass('active');
	},

	wizardNext(e) {
		if (e) e.preventDefault();
		const $modal = $(e.currentTarget).closest('.aips-modal');
		const currentStep = $modal.find('.aips-wizard-step-content:visible').data('step');
		this.wizardGoToStep(currentStep + 1, $modal);
	},

	wizardBack(e) {
		if (e) e.preventDefault();
		const $modal = $(e.currentTarget).closest('.aips-modal');
		const currentStep = $modal.find('.aips-wizard-step-content:visible').data('step');
		this.wizardGoToStep(currentStep - 1, $modal);
	},

	wizardStepClick(e) {
		if (e) e.preventDefault();
		const step = $(e.currentTarget).data('step');
		const $modal = $(e.currentTarget).closest('.aips-modal');
		this.wizardGoToStep(step, $modal);
	},

	filterTemplates() {
		const query = $('#aips-template-search').val().toLowerCase();
		$('.aips-template-card').each(function() {
			const text = $(this).text().toLowerCase();
			$(this).toggle(text.indexOf(query) > -1);
		});
	},

	clearTemplateSearch(e) {
		if (e) e.preventDefault();
		$('#aips-template-search').val('');
		this.filterTemplates();
	},

	updateAIVariablesPanel(vars) {
		// Update variables scan list
	}
});
