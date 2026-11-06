<?php
/**
 * Post Images Repository
 *
 * Repository pattern implementation for entity image configuration data access.
 * Manages post content images for templates and authors in aips_post_images.
 * Featured image settings live on the template and author records.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.21
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Images_Repository {

	/**
	 * @var string Table name with prefix
	 */
	private $table_name;

	/**
	 * @var wpdb WordPress database abstraction object
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 *
	 * @param wpdb|null $wpdb Optional database instance.
	 */
	public function __construct($wpdb = null) {
		global $wpdb;
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
		$this->table_name = $this->wpdb->prefix . 'aips_post_images';
	}

	/**
	 * Get all image configurations for an entity.
	 *
	 * @param string $entity_type 'template' or 'author'.
	 * @param int    $entity_id   Entity ID.
	 * @return array Array of image config objects.
	 */
	public function get_for_entity($entity_type, $entity_id) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE entity_type = %s AND entity_id = %d ORDER BY image_type ASC, image_order ASC, id ASC",
			sanitize_key($entity_type),
			absint($entity_id)
		));
	}

	/**
	 * Get post content images configurations for an entity.
	 *
	 * @param string $entity_type 'template' or 'author'.
	 * @param int    $entity_id   Entity ID.
	 * @return array Array of post content image config objects.
	 */
	public function get_content_images_for_entity($entity_type, $entity_id) {
		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE entity_type = %s AND entity_id = %d AND image_type = 'content' ORDER BY image_order ASC, id ASC",
			sanitize_key($entity_type),
			absint($entity_id)
		));
	}

	/**
	 * Get a single image configuration by ID.
	 *
	 * @param int $id Image configuration ID.
	 * @return object|null
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			absint($id)
		));
	}

	/**
	 * Create an image configuration record.
	 *
	 * @param array $data Record data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function create($data) {
		$now = time();
		$defaults = array(
			'entity_type'       => 'template',
			'entity_id'         => 0,
			'image_type'        => 'content',
			'image_order'       => 0,
			'source'            => 'ai_prompt',
			'image_prompt'      => '',
			'unsplash_keywords' => '',
			'media_ids'         => '',
			'placement'         => 'auto',
			'caption'           => '',
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$record = wp_parse_args($data, $defaults);
		$inserted = $this->wpdb->insert(
			$this->table_name,
			array(
				'entity_type'       => sanitize_key($record['entity_type']),
				'entity_id'         => absint($record['entity_id']),
				'image_type'        => sanitize_key($record['image_type']),
				'image_order'       => (int) $record['image_order'],
				'source'            => sanitize_key($record['source']),
				'image_prompt'      => wp_kses_post($record['image_prompt']),
				'unsplash_keywords' => sanitize_textarea_field($record['unsplash_keywords']),
				'media_ids'         => sanitize_text_field($record['media_ids']),
				'placement'         => sanitize_text_field($record['placement']),
				'caption'           => sanitize_text_field($record['caption']),
				'created_at'        => absint($record['created_at']),
				'updated_at'        => absint($record['updated_at']),
			),
			array('%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
		);

		return $inserted ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update an existing image configuration record.
	 *
	 * @param int   $id   Record ID.
	 * @param array $data Updated fields.
	 * @return bool True if updated or unchanged.
	 */
	public function update($id, $data) {
		$id = absint($id);
		if (!$id) {
			return false;
		}

		$fields = array();
		$formats = array();

		if (isset($data['source'])) {
			$fields['source'] = sanitize_key($data['source']);
			$formats[] = '%s';
		}
		if (isset($data['image_order'])) {
			$fields['image_order'] = (int) $data['image_order'];
			$formats[] = '%d';
		}
		if (isset($data['image_prompt'])) {
			$fields['image_prompt'] = wp_kses_post($data['image_prompt']);
			$formats[] = '%s';
		}
		if (isset($data['unsplash_keywords'])) {
			$fields['unsplash_keywords'] = sanitize_textarea_field($data['unsplash_keywords']);
			$formats[] = '%s';
		}
		if (isset($data['media_ids'])) {
			$fields['media_ids'] = sanitize_text_field($data['media_ids']);
			$formats[] = '%s';
		}
		if (isset($data['placement'])) {
			$fields['placement'] = sanitize_text_field($data['placement']);
			$formats[] = '%s';
		}
		if (isset($data['caption'])) {
			$fields['caption'] = sanitize_text_field($data['caption']);
			$formats[] = '%s';
		}

		$fields['updated_at'] = time();
		$formats[] = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$fields,
			array('id' => $id),
			$formats,
			array('%d')
		);

		return false !== $result;
	}

	/**
	 * Delete an image configuration record.
	 *
	 * @param int $id Record ID.
	 * @return bool
	 */
	public function delete($id) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array('id' => absint($id)),
			array('%d')
		);
		return false !== $result;
	}

	/**
	 * Delete all images for an entity.
	 *
	 * @param string      $entity_type 'template' or 'author'.
	 * @param int         $entity_id   Entity ID.
	 * @param string|null $image_type  Optional image type filter.
	 * @return bool
	 */
	public function delete_for_entity($entity_type, $entity_id, $image_type = null) {
		$where = array(
			'entity_type' => sanitize_key($entity_type),
			'entity_id'   => absint($entity_id),
		);
		$formats = array('%s', '%d');

		if (null !== $image_type) {
			$where['image_type'] = sanitize_key($image_type);
			$formats[] = '%s';
		}

		$result = $this->wpdb->delete($this->table_name, $where, $formats);
		return false !== $result;
	}

	/**
	 * Synchronize image configurations for an entity, preserving row IDs.
	 * Updates existing records, inserts new ones, and purges removed records.
	 *
	 * @param string $entity_type 'template' or 'author'.
	 * @param int    $entity_id   Entity ID.
	 * @param array  $images_data Array of image row data.
	 * @param string $image_type  Image type to synchronize.
	 * @return array Array of preserved/created image record IDs.
	 */
	public function sync_for_entity($entity_type, $entity_id, array $images_data, $image_type = 'content') {
		$entity_type = sanitize_key($entity_type);
		$entity_id = absint($entity_id);
		$image_type = sanitize_key($image_type);

		$existing = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE entity_type = %s AND entity_id = %d AND image_type = %s",
			$entity_type,
			$entity_id,
			$image_type
		));

		// $wpdb returns IDs as strings; normalize them for the strict lookup below.
		$existing_ids = !empty($existing) ? array_map('intval', wp_list_pluck($existing, 'id')) : array();
		$kept_ids = array();
		$create_failed = false;

		foreach ($images_data as $index => $row) {
			$row_id = !empty($row['id']) ? absint($row['id']) : 0;
			$order = isset($row['image_order']) ? (int) $row['image_order'] : (int) $index + 1;

			$data = array(
				'entity_type'       => $entity_type,
				'entity_id'         => $entity_id,
				'image_type'        => $image_type,
				'image_order'       => $order,
				'source'            => !empty($row['source']) ? $row['source'] : 'ai_prompt',
				'image_prompt'      => isset($row['image_prompt']) ? $row['image_prompt'] : '',
				'unsplash_keywords' => isset($row['unsplash_keywords']) ? $row['unsplash_keywords'] : '',
				'media_ids'         => isset($row['media_ids']) ? $row['media_ids'] : '',
				'placement'         => isset($row['placement']) ? $row['placement'] : 'auto',
				'caption'           => isset($row['caption']) ? $row['caption'] : '',
			);

			if ($row_id > 0 && in_array($row_id, $existing_ids, true)) {
				$this->update($row_id, $data);
				$kept_ids[] = $row_id;
			} else {
				$new_id = $this->create($data);
				if ($new_id) {
					$kept_ids[] = $new_id;
				} else {
					$create_failed = true;
				}
			}
		}

		// Purge records that were removed. A failed insert may be standing in for
		// one of them, so keep the old rows rather than lose the configuration.
		$to_delete = array_diff($existing_ids, $kept_ids);
		if (!empty($to_delete) && !$create_failed) {
			$placeholders = implode(',', array_fill(0, count($to_delete), '%d'));
			$query = $this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
				$to_delete
			);
			$this->wpdb->query($query);
		}

		return $kept_ids;
	}

	/**
	 * Clone all image configurations from one entity to another.
	 *
	 * @param string $from_type Source entity type ('template' | 'author').
	 * @param int    $from_id   Source entity ID.
	 * @param string $to_type   Target entity type ('template' | 'author').
	 * @param int    $to_id     Target entity ID.
	 * @return int Number of cloned records.
	 */
	public function clone_for_entity($from_type, $from_id, $to_type, $to_id) {
		$source_records = $this->get_for_entity($from_type, $from_id);
		if (empty($source_records)) {
			return 0;
		}

		$cloned_count = 0;
		foreach ($source_records as $rec) {
			$data = array(
				'entity_type'       => sanitize_key($to_type),
				'entity_id'         => absint($to_id),
				'image_type'        => $rec->image_type,
				'image_order'       => $rec->image_order,
				'source'            => $rec->source,
				'image_prompt'      => $rec->image_prompt,
				'unsplash_keywords' => $rec->unsplash_keywords,
				'media_ids'         => $rec->media_ids,
				'placement'         => $rec->placement,
				'caption'           => $rec->caption,
			);
			if ($this->create($data)) {
				$cloned_count++;
			}
		}

		return $cloned_count;
	}
}
