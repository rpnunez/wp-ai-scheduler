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
 * @var bool       $is_built     Whether any post has been scanned yet.
 * @var int        $orphan_count Published posts with zero inbound internal links.
 * @var int        $total_posts  Published posts in scope.
 * @var array      $post_types   Indexed post types (slug => label).
 * @var bool       $enabled      Whether automatic link indexing is enabled.
 * @var array|null $backfill     Most recent rebuild job status.
 * @var array      $autolink     Auto-link state (enabled, current run, history, pending_review, review_url).
 * @var array      $clicks       Click tracking state (enabled, days, total, top).
 */

if (!defined('ABSPATH')) {
	exit;
}

$backfill_running = is_array($backfill) && in_array($backfill['status'], array('pending', 'processing'), true);
$backfill_paused  = is_array($backfill) && $backfill['status'] === AIPS_Link_Index_Service::STATUS_PAUSED;
$never_indexed    = empty($is_built);

$type_filter_options = array('' => __('All indexed post types', 'ai-post-scheduler'));
foreach ($post_types as $type_slug => $type_label) {
	$type_filter_options[$type_slug] = $type_label;
}
?>

<div class="aips-link-report-tab<?php echo empty($clicks['enabled']) ? ' aips-link-clicks-off' : ''; ?>" id="aips-link-report" data-backfill-running="<?php echo $backfill_running ? '1' : '0'; ?>" data-backfill-paused="<?php echo $backfill_paused ? '1' : '0'; ?>">

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
				<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings&tab=settings-linking')); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
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
				<?php if ($never_indexed) : ?>
					<span class="aips-stat-value" id="aips-link-stat-orphans">&mdash;</span>
				<?php else : ?>
					<span class="aips-stat-value aips-text-warning" id="aips-link-stat-orphans"><?php echo esc_html((string) $orphan_count); ?></span>
					<span class="aips-stat-total">/ <span id="aips-link-stat-posts"><?php echo esc_html((string) $total_posts); ?></span></span>
				<?php endif; ?>
			</div>
			<p class="aips-stat-subtext">
				<?php
				echo $never_indexed
					? esc_html__('Build the link index to find orphans', 'ai-post-scheduler')
					: esc_html__('Published posts no other post links to', 'ai-post-scheduler');
				?>
			</p>
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

		<?php if (!empty($clicks['enabled'])) : ?>
		<div class="aips-stat-card">
			<div class="aips-stat-header">
				<span class="aips-stat-label"><?php esc_html_e('Internal Link Clicks', 'ai-post-scheduler'); ?></span>
				<span class="dashicons dashicons-chart-bar aips-stat-icon" aria-hidden="true"></span>
			</div>
			<div class="aips-stat-value-wrap">
				<span class="aips-stat-value aips-text-info"><?php echo esc_html(number_format_i18n((int) $clicks['total'])); ?></span>
			</div>
			<p class="aips-stat-subtext">
				<?php
				/* translators: %d: number of days */
				echo esc_html(sprintf(_n('Clicks on links between your posts, last %d day', 'Clicks on links between your posts, last %d days', (int) $clicks['days'], 'ai-post-scheduler'), (int) $clicks['days']));
				?>
			</p>
		</div>
		<?php endif; ?>
	</div>

	<!-- Scan progress -->
	<div class="notice notice-info inline aips-banner<?php echo ($backfill_running || $backfill_paused) ? '' : ' aips-hidden'; ?>" id="aips-link-backfill-banner">
		<div class="aips-banner-inner">
			<div>
				<h4 class="aips-banner-title">
					<span class="spinner is-active<?php echo $backfill_paused ? ' aips-hidden' : ''; ?>" id="aips-link-backfill-spinner" aria-hidden="true"></span>
					<span id="aips-link-backfill-title">
						<?php echo $backfill_paused ? esc_html__('Link scan paused', 'ai-post-scheduler') : esc_html__('Scanning posts for links…', 'ai-post-scheduler'); ?>
					</span>
				</h4>
				<p class="aips-banner-desc" id="aips-link-backfill-progress">
					<?php
					if ($backfill_running || $backfill_paused) {
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
			<div class="aips-link-backfill-controls">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary<?php echo $backfill_paused ? ' aips-hidden' : ''; ?>" id="aips-link-backfill-pause">
					<span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
					<?php esc_html_e('Pause', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary<?php echo $backfill_paused ? '' : ' aips-hidden'; ?>" id="aips-link-backfill-resume">
					<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
					<?php esc_html_e('Resume', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-danger" id="aips-link-backfill-cancel">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>

	<?php
	$autolink_threshold = (int) round((new AIPS_Autolink_Policy())->get_settings()['auto_apply_threshold'] * 100);
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-autolink-panel',
			'title'       => __('Automatic Linking', 'ai-post-scheduler'),
			'icon'        => 'dashicons-controls-repeat',
			'description' => $autolink['enabled']
				/* translators: %d: auto-apply confidence threshold in percent */
				? sprintf(__('Runs find inbound links for posts that need them. Links at or above %d%% confidence are inserted automatically; the rest wait for review. Every run can be undone.', 'ai-post-scheduler'), $autolink_threshold)
				: __('Bulk Auto-Linking is off in Settings, so runs only create suggestions for you to review. Turn it on under Settings → Internal Linking to insert confident links automatically.', 'ai-post-scheduler'),
			'actions'     => array(
				array(
					'type'  => 'link',
					'url'   => $autolink['review_url'],
					'label' => sprintf(
						/* translators: %d: number of suggestions awaiting review */
						__('Review Suggestions (%d)', 'ai-post-scheduler'),
						(int) $autolink['pending_review']
					),
					'icon'  => 'dashicons-visibility',
					'id'    => 'aips-autolink-review-link',
				),
				array(
					'id'    => 'aips-autolink-start-btn',
					'label' => __('Start Auto-Link Run', 'ai-post-scheduler'),
					'icon'  => 'dashicons-controls-play',
					'class' => 'aips-btn aips-btn-primary',
				),
			),
			'body_class'  => 'no-padding',
		),
		function () {
			?>
			<div class="notice notice-info inline aips-banner aips-hidden" id="aips-autolink-banner">
				<div class="aips-banner-inner">
					<div>
						<h4 class="aips-banner-title">
							<span class="spinner is-active" id="aips-autolink-spinner" aria-hidden="true"></span>
							<span id="aips-autolink-title"></span>
						</h4>
						<p class="aips-banner-desc" id="aips-autolink-progress"></p>
					</div>
					<div class="aips-link-backfill-controls">
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-autolink-pause">
							<span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
							<?php esc_html_e('Pause', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-hidden" id="aips-autolink-resume">
							<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
							<?php esc_html_e('Resume', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-danger" id="aips-autolink-cancel">
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
							<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>
			</div>

			<table class="aips-table widefat striped" id="aips-autolink-history">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Run', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Posts', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Inserted', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('For Review', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-autolink-history-tbody"></tbody>
			</table>
			<?php
		}
	);
	?>

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
					'label' => $never_indexed ? __('Build Link Index', 'ai-post-scheduler') : __('Scan Links', 'ai-post-scheduler'),
					'icon'  => 'dashicons-update',
					'class' => $never_indexed ? 'aips-btn aips-btn-primary' : 'aips-btn aips-btn-secondary',
				),
			),
			'body_class'  => 'no-padding',
		),
		function () use ($type_filter_options, $never_indexed, $clicks) {
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
						<th scope="col" class="aips-link-clicks-col">
							<?php
							/* translators: %d: number of days */
							echo esc_html(sprintf(__('Clicks (%dd)', 'ai-post-scheduler'), (int) $clicks['days']));
							?>
						</th>
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

	<?php if (!empty($clicks['enabled'])) : ?>
	<!-- Most-clicked internal links -->
	<?php
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-link-clicks-panel',
			'title'       => __('Most-Clicked Internal Links', 'ai-post-scheduler'),
			'icon'        => 'dashicons-chart-bar',
			'description' => sprintf(
				/* translators: %d: number of days */
				__('Which links between your posts visitors actually follow (last %d days). Links nobody clicks may need better placement or anchor text.', 'ai-post-scheduler'),
				(int) $clicks['days']
			),
			'body_class'  => 'no-padding',
		),
		function () use ($clicks) {
			if (empty($clicks['top'])) {
				AIPS_Admin_UI_Primitives::render_empty_state(array(
					'icon'    => 'dashicons-chart-bar',
					'title'   => __('No clicks recorded yet', 'ai-post-scheduler'),
					'message' => __('Clicks by visitors appear here within minutes. Your own clicks while logged in as an editor are not counted.', 'ai-post-scheduler'),
				));
				return;
			}
			?>
			<table class="aips-table widefat striped" id="aips-link-clicks-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('From', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('To', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Clicks', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($clicks['top'] as $link) : ?>
					<tr>
						<td><a href="<?php echo esc_url($link['source_edit']); ?>"><?php echo esc_html($link['source_title']); ?></a></td>
						<td><a href="<?php echo esc_url($link['target_edit']); ?>"><?php echo esc_html($link['target_title']); ?></a></td>
						<td><?php echo esc_html(number_format_i18n((int) $link['clicks'])); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}
	);
	?>
	<?php endif; ?>

	<!-- Broken internal links -->
	<?php
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-broken-links-panel',
			'title'       => __('Broken Internal Links', 'ai-post-scheduler'),
			'icon'        => 'dashicons-editor-unlink',
			'description' => __('Links to pages on this site that no longer exist. Each one lists the live posts it most likely meant; re-point it, or remove the link and keep its text. Every fix can be undone.', 'ai-post-scheduler'),
			'actions'     => array(
				array(
					'id'    => 'aips-broken-links-load',
					'label' => __('Find Fixes', 'ai-post-scheduler'),
					'icon'  => 'dashicons-search',
				),
			),
			'body_class'  => 'no-padding',
		),
		function () {
			?>
			<div id="aips-broken-links-loading" class="aips-audit-loading aips-hidden">
				<span class="spinner is-active"></span>
				<?php esc_html_e('Finding replacements for broken links…', 'ai-post-scheduler'); ?>
			</div>
			<table class="aips-table widefat striped aips-hidden" id="aips-broken-links-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('In Post', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Broken Link', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Fix With', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-broken-links-tbody"></tbody>
			</table>
			<div class="aips-pagination aips-hidden" id="aips-broken-links-pagination">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-broken-links-prev" disabled><?php esc_html_e('Previous', 'ai-post-scheduler'); ?></button>
				<span class="aips-pagination-info" id="aips-broken-links-page-info"></span>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-broken-links-next" disabled><?php esc_html_e('Next', 'ai-post-scheduler'); ?></button>
			</div>
			<div class="aips-broken-fixes aips-hidden" id="aips-broken-fixes-wrap">
				<h4><?php esc_html_e('Recent fixes', 'ai-post-scheduler'); ?></h4>
				<ul id="aips-broken-fixes"></ul>
			</div>
			<?php
		}
	);
	?>

	<!-- Manual replacement picker -->
	<div class="aips-modal" id="aips-broken-pick-modal" role="dialog" aria-modal="true" aria-labelledby="aips-broken-pick-title" style="display:none;">
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h2 id="aips-broken-pick-title"><?php esc_html_e('Choose a post to link to', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<label for="aips-broken-pick-search" class="screen-reader-text"><?php esc_html_e('Search posts', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-broken-pick-search" class="aips-form-input widefat" placeholder="<?php esc_attr_e('Search post titles…', 'ai-post-scheduler'); ?>">
				<ul class="aips-broken-pick-results" id="aips-broken-pick-results"></ul>
			</div>
		</div>
	</div>

	<!-- Inbound link suggestions -->
	<div class="aips-hidden" id="aips-link-suggestions-wrap">
		<?php
		AIPS_Admin_UI_Primitives::render_card(
			array(
				'id'          => 'aips-link-suggestions-panel',
				'title'       => __('Suggested Inbound Links', 'ai-post-scheduler'),
				'icon'        => 'dashicons-randomize',
				'description' => __('Existing posts that could link to the selected post, found from semantic similarity (embeddings) with a keyword fallback. Links are placed in body text only and every insertion can be undone.', 'ai-post-scheduler'),
				'actions'     => array(
					array(
						'id'    => 'aips-link-suggestions-regenerate',
						'label' => __('Find Again', 'ai-post-scheduler'),
						'icon'  => 'dashicons-update',
					),
					array(
						'id'    => 'aips-link-suggestions-close',
						'label' => __('Close', 'ai-post-scheduler'),
						'icon'  => 'dashicons-no-alt',
						'class' => 'aips-btn aips-btn-ghost aips-btn-sm',
					),
				),
				'body_class'  => 'no-padding',
			),
			function () {
				?>
				<p class="aips-link-suggestions-target">
					<?php esc_html_e('Linking to:', 'ai-post-scheduler'); ?>
					<strong id="aips-link-suggestions-title"></strong>
				</p>
				<div id="aips-link-suggestions-loading" class="aips-audit-loading aips-hidden">
					<span class="spinner is-active"></span>
					<?php esc_html_e('Finding posts that could link here…', 'ai-post-scheduler'); ?>
				</div>
				<table class="aips-table widefat striped" id="aips-link-suggestions-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e('Link From', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Anchor Text in Context', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Confidence', 'ai-post-scheduler'); ?></th>
							<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody id="aips-link-suggestions-tbody"></tbody>
				</table>
				<?php
			}
		);
		?>
	</div>

	<!-- Auto-link run options -->
	<div class="aips-modal" id="aips-autolink-modal" role="dialog" aria-modal="true" aria-labelledby="aips-autolink-modal-title" style="display:none;">
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h2 id="aips-autolink-modal-title"><?php esc_html_e('Start an auto-link run', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e('Which posts', 'ai-post-scheduler'); ?></legend>
					<label class="aips-checkbox-label-block">
						<input type="radio" name="aips_autolink_scope" value="orphans" checked>
						<strong><?php esc_html_e('Orphans only', 'ai-post-scheduler'); ?></strong>
						<span class="description"><?php esc_html_e('Posts no other post links to.', 'ai-post-scheduler'); ?></span>
					</label>
					<label class="aips-checkbox-label-block">
						<input type="radio" name="aips_autolink_scope" value="low">
						<strong><?php esc_html_e('Posts with fewer than 3 inbound links', 'ai-post-scheduler'); ?></strong>
						<span class="description"><?php esc_html_e('Orphans plus weakly linked posts.', 'ai-post-scheduler'); ?></span>
					</label>
				</fieldset>
				<p>
					<label>
						<input type="checkbox" id="aips-autolink-dry-run" <?php checked(!$autolink['enabled']); ?> <?php disabled(!$autolink['enabled']); ?>>
						<?php esc_html_e('Dry run: only create suggestions for review, insert nothing', 'ai-post-scheduler'); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e('Runs work in the background in small batches using the pause set under Settings → Internal Linking → Rebuild Speed, and can be paused or cancelled.', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-primary" id="aips-autolink-confirm">
					<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
					<?php esc_html_e('Start Run', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Scan options -->
	<div class="aips-modal" id="aips-link-scan-modal" role="dialog" aria-modal="true" aria-labelledby="aips-link-scan-modal-title" style="display:none;">
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h2 id="aips-link-scan-modal-title"><?php esc_html_e('Scan posts for links', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body">
				<p class="description"><?php esc_html_e('Saving a post already updates its links automatically, so a full rescan is rarely needed. Scans run in the background (no AI calls) and can be paused or cancelled.', 'ai-post-scheduler'); ?></p>
				<fieldset class="aips-link-scan-modes">
					<legend class="screen-reader-text"><?php esc_html_e('What to scan', 'ai-post-scheduler'); ?></legend>
					<label class="aips-checkbox-label-block">
						<input type="radio" name="aips_link_scan_mode" value="missing" <?php checked(!$never_indexed); ?>>
						<strong><?php esc_html_e('New & never-scanned posts', 'ai-post-scheduler'); ?></strong>
						<span class="description"><?php esc_html_e('Only posts that are not in the link index yet. Fastest.', 'ai-post-scheduler'); ?></span>
					</label>
					<label class="aips-checkbox-label-block">
						<input type="radio" name="aips_link_scan_mode" value="recent">
						<strong>
							<?php esc_html_e('Posts updated in the last', 'ai-post-scheduler'); ?>
							<input type="number" id="aips-link-scan-days" class="small-text" min="1" max="3650" value="30" aria-label="<?php esc_attr_e('Days', 'ai-post-scheduler'); ?>">
							<?php esc_html_e('days', 'ai-post-scheduler'); ?>
						</strong>
						<span class="description"><?php esc_html_e('Catches edits made outside the editor, such as imports or bulk updates.', 'ai-post-scheduler'); ?></span>
					</label>
					<label class="aips-checkbox-label-block">
						<input type="radio" name="aips_link_scan_mode" value="all" <?php checked($never_indexed); ?>>
						<strong><?php esc_html_e('Full rescan of every published post', 'ai-post-scheduler'); ?></strong>
						<span class="description"><?php esc_html_e('Needed only after changing permalinks, restoring a backup or changing the indexed post types.', 'ai-post-scheduler'); ?></span>
					</label>
				</fieldset>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-primary" id="aips-link-scan-start">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e('Start Scan', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>

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
							<th scope="col" class="aips-link-clicks-col"><?php esc_html_e('Clicks', 'ai-post-scheduler'); ?></th>
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
							<th scope="col" class="aips-link-clicks-col"><?php esc_html_e('Clicks', 'ai-post-scheduler'); ?></th>
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
				<span class="aips-badge aips-badge-info {{suggestions_class}}">{{suggestions_label}}</span>
			</td>
			<td>{{inbound}}</td>
			<td>{{outbound}}</td>
			<td>{{external}}</td>
			<td>{{broken}}</td>
			<td class="aips-link-clicks-col">{{clicks}}</td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm {{suggest_class}} aips-link-report-suggest" data-post-id="{{id}}" data-title="{{title}}"><?php esc_html_e('Suggest Links', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-link-report-details" data-post-id="{{id}}"><?php esc_html_e('View Links', 'ai-post-scheduler'); ?></button>
				<a href="{{view_url}}" class="aips-btn aips-btn-sm aips-btn-ghost" target="_blank" rel="noopener"><?php esc_html_e('View Post', 'ai-post-scheduler'); ?></a>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-link-report-inbound-row">
		<tr>
			<td><a href="{{source_edit}}">{{source_title}}</a></td>
			<td>{{anchor}}</td>
			<td class="aips-link-clicks-col">{{clicks}}</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-link-report-outbound-row">
		<tr>
			<td>{{anchor}}</td>
			<td><a href="{{url}}" target="_blank" rel="noopener">{{destination}}</a></td>
			<td><span class="aips-badge {{type_class}}">{{type_label}}</span></td>
			<td class="aips-link-clicks-col">{{clicks}}</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-link-report-empty-row">
		<tr><td colspan="{{colspan}}" class="aips-text-muted">{{message}}</td></tr>
	</script>
	<script type="text/html" id="aips-tmpl-link-suggestion-row">
		<tr data-suggestion-id="{{id}}" class="aips-link-suggestion-{{status}}">
			<td>
				<a href="{{source_edit}}">{{source_title}}</a>
				<span class="aips-badge aips-badge-success {{inserted_class}}"><?php esc_html_e('Link inserted', 'ai-post-scheduler'); ?></span>
			</td>
			<td>
				<strong>{{anchor_label}}</strong>
				<span class="aips-badge aips-badge-info {{gsc_class}}" title="<?php esc_attr_e('Visitors find the linked post on Google with this search query (Search Console).', 'ai-post-scheduler'); ?>"><?php esc_html_e('Search query', 'ai-post-scheduler'); ?></span>
				<span class="aips-text-muted aips-link-suggestion-context">{{context}}</span>
			</td>
			<td><span class="aips-badge {{confidence_class}}">{{confidence}}%</span></td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-link-suggestion-apply {{pending_class}}" data-id="{{id}}" {{apply_disabled}}><?php esc_html_e('Insert Link', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-link-suggestion-dismiss {{pending_class}}" data-id="{{id}}"><?php esc_html_e('Dismiss', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-link-suggestion-revert {{inserted_class}}" data-id="{{id}}"><?php esc_html_e('Undo', 'ai-post-scheduler'); ?></button>
			</td>
		</tr>
	</script>
	<script type="text/html" id="aips-tmpl-autolink-run-row">
		<tr>
			<td>{{started}}<span class="aips-text-muted">{{scope_label}}</span></td>
			<td>{{processed}} / {{total}}</td>
			<td>{{applied}}</td>
			<td>{{review}}</td>
			<td><span class="aips-badge {{status_class}}">{{status_label}}</span></td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-autolink-undo {{undo_class}}" data-job-id="{{job_id}}"><?php esc_html_e('Undo Run', 'ai-post-scheduler'); ?></button>
			</td>
		</tr>
	</script>
	<script type="text/html" id="aips-tmpl-broken-row">
		<tr data-row="{{index}}">
			<td><a href="{{source_edit}}">{{source_title}}</a></td>
			<td>
				<strong>{{anchor}}</strong>
				<span class="aips-text-muted aips-broken-url">{{url}}</span>
				<span class="aips-text-muted {{occurrences_class}}">{{occurrences_label}}</span>
			</td>
			<td>
				<label class="screen-reader-text" for="aips-broken-choice-{{index}}"><?php esc_html_e('Fix with', 'ai-post-scheduler'); ?></label>
				<select class="aips-form-select aips-broken-choice" id="aips-broken-choice-{{index}}" data-row="{{index}}"></select>
			</td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-broken-fix" data-row="{{index}}"><?php esc_html_e('Fix', 'ai-post-scheduler'); ?></button>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-broken-option">
		<option value="{{value}}">{{label}}</option>
	</script>

	<script type="text/html" id="aips-tmpl-broken-fix">
		<li>
			<span class="aips-badge {{action_class}}">{{action_label}}</span>
			<a href="{{source_edit}}">{{source_title}}</a>
			<span class="aips-text-muted">{{detail}}</span>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-broken-undo {{undo_class}}" data-fix-id="{{id}}"><?php esc_html_e('Undo', 'ai-post-scheduler'); ?></button>
			<span class="aips-text-muted {{undone_class}}"><?php esc_html_e('Undone', 'ai-post-scheduler'); ?></span>
		</li>
	</script>

	<script type="text/html" id="aips-tmpl-broken-pick-result">
		<li><button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-broken-pick" data-id="{{id}}" data-title="{{title}}">{{title}}</button></li>
	</script>
</div><!-- /.aips-link-report-tab -->
