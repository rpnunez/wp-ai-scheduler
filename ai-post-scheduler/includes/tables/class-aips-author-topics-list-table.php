<?php
/**
 * Author Topics List Table Class
 *
 * Implements WordPress WP_List_Table for an Author's Topics management view.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_List_Table
 */
class AIPS_Author_Topics_List_Table extends AIPS_List_Table {

	/**
	 * Author ID.
	 *
	 * @var int
	 */
	protected $author_id = 0;

	/**
	 * Author object.
	 *
	 * @var object|null
	 */
	protected $author = null;

	/**
	 * Topics repository instance.
	 *
	 * @var AIPS_Author_Topics_Repository
	 */
	protected $topics_repository;

	/**
	 * Structure repository instance.
	 *
	 * @var AIPS_Article_Structure_Repository
	 */
	protected $structures_repository;

	/**
	 * Article structures mapped by ID.
	 *
	 * @var array<int, string>
	 */
	protected $structure_names = array();

	/**
	 * Status counts.
	 *
	 * @var array<string, int>
	 */
	protected $status_counts = array(
		'pending'         => 0,
		'approved'        => 0,
		'rejected'        => 0,
		'posts_generated' => 0,
		'all'             => 0,
	);

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Configuration arguments.
	 */
	public function __construct($args = array()) {
		$this->author_id = isset($args['author_id']) ? absint($args['author_id']) : (isset($_GET['author_id']) ? absint($_GET['author_id']) : 0);

		parent::__construct(wp_parse_args($args, array(
			'singular'  => 'topic',
			'plural'    => 'topics',
			'page_slug' => 'aips-automations',
			'tab_slug'  => 'author-topics',
		)));

		$this->topics_repository     = new AIPS_Author_Topics_Repository();
		$this->structures_repository = new AIPS_Article_Structure_Repository();

		if ($this->author_id > 0) {
			$authors_repo = new AIPS_Authors_Repository();
			$this->author = $authors_repo->get_by_id($this->author_id);
			$counts       = $this->topics_repository->get_status_counts($this->author_id);
			$this->status_counts['pending']         = isset($counts['pending']) ? (int) $counts['pending'] : 0;
			$this->status_counts['approved']        = isset($counts['approved']) ? (int) $counts['approved'] : 0;
			$this->status_counts['rejected']        = isset($counts['rejected']) ? (int) $counts['rejected'] : 0;
			$this->status_counts['posts_generated'] = isset($counts['posts_generated']) ? (int) $counts['posts_generated'] : 0;
			$this->status_counts['all']             = $this->status_counts['pending'] + $this->status_counts['approved'] + $this->status_counts['rejected'] + $this->status_counts['posts_generated'];
		}

		// Cache structure names
		$structures = $this->structures_repository->get_all();
		foreach ($structures as $st) {
			$this->structure_names[(int) $st->id] = $st->name;
		}
	}

	/**
	 * Define columns for the Author Topics table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" id="aips-topics-select-all" class="aips-select-all-topics" />',
			'topic'      => esc_html__('Topic Title', 'ai-post-scheduler'),
			'structure'  => esc_html__('Article Structure', 'ai-post-scheduler'),
			'status'     => esc_html__('Status', 'ai-post-scheduler'),
			'created_at' => esc_html__('Date Added', 'ai-post-scheduler'),
			'actions'    => esc_html__('Actions', 'ai-post-scheduler'),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array<string, array{string, bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'topic'      => array('topic', false),
			'status'     => array('status', false),
			'created_at' => array('created_at', false),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'approve'        => __('Approve Topics', 'ai-post-scheduler'),
			'reject'         => __('Reject Topics', 'ai-post-scheduler'),
			'generate_posts' => __('Generate Posts', 'ai-post-scheduler'),
			'delete'         => __('Delete', 'ai-post-scheduler'),
		);
	}

	/**
	 * Define status filter views.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current_view = isset($_GET['topic_status']) ? sanitize_key(wp_unslash($_GET['topic_status'])) : 'pending';
		$base_url     = add_query_arg(array(
			'page'      => $this->page_slug,
			'tab'       => $this->tab_slug,
			'author_id' => $this->author_id,
		), admin_url('admin.php'));

		$views = array();

		// Pending Review view (default)
		$views['pending'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('topic_status', 'pending', $base_url)),
			('pending' === $current_view) ? 'current' : '',
			esc_html__('Pending Review', 'ai-post-scheduler'),
			$this->status_counts['pending']
		);

		// Approved view
		$views['approved'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('topic_status', 'approved', $base_url)),
			('approved' === $current_view) ? 'current' : '',
			esc_html__('Approved', 'ai-post-scheduler'),
			$this->status_counts['approved']
		);

		// Rejected view
		$views['rejected'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('topic_status', 'rejected', $base_url)),
			('rejected' === $current_view) ? 'current' : '',
			esc_html__('Rejected', 'ai-post-scheduler'),
			$this->status_counts['rejected']
		);

		// Posts Generated view
		$views['posts_generated'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('topic_status', 'posts_generated', $base_url)),
			('posts_generated' === $current_view) ? 'current' : '',
			esc_html__('Posts Generated', 'ai-post-scheduler'),
			$this->status_counts['posts_generated']
		);

		// All view
		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('topic_status', 'all', $base_url)),
			('all' === $current_view) ? 'current' : '',
			esc_html__('All', 'ai-post-scheduler'),
			$this->status_counts['all']
		);

		return $views;
	}

	/**
	 * Prepare topics data items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		if ($this->author_id <= 0) {
			$this->items = array();
			return;
		}

		$status_filter = isset($_GET['topic_status']) ? sanitize_key(wp_unslash($_GET['topic_status'])) : 'pending';

		if ('all' === $status_filter) {
			$topics = $this->topics_repository->get_by_author($this->author_id);
		} else {
			$topics = $this->topics_repository->get_by_author_and_status($this->author_id, $status_filter);
		}

		// Filter by search query if present
		$search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
		if (!empty($search)) {
			$term = strtolower($search);
			$filtered = array();
			foreach ($topics as $topic) {
				$title = strtolower($topic->topic ?? '');
				$prompt = strtolower($topic->prompt ?? '');
				if (strpos($title, $term) !== false || strpos($prompt, $term) !== false) {
					$filtered[] = $topic;
				}
			}
			$topics = $filtered;
		}

		// Sorting
		$orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'created_at';
		$order   = isset($_GET['order']) && 'asc' === strtolower(sanitize_key(wp_unslash($_GET['order']))) ? 'asc' : 'desc';

		usort($topics, function($a, $b) use ($orderby, $order) {
			if ('topic' === $orderby) {
				$res = strcasecmp($a->topic ?? '', $b->topic ?? '');
			} elseif ('status' === $orderby) {
				$res = strcmp($a->status ?? '', $b->status ?? '');
			} else {
				$res = strcmp($a->created_at ?? '', $b->created_at ?? '');
			}
			return ('desc' === $order) ? -$res : $res;
		});

		$this->items = $topics;

		$this->set_pagination_args(array(
			'total_items' => count($topics),
			'per_page'    => count($topics),
			'total_pages' => 1,
		));
	}

	/**
	 * Checkbox column rendering.
	 *
	 * @param object $item Topic row object.
	 * @return string
	 */
	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name="topic_ids[]" value="%d" class="aips-topic-checkbox" aria-label="%s" />',
			absint($item->id),
			esc_attr(sprintf(__('Select %s', 'ai-post-scheduler'), $item->topic))
		);
	}

	/**
	 * Topic title column with expandable prompt detail and row actions.
	 *
	 * @param object $item Topic row object.
	 * @return string
	 */
	protected function column_topic($item) {
		$topic_id = (int) $item->id;
		$title    = esc_html($item->topic ?? '');
		$prompt   = !empty($item->prompt) ? esc_html($item->prompt) : '';

		$html  = '<div class="topic-title-cell" data-topic-id="' . $topic_id . '">';
		$html .= '<div class="cell-primary">';
		$html .= '<strong class="aips-topic-text">' . $title . '</strong>';
		if (!empty($prompt)) {
			$html .= ' <button type="button" class="aips-topic-expand-btn aips-btn aips-btn-ghost aips-btn-xs" data-topic-id="' . $topic_id . '" title="' . esc_attr__('Toggle Prompt Details', 'ai-post-scheduler') . '"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>';
		}
		$html .= '</div>';

		// Editable container (hidden by default, revealed when editing)
		$html .= '<div class="aips-topic-edit-form" id="topic-edit-' . $topic_id . '">';
		$html .= '<input type="text" class="aips-form-input aips-topic-edit-input" value="' . esc_attr($item->topic ?? '') . '" />';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-save-topic" data-topic-id="' . $topic_id . '">' . esc_html__('Save', 'ai-post-scheduler') . '</button> ';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-cancel-edit-topic" data-topic-id="' . $topic_id . '">' . esc_html__('Cancel', 'ai-post-scheduler') . '</button>';
		$html .= '</div>';

		// Expandable prompt container
		if (!empty($prompt)) {
			$html .= '<div class="aips-topic-detail" id="topic-detail-' . $topic_id . '">';
			$html .= '<strong>' . esc_html__('Generation Prompt:', 'ai-post-scheduler') . '</strong> ';
			$html .= $prompt;
			$html .= '</div>';
		}

		// WordPress native row actions
		$actions = array(
			'approve'       => sprintf('<a href="#" class="aips-approve-topic" data-topic-id="%d">%s</a>', $topic_id, esc_html__('Approve', 'ai-post-scheduler')),
			'reject'        => sprintf('<a href="#" class="aips-reject-topic" data-topic-id="%d">%s</a>', $topic_id, esc_html__('Reject', 'ai-post-scheduler')),
			'generate_post' => sprintf('<a href="#" class="aips-generate-post-now" data-topic-id="%d">%s</a>', $topic_id, esc_html__('Generate Post', 'ai-post-scheduler')),
			'edit'          => sprintf('<a href="#" class="aips-edit-topic" data-topic-id="%d">%s</a>', $topic_id, esc_html__('Quick Edit', 'ai-post-scheduler')),
			'delete'        => sprintf('<a href="#" class="aips-delete-topic aips-link-danger" data-topic-id="%d">%s</a>', $topic_id, esc_html__('Delete', 'ai-post-scheduler')),
		);

		$html .= $this->row_actions($actions);
		$html .= '</div>';

		return $html;
	}

	/**
	 * Article structure column rendering.
	 *
	 * @param object $item Topic row object.
	 * @return string
	 */
	protected function column_structure($item) {
		$structure_id = (int) ($item->article_structure_id ?? 0);
		if ($structure_id > 0 && isset($this->structure_names[$structure_id])) {
			return sprintf(
				'<span class="aips-badge aips-badge-secondary"><span class="dashicons dashicons-layout" aria-hidden="true"></span> %s</span>',
				esc_html($this->structure_names[$structure_id])
			);
		}
		return '<span class="cell-meta">—</span>';
	}

	/**
	 * Status column rendering.
	 *
	 * @param object $item Topic row object.
	 * @return string
	 */
	protected function column_status($item) {
		$status = $item->status ?? 'pending';
		switch ($status) {
			case 'approved':
				return $this->render_status_badge(__('Approved', 'ai-post-scheduler'), 'success', 'dashicons-yes');
			case 'rejected':
				return $this->render_status_badge(__('Rejected', 'ai-post-scheduler'), 'neutral', 'dashicons-dismiss');
			case 'posts_generated':
				return $this->render_status_badge(__('Post Generated', 'ai-post-scheduler'), 'info', 'dashicons-admin-post');
			case 'pending':
			default:
				return $this->render_status_badge(__('Pending Review', 'ai-post-scheduler'), 'warning', 'dashicons-clock');
		}
	}

	/**
	 * Date created column rendering.
	 *
	 * @param object $item Topic row object.
	 * @return string
	 */
	protected function column_created_at($item) {
		if (!empty($item->created_at)) {
			$date = date_i18n(get_option('date_format'), strtotime($item->created_at));
			return '<span class="cell-meta">' . esc_html($date) . '</span>';
		}
		return '<span class="cell-meta">—</span>';
	}

	/**
	 * Action buttons column rendering.
	 *
	 * @param object $item Topic row object.
	 * @return string
	 */
	protected function column_actions($item) {
		$topic_id = (int) $item->id;
		$status   = $item->status ?? 'pending';

		$html = '<div class="cell-actions">';

		if ('approved' !== $status && 'posts_generated' !== $status) {
			$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-success aips-approve-topic" data-topic-id="' . $topic_id . '" title="' . esc_attr__('Approve Topic', 'ai-post-scheduler') . '"><span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html__('Approve', 'ai-post-scheduler') . '</button>';
		}

		if ('rejected' !== $status && 'posts_generated' !== $status) {
			$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-reject-topic" data-topic-id="' . $topic_id . '" title="' . esc_attr__('Reject Topic', 'ai-post-scheduler') . '"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span> ' . esc_html__('Reject', 'ai-post-scheduler') . '</button>';
		}

		if ('approved' === $status) {
			$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-generate-post-now" data-topic-id="' . $topic_id . '" title="' . esc_attr__('Generate Post From Topic', 'ai-post-scheduler') . '"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span> ' . esc_html__('Generate Post', 'ai-post-scheduler') . '</button>';
		}

		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-topic" data-topic-id="' . $topic_id . '" title="' . esc_attr__('Edit Topic Title', 'ai-post-scheduler') . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-topic" data-topic-id="' . $topic_id . '" title="' . esc_attr__('Delete Topic', 'ai-post-scheduler') . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Default column fallback.
	 *
	 * @param object $item        Topic row object.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default($item, $column_name) {
		return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
	}
}
