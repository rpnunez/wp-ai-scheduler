<?php
/**
 * Editions Repository
 *
 * Stores editorial package editions and their coordinated story slots.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Editions_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $editions_table;

	/**
	 * @var string
	 */
	private $slots_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->editions_table = $wpdb->prefix . 'aips_editions';
		$this->slots_table = $wpdb->prefix . 'aips_edition_slots';
	}

	/**
	 * Get the built-in slot types.
	 *
	 * @return array
	 */
	public static function get_default_slot_types() {
		return array(
			'lead_story' => __('Lead Story', 'ai-post-scheduler'),
			'secondary_analysis' => __('Secondary Analysis', 'ai-post-scheduler'),
			'roundup' => __('Roundup', 'ai-post-scheduler'),
			'faq_sidebar' => __('FAQ / Sidebar', 'ai-post-scheduler'),
			'newsletter_intro' => __('Newsletter Intro', 'ai-post-scheduler'),
		);
	}

	/**
	 * Get every edition with slot/completeness data.
	 *
	 * @param bool $include_inactive Whether to include inactive editions.
	 * @return array
	 */
	public function get_all($include_inactive = true) {
		$where = $include_inactive ? '' : 'WHERE is_active = 1';
		$editions = $this->wpdb->get_results("SELECT * FROM {$this->editions_table} {$where} ORDER BY target_publish_date ASC, name ASC");

		return $this->hydrate_editions($editions);
	}

	/**
	 * Get active editions.
	 *
	 * @return array
	 */
	public function get_active() {
		return $this->get_all(false);
	}

	/**
	 * Get a single edition by ID.
	 *
	 * @param int $edition_id Edition ID.
	 * @return object|null
	 */
	public function get_by_id($edition_id) {
		$edition = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->editions_table} WHERE id = %d",
			$edition_id
		));

		if (!$edition) {
			return null;
		}

		$items = $this->hydrate_editions(array($edition));

		return !empty($items) ? $items[0] : null;
	}

	/**
	 * Save an edition and its slots.
	 *
	 * @param array $edition_data Edition data.
	 * @param array $slots        Slot definitions.
	 * @return int|false
	 */
	public function save($edition_data, $slots) {
		$required_slots = isset($edition_data['required_slots']) ? absint($edition_data['required_slots']) : count($slots);
		if ($required_slots < 1) {
			$required_slots = max(1, count($slots));
		}

		$target_publish_date = isset($edition_data['target_publish_date']) ? str_replace('T', ' ', sanitize_text_field($edition_data['target_publish_date'])) : '';

		$payload = array(
			'name' => sanitize_text_field($edition_data['name']),
			'theme' => isset($edition_data['theme']) ? sanitize_text_field($edition_data['theme']) : '',
			'cadence' => sanitize_text_field($edition_data['cadence']),
			'target_publish_date' => $target_publish_date,
			'required_slots' => $required_slots,
			'owner' => sanitize_text_field($edition_data['owner']),
			'channel_type' => sanitize_text_field($edition_data['channel_type']),
			'is_active' => !empty($edition_data['is_active']) ? 1 : 0,
		);

		$formats = array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d');
		$edition_id = isset($edition_data['id']) ? absint($edition_data['id']) : 0;
		$clean_slots = $this->sanitize_slots($slots);

		if (empty($payload['name']) || empty($payload['cadence']) || empty($payload['target_publish_date']) || empty($payload['owner']) || empty($payload['channel_type']) || empty($clean_slots)) {
			return false;
		}

		if ($edition_id) {
			$result = $this->wpdb->update(
				$this->editions_table,
				$payload,
				array('id' => $edition_id),
				$formats,
				array('%d')
			);

			if ($result === false) {
				return false;
			}
		} else {
			$result = $this->wpdb->insert($this->editions_table, $payload, $formats);
			if (!$result) {
				return false;
			}
			$edition_id = (int) $this->wpdb->insert_id;
		}

		$this->replace_slots($edition_id, $clean_slots);

		return $edition_id;
	}

	/**
	 * Delete an edition and its slots.
	 *
	 * @param int $edition_id Edition ID.
	 * @return bool
	 */
	public function delete($edition_id) {
		$this->wpdb->delete($this->slots_table, array('edition_id' => absint($edition_id)), array('%d'));

		return false !== $this->wpdb->delete($this->editions_table, array('id' => absint($edition_id)), array('%d'));
	}

	/**
	 * Update edition active state.
	 *
	 * @param int $edition_id Edition ID.
	 * @param int $is_active  Active flag.
	 * @return bool
	 */
	public function set_active($edition_id, $is_active) {
		return false !== $this->wpdb->update(
			$this->editions_table,
			array('is_active' => $is_active ? 1 : 0),
			array('id' => absint($edition_id)),
			array('%d'),
			array('%d')
		);
	}

	/**
	 * Get the next available slots for planner assignment.
	 *
	 * @param int $edition_id Edition ID.
	 * @param int $limit      Max slots.
	 * @return array
	 */
	public function get_next_unfilled_slots($edition_id, $limit = 0) {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->slots_table} WHERE edition_id = %d AND (assigned_topic = '' OR assigned_topic IS NULL) ORDER BY sort_order ASC, id ASC",
			$edition_id
		);

		if ($limit > 0) {
			$sql .= ' LIMIT ' . absint($limit);
		}

		return $this->wpdb->get_results($sql);
	}

	/**
	 * Attach a schedule/topic/template to a slot.
	 *
	 * @param int    $slot_id      Slot ID.
	 * @param int    $schedule_id  Schedule ID.
	 * @param string $topic        Assigned topic.
	 * @param int    $template_id  Template ID.
	 * @return bool
	 */
	public function assign_slot_schedule($slot_id, $schedule_id, $topic, $template_id) {
		return false !== $this->wpdb->update(
			$this->slots_table,
			array(
				'assigned_topic' => sanitize_text_field($topic),
				'schedule_id' => absint($schedule_id),
				'template_id' => absint($template_id),
			),
			array('id' => absint($slot_id)),
			array('%s', '%d', '%d'),
			array('%d')
		);
	}

	/**
	 * Mark a slot's post after generation.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @param int $post_id     Post ID.
	 * @return bool
	 */
	public function mark_slot_post_generated($schedule_id, $post_id) {
		$slot = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->slots_table} WHERE schedule_id = %d ORDER BY id ASC LIMIT 1",
			$schedule_id
		));

		if (!$slot) {
			return false;
		}

		update_post_meta($post_id, '_aips_edition_id', (int) $slot->edition_id);
		update_post_meta($post_id, '_aips_edition_slot_id', (int) $slot->id);
		update_post_meta($post_id, '_aips_edition_slot_key', (string) $slot->slot_key);
		update_post_meta($post_id, '_aips_edition_slot_label', (string) $slot->slot_label);

		return false !== $this->wpdb->update(
			$this->slots_table,
			array('post_id' => absint($post_id)),
			array('id' => absint($slot->id)),
			array('%d'),
			array('%d')
		);
	}

	/**
	 * Get edition prompt context for a scheduled slot.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return array
	 */
	public function get_generation_context_by_schedule_id($schedule_id) {
		$slot = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT s.*, e.name AS edition_name, e.theme, e.cadence, e.target_publish_date, e.required_slots, e.owner, e.channel_type
			 FROM {$this->slots_table} s
			 INNER JOIN {$this->editions_table} e ON s.edition_id = e.id
			 WHERE s.schedule_id = %d
			 ORDER BY s.id ASC LIMIT 1",
			$schedule_id
		));

		if (!$slot) {
			return array();
		}

		$related_slots = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT slot_label, assigned_topic FROM {$this->slots_table} WHERE edition_id = %d ORDER BY sort_order ASC, id ASC",
			$slot->edition_id
		));

		$related_items = array();
		foreach ($related_slots as $related_slot) {
			$label = $related_slot->slot_label;
			if (!empty($related_slot->assigned_topic)) {
				$label .= ': ' . $related_slot->assigned_topic;
			}
			$related_items[] = $label;
		}

		return array(
			'edition_id' => (int) $slot->edition_id,
			'edition_name' => (string) $slot->edition_name,
			'edition_theme' => !empty($slot->theme) ? (string) $slot->theme : (string) $slot->edition_name,
			'edition_cadence' => (string) $slot->cadence,
			'edition_target_publish_date' => (string) $slot->target_publish_date,
			'edition_required_slots' => (int) $slot->required_slots,
			'edition_owner' => (string) $slot->owner,
			'edition_channel_type' => (string) $slot->channel_type,
			'edition_slot_name' => (string) $slot->slot_label,
			'edition_slot_key' => (string) $slot->slot_key,
			'edition_related_items' => $related_items,
		);
	}

	/**
	 * Get edition details by post IDs.
	 *
	 * @param array $post_ids Post IDs.
	 * @return array
	 */
	public function get_post_edition_details_map($post_ids) {
		$post_ids = array_values(array_unique(array_filter(array_map('absint', (array) $post_ids))));
		if (empty($post_ids)) {
			return array();
		}

		$placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
		$query = $this->wpdb->prepare(
			"SELECT s.post_id, s.slot_label, s.slot_key, e.id AS edition_id, e.name, e.theme, e.channel_type, e.target_publish_date
			 FROM {$this->slots_table} s
			 INNER JOIN {$this->editions_table} e ON s.edition_id = e.id
			 WHERE s.post_id IN ({$placeholders})",
			$post_ids
		);

		$rows = $this->wpdb->get_results($query);
		$map = array();
		foreach ($rows as $row) {
			$map[(int) $row->post_id] = array(
				'edition_id' => (int) $row->edition_id,
				'edition_name' => (string) $row->name,
				'edition_theme' => !empty($row->theme) ? (string) $row->theme : (string) $row->name,
				'channel_type' => (string) $row->channel_type,
				'target_publish_date' => (string) $row->target_publish_date,
				'slot_label' => (string) $row->slot_label,
				'slot_key' => (string) $row->slot_key,
			);
		}

		return $map;
	}

	/**
	 * Hydrate edition stats and slots.
	 *
	 * @param array $editions Edition rows.
	 * @return array
	 */
	private function hydrate_editions($editions) {
		if (empty($editions)) {
			return array();
		}

		$edition_ids = array_map(static function ($edition) {
			return (int) $edition->id;
		}, $editions);

		$slots_by_edition = $this->get_slots_for_editions($edition_ids);
		$result = array();

		foreach ($editions as $edition) {
			$edition_slots = isset($slots_by_edition[$edition->id]) ? $slots_by_edition[$edition->id] : array();
			$completeness = $this->build_completeness($edition, $edition_slots);
			$edition->theme = !empty($edition->theme) ? $edition->theme : $edition->name;
			$edition->slots = $edition_slots;
			$edition->completeness = $completeness;
			$edition->slots_filled = $completeness['slots_filled'];
			$edition->ready_for_review = $completeness['ready_for_review'];
			$edition->blocked_by_missing_sourcing = $completeness['blocked_by_missing_sourcing'];
			$edition->ready_to_publish = $completeness['ready_to_publish'];
			$result[] = $edition;
		}

		return $result;
	}

	/**
	 * Get slots for multiple editions.
	 *
	 * @param array $edition_ids Edition IDs.
	 * @return array
	 */
	private function get_slots_for_editions($edition_ids) {
		$edition_ids = array_values(array_filter(array_map('absint', $edition_ids)));
		if (empty($edition_ids)) {
			return array();
		}

		$placeholders = implode(', ', array_fill(0, count($edition_ids), '%d'));
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->slots_table} WHERE edition_id IN ({$placeholders}) ORDER BY edition_id ASC, sort_order ASC, id ASC",
			$edition_ids
		);

		$rows = $this->wpdb->get_results($query);
		$grouped = array();
		foreach ($rows as $row) {
			$row->post_status = $row->post_id ? get_post_status($row->post_id) : '';
			$grouped[$row->edition_id][] = $row;
		}

		return $grouped;
	}

	/**
	 * Build completeness indicators.
	 *
	 * @param object $edition Edition object.
	 * @param array  $slots   Slots.
	 * @return array
	 */
	private function build_completeness($edition, $slots) {
		$required_slots = max(1, (int) $edition->required_slots);
		$slots_filled = 0;
		$ready_for_review = 0;
		$blocked = 0;
		$ready_to_publish = 0;

		foreach ($slots as $slot) {
			$is_filled = !empty($slot->assigned_topic) || !empty($slot->schedule_id) || !empty($slot->post_id);
			if ($is_filled) {
				$slots_filled++;
			}

			if ($slot->sourcing_status === 'missing') {
				$blocked++;
			}

			if (!empty($slot->post_id)) {
				$status = get_post_status($slot->post_id);
				if (in_array($status, array('draft', 'pending'), true)) {
					$ready_for_review++;
				}
				if (in_array($status, array('future', 'publish'), true)) {
					$ready_to_publish++;
				}
			}
		}

		return array(
			'required_slots' => $required_slots,
			'total_slots' => count($slots),
			'slots_filled' => $slots_filled,
			'ready_for_review' => $ready_for_review,
			'blocked_by_missing_sourcing' => $blocked,
			'ready_to_publish' => $ready_to_publish,
			'is_ready_for_review' => ($slots_filled >= $required_slots && 0 === $blocked && $ready_for_review > 0),
			'is_ready_to_publish' => ($slots_filled >= $required_slots && 0 === $blocked && $ready_to_publish >= $required_slots),
		);
	}

	/**
	 * Sanitize slot rows.
	 *
	 * @param array $slots Raw slots.
	 * @return array
	 */
	private function sanitize_slots($slots) {
		$clean = array();
		$default_types = self::get_default_slot_types();

		foreach ((array) $slots as $index => $slot) {
			if (!is_array($slot)) {
				continue;
			}

			$slot_key = isset($slot['slot_key']) ? sanitize_key($slot['slot_key']) : '';
			$slot_label = isset($slot['slot_label']) ? sanitize_text_field($slot['slot_label']) : '';
			if (empty($slot_key) && !empty($slot_label)) {
				$slot_key = sanitize_key($slot_label);
			}
			if (empty($slot_label) && isset($default_types[$slot_key])) {
				$slot_label = $default_types[$slot_key];
			}

			if (empty($slot_key) || empty($slot_label)) {
				continue;
			}

			$clean[] = array(
				'slot_key' => $slot_key,
				'slot_label' => $slot_label,
				'assigned_topic' => isset($slot['assigned_topic']) ? sanitize_text_field($slot['assigned_topic']) : '',
				'template_id' => isset($slot['template_id']) ? absint($slot['template_id']) : 0,
				'schedule_id' => isset($slot['schedule_id']) ? absint($slot['schedule_id']) : 0,
				'post_id' => isset($slot['post_id']) ? absint($slot['post_id']) : 0,
				'sourcing_status' => (isset($slot['sourcing_status']) && 'missing' === $slot['sourcing_status']) ? 'missing' : 'ready',
				'notes' => isset($slot['notes']) ? sanitize_textarea_field($slot['notes']) : '',
				'sort_order' => isset($slot['sort_order']) ? absint($slot['sort_order']) : absint($index),
			);
		}

		return $clean;
	}

	/**
	 * Replace all slots for an edition.
	 *
	 * @param int   $edition_id Edition ID.
	 * @param array $slots      Slots.
	 * @return void
	 */
	private function replace_slots($edition_id, $slots) {
		$existing_slots = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT id, template_id, schedule_id, post_id FROM {$this->slots_table} WHERE edition_id = %d ORDER BY sort_order ASC, id ASC",
			$edition_id
		));

		$this->wpdb->delete($this->slots_table, array('edition_id' => absint($edition_id)), array('%d'));

		foreach ($slots as $index => $slot) {
			$existing = isset($existing_slots[$index]) ? $existing_slots[$index] : null;
			$this->wpdb->insert(
				$this->slots_table,
				array(
					'edition_id' => absint($edition_id),
					'slot_key' => $slot['slot_key'],
					'slot_label' => $slot['slot_label'],
					'assigned_topic' => $slot['assigned_topic'],
					'template_id' => !empty($slot['template_id']) ? $slot['template_id'] : ($existing ? (int) $existing->template_id : 0),
					'schedule_id' => !empty($slot['schedule_id']) ? $slot['schedule_id'] : ($existing ? (int) $existing->schedule_id : 0),
					'post_id' => !empty($slot['post_id']) ? $slot['post_id'] : ($existing ? (int) $existing->post_id : 0),
					'sourcing_status' => $slot['sourcing_status'],
					'notes' => $slot['notes'],
					'sort_order' => absint($index),
				),
				array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d')
			);
		}
	}
}
