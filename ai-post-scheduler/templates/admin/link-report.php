<?php
/**
 * Link Report Admin Tab Partial (Content hub)
 *
 * Inbound / outbound / external / broken link counts for every published
 * post, true orphans (no inbound internal links), a per-post drill-down, and
 * link index rebuild controls. Rows are loaded over AJAX by
 * assets/js/admin-link-report.js.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 *
 * @var array      $summary      Site totals from AIPS_Link_Index_Repository::get_summary().
 * @var int        $orphan_count Published posts with zero inbound internal links.
 * @var int        $total_posts  Published posts in scope.
 * @var array      $post_types   Indexed post types (slug => label).
 * @var bool       $enabled      Whether automatic link indexing is enabled.
 * @var array|null $backfill     Most recent rebuild job status.
 */

if (!defined('ABSPATH')) {
	exit;
}

$backfill_running = is_array($backfill) && in_array($backfill['status'], array('pending', 'processing'), true);
$never_indexed    = (int) $summary['sources'] === 0;

$type_filter_options = array('' => __('All indexed post types', 'ai-post-scheduler'));
foreach ($post_types as $type_slug => $type_label) {
	$type_filter_options[$type_slug] = $type_label;
}
?>

<div class="aips-link-report-tab" id="aips-link-report" data-backfill-running="<?php echo $backfill_running ? '1' : '0'; ?>">

	<?php if (!$enabled) : ?>
	<div class="notice notice-warning inline aips-banner">
		<div class="aips-banner-inner">
			<div>
				<h4 class="aips-banner-title">
					<span class="dashicons dashicons-warning aips-banner-icon" aria-hidden="true"></span>
					<?php esc_html_e('Link indexing is turned off', 'ai-post-scheduler'); ?>
				</h4>
				<p class="aips-banner-desc"><?php esc_html_e('Saved posts are not being re-read, so these numbers may be out of date.', 'ai-post-scheduler'); ?></p>
			</div>
			<div>
				<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#settings-ai')); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<?php esc_html_e('Open Settings', 'ai-post-scheduler'); ?>
				</a>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Link health metrics -->
	<div class="aips-stats-grid">
		<div class="aips-stat-card">
			<div class="aips-stat-header">
				<span class="aips-stat-label"><?php esc_html_e('Orphaned Posts', 'ai-post-scheduler'); ?></span>
				<span class="dashicons dashicons-warning aips-stat-icon" aria-hidden="true"></span>
			</div>
			<div class="aips-stat-value-wrap">
				<span class="aips-stat-value aips-text-warning" id="aips-link-stat-orphans"><?php echo esc_html((string) $orphan_count); ?></span>
				<span class="aips-stat-total">/ <span id="aips-link-stat-posts"><?php echo esc_html((string) $total_posts); ?></span></span>
			</div>
			<p class="aips-stat-subtext"><?php esc_html_e('Published posts no other post links to', 'ai-post-scheduler'); ?></p>
		</div>

		<div class="aips-stat-card">
			<div class="aips-stat-header">
				<span class="aips-stat-label"><?php esc_html_e('Internal Links', 'ai-post-scheduler'); ?></span>
				<span class="dashicons dashicons-admin-links aips-stat-icon" aria-hidden="true"></span>
			</div>
			<div class="aips-stat-value-wrap">
				<span class="aips-stat-value aips-text-success" id="aips-link-stat-internal"><?php echo esc_html((string) $summary['internal']); ?></span>
			</div>
			<p class="aips-stat-subtext"><?php esc_html_e('Links between your own posts', 'ai-post-scheduler'); ?></p>
		</div>

		<div class="aips-stat-card">
			<div class="aips-stat-header">
				<span class="aips-stat-label"><?php esc_html_e('External Links', 'ai-post-scheduler'); ?></span>
				<span class="dashicons dashicons-external aips-stat-icon" aria-hidden="true"></span>
			</div>
			<div class="aips-stat-value-wrap">
				<span class="aips-stat-value" id="aips-link-stat-external"><?php echo esc_html((string) $summary['external']); ?></span>
			</div>
			<p class="aips-stat-subtext"><?php esc_html_e('Links to other websites', 'ai-post-scheduler'); ?></p>
		</div>

		<div class="aips-stat-card">
			<div class="aips-stat-header">
				<span class="aips-stat-label"><?php esc_html_e('Broken Internal Links', 'ai-post-scheduler'); ?></span>
				<span class="dashicons dashicons-dismiss aips-stat-icon" aria-hidden="true"></span>
			</div>
			<div class="aips-stat-value-wrap">
				<span class="aips-stat-value aips-text-danger" id="aips-link-stat-broken"><?php echo esc_html((string) $summary['broken']); ?></span>
			</div>
			<p class="aips-stat-subtext"><?php esc_html_e('Links to pages on this site that no longer exist', 'ai-post-scheduler'); ?></p>
		</div>
	</div>

	<!-- Rebuild progress -->
	<div class="notice notice-info inline aips-banner<?php echo $backfill_running ? '' : ' aips-hidden'; ?>" id="aips-link-backfill-banner">
		<div class="aips-banner-inner">
			<div>
				<h4 class="aips-banner-title">
					<span class="spinner is-active" aria-hidden="true"></span>
					<?php esc_html_e('Building the link index…', 'ai-post-scheduler'); ?>
				</h4>
				<p class="aips-banner-desc" id="aips-link-backfill-progress">
					<?php
					if ($backfill_running) {
						printf(
							/* translators: 1: processed posts, 2: total posts */
							esc_html__('%1$d of %2$d posts processed.', 'ai-post-scheduler'),
							(int) $backfill['processed'],
							(int) $backfill['total']
						);
					}
					?>
				</p>
			</div>
		</div>
	</div>

	<?php
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-link-report-panel',
			'title'       => __('Link Report', 'ai-post-scheduler'),
			'icon'        => 'dashicons-admin-links',
			'description' => __('Every published post with the links it sends and receives. Posts with no inbound internal links are orphans: readers and search engines can only reach them from menus, archives or search.', 'ai-post-scheduler'),
			'actions'     => array(
				array(
					'id'    => 'aips-link-report-rebuild',
					'label' => $never_indexed ? __('Build Link Index', 'ai-post-scheduler') : __('Rebuild Link Index', 'ai-post-scheduler'),
					'icon'  => 'dashicons-update',
					'class' => $never_indexed ? 'aips-btn aips-btn-primary' : 'aips-btn aips-btn-secondary',
				),
			),
			'body_class'  => 'no-padding',
		),
		function () use ($type_filter_options, $never_indexed) {
			AIPS_Admin_UI_Primitives::render_action_toolbar(array(
				'class'   => 'aips-link-report-toolbar',
				'search'  => array(
					'id'          => 'aips-link-report-search',
					'name'        => 'aips_link_report_search',
					'placeholder' => __('Search post titles…', 'ai-post-scheduler'),
				),
				'filters' => array(
					array(
						'id'      => 'aips-link-report-post-type',
						'options' => $type_filter_options,
					),
					array(
						'id'      => 'aips-link-report-view',
						'options' => array(
							'all'     => __('All posts', 'ai-post-scheduler'),
							'orphans' => __('Orphans only', 'ai-post-scheduler'),
						),
					),
				),
			));
			?>
			<div id="aips-link-report-loading" class="aips-audit-loading aips-hidden">
				<span class="spinner is-active"></span>
				<?php esc_html_e('Loading link report…', 'ai-post-scheduler'); ?>
			</div>

			<table class="aips-table widefat striped" id="aips-link-report-table">
				<thead>
					<tr>
						<th scope="col"><button type="button" class="aips-sort-link" data-orderby="title"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></button></th>
						<th scope="col"><button type="button" class="aips-sort-link" data-orderby="inbound"><?php esc_html_e('Inbound', 'ai-post-scheduler'); ?></button></th>
						<th scope="col"><button type="button" class="aips-sort-link" data-orderby="outbound"><?php esc_html_e('Outbound', 'ai-post-scheduler'); ?></button></th>
						<th scope="col"><button type="button" class="aips-sort-link" data-orderby="external"><?php esc_html_e('External', 'ai-post-scheduler'); ?></button></th>
						<th scope="col"><?php esc_html_e('Broken', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-link-report-tbody"></tbody>
			</table>

			<div class="aips-hidden" id="aips-link-report-empty-wrap">
				<?php
				AIPS_Admin_UI_Primitives::render_empty_state(array(
					'id'      => 'aips-link-report-empty',
					'icon'    => 'dashicons-admin-links',
					'title'   => $never_indexed ? __('The link index has not been built yet', 'ai-post-scheduler') : __('No posts match these filters', 'ai-post-scheduler'),
					'message' => $never_indexed
						? __('Build the index once to scan existing content. After that, posts are re-scanned automatically whenever they are saved.', 'ai-post-scheduler')
						: __('Try a different search or post type.', 'ai-post-scheduler'),
				));
				?>
			</div>

			<div class="aips-pagination" id="aips-link-report-pagination">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-link-report-prev" disabled>
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					<?php esc_html_e('Previous', 'ai-post-scheduler'); ?>
				</button>
				<span class="aips-pagination-info" id="aips-link-report-page-info"></span>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-link-report-next" disabled>
					<?php esc_html_e('Next', 'ai-post-scheduler'); ?>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</button>
			</div>
			<?php
		}
	);
	?>

	<!-- Per-post drill-down -->
	<div class="aips-modal" id="aips-link-report-modal" role="dialog" aria-modal="true" aria-labelledby="aips-link-report-modal-title" style="display:none;">
		<div class="aips-modal-content aips-modal-large">
			<div class="aips-modal-header">
				<h2 id="aips-link-report-modal-title"></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<h3><?php esc_html_e('Linked from', 'ai-post-scheduler'); ?> <span class="aips-badge aips-badge-secondary" id="aips-link-report-inbound-count"></span></h3>
				<table class="aips-table widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e('Source Post', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Anchor Text', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody id="aips-link-report-inbound"></tbody>
				</table>

				<h3><?php esc_html_e('Links in this post', 'ai-post-scheduler'); ?> <span class="aips-badge aips-badge-secondary" id="aips-link-report-outbound-count"></span></h3>
				<table class="aips-table widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e('Anchor Text', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Destination', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody id="aips-link-report-outbound"></tbody>
				</table>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
	</div>

	<!-- Row templates (rendered by AIPS.Templates) -->
	<script type="text/html" id="aips-tmpl-link-report-row">
		<tr data-post-id="{{id}}">
			<td>
				<strong><a href="{{edit_url}}">{{title}}</a></strong>
				<span class="aips-text-muted">{{post_type}}</span>
				<span class="aips-badge aips-badge-warning {{orphan_class}}"><?php esc_html_e('Orphan', 'ai-post-scheduler'); ?></span>
			</td>
			<td>{{inbound}}</td>
			<td>{{outbound}}</td>
			<td>{{external}}</td>
			<td>{{broken}}</td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-link-report-details" data-post-id="{{id}}"><?php esc_html_e('View Links', 'ai-post-scheduler'); ?></button>
				<a href="{{view_url}}" class="aips-btn aips-btn-sm aips-btn-ghost" target="_blank" rel="noopener"><?php esc_html_e('View Post', 'ai-post-scheduler'); ?></a>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-link-report-inbound-row">
		<tr>
			<td><a href="{{source_edit}}">{{source_title}}</a></td>
			<td>{{anchor}}</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-link-report-outbound-row">
		<tr>
			<td>{{anchor}}</td>
			<td><a href="{{url}}" target="_blank" rel="noopener">{{destination}}</a></td>
			<td><span class="aips-badge {{type_class}}">{{type_label}}</span></td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-link-report-empty-row">
		<tr><td colspan="{{colspan}}" class="aips-text-muted">{{message}}</td></tr>
	</script>
</div><!-- /.aips-link-report-tab -->
