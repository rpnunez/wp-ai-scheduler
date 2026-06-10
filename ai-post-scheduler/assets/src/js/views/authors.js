import Backbone from 'backbone';
import $ from 'jquery';
import { AuthorModel, AuthorCollection } from '../models/author';

/**
 * Authors View Controller
 */
export const AuthorsView = Backbone.View.extend({
	el: 'body',

	events: {
		// Tabs
		'aips:tabSwitch': 'handleSharedTabSwitch',

		// Author CRUD
		'click .aips-add-author-btn': 'openAddModal',
		'click .aips-edit-author': 'editAuthor',
		'submit #aips-author-form': 'saveAuthor',
		'click .aips-delete-author': 'deleteAuthor',
		'click .aips-generate-topics-now': 'generateTopicsNow',
		'click .aips-generate-author-posts-now': 'generateAuthorPostsNow',
		'change #author_include_sources': 'toggleAuthorSourceGroups',

		// Suggest authors & bulk operations
		'click #aips-suggest-authors-btn': 'openSuggestModal',
		'submit #aips-suggest-authors-form': 'suggestAuthors',
		'click .aips-import-suggested-author': 'importSuggestedAuthor',
		'change #aips-authors-select-all': 'toggleSelectAllAuthors',
		'click #aips-authors-bulk-apply': 'executeAuthorsBulkAction',

		// Close modal
		'click .aips-modal-close': 'closeModals',

		// Queue
		'click .aips-queue-bulk-action-execute': 'executeQueueBulkAction',
		'click .aips-queue-select-all': 'toggleQueueSelectAll',
		'click #aips-queue-reload-btn': 'loadQueueTopics',
		'click #aips-queue-filter-submit': 'applyQueueFilters',
		'change #aips-queue-author-filter, #aips-queue-field-filter': 'applyQueueFilters',
		'keyup #aips-queue-search': 'applyQueueFilters',
		'search #aips-queue-search': 'applyQueueFilters',
		'click #aips-queue-search-clear': 'clearQueueSearch',
		'click .aips-queue-page-link': 'goToQueuePage'
	},

	initialize() {
		this.model = new AuthorModel();
		this.collection = new AuthorCollection();

		this.currentAuthorId = null;
		this.hasImportedSuggestedAuthor = false;

		// Queue state
		this.queueTopics = [];
		this.filteredQueueTopics = [];
		this.queueCurrentPage = 1;
		this.queuePerPage = 10;

		// Deep-link: on the Authors list page, open the Edit modal directly when
		// an author_id is provided in the URL (e.g., redirected from "Edit Author").
		if (typeof window.aipsAuthorContext !== 'undefined' && window.aipsAuthorContext.deepLinkAuthorId) {
			const deepLinkId = parseInt(window.aipsAuthorContext.deepLinkAuthorId, 10);
			const $editBtn = $('.aips-edit-author').filter(function() {
				return parseInt($(this).data('id'), 10) === deepLinkId;
			});
			if ($editBtn.length) {
				setTimeout(() => $editBtn.first().trigger('click'), 100);
			}
		}
	},

	openAddModal(e) {
		if (e) e.preventDefault();
		const l10n = window.aipsAuthorsL10n || {};
		$('#aips-author-modal-title').text(l10n.addNewAuthor || 'Add New Author');
		$('#aips-author-form')[0].reset();
		$('#author_id').val('');

		this.currentAuthorId = null;
		$('#aips-author-modal-loader').hide();
		$('#aips-author-form').show();

		$('#author_include_sources').prop('checked', false);
		$('.aips-author-source-group-cb').prop('checked', false);
		$('#author-source-groups-selector').hide();
		$('#aips-author-modal').fadeIn();
	},

	toggleAuthorSourceGroups(e) {
		$('#author-source-groups-selector').toggle($(e.currentTarget).is(':checked'));
	},

	editAuthor(e) {
		e.preventDefault();
		const authorId = $(e.currentTarget).data('id');
		this.currentAuthorId = authorId;
		const l10n = window.aipsAuthorsL10n || {};

		$('#aips-author-modal-title').text(l10n.loading || 'Loading...');
		$('#aips-author-form').hide();
		$('#aips-author-modal-loader').show();
		$('#aips-author-modal').fadeIn();

		const author = new AuthorModel({ id: authorId });
		author.fetch({
			success: (model) => {
				const a = model.toJSON();
				if (String(this.currentAuthorId) !== String(a.id) || !$('#aips-author-modal').is(':visible')) {
					return;
				}
				$('#aips-author-modal-loader').hide();
				$('#aips-author-form').show();
				$('#aips-author-modal-title').text(l10n.editAuthor || 'Edit Author');
				$('#author_id').val(a.id);
				$('#author_name').val(a.name);
				$('#author_field_niche').val(a.field_niche);
				$('#author_description').val(a.description);
				$('#author_keywords').val(a.keywords || '');
				$('#author_details').val(a.details || '');
				$('#article_structure_id').val(a.article_structure_id || '');
				$('#voice_tone').val(a.voice_tone || '');
				$('#writing_style').val(a.writing_style || '');
				$('#author_target_audience').val(a.target_audience || '');
				$('#author_expertise_level').val(a.expertise_level || '');
				$('#author_content_goals').val(a.content_goals || '');
				$('#author_excluded_topics').val(a.excluded_topics || '');
				$('#author_preferred_content_length').val(a.preferred_content_length || '');
				$('#author_language').val(a.language || 'en');
				$('#author_max_posts_per_topic').val(a.max_posts_per_topic || 1);
				$('#author_manual_post_generation_quantity').val(a.manual_post_generation_quantity || 1);
				$('#author_scheduled_post_generation_quantity').val(a.scheduled_post_generation_quantity || 1);
				$('#topic_generation_quantity').val(a.topic_generation_quantity);
				$('#topic_generation_frequency').val(a.topic_generation_frequency);
				$('#post_generation_frequency').val(a.post_generation_frequency);
				$('#is_active').prop('checked', a.is_active == 1);

				const includeSources = a.include_sources == 1;
				$('#author_include_sources').prop('checked', includeSources);
				$('#author-source-groups-selector').toggle(includeSources);
				$('.aips-author-source-group-cb').prop('checked', false);

				let sgIds = [];
				try {
					sgIds = JSON.parse(a.source_group_ids || '[]');
				} catch (err) {
					sgIds = [];
				}
				sgIds.forEach((tid) => {
					$('.aips-author-source-group-cb[value="' + tid + '"]').prop('checked', true);
				});
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || (l10n.errorLoading || 'Error loading author.');
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
				$('#aips-author-modal').fadeOut();
			}
		});
	},

	saveAuthor(e) {
		e.preventDefault();
		const $form = $('#aips-author-form');
		const $submitBtn = $form.find('[type="submit"]');
		const l10n = window.aipsAuthorsL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($submitBtn, l10n.saving || 'Saving...');
		}

		// Gather checked source group checkboxes
		const sgIds = [];
		$('.aips-author-source-group-cb:checked').each(function() {
			sgIds.push($(this).val());
		});

		const data = {
			id: $('#author_id').val(),
			name: $('#author_name').val(),
			field_niche: $('#author_field_niche').val(),
			description: $('#author_description').val(),
			keywords: $('#author_keywords').val(),
			details: $('#author_details').val(),
			article_structure_id: $('#article_structure_id').val(),
			voice_tone: $('#voice_tone').val(),
			writing_style: $('#writing_style').val(),
			target_audience: $('#author_target_audience').val(),
			expertise_level: $('#author_expertise_level').val(),
			content_goals: $('#author_content_goals').val(),
			excluded_topics: $('#author_excluded_topics').val(),
			preferred_content_length: $('#author_preferred_content_length').val(),
			language: $('#author_language').val(),
			max_posts_per_topic: $('#author_max_posts_per_topic').val(),
			manual_post_generation_quantity: $('#author_manual_post_generation_quantity').val(),
			scheduled_post_generation_quantity: $('#author_scheduled_post_generation_quantity').val(),
			topic_generation_quantity: $('#topic_generation_quantity').val(),
			topic_generation_frequency: $('#topic_generation_frequency').val(),
			post_generation_frequency: $('#post_generation_frequency').val(),
			is_active: $('#is_active').is(':checked'),
			include_sources: $('#author_include_sources').is(':checked'),
			source_group_ids: JSON.stringify(sgIds)
		};

		const author = new AuthorModel(data);
		author.save(null, {
			success: (model, response) => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.authorSaved || 'Author saved successfully.', 'success');
				}
				setTimeout(() => location.reload(), 1000);
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || (l10n.errorSaving || 'Error saving author.');
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
					window.AIPS.Utilities.resetButton($submitBtn);
				}
			}
		});
	},

	deleteAuthor(e) {
		e.preventDefault();
		const authorId = $(e.currentTarget).data('id');
		const l10n = window.aipsAuthorsL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(l10n.confirmDelete || 'Are you sure you want to delete this author?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const author = new AuthorModel({ id: authorId });
						author.destroy({
							success: () => {
								window.AIPS.Utilities.showToast(l10n.authorDeleted || 'Author deleted successfully.', 'success');
								setTimeout(() => location.reload(), 1000);
							},
							error: (model, err) => {
								const errMsg = (err && err.message) || (l10n.errorDeleting || 'Error deleting author.');
								window.AIPS.Utilities.showToast(errMsg, 'error');
							}
						});
					}
				}
			]);
		}
	},

	generateTopicsNow(e) {
		e.preventDefault();
		const authorId = $(e.currentTarget).data('id');
		const $btn = $(e.currentTarget);
		const l10n = window.aipsAuthorsL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(l10n.confirmGenerateTopics || 'Generate topics now for this author?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						window.AIPS.Utilities.setButtonLoading($btn, l10n.generating || 'Generating...');
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_generate_topics_now',
								nonce: l10n.nonce || '',
								author_id: authorId
							},
							success: (response) => {
								if (response.success) {
									window.AIPS.Utilities.showToast(response.data.message || l10n.topicsGenerated, 'success');
									setTimeout(() => location.reload(), 1000);
								} else {
									window.AIPS.Utilities.showToast(response.data.message || l10n.errorGenerating, 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(l10n.errorGenerating || 'Error generating topics.', 'error');
							},
							complete: () => {
								window.AIPS.Utilities.resetButton($btn);
							}
						});
					}
				}
			]);
		}
	},

	generateAuthorPostsNow(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const authorId = parseInt($btn.data('id'), 10);
		const type = $btn.data('type') || 'author_post_gen';
		const l10n = window.aipsAuthorsL10n || {};

		if (!Number.isInteger(authorId) || authorId <= 0) return;

		const defaultQuantity = this.getAuthorManualPostQuantity($btn);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.showModal({
				heading: l10n.generatePostsModalTitle || 'Generate Posts',
				message: l10n.generatePostsModalMessage || 'How many posts would you like to generate for this author?',
				fields: [
					{
						type: 'number',
						name: 'quantity',
						label: l10n.numberOfPostsLabel || 'Number of Posts to Generate',
						value: defaultQuantity,
						min: 1,
						max: 10,
						required: true,
						validate: function(value) {
							const num = parseInt(value, 10);
							if (!num || num < 1 || num > 10) {
								return l10n.invalidQuantityError || 'Please enter a valid quantity between 1 and 10.';
							}
							return null;
						}
					}
				],
				buttons: [
					{
						label: l10n.cancel || 'Cancel',
						className: 'aips-btn aips-btn-primary'
					},
					{
						label: l10n.generateButtonLabel || 'Generate',
						className: 'aips-btn aips-btn-author-posts',
						submit: true,
						action: (formData) => {
							window.AIPS.Utilities.setButtonLoading($btn, '<span class="dashicons dashicons-update aips-spin"></span>', { isHtml: true });

							$.ajax({
								url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_unified_run_now',
									nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
									id: authorId,
									type: type,
									quantity: formData.quantity
								},
								success: (response) => {
									if (response.success) {
										let message = response.data && response.data.message ? response.data.message : l10n.postsGenerated;
										if (response.data && response.data.edit_url) {
											message += ' <a href="' + response.data.edit_url + '" target="_blank">' + (l10n.editPost || 'Edit Post') + '</a>';
										}
										window.AIPS.Utilities.showToast(message, 'success', { isHtml: true, duration: 8000 });
									} else {
										window.AIPS.Utilities.showToast(response.data.message || l10n.errorGeneratingPosts, 'error');
									}
								},
								error: () => {
									window.AIPS.Utilities.showToast(l10n.errorGeneratingPosts || 'Error generating posts.', 'error');
								},
								complete: () => {
									window.AIPS.Utilities.resetButton($btn);
								}
							});
						}
					}
				]
			});
		}
	},

	getAuthorManualPostQuantity($btn) {
		const quantity = parseInt($btn.data('quantity'), 10);
		return (!isNaN(quantity) && quantity >= 1) ? quantity : 1;
	},

	closeModals(e) {
		if (e) e.preventDefault();
		const shouldReloadAfterClose = $('#aips-suggest-authors-modal').is(':visible') && this.hasImportedSuggestedAuthor;
		const $visibleModals = $('.aips-modal:visible');

		$visibleModals.fadeOut();

		if (shouldReloadAfterClose) {
			$visibleModals.promise().done(function() {
				window.location.reload();
			});
		}
	},

	openSuggestModal(e) {
		e.preventDefault();
		this.hasImportedSuggestedAuthor = false;
		$('#aips-suggest-authors-results').hide();
		$('#aips-suggest-authors-cards').html('');
		$('#aips-suggest-authors-modal').fadeIn();
	},

	suggestAuthors(e) {
		e.preventDefault();
		const siteNiche = $('#aips-suggest-site-niche').val().trim();
		const l10n = window.aipsAuthorsL10n || {};

		if (!siteNiche) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.siteNicheRequired || 'Site niche is required.', 'warning');
			}
			return;
		}

		const $btn = $('#aips-suggest-authors-submit');
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn,
				'<span class="dashicons dashicons-update aips-spin"></span> ' + (l10n.generatingSuggestions || 'Generating suggestions...'),
				{ isHtml: true }
			);
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_suggest_authors',
				nonce: l10n.nonce || '',
				site_niche: siteNiche,
				target_audience: $('#aips-suggest-target-audience').val().trim(),
				content_goals: $('#aips-suggest-content-goals').val().trim(),
				count: $('#aips-suggest-count').val()
			},
			success: (response) => {
				if (response.success && response.data.suggestions && response.data.suggestions.length > 0) {
					this.renderSuggestedAuthors(response.data.suggestions);
					$('#aips-suggest-authors-results').show();
				} else {
					const msg = response.data.message || (l10n.errorGeneratingSuggestions || 'Error generating author suggestions.');
					window.AIPS.Utilities.showToast(msg, 'error');
				}
			},
			error: () => {
				window.AIPS.Utilities.showToast(l10n.errorGeneratingSuggestions || 'Error generating author suggestions.', 'error');
			},
			complete: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	renderSuggestedAuthors(suggestions) {
		const l10n = window.aipsAuthorsL10n || {};
		let html = '';

		suggestions.forEach((suggestion, index) => {
			let metaRows = '';
			const metaFields = [
				{ key: 'keywords', label: l10n.keywordsLabel || 'Keywords' },
				{ key: 'voice_tone', label: l10n.voiceToneLabel || 'Voice/Tone' },
				{ key: 'writing_style', label: l10n.writingStyleLabel || 'Writing Style' },
				{ key: 'topic_generation_prompt', label: l10n.topicPromptLabel || 'Topic Generation Prompt' }
			];

			metaFields.forEach(field => {
				if (suggestion[field.key]) {
					metaRows += window.AIPS.Templates.render('aips-tmpl-suggestion-meta-row', {
						label: field.label,
						value: suggestion[field.key]
					});
				}
			});

			const importLabel = l10n.importAuthor || 'Import Author';
			const ariaLabel = importLabel + ': ' + (suggestion.name || '');

			html += window.AIPS.Templates.render('aips-tmpl-suggestion-card', {
				index: index,
				name: suggestion.name || '',
				field_niche: suggestion.field_niche || '',
				description: suggestion.description || '',
				meta: metaRows,
				importLabel: importLabel,
				importAriaLabel: ariaLabel
			});
		});

		const $cards = $('#aips-suggest-authors-cards');
		$cards.html(html);
		$cards.data('suggestions', suggestions);
	},

	importSuggestedAuthor(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const index = parseInt($btn.data('index'), 10);
		const suggestions = $('#aips-suggest-authors-cards').data('suggestions');
		const l10n = window.aipsAuthorsL10n || {};

		if (!suggestions || !suggestions[index]) return;

		const suggestion = suggestions[index];
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn,
				'<span class="dashicons dashicons-update aips-spin"></span> ' + (l10n.importingAuthor || 'Importing...'),
				{ isHtml: true }
			);
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_save_author',
				nonce: l10n.nonce || '',
				name: suggestion.name,
				field_niche: suggestion.field_niche,
				description: suggestion.description || '',
				details: suggestion.details || '',
				keywords: suggestion.keywords || '',
				voice_tone: suggestion.voice_tone || '',
				writing_style: suggestion.writing_style || '',
				topic_generation_prompt: suggestion.topic_generation_prompt || '',
				target_audience: suggestion.target_audience || '',
				expertise_level: suggestion.expertise_level || '',
				content_goals: suggestion.content_goals || '',
				excluded_topics: suggestion.excluded_topics || '',
				preferred_content_length: suggestion.preferred_content_length || '',
				language: suggestion.language || 'en',
				max_posts_per_topic: suggestion.max_posts_per_topic || 1,
				topic_generation_frequency: 'weekly',
				topic_generation_quantity: 5,
				post_generation_frequency: 'daily',
				post_status: 'draft',
				is_active: 1
			},
			success: (response) => {
				if (response.success) {
					this.hasImportedSuggestedAuthor = true;
					window.AIPS.Utilities.showToast(l10n.authorImported || 'Author imported successfully.', 'success');
					$btn.prop('disabled', true).html(
						'<span class="dashicons dashicons-yes"></span> ' + (l10n.importedAuthor || 'Imported Author')
					);
				} else {
					const msg = response.data.message || (l10n.errorImportingAuthor || 'Error importing author.');
					window.AIPS.Utilities.showToast(msg, 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			},
			error: () => {
				window.AIPS.Utilities.showToast(l10n.errorImportingAuthor || 'Error importing author.', 'error');
				window.AIPS.Utilities.resetButton($btn);
			}
		});
	},

	toggleSelectAllAuthors(e) {
		const isChecked = $(e.currentTarget).prop('checked');
		$('.aips-author-checkbox').prop('checked', isChecked);
	},

	executeAuthorsBulkAction(e) {
		e.preventDefault();
		const action = $('#aips-authors-bulk-action-select').val();
		const authorIds = $('.aips-author-checkbox:checked').map(function() {
			return parseInt($(this).val(), 10);
		}).get().filter(id => Number.isInteger(id) && id > 0);

		const l10n = window.aipsAuthorsL10n || {};

		if (!action) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.selectBulkAction || 'Please select a bulk action.', 'warning');
			}
			return;
		}

		if (authorIds.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.noAuthorsSelected || 'Please select at least one author.', 'warning');
			}
			return;
		}

		if (action === 'generate_topics') {
			this.bulkGenerateTopics(authorIds);
		} else if (action === 'delete') {
			this.bulkDeleteAuthors(authorIds);
		}
	},

	bulkGenerateTopics(authorIds) {
		const l10n = window.aipsAuthorsL10n || {};
		const message = (l10n.confirmGenerateTopicsBulk || 'Generate topics now for %d selected author(s)?').replace('%d', authorIds.length);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(message, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = authorIds.map((authorId) => {
							return $.ajax({
								url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_generate_topics_now',
									nonce: l10n.nonce || '',
									author_id: authorId
								}
							});
						});

						Promise.allSettled(requests).then((results) => {
							const successCount = results.filter((r) => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								window.AIPS.Utilities.showToast((l10n.topicsGeneratedBulk || '%d author(s) queued for topic generation.').replace('%d', successCount), 'success');
								setTimeout(() => location.reload(), 800);
							} else {
								window.AIPS.Utilities.showToast(l10n.errorGenerating || 'Error generating topics.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	bulkDeleteAuthors(authorIds) {
		const l10n = window.aipsAuthorsL10n || {};
		const message = (l10n.confirmDeleteBulk || 'Delete %d selected author(s)?').replace('%d', authorIds.length);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(message, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = authorIds.map((authorId) => {
							return $.ajax({
								url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_delete_author',
									nonce: l10n.nonce || '',
									author_id: authorId
								}
							});
						});

						Promise.allSettled(requests).then((results) => {
							const successCount = results.filter((r) => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								window.AIPS.Utilities.showToast((l10n.authorDeletedBulk || '%d author(s) deleted.').replace('%d', successCount), 'success');
								setTimeout(() => location.reload(), 800);
							} else {
								window.AIPS.Utilities.showToast(l10n.errorDeleting || 'Error deleting authors.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	handleSharedTabSwitch(e, tabId) {
		if (!$('#generation-queue-tab').length) return;
		if (tabId === 'generation-queue') {
			this.loadQueueTopics();
		}
	},

	loadQueueTopics() {
		const l10n = window.aipsAuthorsL10n || {};
		$('#aips-queue-topics-list').html('<div class="aips-panel-body"><p>' + (l10n.loadingQueue || 'Loading queue...') + '</p></div>');
		$('#aips-queue-tablenav').hide();

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_generation_queue',
				nonce: l10n.nonce || ''
			},
			success: (response) => {
				if (response.success && response.data.topics) {
					this.queueTopics = response.data.topics;
					this.populateQueueFilters();
					this.applyQueueFilters();
				} else {
					$('#aips-queue-topics-list').html(
						'<div class="aips-panel-body"><p>' + (response.data.message || (l10n.errorLoadingQueue || 'Error loading queue.')) + '</p></div>'
					);
					$('#aips-queue-tablenav').hide();
				}
			},
			error: () => {
				$('#aips-queue-topics-list').html('<div class="aips-panel-body"><p>' + (l10n.errorLoadingQueue || 'Error loading queue.') + '</p></div>');
				$('#aips-queue-tablenav').hide();
			}
		});
	},

	populateQueueFilters() {
		const authorValue = $('#aips-queue-author-filter').val() || '';
		const fieldValue = $('#aips-queue-field-filter').val() || '';
		const authorSet = new Set();
		const fieldSet = new Set();

		this.queueTopics.forEach(topic => {
			if (topic.author_name) authorSet.add(topic.author_name);
			if (topic.field_niche) fieldSet.add(topic.field_niche);
		});

		const authors = Array.from(authorSet).sort((a, b) => a.localeCompare(b));
		const fields = Array.from(fieldSet).sort((a, b) => a.localeCompare(b));

		const $authorFilter = $('#aips-queue-author-filter');
		const $fieldFilter = $('#aips-queue-field-filter');

		$authorFilter.find('option:not(:first)').remove();
		$fieldFilter.find('option:not(:first)').remove();

		authors.forEach(author => {
			$authorFilter.append($('<option>').val(author).text(author));
		});

		fields.forEach(field => {
			$fieldFilter.append($('<option>').val(field).text(field));
		});

		$authorFilter.val(authorValue);
		$fieldFilter.val(fieldValue);
	},

	applyQueueFilters() {
		const selectedAuthor = $('#aips-queue-author-filter').val() || '';
		const selectedField = $('#aips-queue-field-filter').val() || '';
		const searchTerm = ($('#aips-queue-search').val() || '').toLowerCase().trim();

		this.queueCurrentPage = 1;
		this.filteredQueueTopics = this.queueTopics.filter(topic => {
			const matchesAuthor = !selectedAuthor || topic.author_name === selectedAuthor;
			const matchesField = !selectedField || topic.field_niche === selectedField;
			const haystack = ((topic.topic_title || '') + ' ' + (topic.author_name || '') + ' ' + (topic.field_niche || '')).toLowerCase();
			const matchesSearch = !searchTerm || haystack.indexOf(searchTerm) !== -1;
			return matchesAuthor && matchesField && matchesSearch;
		});

		$('#aips-queue-search-clear').toggle(searchTerm.length > 0);
		this.renderQueueTopics();
	},

	clearQueueSearch(e) {
		e.preventDefault();
		$('#aips-queue-search').val('');
		this.applyQueueFilters();
		$('#aips-queue-search').focus();
	},

	renderQueueTopics() {
		const topics = this.filteredQueueTopics;
		const l10n = window.aipsAuthorsL10n || {};

		if (!topics || topics.length === 0) {
			$('#aips-queue-topics-list').html(
				'<div class="aips-panel-body"><div class="aips-empty-state">'
				+ '<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>'
				+ '<h3 class="aips-empty-state-title">' + (l10n.noQueueTopicsTitle || 'No Queue Topics Found') + '</h3>'
				+ '<p class="aips-empty-state-description">' + (l10n.noQueueTopics || 'No approved topics in the queue yet.') + '</p>'
				+ '</div></div>'
			);
			$('#aips-queue-tablenav').hide();
			return;
		}

		const totalItems = topics.length;
		const totalPages = Math.max(1, Math.ceil(totalItems / this.queuePerPage));
		if (this.queueCurrentPage > totalPages) {
			this.queueCurrentPage = totalPages;
		}
		const start = (this.queueCurrentPage - 1) * this.queuePerPage;
		const pageItems = topics.slice(start, start + this.queuePerPage);

		let rowsHtml = '';
		pageItems.forEach(topic => {
			rowsHtml += window.AIPS.Templates.render('aips-tmpl-queue-row', {
				id: topic.id,
				title: topic.topic_title,
				author: topic.author_name,
				field: topic.field_niche,
				date: topic.reviewed_at || l10n.notAvailable || 'N/A'
			});
		});

		const tableHtml = window.AIPS.Templates.render('aips-tmpl-queue-table', {
			titleLabel: l10n.topicTitle || 'Topic Title',
			authorLabel: l10n.author || 'Author',
			fieldLabel: l10n.fieldNiche || 'Field/Niche',
			dateLabel: l10n.approvedDate || 'Approved Date',
			rows: rowsHtml
		});

		$('#aips-queue-topics-list').html(tableHtml);

		const topicLabel = totalItems === 1 ? (l10n.topic || 'topic') : (l10n.topics || 'topics');
		$('#aips-queue-table-footer-count').text(totalItems + ' ' + topicLabel);
		this.renderQueuePagination(totalPages);
		$('#aips-queue-tablenav').show();
	},

	renderQueuePagination(totalPages) {
		const current = this.queueCurrentPage;

		if (totalPages <= 1) {
			$('#aips-queue-pagination-links').html('');
			return;
		}

		let pagesHtml = '';
		const start = Math.max(1, current - 3);
		const end = Math.min(totalPages, current + 3);

		if (start > 1) {
			pagesHtml += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="1">1</button>';
			if (start > 2) {
				pagesHtml += '<span class="aips-history-page-ellipsis">…</span>';
			}
		}

		for (let p = start; p <= end; p++) {
			if (p === current) {
				pagesHtml += '<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page">' + p + '</span>';
			} else {
				pagesHtml += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="' + p + '">' + p + '</button>';
			}
		}

		if (end < totalPages) {
			if (end < totalPages - 1) {
				pagesHtml += '<span class="aips-history-page-ellipsis">…</span>';
			}
			pagesHtml += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="' + totalPages + '">' + totalPages + '</button>';
		}

		const paginationHtml = window.AIPS.Templates.render('aips-tmpl-queue-pagination', {
			prevPage: current - 1,
			prevDisabled: current <= 1 ? 'disabled' : '',
			nextPage: current + 1,
			nextDisabled: current >= totalPages ? 'disabled' : '',
			pages: pagesHtml
		});

		$('#aips-queue-pagination-links').html(paginationHtml);
	},

	goToQueuePage(e) {
		e.preventDefault();
		const page = parseInt($(e.currentTarget).data('page'), 10);
		if (!Number.isInteger(page) || page < 1) return;
		this.queueCurrentPage = page;
		this.renderQueueTopics();
	},

	toggleQueueSelectAll(e) {
		const isChecked = $(e.currentTarget).prop('checked');
		$('.aips-queue-topic-checkbox').prop('checked', isChecked);
	},

	executeQueueBulkAction(e) {
		e.preventDefault();
		const action = $('#aips-queue-bulk-action-select').val();
		const l10n = window.aipsAuthorsL10n || {};

		if (!action) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.selectBulkAction || 'Please select a bulk action.', 'warning');
			}
			return;
		}

		const topicIds = [];
		$('.aips-queue-topic-checkbox:checked').each(function() {
			topicIds.push($(this).val());
		});

		if (topicIds.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.noTopicsSelected || 'Please select at least one topic.', 'warning');
			}
			return;
		}

		switch (action) {
			case 'generate_now':
				this.generateNowFromQueue(topicIds);
				break;
			default:
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.invalidAction || 'Invalid action.', 'error');
				}
		}
	},

	generateNowFromQueue(topicIds) {
		const l10n = window.aipsAuthorsL10n || {};
		const confirmMessage = (l10n.confirmGenerateFromQueue || 'Generate posts now for %d selected topic(s)?').replace('%d', topicIds.length);
		const $button = $('.aips-queue-bulk-action-execute');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMessage, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						window.AIPS.Utilities.setButtonLoading($button, l10n.generating || 'Generating...');

						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_bulk_generate_from_queue',
								nonce: l10n.nonce || '',
								topic_ids: topicIds
							},
							success: (response) => {
								if (response.success) {
									window.AIPS.Utilities.showToast(
										response.data.message || l10n.postsGenerated || 'Posts generated successfully.',
										'success'
									);
									this.loadQueueTopics();
								} else {
									window.AIPS.Utilities.showToast(response.data.message || l10n.errorGenerating, 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(l10n.errorGenerating || 'Error generating posts.', 'error');
							},
							complete: () => {
								window.AIPS.Utilities.resetButton($button);
							}
						});
					}
				}
			]);
		}
	}
});
