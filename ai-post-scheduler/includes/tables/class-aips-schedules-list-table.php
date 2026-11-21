<?php
/**
 * Schedules List Table Class
 *
 * Implements WordPress WP_List_Table for unified schedules management.
 *
 * Layout: Schedule | Timing | Status | Actions. Each row has an expandable
 * details row (Activity, Health, Recent output) that is loaded on demand via
 * the `aips_get_schedule_details` AJAX action.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Schedules_List_Table
 */
class AIPS_Schedules_List_Table extends AIPS_List_Table {

	/**
	 * Unified schedule service.
	 *
	 * @var AIPS_Unified_Schedule_Service
	 */
	protected $unified_service;

	/**
	 * Campaign map keyed by ID.
	 *
	 * @var array<int, object>
	 */
	protected $campaign_map = array();

	/**
	 * Total items count across types.
	 *
	 * @var array{all:int, template:int, blueprint:int}
	 */
	protected $counts = array(
		'all'       => 0,
		'template'  => 0,
		'blueprint' => 0,
	);

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Configuration arguments.
	 */
	public function __construct($args = array()) {
		parent::__construct(wp_parse_args($args, array(
			'singular'  => 'schedule',
			'plural'    => 'schedules',
			'page_slug' => 'aips-automations',
			'tab_slug'  => 'schedules',
		)));

		$this->unified_service = new AIPS_Unified_Schedule_Service();

		$campaign_options = AIPS_Campaigns_Repository::instance()->get_campaign_filter_options();
		$this->campaign_map = array();
		foreach ($campaign_options as $campaign_option) {
			$this->campaign_map[(int) $campaign_option->id] = $campaign_option;
		}
	}

	/**
	 * Define columns for the Schedules table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" id="aips-schedules-select-all" />',
			'title'   => esc_html__('Schedule', 'ai-post-scheduler'),
			'timing'  => esc_html__('Timing', 'ai-post-scheduler'),
			'status'  => esc_html__('Status', 'ai-post-scheduler'),
			'actions' => esc_html__('Actions', 'ai-post-scheduler'),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array<string, array{string, bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'title'  => array('title', false),
			'timing' => array('next_run', false),
			'status' => array('status', false),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'run_now' => __('Run Now', 'ai-post-scheduler'),
			'pause'   => __('Pause', 'ai-post-scheduler'),
			'resume'  => __('Resume', 'ai-post-scheduler'),
			'delete'  => __('Delete', 'ai-post-scheduler'),
		);
	}

	/**
	 * Render bulk actions only in the top toolbar; the bottom bar shows pagination only.
	 *
	 * @param string $which Either 'top' or 'bottom'.
	 * @return void
	 */
	protected function bulk_actions($which = '') {
		if ('top' !== $which) {
			return;
		}
		parent::bulk_actions($which);
	}

	/**
	 * Define type filter views.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current_view = $this->get_current_view();
		$base_url     = add_query_arg(array('page' => $this->page_slug, 'tab' => $this->tab_slug), admin_url('admin.php'));

		$views = array(
			'all'       => array(
				'label' => __('All Schedules', 'ai-post-scheduler'),
				'url'   => remove_query_arg('schedule_type', $base_url),
				'count' => $this->counts['all'],
			),
			'template'  => array(
				'label' => __('Post Generation', 'ai-post-scheduler'),
				'url'   => add_query_arg('schedule_type', AIPS_Unified_Schedule_Service::TYPE_TEMPLATE, $base_url),
				'count' => $this->counts['template'],
			),
			'blueprint' => array(
				'label' => __('Blueprints', 'ai-post-scheduler'),
				'url'   => add_query_arg('schedule_type', AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT, $base_url),
				'count' => $this->counts['blueprint'],
			),
		);

		$output = array();
		foreach ($views as $key => $view) {
			$is_current   = ($key === $current_view);
			$output[$key] = sprintf(
				'<a href="%s" class="aips-schedules-view%s"%s>%s <span class="count">%s</span></a>',
				esc_url($view['url']),
				$is_current ? ' current' : '',
				$is_current ? ' aria-current="page"' : '',
				esc_html($view['label']),
				esc_html(number_format_i18n($view['count']))
			);
		}

		return $output;
	}

	/**
	 * Render the view filters as a segmented control instead of WordPress's pipe-separated list.
	 *
	 * @return void
	 */
	public function views() {
		$views = $this->get_views();
		if (empty($views)) {
			return;
		}

		echo '<nav class="aips-schedules-views" aria-label="' . esc_attr__('Filter schedules by type', 'ai-post-scheduler') . '">';
		foreach ($views as $view) {
			echo $view; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_views().
		}
		echo '</nav>';
	}

	/**
	 * Resolve the active type view key (all|template|blueprint).
	 *
	 * @return string
	 */
	protected function get_current_view() {
		$type = isset($_GET['schedule_type']) ? sanitize_key(wp_unslash($_GET['schedule_type'])) : '';

		if (AIPS_Unified_Schedule_Service::TYPE_TEMPLATE === $type) {
			return 'template';
		}
		if (AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT === $type) {
			return 'blueprint';
		}
		return 'all';
	}

	/**
	 * Display the schedules table with a single toolbar (views + search) above it.
	 *
	 * @return void
	 */
	public function display_page() {
		?>
		<div class="aips-list-table-wrap aips-schedules-table-wrap" data-persist-filters="true" data-table-id="schedules">
			<form id="aips-schedules-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
				<input type="hidden" name="tab" value="<?php echo esc_attr($this->tab_slug); ?>" />
				<?php if ('all' !== $this->get_current_view()) : ?>
					<input type="hidden" name="schedule_type" value="<?php echo esc_attr(isset($_GET['schedule_type']) ? sanitize_key(wp_unslash($_GET['schedule_type'])) : ''); ?>" />
				<?php endif; ?>

				<div class="aips-schedules-toolbar">
					<?php
					$this->views();
					$this->search_box(__('Search', 'ai-post-scheduler'), 'aips-schedule-search');
					?>
				</div>

				<?php $this->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Table classes. No `striped`: hidden details rows sit between schedule rows,
	 * so nth-child striping would shade every schedule row the same.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		return array('widefat', 'fixed', $this->_args['plural'], 'aips-schedules-table');
	}

	/**
	 * Get default primary column.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * Prepare schedules data items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();

		$all_schedules = $this->unified_service->get_all_grouped();

		$this->counts['all'] = count($all_schedules);
		$template_count      = 0;
		$blueprint_count     = 0;

		foreach ($all_schedules as $sched) {
			if ($sched['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
				$template_count++;
			} elseif ($this->is_blueprint_type($sched['type'])) {
				$blueprint_count++;
			}
		}

		$this->counts['template']  = $template_count;
		$this->counts['blueprint'] = $blueprint_count;

		$current_view = $this->get_current_view();
		$search       = isset($_REQUEST['s']) ? strtolower(sanitize_text_field(wp_unslash($_REQUEST['s']))) : '';
		$filtered     = array();

		foreach ($all_schedules as $sched) {
			if ('template' === $current_view && $sched['type'] !== AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
				continue;
			}
			if ('blueprint' === $current_view && !$this->is_blueprint_type($sched['type'])) {
				continue;
			}

			if ('' !== $search) {
				$haystacks = array(
					strtolower($sched['title'] ?? ''),
					strtolower($sched['subtitle'] ?? ''),
					strtolower($sched['cron_hook'] ?? ''),
					strtolower($sched['topic'] ?? ''),
				);
				$matched = false;
				foreach ($haystacks as $haystack) {
					if (false !== strpos($haystack, $search)) {
						$matched = true;
						break;
					}
				}
				if (!$matched) {
					continue;
				}
			}

			$filtered[] = $sched;
		}

		// Without an explicit sort, keep the service's run-proximity order
		// (soonest active run first, paused/unscheduled last).
		$orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : '';
		$order   = isset($_GET['order']) && 'desc' === strtolower(sanitize_key(wp_unslash($_GET['order']))) ? 'desc' : 'asc';

		if ('' !== $orderby) {
			usort($filtered, function($a, $b) use ($orderby, $order) {
				if ('status' === $orderby) {
					$res = (int) !empty($b['is_active']) - (int) !empty($a['is_active']);
				} elseif ('next_run' === $orderby) {
					$a_ts = (!empty($a['is_active']) && !empty($a['next_run'])) ? (int) $a['next_run'] : PHP_INT_MAX;
					$b_ts = (!empty($b['is_active']) && !empty($b['next_run'])) ? (int) $b['next_run'] : PHP_INT_MAX;
					$res  = $a_ts <=> $b_ts;
				} else {
					$res = strcasecmp($a['title'] ?? '', $b['title'] ?? '');
				}

				if (0 === $res) {
					$res = strcasecmp($a['title'] ?? '', $b['title'] ?? '');
				}

				return ('desc' === $order) ? -$res : $res;
			});
		}

		$per_page     = $this->get_per_page(20);
		$current_page = $this->get_pagenum();
		$total_items  = count($filtered);

		$this->items = array_slice($filtered, ($current_page - 1) * $per_page, $per_page);

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil($total_items / $per_page),
		));
	}

	/**
	 * Render single row with unified schedule metadata attributes and a lazily loaded details row.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return void
	 */
	public function single_row($item) {
		$is_active            = !empty($item['is_active']);
		$circuit_state        = isset($item['circuit_state']) ? $item['circuit_state'] : 'closed';
		$has_incomplete_batch = !empty($item['has_incomplete_batch']);
		$details_id           = $this->get_details_id($item);

		$tab_category = 'all';
		if ($item['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
			$tab_category = 'content';
		} elseif ($this->is_blueprint_type($item['type'])) {
			$tab_category = 'author';
		}

		$attrs = array(
			'class'                     => 'aips-unified-row' . ($is_active ? '' : ' is-paused'),
			'data-id'                   => $item['id'],
			'data-type'                 => $item['type'],
			'data-row-key'              => $item['type'] . ':' . $item['id'],
			'data-tab-category'         => $tab_category,
			'data-can-delete'           => !empty($item['can_delete']) ? '1' : '0',
			'data-is-active'            => $is_active ? '1' : '0',
			'data-circuit-state'        => $circuit_state,
			'data-has-incomplete-batch' => $has_incomplete_batch ? '1' : '0',
			'data-title'                => $item['title'],
			'data-schedule-id'          => $item['id'],
			'data-template-id'          => $item['template_id'] ?? '',
			'data-frequency'            => $item['frequency'] ?? '',
			'data-topic'                => $item['topic'] ?? '',
			'data-article-structure-id' => $item['article_structure_id'] ?? '',
			'data-rotation-pattern'     => $item['rotation_pattern'] ?? '',
			'data-next-run'             => $item['next_run'] ?? '',
		);

		echo '<tr';
		foreach ($attrs as $name => $value) {
			echo ' ' . esc_attr($name) . '="' . esc_attr((string) $value) . '"';
		}
		echo '>';
		$this->single_row_columns($item);
		echo '</tr>';

		echo '<tr class="aips-row-details aips-schedule-details-row" id="' . esc_attr($details_id) . '" hidden>';
		echo '<td colspan="' . esc_attr((string) count($this->get_columns())) . '">';
		echo '<div class="aips-row-details-content">';
		echo $this->single_row_details($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in single_row_details().
		echo '</div>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Checkbox column rendering.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" class="aips-unified-checkbox" name="schedule_ids[]" value="%s" aria-label="%s" />',
			esc_attr($item['type'] . ':' . $item['id']),
			/* translators: %s: schedule name */
			esc_attr(sprintf(__('Select %s', 'ai-post-scheduler'), $item['title']))
		);
	}

	/**
	 * Schedule column: expand toggle, name, type + output count, subtitle and hover row actions.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_title($item) {
		$is_template = ($item['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE);
		$details_id  = $this->get_details_id($item);

		$html  = '<div class="aips-sched-title">';
		$html .= '<div class="aips-sched-name-row">';
		$html .= '<button type="button" class="aips-btn-icon aips-row-expand-toggle" aria-expanded="false" aria-controls="' . esc_attr($details_id) . '" aria-label="' . esc_attr(sprintf(/* translators: %s: schedule name */ __('Show details for %s', 'ai-post-scheduler'), $item['title'])) . '" title="' . esc_attr__('Expand details', 'ai-post-scheduler') . '"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>';
		$html .= '<strong class="aips-schedule-name">' . esc_html($item['title']) . '</strong>';

		if ($this->needs_attention($item)) {
			$html .= '<span class="aips-sched-alert dashicons dashicons-warning" title="' . esc_attr__('Needs attention — expand for health details', 'ai-post-scheduler') . '" aria-hidden="true"></span>';
			$html .= '<span class="screen-reader-text">' . esc_html__('Needs attention', 'ai-post-scheduler') . '</span>';
		}
		$html .= '</div>';

		$html .= '<div class="aips-sched-meta">';
		$html .= $this->render_type_chip($item['type']);
		$html .= '<span class="aips-sched-meta-sep" aria-hidden="true">·</span>';
		$html .= '<span class="aips-sched-count">' . esc_html($this->get_output_count_label($item)) . '</span>';

		if (!empty($item['campaign_id']) && isset($this->campaign_map[(int) $item['campaign_id']])) {
			$campaign     = $this->campaign_map[(int) $item['campaign_id']];
			$campaign_url = add_query_arg(array('page' => 'aips-generated-posts', 'campaign_id' => absint($item['campaign_id'])), admin_url('admin.php'));
			$html .= '<span class="aips-sched-meta-sep" aria-hidden="true">·</span>';
			$html .= '<a class="aips-sched-campaign" href="' . esc_url($campaign_url) . '">' . esc_html($campaign->name) . '</a>';
		}
		$html .= '</div>';

		if (!empty($item['subtitle'])) {
			$html .= '<div class="aips-sched-subtitle" title="' . esc_attr($item['subtitle']) . '">' . esc_html($item['subtitle']) . '</div>';
		}

		// WordPress native row actions (revealed on hover).
		$actions = array();
		$actions['run_now'] = sprintf(
			'<a href="#" class="aips-unified-run-now" data-id="%s" data-type="%s">%s</a>',
			esc_attr($item['id']),
			esc_attr($item['type']),
			esc_html__('Run Now', 'ai-post-scheduler')
		);

		if ($is_template) {
			$actions['edit'] = sprintf(
				'<a href="#" class="aips-edit-schedule" %s>%s</a>',
				$this->get_edit_data_attributes($item),
				esc_html__('Edit', 'ai-post-scheduler')
			);
		}

		$actions['history'] = sprintf(
			'<a href="#" class="aips-view-unified-history" data-id="%s" data-type="%s" data-name="%s" data-limit="5">%s</a>',
			esc_attr($item['id']),
			esc_attr($item['type']),
			esc_attr($item['title']),
			esc_html__('History', 'ai-post-scheduler')
		);

		if (!empty($item['can_delete'])) {
			$actions['delete'] = sprintf(
				'<a href="#" class="aips-delete-schedule aips-link-danger" data-id="%s">%s</a>',
				esc_attr($item['id']),
				esc_html__('Delete', 'ai-post-scheduler')
			);
		}

		$html .= $this->row_actions($actions);
		$html .= '</div>';

		return $html;
	}

	/**
	 * Timing column: frequency chip plus a compact Next / Last grid.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_timing($item) {
		$next_run_dt = $this->parse_datetime($item['next_run'] ?? null);
		$last_run_dt = $this->parse_datetime($item['last_run'] ?? null);
		$is_active   = !empty($item['is_active']);

		$html  = '<div class="aips-sched-timing">';
		$html .= $this->render_frequency_chip($item);
		$html .= '<dl class="aips-sched-times">';

		// Next run.
		$html .= '<dt>' . esc_html__('Next', 'ai-post-scheduler') . '</dt>';
		if (!$is_active) {
			$html .= '<dd class="aips-sched-time-muted">' . esc_html__('Paused', 'ai-post-scheduler') . '</dd>';
		} elseif ($next_run_dt) {
			$next_ts = $next_run_dt->timestamp();
			$html   .= '<dd title="' . esc_attr($next_run_dt->toDisplay()) . '">';
			if ($next_ts <= time()) {
				$html .= '<span class="aips-sched-due">' . esc_html__('Due now', 'ai-post-scheduler') . '</span>';
			} else {
				$html .= '<span class="aips-sched-rel">' . esc_html($this->format_relative_short($next_ts)) . '</span>';
			}
			$html .= '<span class="aips-sched-abs">' . esc_html($this->format_short_datetime($next_run_dt)) . '</span>';
			$html .= '</dd>';
		} else {
			$html .= '<dd class="aips-sched-time-muted">—</dd>';
		}

		// Last run.
		$html .= '<dt>' . esc_html__('Last', 'ai-post-scheduler') . '</dt>';
		if ($last_run_dt) {
			$html .= '<dd title="' . esc_attr($last_run_dt->toDisplay()) . '"><span class="aips-sched-abs aips-sched-abs-plain">' . esc_html($this->format_short_datetime($last_run_dt)) . '</span></dd>';
		} else {
			$html .= '<dd class="aips-sched-time-muted">' . esc_html__('Never', 'ai-post-scheduler') . '</dd>';
		}

		$html .= '</dl>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Status column: Enabled / Paused switch.
	 *
	 * The checkbox keeps the `aips-unified-toggle-schedule` class so the existing
	 * `aips_unified_toggle` AJAX handler continues to drive it.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_status($item) {
		$is_active = !empty($item['is_active']);

		$html  = '<label class="aips-status-switch">';
		$html .= '<input type="checkbox" role="switch" class="aips-unified-toggle-schedule" data-id="' . esc_attr($item['id']) . '" data-type="' . esc_attr($item['type']) . '" aria-label="' . esc_attr(sprintf(/* translators: %s: schedule name */ __('Enable schedule %s', 'ai-post-scheduler'), $item['title'])) . '" ' . checked($is_active, true, false) . '>';
		$html .= '<span class="aips-status-switch-track" aria-hidden="true">';
		$html .= '<span class="aips-status-switch-text aips-status-switch-on">' . esc_html__('Enabled', 'ai-post-scheduler') . '</span>';
		$html .= '<span class="aips-status-switch-text aips-status-switch-off">' . esc_html__('Paused', 'ai-post-scheduler') . '</span>';
		$html .= '<span class="aips-status-switch-knob"></span>';
		$html .= '</span>';
		$html .= '</label>';

		return $html;
	}

	/**
	 * Action buttons column rendering.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_actions($item) {
		$id                   = esc_attr($item['id']);
		$type                 = esc_attr($item['type']);
		$is_template          = ($item['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE);
		$circuit_state        = isset($item['circuit_state']) ? $item['circuit_state'] : 'closed';
		$has_incomplete_batch = !empty($item['has_incomplete_batch']);
		$can_delete           = !empty($item['can_delete']);
		$menu_id              = 'aips-schedule-actions-' . sanitize_key($item['type'] . '-' . $item['id']);

		$html  = '<div class="cell-actions aips-sched-actions">';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-unified-run-now" data-id="' . $id . '" data-type="' . $type . '"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> ' . esc_html__('Run Now', 'ai-post-scheduler') . '</button>';

		$html .= '<div class="aips-row-action-group">';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($menu_id) . '" title="' . esc_attr__('More actions', 'ai-post-scheduler') . '"><span class="dashicons dashicons-ellipsis" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('More actions', 'ai-post-scheduler') . '</span></button>';
		$html .= '<div id="' . esc_attr($menu_id) . '" class="aips-row-action-menu" hidden>';

		if ($is_template) {
			$html .= '<button type="button" class="aips-row-action-item aips-edit-schedule" ' . $this->get_edit_data_attributes($item) . '><span class="dashicons dashicons-edit" aria-hidden="true"></span> ' . esc_html__('Edit Schedule', 'ai-post-scheduler') . '</button>';
		}

		$html .= '<button type="button" class="aips-row-action-item aips-view-unified-history" data-id="' . $id . '" data-type="' . $type . '" data-name="' . esc_attr($item['title']) . '" data-limit="5"><span class="dashicons dashicons-backup" aria-hidden="true"></span> ' . esc_html__('View History', 'ai-post-scheduler') . '</button>';

		if ($is_template && 'open' === $circuit_state) {
			$html .= '<button type="button" class="aips-row-action-item aips-reset-circuit aips-text-warning" data-id="' . $id . '" data-type="' . $type . '"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html__('Reset Circuit & Retry', 'ai-post-scheduler') . '</button>';
		}

		if ($is_template && $has_incomplete_batch) {
			$html .= '<button type="button" class="aips-row-action-item aips-resume-batch" data-id="' . $id . '" data-type="' . $type . '"><span class="dashicons dashicons-controls-forward" aria-hidden="true"></span> ' . esc_html__('Resume Batch', 'ai-post-scheduler') . '</button>';
		}

		if ($can_delete) {
			$html .= '<button type="button" class="aips-row-action-item aips-delete-schedule aips-text-danger" data-id="' . $id . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span> ' . esc_html__('Delete Schedule', 'ai-post-scheduler') . '</button>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Details row placeholder. Content (Activity, Health, Recent output) is
	 * fetched on first expand by assets/js/admin-schedules-table.js.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string HTML placeholder.
	 */
	public function single_row_details($item) {
		$html  = '<div class="aips-sched-details" data-id="' . esc_attr($item['id']) . '" data-type="' . esc_attr($item['type']) . '" data-loaded="0" aria-live="polite">';
		$html .= '<div class="aips-sched-details-loading"><span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span> ' . esc_html__('Loading details…', 'ai-post-scheduler') . '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Fallback column default.
	 *
	 * @param array<string, mixed> $item        Schedule item data.
	 * @param string               $column_name Column key.
	 * @return string
	 */
	protected function column_default($item, $column_name) {
		return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Whether a schedule type is a blueprint (persona pipeline) row.
	 *
	 * @param string $type Schedule type.
	 * @return bool
	 */
	protected function is_blueprint_type($type) {
		return AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT === $type || AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW === $type;
	}

	/**
	 * DOM id of a row's details row.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function get_details_id($item) {
		return 'aips-details-' . sanitize_key($item['type'] . '-' . $item['id']);
	}

	/**
	 * Whether the row should show a "needs attention" marker next to its name.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return bool
	 */
	protected function needs_attention($item) {
		$circuit_state = isset($item['circuit_state']) ? $item['circuit_state'] : 'closed';
		$status        = isset($item['status']) ? $item['status'] : '';

		return in_array($circuit_state, array('open', 'half_open'), true) || 'failed' === $status;
	}

	/**
	 * Escaped data-* attributes used by the template-schedule edit triggers.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function get_edit_data_attributes($item) {
		$attrs = array(
			'data-schedule-id'          => $item['id'],
			'data-template-id'          => $item['template_id'] ?? '',
			'data-title'                => $item['title'],
			'data-frequency'            => $item['frequency'] ?? '',
			'data-topic'                => $item['topic'] ?? '',
			'data-article-structure-id' => $item['article_structure_id'] ?? '',
			'data-rotation-pattern'     => $item['rotation_pattern'] ?? '',
			'data-next-run'             => $item['next_run'] ?? '',
			'data-is-active'            => !empty($item['is_active']) ? '1' : '0',
		);

		$parts = array();
		foreach ($attrs as $name => $value) {
			$parts[] = esc_attr($name) . '="' . esc_attr((string) $value) . '"';
		}

		return implode(' ', $parts);
	}

	/**
	 * Render the small pipeline-type chip shown under the schedule name.
	 *
	 * @param string $type Schedule type identifier.
	 * @return string HTML chip.
	 */
	protected function render_type_chip($type) {
		switch ($type) {
			case AIPS_Unified_Schedule_Service::TYPE_TEMPLATE:
				$label    = __('Post Generation', 'ai-post-scheduler');
				$modifier = 'template';
				break;
			case AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC:
				$label    = __('Author Topics', 'ai-post-scheduler');
				$modifier = 'author';
				break;
			case AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST:
				$label    = __('Author Posts', 'ai-post-scheduler');
				$modifier = 'author';
				break;
			case AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT:
				$label    = __('Blueprint', 'ai-post-scheduler');
				$modifier = 'blueprint';
				break;
			default:
				return '';
		}

		return '<span class="aips-sched-type aips-sched-type--' . esc_attr($modifier) . '">' . esc_html($label) . '</span>';
	}

	/**
	 * Short output count shown next to the type chip (e.g. "37 posts").
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string Unescaped label.
	 */
	protected function get_output_count_label($item) {
		$count = isset($item['stats_count']) ? (int) $item['stats_count'] : 0;

		if (AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC === $item['type']) {
			/* translators: %s: number of topics */
			return sprintf(_n('%s topic', '%s topics', $count, 'ai-post-scheduler'), number_format_i18n($count));
		}

		/* translators: %s: number of posts */
		return sprintf(_n('%s post', '%s posts', $count, 'ai-post-scheduler'), number_format_i18n($count));
	}

	/**
	 * Frequency chip for the Timing column. Blueprints whose stages run on
	 * different cadences show "Mixed" with the per-stage cadence in a tooltip.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string HTML chip.
	 */
	protected function render_frequency_chip($item) {
		$title = '';

		if (!empty($item['mixed_frequency'])) {
			$label  = __('Mixed', 'ai-post-scheduler');
			$stages = isset($item['stages']) && is_array($item['stages']) ? $item['stages'] : array();
			$parts  = array();
			foreach ($stages as $stage) {
				$parts[] = $stage['label'] . ': ' . $this->get_frequency_label($stage['frequency'] ?? '');
			}
			$title = implode(' · ', $parts);
		} elseif (!empty($item['frequency'])) {
			$label = $this->get_frequency_label($item['frequency']);
		} else {
			return '';
		}

		$html  = '<span class="aips-sched-freq"' . ('' !== $title ? ' title="' . esc_attr($title) . '"' : '') . '>';
		$html .= '<span class="dashicons dashicons-update" aria-hidden="true"></span>';
		$html .= esc_html($label);
		$html .= '</span>';

		return $html;
	}

	/**
	 * Get frequency label.
	 *
	 * @param string $frequency Frequency key.
	 * @return string
	 */
	protected function get_frequency_label($frequency) {
		if (empty($frequency)) {
			return __('—', 'ai-post-scheduler');
		}
		$schedules = wp_get_schedules();
		if (isset($schedules[$frequency])) {
			return $schedules[$frequency]['display'];
		}
		return ucfirst(str_replace('_', ' ', $frequency));
	}

	/**
	 * Compact relative time until a future timestamp, e.g. "in 22h 52m".
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	protected function format_relative_short($timestamp) {
		$diff = (int) $timestamp - time();
		if ($diff < MINUTE_IN_SECONDS) {
			return __('in under a minute', 'ai-post-scheduler');
		}

		$days    = (int) floor($diff / DAY_IN_SECONDS);
		$hours   = (int) floor(($diff % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
		$minutes = (int) floor(($diff % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);

		$parts = array();
		if ($days > 0) {
			/* translators: %d: number of days (abbreviated) */
			$parts[] = sprintf(_x('%dd', 'abbreviated days', 'ai-post-scheduler'), $days);
			if ($hours > 0) {
				/* translators: %d: number of hours (abbreviated) */
				$parts[] = sprintf(_x('%dh', 'abbreviated hours', 'ai-post-scheduler'), $hours);
			}
		} elseif ($hours > 0) {
			/* translators: %d: number of hours (abbreviated) */
			$parts[] = sprintf(_x('%dh', 'abbreviated hours', 'ai-post-scheduler'), $hours);
			if ($minutes > 0) {
				/* translators: %d: number of minutes (abbreviated) */
				$parts[] = sprintf(_x('%dm', 'abbreviated minutes', 'ai-post-scheduler'), $minutes);
			}
		} else {
			/* translators: %d: number of minutes (abbreviated) */
			$parts[] = sprintf(_x('%dm', 'abbreviated minutes', 'ai-post-scheduler'), $minutes);
		}

		/* translators: %s: compact duration, e.g. "22h 52m" */
		return sprintf(__('in %s', 'ai-post-scheduler'), implode(' ', $parts));
	}

	/**
	 * Short absolute date/time in the site timezone, e.g. "Nov 21, 10:10 PM".
	 * The year is included only when it differs from the current year.
	 *
	 * @param AIPS_DateTime $date_time Date to format.
	 * @return string
	 */
	protected function format_short_datetime(AIPS_DateTime $date_time) {
		$same_year = $date_time->toDisplay('Y') === AIPS_DateTime::now()->toDisplay('Y');
		$date_fmt  = $same_year
			? _x('M j', 'short date format without year', 'ai-post-scheduler')
			: _x('M j, Y', 'short date format with year', 'ai-post-scheduler');

		return $date_time->toDisplay($date_fmt . ', ' . get_option('time_format', 'g:i a'));
	}

	/**
	 * Normalize DB date/time value to an AIPS_DateTime instance.
	 *
	 * @param mixed $value Timestamp, string or null.
	 * @return AIPS_DateTime|null
	 */
	protected function parse_datetime($value) {
		if (empty($value) || '0000-00-00 00:00:00' === $value) {
			return null;
		}

		if (is_numeric($value)) {
			$timestamp = (int) $value;
			if ($timestamp > 0 && $timestamp < AIPS_Date_Time_DB_Repair::MIN_VALID_TIMESTAMP) {
				return null;
			}
			return AIPS_DateTime::fromTimestampOrNull($timestamp);
		}

		return AIPS_DateTime::fromMysqlOrNull((string) $value);
	}
}
