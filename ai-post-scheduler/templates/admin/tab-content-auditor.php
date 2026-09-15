<?php
/**
 * Admin Template: Content Auditor & 5-Pillar Intelligence Suite
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

$site_context  = AIPS_Site_Context::get();
$default_niche = !empty($site_context['niche']) ? $site_context['niche'] : '';
$templates     = (new AIPS_Template_Repository())->get_all(true);
$authors       = (new AIPS_Authors_Repository())->get_all(true);
?>

<div class="aips-content-auditor-wrapper">
	<!-- Control Panel -->
	<div class="aips-content-panel">
		<div class="aips-panel-header" style="display: flex; justify-content: space-between; align-items: center;">
			<div>
				<h2 class="aips-panel-title"><?php esc_html_e('Content Auditor & Strategic Intelligence', 'ai-post-scheduler'); ?></h2>
				<p class="description"><?php esc_html_e('Run a multi-pillar deep audit across your site to detect content gaps, keyword cannibalization, content decay, internal link silos, and fresh industry trends.', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-auditor-history-select-wrap">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-auditor-history-btn">
					<span class="dashicons dashicons-backup"></span>
					<?php esc_html_e('Audit History', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>

		<div class="aips-panel-body">
			<form id="aips-auditor-form">
				<div class="aips-auditor-form-grid">
					<div class="aips-form-group">
						<label for="auditor-niche" class="aips-form-label">
							<strong><?php esc_html_e('Target Niche / Topic Domain', 'ai-post-scheduler'); ?></strong>
						</label>
						<input type="text" id="auditor-niche" class="regular-text aips-form-input" 
							   value="<?php echo esc_attr($default_niche); ?>" 
							   placeholder="<?php esc_attr_e('e.g., Cloud Architecture, Sustainable Gardening, Web Development', 'ai-post-scheduler'); ?>" required>
					</div>

					<div class="aips-form-group">
						<label for="auditor-limit" class="aips-form-label">
							<strong><?php esc_html_e('Scan Depth (Max Posts)', 'ai-post-scheduler'); ?></strong>
						</label>
						<select id="auditor-limit" class="aips-form-select">
							<option value="50"><?php esc_html_e('Fast Scan (50 recent posts)', 'ai-post-scheduler'); ?></option>
							<option value="100" selected><?php esc_html_e('Standard Scan (100 posts)', 'ai-post-scheduler'); ?></option>
							<option value="250"><?php esc_html_e('Deep Scan (250 posts)', 'ai-post-scheduler'); ?></option>
							<option value="500"><?php esc_html_e('Comprehensive Scan (500 posts)', 'ai-post-scheduler'); ?></option>
						</select>
					</div>
				</div>

				<div class="aips-auditor-modules-selection">
					<span class="aips-modules-label"><strong><?php esc_html_e('Active Intelligence Pillars:', 'ai-post-scheduler'); ?></strong></span>
					<div class="aips-checkbox-pills">
						<label class="aips-pill-checkbox">
							<input type="checkbox" name="auditor_modules[]" value="gaps" checked>
							<span class="aips-pill-text"><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e('Topic Gaps & Pillars', 'ai-post-scheduler'); ?></span>
						</label>
						<label class="aips-pill-checkbox">
							<input type="checkbox" name="auditor_modules[]" value="cannibalization" checked>
							<span class="aips-pill-text"><span class="dashicons dashicons-randomize"></span> <?php esc_html_e('Keyword Cannibalization', 'ai-post-scheduler'); ?></span>
						</label>
						<label class="aips-pill-checkbox">
							<input type="checkbox" name="auditor_modules[]" value="decay" checked>
							<span class="aips-pill-text"><span class="dashicons dashicons-clock"></span> <?php esc_html_e('Content Decay & Freshness', 'ai-post-scheduler'); ?></span>
						</label>
						<label class="aips-pill-checkbox">
							<input type="checkbox" name="auditor_modules[]" value="links" checked>
							<span class="aips-pill-text"><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Internal Link Silos', 'ai-post-scheduler'); ?></span>
						</label>
						<label class="aips-pill-checkbox">
							<input type="checkbox" name="auditor_modules[]" value="trends" checked>
							<span class="aips-pill-text"><span class="dashicons dashicons-rss"></span> <?php esc_html_e('Source Industry Trends', 'ai-post-scheduler'); ?></span>
						</label>
					</div>
				</div>

				<div class="aips-auditor-submit-row">
					<button type="submit" class="aips-btn aips-btn-primary aips-btn-lg" id="aips-run-audit-btn">
						<span class="dashicons dashicons-superhero-alt"></span>
						<?php esc_html_e('Run 5-Pillar Content Audit', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-cancel-audit-btn" style="display: none;">
						<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</form>

			<!-- Progress Pipeline Display -->
			<div id="aips-auditor-progress-container" class="aips-auditor-progress-wrap" style="display: none;">
				<div class="aips-progress-header">
					<span id="aips-auditor-step-label" class="aips-step-label"><?php esc_html_e('Initializing Audit...', 'ai-post-scheduler'); ?></span>
					<span id="aips-auditor-percent-label" class="aips-percent-label">0%</span>
				</div>
				<div class="aips-progress-bar-bg">
					<div id="aips-auditor-progress-bar" class="aips-progress-bar-fill" style="width: 0%;"></div>
				</div>
				<div class="aips-pipeline-steps-indicators">
					<div class="aips-pipe-step" data-step="1">
						<span class="aips-pipe-dot"></span>
						<span class="aips-pipe-name"><?php esc_html_e('1. Library Profile', 'ai-post-scheduler'); ?></span>
					</div>
					<div class="aips-pipe-step" data-step="2">
						<span class="aips-pipe-dot"></span>
						<span class="aips-pipe-name"><?php esc_html_e('2. Link & Entity Graph', 'ai-post-scheduler'); ?></span>
					</div>
					<div class="aips-pipe-step" data-step="3">
						<span class="aips-pipe-dot"></span>
						<span class="aips-pipe-name"><?php esc_html_e('3. AI Intelligence Modules', 'ai-post-scheduler'); ?></span>
					</div>
					<div class="aips-pipe-step" data-step="4">
						<span class="aips-pipe-dot"></span>
						<span class="aips-pipe-name"><?php esc_html_e('4. Health Scorecard', 'ai-post-scheduler'); ?></span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Results Dashboard -->
	<div id="aips-auditor-results-container" style="display: none;">
		<!-- Overall Health Scorecard -->
		<div class="aips-content-panel aips-scorecard-panel">
			<div class="aips-panel-header" style="display: flex; justify-content: space-between; align-items: center;">
				<h3 class="aips-panel-title">
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e('Content Health Scorecard', 'ai-post-scheduler'); ?>
				</h3>
				<div class="aips-scorecard-actions">
					<span id="aips-audit-timestamp" class="aips-timestamp-badge"></span>
					<div class="aips-dropdown-wrap" style="display: inline-block;">
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-export-report-btn">
							<span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Report', 'ai-post-scheduler'); ?>
						</button>
						<div id="aips-export-dropdown" class="aips-dropdown-menu" style="display: none;">
							<a href="#" class="aips-dropdown-item" data-export="markdown"><?php esc_html_e('Markdown (.md)', 'ai-post-scheduler'); ?></a>
							<a href="#" class="aips-dropdown-item" data-export="csv"><?php esc_html_e('CSV (.csv)', 'ai-post-scheduler'); ?></a>
							<a href="#" class="aips-dropdown-item" data-export="json"><?php esc_html_e('JSON (.json)', 'ai-post-scheduler'); ?></a>
						</div>
					</div>
				</div>
			</div>

			<div class="aips-panel-body">
				<div class="aips-scorecard-grid">
					<!-- Overall Gauge -->
					<div class="aips-overall-gauge-card">
						<div class="aips-gauge-circle" id="aips-overall-gauge-circle">
							<span id="aips-overall-score-val" class="aips-gauge-number">--</span>
							<span class="aips-gauge-caption"><?php esc_html_e('Overall Health', 'ai-post-scheduler'); ?></span>
						</div>
						<div id="aips-overall-status-badge" class="aips-status-badge aips-badge-good"><?php esc_html_e('Good Standing', 'ai-post-scheduler'); ?></div>
					</div>

					<!-- Sub-Score Cards -->
					<div class="aips-subscores-grid">
						<div class="aips-subscore-card">
							<div class="aips-subscore-icon dashicons dashicons-clock"></div>
							<div class="aips-subscore-info">
								<span class="aips-subscore-label"><?php esc_html_e('Freshness & Age', 'ai-post-scheduler'); ?></span>
								<span id="aips-score-freshness" class="aips-subscore-value">--/100</span>
							</div>
						</div>

						<div class="aips-subscore-card">
							<div class="aips-subscore-icon dashicons dashicons-admin-links"></div>
							<div class="aips-subscore-info">
								<span class="aips-subscore-label"><?php esc_html_e('Internal Link Silos', 'ai-post-scheduler'); ?></span>
								<span id="aips-score-links" class="aips-subscore-value">--/100</span>
							</div>
						</div>

						<div class="aips-subscore-card">
							<div class="aips-subscore-icon dashicons dashicons-randomize"></div>
							<div class="aips-subscore-info">
								<span class="aips-subscore-label"><?php esc_html_e('Cannibalization Health', 'ai-post-scheduler'); ?></span>
								<span id="aips-score-cannibalization" class="aips-subscore-value">--/100</span>
							</div>
						</div>

						<div class="aips-subscore-card">
							<div class="aips-subscore-icon dashicons dashicons-chart-area"></div>
							<div class="aips-subscore-info">
								<span class="aips-subscore-label"><?php esc_html_e('Topical Coverage Index', 'ai-post-scheduler'); ?></span>
								<span id="aips-score-gaps" class="aips-subscore-value">--/100</span>
							</div>
						</div>
					</div>
				</div>

				<!-- Key Strategic Takeaways -->
				<div class="aips-takeaways-box" id="aips-takeaways-container">
					<h4><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e('Executive Takeaways & Priorities', 'ai-post-scheduler'); ?></h4>
					<ul id="aips-takeaways-list">
						<!-- Injected via JS -->
					</ul>
				</div>
			</div>
		</div>

		<!-- Detailed Findings Tabs -->
		<div class="aips-content-panel">
			<div class="aips-tab-nav aips-findings-tab-nav">
				<button type="button" class="aips-findings-tab-btn active" data-findings-tab="gaps">
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e('Topic Gaps & Pillars', 'ai-post-scheduler'); ?>
					<span class="aips-tab-badge" id="aips-count-badge-gaps">0</span>
				</button>
				<button type="button" class="aips-findings-tab-btn" data-findings-tab="cannibalization">
					<span class="dashicons dashicons-randomize"></span>
					<?php esc_html_e('Keyword Cannibalization', 'ai-post-scheduler'); ?>
					<span class="aips-tab-badge" id="aips-count-badge-cannibalization">0</span>
				</button>
				<button type="button" class="aips-findings-tab-btn" data-findings-tab="decay">
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e('Content Decay & Freshness', 'ai-post-scheduler'); ?>
					<span class="aips-tab-badge" id="aips-count-badge-decay">0</span>
				</button>
				<button type="button" class="aips-findings-tab-btn" data-findings-tab="links">
					<span class="dashicons dashicons-admin-links"></span>
					<?php esc_html_e('Link Silos & Orphans', 'ai-post-scheduler'); ?>
					<span class="aips-tab-badge" id="aips-count-badge-links">0</span>
				</button>
				<button type="button" class="aips-findings-tab-btn" data-findings-tab="trends">
					<span class="dashicons dashicons-rss"></span>
					<?php esc_html_e('Source Industry Trends', 'ai-post-scheduler'); ?>
					<span class="aips-tab-badge" id="aips-count-badge-trends">0</span>
				</button>
			</div>

			<div class="aips-panel-body">
				<!-- Tab 1: Gaps -->
				<div class="aips-findings-view active" id="aips-view-gaps">
					<div id="aips-gaps-table-container"></div>
				</div>

				<!-- Tab 2: Cannibalization -->
				<div class="aips-findings-view" id="aips-view-cannibalization" style="display: none;">
					<div id="aips-cannibalization-container"></div>
				</div>

				<!-- Tab 3: Decay -->
				<div class="aips-findings-view" id="aips-view-decay" style="display: none;">
					<div id="aips-decay-container"></div>
				</div>

				<!-- Tab 4: Links & Orphans -->
				<div class="aips-findings-view" id="aips-view-links" style="display: none;">
					<div id="aips-links-container"></div>
				</div>

				<!-- Tab 5: Trends -->
				<div class="aips-findings-view" id="aips-view-trends" style="display: none;">
					<div id="aips-trends-container"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- History Modal -->
	<div id="aips-auditor-history-modal" class="aips-modal" style="display: none;">
		<div class="aips-modal-content aips-modal-large">
			<div class="aips-modal-header">
				<h2 class="aips-modal-title"><?php esc_html_e('Audit History & Snapshots', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body" id="aips-auditor-history-modal-body">
				<p class="description"><?php esc_html_e('Loading historical snapshots...', 'ai-post-scheduler'); ?></p>
			</div>
		</div>
	</div>

	<!-- Add to Author Topic Modal -->
	<div id="aips-add-to-author-modal" class="aips-modal" style="display: none;">
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h2 class="aips-modal-title"><?php esc_html_e('Add Topic to Author Persona', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<form id="aips-add-topic-author-form">
					<div class="aips-form-group">
						<label class="aips-form-label"><strong><?php esc_html_e('Topic Title', 'ai-post-scheduler'); ?></strong></label>
						<input type="text" id="aips-modal-topic-title" class="regular-text" style="width: 100%;" required>
					</div>
					<div class="aips-form-group" style="margin-top: 15px;">
						<label class="aips-form-label"><strong><?php esc_html_e('Select Author Persona', 'ai-post-scheduler'); ?></strong></label>
						<select id="aips-modal-author-select" class="aips-form-select" style="width: 100%;" required>
							<option value=""><?php esc_html_e('Select an Author...', 'ai-post-scheduler'); ?></option>
							<?php foreach ($authors as $author): ?>
								<option value="<?php echo esc_attr($author->id); ?>"><?php echo esc_html($author->name); ?> (<?php echo esc_html($author->field_niche); ?>)</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
						<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
						<button type="submit" class="aips-btn aips-btn-primary" id="aips-confirm-add-topic-btn">
							<span class="dashicons dashicons-plus"></span> <?php esc_html_e('Save Topic', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
