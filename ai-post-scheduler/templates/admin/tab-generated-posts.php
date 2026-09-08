<?php
/**
 * Generated Posts Tab Template
 *
 * Tab 1 panel for the Content admin page.
 * Displays published/completed AI-generated posts using the standardized progressive disclosure table primitive.
 *
 * @var AIPS_Generated_Posts_Controller $controller
 * @var array $authors
 * @var array $templates
 * @var int $author_id
 * @var int $template_id
 * @var int $campaign_id
 * @var string $search_query
 * @var array $posts_data
 * @var array $history
 * @var int $current_page
 * @var array $selectable_post_types
 * @var string $post_type_filter
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$persist_filters_setting = (bool) AIPS_Config::get_instance()->get_option('aips_persist_table_filters', true);
?>
<!-- Filter Bar -->
<div class="aips-filter-bar">
	<form method="get" class="search-form aips-filter-form">
		<input type="hidden" name="page" value="aips-content">
		<input type="hidden" name="tab" value="generated_posts">
		<div class="aips-filter-left">
			<?php if (!empty($authors)): ?>
			<label class="screen-reader-text" for="aips-filter-author"><?php esc_html_e('Filter by Author:', 'ai-post-scheduler'); ?></label>
			<select name="author_id" id="aips-filter-author" class="aips-form-select">
				<option value=""><?php esc_html_e('All Authors', 'ai-post-scheduler'); ?></option>
				<?php foreach ($authors as $a): ?>
				<option value="<?php echo esc_attr($a->id); ?>" <?php selected($author_id, $a->id); ?>>
					<?php echo esc_html($a->name); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
			<?php if (!empty($templates)): ?>
			<label class="screen-reader-text" for="aips-filter-template-generated"><?php esc_html_e('Filter by Template:', 'ai-post-scheduler'); ?></label>
			<select name="template_id" id="aips-filter-template-generated" class="aips-form-select">
				<option value=""><?php esc_html_e('All Templates', 'ai-post-scheduler'); ?></option>
				<?php foreach ($templates as $template): ?>
				<option value="<?php echo esc_attr($template->id); ?>" <?php selected($template_id, $template->id); ?>>
					<?php echo esc_html($template->name); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
			<?php if (!empty($campaigns)): ?>
			<label class="screen-reader-text" for="aips-filter-campaign-generated"><?php esc_html_e('Filter by Campaign:', 'ai-post-scheduler'); ?></label>
			<select name="campaign_id" id="aips-filter-campaign-generated" class="aips-form-select">
				<option value=""><?php esc_html_e('All Campaigns', 'ai-post-scheduler'); ?></option>
				<?php foreach ($campaigns as $campaign): ?>
				<option value="<?php echo esc_attr($campaign->id); ?>" <?php selected($campaign_id, $campaign->id); ?>>
					<?php echo esc_html($campaign->name); ?><?php echo !empty($campaign->is_archived) ? esc_html__(' (Archived)', 'ai-post-scheduler') : ''; ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
			<?php if (!empty($selectable_post_types)): ?>
			<label class="screen-reader-text" for="aips-filter-post-type-generated"><?php esc_html_e('Filter by Post Type:', 'ai-post-scheduler'); ?></label>
			<select name="post_type" id="aips-filter-post-type-generated" class="aips-form-select">
				<option value=""><?php esc_html_e('All Post Types', 'ai-post-scheduler'); ?></option>
				<?php foreach ($selectable_post_types as $post_type_key => $post_type_info): ?>
				<option value="<?php echo esc_attr($post_type_key); ?>" <?php selected($post_type_filter, $post_type_key); ?>>
					<?php echo esc_html($post_type_info['label']); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
			<button type="submit" id="aips-filter-submit" class="aips-btn aips-btn-sm aips-btn-secondary">
				<span class="dashicons dashicons-filter" aria-hidden="true"></span>
				<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
			</button>
			<?php if (!empty($author_id) || !empty($template_id) || !empty($campaign_id) || !empty($post_type_filter)): ?>
			<a href="<?php echo esc_url(remove_query_arg(array('author_id', 'template_id', 'campaign_id', 'post_type', 'generated_paged'))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost aips-clear-filters" title="<?php esc_attr_e('Clear filters', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear filters', 'ai-post-scheduler'); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></a>
			<?php endif; ?>
		</div>
		<div class="aips-filter-right">
			<label class="screen-reader-text" for="post-search-input"><?php esc_html_e('Search Posts:', 'ai-post-scheduler'); ?></label>
			<input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search posts...', 'ai-post-scheduler'); ?>">
			<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
			</button>
			<?php if (!empty($search_query)): ?>
			<a href="<?php echo esc_url(remove_query_arg(array('s', 'generated_paged'))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost" title="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></a>
			<?php endif; ?>
		</div>
	</form>
</div>

<!-- Generated posts table via AIPS_Admin_UI_Primitives::render_table() -->
<div class="aips-panel-body no-padding">
	<?php
	$columns = array(
		'title'     => __('Title', 'ai-post-scheduler'),
		'type'      => __('Type', 'ai-post-scheduler'),
		'scheduled' => __('Scheduled', 'ai-post-scheduler'),
		'published' => __('Published', 'ai-post-scheduler'),
		'actions'   => __('Actions', 'ai-post-scheduler'),
	);

	$rows = array();
	if (!empty($posts_data)) {
		foreach ($posts_data as $post_data) {
			$post_type_obj = get_post_type_object($post_data['post_type']);
			$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post_data['post_type'];

			$title_cell = '<a href="' . esc_url($post_data['edit_link']) . '" class="cell-primary">' . esc_html($post_data['title']) . '</a>';
			if (!empty($post_data['source'])) {
				$title_cell .= '<span class="aips-cell-source">' . esc_html($post_data['source']) . '</span>';
			}

			$type_cell = '<span class="aips-badge aips-badge-neutral">' . esc_html($post_type_label) . '</span>';
			$scheduled_cell = '<div class="cell-meta">' . esc_html($post_data['date_scheduled']) . '</div>';
			$published_cell = '<div class="cell-meta">' . esc_html($post_data['date_published']) . '</div>';

			$history_url = AIPS_Admin_Menu_Helper::get_page_url('history', array_filter(array(
				'history_id' => !empty($post_data['history_id']) ? absint($post_data['history_id']) : 0,
				'post_id'    => !empty($post_data['post_id']) ? absint($post_data['post_id']) : 0,
			)));

			$primary_action = array(
				'label'      => __('Edit', 'ai-post-scheduler'),
				'icon'       => 'dashicons-edit',
				'class'      => 'aips-btn aips-btn-sm aips-btn-secondary aips-edit-post',
				'url'        => $post_data['edit_link'],
				'data_attrs' => array('edit-url' => $post_data['edit_link']),
			);

			$overflow_actions = array(
				array(
					'label'      => __('Preview', 'ai-post-scheduler'),
					'icon'       => 'dashicons-visibility',
					'class'      => 'aips-row-action-item aips-preview-post',
					'data_attrs' => array('post-id' => $post_data['post_id']),
				),
				array(
					'label'      => __('AI Edit', 'ai-post-scheduler'),
					'icon'       => 'dashicons-admin-customizer',
					'class'      => 'aips-row-action-item aips-ai-edit-btn',
					'data_attrs' => array(
						'post-id'    => $post_data['post_id'],
						'history-id' => $post_data['history_id'],
					),
				),
				array(
					'label'      => __('History', 'ai-post-scheduler'),
					'icon'       => 'dashicons-backup',
					'class'      => 'aips-row-action-item aips-open-history-modal',
					'url'        => $history_url,
					'data_attrs' => array(
						'history-id' => $post_data['history_id'],
						'post-id'    => $post_data['post_id'],
					),
				),
				array(
					'label'      => __('View Session', 'ai-post-scheduler'),
					'icon'       => 'dashicons-visibility',
					'class'      => 'aips-row-action-item aips-view-session',
					'data_attrs' => array('history-id' => $post_data['history_id']),
				),
			);

			$details_html = '<div class="aips-grid aips-grid-2">';
			$details_html .= '<div><strong>' . esc_html__('Generated Date:', 'ai-post-scheduler') . '</strong> ' . esc_html($post_data['date_generated']) . '</div>';
			$details_html .= '<div><strong>' . esc_html__('History Record ID:', 'ai-post-scheduler') . '</strong> #' . esc_html($post_data['history_id']) . '</div>';
			$details_html .= '</div>';

			$rows[] = array(
				'id'             => 'post-' . $post_data['post_id'],
				'checkbox_value' => $post_data['post_id'],
				'checkbox_label' => sprintf(__('Select post %s', 'ai-post-scheduler'), $post_data['title']),
				'cells'          => array(
					'title'     => $title_cell,
					'type'      => $type_cell,
					'scheduled' => $scheduled_cell,
					'published' => $published_cell,
				),
				'actions'        => array(
					'primary'  => $primary_action,
					'overflow' => $overflow_actions,
				),
				'details'        => $details_html,
			);
		}
	}

	$footer_count_text = sprintf(
		esc_html(_n('%s post', '%s posts', $history['total'], 'ai-post-scheduler')),
		number_format_i18n($history['total'])
	);

	$pagination_html = '';
	if ($history['pages'] > 1) {
		ob_start();
		$current = (int) $current_page;
		$pages   = (int) $history['pages'];
		$start   = max(1, $current - 3);
		$end     = min($pages, $current + 3);
		$base_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');
		$build_generated_posts_page_url = static function($page_number) use ($base_url, $author_id, $template_id, $campaign_id, $post_type_filter, $search_query) {
			return add_query_arg(array_filter(array(
				'generated_paged' => absint($page_number),
				'author_id'       => $author_id ? $author_id : false,
				'template_id'     => $template_id ? $template_id : false,
				'campaign_id'     => $campaign_id ? $campaign_id : false,
				'post_type'       => $post_type_filter ? $post_type_filter : false,
				's'               => $search_query ? $search_query : false,
			)), $base_url);
		};
		?>
		<div class="aips-history-pagination-links">
			<?php if ($current > 1) : ?>
				<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-prev" href="<?php echo esc_url($build_generated_posts_page_url($current - 1)); ?>" aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				</a>
			<?php else : ?>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-prev" disabled aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				</button>
			<?php endif; ?>

			<span class="aips-history-page-numbers">
				<?php if ($start > 1) : ?>
					<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_generated_posts_page_url(1)); ?>">1</a>
					<?php if ($start > 2) : ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
				<?php endif; ?>

				<?php for ($p = $start; $p <= $end; $p++) : ?>
					<?php if ($p === $current) : ?>
						<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page"><?php echo esc_html($p); ?></span>
					<?php else : ?>
						<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_generated_posts_page_url($p)); ?>"><?php echo esc_html($p); ?></a>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ($end < $pages) : ?>
					<?php if ($end < $pages - 1) : ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
					<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_generated_posts_page_url($pages)); ?>"><?php echo esc_html($pages); ?></a>
				<?php endif; ?>
			</span>

			<?php if ($current < $pages) : ?>
				<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-next" href="<?php echo esc_url($build_generated_posts_page_url($current + 1)); ?>" aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
			<?php else : ?>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-next" disabled aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</button>
			<?php endif; ?>
		</div>
		<?php
		$pagination_html = ob_get_clean();
	}

	$empty_state = array(
		'icon'       => !empty($search_query) ? 'dashicons-search' : 'dashicons-admin-post',
		'title'      => !empty($search_query) ? __('No Posts Found', 'ai-post-scheduler') : __('No Generated Posts', 'ai-post-scheduler'),
		'message'    => !empty($search_query) ? __('No generated posts match your search criteria. Try a different search term.', 'ai-post-scheduler') : __('No generated posts found. Start creating content by setting up templates and schedules.', 'ai-post-scheduler'),
		'cta_label'  => !empty($search_query) ? __('Clear Search', 'ai-post-scheduler') : __('Create Template', 'ai-post-scheduler'),
		'cta_url'    => !empty($search_query) ? remove_query_arg(array('s', 'generated_paged')) : AIPS_Admin_Menu_Helper::get_page_url('templates'),
		'cta_icon'   => !empty($search_query) ? 'dashicons-dismiss' : 'dashicons-plus-alt',
	);

	AIPS_Admin_UI_Primitives::render_table(array(
		'id'              => 'aips-generated-posts-table',
		'aria_label'      => __('Generated Posts Table', 'ai-post-scheduler'),
		'columns'         => $columns,
		'rows'            => $rows,
		'bulk_actions'    => array(
			'trash' => __('Move to Trash', 'ai-post-scheduler'),
		),
		'persist_filters' => $persist_filters_setting,
		'empty_state'     => $empty_state,
		'footer_count'    => $footer_count_text,
		'pagination'      => $pagination_html,
	));
	?>
</div>
