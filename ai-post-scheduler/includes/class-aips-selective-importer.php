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
		global $wpdb;

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
			$table       = $wpdb->prefix . 'aips_voices';

			foreach ($entities['voices'] as $voice) {
				$old_id = !empty($voice['id']) ? (int) $voice['id'] : 0;
				$name   = sanitize_text_field($voice['name']);

				$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));

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
						$name .= ' (' . __('Imported', 'ai-post-scheduler') . ' ' . wp_rand(10, 99) . ')';
						$voice['name'] = $name;
					}
				}

				$new_id = $voices_repo->create($voice);
				if ($new_id) {
					$id_map['voices'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Voice: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 2. PROMPT SECTIONS
		if (isset($types['prompt_sections']) && !empty($entities['prompt_sections'])) {
			$sec_repo = AIPS_Prompt_Section_Repository::instance();
			$table    = $wpdb->prefix . 'aips_prompt_sections';

			foreach ($entities['prompt_sections'] as $sec) {
				$old_id = !empty($sec['id']) ? (int) $sec['id'] : 0;
				$key    = sanitize_key($sec['section_key']);

				$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE section_key = %s", $key));

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
						$key .= '_imp_' . wp_rand(10, 99);
						$sec['section_key'] = $key;
						$sec['name']       .= ' (' . __('Imported', 'ai-post-scheduler') . ')';
					}
				}

				$new_id = $sec_repo->create($sec);
				if ($new_id) {
					$id_map['sections'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Section: %s', 'ai-post-scheduler'), $sec['name']);
				}
			}
		}

		// 3. ARTICLE STRUCTURES
		if (isset($types['article_structures']) && !empty($entities['article_structures'])) {
			$str_repo = AIPS_Article_Structure_Repository::instance();
			$table    = $wpdb->prefix . 'aips_article_structures';

			foreach ($entities['article_structures'] as $str) {
				$old_id = !empty($str['id']) ? (int) $str['id'] : 0;
				$name   = sanitize_text_field($str['name']);

				$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));

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
						$name .= ' (' . __('Imported', 'ai-post-scheduler') . ' ' . wp_rand(10, 99) . ')';
						$str['name'] = $name;
					}
				}

				$new_id = $str_repo->create($str);
				if ($new_id) {
					$id_map['structures'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Structure: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 4. AUTHORS
		if (isset($types['authors']) && !empty($entities['authors'])) {
			$auth_repo = AIPS_Authors_Repository::instance();
			$table     = $wpdb->prefix . 'aips_authors';

			foreach ($entities['authors'] as $author) {
				$old_id = !empty($author['id']) ? (int) $author['id'] : 0;
				$name   = sanitize_text_field($author['name']);

				// Remap article_structure_id if present
				if (!empty($author['article_structure_id']) && isset($id_map['structures'][(int) $author['article_structure_id']])) {
					$author['article_structure_id'] = $id_map['structures'][(int) $author['article_structure_id']];
				}

				$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));

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
						$name .= ' (' . __('Imported', 'ai-post-scheduler') . ' ' . wp_rand(10, 99) . ')';
						$author['name'] = $name;
					}
				}

				unset($author['id']);
				$new_id = $auth_repo->create($author);
				if ($new_id) {
					$id_map['authors'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Author: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 5. AUTHOR TOPICS
		if (isset($types['author_topics']) && !empty($entities['author_topics'])) {
			$topics_repo = AIPS_Author_Topics_Repository::instance();

			foreach ($entities['author_topics'] as $topic) {
				$old_author_id = !empty($topic['author_id']) ? (int) $topic['author_id'] : 0;
				$new_author_id = isset($id_map['authors'][$old_author_id]) ? $id_map['authors'][$old_author_id] : $old_author_id;

				$topic['author_id'] = $new_author_id;
				unset($topic['id']);

				$new_id = $topics_repo->create($topic);
				if ($new_id) {
					$report['imported'][] = sprintf(__('Topic: %s', 'ai-post-scheduler'), $topic['topic_title']);
				}
			}
		}

		// 6. TEMPLATES
		if (isset($types['templates']) && !empty($entities['templates'])) {
			$tpl_repo = AIPS_Template_Repository::instance();
			$table    = $wpdb->prefix . 'aips_templates';

			foreach ($entities['templates'] as $tpl) {
				$old_id = !empty($tpl['id']) ? (int) $tpl['id'] : 0;
				$name   = sanitize_text_field($tpl['name']);

				// Remap foreign keys
				if (!empty($tpl['voice_id']) && isset($id_map['voices'][(int) $tpl['voice_id']])) {
					$tpl['voice_id'] = $id_map['voices'][(int) $tpl['voice_id']];
				}
				if (!empty($tpl['post_author']) && isset($id_map['authors'][(int) $tpl['post_author']])) {
					$tpl['post_author'] = $id_map['authors'][(int) $tpl['post_author']];
				}

				$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));

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
						$name .= ' (' . __('Imported', 'ai-post-scheduler') . ' ' . wp_rand(10, 99) . ')';
						$tpl['name'] = $name;
					}
				}

				unset($tpl['id']);
				$new_id = $tpl_repo->create($tpl);
				if ($new_id) {
					$id_map['templates'][$old_id] = (int) $new_id;
					$report['imported'][] = sprintf(__('Template: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 7. SCHEDULES
		if (isset($types['schedules']) && !empty($entities['schedules'])) {
			$sch_repo = AIPS_Schedule_Repository::instance();
			$table    = $wpdb->prefix . 'aips_schedule';

			foreach ($entities['schedules'] as $sch) {
				$title = !empty($sch['title']) ? sanitize_text_field($sch['title']) : __('Scheduled Job', 'ai-post-scheduler');

				// Remap foreign keys
				if (!empty($sch['template_id']) && isset($id_map['templates'][(int) $sch['template_id']])) {
					$sch['template_id'] = $id_map['templates'][(int) $sch['template_id']];
				}
				if (!empty($sch['author_id']) && isset($id_map['authors'][(int) $sch['author_id']])) {
					$sch['author_id'] = $id_map['authors'][(int) $sch['author_id']];
				}
				if (!empty($sch['article_structure_id']) && isset($id_map['structures'][(int) $sch['article_structure_id']])) {
					$sch['article_structure_id'] = $id_map['structures'][(int) $sch['article_structure_id']];
				}

				// Reset runtime states for newly imported schedules
				$sch['last_run']            = 0;
				$sch['schedule_history_id'] = null;
				$sch['next_run']            = AIPS_DateTime::now()->timestamp() + 86400;
				unset($sch['id']);

				$new_id = $sch_repo->create($sch);
				if ($new_id) {
					$report['imported'][] = sprintf(__('Schedule: %s', 'ai-post-scheduler'), $title);
				}
			}
		}

		// 8. POST SLICES
		if (isset($types['post_slices']) && !empty($entities['post_slices'])) {
			$table = $wpdb->prefix . 'aips_post_slices';

			foreach ($entities['post_slices'] as $slice) {
				$name     = sanitize_text_field($slice['name']);
				$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));
				if (!$existing) {
					unset($slice['id']);
					$slice['created_at'] = AIPS_DateTime::now()->timestamp();
					$slice['updated_at'] = $slice['created_at'];
					$wpdb->insert($table, $slice);
					$report['imported'][] = sprintf(__('Post Slice: %s', 'ai-post-scheduler'), $name);
				} else {
					$report['skipped'][] = sprintf(__('Post Slice: %s', 'ai-post-scheduler'), $name);
				}
			}
		}

		// 9. SOURCES
		if (isset($types['sources']) && !empty($entities['sources'])) {
			$table = $wpdb->prefix . 'aips_sources';

			foreach ($entities['sources'] as $src) {
				$old_id   = !empty($src['id']) ? (int) $src['id'] : 0;
				$url      = esc_url_raw($src['url']);
				$existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE url = %s", $url));

				if ($existing) {
					$id_map['sources'][$old_id] = (int) $existing->id;
					$report['skipped'][] = sprintf(__('Source: %s', 'ai-post-scheduler'), $url);
					continue;
				}

				unset($src['id']);
				$src['created_at'] = AIPS_DateTime::now()->timestamp();
				$src['updated_at'] = $src['created_at'];
				$wpdb->insert($table, $src);
				$new_id = (int) $wpdb->insert_id;
				if ($new_id) {
					$id_map['sources'][$old_id] = $new_id;
					$report['imported'][] = sprintf(__('Source: %s', 'ai-post-scheduler'), $url);
				}
			}
		}

		// 10. SOURCES DATA
		if (isset($types['sources_data']) && !empty($entities['sources_data'])) {
			$table = $wpdb->prefix . 'aips_sources_data';

			foreach ($entities['sources_data'] as $data_row) {
				$old_src_id = !empty($data_row['source_id']) ? (int) $data_row['source_id'] : 0;
				$new_src_id = isset($id_map['sources'][$old_src_id]) ? $id_map['sources'][$old_src_id] : $old_src_id;

				$data_row['source_id'] = $new_src_id;
				unset($data_row['id']);
				$wpdb->insert($table, $data_row);
			}
			$report['imported'][] = sprintf(__('Source data records (%d)', 'ai-post-scheduler'), count($entities['sources_data']));
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
			);

			foreach ($entities['settings'] as $key => $val) {
				if (in_array($key, $allowed, true)) {
					update_option($key, sanitize_text_field((string) $val));
				}
			}
			$report['imported'][] = __('Content Strategy & General Settings', 'ai-post-scheduler');
		}

		return $report;
	}
}
