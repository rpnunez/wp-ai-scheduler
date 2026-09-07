/**
 * Content Auditor & Intelligence Suite JavaScript Runner
 *
 * Drives the chunked 4-step AJAX pipeline, renders dynamic health scorecards,
 * and handles one-click action suite triggers.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

(function ($) {
	'use strict';

	var currentReport = null;
	var isRunning = false;

	$(document).ready(function () {
		initEventListeners();
		loadLatestAudit();
	});

	function initEventListeners() {
		// Form Submit: Run Audit Pipeline
		$('#aips-auditor-form').on('submit', function (e) {
			e.preventDefault();
			if (isRunning) return;

			var niche = $('#auditor-niche').val().trim();
			if (!niche) return;

			var limit = parseInt($('#auditor-limit').val(), 10) || 100;
			var modules = [];
			$('input[name="auditor_modules[]"]:checked').each(function () {
				modules.push($(this).val());
			});

			if (modules.length === 0) {
				alert(aipsAuditorL10n.selectAtLeastOneModule || 'Please select at least one audit module.');
				return;
			}

			startAuditPipeline(niche, limit, modules);
		});

		// Findings Tab Switching
		$(document).on('click', '.aips-findings-tab-btn', function (e) {
			e.preventDefault();
			var tabKey = $(this).data('findings-tab');

			$('.aips-findings-tab-btn').removeClass('active');
			$(this).addClass('active');

			$('.aips-findings-view').hide();
			$('#aips-view-' + tabKey).show();
		});

		// Export Dropdown Toggle
		$('#aips-export-report-btn').on('click', function (e) {
			e.stopPropagation();
			$('#aips-export-dropdown').toggle();
		});

		$(document).on('click', function () {
			$('#aips-export-dropdown').hide();
		});

		// Export Format Click
		$(document).on('click', '.aips-dropdown-item', function (e) {
			e.preventDefault();
			var format = $(this).data('export');
			if (currentReport) {
				exportAuditReport(currentReport, format);
			}
			$('#aips-export-dropdown').hide();
		});

		// History Modal Open
		$('#aips-auditor-history-btn').on('click', function (e) {
			e.preventDefault();
			openHistoryModal();
		});

		// Modal Close Buttons
		$(document).on('click', '.aips-modal-close', function () {
			$(this).closest('.aips-modal').hide();
		});

		// One-Click Action: Add Gap to Author Topic Modal
		$(document).on('click', '.aips-action-add-topic', function (e) {
			e.preventDefault();
			var topic = $(this).data('topic') || '';
			$('#aips-modal-topic-title').val(topic);
			$('#aips-add-to-author-modal').show();
		});

		// Add Topic Form Submit
		$('#aips-add-topic-author-form').on('submit', function (e) {
			e.preventDefault();
			var topic = $('#aips-modal-topic-title').val().trim();
			var authorId = $('#aips-modal-author-select').val();

			if (!topic || !authorId) return;

			var $btn = $('#aips-confirm-add-topic-btn');
			$btn.prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_save_author_topic',
					nonce: aipsAuditorL10n.nonce,
					author_id: authorId,
					topic: topic,
					status: 'approved'
				},
				success: function (res) {
					$btn.prop('disabled', false);
					if (res && res.success) {
						alert(aipsAuditorL10n.topicAddedSuccess || 'Topic successfully added to Author Persona!');
						$('#aips-add-to-author-modal').hide();
					} else {
						alert(res && res.data && res.data.message ? res.data.message : 'Error saving topic.');
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					alert('Network error while saving topic.');
				}
			});
		});

		// One-Click Action: Generate Post Now
		$(document).on('click', '.aips-action-generate-post', function (e) {
			e.preventDefault();
			var topic = $(this).data('topic') || '';
			if (!topic) return;

			if (confirm(aipsAuditorL10n.confirmGeneratePost || 'Generate post immediately for topic: "' + topic + '"?')) {
				window.location.href = 'admin.php?page=aips-templates&generate_topic=' + encodeURIComponent(topic);
			}
		});
	}

	/**
	 * Run the 4-step progressive chunked pipeline.
	 */
	function startAuditPipeline(niche, limit, modules) {
		isRunning = true;
		$('#aips-run-audit-btn').prop('disabled', true);
		$('#aips-auditor-progress-container').slideDown(200);
		$('#aips-auditor-results-container').hide();

		updateProgress(5, 1, aipsAuditorL10n.step1Text || 'Step 1/4: Ingesting and profiling content library...');

		// Step 1: Scan Library
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_auditor_scan_step',
				nonce: aipsAuditorL10n.nonce,
				limit: limit,
				offset: 0
			},
			success: function (res1) {
				if (!res1 || !res1.success) {
					handleAuditError(res1);
					return;
				}

				var fingerprints = res1.data.fingerprints || [];
				updateProgress(25, 2, aipsAuditorL10n.step2Text || 'Step 2/4: Constructing link graph & entity clusters...');

				// Step 2: Build Graph & Clusters
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'aips_auditor_graph_step',
						nonce: aipsAuditorL10n.nonce,
						fingerprints: fingerprints
					},
					success: function (res2) {
						if (!res2 || !res2.success) {
							handleAuditError(res2);
							return;
						}

						var linkGraph = res2.data.link_graph || {};
						var entityClusters = res2.data.entity_clusters || {};
						updateProgress(50, 3, aipsAuditorL10n.step3Text || 'Step 3/4: Running AI intelligence modules...');

						// Step 3: Execute Modules in Sequence
						runModulesSequence(niche, fingerprints, linkGraph, entityClusters, modules, function (executedModules) {
							updateProgress(85, 4, aipsAuditorL10n.step4Text || 'Step 4/4: Synthesizing health scorecard & saving...');

							// Step 4: Synthesize & Save
							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_auditor_synthesize_step',
									nonce: aipsAuditorL10n.nonce,
									niche: niche,
									fingerprints: fingerprints,
									link_graph: linkGraph,
									entity_clusters: entityClusters,
									modules: executedModules
								},
								success: function (res4) {
									if (!res4 || !res4.success) {
										handleAuditError(res4);
										return;
									}

									updateProgress(100, 4, aipsAuditorL10n.completeText || 'Audit complete!');
									currentReport = res4.data.report;

									setTimeout(function () {
										$('#aips-auditor-progress-container').slideUp(300);
										renderAuditReport(currentReport);
										isRunning = false;
										$('#aips-run-audit-btn').prop('disabled', false);
									}, 600);
								},
								error: handleAuditError
							});
						});
					},
					error: handleAuditError
				});
			},
			error: handleAuditError
		});
	}

	/**
	 * Run individual AI modules in sequence to prevent timeout.
	 */
	function runModulesSequence(niche, fingerprints, linkGraph, entityClusters, modules, onComplete) {
		var executedResults = {};
		var index = 0;

		function nextModule() {
			if (index >= modules.length) {
				onComplete(executedResults);
				return;
			}

			var mod = modules[index];
			var progressPct = 50 + Math.round(((index + 1) / modules.length) * 30);
			updateProgress(progressPct, 3, (aipsAuditorL10n.runningModule || 'Analyzing') + ': ' + formatModuleName(mod) + '...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_auditor_analyze_step',
					nonce: aipsAuditorL10n.nonce,
					niche: niche,
					module: mod,
					fingerprints: fingerprints,
					link_graph: linkGraph,
					entity_clusters: entityClusters
				},
				success: function (res) {
					if (res && res.success) {
						executedResults[mod] = res.data.result || {};
					}
					index++;
					nextModule();
				},
				error: function () {
					index++;
					nextModule();
				}
			});
		}

		nextModule();
	}

	function updateProgress(percent, stepIndex, label) {
		$('#aips-auditor-progress-bar').css('width', percent + '%');
		$('#aips-auditor-percent-label').text(percent + '%');
		if (label) {
			$('#aips-auditor-step-label').text(label);
		}

		$('.aips-pipe-step').each(function () {
			var s = parseInt($(this).data('step'), 10);
			$(this).removeClass('active completed');
			if (s < stepIndex) {
				$(this).addClass('completed');
			} else if (s === stepIndex) {
				$(this).addClass('active');
			}
		});
	}

	function handleAuditError(res) {
		isRunning = false;
		$('#aips-run-audit-btn').prop('disabled', false);
		$('#aips-auditor-progress-container').slideUp(200);
		var msg = (res && res.data && res.data.message) ? res.data.message : (aipsAuditorL10n.auditError || 'An error occurred during the audit.');
		alert(msg);
	}

	/**
	 * Render the full audit report into the dashboard.
	 */
	function renderAuditReport(report) {
		if (!report) return;

		var scorecard = report.health_scorecard || {};
		var overall = scorecard.overall_score !== undefined ? scorecard.overall_score : 0;

		// Gauge
		$('#aips-overall-score-val').text(overall);
		var $circle = $('#aips-overall-gauge-circle');
		$circle.removeClass('tier-warning tier-danger');
		var $badge = $('#aips-overall-status-badge');
		$badge.removeClass('aips-badge-good aips-badge-warning aips-badge-danger');

		if (overall >= 80) {
			$badge.addClass('aips-badge-good').text(aipsAuditorL10n.badgeGood || 'Strong Standing');
		} else if (overall >= 60) {
			$circle.addClass('tier-warning');
			$badge.addClass('aips-badge-warning').text(aipsAuditorL10n.badgeWarning || 'Moderate Gaps');
		} else {
			$circle.addClass('tier-danger');
			$badge.addClass('aips-badge-danger').text(aipsAuditorL10n.badgeDanger || 'Action Required');
		}

		// Sub-scores
		$('#aips-score-freshness').text((scorecard.freshness_score || 0) + '/100');
		$('#aips-score-links').text((scorecard.link_score || 0) + '/100');
		$('#aips-score-cannibalization').text((scorecard.cannibalization_score || 0) + '/100');
		$('#aips-score-gaps').text((scorecard.gap_score || 0) + '/100');

		$('#aips-audit-timestamp').text((aipsAuditorL10n.auditedAt || 'Audited:') + ' ' + (report.audited_at || 'Just now'));

		// Takeaways
		var takeaways = scorecard.key_takeaways || [];
		var takeawaysHtml = '';
		takeaways.forEach(function (t) {
			takeawaysHtml += '<li>' + escapeHtml(t) + '</li>';
		});
		$('#aips-takeaways-list').html(takeawaysHtml);

		// Render Findings Sub-Tabs
		renderGapsTab(report.modules ? report.modules.gaps : null);
		renderCannibalizationTab(report.modules ? report.modules.cannibalization : null);
		renderDecayTab(report.modules ? report.modules.decay : null);
		renderLinksTab(report.modules ? report.modules.links : null);
		renderTrendsTab(report.modules ? report.modules.trends : null);

		$('#aips-auditor-results-container').fadeIn(300);
	}

	function renderGapsTab(data) {
		var gaps = (data && data.gaps) ? data.gaps : [];
		$('#aips-count-badge-gaps').text(gaps.length);

		if (gaps.length === 0) {
			$('#aips-gaps-table-container').html('<p class="description">' + (aipsAuditorL10n.noGapsFound || 'No major content gaps identified.') + '</p>');
			return;
		}

		var html = '<table class="aips-findings-table">';
		html += '<thead><tr>';
		html += '<th>' + (aipsAuditorL10n.thTopic || 'Missing Topic') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thPriority || 'Priority') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thType || 'Type') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thIntent || 'Intent') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thReason || 'Strategic Reason & Angle') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thActions || 'Actions') + '</th>';
		html += '</tr></thead><tbody>';

		gaps.forEach(function (g) {
			var priorityClass = (g.priority === 'High') ? 'aips-badge-high' : 'aips-badge-medium';
			var typeClass = (g.type === 'Pillar') ? 'aips-badge-pillar' : 'aips-badge-cluster';

			html += '<tr>';
			html += '<td><strong>' + escapeHtml(g.missing_topic) + '</strong></td>';
			html += '<td><span class="aips-pill-badge ' + priorityClass + '">' + escapeHtml(g.priority) + '</span></td>';
			html += '<td><span class="aips-pill-badge ' + typeClass + '">' + escapeHtml(g.type || 'Pillar') + '</span></td>';
			html += '<td><code>' + escapeHtml(g.search_intent || 'Informational') + '</code></td>';
			html += '<td>' + escapeHtml(g.reason) + (g.suggested_angle ? '<br><small><em>' + escapeHtml(g.suggested_angle) + '</em></small>' : '') + '</td>';
			html += '<td><div class="aips-action-btn-group">';
			html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-action-add-topic" data-topic="' + escapeHtml(g.missing_topic) + '">+ Add to Author</button>';
			html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-action-generate-post" data-topic="' + escapeHtml(g.missing_topic) + '">⚡ Generate</button>';
			html += '</div></td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		$('#aips-gaps-table-container').html(html);
	}

	function renderCannibalizationTab(data) {
		var conflicts = (data && data.conflicts) ? data.conflicts : [];
		$('#aips-count-badge-cannibalization').text(conflicts.length);

		if (conflicts.length === 0) {
			$('#aips-cannibalization-container').html('<p class="description">' + (aipsAuditorL10n.noConflictsFound || 'No keyword cannibalization conflicts detected across your published articles.') + '</p>');
			return;
		}

		var html = '';
		conflicts.forEach(function (c) {
			var severityClass = (c.severity === 'High') ? 'aips-badge-high' : 'aips-badge-medium';
			html += '<div class="aips-cannibalization-card">';
			html += '<div style="display:flex; justify-content:space-between; align-items:center;">';
			html += '<strong>Conflict #' + (c.pair_index || 1) + '</strong>';
			html += '<span class="aips-pill-badge ' + severityClass + '">' + escapeHtml(c.severity) + ' Severity</span>';
			html += '</div>';
			html += '<p style="margin:8px 0; color:#334155;">' + escapeHtml(c.conflict_summary) + '</p>';
			html += '<div style="margin-top:10px;"><strong>Recommendation:</strong> <span style="color:#2563eb;">' + escapeHtml(c.action_recommendation) + '</span></div>';
			html += '</div>';
		});

		$('#aips-cannibalization-container').html(html);
	}

	function renderDecayTab(data) {
		var recs = (data && data.recommendations) ? data.recommendations : [];
		$('#aips-count-badge-decay').text(recs.length);

		if (recs.length === 0) {
			$('#aips-decay-container').html('<p class="description">' + (aipsAuditorL10n.noDecayFound || 'All evaluated content is fresh and within healthy word count thresholds.') + '</p>');
			return;
		}

		var html = '<table class="aips-findings-table">';
		html += '<thead><tr>';
		html += '<th>' + (aipsAuditorL10n.thPost || 'Post Title') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thUrgency || 'Urgency') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thRefreshPlan || 'Refresh Checklist & Actions') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thActions || 'Actions') + '</th>';
		html += '</tr></thead><tbody>';

		recs.forEach(function (r) {
			var urgencyClass = (r.urgency === 'Urgent') ? 'aips-badge-high' : 'aips-badge-medium';
			var actionsList = (r.refresh_actions && r.refresh_actions.length) ? '<ul><li>' + r.refresh_actions.map(escapeHtml).join('</li><li>') + '</li></ul>' : '';

			html += '<tr>';
			html += '<td><strong>' + escapeHtml(r.title) + '</strong> (ID ' + (r.post_id || '') + ')</td>';
			html += '<td><span class="aips-pill-badge ' + urgencyClass + '">' + escapeHtml(r.urgency) + '</span></td>';
			html += '<td>' + actionsList + (r.editorial_notes ? '<small><em>' + escapeHtml(r.editorial_notes) + '</em></small>' : '') + '</td>';
			html += '<td><a href="post.php?post=' + (r.post_id || '') + '&action=edit" target="_blank" class="aips-btn aips-btn-sm aips-btn-secondary">✏️ Edit / Refresh</a></td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		$('#aips-decay-container').html(html);
	}

	function renderLinksTab(data) {
		var suggestions = (data && data.link_suggestions) ? data.link_suggestions : [];
		var orphans = (data && data.orphan_posts) ? data.orphan_posts : [];
		$('#aips-count-badge-links').text(suggestions.length || orphans.length);

		if (suggestions.length === 0 && orphans.length === 0) {
			$('#aips-links-container').html('<p class="description">' + (aipsAuditorL10n.noLinkGapsFound || 'Internal link connectivity is strong with no orphan articles.') + '</p>');
			return;
		}

		var html = '<table class="aips-findings-table">';
		html += '<thead><tr>';
		html += '<th>' + (aipsAuditorL10n.thOrphan || 'Orphan Article') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thTargetSource || 'Suggested Source Article') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thAnchorRationale || 'Anchor & Silo Rationale') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thActions || 'Actions') + '</th>';
		html += '</tr></thead><tbody>';

		suggestions.forEach(function (s) {
			html += '<tr>';
			html += '<td><strong>' + escapeHtml(s.orphan_title) + '</strong></td>';
			html += '<td>' + escapeHtml(s.suggested_source_title) + '</td>';
			html += '<td>Anchor: <code>' + escapeHtml(s.recommended_anchor) + '</code><br><small>' + escapeHtml(s.rationale) + '</small></td>';
			html += '<td><a href="post.php?post=' + (s.suggested_source_id || '') + '&action=edit" target="_blank" class="aips-btn aips-btn-sm aips-btn-secondary">🔗 Insert Link</a></td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		$('#aips-links-container').html(html);
	}

	function renderTrendsTab(data) {
		var trends = (data && data.trends) ? data.trends : [];
		$('#aips-count-badge-trends').text(trends.length);

		if (trends.length === 0) {
			$('#aips-trends-container').html('<p class="description">' + (aipsAuditorL10n.noTrendsFound || 'No new external industry trends uncovered from active sources.') + '</p>');
			return;
		}

		var html = '<table class="aips-findings-table">';
		html += '<thead><tr>';
		html += '<th>' + (aipsAuditorL10n.thTrend || 'Industry Trend') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thSourceSnippet || 'Source Evidence') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thAngle || 'Recommended Angle') + '</th>';
		html += '<th>' + (aipsAuditorL10n.thActions || 'Actions') + '</th>';
		html += '</tr></thead><tbody>';

		trends.forEach(function (t) {
			html += '<tr>';
			html += '<td><strong>' + escapeHtml(t.trend_topic) + '</strong></td>';
			html += '<td><small>' + escapeHtml(t.source_evidence) + '</small></td>';
			html += '<td>' + escapeHtml(t.recommended_article_angle) + '</td>';
			html += '<td><button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-action-add-topic" data-topic="' + escapeHtml(t.trend_topic) + '">+ Add Topic</button></td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		$('#aips-trends-container').html(html);
	}

	function loadLatestAudit() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_auditor_get_latest',
				nonce: aipsAuditorL10n.nonce
			},
			success: function (res) {
				if (res && res.success && res.data && res.data.audit && res.data.audit.report) {
					currentReport = res.data.audit.report;
					renderAuditReport(currentReport);
				}
			}
		});
	}

	function openHistoryModal() {
		var $modalBody = $('#aips-auditor-history-modal-body');
		$modalBody.html('<p class="description">Loading history...</p>');
		$('#aips-auditor-history-modal').show();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_auditor_get_history',
				nonce: aipsAuditorL10n.nonce,
				limit: 20
			},
			success: function (res) {
				if (res && res.success && res.data && res.data.history && res.data.history.length) {
					var html = '<table class="aips-findings-table"><thead><tr>';
					html += '<th>Date</th><th>Niche</th><th>Overall Health</th><th>Gaps</th><th>Decayed</th><th>Actions</th></tr></thead><tbody>';

					res.data.history.forEach(function (item) {
						var d = new Date(item.created_at * 1000);
						var dateStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString();

						html += '<tr>';
						html += '<td>' + dateStr + '</td>';
						html += '<td><strong>' + escapeHtml(item.niche) + '</strong></td>';
						html += '<td><strong>' + item.overall_score + '/100</strong></td>';
						html += '<td>' + item.gap_count + '</td>';
						html += '<td>' + item.decay_count + '</td>';
						html += '<td><button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-load-history-item" data-id="' + item.id + '">Load Snapshot</button></td>';
						html += '</tr>';
					});

					html += '</tbody></table>';
					$modalBody.html(html);

					$('.aips-load-history-item').on('click', function () {
						var histId = $(this).data('id');
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_auditor_get_audit',
								nonce: aipsAuditorL10n.nonce,
								id: histId
							},
							success: function (histRes) {
								if (histRes && histRes.success && histRes.data && histRes.data.audit && histRes.data.audit.report) {
									currentReport = histRes.data.audit.report;
									renderAuditReport(currentReport);
									$('#aips-auditor-history-modal').hide();
								}
							}
						});
					});
				} else {
					$modalBody.html('<p class="description">No previous audit snapshots found.</p>');
				}
			},
			error: function () {
				$modalBody.html('<p class="description">Failed to load history.</p>');
			}
		});
	}

	function exportAuditReport(report, format) {
		var filename = 'content-audit-' + (report.niche || 'report').toLowerCase().replace(/[^a-z0-9]/g, '-') + '-' + Date.now();
		var content = '';
		var mimeType = 'text/plain';

		if (format === 'json') {
			content = JSON.stringify(report, null, 2);
			filename += '.json';
			mimeType = 'application/json';
		} else if (format === 'csv') {
			content = 'Category,Item,Score/Value,Details\n';
			var sc = report.health_scorecard || {};
			content += 'Scorecard,Overall Health,' + (sc.overall_score || 0) + ',\n';
			content += 'Scorecard,Freshness Score,' + (sc.freshness_score || 0) + ',\n';
			content += 'Scorecard,Link Silo Score,' + (sc.link_score || 0) + ',\n';
			content += 'Scorecard,Cannibalization Score,' + (sc.cannibalization_score || 0) + ',\n';
			content += 'Scorecard,Gap Index,' + (sc.gap_score || 0) + ',\n';

			var gaps = (report.modules && report.modules.gaps && report.modules.gaps.gaps) ? report.modules.gaps.gaps : [];
			gaps.forEach(function (g) {
				content += 'Content Gap,"' + (g.missing_topic || '').replace(/"/g, '""') + '",' + g.priority + ',"' + (g.reason || '').replace(/"/g, '""') + '"\n';
			});

			filename += '.csv';
			mimeType = 'text/csv';
		} else {
			// Markdown
			content = '# Content Audit Report: ' + (report.niche || 'General') + '\n\n';
			content += '**Audited At:** ' + (report.audited_at || '') + '\n\n';
			var sc = report.health_scorecard || {};
			content += '## Health Scorecard\n';
			content += '- **Overall Health Score:** ' + (sc.overall_score || 0) + '/100\n';
			content += '- **Freshness & Age Index:** ' + (sc.freshness_score || 0) + '/100\n';
			content += '- **Internal Link Silo Index:** ' + (sc.link_score || 0) + '/100\n';
			content += '- **Cannibalization Health:** ' + (sc.cannibalization_score || 0) + '/100\n';
			content += '- **Topical Coverage Index:** ' + (sc.gap_score || 0) + '/100\n\n';

			content += '### Key Takeaways\n';
			(sc.key_takeaways || []).forEach(function (t) {
				content += '- ' + t + '\n';
			});
			content += '\n';

			var gaps = (report.modules && report.modules.gaps && report.modules.gaps.gaps) ? report.modules.gaps.gaps : [];
			if (gaps.length) {
				content += '## Topic Gaps & Missing Pillars\n';
				gaps.forEach(function (g) {
					content += '### ' + g.missing_topic + ' (' + g.priority + ' Priority | ' + (g.type || 'Pillar') + ')\n';
					content += '- **Intent:** ' + (g.search_intent || 'Informational') + '\n';
					content += '- **Rationale:** ' + g.reason + '\n';
					if (g.suggested_angle) content += '- **Suggested Angle:** ' + g.suggested_angle + '\n';
					content += '\n';
				});
			}

			filename += '.md';
			mimeType = 'text/markdown';
		}

		var blob = new Blob([content], { type: mimeType });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = filename;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

	function formatModuleName(key) {
		switch (key) {
			case 'gaps': return 'Topic Gaps & Pillars';
			case 'cannibalization': return 'Keyword Cannibalization';
			case 'decay': return 'Content Decay';
			case 'links': return 'Internal Link Silos';
			case 'trends': return 'Industry Trends';
			default: return key;
		}
	}

	function escapeHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

})(jQuery);
