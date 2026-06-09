import Backbone from 'backbone';
import $ from 'jquery';
import { TemplateModel } from '../models/template';

// System variables that should not be treated as AI Variables
const SYSTEM_VARIABLES = ['date', 'year', 'month', 'day', 'time', 'site_name', 'site_description', 'random_number', 'topic', 'title'];

// Required-field rules for the template wizard
const WIZARD_REQUIRED_FIELDS = [
	{ step: 1, selector: '#template_name', messageKey: 'templateNameRequired' },
	{ step: 2, selector: '#prompt_template', messageKey: 'contentPromptRequired' }
];

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
		'click .aips-clear-search-btn': 'clearTemplateSearch',

		// Prompts Preview Drawer
		'click .aips-preview-prompts': 'previewPrompts',
		'click .aips-preview-drawer-handle': 'togglePreviewDrawer',

		// AI Variables
		'click .aips-ai-var-tag': 'copyAIVariable',
		'keyup .aips-ai-var-input': 'scanAllAIVariables',
		'change .aips-ai-var-input': 'scanAllAIVariables',
		'keyup #voice_search': 'searchVoices'
	},

	initialize() {
		this.model = new TemplateModel();
		this.mediaFrame = null;
		this.generatedPostPreviewMap = {};

		// Expose variables scanning to global window namespace for diagnostic scripts
		window.AIPS = window.AIPS || {};
		window.AIPS.initAIVariablesScanner = this.initAIVariablesScanner.bind(this);
		window.AIPS.scanAllAIVariables = this.scanAllAIVariables.bind(this);
		window.AIPS.extractAIVariables = this.extractAIVariables.bind(this);
		window.AIPS.updateAIVariablesPanel = this.updateAIVariablesPanel.bind(this);
		window.AIPS.showGeneratedPostsModal = this.showGeneratedPostsModal.bind(this);
		window.AIPS.generatedPostPreviewMap = this.generatedPostPreviewMap;

		// Initial scan
		$(document).ready(() => {
			this.initAIVariablesScanner();
			if (this.$('#voice_search').length) {
				this.searchVoices();
			}
		});
	},

	openTemplateModal(e) {
		if (e) e.preventDefault();
		const $form = $('#aips-template-form');
		if ($form.length) $form[0].reset();
		$('#template_id').val('');
		$('#template_campaign_id').val('');
		const l10n = window.aipsTemplatesL10n || {};
		$('#aips-modal-title').text(l10n.addNewTemplate || 'Add New Template');
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

				this.initAIVariablesScanner();

				const l10n = window.aipsTemplatesL10n || {};
				$('#aips-modal-title').text(l10n.editTemplate || 'Edit Template');
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
			success: () => {
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
		const l10n = window.aipsTemplatesL10n || {};
		const adminL10n = window.aipsAdminL10n || {};

		// Validate step 2 prompt field
		const promptRule = WIZARD_REQUIRED_FIELDS.filter(r => r.step === 2)[0];
		if (promptRule && !$(promptRule.selector).val().trim()) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n[promptRule.messageKey] || 'Prompt is required.', 'warning');
			}
			$(promptRule.selector).focus();
			return;
		}

		const $btn = $(e.currentTarget);
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn,
				'<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> ' + (adminL10n.generating || 'Generating...'),
				{ isHtml: true }
			);
		}

		const data = {
			action: 'aips_test_template',
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			template_id: $('#template_id').val(),
			name: $('#template_name').val(),
			description: $('#template_description').val(),
			prompt_template: $('#prompt_template').val(),
			title_prompt: $('#title_prompt').val(),
			voice_id: $('#voice_id').val(),
			post_quantity: 1,
			generate_featured_image: $('#generate_featured_image').is(':checked') ? 1 : 0,
			image_prompt: $('#image_prompt').val(),
			featured_image_source: $('#featured_image_source').val(),
			featured_image_unsplash_keywords: $('#featured_image_unsplash_keywords').val(),
			featured_image_media_ids: $('#featured_image_media_ids').val(),
			post_status: $('#post_status').val(),
			post_category: $('#post_category').val(),
			post_tags: $('#post_tags').val(),
			post_author: $('#post_author').val()
		};

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: data,
			success: (response) => {
				if (response.success) {
					const result = response.data.result;
					$('#aips-test-title').text(result.title || '-');
					$('#aips-test-excerpt').text(result.excerpt || '-');
					$('#aips-test-content').text(result.content || '-');

					if (result.image_prompt) {
						$('#aips-test-image-row').show();
						$('#aips-test-image').text(result.image_prompt);
					} else {
						$('#aips-test-image-row').hide();
					}

					$('#aips-test-result-modal').show();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || (adminL10n.generationFailed || 'Generation failed.'), 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(adminL10n.errorTryAgain || 'An error occurred.', 'error');
				}
			},
			complete: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	runNow(e) {
		if (e) e.preventDefault();
		const id = $(e.currentTarget).data('id');
		const $btn = $(e.currentTarget);
		const adminL10n = window.aipsAdminL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, adminL10n.generating || 'Generating...');
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_run_now',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				template_id: id
			},
			success: (response) => {
				if (response.success) {
					this.showGeneratedPostsModal(response.data);
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(adminL10n.errorTryAgain || 'An error occurred.', 'error');
				}
			},
			complete: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	showGeneratedPostsModal(data) {
		let generatedCount = parseInt(data && data.generated_count, 10);
		const posts = Array.isArray(data && data.posts) ? data.posts : [];
		const summaryMessage = data && data.summary_message ? data.summary_message : '';
		const noticeMessage = data && data.notice_message ? data.notice_message : '';
		let rowsHtml = '';
		let tableHtml = '';
		const $modalTitle = $('#aips-post-success-modal-title');
		const $summary = $('#aips-success-message');
		const $notice = $('#aips-success-note');
		const $results = $('#aips-post-results-container');

		this.generatedPostPreviewMap = {};
		window.AIPS.generatedPostPreviewMap = this.generatedPostPreviewMap;

		if (isNaN(generatedCount) || generatedCount < 1) {
			generatedCount = posts.length;
		}

		if ($modalTitle.length) {
			$modalTitle.text(
				generatedCount === 1
					? ($modalTitle.data('singularTitle') || 'Post Successfully Generated')
					: ($modalTitle.data('pluralTitle') || 'Posts Successfully Generated')
			);
		}

		if ($summary.length) {
			$summary.text(summaryMessage || (generatedCount === 1 ? '1 post has been generated.' : generatedCount + ' posts have been generated.'));
		}

		if ($notice.length) {
			if (noticeMessage) {
				$notice.text(noticeMessage).show();
			} else {
				$notice.text('').hide();
			}
		}

		if ($results.length) {
			$results.empty();

			if (posts.length) {
				posts.forEach(post => {
					const postId = parseInt(post.id, 10);

					if (!isNaN(postId) && postId > 0) {
						this.generatedPostPreviewMap[postId] = {
							title: post.title || '',
							excerpt: post.excerpt || '',
							post_content: post.post_content || ''
						};
					}

					rowsHtml += window.AIPS.Templates.render('aips-tmpl-generated-post-row', {
						post_id: postId || 0,
						title: post.title || '',
						excerpt: post.excerpt || '',
						content_snippet: post.content_snippet || '',
						edit_url: post.edit_url || '',
						view_url: post.view_url || ''
					});
				});

				tableHtml = window.AIPS.Templates.render('aips-tmpl-generated-posts-table', {
					rows: rowsHtml
				});

				$results.html(tableHtml);
			}
		}

		$('#aips-post-success-modal').show();
	},

	openGeneratedPostPreview(e) {
		e.preventDefault();
		const postId = parseInt($(e.currentTarget).data('postId'), 10);
		const preview = !isNaN(postId) ? this.generatedPostPreviewMap[postId] : null;

		if (!preview) {
			const adminL10n = window.aipsAdminL10n || {};
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(adminL10n.errorTryAgain || 'An error occurred.', 'error');
			}
			return;
		}

		$('#aips-post-preview-title').text(preview.title || '(no title)');
		$('#aips-post-preview-excerpt').text(preview.excerpt || 'No excerpt available.');
		$('#aips-post-preview-content').html(preview.post_content || '<p>No content available.</p>');
		$('#aips-post-quick-preview-modal').show();
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
		let parsedIds = [];

		if (Array.isArray(ids)) {
			parsedIds = ids;
		} else if (typeof ids === 'string') {
			parsedIds = ids.split(',').filter(id => id.trim().length > 0);
		}

		$('#featured_image_media_ids').val(parsedIds.join(','));
		$('#featured_image_media_preview').text(parsedIds.length ? parsedIds.join(', ') : 'No images selected.');
	},

	openMediaLibrary(e) {
		e.preventDefault();

		if (typeof wp === 'undefined' || !wp.media) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Media library is not available.', 'warning');
			}
			return;
		}

		if (!this.mediaFrame) {
			this.mediaFrame = wp.media({
				title: 'Select Images',
				multiple: true,
				library: { type: 'image' },
				button: { text: 'Use these images' }
			});

			this.mediaFrame.on('select', () => {
				const selection = this.mediaFrame.state().get('selection');
				const ids = [];

				selection.each(attachment => {
					ids.push(attachment.id);
				});

				this.setMediaSelection(ids);
			});
		}

		this.mediaFrame.open();
	},

	clearMediaSelection(e) {
		if (e) e.preventDefault();
		this.setMediaSelection([]);
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
		
		// Validate current step
		const rule = WIZARD_REQUIRED_FIELDS.filter(r => r.step === currentStep)[0];
		if (rule && !$(rule.selector).val().trim()) {
			const l10n = window.aipsTemplatesL10n || {};
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n[rule.messageKey] || 'Field is required.', 'warning');
			}
			$(rule.selector).focus();
			return;
		}

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
		const currentStep = $modal.find('.aips-wizard-step-content:visible').data('step');

		// If jumping forward, validate current step
		if (step > currentStep) {
			const rule = WIZARD_REQUIRED_FIELDS.filter(r => r.step === currentStep)[0];
			if (rule && !$(rule.selector).val().trim()) {
				const l10n = window.aipsTemplatesL10n || {};
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n[rule.messageKey] || 'Field is required.', 'warning');
				}
				$(rule.selector).focus();
				return;
			}
		}

		this.wizardGoToStep(step, $modal);
	},

	filterTemplates() {
		const query = $('#aips-template-search').val().toLowerCase();
		const $rows = $('.aips-templates-list tbody tr');
		const $noResults = $('#aips-template-search-no-results');
		const $table = $('.aips-templates-list table');
		const $clearBtn = $('#aips-template-search-clear');
		let hasVisible = false;

		if (query.length > 0) {
			$clearBtn.show();
		} else {
			$clearBtn.hide();
		}

		$rows.each(function() {
			const $row = $(this);
			const name = $row.find('.column-name').text().toLowerCase();
			const category = $row.find('.column-category').text().toLowerCase();

			if (name.indexOf(query) > -1 || category.indexOf(query) > -1) {
				$row.show();
				hasVisible = true;
			} else {
				$row.hide();
			}
		});

		if (!hasVisible && query.length > 0) {
			$table.hide();
			$noResults.show();
		} else {
			$table.show();
			$noResults.hide();
		}
	},

	clearTemplateSearch(e) {
		if (e) e.preventDefault();
		$('#aips-template-search').val('');
		this.filterTemplates();
	},

	searchVoices() {
		const search = $('#voice_search').val();
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_search_voices',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				search: search
			},
			success: (response) => {
				if (response.success) {
					const $select = $('#voice_id');
					const currentVal = $select.val();
					const adminL10n = window.aipsAdminL10n || {};
					$select.html('<option value="0">' + (adminL10n.noVoiceDefault || 'Default') + '</option>');
					response.data.voices.forEach(voice => {
						$select.append('<option value="' + voice.id + '">' + voice.name + '</option>');
					});
					$select.val(currentVal);
				}
			}
		});
	},

	initAIVariablesScanner() {
		this.scanAllAIVariables();
	},

	scanAllAIVariables() {
		const allVariables = [];
		$('.aips-ai-var-input').each(function() {
			const text = $(this).val() || '';
			const vars = window.AIPS.extractAIVariables(text);
			vars.forEach(v => {
				if (allVariables.indexOf(v) === -1) {
					allVariables.push(v);
				}
			});
		});
		this.updateAIVariablesPanel(allVariables);
	},

	extractAIVariables(text) {
		const variables = [];
		const regex = /\{\{([^}]+)\}\}/g;
		let match;

		while ((match = regex.exec(text)) !== null) {
			const varName = match[1].trim();
			if (SYSTEM_VARIABLES.indexOf(varName) === -1 && variables.indexOf(varName) === -1) {
				variables.push(varName);
			}
		}

		return variables;
	},

	updateAIVariablesPanel(variables) {
		const $panel = $('.aips-ai-variables-panel');
		const $list = $('#aips-ai-variables-list');
		const l10n = window.aipsTemplatesL10n || {};

		if (variables.length === 0) {
			$panel.hide();
			return;
		}

		let html = '';
		variables.forEach(varName => {
			html += '<span class="aips-ai-var-tag" data-variable="{{' + varName + '}}" title="' + (l10n.clickToCopy || 'Click to copy') + '">';
			html += '<span class="dashicons dashicons-tag"></span>';
			html += '{{' + varName + '}}';
			html += '</span>';
		});

		$list.html(html);
		$panel.show();
	},

	copyAIVariable(e) {
		e.preventDefault();
		const $tag = $(e.currentTarget);
		const variable = $tag.data('variable');

		if (!variable) return;

		const showSuccess = () => {
			$tag.addClass('aips-ai-var-copied');
			setTimeout(() => {
				$tag.removeClass('aips-ai-var-copied');
			}, 1500);
		};

		if (!navigator.clipboard) {
			const textArea = document.createElement('textarea');
			textArea.value = variable;
			document.body.appendChild(textArea);
			textArea.select();
			try {
				document.execCommand('copy');
				showSuccess();
			} catch (err) {
				console.error('Fallback: Unable to copy', err);
			}
			document.body.removeChild(textArea);
			return;
		}

		navigator.clipboard.writeText(variable).then(() => {
			showSuccess();
		}, (err) => {
			console.error('Could not copy text: ', err);
		});
	},

	previewPrompts(e) {
		e.preventDefault();
		
		const $drawer = $('#aips-preview-drawer');
		const $content = $drawer.find('.aips-preview-drawer-content');
		const $loading = $drawer.find('.aips-preview-loading');
		const $error = $drawer.find('.aips-preview-error');
		const $sections = $drawer.find('.aips-preview-sections');
		
		if (!$drawer.hasClass('expanded')) {
			this.togglePreviewDrawer();
		}
		
		$content.show();
		$loading.show();
		$error.hide();
		$sections.hide();
		
		const sourceGroupIds = [];
		$('.aips-template-source-group-cb:checked').each(function() {
			sourceGroupIds.push($(this).val());
		});

		const formData = {
			action: 'aips_preview_template_prompts',
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			prompt_template: $('#prompt_template').val(),
			title_prompt: $('#title_prompt').val(),
			voice_id: parseInt($('#voice_id').val(), 10) || 0,
			article_structure_id: parseInt($('#article_structure_id').val(), 10) || 0,
			image_prompt: $('#image_prompt').val(),
			generate_featured_image: $('#generate_featured_image').is(':checked') ? 1 : 0,
			featured_image_source: $('#featured_image_source').val(),
			include_sources: $('#include_sources').is(':checked') ? 1 : 0,
			source_group_ids: sourceGroupIds
		};
		
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: formData,
			success: (response) => {
				$loading.hide();
				
				if (response.success) {
					const prompts = response.data.prompts;
					const metadata = response.data.metadata;
					const l10n = window.aipsTemplatesL10n || {};
					
					if (metadata.voice) {
						$('#aips-preview-voice').show().find('.aips-preview-voice-name').text(metadata.voice);
					} else {
						$('#aips-preview-voice').hide();
					}
					
					if (metadata.article_structure) {
						$('#aips-preview-structure').show().find('.aips-preview-structure-name').text(metadata.article_structure);
					} else {
						$('#aips-preview-structure').hide();
					}
					
					$('.aips-preview-sample-topic').text(metadata.sample_topic || l10n.exampleTopic || 'Sample Topic');
					
					$('#aips-preview-content-prompt').text(prompts.content || '-');
					$('#aips-preview-title-prompt').text(prompts.title || '-');
					$('#aips-preview-excerpt-prompt').text(prompts.excerpt || '-');
					
					if (prompts.image) {
						$('#aips-preview-image-section').show();
						$('#aips-preview-image-prompt').text(prompts.image);
					} else {
						$('#aips-preview-image-section').hide();
					}
					
					$sections.show();
				} else {
					const l10n = window.aipsTemplatesL10n || {};
					const errorMsg = response.data.message || l10n.failedToGeneratePreview || 'Failed to generate preview.';
					$error.text(errorMsg).show();
				}
			},
			error: () => {
				const l10n = window.aipsTemplatesL10n || {};
				$loading.hide();
				$error.text(l10n.previewNetworkError || 'Network error generating preview.').show();
			}
		});
	},

	togglePreviewDrawer(e) {
		if (e) e.preventDefault();
		
		const $drawer = $('#aips-preview-drawer');
		const $content = $drawer.find('.aips-preview-drawer-content');
		
		if ($drawer.hasClass('expanded')) {
			$drawer.removeClass('expanded');
			$content.slideUp(300);
		} else {
			$drawer.addClass('expanded');
			$content.slideDown(300);
		}
	}
});
