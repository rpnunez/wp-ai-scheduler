<?php
/**
 * Selective Importer
 *
 * Parses, validates, and selectively imports entities from an AIPS JSON backup
 * with conflict resolution (copy, overwrite, skip) and relational ID remapping.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Selective_Importer {

	/**
	 * @var AIPS_Import_Export_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Import_Export_Repository|null $repository Optional repository.
	 */
	public function __construct(?AIPS_Import_Export_Repository $repository = null) {
		$this->repository = $repository ?: AIPS_Import_Export_Repository::instance();
	}

	/**
	 * Parse and validate an uploaded backup file.
	 *
	 * @param string $file_path Absolute path to the uploaded JSON file.
	 * @return array|WP_Error Summary array on success or WP_Error on failure.
	 */
	public function parse_file(string $file_path) {
		if (!file_exists($file_path) || !is_readable($file_path)) {
			return new WP_Error('file_not_readable', __('Cannot read uploaded file.', 'ai-post-scheduler'));
		}

		$json = file_get_contents($file_path);
		if (false === $json || '' === trim($json)) {
			return new WP_Error('empty_file', __('The uploaded file is empty.', 'ai-post-scheduler'));
		}

		$data = json_decode($json, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
			return new WP_Error('invalid_json', __('Invalid JSON syntax in export file.', 'ai-post-scheduler'));
		}

		// Support schema 2.0 (entities) or legacy 1.0 (tables)
		$entities = array();
		if (isset($data['entities']) && is_array($data['entities'])) {
			$entities = $data['entities'];
		} elseif (isset($data['tables']) && is_array($data['tables'])) {
			// Map legacy table names to logical keys
			$table_map = array(
				'aips_templates'          => 'templates',
				'aips_schedule'           => 'schedules',
				'aips_authors'            => 'authors',
				'aips_author_topics'      => 'author_topics',
				'aips_voices'             => 'voices',
				'aips_article_structures' => 'article_structures',
				'aips_prompt_sections'    => 'prompt_sections',
				'aips_post_slices'        => 'post_slices',
				'aips_sources'            => 'sources',
				'aips_sources_data'       => 'sources_data',
				'aips_history'            => 'history',
			);
			foreach ($data['tables'] as $tbl => $rows) {
				$key = isset($table_map[$tbl]) ? $table_map[$tbl] : $tbl;
				$entities[$key] = $rows;
			}
		} else {
			return new WP_Error('invalid_structure', __('Unrecognized export structure: missing entities or tables.', 'ai-post-scheduler'));
		}

		$counts = array();
		foreach (AIPS_Selective_Exporter::$available_types as $type_key => $label) {
			$count = 0;
			if (isset($entities[$type_key])) {
				$count = is_array($entities[$type_key]) ? count($entities[$type_key]) : 1;
			}
			$counts[$type_key] = $count;
		}

		return array(
			'counts'   => $counts,
			'entities' => $entities,
			'raw'      => $data,
		);
	}

	/**
	 * Import parsed data based on chosen types and conflict policy.
	 *
	 * @param array  $entities        Array of entity lists.
	 * @param array  $selected_types  List of entity types to import.
	 * @param string $conflict_policy 'copy', 'overwrite', or 'skip'.
	 * @return array Summary of import execution.
	 */
	public function import(array $entities, array $selected_types, string $conflict_policy = 'copy'): array {
		$types  = array_flip($selected_types);
		$id_map = array(
			'voices'     => array(),
			'sections'   => array(),
			'structures' => array(),
			'authors'    => array(),
			'templates'  => array(),
			'sources'    => array(),
		);

		$report = array(
			'imported' => array(),
			'updated'  => array(),
			'skipped'  => array(),
			'errors'   => array(),
		);

		// 1. VOICES
		if (isset($types['voices']) && !empty($entities['voices'])) {
			$voices_repo = AIPS_Voices_Repository::instance();

			foreach ($entities['voices'] as $voice) {
				$old_id = !empty($voice['id']) ? (int) $voice['id'] : 0;
				$name   = sanitize_text_field($voice['name']);

				$existing = $this->repository->find_existing('voices', 'name', $name);

				if ($existing) {
					if ('skip' === $conflict_policy) {
						$id_map['voices'][$old_id] = (int) $existing->id;
						$report['skipped'][] = sprintf(__('Voice: %s', 'ai-post-scheduler'), $name);
						continue;
					} elseif ('overwrite' === $conflict_policy) {
						$voices_repo->update($existing->id, $voice);
						$id_map['voices'][$old_id] = (int) $existing->id;
						$report['updated'][] = sprintf(__('Voice: %s', 'ai-post-scheduler'), $name);
						continue;
					} else { // 'copy'
						$name          = $this->unique_copy_name('voices', $name);
						$voice['name'] = $name;
					}
				}

				$new_id = $voices_repo->create($voice);
				if ($new_id) {
					$id_map['voices'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Voice: %s', 'ai-post-scheduler'), $name);
				} else {
					$report['errors'][] = sprintf(__('Voice: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 2. PROMPT SECTIONS
		if (isset($types['prompt_sections']) && !empty($entities['prompt_sections'])) {
			$sec_repo = AIPS_Prompt_Section_Repository::instance();

			foreach ($entities['prompt_sections'] as $sec) {
				$old_id = !empty($sec['id']) ? (int) $sec['id'] : 0;
				$key    = sanitize_key($sec['section_key']);

				$existing = $this->repository->find_existing('prompt_sections', 'section_key', $key);

				if ($existing) {
					if ('skip' === $conflict_policy) {
						$id_map['sections'][$old_id] = (int) $existing->id;
						$report['skipped'][] = sprintf(__('Section: %s', 'ai-post-scheduler'), $sec['name']);
						continue;
					} elseif ('overwrite' === $conflict_policy) {
						$sec_repo->update($existing->id, $sec);
						$id_map['sections'][$old_id] = (int) $existing->id;
						$report['updated'][] = sprintf(__('Section: %s', 'ai-post-scheduler'), $sec['name']);
						continue;
					} else { // 'copy'
						$key                = $this->unique_copy_key($key);
						$sec['section_key'] = $key;
						$sec['name']       .= ' (' . __('Imported', 'ai-post-scheduler') . ')';
					}
				}

				$new_id = $sec_repo->create($sec);
				if ($new_id) {
					$id_map['sections'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Section: %s', 'ai-post-scheduler'), $sec['name']);
				} else {
					$report['errors'][] = sprintf(__('Section: %s', 'ai-post-scheduler'), $sec['name']);
				}
			}
		}

		// 3. ARTICLE STRUCTURES
		if (isset($types['article_structures']) && !empty($entities['article_structures'])) {
			$str_repo = AIPS_Article_Structure_Repository::instance();

			foreach ($entities['article_structures'] as $str) {
				$old_id = !empty($str['id']) ? (int) $str['id'] : 0;
				$name   = sanitize_text_field($str['name']);

				$existing = $this->repository->find_existing('article_structures', 'name', $name);

				if ($existing) {
					if ('skip' === $conflict_policy) {
						$id_map['structures'][$old_id] = (int) $existing->id;
						$report['skipped'][] = sprintf(__('Structure: %s', 'ai-post-scheduler'), $name);
						continue;
					} elseif ('overwrite' === $conflict_policy) {
						$str_repo->update($existing->id, $str);
						$id_map['structures'][$old_id] = (int) $existing->id;
						$report['updated'][] = sprintf(__('Structure: %s', 'ai-post-scheduler'), $name);
						continue;
					} else { // 'copy'
						$name        = $this->unique_copy_name('article_structures', $name);
						$str['name'] = $name;
					}
				}

				$new_id = $str_repo->create($str);
				if ($new_id) {
					$id_map['structures'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Structure: %s', 'ai-post-scheduler'), $name);
				} else {
					$report['errors'][] = sprintf(__('Structure: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 4. AUTHORS
		if (isset($types['authors']) && !empty($entities['authors'])) {
			$auth_repo = AIPS_Authors_Repository::instance();

			foreach ($entities['authors'] as $author) {
				$old_id = !empty($author['id']) ? (int) $author['id'] : 0;
				$name   = sanitize_text_field($author['name']);

				// Remap article_structure_id; drop references to structures not present on this site.
				$author['article_structure_id'] = $this->map_id($id_map['structures'], $author['article_structure_id'] ?? 0);

				$existing = $this->repository->find_existing('authors', 'name', $name);

				if ($existing) {
					if ('skip' === $conflict_policy) {
						$id_map['authors'][$old_id] = (int) $existing->id;
						$report['skipped'][] = sprintf(__('Author: %s', 'ai-post-scheduler'), $name);
						continue;
					} elseif ('overwrite' === $conflict_policy) {
						$auth_repo->update($existing->id, $author);
						$id_map['authors'][$old_id] = (int) $existing->id;
						$report['updated'][] = sprintf(__('Author: %s', 'ai-post-scheduler'), $name);
						continue;
					} else { // 'copy'
						$name           = $this->unique_copy_name('authors', $name);
						$author['name'] = $name;
					}
				}

				unset($author['id']);
				$new_id = $auth_repo->create($author);
				if ($new_id) {
					$id_map['authors'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Author: %s', 'ai-post-scheduler'), $name);
				} else {
					$report['errors'][] = sprintf(__('Author: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 5. AUTHOR TOPICS
		if (isset($types['author_topics']) && !empty($entities['author_topics'])) {
			$topics_repo = AIPS_Author_Topics_Repository::instance();

			foreach ($entities['author_topics'] as $topic) {
				$topic_title   = isset($topic['topic_title']) ? $topic['topic_title'] : '';
				$new_author_id = $this->map_id($id_map['authors'], $topic['author_id'] ?? 0);

				// Never attach a topic to an unrelated local author with the same numeric ID.
				if (!$new_author_id) {
					$report['errors'][] = sprintf(__('Topic: %s (author not imported)', 'ai-post-scheduler'), $topic_title);
					continue;
				}

				$topic['author_id'] = $new_author_id;
				unset($topic['id']);

				$new_id = $topics_repo->create($topic);
				if ($new_id) {
					$report['imported'][] = sprintf(__('Topic: %s', 'ai-post-scheduler'), $topic_title);
				} else {
					$report['errors'][] = sprintf(__('Topic: %s', 'ai-post-scheduler'), $topic_title);
				}
			}
		}

		// 6. TEMPLATES
		if (isset($types['templates']) && !empty($entities['templates'])) {
			$tpl_repo = AIPS_Template_Repository::instance();

			foreach ($entities['templates'] as $tpl) {
				$old_id = !empty($tpl['id']) ? (int) $tpl['id'] : 0;
				$name   = sanitize_text_field($tpl['name']);

				// Remap voice; post_author is a WordPress user ID, so keep it only if that user exists here.
				$tpl['voice_id'] = $this->map_id($id_map['voices'], $tpl['voice_id'] ?? 0);
				if (empty($tpl['post_author']) || !get_userdata((int) $tpl['post_author'])) {
					$tpl['post_author'] = get_current_user_id();
				}

				$existing = $this->repository->find_existing('templates', 'name', $name);

				if ($existing) {
					if ('skip' === $conflict_policy) {
						$id_map['templates'][$old_id] = (int) $existing->id;
						$report['skipped'][] = sprintf(__('Template: %s', 'ai-post-scheduler'), $name);
						continue;
					} elseif ('overwrite' === $conflict_policy) {
						$tpl_repo->update($existing->id, $tpl);
						$id_map['templates'][$old_id] = (int) $existing->id;
						$report['updated'][] = sprintf(__('Template: %s', 'ai-post-scheduler'), $name);
						continue;
					} else { // 'copy'
						$name        = $this->unique_copy_name('templates', $name);
						$tpl['name'] = $name;
					}
				}

				unset($tpl['id']);
				$new_id = $tpl_repo->create($tpl);
				if ($new_id) {
					$id_map['templates'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Template: %s', 'ai-post-scheduler'), $name);
				} else {
					$report['errors'][] = sprintf(__('Template: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 7. SCHEDULES
		if (isset($types['schedules']) && !empty($entities['schedules'])) {
			$sch_repo = AIPS_Schedule_Repository::instance();

			foreach ($entities['schedules'] as $sch) {
				$title = !empty($sch['title']) ? sanitize_text_field($sch['title']) : __('Scheduled Job', 'ai-post-scheduler');

				// Remap foreign keys; a schedule cannot exist without its template.
				$sch['template_id'] = $this->map_id($id_map['templates'], $sch['template_id'] ?? 0);
				if (!$sch['template_id']) {
					$report['errors'][] = sprintf(__('Schedule: %s (template not imported)', 'ai-post-scheduler'), $title);
					continue;
				}
				$sch['author_id']            = $this->map_id($id_map['authors'], $sch['author_id'] ?? 0);
				$sch['article_structure_id'] = $this->map_id($id_map['structures'], $sch['article_structure_id'] ?? 0);

				// Reset runtime states for newly imported schedules
				$sch['last_run']            = 0;
				$sch['schedule_history_id'] = null;
				$sch['next_run']            = AIPS_DateTime::now()->timestamp() + 86400;
				unset($sch['id']);

				$new_id = $sch_repo->create($sch);
				if ($new_id) {
					$report['imported'][] = sprintf(__('Schedule: %s', 'ai-post-scheduler'), $title);
				} else {
					$report['errors'][] = sprintf(__('Schedule: %s', 'ai-post-scheduler'), $title);
				}
			}
		}

		// 8. POST SLICES
		if (isset($types['post_slices']) && !empty($entities['post_slices'])) {
			foreach ($entities['post_slices'] as $slice) {
				$name     = sanitize_text_field($slice['name']);
				$existing = $this->repository->find_existing('post_slices', 'name', $name);
				if (!$existing) {
					// Only insert known columns so exports from other versions don't break the insert.
					$slice               = $this->pick_columns($slice, array('description', 'sort_order', 'is_active'));
					$slice['name']       = $name;
					$slice['created_at'] = AIPS_DateTime::now()->timestamp();
					$slice['updated_at'] = $slice['created_at'];
					if ($this->repository->insert_row('post_slices', $slice)) {
						$report['imported'][] = sprintf(__('Post Slice: %s', 'ai-post-scheduler'), $name);
					} else {
						$report['errors'][] = sprintf(__('Post Slice: %s', 'ai-post-scheduler'), $name);
					}
				} else {
					$report['skipped'][] = sprintf(__('Post Slice: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 9. SOURCES
		if (isset($types['sources']) && !empty($entities['sources'])) {
			foreach ($entities['sources'] as $src) {
				$old_id   = !empty($src['id']) ? (int) $src['id'] : 0;
				$url      = esc_url_raw($src['url']);
				$existing = $this->repository->find_existing('sources', 'url', $url);

				if ($existing) {
					$id_map['sources'][$old_id] = (int) $existing->id;
					$report['skipped'][] = sprintf(__('Source: %s', 'ai-post-scheduler'), $url);
					continue;
				}

				$src               = $this->pick_columns($src, array('label', 'description', 'is_active', 'fetch_interval'));
				$src['url']        = $url;
				$src['created_at'] = AIPS_DateTime::now()->timestamp();
				$src['updated_at'] = $src['created_at'];
				$new_id            = $this->repository->insert_row('sources', $src);
				if ($new_id) {
					$id_map['sources'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Source: %s', 'ai-post-scheduler'), $url);
				} else {
					$report['errors'][] = sprintf(__('Source: %s', 'ai-post-scheduler'), $url);
				}
			}
		}

		// 10. SOURCES DATA
		if (isset($types['sources_data']) && !empty($entities['sources_data'])) {
			$data_imported = 0;
			$data_failed   = 0;
			$data_columns  = array(
				'url', 'page_title', 'meta_description', 'extracted_text', 'raw_html', 'char_count',
				'content_hash', 'num_used', 'fetch_status', 'http_status', 'error_message',
				'fetched_at', 'created_at', 'updated_at',
			);

			foreach ($entities['sources_data'] as $data_row) {
				$new_src_id = $this->map_id($id_map['sources'], $data_row['source_id'] ?? 0);
				if (!$new_src_id) {
					$data_failed++;
					continue;
				}

				$data_row              = $this->pick_columns($data_row, $data_columns);
				$data_row['source_id'] = $new_src_id;
				if ($this->repository->insert_row('sources_data', $data_row)) {
					$data_imported++;
				} else {
					$data_failed++;
				}
			}

			if ($data_imported > 0) {
				$report['imported'][] = sprintf(__('Source data records (%d)', 'ai-post-scheduler'), $data_imported);
			}
			if ($data_failed > 0) {
				$report['errors'][] = sprintf(__('Source data records (%d)', 'ai-post-scheduler'), $data_failed);
			}
		}

		// 11. SETTINGS
		if (isset($types['settings']) && !empty($entities['settings'])) {
			$allowed = array(
				'aips_site_niche',
				'aips_site_target_audience',
				'aips_site_content_goals',
				'aips_writing_style_general',
				'aips_brand_voice',
				'aips_banned_words',
				'aips_ai_model',
				'aips_default_post_status',
				'aips_default_post_type',
				'aips_default_article_structure_id',
			);

			foreach ($entities['settings'] as $key => $val) {
				if (!in_array($key, $allowed, true)) {
					continue;
				}

				if ('aips_default_article_structure_id' === $key) {
					// Only keep the default when its structure was imported or matched on this site.
					$mapped = $this->map_id($id_map['structures'], $val);
					if ($mapped) {
						update_option($key, $mapped);
					}
					continue;
				}

				update_option($key, is_array($val) ? map_deep($val, 'sanitize_text_field') : sanitize_textarea_field((string) $val));
			}
			$report['imported'][] = __('Content Strategy & General Settings', 'ai-post-scheduler');
		}

		return $report;
	}

	/**
	 * Resolve a source-site ID through an import ID map.
	 *
	 * @param array<int, int> $map    Old ID => new ID.
	 * @param mixed           $old_id Source-site ID.
	 * @return int|null New local ID, or null when the referenced row was not imported.
	 */
	private function map_id(array $map, $old_id): ?int {
		$old_id = (int) $old_id;
		return ($old_id > 0 && isset($map[$old_id])) ? (int) $map[$old_id] : null;
	}

	/**
	 * Build a "Name (Imported N)" copy name that is not already taken.
	 *
	 * @param string $type Entity type key.
	 * @param string $name Original name.
	 * @return string
	 */
	private function unique_copy_name(string $type, string $name): string {
		$n = 1;
		do {
			$candidate = sprintf('%s (%s %d)', $name, __('Imported', 'ai-post-scheduler'), $n++);
		} while ($this->repository->find_existing($type, 'name', $candidate));

		return $candidate;
	}

	/**
	 * Build a "key_imp_N" prompt section key that is not already taken.
	 *
	 * @param string $key Original section key.
	 * @return string
	 */
	private function unique_copy_key(string $key): string {
		$n = 1;
		do {
			$candidate = $key . '_imp_' . $n++;
		} while ($this->repository->find_existing('prompt_sections', 'section_key', $candidate));

		return $candidate;
	}

	/**
	 * Keep only the given columns from an imported row.
	 *
	 * @param array         $row     Imported row.
	 * @param array<string> $columns Allowed column names.
	 * @return array
	 */
	private function pick_columns(array $row, array $columns): array {
		return array_intersect_key($row, array_flip($columns));
	}
}
