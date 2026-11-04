<?php
/**
 * Monetized Posts List Table
 *
 * Renders the Content → Monetized Posts tab as a native WP_List_Table subclass
 * so it gains WordPress Screen Options, filter by Affiliate Offer, and search.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Monetized_Posts_List_Table extends AIPS_List_Table {

	const PER_PAGE_OPTION = 'aips_monetized_posts_per_page';
	const PER_PAGE_DEFAULT = 20;

	/**
	 * @var wpdb
	 */
	protected $wpdb;

	/**
	 * @var AIPS_Affiliate_Links_Repository
	 */
	protected $affiliate_repo;

	/**
	 * Total items matching filters.
	 *
	 * @var int
	 */
	protected $total_items = 0;

	/**
	 * Date/time format.
	 *
	 * @var string
	 */
	protected $datetime_format = '';

	public function __construct($args = array()) {
		global $wpdb;
		$this->wpdb            = $wpdb;
		$this->affiliate_repo  = new AIPS_Affiliate_Links_Repository();
		$this->datetime_format = get_option('date_format') . ' ' . get_option('time_format');

		parent::__construct(wp_parse_args($args, array(
			'singular'  => 'monetized_post',
			'plural'    => 'monetized_posts',
			'page_slug' => 'aips-generated-posts',
			'tab_slug'  => 'aips-monetized-posts',
			'ajax'      => false,
		)));
	}

	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'title'   => esc_html__('Post', 'ai-post-scheduler'),
			'offers'  => esc_html__('Affiliate Offers', 'ai-post-scheduler'),
			'links'   => esc_html__('Injected Links & Anchors', 'ai-post-scheduler'),
			'clicks'  => esc_html__('Clicks', 'ai-post-scheduler'),
			'date'    => esc_html__('Date', 'ai-post-scheduler'),
			'actions' => esc_html__('Actions', 'ai-post-scheduler'),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'title' => array('post_title', false),
			'date'  => array('post_date', true),
		);
	}

	public function get_bulk_actions() {
		return array();
	}

	public function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name="post_ids[]" value="%d" />',
			(int) $item->ID
		);
	}

	public function column_title($item) {
		$edit_url  = get_edit_post_link($item->ID);
		$view_url  = get_permalink($item->ID);
		$title_txt = !empty($item->post_title) ? $item->post_title : sprintf(__('Post #%d', 'ai-post-scheduler'), $item->ID);

		$status_label = '';
		if ('draft' === $item->post_status) {
			$status_label = ' <span class="aips-badge aips-badge-warning">' . esc_html__('Draft', 'ai-post-scheduler') . '</span>';
		} elseif ('future' === $item->post_status) {
			$status_label = ' <span class="aips-badge aips-badge-info">' . esc_html__('Scheduled', 'ai-post-scheduler') . '</span>';
		}

		$out = '<strong><a class="row-title" href="' . esc_url($edit_url) . '">' . esc_html($title_txt) . '</a></strong>' . $status_label;

		$actions = array();
		if ($edit_url) {
			$actions['edit'] = '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'ai-post-scheduler') . '</a>';
		}
		if ($view_url && 'publish' === $item->post_status) {
			$actions['view'] = '<a href="' . esc_url($view_url) . '" target="_blank">' . esc_html__('View', 'ai-post-scheduler') . '</a>';
		}

		return $out . $this->row_actions($actions);
	}

	public function column_offers($item) {
		if (empty($item->detected_offers)) {
			return '<span class="aips-text-muted">—</span>';
		}

		$badges = array();
		foreach ($item->detected_offers as $offer) {
			$label = !empty($offer->label) ? $offer->label : $offer->tag;
			$badges[] = '<span class="aips-badge aips-badge-accent">' . esc_html($label) . '</span>';
		}

		return implode(' ', $badges);
	}

	public function column_links($item) {
		if (empty($item->detected_links)) {
			return '<span class="aips-text-muted">—</span>';
		}

		$lines = array();
		foreach ($item->detected_links as $l) {
			$anchor = !empty($l['anchor']) ? $l['anchor'] : __('Link', 'ai-post-scheduler');
			$short  = strlen($l['url']) > 35 ? substr($l['url'], 0, 32) . '…' : $l['url'];
			$lines[] = '<div><strong>' . esc_html($anchor) . '</strong><br><a href="' . esc_url($l['url']) . '" target="_blank" rel="noopener" class="aips-link-muted" style="font-size:11px;">' . esc_html($short) . '</a></div>';
		}

		return implode('<div style="margin-top:6px;"></div>', $lines);
	}

	public function column_clicks($item) {
		$clicks = isset($item->total_clicks) ? (int) $item->total_clicks : 0;
		return '<span class="aips-badge ' . ($clicks > 0 ? 'aips-badge-success' : 'aips-badge-neutral') . '">' . esc_html(number_format_i18n($clicks)) . '</span>';
	}

	public function column_date($item) {
		$t = strtotime($item->post_date);
		return $t ? esc_html(wp_date($this->datetime_format, $t)) : esc_html($item->post_date);
	}

	public function column_actions($item) {
		$edit_url = get_edit_post_link($item->ID);
		return sprintf(
			'<a href="%s" class="aips-btn aips-btn-sm aips-btn-secondary">%s</a>',
			esc_url($edit_url),
			esc_html__('Edit Post', 'ai-post-scheduler')
		);
	}

	protected function extra_tablenav($which) {
		if ('top' !== $which) {
			return;
		}

		$offers         = $this->affiliate_repo->get_all();
		$selected_offer = isset($_GET['offer_id']) ? absint($_GET['offer_id']) : 0;
		$status         = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : '';
		?>
		<div class="alignleft actions">
			<label for="filter-by-offer" class="screen-reader-text"><?php esc_html_e('Filter by Affiliate Offer', 'ai-post-scheduler'); ?></label>
			<select name="offer_id" id="filter-by-offer">
				<option value="0"><?php esc_html_e('All Affiliate Offers', 'ai-post-scheduler'); ?></option>
				<?php foreach ($offers as $off) : ?>
					<option value="<?php echo esc_attr((string) $off->id); ?>" <?php selected($selected_offer, (int) $off->id); ?>>
						<?php echo esc_html($off->label ?: $off->tag); ?> (<?php echo esc_html($off->tag); ?>)
					</option>
				<?php endforeach; ?>
			</select>

			<select name="post_status" id="filter-by-status">
				<option value=""><?php esc_html_e('All Post Statuses', 'ai-post-scheduler'); ?></option>
				<option value="publish" <?php selected($status, 'publish'); ?>><?php esc_html_e('Published', 'ai-post-scheduler'); ?></option>
				<option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
			</select>

			<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e('Filter', 'ai-post-scheduler'); ?>">
		</div>
		<?php
	}

	public function prepare_items() {
		$per_page     = $this->get_items_per_page(self::PER_PAGE_OPTION, self::PER_PAGE_DEFAULT);
		$current_page = $this->get_pagenum();
		$offset       = ($current_page - 1) * $per_page;

		$selected_offer_id = isset($_GET['offer_id']) ? absint($_GET['offer_id']) : 0;
		$status_filter     = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : '';
		$search            = isset($_REQUEST['s']) ? trim(sanitize_text_field(wp_unslash($_REQUEST['s']))) : '';

		$where = array("p.post_type = 'post'", "p.post_status IN ('publish', 'draft', 'future')");
		$args  = array();

		if (!empty($status_filter)) {
			$where[1] = "p.post_status = %s";
			$args[]   = $status_filter;
		}

		if (!empty($search)) {
			$where[] = "(p.post_title LIKE %s OR p.post_content LIKE %s)";
			$like    = '%' . $this->wpdb->esc_like($search) . '%';
			$args[]  = $like;
			$args[]  = $like;
		}

		$all_offers = $this->affiliate_repo->get_all();
		$offer_map  = array();
		$url_conditions = array();

		if ($selected_offer_id > 0) {
			$target_offer = $this->affiliate_repo->get_by_id($selected_offer_id);
			if ($target_offer && !empty($target_offer->affiliate_url)) {
				$offer_map[$target_offer->affiliate_url] = $target_offer;
				$url_conditions[] = "p.post_content LIKE %s";
				$args[] = '%' . $this->wpdb->esc_like($target_offer->affiliate_url) . '%';
			}
		} else {
			foreach ($all_offers as $off) {
				if (!empty($off->affiliate_url)) {
					$offer_map[$off->affiliate_url] = $off;
					$url_conditions[] = "p.post_content LIKE %s";
					$args[] = '%' . $this->wpdb->esc_like($off->affiliate_url) . '%';
				}
			}
		}

		if (empty($url_conditions)) {
			$this->items       = array();
			$this->total_items = 0;
			return;
		}

		$where[] = '(' . implode(' OR ', $url_conditions) . ')';
		$where_sql = implode(' AND ', $where);

		$count_query = "SELECT COUNT(DISTINCT p.ID) FROM {$this->wpdb->posts} p WHERE {$where_sql}";
		$this->total_items = !empty($args)
			? (int) $this->wpdb->get_var($this->wpdb->prepare($count_query, ...$args))
			: (int) $this->wpdb->get_var($count_query);

		$query_args = $args;
		$query = "SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_content
		          FROM {$this->wpdb->posts} p
		          WHERE {$where_sql}
		          ORDER BY p.post_date DESC
		          LIMIT %d OFFSET %d";
		$query_args[] = $per_page;
		$query_args[] = $offset;

		$posts = $this->wpdb->get_results(
			$this->wpdb->prepare($query, ...$query_args)
		);

		$clicks_repo = AIPS_Container::get_instance()->make( AIPS_Link_Clicks_Repository::class );

		foreach ($posts as $p) {
			$p->detected_offers = array();
			$p->detected_links  = array();
			$p->total_clicks    = (int) array_sum( $clicks_repo->get_clicks_by_target( (int) $p->ID, 0 ) );

			// Extract <a> tags
			if (preg_match_all('/<a\s+[^>]*?href=(["\'])(https?:\/\/[^"\']+)\1[^>]*?>(.*?)<\/a>/is', $p->post_content, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $m) {
					$href   = $m[2];
					$anchor = wp_strip_all_tags($m[3]);

					foreach ($offer_map as $aff_url => $offer_obj) {
						if (strpos($href, $aff_url) !== false || $href === $aff_url) {
							if (!isset($p->detected_offers[$offer_obj->id])) {
								$p->detected_offers[$offer_obj->id] = $offer_obj;
							}
							$p->detected_links[] = array(
								'url'    => $href,
								'anchor' => $anchor,
							);
						}
					}
				}
			}
		}

		$this->items = $posts;

		$this->set_pagination_args(array(
			'total_items' => $this->total_items,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil($this->total_items / $per_page),
		));
	}
}
