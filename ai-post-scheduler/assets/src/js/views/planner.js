import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Planner View
 */
export const PlannerView = Backbone.View.extend({
	el: 'body',

	events: {
		'click #btn-generate-topics': 'generateTopics',
		'click #btn-parse-manual': 'parseManualTopics',
		'click #btn-bulk-schedule': 'bulkSchedule',
		'click #btn-bulk-generate-now': 'bulkGenerateNow',
		'click #btn-clear-topics': 'clearTopics',
		'click #btn-copy-topics': 'copySelectedTopics',
		'keyup #planner-topic-search': 'filterTopics',
		'search #planner-topic-search': 'filterTopics',
		'change #check-all-topics': 'toggleAllTopics',
		'change .topic-checkbox': 'updateSelectionCount',
		'click #planner-topic-search-clear': 'clearTopicSearch',
		'click .aips-clear-topic-search-btn': 'clearTopicSearch',
		'click .aips-remove-topic-btn': 'removeTopic'
	},

	initialize() {
		// Event handler initialization if any
	},

	generateTopics(e) {
		e.preventDefault();
		const niche = this.$('#planner-niche').val();
		const count = this.$('#planner-count').val();

		if (!niche) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please enter a niche or topic.', 'warning');
			}
			return;
		}

		const $btn = $(e.currentTarget);
		$btn.prop('disabled', true);
		$btn.next('.spinner').addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_generate_topics',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				niche: niche,
				count: count
			},
			success: (response) => {
				if (response.success) {
					this.renderTopics(response.data.topics);
					this.$('#planner-results').addClass('active');
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
				}
			},
			complete: () => {
				$btn.prop('disabled', false);
				$btn.next('.spinner').removeClass('is-active');
			}
		});
	},

	parseManualTopics(e) {
		e.preventDefault();
		const text = this.$('#planner-manual-topics').val();
		if (!text) return;

		const topics = text.split('\n')
			.map(t => t.trim())
			.filter(t => t.length > 0);

		if (topics.length > 0) {
			this.renderTopics(topics, true); // true = append
			this.$('#planner-results').addClass('active');
			this.$('#planner-manual-topics').val('');
		}
	},

	renderTopics(topics, append) {
		let html = '';
		if (window.AIPS && window.AIPS.Templates) {
			topics.forEach(topic => {
				html += window.AIPS.Templates.render('aips-tmpl-planner-topic-item', { topic: topic });
			});
		}

		const $list = this.$('#topics-list');
		if (append) {
			$list.append(html);
		} else {
			$list.html(html);
		}

		this.updateSelectionCount();
	},

	removeTopic(e) {
		e.preventDefault();
		const $item = $(e.currentTarget).closest('.topic-item');

		$item.fadeOut(200, () => {
			$item.remove();
			this.updateSelectionCount();

			// Hide panel if list is completely empty
			if (this.$('#topics-list .topic-item').length === 0) {
				this.$('#planner-results').removeClass('active');
				this.$('#planner-niche').val('');
				this.$('#planner-topic-search').val('');
			}
		});
	},

	toggleAllTopics(e) {
		const isChecked = $(e.currentTarget).is(':checked');
		this.$('.topic-checkbox:visible').prop('checked', isChecked);
		this.updateSelectionCount();
	},

	updateSelectionCount() {
		const count = this.$('.topic-checkbox:checked').length;
		this.$('.selection-count').text(count + ' selected');
	},

	clearTopics(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const originalText = $btn.data('original-text') || $btn.text();

		// Store original text if not already stored
		if (!$btn.data('original-text')) {
			$btn.data('original-text', originalText);
		}

		if ($btn.data('is-confirming')) {
			// Second click - Execute
			this.$('#topics-list').empty();
			this.$('#planner-results').removeClass('active');
			this.$('#planner-niche').val('');
			this.$('#planner-manual-topics').val('');
			this.$('#planner-topic-search').val(''); // Clear search input
			this.updateSelectionCount();

			// Reset button
			$btn.text(originalText);
			$btn.removeData('is-confirming');
			clearTimeout($btn.data('timeout'));
		} else {
			// First click - Ask for confirmation
			$btn.text('Click again to confirm');
			$btn.data('is-confirming', true);

			// Reset after 3 seconds
			const timeout = setTimeout(() => {
				$btn.text(originalText);
				$btn.removeData('is-confirming');
			}, 3000);

			$btn.data('timeout', timeout);
		}
	},

	filterTopics(e) {
		const term = $(e.currentTarget).val().toLowerCase();
		const $clearBtn = this.$('#planner-topic-search-clear');
		
		// Show/hide clear button based on search term
		if (term) {
			$clearBtn.show();
		} else {
			$clearBtn.hide();
		}
		
		this.$('.topic-item').each(function() {
			const text = $(this).find('.topic-text-input').val().toLowerCase();
			$(this).toggle(text.indexOf(term) > -1);
		});
		
		// Show an empty state message when no topics match the filter
		const $topicsList = this.$('#topics-list');
		const visibleCount = $topicsList.find('.topic-item:visible').length;
		const $emptyState = $topicsList.find('.topics-empty-state');

		if (term && visibleCount === 0) {
			if ($emptyState.length === 0 && window.AIPS && window.AIPS.Templates) {
				$topicsList.append(window.AIPS.Templates.render('aips-tmpl-planner-search-empty', {}));
			}
		} else {
			if ($emptyState.length) {
				$emptyState.remove();
			}
		}

		this.updateSelectionCount();
	},

	clearTopicSearch(e) {
		e.preventDefault();
		this.$('#planner-topic-search').val('').trigger('keyup');
	},

	copySelectedTopics(e) {
		e.preventDefault();
		const topics = [];
		this.$('.topic-checkbox:checked').each(function() {
			const val = $(this).siblings('.topic-text-input').val();
			if (val && val.trim().length > 0) {
				topics.push(val.trim());
			}
		});

		if (topics.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please select at least one topic.', 'warning');
			}
			return;
		}

		const textToCopy = topics.join('\n');
		const $btn = this.$('#btn-copy-topics');
		const originalText = $btn.text();

		const fallbackCopy = () => {
			const $temp = $('<textarea>');
			$temp.css({
				position: 'fixed',
				top: '-9999px',
				left: '-9999px'
			});
			$('body').append($temp);
			$temp.val(textToCopy).trigger('focus').trigger('select');

			let success = false;
			try {
				if (typeof document.queryCommandSupported !== 'function' || document.queryCommandSupported('copy')) {
					success = document.execCommand('copy');
				}
			} catch (err) {
				success = false;
			}

			$temp.remove();

			if (success) {
				$btn.text('Copied!');
				setTimeout(() => { $btn.text(originalText); }, 2000);
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Unable to copy text automatically. Please select the topics and copy them manually (Ctrl+C or Cmd+C on Mac).', 'warning');
				}
			}
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(textToCopy).then(() => {
				$btn.text('Copied!');
				setTimeout(() => { $btn.text(originalText); }, 2000);
			}).catch(() => {
				fallbackCopy();
			});
		} else {
			fallbackCopy();
		}
	},

	bulkGenerateNow(e) {
		e.preventDefault();
		const topics = [];

		this.$('.topic-checkbox:checked').each(function() {
			const val = $(this).siblings('.topic-text-input').val();
			if (val && val.trim().length > 0) {
				topics.push(val.trim());
			}
		});

		if (topics.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please select at least one topic.', 'warning');
			}
			return;
		}

		const templateId = this.$('#bulk-template').val();

		if (!templateId) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please select a template.', 'warning');
			}
			return;
		}

		const $btn = $(e.currentTarget);
		$btn.prop('disabled', true);
		$btn.nextAll('.spinner').first().addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_bulk_generate_now',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				topics: topics,
				template_id: templateId
			},
			success: (response) => {
				if (response && response.success) {
					const data = response.data || {};
					const failedTopics = data.failed_topics || data.errors || [];
					const hasFailedTopics = $.isArray(failedTopics) ? failedTopics.length > 0 : false;

					if (hasFailedTopics) {
						const partialMsg = data.message || 'Some topics could not be generated. Please review and try again.';
						if (window.AIPS && window.AIPS.Utilities) {
							window.AIPS.Utilities.showToast(partialMsg, 'warning');
						}
					} else {
						const successMsg = data.message || 'Posts generated successfully.';
						if (window.AIPS && window.AIPS.Utilities) {
							window.AIPS.Utilities.showToast(successMsg, 'success');
						}

						this.$('.topic-checkbox:checked').closest('.topic-item').fadeOut(200, function() {
							$(this).remove();
							if (this.$('#topics-list .topic-item').length === 0) {
								this.$('#planner-results').removeClass('active');
								this.$('#planner-niche').val('');
								this.$('#planner-manual-topics').val('');
								this.$('#planner-topic-search').val('');
							}
							this.updateSelectionCount();
						}.bind(this));
					}
				} else {
					const errorMsg = (response && response.data && response.data.message) ? response.data.message : 'An error occurred. Please try again.';
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(errorMsg, 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
				}
			},
			complete: () => {
				$btn.prop('disabled', false);
				$btn.nextAll('.spinner').first().removeClass('is-active');
			}
		});
	},

	bulkSchedule(e) {
		e.preventDefault();
		const topics = [];

		this.$('.topic-checkbox:checked').each(function() {
			const val = $(this).siblings('.topic-text-input').val();
			if (val && val.trim().length > 0) {
				topics.push(val.trim());
			}
		});

		if (topics.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please select at least one topic.', 'warning');
			}
			return;
		}

		const templateId = this.$('#bulk-template').val();
		const startDate = this.$('#bulk-start-date').val();

		if (!templateId) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please select a template.', 'warning');
			}
			return;
		}
		if (!startDate) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please select a start date.', 'warning');
			}
			return;
		}

		const $btn = $(e.currentTarget);
		$btn.prop('disabled', true);
		$btn.nextAll('.spinner').first().addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_bulk_schedule',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				topics: topics,
				template_id: templateId,
				start_date: startDate,
				frequency: this.$('#bulk-frequency').val()
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'success');
					}

					this.$('.topic-checkbox:checked').closest('.topic-item').fadeOut(200, function() {
						$(this).remove();
						if (this.$('#topics-list .topic-item').length === 0) {
							this.$('#planner-results').removeClass('active');
							this.$('#planner-niche').val('');
							this.$('#planner-manual-topics').val('');
							this.$('#planner-topic-search').val('');
						}
						this.updateSelectionCount();
					}.bind(this));
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
				}
			},
			complete: () => {
				$btn.prop('disabled', false);
				$btn.nextAll('.spinner').first().removeClass('is-active');
			}
		});
	}
});
