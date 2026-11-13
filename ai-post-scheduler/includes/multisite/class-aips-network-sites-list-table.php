<?php
/**
 * Multisite Network Sites List Table
 *
 * Displays all sites in the multisite network with their AIPS status,
 * schema version, AI mode, and quota usage.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AIPS_Network_Sites_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(array(
			'singular' => 'site',
			'plural'   => 'sites',
			'ajax'     => false,
		));
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'blog_id'     => esc_html__('ID', 'ai-post-scheduler'),
			'site_name'   => esc_html__('Site', 'ai-post-scheduler'),
			'status'      => esc_html__('Status', 'ai-post-scheduler'),
			'db_version'  => esc_html__('DB Version', 'ai-post-scheduler'),
			'ai_mode'     => esc_html__('AI Mode', 'ai-post-scheduler'),
			'quota_usage' => esc_html__('Quota Usage', 'ai-post-scheduler'),
		);
	}

	/**
	 * Prepare list table items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$sites_args = array(
			'number'  => $per_page,
			'offset'  => ($current_page - 1) * $per_page,
			'count'   => false,
			'orderby' => 'id',
			'order'   => 'ASC',
		);

		$sites = get_sites($sites_args);
		$total_sites = get_sites(array('count' => true));

		$data = array();
		foreach ($sites as $site) {
			$blog_id = (int) $site->blog_id;
			$details = get_blog_details($blog_id);

			switch_to_blog($blog_id);
			$db_version = get_option('aips_db_version', '');
			$has_local_mwai = !empty(get_option('mwai_options', array()));
			$has_local_ai_client = !empty(get_option('wp_ai_client_settings', array()));
			restore_current_blog();

			$using_network = AIPS_Network_Config::is_site_using_network_credentials($blog_id);
			$usage         = AIPS_Site_Quota_Service::get_usage($blog_id);
			$limits        = AIPS_Site_Quota_Service::get_site_limits($blog_id);

			$data[] = array(
				'blog_id'             => $blog_id,
				'site_name'           => $details ? $details->blogname : "Site #{$blog_id}",
				'site_url'            => $details ? $details->siteurl : '',
				'is_archived'         => (bool) $site->archived,
				'is_spam'             => (bool) $site->spam,
				'is_deleted'          => (bool) $site->deleted,
				'db_version'          => $db_version,
				'using_network_creds' => $using_network,
				'has_custom_keys'     => ($has_local_mwai || $has_local_ai_client),
				'usage'               => $usage,
				'limits'              => $limits,
			);
		}

		$this->items = $data;

		$this->set_pagination_args(array(
			'total_items' => $total_sites,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_sites / $per_page),
		));
	}

	/**
	 * Column: default fallback.
	 *
	 * @param array  $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default($item, $column_name) {
		return isset($item[$column_name]) ? esc_html((string) $item[$column_name]) : '';
	}

	/**
	 * Column: blog_id.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_blog_id($item): string {
		return '<strong>' . esc_html((string) $item['blog_id']) . '</strong>';
	}

	/**
	 * Column: site_name.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_site_name($item): string {
		$admin_url = get_admin_url($item['blog_id'], 'admin.php?page=ai-post-scheduler');
		$site_url  = esc_url($item['site_url']);
		$title     = esc_html($item['site_name']);

		$output = sprintf('<strong><a href="%s">%s</a></strong>', esc_url($admin_url), $title);
		$output .= sprintf('<br><span class="description"><a href="%s" target="_blank">%s</a></span>', $site_url, $site_url);

		return $output;
	}

	/**
	 * Column: status.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_status($item): string {
		if ($item['is_deleted']) {
			return '<span class="dashicons dashicons-trash" style="color:#d63638;"></span> ' . esc_html__('Deleted', 'ai-post-scheduler');
		}
		if ($item['is_spam']) {
			return '<span class="dashicons dashicons-flag" style="color:#d63638;"></span> ' . esc_html__('Spam', 'ai-post-scheduler');
		}
		if ($item['is_archived']) {
			return '<span class="dashicons dashicons-archive" style="color:#dba617;"></span> ' . esc_html__('Archived', 'ai-post-scheduler');
		}

		return '<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span> ' . esc_html__('Active', 'ai-post-scheduler');
	}

	/**
	 * Column: db_version.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_db_version($item): string {
		if (empty($item['db_version'])) {
			return '<span class="description">' . esc_html__('Not initialized', 'ai-post-scheduler') . '</span>';
		}

		$current = defined('AIPS_VERSION') ? AIPS_VERSION : '';
		if ($item['db_version'] === $current) {
			return '<code>' . esc_html($item['db_version']) . '</code> <span class="dashicons dashicons-yes" style="color:#00a32a;"></span>';
		}

		return '<code>' . esc_html($item['db_version']) . '</code> <span class="dashicons dashicons-warning" style="color:#dba617;" title="' . esc_attr__('Upgrade pending', 'ai-post-scheduler') . '"></span>';
	}

	/**
	 * Column: ai_mode.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_ai_mode($item): string {
		if ($item['using_network_creds']) {
			return '<span class="dashicons dashicons-networking" style="color:#2271b1;"></span> ' . esc_html__('Network Shared', 'ai-post-scheduler');
		}

		if ($item['has_custom_keys']) {
			return '<span class="dashicons dashicons-admin-network" style="color:#646970;"></span> ' . esc_html__('Custom Keys', 'ai-post-scheduler');
		}

		return '<span class="dashicons dashicons-warning" style="color:#dba617;"></span> ' . esc_html__('Unconfigured', 'ai-post-scheduler');
	}

	/**
	 * Column: quota_usage.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_quota_usage($item): string {
		$usage  = $item['usage'];
		$limits = $item['limits'];

		$daily_limit   = $limits['daily'] > 0 ? (string) $limits['daily'] : esc_html__('unlimited', 'ai-post-scheduler');
		$monthly_limit = $limits['monthly'] > 0 ? (string) $limits['monthly'] : esc_html__('unlimited', 'ai-post-scheduler');

		$daily_str   = sprintf('%d / %s', $usage['daily_calls'], $daily_limit);
		$monthly_str = sprintf('%d / %s', $usage['monthly_calls'], $monthly_limit);

		return sprintf(
			'<div><strong>%s:</strong> %s</div><div><strong>%s:</strong> %s</div>',
			esc_html__('Today', 'ai-post-scheduler'),
			esc_html($daily_str),
			esc_html__('This Month', 'ai-post-scheduler'),
			esc_html($monthly_str)
		);
	}
}
