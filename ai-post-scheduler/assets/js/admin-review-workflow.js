(function($) {
	'use strict';

	var state = {
		currentItemId: 0,
		currentStageKey: '',
		item: null,
		stageData: {},
		stages: (window.aipsReviewWorkflowL10n && window.aipsReviewWorkflowL10n.stages) ? window.aipsReviewWorkflowL10n.stages : {}
	};

	function ajax(data, onSuccess, onError) {
		$.ajax({
			url: aipsReviewWorkflowL10n.ajaxUrl,
			type: 'POST',
			data: $.extend({
				nonce: aipsReviewWorkflowL10n.nonce
			}, data),
			success: function(resp) {
				if (resp && resp.success) {
					onSuccess && onSuccess(resp.data);
				} else {
					onError && onError(resp && resp.data ? resp.data : { message: aipsReviewWorkflowL10n.errorTryAgain });
				}
			},
			error: function() {
				onError && onError({ message: aipsReviewWorkflowL10n.errorTryAgain });
			}
		});
	}

	function openDrawer() {
		$('#aips-rw-drawer').addClass('is-open').attr('aria-hidden', 'false');
	}

	function closeDrawer() {
		$('#aips-rw-drawer').removeClass('is-open').attr('aria-hidden', 'true');
		state.currentItemId = 0;
		state.currentStageKey = '';
	}

	function setLoading() {
		$('#aips-rw-drawer-post-title').text(aipsReviewWorkflowL10n.loading || 'Loading...');
		$('#aips-rw-preview').html('');
		$('#aips-rw-comments').html('');
		$('#aips-rw-checklist-items').html('');
		$('#aips-rw-notes').val('');
		$('#aips-rw-schedule-section').hide();
	}

	function renderStageSelect(currentKey) {
		var $sel = $('#aips-rw-stage-select');
		$sel.empty();
		Object.keys(state.stages).forEach(function(k) {
			var label = state.stages[k].label || k;
			$sel.append($('<option>').attr('value', k).text(label));
		});
		$sel.val(currentKey);
	}

	function renderChecklist(stageKey) {
		var $wrap = $('#aips-rw-checklist-items');
		$wrap.empty();

		var def = state.stages[stageKey];
		var list = def && def.checklist ? def.checklist : [];
		var stageState = state.stageData[stageKey] ? state.stageData[stageKey].checklist_state : {};

		if (!list.length) {
			$wrap.append($('<div>').text('—'));
			return;
		}

		list.forEach(function(item) {
			var checked = !!(stageState && stageState[item.key]);
			var $row = $('<label class="aips-rw-checklist-item">');
			var $cb = $('<input type="checkbox" class="aips-rw-check">')
				.attr('data-check-key', item.key)
				.prop('checked', checked);
			$row.append($cb).append($('<span>').text(item.label || item.key));
			$wrap.append($row);
		});
	}

	function renderComments(comments) {
		var $wrap = $('#aips-rw-comments');
		$wrap.empty();

		if (!comments || !comments.length) {
			$wrap.append($('<div>').text('—'));
			return;
		}

		comments.forEach(function(c) {
			var who = c.user_label ? c.user_label : ('User #' + (c.user_id || ''));
			var when = c.created_at || '';
			var $c = $('<div class="aips-rw-comment">');
			$c.append($('<div class="aips-rw-comment-meta">').text(who + (when ? (' • ' + when) : '')));
			$c.append($('<div class="aips-rw-comment-body">').text(c.comment || ''));
			$wrap.append($c);
		});
	}

	function renderPreview(preview) {
		var html = '';
		if (preview.featured_image) {
			html += '<p><img src="' + String(preview.featured_image).replace(/"/g, '&quot;') + '" alt="" /></p>';
		}
		html += '<h2>' + $('<div>').text(preview.title || '').html() + '</h2>';
		if (preview.excerpt) {
			html += '<p><em>' + $('<div>').text(preview.excerpt).html() + '</em></p>';
		}
		if (preview.content) {
			html += '<div class="aips-rw-preview-content">' + preview.content + '</div>';
		}
		if (preview.edit_url) {
			html += '<p><a class="aips-btn aips-btn-sm aips-btn-secondary" href="' + String(preview.edit_url).replace(/"/g, '&quot;') + '" target="_blank">Edit Post</a></p>';
		}
		$('#aips-rw-preview').html(html);
	}

	function applyItemToUI(data) {
		state.item = data.item;
		state.stageData = data.stage_data || {};
		state.currentItemId = data.item.id;
		state.currentStageKey = data.item.stage;

		$('#aips-rw-drawer-post-title').text(data.item.post_title || '');
		$('#aips-rw-drawer-stage-badge').text(state.stages[state.currentStageKey] ? state.stages[state.currentStageKey].label : state.currentStageKey);

		renderStageSelect(state.currentStageKey);

		$('#aips-rw-assignee-select').val(data.item.assigned_to ? String(data.item.assigned_to) : '');
		$('#aips-rw-priority-select').val(data.item.priority || 'normal');

		if (data.item.due_at) {
			$('#aips-rw-due-input').val(String(data.item.due_at).replace(' ', 'T').slice(0, 16));
		} else {
			$('#aips-rw-due-input').val('');
		}

		var stageNotes = state.stageData[state.currentStageKey] ? (state.stageData[state.currentStageKey].notes || '') : '';
		$('#aips-rw-notes').val(stageNotes);

		renderChecklist(state.currentStageKey);
		renderComments(data.comments || []);
		renderPreview(data.preview || {});

		if (state.currentStageKey === 'ready' && data.item.closed_state === 'open') {
			$('#aips-rw-schedule-section').show();
		} else {
			$('#aips-rw-schedule-section').hide();
		}
	}

	function loadItem(reviewItemId, postId) {
		openDrawer();
		setLoading();

		var payload = {
			action: 'aips_review_workflow_get_item'
		};
		if (reviewItemId) {
			payload.review_item_id = reviewItemId;
		}
		if (postId) {
			payload.post_id = postId;
		}

		ajax(payload, function(data) {
			applyItemToUI(data);
		}, function(err) {
			AIPS.Utilities.showToast((err && err.message) ? err.message : aipsReviewWorkflowL10n.errorTryAgain, 'error');
			closeDrawer();
		});
	}

	function refreshRow(reviewItemId) {
		var $row = $('tr[data-review-item-id="' + reviewItemId + '"]');
		if (!$row.length) return;

		ajax({ action: 'aips_review_workflow_get_item', review_item_id: reviewItemId }, function(data) {
			var stageLabel = state.stages[data.item.stage] ? state.stages[data.item.stage].label : data.item.stage;
			$row.find('td').eq(1).find('.aips-badge').first().text(stageLabel);
			if (data.item.closed_state !== 'open') {
				$row.fadeOut(200, function() { $(this).remove(); });
			}
		});
	}

	function updateMeta() {
		if (!state.currentItemId) return;

		var assignee = $('#aips-rw-assignee-select').val();
		var priority = $('#aips-rw-priority-select').val();
		var due = $('#aips-rw-due-input').val();

		ajax({
			action: 'aips_review_workflow_update_item_meta',
			review_item_id: state.currentItemId,
			assigned_to: assignee ? parseInt(assignee, 10) : 0,
			priority: priority,
			due_at: due ? due.replace('T', ' ') + ':00' : ''
		}, function() {
			refreshRow(state.currentItemId);
		});
	}

	$(document).ready(function() {
		$(document).on('click', '.aips-rw-open', function() {
			var $tr = $(this).closest('tr');
			var id = parseInt($tr.data('review-item-id'), 10);
			loadItem(id, 0);
		});

		$(document).on('click', '.aips-rw-close', function() {
			closeDrawer();
		});

		$('#aips-rw-stage-select').on('change', function() {
			if (!state.currentItemId) return;
			var stageKey = $(this).val();
			ajax({
				action: 'aips_review_workflow_set_stage',
				review_item_id: state.currentItemId,
				stage_key: stageKey
			}, function() {
				loadItem(state.currentItemId, 0);
				refreshRow(state.currentItemId);
			}, function(err) {
				AIPS.Utilities.showToast(err.message || aipsReviewWorkflowL10n.errorTryAgain, 'error');
			});
		});

		$('#aips-rw-assignee-select, #aips-rw-priority-select, #aips-rw-due-input').on('change', function() {
			updateMeta();
		});

		$(document).on('change', '.aips-rw-check', function() {
			if (!state.currentItemId) return;
			var key = $(this).data('check-key');
			var checked = $(this).is(':checked') ? 1 : 0;
			ajax({
				action: 'aips_review_workflow_toggle_checklist',
				review_item_id: state.currentItemId,
				stage_key: state.currentStageKey,
				check_key: key,
				checked: checked
			}, function(data) {
				if (!state.stageData[state.currentStageKey]) state.stageData[state.currentStageKey] = {};
				state.stageData[state.currentStageKey].checklist_state = data.checklist_state || {};
			});
		});

		function stageAction(actionName, force) {
			if (!state.currentItemId) return;
			var notes = $('#aips-rw-notes').val();

			ajax({
				action: actionName,
				review_item_id: state.currentItemId,
				stage_key: state.currentStageKey,
				notes: notes,
				force: force ? 1 : 0
			}, function() {
				AIPS.Utilities.showToast('Saved', 'success');
				loadItem(state.currentItemId, 0);
				refreshRow(state.currentItemId);
			}, function(err) {
				if (err && err.code === 'checklist_incomplete') {
					AIPS.Utilities.confirm(aipsReviewWorkflowL10n.confirmApproveIncomplete, 'Notice', [
						{ label: 'Cancel', className: 'aips-btn aips-btn-primary' },
						{ label: 'Approve anyway', className: 'aips-btn aips-btn-danger-solid', action: function() {
							stageAction(actionName, true);
						}}
					]);
					return;
				}
				AIPS.Utilities.showToast(err.message || aipsReviewWorkflowL10n.errorTryAgain, 'error');
			});
		}

		$(document).on('click', '.aips-rw-approve', function() {
			stageAction('aips_review_workflow_approve_stage', false);
		});

		$(document).on('click', '.aips-rw-request-changes', function() {
			stageAction('aips_review_workflow_request_changes', false);
		});

		$(document).on('click', '.aips-rw-skip', function() {
			stageAction('aips_review_workflow_skip_stage', true);
		});

		$(document).on('click', '.aips-rw-save-notes', function() {
			if (!state.currentItemId) return;
			var notes = $('#aips-rw-notes').val();
			ajax({
				action: 'aips_review_workflow_save_stage_notes',
				review_item_id: state.currentItemId,
				stage_key: state.currentStageKey,
				notes: notes
			}, function() {
				AIPS.Utilities.showToast('Saved', 'success');
			}, function(err) {
				AIPS.Utilities.showToast(err.message || aipsReviewWorkflowL10n.errorTryAgain, 'error');
			});
		});

		$(document).on('click', '.aips-rw-add-comment', function() {
			if (!state.currentItemId) return;
			var comment = $('#aips-rw-new-comment').val();
			if (!comment) return;
			ajax({
				action: 'aips_review_workflow_add_comment',
				review_item_id: state.currentItemId,
				comment: comment
			}, function() {
				$('#aips-rw-new-comment').val('');
				loadItem(state.currentItemId, 0);
			}, function(err) {
				AIPS.Utilities.showToast(err.message || aipsReviewWorkflowL10n.errorTryAgain, 'error');
			});
		});

		$(document).on('click', '.aips-rw-publish-now', function() {
			if (!state.currentItemId) return;
			AIPS.Utilities.confirm(aipsReviewWorkflowL10n.confirmPublish, 'Notice', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-primary' },
				{ label: 'Publish now', className: 'aips-btn aips-btn-danger-solid', action: function() {
					ajax({
						action: 'aips_review_workflow_publish_now',
						review_item_id: state.currentItemId
					}, function(data) {
						AIPS.Utilities.showToast(data.message || 'Published', 'success');
						refreshRow(state.currentItemId);
						closeDrawer();
					}, function(err) {
						AIPS.Utilities.showToast(err.message || aipsReviewWorkflowL10n.errorTryAgain, 'error');
					});
				}}
			]);
		});

		$(document).on('click', '.aips-rw-schedule', function() {
			if (!state.currentItemId) return;
			var at = $('#aips-rw-schedule-at').val();
			if (!at) {
				AIPS.Utilities.showToast('Select a date/time first.', 'warning');
				return;
			}
			AIPS.Utilities.confirm(aipsReviewWorkflowL10n.confirmSchedule, 'Notice', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-primary' },
				{ label: 'Schedule', className: 'aips-btn aips-btn-danger-solid', action: function() {
					ajax({
						action: 'aips_review_workflow_schedule',
						review_item_id: state.currentItemId,
						schedule_at: at
					}, function(data) {
						AIPS.Utilities.showToast(data.message || 'Scheduled', 'success');
						refreshRow(state.currentItemId);
						closeDrawer();
					}, function(err) {
						AIPS.Utilities.showToast(err.message || aipsReviewWorkflowL10n.errorTryAgain, 'error');
					});
				}}
			]);
		});

		$(document).on('click', '.aips-rw-archive', function() {
			if (!state.currentItemId) return;
			AIPS.Utilities.confirm(aipsReviewWorkflowL10n.confirmArchive, 'Notice', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-primary' },
				{ label: 'Archive', className: 'aips-btn aips-btn-danger-solid', action: function() {
					ajax({
						action: 'aips_review_workflow_archive',
						review_item_id: state.currentItemId
					}, function(data) {
						AIPS.Utilities.showToast(data.message || 'Archived', 'success');
						refreshRow(state.currentItemId);
						closeDrawer();
					}, function(err) {
						AIPS.Utilities.showToast(err.message || aipsReviewWorkflowL10n.errorTryAgain, 'error');
					});
				}}
			]);
		});

		// Deep-link auto open.
		var params = new URLSearchParams(window.location.search || '');
		if (params.get('page') === 'aips-review-workflow' && params.get('post_id')) {
			var postId = parseInt(params.get('post_id'), 10);
			if (postId) {
				loadItem(0, postId);
			}
		}
	});

})(jQuery);

