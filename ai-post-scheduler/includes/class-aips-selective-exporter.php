<?php
/**
 * Selective Exporter
 *
 * Exports user-selected subsets of AI Post Scheduler data (templates, schedules,
 * authors, voices, structures, slices, sources, history, settings) into a
 * structured, portable JSON format with automated dependency resolution.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Selective_Exporter {

	/**
	 * @var AIPS_Import_Export_Repository
	 */
	private $repository;

	/**
	 * Available exportable entity types and their human-readable labels.
	 *
	 * @var array<string, string>
	 */
	public static $available_types = array(
		'templates'          => 'Templates',
		'schedules'          => 'Schedules',
		'authors'            => 'Authors',
		'author_topics'      => 'Author Topics',
		'voices'             => 'Voices',
		'article_structures' => 'Article Structures',
		'prompt_sections'    => 'Prompt Sections',
		'post_slices'        => 'Post Slices',
		'sources'            => 'Sources',
		'sources_data'       => 'Source Data',
		'history'            => 'Generated Posts & History',
		'settings'           => 'Content Strategy & Settings',
	);

	/**
	 * Constructor.
	 *
	 * @param AIPS_Import_Export_Repository|null $repository Optional repository.
	 */
	public function __construct(?AIPS_Import_Export_Repository $repository = null) {
		$this->repository = $repository ?: AIPS_Import_Export_Repository::instance();
	}

	/**
	 * Export selected entity types to an array structure.
	 *
	 * @param array $selected_types List of entity keys to export.
	 * @return array Export package array.
	 */
	public function export_data(array $selected_types): array {
		$package = array(
			'generator'      => 'AI_Post_Scheduler',
			'version'        => defined('AIPS_VERSION') ? AIPS_VERSION : '3.7.19',
			'exported_at'    => AIPS_DateTime::now()->toMysql(),
			'schema_version' => '2.0',
			'entities'       => array(),
		);

		$types = array_flip($selected_types);

		// 1. Voices
		if (isset($types['voices'])) {
			$package['entities']['voices'] = $this->repository->export_table_rows('voices');
		}

		// 2. Prompt Sections
		if (isset($types['prompt_sections'])) {
			$package['entities']['prompt_sections'] = $this->repository->export_table_rows('prompt_sections');
		}

		// 3. Article Structures
		if (isset($types['article_structures'])) {
			$package['entities']['article_structures'] = $this->repository->export_table_rows('article_structures');
		}

		// 4. Authors
		if (isset($types['authors'])) {
			$package['entities']['authors'] = $this->repository->export_table_rows('authors');
		}

		// 5. Author Topics
		if (isset($types['author_topics'])) {
			$package['entities']['author_topics'] = $this->repository->export_table_rows('author_topics');
		}

		// 6. Templates
		if (isset($types['templates'])) {
			$package['entities']['templates'] = $this->repository->export_table_rows('templates');
		}

		// 7. Schedules
		if (isset($types['schedules'])) {
			$package['entities']['schedules'] = $this->repository->export_table_rows('schedules');
		}

		// 8. Post Slices
		if (isset($types['post_slices'])) {
			$package['entities']['post_slices'] = $this->repository->export_table_rows('post_slices');
		}

		// 9. Sources
		if (isset($types['sources'])) {
			$package['entities']['sources'] = $this->repository->export_table_rows('sources');
		}

		// 10. Source Data
		if (isset($types['sources_data'])) {
			$package['entities']['sources_data'] = $this->repository->export_table_rows('sources_data', 5000);
		}

		// 11. History
		if (isset($types['history'])) {
			$package['entities']['history'] = $this->repository->export_table_rows('history', 1000, 'id DESC');
		}

		// 12. Settings
		if (isset($types['settings'])) {
			$settings_keys = array(
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
			$settings = array();
			foreach ($settings_keys as $key) {
				$val = get_option($key, null);
				if ($val !== null) {
					$settings[$key] = $val;
				}
			}
			$package['entities']['settings'] = $settings;
		}

		// Smart Dependency Resolution: Ensure referenced items exist in the export
		$this->resolve_dependencies($package);

		return $package;
	}

	/**
	 * Automatically fetch missing dependencies referenced by exported records.
	 *
	 * Runs iteratively to resolve transitive dependency chains (e.g., Schedule -> Template -> Voice).
	 *
	 * @param array &$package Export package.
	 */
	private function resolve_dependencies(array &$package): void {
		for ($pass = 0; $pass < 3; $pass++) {
			$added_any = false;

			// 1. Schedules -> Templates, Authors, Structures
			if (!empty($package['entities']['schedules'])) {
				$tpl_ids    = array();
				$author_ids = array();
				$struct_ids = array();
				foreach ($package['entities']['schedules'] as $sch) {
					if (!empty($sch['template_id'])) {
						$tpl_ids[] = (int) $sch['template_id'];
					}
					if (!empty($sch['author_id'])) {
						$author_ids[] = (int) $sch['author_id'];
					}
					if (!empty($sch['article_structure_id'])) {
						$struct_ids[] = (int) $sch['article_structure_id'];
					}
				}

				if (!empty($tpl_ids)) {
					$existing_tpl = !empty($package['entities']['templates']) ? wp_list_pluck($package['entities']['templates'], 'id') : array();
					$missing_tpl  = array_diff($tpl_ids, $existing_tpl);
					if (!empty($missing_tpl)) {
						$rows = $this->repository->export_rows_by_ids('templates', $missing_tpl);
						$package['entities']['templates'] = array_merge($package['entities']['templates'] ?? array(), $rows);
						$added_any = true;
					}
				}

				if (!empty($author_ids)) {
					$existing_author_ids = !empty($package['entities']['authors']) ? wp_list_pluck($package['entities']['authors'], 'id') : array();
					$missing_authors     = array_diff($author_ids, $existing_author_ids);
					if (!empty($missing_authors)) {
						$rows = $this->repository->export_rows_by_ids('authors', $missing_authors);
						$package['entities']['authors'] = array_merge($package['entities']['authors'] ?? array(), $rows);
						$added_any = true;
					}
				}

				if (!empty($struct_ids)) {
					$existing_struct = !empty($package['entities']['article_structures']) ? wp_list_pluck($package['entities']['article_structures'], 'id') : array();
					$missing_struct  = array_diff($struct_ids, $existing_struct);
					if (!empty($missing_struct)) {
						$rows = $this->repository->export_rows_by_ids('article_structures', $missing_struct);
						$package['entities']['article_structures'] = array_merge($package['entities']['article_structures'] ?? array(), $rows);
						$added_any = true;
					}
				}
			}

			// 2. Templates -> Voices (post_author is a WordPress user ID, not an AIPS author).
			if (!empty($package['entities']['templates'])) {
				$voice_ids = array();
				foreach ($package['entities']['templates'] as $tpl) {
					if (!empty($tpl['voice_id'])) {
						$voice_ids[] = (int) $tpl['voice_id'];
					}
				}

				if (!empty($voice_ids)) {
					$existing_voice_ids = !empty($package['entities']['voices']) ? wp_list_pluck($package['entities']['voices'], 'id') : array();
					$missing_voices     = array_diff($voice_ids, $existing_voice_ids);
					if (!empty($missing_voices)) {
						$rows = $this->repository->export_rows_by_ids('voices', $missing_voices);
						$package['entities']['voices'] = array_merge($package['entities']['voices'] ?? array(), $rows);
						$added_any = true;
					}
				}
			}

			// 3. Structures -> Prompt Sections
			if (!empty($package['entities']['article_structures'])) {
				$needed_section_keys = array();
				foreach ($package['entities']['article_structures'] as $str) {
					if (!empty($str['structure_data'])) {
						$data = json_decode($str['structure_data'], true);
						if (is_array($data) && !empty($data['sections'])) {
							$needed_section_keys = array_merge($needed_section_keys, $data['sections']);
						}
					}
				}

				if (!empty($needed_section_keys)) {
					$existing_keys = !empty($package['entities']['prompt_sections']) ? wp_list_pluck($package['entities']['prompt_sections'], 'section_key') : array();
					$missing_keys  = array_unique(array_diff($needed_section_keys, $existing_keys));
					if (!empty($missing_keys)) {
						$rows = $this->repository->export_sections_by_keys($missing_keys);
						$package['entities']['prompt_sections'] = array_merge($package['entities']['prompt_sections'] ?? array(), $rows);
						$added_any = true;
					}
				}
			}

			if (!$added_any) {
				break;
			}
		}
	}

	/**
	 * Export data to a JSON string.
	 *
	 * @param array $selected_types
	 * @return string JSON representation.
	 */
	public function export_json(array $selected_types): string {
		$data = $this->export_data($selected_types);
		return (string) wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Send the export as a direct browser file download.
	 *
	 * @param array $selected_types
	 */
	public function download(array $selected_types): void {
		$json     = $this->export_json($selected_types);
		$filename = 'aips-export-' . gmdate('Y-m-d_H-i-s') . '.json';

		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($json));
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
