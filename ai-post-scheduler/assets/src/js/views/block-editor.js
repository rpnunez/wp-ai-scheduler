import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseModalView } from './base-modal';

/**
 * Block Editor, AI Assistance & Taxonomy Assigner View Controller
 */
export const BlockEditorView = Backbone.View.extend({
	el: 'body',

	events: {
		// AI Field Assistance Events
		'click .aips-ai-assist-btn': 'onAssistClick',
		'click .aips-ai-assist-history-btn': 'onHistoryClick',
		'click #aips-ai-assist-history-modal .aips-modal-close': 'closeHistoryModal',
		'click .aips-ai-assist-history-use': 'useHistoryValue',

		// Taxonomy Events
		'click #aips-open-generate-modal': 'openGenerateModal',
		'click #aips-generate-taxonomy-modal .aips-modal-close': 'closeTaxonomyModal',
		'submit #aips-generate-taxonomy-form': 'generateTaxonomy',
		'keyup #base_posts': 'searchPosts',
		'click .aips-remove-post': 'removeSelectedPost',
		'click .aips-search-result': 'selectSearchResult',
		'click .aips-taxonomy-tab-link': 'switchTaxonomyTab', // Renamed from aips-tab-link to avoid conflicts
		'click .aips-select-all-taxonomy': 'toggleSelectAllTaxonomy',
		'change .aips-taxonomy-checkbox': 'syncSelectAllTaxonomyState',
		'click .aips-bulk-action-execute': 'executeTaxonomyBulkAction',
		'click .aips-approve-taxonomy': 'approveTaxonomy',
		'click .aips-reject-taxonomy': 'rejectTaxonomy',
		'click .aips-delete-taxonomy': 'deleteTaxonomy',
		'click .aips-create-term': 'createTerm',
		'keyup #aips-taxonomy-search': 'filterTaxonomyItems',
		'search #aips-taxonomy-search': 'filterTaxonomyItems',
		'click #aips-taxonomy-search-clear': 'clearTaxonomySearch'
	},

	initialize() {
		this.l10n = window.aipsAIAssistanceL10n || {};
		this.taxL10n = window.aipsTaxonomyL10n || {};
		this.adminL10n = window.aipsAdminL10n || {};

		// Field maps for AI assistance
		this.fieldMaps = {
			authors: {
				author_name: {
					fieldName: 'Name',
					description: 'The display name for this AI author persona',
					influence: 'Sets the author byline on generated posts; used in topic-generation prompts as the attributed author',
					expectedResponse: 'A short, memorable author pen name (1–3 words)'
				},
				author_field_niche: {
					fieldName: 'Field/Niche',
					description: 'The subject-matter niche this author specialises in',
					influence: 'Focuses AI topic generation and content toward this niche',
					expectedResponse: 'A concise niche description (1–5 words, e.g. "Personal Finance for Millennials")'
				},
				author_keywords: {
					fieldName: 'Keywords',
					description: 'Comma-separated keywords associated with this author\'s content',
					influence: 'Guides keyword selection during post and topic generation',
					expectedResponse: 'A comma-separated list of 5–10 relevant keywords'
				},
				author_details: {
					fieldName: 'Details',
					description: 'Short background details about this author persona',
					influence: 'Provides context to the AI when generating posts in the author\'s voice',
					expectedResponse: 'Two to four sentences describing the author\'s background and expertise'
				},
				author_description: {
					fieldName: 'Description',
					description: 'A longer public-facing bio or description for this author',
					influence: 'May be appended to generated posts as an author bio section',
					expectedResponse: 'A 3–5 sentence author biography in first or third person'
				},
				voice_tone: {
					fieldName: 'Tone',
					description: 'The emotional tone this author uses when writing',
					influence: 'Instructs the AI to match this tone throughout every generated post',
					expectedResponse: 'One to three descriptive words (e.g. "friendly, authoritative, approachable")'
				},
				writing_style: {
					fieldName: 'Writing Style',
					description: 'The structural and stylistic approach this author takes',
					influence: 'Shapes sentence length, vocabulary, and narrative style in AI-generated content',
					expectedResponse: 'A brief style description (e.g. "conversational with data-driven examples")'
				},
				author_target_audience: {
					fieldName: 'Target Audience',
					description: 'The intended readership for this author\'s content',
					influence: 'Tailors complexity, vocabulary, and examples to this audience in generated posts',
					expectedResponse: 'A concise audience description (e.g. "beginner home cooks aged 25–45")'
				},
				author_content_goals: {
					fieldName: 'Content Goals',
					description: 'The primary objectives this author\'s content should achieve',
					influence: 'Aligns AI-generated post structure and calls-to-action with these goals',
					expectedResponse: 'One to three goal statements (e.g. "educate readers, drive newsletter signups")'
				},
				author_excluded_topics: {
					fieldName: 'Excluded Topics',
					description: 'Topics or subject areas this author should never write about',
					influence: 'Prevents the AI from generating posts or topics in these areas',
					expectedResponse: 'A comma-separated list of excluded topics or themes'
				}
			}
		};

		// AI Assist state
		this.assistSessionId = this.generateSessionId();
		this.formContext = 'authors';
		this.historyRecordCache = {};

		// Taxonomy state
		this.selectedPostIds = [];
		this.currentTaxTab = 'categories';
		this.searchTimeout = null;

		// Initialize modals
		if (this.$('#aips-ai-assist-history-modal').length) {
			this.assistHistoryModal = new BaseModalView({ el: '#aips-ai-assist-history-modal' });
		}
		if (this.$('#aips-generate-taxonomy-modal').length) {
			this.generateTaxonomyModal = new BaseModalView({ el: '#aips-generate-taxonomy-modal' });
		}

		// Inject sparkle buttons if we're on the authors screen
		if (this.$('.aips-ai-assist-wrap').length || this.$('#author_name').length) {
			this.injectAssistButtons();
		}

		// Load initial taxonomy table if taxonomy container is active
		if (this.$('#aips-taxonomy-loading').length) {
			this.loadTaxonomyItems('categories');
		}

		// Bind globally on window for legacy compat
		window.AIPS = window.AIPS || {};
		window.AIPS.AIAssistance = this;
		window.AIPS.Taxonomy = this;
	},

	// -------------------------------------------------------------------------
	// AI Assist Methods
	// -------------------------------------------------------------------------
	generateSessionId() {
		if (typeof crypto !== 'undefined' && crypto.randomUUID) {
			return crypto.randomUUID();
		}
		const buf = new Uint8Array(16);
		if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
			crypto.getRandomValues(buf);
			return Array.prototype.map.call(buf, (b) => ('0' + b.toString(16)).slice(-2)).join('');
		}
		return Date.now().toString(36) + Math.random().toString(36).slice(2);
	},

	injectAssistButtons() {
		const fieldMap = this.fieldMaps[this.formContext];
		if (!fieldMap) return;

		const T = window.AIPS.Templates;

		$.each(fieldMap, (fieldId) => {
			const $field = this.$('#' + fieldId);
			if (!$field.length) return;

			const $formGroup = $field.closest('.form-group');
			if ($formGroup.length) {
				$formGroup.addClass('aips-ai-assist-wrap');
			}

			if (T) {
				const btnHtml = T.renderRaw('aips-tmpl-ai-assist-btn', { fieldId: fieldId });
				const $description = $field.nextAll('.description').first();

				if ($description.length) {
					$description.before(btnHtml);
				} else {
					$field.after(btnHtml);
				}
			}
		});
	},

	onAssistClick(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const fieldId = $btn.attr('data-field-id') || $btn.data('field-id');
		const fieldConfig = this.fieldMaps[this.formContext][fieldId];

		if (!fieldConfig) return;

		const $field = this.$('#' + fieldId);
		const currentValue = $field.val() || '';
		const authorName = this.$('#author_name').val() || '';
		const fieldNiche = this.$('#author_field_niche').val() || '';

		$btn.prop('disabled', true).addClass('loading');
		const $label = $btn.find('.aips-ai-assist-btn-label');
		const originalLabel = $label.text();
		$label.text(this.l10n.suggesting || 'Suggesting…');

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_ai_field_assist',
			nonce: this.l10n.nonce || '',
			form_context: this.formContext,
			field_key: fieldId,
			field_name: fieldConfig.fieldName,
			description: fieldConfig.description,
			influence: fieldConfig.influence,
			expected_response: fieldConfig.expectedResponse,
			current_value: currentValue,
			session_id: this.assistSessionId,
			author_name: authorName,
			field_niche: fieldNiche
		}, (response) => {
			$btn.prop('disabled', false).removeClass('loading');
			$label.text(originalLabel);

			if (response.success && response.data && typeof response.data.response !== 'undefined') {
				$field.val(response.data.response).trigger('change');
				$btn.closest('.aips-ai-assist-btn-group').find('.aips-ai-assist-history-btn[data-field-id="' + fieldId + '"]').show();

				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(this.l10n.suggested || 'AI suggestion applied.', 'success');
				}
			} else {
				const errMsg = (response.data && response.data.message) || this.l10n.errorSuggesting || 'Could not get AI suggestion.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
			}
		}).fail(() => {
			$btn.prop('disabled', false).removeClass('loading');
			$label.text(originalLabel);
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(this.l10n.errorSuggesting || 'Could not get AI suggestion.', 'error');
			}
		});
	},

	onHistoryClick(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const fieldId = $btn.attr('data-field-id') || $btn.data('field-id');
		const fieldConfig = this.fieldMaps[this.formContext][fieldId];
		const fieldName = fieldConfig ? fieldConfig.fieldName : fieldId;

		this.$('#aips-ai-assist-history-field-label').text(fieldName);
		this.$('#aips-ai-assist-history-session-tab').html('<p class="description">' + (this.l10n.loading || 'Loading…') + '</p>');
		this.$('#aips-ai-assist-history-alltime-tab').html('<p class="description">' + (this.l10n.loading || 'Loading…') + '</p>');

		this.$('#aips-ai-assist-history-modal .aips-tab-link[data-tab="aips-ai-assist-history-session"]').addClass('active');
		this.$('#aips-ai-assist-history-modal .aips-tab-link[data-tab="aips-ai-assist-history-alltime"]').removeClass('active');
		this.$('#aips-ai-assist-history-session-tab').show();
		this.$('#aips-ai-assist-history-alltime-tab').hide();

		if (this.assistHistoryModal) {
			this.assistHistoryModal.open();
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_get_field_assist_history',
			nonce: this.l10n.nonce || '',
			form_context: this.formContext,
			field_key: fieldId,
			session_id: this.assistSessionId
		}, (response) => {
			if (response.success && response.data) {
				this.renderHistoryTab('#aips-ai-assist-history-session-tab', response.data.session, fieldId);
				this.renderHistoryTab('#aips-ai-assist-history-alltime-tab', response.data.alltime, fieldId);
			} else {
				const noHistoryMsg = this.l10n.noHistory || 'No AI suggestions found.';
				this.$('#aips-ai-assist-history-session-tab').html('<p class="description">' + noHistoryMsg + '</p>');
				this.$('#aips-ai-assist-history-alltime-tab').html('<p class="description">' + noHistoryMsg + '</p>');
			}
		}).fail(() => {
			const noHistoryMsg = this.l10n.noHistory || 'No AI suggestions found.';
			this.$('#aips-ai-assist-history-session-tab').html('<p class="description">' + noHistoryMsg + '</p>');
			this.$('#aips-ai-assist-history-alltime-tab').html('<p class="description">' + noHistoryMsg + '</p>');
		});
	},

	renderHistoryTab(selector, records, fieldId) {
		const $container = this.$(selector);
		const noHistoryMsg = this.l10n.noHistory || 'No AI suggestions found.';
		const T = window.AIPS.Templates;

		if (!records || !records.length) {
			$container.html('<p class="description">' + noHistoryMsg + '</p>');
			return;
		}

		let html = '';
		records.forEach((record) => {
			this.historyRecordCache[record.id] = record.response;
			if (T) {
				html += T.render('aips-tmpl-ai-assist-history-item', {
					id: record.id,
					response: record.response,
					created_at: record.created_at,
					fieldId: fieldId
				});
			}
		});
		$container.html(html);
	},

	useHistoryValue(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const fieldId = $btn.attr('data-field-id') || $btn.data('field-id');
		const recordId = $btn.attr('data-record-id') || $btn.data('record-id');
		const value = this.historyRecordCache[recordId] || '';

		this.$('#' + fieldId).val(value).trigger('change');
		this.closeHistoryModal();

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.showToast(this.l10n.valueApplied || 'Value applied.', 'success');
		}
	},

	closeHistoryModal() {
		if (this.assistHistoryModal) {
			this.assistHistoryModal.close();
		}
	},

	// -------------------------------------------------------------------------
	// Taxonomy Methods
	// -------------------------------------------------------------------------
	openGenerateModal(e) {
		if (e) e.preventDefault();
		this.selectedPostIds = [];
		this.$('#aips-generate-taxonomy-form')[0].reset();
		this.$('#base-post-search-results').empty();
		this.$('#selected-posts-container').empty();

		if (this.generateTaxonomyModal) {
			this.generateTaxonomyModal.open();
		}
	},

	closeTaxonomyModal(e) {
		if (e) e.preventDefault();
		if (this.generateTaxonomyModal) {
			this.generateTaxonomyModal.close();
		}
	},

	searchPosts(e) {
		const searchTerm = $(e.currentTarget).val();
		if (searchTerm.length < 3) {
			this.$('#base-post-search-results').empty();
			return;
		}

		clearTimeout(this.searchTimeout);
		this.searchTimeout = setTimeout(() => {
			$.ajax({
				url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'aips_search_posts',
					nonce: this.taxL10n.nonce || '',
					search_term: searchTerm
				},
				success: (response) => {
					if (response.success && response.data.posts) {
						this.displayPostSearchResults(response.data.posts);
					}
				}
			});
		}, 300);
	},

	selectSearchResult(e) {
		e.preventDefault();
		const $result = $(e.currentTarget);
		const postId = Number($result.attr('data-post-id') || $result.data('post-id'));
		const postTitle = $result.find('span').text();

		this.addSelectedPost(postId, postTitle);
		$result.remove();
	},

	displayPostSearchResults(posts) {
		const container = this.$('#base-post-search-results');
		let html = '';
		const esc = _.escape;

		posts.forEach((post) => {
			if (this.selectedPostIds.indexOf(post.id) === -1) {
				html += '<div class="aips-search-result" data-post-id="' + post.id + '" style="cursor: pointer; padding: 5px; border-bottom: 1px solid #ddd;">';
				html += '<span>' + esc(post.title) + '</span>';
				html += '</div>';
			}
		});

		if (html) {
			container.html(html);
		} else {
			container.empty();
		}
	},

	addSelectedPost(postId, postTitle) {
		if (this.selectedPostIds.indexOf(postId) !== -1) return;

		this.selectedPostIds.push(postId);
		const T = window.AIPS.Templates;

		if (T) {
			const html = T.renderRaw('aips-tmpl-selected-post', {
				id: postId,
				title: T.escape(postTitle)
			});
			this.$('#selected-posts-container').append(html);
		}

		this.$('#base-post-search-results').empty();
		this.$('#base_posts').val('');
	},

	removeSelectedPost(e) {
		e.preventDefault();
		const postId = $(e.currentTarget).attr('data-post-id') || $(e.currentTarget).data('post-id');
		const index = this.selectedPostIds.indexOf(postId);

		if (index > -1) {
			this.selectedPostIds.splice(index, 1);
		}
		$(e.currentTarget).closest('.aips-selected-post').remove();
	},

	generateTaxonomy(e) {
		e.preventDefault();
		const taxonomyType = this.$('#taxonomy_type').val();
		const generationPrompt = this.$('#generation_prompt').val();

		if (!taxonomyType) {
			alert(this.taxL10n.selectTaxonomyType || 'Please select a taxonomy type.');
			return;
		}

		if (this.selectedPostIds.length === 0) {
			alert(this.taxL10n.selectPost || 'Please select at least one post.');
			return;
		}

		const submitBtn = this.$('#generate-taxonomy-submit-btn');
		submitBtn.prop('disabled', true).text(this.taxL10n.generating || 'Generating...');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aips_generate_taxonomy',
				nonce: this.taxL10n.nonce || '',
				taxonomy_type: taxonomyType,
				generation_prompt: generationPrompt,
				base_post_ids: this.selectedPostIds
			},
			success: (response) => {
				if (response.success) {
					alert(response.data.message);
					this.updateStats(response.data.stats || null);
					this.closeTaxonomyModal();
					this.loadTaxonomyItems(this.currentTaxTab);
				} else {
					alert(response.data.message || this.taxL10n.generationFailed || 'Failed.');
				}
			},
			complete: () => {
				submitBtn.prop('disabled', false).text(this.taxL10n.generate || 'Generate');
			}
		});
	},

	switchTaxonomyTab(e) {
		e.preventDefault();
		const tab = $(e.currentTarget).attr('data-tab') || $(e.currentTarget).data('tab');

		this.$('.aips-taxonomy-tab-link').removeClass('active');
		$(e.currentTarget).addClass('active');

		this.currentTaxTab = tab;
		this.loadTaxonomyItems(tab);
	},

	loadTaxonomyItems(tab) {
		const taxonomyType = tab === 'categories' ? 'category' : 'post_tag';
		const activeSearchTerm = this.$('#aips-taxonomy-search').val();

		this.$('#aips-taxonomy-loading').show();
		this.$('#aips-taxonomy-content').hide();

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aips_get_taxonomy_items',
				nonce: this.taxL10n.nonce || '',
				taxonomy_type: taxonomyType
			},
			success: (response) => {
				if (response.success) {
					this.updateStats(response.data.stats || null);
					this.renderTaxonomyItems(response.data.items);
					if (activeSearchTerm) {
						this.$('#aips-taxonomy-search').trigger('search');
					}
				}
			},
			complete: () => {
				this.$('#aips-taxonomy-loading').hide();
				this.$('#aips-taxonomy-content').show();
			}
		});
	},

	renderTaxonomyItems(items) {
		let rowsHtml = '';
		const esc = _.escape;
		const T = window.AIPS.Templates;

		items.forEach((item) => {
			const actions = this.renderItemActions(item);
			if (T) {
				rowsHtml += T.renderRaw('aips-tmpl-taxonomy-row', {
					id: item.id,
					name: esc(item.name),
					taxonomy_type: item.taxonomy_type,
					status: item.status,
					status_label: esc(this.toTitleCase(item.status)),
					generated_at: esc(item.created_at),
					actions: actions
				});
			}
		});

		if (!rowsHtml) {
			rowsHtml = '<tr><td colspan="5" style="text-align: center;">No items found.</td></tr>';
		}

		if (T) {
			const tableHtml = T.renderRaw('aips-tmpl-taxonomy-table', {
				selectAllLabel: 'Select all taxonomy items',
				nameLabel: 'Name',
				statusLabel: 'Status',
				generatedAtLabel: 'Generated',
				actionsLabel: 'Actions',
				rows: rowsHtml
			});
			this.$('#aips-taxonomy-content').html(tableHtml);
		}
		this.updateVisibleResultCount();
	},

	renderItemActions(item) {
		let templateId = '';
		let createControl = '';
		const T = window.AIPS.Templates;

		if (item.status === 'pending') {
			templateId = 'aips-tmpl-taxonomy-actions-pending';
		} else if (item.status === 'approved') {
			templateId = 'aips-tmpl-taxonomy-actions-approved';
			if (item.term_id && Number(item.term_id) > 0) {
				createControl = '<span class="aips-taxonomy-term-created">Term Created</span>';
			} else {
				createControl = '<button class="aips-btn aips-btn-sm aips-btn-secondary aips-create-term" data-id="' + item.id + '">Create Term</button>';
			}
		} else if (item.status === 'rejected') {
			templateId = 'aips-tmpl-taxonomy-actions-rejected';
		} else if (item.status === 'created') {
			return '<span class="aips-taxonomy-term-created">Term Created</span>';
		}

		if (!templateId || !T) return '';

		return T.renderRaw(templateId, {
			id: item.id,
			approveLabel: 'Approve',
			rejectLabel: 'Reject',
			deleteLabel: 'Delete',
			createControl: createControl
		});
	},

	toggleSelectAllTaxonomy(e) {
		const isChecked = $(e.currentTarget).prop('checked');
		this.$('.aips-taxonomy-checkbox').prop('checked', isChecked);
	},

	syncSelectAllTaxonomyState() {
		const $checkboxes = this.$('.aips-taxonomy-checkbox');
		const allChecked = $checkboxes.length > 0 && $checkboxes.length === $checkboxes.filter(':checked').length;
		this.$('.aips-select-all-taxonomy').prop('checked', allChecked);
	},

	executeTaxonomyBulkAction(e) {
		e.preventDefault();
		const action = this.$('.aips-bulk-action-select').val();
		if (!action) {
			alert(this.taxL10n.selectAction || 'Please select an action.');
			return;
		}

		const itemIds = [];
		this.$('.aips-taxonomy-checkbox:checked').each(function() {
			itemIds.push($(this).val());
		});

		if (itemIds.length === 0) {
			alert(this.taxL10n.selectItem || 'Please select items.');
			return;
		}

		const ajaxAction = action === 'generate_terms' ? 'aips_bulk_create_taxonomy_terms' : 'aips_bulk_' + action + '_taxonomy';
		const actionLabel = action === 'generate_terms' ? 'generate terms for' : action;
		const confirmMsg = (this.taxL10n.confirmBulkAction || 'Execute action %s on %d items?').replace('%s', actionLabel).replace('%d', itemIds.length);

		if (!confirm(confirmMsg)) return;

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			data: {
				action: ajaxAction,
				nonce: this.taxL10n.nonce || '',
				item_ids: itemIds
			},
			success: (response) => {
				if (response.success) {
					alert(response.data.message);
					this.updateStats(response.data.stats || null);
					this.loadTaxonomyItems(this.currentTaxTab);
				} else {
					alert(response.data.message || this.taxL10n.actionFailed || 'Failed.');
				}
			}
		});
	},

	approveTaxonomy(e) {
		e.preventDefault();
		const itemId = $(e.currentTarget).attr('data-id') || $(e.currentTarget).data('id');
		this.updateItemStatus(itemId, 'aips_approve_taxonomy');
	},

	rejectTaxonomy(e) {
		e.preventDefault();
		const itemId = $(e.currentTarget).attr('data-id') || $(e.currentTarget).data('id');
		this.updateItemStatus(itemId, 'aips_reject_taxonomy');
	},

	deleteTaxonomy(e) {
		e.preventDefault();
		if (!confirm(this.taxL10n.confirmDelete || 'Are you sure?')) return;

		const itemId = $(e.currentTarget).attr('data-id') || $(e.currentTarget).data('id');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aips_delete_taxonomy',
				nonce: this.taxL10n.nonce || '',
				item_id: itemId
			},
			success: (response) => {
				if (response.success) {
					this.updateStats(response.data.stats || null);
					this.loadTaxonomyItems(this.currentTaxTab);
				} else {
					alert(response.data.message || this.taxL10n.deleteFailed || 'Failed.');
				}
			}
		});
	},

	createTerm(e) {
		e.preventDefault();
		if (!confirm(this.taxL10n.confirmCreateTerm || 'Create WordPress term?')) return;

		const itemId = $(e.currentTarget).attr('data-id') || $(e.currentTarget).data('id');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aips_create_taxonomy_term',
				nonce: this.taxL10n.nonce || '',
				item_id: itemId
			},
			success: (response) => {
				if (response.success) {
					alert(response.data.message);
					this.updateStats(response.data.stats || null);
					this.loadTaxonomyItems(this.currentTaxTab);
				} else {
					alert(response.data.message || this.taxL10n.termCreationFailed || 'Failed.');
				}
			}
		});
	},

	updateItemStatus(itemId, action) {
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			data: {
				action: action,
				nonce: this.taxL10n.nonce || '',
				item_id: itemId
			},
			success: (response) => {
				if (response.success) {
					this.updateStats(response.data.stats || null);
					this.loadTaxonomyItems(this.currentTaxTab);
				} else {
					alert(response.data.message || this.taxL10n.updateFailed || 'Failed.');
				}
			}
		});
	},

	filterTaxonomyItems(e) {
		const searchTerm = $(e.currentTarget).val().toLowerCase();
		const clearBtn = this.$('#aips-taxonomy-search-clear');

		if (searchTerm) {
			clearBtn.show();
		} else {
			clearBtn.hide();
		}

		this.$('.aips-taxonomy-table tbody tr').each(function() {
			const name = $(this).find('.column-name').text().toLowerCase();
			if (name.indexOf(searchTerm) !== -1) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});

		this.updateVisibleResultCount();
	},

	clearTaxonomySearch(e) {
		e.preventDefault();
		this.$('#aips-taxonomy-search').val('').trigger('search');
	},

	updateStats(stats) {
		if (!stats) return;

		this.$('#stat-pending-count').text(stats.pending_total || 0);
		this.$('#stat-approved-count').text(stats.approved_total || 0);
		this.$('#stat-rejected-count').text(stats.rejected_total || 0);
		this.$('#stat-total-count').text(stats.total_items || 0);
		this.$('#categories-count').text(stats.categories_total || 0);
		this.$('#tags-count').text(stats.tags_total || 0);
	},

	updateVisibleResultCount() {
		const visibleCount = this.$('.aips-taxonomy-table tbody tr[data-taxonomy-id]:visible').length;
		this.updateResultCountLabel(visibleCount);
	},

	updateResultCountLabel(count) {
		const normalizedCount = Number(count || 0);
		const label = normalizedCount === 1 ? (this.taxL10n.item || 'item') : (this.taxL10n.items || 'items');
		this.$('#aips-taxonomy-result-count').text(normalizedCount + ' ' + label);
	},

	toTitleCase(str) {
		return String(str || '').replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
	}
});

export default BlockEditorView;
