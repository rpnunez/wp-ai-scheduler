<?php
/**
 * Silos Admin Tab Partial (Content hub)
 *
 * Topic clusters with a confirmed pillar (silos), their link coverage and
 * Fix silo; clusters without a confirmed pillar get a suggested one. Data
 * loads over AJAX (assets/js/admin-silos.js).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 *
 * @var bool   $guide_enabled Whether the "In this guide" list is on.
 * @var string $settings_url  Settings → Internal Linking.
 * @var string $review_url    Inbound suggestion review queue.
 * @var string $report_url    Link Report (run history and undo).
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-silos-tab aips-link-report-tab" id="aips-silos" data-review-url="<?php echo esc_url($review_url); ?>" data-report-url="<?php echo esc_url($report_url); ?>">

	<?php
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-silos-panel',
			'title'       => __('Silos', 'ai-post-scheduler'),
			'icon'        => 'dashicons-index-card',
			'description' => __('A silo is a topic cluster with a pillar you confirmed. Each article should link up to the pillar, and the pillar should reach every article. Fix silo adds the missing article → pillar links in the articles\' text (inserted when Bulk Auto-Linking is on, otherwise sent to review; undo from the Link Report). The pillar reaches its articles through its own links plus an "In this guide" list shown when it is displayed; its text is never edited.', 'ai-post-scheduler'),
		),
		function () use ($guide_enabled, $settings_url) {
			?>
			<div class="aips-silos-toolbar">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-silos-refresh">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e('Re-detect Clusters', 'ai-post-scheduler'); ?>
				</button>
				<a class="aips-btn aips-btn-sm aips-btn-ghost" href="<?php echo esc_url($settings_url); ?>">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<?php esc_html_e('"In this guide" Settings', 'ai-post-scheduler'); ?>
				</a>
				<?php if (!$guide_enabled) : ?>
					<span class="aips-badge aips-badge-warning"><?php esc_html_e('"In this guide" list is off', 'ai-post-scheduler'); ?></span>
				<?php endif; ?>
			</div>
			<p class="aips-silos-loading" id="aips-silos-loading"><span class="spinner is-active"></span> <?php esc_html_e('Loading silos…', 'ai-post-scheduler'); ?></p>
			<div id="aips-silos-list" class="aips-silos-list"></div>
			<?php
		}
	);

	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-silos-candidates-panel',
			'title'       => __('Clusters Without a Pillar', 'ai-post-scheduler'),
			'icon'        => 'dashicons-lightbulb',
			'description' => __('These clusters become silos once you confirm a pillar: the main guide for the topic. AI Post Scheduler suggests one from inbound links, length and how closely it matches the cluster\'s topic. Nothing changes until you confirm.', 'ai-post-scheduler'),
			'body_class'  => 'no-padding',
		),
		function () {
			?>
			<table class="aips-table widefat striped" id="aips-silos-candidates-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Cluster', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Suggested Pillar', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Pillar', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-silos-candidates-tbody"></tbody>
			</table>
			<?php
		}
	);
	?>

	<script type="text/html" id="aips-tmpl-silo-card">
		<div class="aips-silo-card" data-cluster="{{cluster_id}}">
			<div class="aips-silo-card-head">
				<div class="aips-silo-card-title">
					<strong>{{name}}</strong>
					<span class="aips-text-muted"><?php esc_html_e('Pillar:', 'ai-post-scheduler'); ?> <a href="{{pillar_url}}" target="_blank" rel="noopener">{{pillar_title}}</a></span>
				</div>
				<div class="aips-silo-health">
					<div class="aips-silo-health-bar" role="img" aria-label="{{health_label}}"><span class="{{health_class}}" style="width: {{health}}%"></span></div>
					<small>{{health_label}}</small>
				</div>
			</div>
			<ul class="aips-silo-stats">
				<li>{{up_label}}</li>
				<li>{{down_label}}</li>
			</ul>
			<div class="aips-silo-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-silo-fix" data-cluster="{{cluster_id}}" {{fix_disabled}}><?php esc_html_e('Fix Silo', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-silo-toggle" data-cluster="{{cluster_id}}"><?php esc_html_e('Show articles', 'ai-post-scheduler'); ?></button>
				<label class="screen-reader-text" for="aips-silo-pillar-{{cluster_id}}"><?php esc_html_e('Change pillar', 'ai-post-scheduler'); ?></label>
				<select id="aips-silo-pillar-{{cluster_id}}" class="aips-silo-pillar-select" data-cluster="{{cluster_id}}"></select>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-silo-change" data-cluster="{{cluster_id}}"><?php esc_html_e('Change Pillar', 'ai-post-scheduler'); ?></button>
			</div>
			<table class="aips-table widefat striped aips-silo-members aips-hidden" data-cluster="{{cluster_id}}">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Article', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Links to Pillar', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Pillar Links Here', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</script>

	<script type="text/html" id="aips-tmpl-silo-member">
		<tr>
			<td><a href="{{edit_url}}" target="_blank" rel="noopener">{{title}}</a></td>
			<td><span class="aips-badge {{up_class}}">{{up_label}}</span></td>
			<td><span class="aips-badge {{down_class}}">{{down_label}}</span></td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-silo-option">
		<option value="{{id}}" {{selected}}>{{title}}</option>
	</script>

	<script type="text/html" id="aips-tmpl-silo-candidate">
		<tr>
			<td><strong>{{name}}</strong><br><small class="aips-text-muted">{{total_label}}</small></td>
			<td>
				<a href="{{url}}" target="_blank" rel="noopener">{{title}}</a><br>
				<small class="aips-text-muted">{{reasons}}</small>
			</td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-silo-confirm" data-cluster="{{cluster_id}}" data-post="{{id}}"><?php esc_html_e('Use This', 'ai-post-scheduler'); ?></button>
				<label class="screen-reader-text" for="aips-silo-pick-{{cluster_id}}"><?php esc_html_e('Pick another pillar', 'ai-post-scheduler'); ?></label>
				<select id="aips-silo-pick-{{cluster_id}}" class="aips-silo-pick" data-cluster="{{cluster_id}}"></select>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-silo-confirm-picked" data-cluster="{{cluster_id}}"><?php esc_html_e('Use Selected', 'ai-post-scheduler'); ?></button>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-silo-empty">
		<p class="aips-text-muted">{{message}}</p>
	</script>

	<script type="text/html" id="aips-tmpl-silo-candidates-empty">
		<tr><td colspan="3" class="aips-text-muted">{{message}}</td></tr>
	</script>
</div>
