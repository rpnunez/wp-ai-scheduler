<?php
/**
 * Schedules List Table Class
 *
 * Implements WordPress WP_List_Table for unified schedules management.
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
	 * Date and time format string.
	 *
	 * @var string
	 */
	protected $date_format = '';

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

		$this->date_format = get_option('date_format') . ' ' . get_option('time_format');
	}

	/**
	 * Define columns for the Schedules table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" id="aips-schedules-select-all" />',
			'title'   => esc_html__('Schedule Name', 'ai-post-scheduler'),
			'type'    => esc_html__('Pipeline & Frequency', 'ai-post-scheduler'),
			'timing'  => esc_html__('Timing', 'ai-post-scheduler'),
			'stats'   => esc_html__('Activity', 'ai-post-scheduler'),
			'status'  => esc_html__('Status & Health', 'ai-post-scheduler'),
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
			'type'   => array('type', false),
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
	 * Define status/type filter views.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current_view = isset($_GET['schedule_type']) ? sanitize_key(wp_unslash($_GET['schedule_type'])) : 'all';
		$base_url     = add_query_arg(array('page' => $this->page_slug, 'tab' => $this->tab_slug), admin_url('admin.php'));

		$views = array();

		// All view
		$all_class = ('all' === $current_view || empty($current_view)) ? 'current' : '';
		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(remove_query_arg('schedule_type', $base_url)),
			$all_class,
			esc_html__('All Schedules', 'ai-post-scheduler'),
			$this->counts['all']
		);

		// Post Generation view
		$template_class = (AIPS_Unified_Schedule_Service::TYPE_TEMPLATE === $current_view) ? 'current' : '';
		$views['template'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('schedule_type', AIPS_Unified_Schedule_Service::TYPE_TEMPLATE, $base_url)),
			$template_class,
			esc_html__('Post Generation', 'ai-post-scheduler'),
			$this->counts['template']
		);

		// Blueprints view
		$blueprint_class = (AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT === $current_view || AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW === $current_view) ? 'current' : '';
		$views['blueprint'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('schedule_type', AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT, $base_url)),
			$blueprint_class,
			esc_html__('Blueprints', 'ai-post-scheduler'),
			$this->counts['blueprint']
		);

		return $views;
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

		// Count types
		$this->counts['all'] = count($all_schedules);
		$template_count      = 0;
		$blueprint_count     = 0;

		foreach ($all_schedules as $sched) {
			if ($sched['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
				$template_count++;
			} elseif ($sched['type'] === AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT || $sched['type'] === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW) {
				$blueprint_count++;
			}
		}

		$this->counts['template']  = $template_count;
		$this->counts['blueprint'] = $blueprint_count;

		// Filter by schedule_type if requested
		$type_filter = isset($_GET['schedule_type']) ? sanitize_key(wp_unslash($_GET['schedule_type'])) : 'all';
		$filtered    = array();

		foreach ($all_schedules as $sched) {
			if ('all' !== $type_filter && !empty($type_filter)) {
				if ($type_filter === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE && $sched['type'] !== AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
					continue;
				}
				if ($type_filter === AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT && $sched['type'] !== AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT && $sched['type'] !== AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW) {
					continue;
				}
			}

			// Filter by search query if present
			$search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
			if (!empty($search)) {
				$term       = strtolower($search);
				$title      = strtolower($sched['title'] ?? '');
				$subtitle   = strtolower($sched['subtitle'] ?? '');
				$cron_hook  = strtolower($sched['cron_hook'] ?? '');
				$topic      = strtolower($sched['topic'] ?? '');

				if (
					strpos($title, $term) === false &&
					strpos($subtitle, $term) === false &&
					strpos($cron_hook, $term) === false &&
					strpos($topic, $term) === false
				) {
					continue;
				}
			}

			$filtered[] = $sched;
		}

		// Sorting
		$orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'title';
		$order   = isset($_GET['order']) && 'desc' === strtolower(sanitize_key(wp_unslash($_GET['order']))) ? 'desc' : 'asc';

		usort($filtered, function($a, $b) use ($orderby, $order) {
			if ('status' === $orderby) {
				$a_status = (!empty($a['is_active'])) ? 'active' : 'inactive';
				$b_status = (!empty($b['is_active'])) ? 'active' : 'inactive';
				$res      = strcmp($a_status, $b_status);
			} elseif ('type' === $orderby) {
				$res = strcmp($a['type'] ?? '', $b['type'] ?? '');
			} else {
				$res = strcasecmp($a['title'] ?? '', $b['title'] ?? '');
			}
			return ('desc' === $order) ? -$res : $res;
		});

		// Dynamic Pagination via Screen Options
		$per_page     = $this->get_per_page(20);
		$current_page = $this->get_pagenum();
		$total_items  = count($filtered);

		$this->items = array_slice($filtered, ($current_page - 1) * $per_page, $per_page);

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items / $per_page),
		));
	}

	/**
	 * Render single row with unified schedule metadata attributes and progressive disclosure drawer.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return void
	 */
	public function single_row($item) {
		$is_active            = !empty($item['is_active']);
		$circuit_state        = isset($item['circuit_state']) ? $item['circuit_state'] : 'closed';
		$has_incomplete_batch = !empty($item['has_incomplete_batch']);
		$row_key              = esc_attr($item['type'] . ':' . $item['id']);
		$details_id           = 'aips-details-' . sanitize_key($item['type'] . '-' . $item['id']);

		$tab_category = 'all';
		if ($item['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
			$tab_category = 'content';
		} elseif ($item['type'] === AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT || $item['type'] === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW) {
			$tab_category = 'author';
		}

		echo '<tr class="aips-unified-row" ';
		echo 'data-id="' . esc_attr($item['id']) . '" ';
		echo 'data-type="' . esc_attr($item['type']) . '" ';
		echo 'data-row-key="' . $row_key . '" ';
		echo 'data-tab-category="' . esc_attr($tab_category) . '" ';
		echo 'data-can-delete="' . esc_attr(!empty($item['can_delete']) ? '1' : '0') . '" ';
		echo 'data-is-active="' . esc_attr($is_active ? '1' : '0') . '" ';
		echo 'data-circuit-state="' . esc_attr($circuit_state) . '" ';
		echo 'data-has-incomplete-batch="' . esc_attr($has_incomplete_batch ? '1' : '0') . '" ';
		echo 'data-title="' . esc_attr($item['title']) . '" ';
		echo 'data-schedule-id="' . esc_attr($item['id']) . '" ';
		echo 'data-template-id="' . esc_attr($item['template_id'] ?? '') . '" ';
		echo 'data-frequency="' . esc_attr($item['frequency'] ?? '') . '" ';
		echo 'data-topic="' . esc_attr($item['topic'] ?? '') . '" ';
		echo 'data-article-structure-id="' . esc_attr($item['article_structure_id'] ?? '') . '" ';
		echo 'data-rotation-pattern="' . esc_attr($item['rotation_pattern'] ?? '') . '" ';
		echo 'data-next-run="' . esc_attr($item['next_run'] ?? '') . '">';
		$this->single_row_columns($item);
		echo '</tr>';

		$details = $this->single_row_details($item);
		if (!empty($details)) {
			$total_cols = count($this->get_columns());
			echo '<tr class="aips-row-details" id="' . esc_attr($details_id) . '" hidden>';
			echo '<td colspan="' . esc_attr($total_cols) . '">';
			echo '<div class="aips-row-details-content">';
			echo $details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			echo '</td>';
			echo '</tr>';
		}
	}

	/**
	 * Checkbox column rendering.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_cb($item) {
		$row_key = esc_attr($item['type'] . ':' . $item['id']);
		return sprintf(
			'<input type="checkbox" class="aips-unified-checkbox" name="schedule_ids[]" value="%s" aria-label="%s" />',
			$row_key,
			esc_attr(sprintf(__('Select %s', 'ai-post-scheduler'), $item['title']))
		);
	}

	/**
	 * Schedule name column rendering with meta and WordPress row actions.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_title($item) {
		$is_template = ($item['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE);
		$details_id  = 'aips-details-' . sanitize_key($item['type'] . '-' . $item['id']);

		$html  = '<div class="cell-primary aips-schedule-title-cell">';
		$html .= '<div class="aips-schedule-header" style="display:flex;align-items:center;gap:6px;">';
		$html .= '<button type="button" class="aips-btn-icon aips-row-expand-toggle" aria-expanded="false" aria-controls="' . esc_attr($details_id) . '" aria-label="' . esc_attr__('Toggle schedule details', 'ai-post-scheduler') . '" title="' . esc_attr__('Expand details', 'ai-post-scheduler') . '"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>';
		$html .= '<strong class="aips-schedule-name">' . esc_html($item['title']) . '</strong>';
		$html .= '</div>';

		if (!empty($item['subtitle'])) {
			$html .= '<div class="cell-meta aips-muted">' . esc_html($item['subtitle']) . '</div>';
		}

		if (!empty($item['campaign_id']) && isset($this->campaign_map[(int) $item['campaign_id']])) {
			$campaign     = $this->campaign_map[(int) $item['campaign_id']];
			$campaign_url = add_query_arg(array('page' => 'aips-generated-posts', 'campaign_id' => absint($item['campaign_id'])), admin_url('admin.php'));
			$html .= '<div class="cell-meta" style="margin-top:4px;">';
			$html .= '<a class="aips-badge aips-badge-info" href="' . esc_url($campaign_url) . '">' . esc_html($campaign->name) . '</a>';
			$html .= '</div>';
		}

		// WordPress native row actions (revealed on hover)
		$actions = array();
		$actions['run_now'] = sprintf(
			'<a href="#" class="aips-unified-run-now" data-id="%s" data-type="%s">%s</a>',
			esc_attr($item['id']),
			esc_attr($item['type']),
			esc_html__('Run Now', 'ai-post-scheduler')
		);

		if ($is_template) {
			$actions['edit'] = sprintf(
				'<a href="#" class="aips-edit-schedule" data-schedule-id="%s" data-template-id="%s" data-title="%s" data-frequency="%s" data-topic="%s" data-article-structure-id="%s" data-rotation-pattern="%s" data-next-run="%s" data-is-active="%s">%s</a>',
				esc_attr($item['id']),
				esc_attr($item['template_id'] ?? ''),
				esc_attr($item['title']),
				esc_attr($item['frequency'] ?? ''),
				esc_attr($item['topic'] ?? ''),
				esc_attr($item['article_structure_id'] ?? ''),
				esc_attr($item['rotation_pattern'] ?? ''),
				esc_attr($item['next_run'] ?? ''),
				esc_attr(!empty($item['is_active']) ? '1' : '0'),
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
	 * Pipeline & frequency column rendering.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_type($item) {
		$html  = '<div class="aips-pipeline-cell">';
		$html .= '<div class="aips-pipeline-header" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">';
		$html .= $this->render_type_badge($item['type']);

		if (!empty($item['mixed_frequency'])) {
			$html .= '<span class="aips-badge aips-badge-neutral">' . esc_html__('Mixed', 'ai-post-scheduler') . '</span>';
		} elseif (!empty($item['frequency'])) {
			$html .= '<span class="aips-badge aips-badge-info">' . esc_html($this->get_frequency_label($item['frequency'])) . '</span>';
		}
		$html .= '</div>';

		if (!empty($item['cron_hook'])) {
			$html .= '<div class="cell-meta aips-muted" style="font-size:11px;">' . esc_html($item['cron_hook']) . '</div>';
		}

		$stages = isset($item['stages']) && is_array($item['stages']) ? $item['stages'] : array();
		if (!empty($stages)) {
			$html .= '<ul class="aips-stage-list" style="margin-top:6px;">';
			foreach ($stages as $stage) {
				$is_paused = empty($stage['is_active']) ? ' is-paused' : '';
				$html .= '<li class="aips-stage-item' . $is_paused . '">';
				$html .= '<span class="aips-stage-label">' . esc_html($stage['label']) . '</span>';
				$html .= '<span class="aips-stage-meta">' . esc_html($this->get_frequency_label($stage['frequency'])) . '</span>';
				$html .= '<span class="aips-stage-meta">' . esc_html(sprintf('%s %s', number_format_i18n($stage['stats_count']), $stage['stats_label'])) . '</span>';
				if (empty($stage['is_active'])) {
					$html .= '<span class="aips-stage-meta">' . esc_html__('Paused', 'ai-post-scheduler') . '</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Timing column rendering (Next Run & Last Run).
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_timing($item) {
		$next_run_dt = $this->parse_datetime($item['next_run'] ?? null);
		$last_run_dt = $this->parse_datetime($item['last_run'] ?? null);
		$next_run_ts = $next_run_dt ? $next_run_dt->timestamp() : 0;
		$last_run_ts = $last_run_dt ? $last_run_dt->timestamp() : 0;
		$is_active   = !empty($item['is_active']);

		$html  = '<div class="aips-timing-cell">';

		// Next Run
		$html .= '<div class="aips-timing-next" style="margin-bottom:6px;">';
		$html .= '<div class="cell-meta" style="font-weight:600;font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:0.5px;">' . esc_html__('Next Run', 'ai-post-scheduler') . '</div>';
		if (!$is_active) {
			$html .= '<div class="cell-meta aips-muted">' . esc_html__('Paused (N/A)', 'ai-post-scheduler') . '</div>';
		} elseif ($next_run_ts) {
			$html .= '<div class="cell-primary aips-next-run-countdown" title="' . esc_attr(date_i18n($this->date_format, $next_run_ts)) . '">';
			$html .= esc_html($this->format_relative_time($next_run_ts));
			$html .= '</div>';
			$html .= '<div class="cell-meta aips-muted" style="font-size:11px;">' . esc_html(date_i18n($this->date_format, $next_run_ts)) . '</div>';
			if ($next_run_ts < time()) {
				$html .= '<div class="cell-meta" style="color:var(--aips-warning);font-size:11px;">' . esc_html__('Due — runs on next cron trigger', 'ai-post-scheduler') . '</div>';
			}
		} else {
			$html .= '<div class="cell-meta aips-muted">—</div>';
		}
		$html .= '</div>';

		// Last Run
		$html .= '<div class="aips-timing-last">';
		$html .= '<div class="cell-meta" style="font-weight:600;font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:0.5px;">' . esc_html__('Last Run', 'ai-post-scheduler') . '</div>';
		if ($last_run_ts) {
			$html .= '<div class="cell-meta">' . esc_html(date_i18n($this->date_format, $last_run_ts)) . '</div>';
			$html .= '<div class="cell-meta aips-muted" style="font-size:11px;">' . esc_html($this->get_run_output_label($item['type'])) . '</div>';
		} else {
			$html .= '<div class="cell-meta aips-muted">' . esc_html__('Never', 'ai-post-scheduler') . '</div>';
		}
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Stats column rendering.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_stats($item) {
		$stats_count = isset($item['stats_count']) ? (int) $item['stats_count'] : 0;
		$stats_label = !empty($item['stats_label']) ? $item['stats_label'] : __('Posts Generated', 'ai-post-scheduler');

		$html  = '<div class="cell-primary">';
		$html .= '<span class="aips-badge aips-badge-neutral" title="' . esc_attr($stats_label) . '">';
		$html .= '<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> ';
		$html .= sprintf(esc_html__('%s %s', 'ai-post-scheduler'), number_format_i18n($stats_count), esc_html($stats_label));
		$html .= '</span>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Status and health column rendering.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string
	 */
	protected function column_status($item) {
		$circuit_state = isset($item['circuit_state']) ? $item['circuit_state'] : 'closed';
		$status        = isset($item['status']) ? $item['status'] : 'inactive';
		$is_active     = !empty($item['is_active']);

		// Circuit health
		switch ($circuit_state) {
			case 'open':
				$health_badge_cls = 'aips-badge-error';
				$health_icon_cls  = 'dashicons-dismiss';
				$health_label     = __('Circuit Open', 'ai-post-scheduler');
				break;
			case 'half_open':
				$health_badge_cls = 'aips-badge-warning';
				$health_icon_cls  = 'dashicons-warning';
				$health_label     = __('Recovering', 'ai-post-scheduler');
				break;
			default:
				$health_badge_cls = 'aips-badge-success';
				$health_icon_cls  = 'dashicons-yes';
				$health_label     = __('Healthy', 'ai-post-scheduler');
		}

		// Schedule status badge
		switch ($status) {
			case 'failed':
				$badge_cls  = 'aips-badge-error';
				$icon_cls   = 'dashicons-warning';
				$status_lbl = __('Failed', 'ai-post-scheduler');
				break;
			case 'inactive':
				$badge_cls  = 'aips-badge-neutral';
				$icon_cls   = 'dashicons-minus';
				$status_lbl = __('Paused', 'ai-post-scheduler');
				break;
			default:
				$badge_cls  = 'aips-badge-info';
				$icon_cls   = 'dashicons-yes-alt';
				$status_lbl = __('Active', 'ai-post-scheduler');
		}

		$html  = '<div class="aips-schedule-status-wrapper" style="display:flex;flex-direction:column;gap:6px;">';
		$html .= '<div style="display:flex;align-items:center;gap:8px;">';
		$html .= '<span class="aips-badge ' . esc_attr($health_badge_cls) . '" title="' . esc_attr__('Circuit Breaker Status', 'ai-post-scheduler') . '">';
		$html .= '<span class="dashicons ' . esc_attr($health_icon_cls) . '"></span> ';
		$html .= esc_html($health_label);
		$html .= '</span>';
		$html .= '</div>';
		$html .= '<div style="display:flex;align-items:center;gap:8px;">';
		$html .= '<span class="aips-badge ' . esc_attr($badge_cls) . '">';
		$html .= '<span class="dashicons ' . esc_attr($icon_cls) . '"></span> ';
		$html .= esc_html($status_lbl);
		$html .= '</span>';
		$html .= '<label class="aips-toggle">';
		$html .= '<input type="checkbox" class="aips-unified-toggle-schedule" data-id="' . esc_attr($item['id']) . '" data-type="' . esc_attr($item['type']) . '" aria-label="' . esc_attr__('Toggle schedule status', 'ai-post-scheduler') . '" ' . checked($is_active, true, false) . '>';
		$html .= '<span class="aips-toggle-slider"></span>';
		$html .= '</label>';
		$html .= '</div>';
		$html .= '</div>';

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

		$html  = '<div class="cell-actions">';
		$html .= '<div class="aips-btn-group aips-btn-group-inline">';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-unified-run-now" data-id="' . $id . '" data-type="' . $type . '" title="' . esc_attr__('Run Now', 'ai-post-scheduler') . '"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> ' . esc_html__('Run Now', 'ai-post-scheduler') . '</button>';
		$html .= '</div>';

		$html .= '<div class="aips-row-action-group">';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr($menu_id) . '" title="' . esc_attr__('More actions', 'ai-post-scheduler') . '"><span class="dashicons dashicons-ellipsis"></span><span class="screen-reader-text">' . esc_html__('More actions', 'ai-post-scheduler') . '</span></button>';
		$html .= '<div id="' . esc_attr($menu_id) . '" class="aips-row-action-menu" hidden>';

		if ($is_template) {
			$html .= '<button type="button" class="aips-row-action-item aips-edit-schedule" data-schedule-id="' . $id . '" data-template-id="' . esc_attr($item['template_id'] ?? '') . '" data-title="' . esc_attr($item['title']) . '" data-frequency="' . esc_attr($item['frequency'] ?? '') . '" data-topic="' . esc_attr($item['topic'] ?? '') . '" data-article-structure-id="' . esc_attr($item['article_structure_id'] ?? '') . '" data-rotation-pattern="' . esc_attr($item['rotation_pattern'] ?? '') . '" data-next-run="' . esc_attr($item['next_run'] ?? '') . '" data-is-active="' . esc_attr(!empty($item['is_active']) ? '1' : '0') . '"><span class="dashicons dashicons-edit"></span> ' . esc_html__('Edit Schedule', 'ai-post-scheduler') . '</button>';
		}

		$html .= '<button type="button" class="aips-row-action-item aips-view-unified-history" data-id="' . $id . '" data-type="' . $type . '" data-name="' . esc_attr($item['title']) . '" data-limit="5"><span class="dashicons dashicons-backup"></span> ' . esc_html__('View History', 'ai-post-scheduler') . '</button>';

		if ($is_template && 'open' === $circuit_state) {
			$html .= '<button type="button" class="aips-row-action-item aips-reset-circuit aips-text-warning" data-id="' . $id . '" data-type="' . $type . '"><span class="dashicons dashicons-update"></span> ' . esc_html__('Reset Circuit & Retry', 'ai-post-scheduler') . '</button>';
		}

		if ($is_template && $has_incomplete_batch) {
			$html .= '<button type="button" class="aips-row-action-item aips-resume-batch" data-id="' . $id . '" data-type="' . $type . '"><span class="dashicons dashicons-controls-forward"></span> ' . esc_html__('Resume Batch', 'ai-post-scheduler') . '</button>';
		}

		if ($can_delete) {
			$html .= '<button type="button" class="aips-row-action-item aips-delete-schedule aips-text-danger" data-id="' . $id . '"><span class="dashicons dashicons-trash"></span> ' . esc_html__('Delete Schedule', 'ai-post-scheduler') . '</button>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render pipeline type badge.
	 *
	 * @param string $type Schedule type identifier.
	 * @return string HTML badge.
	 */
	protected function render_type_badge($type) {
		switch ($type) {
			case AIPS_Unified_Schedule_Service::TYPE_TEMPLATE:
				return '<span class="aips-badge aips-badge-type-template">' . esc_html__('Post Generation', 'ai-post-scheduler') . '</span>';
			case AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC:
				return '<span class="aips-badge aips-badge-type-topic">' . esc_html__('Author Topics', 'ai-post-scheduler') . '</span>';
			case AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST:
				return '<span class="aips-badge aips-badge-type-post">' . esc_html__('Author Posts', 'ai-post-scheduler') . '</span>';
			case AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT:
			case AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW:
				return '<span class="aips-badge aips-badge-type-post">' . esc_html__('Blueprint', 'ai-post-scheduler') . '</span>';
		}
		return '';
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
	 * Get run output label.
	 *
	 * @param string $type Schedule type identifier.
	 * @return string
	 */
	protected function get_run_output_label($type) {
		if ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC) {
			return __('Generated topics for author queue', 'ai-post-scheduler');
		}
		if ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST) {
			return __('Generated approved-topic post', 'ai-post-scheduler');
		}
		if ($type === AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT || $type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW) {
			return __('Most recent run across stages', 'ai-post-scheduler');
		}
		return __('Generated post from template', 'ai-post-scheduler');
	}

	/**
	 * Format relative time string.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	protected function format_relative_time($timestamp) {
		$diff = $timestamp - time();
		if ($diff <= 0) {
			return __('Past due', 'ai-post-scheduler');
		}

		$units = array(
			array(
				'seconds'  => DAY_IN_SECONDS,
				'singular' => '%s day',
				'plural'   => '%s days',
			),
			array(
				'seconds'  => HOUR_IN_SECONDS,
				'singular' => '%s hour',
				'plural'   => '%s hours',
			),
			array(
				'seconds'  => MINUTE_IN_SECONDS,
				'singular' => '%s minute',
				'plural'   => '%s minutes',
			),
		);
		$parts = array();

		foreach ($units as $unit) {
			if ($diff < $unit['seconds']) {
				continue;
			}

			$value = (int) floor($diff / $unit['seconds']);
			if ($value <= 0) {
				continue;
			}

			$parts[] = sprintf(
				_n($unit['singular'], $unit['plural'], $value, 'ai-post-scheduler'),
				number_format_i18n($value)
			);
			$diff -= $value * $unit['seconds'];

			if (count($parts) === 2) {
				break;
			}
		}

		if (empty($parts)) {
			$parts[] = sprintf(_n('%s minute', '%s minutes', 1, 'ai-post-scheduler'), '1');
		}

		/* translators: %s = human-readable time difference, e.g. "6 days 4 hours" */
		return sprintf(__('In %s', 'ai-post-scheduler'), implode(' ', $parts));
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

	/**
	 * Render progressive disclosure drawer details for a Schedule row.
	 *
	 * @param array<string, mixed> $item Schedule item data.
	 * @return string HTML drawer content.
	 */
	public function single_row_details($item) {
		$html  = '<div class="aips-drawer-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;padding:12px 16px;">';

		// Pipeline Configuration
		$html .= '<div class="aips-drawer-col">';
		$html .= '<div class="aips-drawer-label" style="font-weight:600;font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">' . esc_html__('Configuration & Cron', 'ai-post-scheduler') . '</div>';
		if (!empty($item['cron_hook'])) {
			$html .= '<div style="font-size:12px;color:var(--aips-gray-600);margin-bottom:4px;"><strong style="color:var(--aips-gray-700);">' . esc_html__('Hook:', 'ai-post-scheduler') . '</strong> <code>' . esc_html($item['cron_hook']) . '</code></div>';
		}
		if (!empty($item['frequency'])) {
			$html .= '<div style="font-size:12px;color:var(--aips-gray-600);margin-bottom:4px;"><strong style="color:var(--aips-gray-700);">' . esc_html__('Recurrence:', 'ai-post-scheduler') . '</strong> ' . esc_html($this->get_frequency_label($item['frequency'])) . '</div>';
		}
		if (!empty($item['topic'])) {
			$html .= '<div style="font-size:12px;color:var(--aips-gray-600);margin-bottom:4px;"><strong style="color:var(--aips-gray-700);">' . esc_html__('Topic:', 'ai-post-scheduler') . '</strong> ' . esc_html($item['topic']) . '</div>';
		}
		if (!empty($item['rotation_pattern'])) {
			$html .= '<div style="font-size:12px;color:var(--aips-gray-600);margin-bottom:4px;"><strong style="color:var(--aips-gray-700);">' . esc_html__('Rotation:', 'ai-post-scheduler') . '</strong> ' . esc_html(ucwords(str_replace('_', ' ', $item['rotation_pattern']))) . '</div>';
		}
		$html .= '</div>';

		// Pipeline Stages breakdown (if blueprint/workflow)
		$stages = isset($item['stages']) && is_array($item['stages']) ? $item['stages'] : array();
		if (!empty($stages)) {
			$html .= '<div class="aips-drawer-col">';
			$html .= '<div class="aips-drawer-label" style="font-weight:600;font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">' . esc_html__('Pipeline Stages', 'ai-post-scheduler') . '</div>';
			$html .= '<ul class="aips-stage-list" style="margin:0;padding-left:16px;">';
			foreach ($stages as $stage) {
				$stage_name = isset($stage['name']) ? $stage['name'] : (isset($stage['stage']) ? $stage['stage'] : __('Stage', 'ai-post-scheduler'));
				$stage_freq = isset($stage['frequency']) ? $this->get_frequency_label($stage['frequency']) : '';
				$html .= '<li style="font-size:12px;color:var(--aips-gray-700);margin-bottom:2px;">' . esc_html($stage_name) . ($stage_freq ? ' <span class="aips-muted">(' . esc_html($stage_freq) . ')</span>' : '') . '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		// Health & Telemetry
		$circuit_state = isset($item['circuit_state']) ? $item['circuit_state'] : 'closed';
		$consec_fails  = isset($item['consecutive_failures']) ? (int) $item['consecutive_failures'] : 0;
		$html .= '<div class="aips-drawer-col">';
		$html .= '<div class="aips-drawer-label" style="font-weight:600;font-size:11px;color:var(--aips-gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">' . esc_html__('Health & Telemetry', 'ai-post-scheduler') . '</div>';
		$html .= '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
		if ('open' === $circuit_state) {
			$html .= '<span class="aips-badge aips-badge-danger">' . esc_html__('Circuit Breaker Tripped', 'ai-post-scheduler') . '</span>';
		} else {
			$html .= '<span class="aips-badge aips-badge-success">' . esc_html__('Circuit Healthy', 'ai-post-scheduler') . '</span>';
		}
		if ($consec_fails > 0) {
			$html .= '<span class="aips-badge aips-badge-warning">' . sprintf(esc_html__('%d Failures', 'ai-post-scheduler'), $consec_fails) . '</span>';
		}
		$html .= '</div>';
		$html .= '</div>';

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
}
