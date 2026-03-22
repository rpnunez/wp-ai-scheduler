/**
 * Generated Posts Admin JavaScript
 *
 * Handles Generated Posts interactions including live-story controls.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
(function($) {
	'use strict';

	function getPanelData($panel) {
		return {
			post_id: $panel.data('post-id'),
			history_id: $panel.data('history-id'),
			is_live_story: $panel.find('.aips-live-story-flag').is(':checked') ? 1 : 0,
			story_status: $panel.find('.aips-live-story-status').val(),
			thread_identifier: $panel.find('.aips-live-story-thread').val(),
			parent_story_id: $panel.find('.aips-live-story-parent').val(),
			update_brief: $panel.find('.aips-live-story-brief').val(),
			update_reason: $panel.find('.aips-live-story-reason').val(),
			published_at: $panel.find('.aips-live-story-published-at').val(),
			is_major_update: $panel.find('.aips-live-story-major').is(':checked') ? 1 : 0
		};
	}

	function renderHistory($panel, items) {
		var $history = $panel.find('.aips-live-story-history');
		$history.empty();

		if (!items || !items.length) {
			$history.append($('<li>').text(aipsGeneratedPostsConfig.noLiveHistory || 'No live-story updates recorded yet.'));
			return;
		}

		items.forEach(function(item) {
			var meta = [];
			if (item.published_at) {
				meta.push(item.published_at);
			}
			if (item.update_reason) {
				meta.push(item.update_reason);
			}
			if (item.changed_sections && item.changed_sections.length) {
				meta.push(item.changed_sections.join(', '));
			}

			var $li = $('<li>');
			$li.append($('<div>').text(item.message || ''));
			$li.append($('<div>').addClass('aips-live-story-history-meta').text(meta.join(' · ')));
			$history.append($li);
		});
	}

	function updateStoryDisplay($panel, response) {
		if (!response || !response.metadata) {
			return;
		}

		var meta = response.metadata;
		$panel.find('.aips-live-story-status').val(meta.story_status || '');
		$panel.find('.aips-live-story-thread').val(meta.thread_identifier || '');
		$panel.find('.aips-live-story-parent').val(meta.parent_story_id || '');
		$panel.find('.aips-live-story-flag').prop('checked', !!meta.is_live_story);
		if (response.top_summary) {
			$panel.find('.aips-live-story-current-excerpt').text(response.top_summary);
		}
		if (response.headline) {
			var $row = $panel.closest('tr');
			$row.find('.cell-primary').first().text(response.headline);
		}
		if (response.change_history) {
			renderHistory($panel, response.change_history);
		}
	}

	function submitLiveAction($button, action, extraData) {
		var $panel = $button.closest('.aips-live-story-panel-body');
		var data = $.extend({
			action: action,
			nonce: aipsGeneratedPostsConfig.nonce
		}, getPanelData($panel), extraData || {});

		$button.prop('disabled', true);
		$.post(aipsGeneratedPostsConfig.ajaxUrl, data)
			.done(function(response) {
				if (response && response.success) {
					updateStoryDisplay($panel, response.data);
					if (action === 'aips_live_story_append_update') {
						$panel.find('.aips-live-story-brief, .aips-live-story-reason, .aips-live-story-published-at').val('');
						$panel.find('.aips-live-story-major').prop('checked', false);
					}
					window.AIPS.Utilities.showToast(response.data.message || aipsGeneratedPostsConfig.liveStorySaved, 'success');
				} else {
					window.AIPS.Utilities.showToast((response && response.data && response.data.message) || aipsGeneratedPostsConfig.liveStoryError, 'error');
				}
			})
			.fail(function() {
				window.AIPS.Utilities.showToast(aipsGeneratedPostsConfig.liveStoryError, 'error');
			})
			.always(function() {
				$button.prop('disabled', false);
			});
	}

	$(document).ready(function() {
		var previewModal = $('#aips-post-preview-modal');
		var previewIframe = $('#aips-post-preview-iframe');

		$(document).on('click', '.aips-preview-trigger', function() {
			var postId = $(this).data('post-id');
			var siteUrl = (aipsGeneratedPostsConfig && aipsGeneratedPostsConfig.siteUrl) ? aipsGeneratedPostsConfig.siteUrl : '';
			previewIframe.attr('src', siteUrl + '/?p=' + postId + '&preview=true');
			previewModal.fadeIn(200);
		});

		$(document).on('click', '#aips-post-preview-modal .aips-modal-close', function(e) {
			e.preventDefault();
			previewModal.fadeOut(200, function() {
				previewIframe.attr('src', '');
			});
		});

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && previewModal.is(':visible')) {
				previewModal.fadeOut(200, function() {
					previewIframe.attr('src', '');
				});
			}
		});

		$(document).on('click', '.aips-live-story-append', function(e) {
			e.preventDefault();
			submitLiveAction($(this), 'aips_live_story_append_update');
		});

		$(document).on('click', '.aips-live-story-regenerate', function(e) {
			e.preventDefault();
			submitLiveAction($(this), 'aips_live_story_regenerate_sections', {
				sections: [$(this).data('sections')]
			});
		});

		$(document).on('click', '.aips-live-story-refresh-history', function(e) {
			e.preventDefault();
			submitLiveAction($(this), 'aips_live_story_get_history');
		});
	});
})(jQuery);
