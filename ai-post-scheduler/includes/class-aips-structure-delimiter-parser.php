<?php
/**
 * Structure Delimiter Parser
 *
 * Parses tagged component delimiters in generated content and converts them
 * into either native WordPress Gutenberg core blocks or Classic Editor shortcodes,
 * while extracting structured data for SEO JSON-LD schema generation.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Structure_Delimiter_Parser
 */
class AIPS_Structure_Delimiter_Parser {

	/**
	 * Parse content with component delimiters.
	 *
	 * @param string $content Raw generated post content.
	 * @param string $mode    Target format: 'gutenberg', 'shortcode', or 'auto'.
	 * @return array {
	 *     @type string $content         Converted content.
	 *     @type array  $extracted_data  Extracted structured data records.
	 * }
	 */
	public function parse(string $content, string $mode = 'auto'): array {
		if ($mode === 'auto') {
			$mode = $this->detect_editor_mode();
		}

		$extracted_data = array(
			'faqs'      => array(),
			'faq'       => array(),
			'steps'     => array(),
			'step_list' => array(),
			'pros'      => array(),
			'cons'      => array(),
			'rating'    => null,
			'callout'   => null,
		);

		// 1a. Parse FAQ blocks: [FAQ]...[/FAQ]
		$content = preg_replace_callback(
			'/\[FAQ\](.*?)\[\/FAQ\]/is',
			function($matches) use (&$extracted_data, $mode) {
				$inner = trim($matches[1]);
				$items = array();

				if (preg_match_all('/(?:Q|Question):\s*(.*?)\s*(?:A|Answer):\s*(.*?)(?=(?:Q|Question):|$)/is', $inner, $qa_matches, PREG_SET_ORDER)) {
					foreach ($qa_matches as $qa) {
						$q = trim($qa[1]);
						$a = trim($qa[2]);
						if (!empty($q)) {
							$items[] = array('q' => $q, 'a' => $a, 'question' => $q, 'answer' => $a);
						}
					}
				}

				if (empty($items)) {
					$lines = array_values(array_filter(array_map('trim', explode("\n", $inner))));
					for ($i = 0; $i < count($lines); $i += 2) {
						$q = preg_replace('/^(?:Q|Question)[\s:.-]+/i', '', $lines[$i]);
						$a = isset($lines[$i + 1]) ? preg_replace('/^(?:A|Answer)[\s:.-]+/i', '', $lines[$i + 1]) : '';
						if (!empty($q)) {
							$items[] = array('q' => $q, 'a' => $a, 'question' => $q, 'answer' => $a);
						}
					}
				}

				$rendered = '';
				foreach ($items as $item) {
					$extracted_data['faqs'][] = $item;
					$extracted_data['faq'][] = $item;
					if ($mode === 'gutenberg') {
						$rendered .= "\n<!-- wp:details {\"className\":\"aips-faq-item\"} -->\n<details class=\"wp-block-details aips-faq-item\"><summary><strong>" . esc_html($item['q']) . "</strong></summary><p>" . esc_html($item['a']) . "</p></details>\n<!-- /wp:details -->\n";
					} else {
						$rendered .= "\n[aips_faq question=\"" . esc_attr($item['q']) . "\"]" . esc_html($item['a']) . "[/aips_faq]\n";
					}
				}

				return $rendered;
			},
			$content
		);

		// 1b. Parse inline FAQ tags: [FAQ q="..." a="..."] or [FAQ question="..." answer="..."]
		$content = preg_replace_callback(
			'/\[FAQ\s+(?:q|question)=["\'](.*?)["\']\s+(?:a|answer)=["\'](.*?)["\']\s*\]/is',
			function($matches) use (&$extracted_data, $mode) {
				$q = trim($matches[1]);
				$a = trim($matches[2]);
				$pair = array('q' => $q, 'a' => $a, 'question' => $q, 'answer' => $a);
				$extracted_data['faqs'][] = $pair;
				$extracted_data['faq'][] = $pair;

				if ($mode === 'gutenberg') {
					return "\n<!-- wp:details {\"className\":\"aips-faq-item\"} -->\n<details class=\"wp-block-details aips-faq-item\"><summary><strong>" . esc_html($q) . "</strong></summary><p>" . esc_html($a) . "</p></details>\n<!-- /wp:details -->\n";
				} else {
					return "\n[aips_faq question=\"" . esc_attr($q) . "\"]" . esc_html($a) . "[/aips_faq]\n";
				}
			},
			$content
		);

		// 2. Parse Rating tags: [RATING (?:value|score)="X" (?:max="Y")? (?:verdict="...")?]...[/RATING]
		$content = preg_replace_callback(
			'/\[RATING\s+(?:value|score)=["\']([0-9.]+)["\'](?:\s+max=["\']([0-9.]+)["\'])?(?:\s+verdict=["\'](.*?)["\'])?\s*\](.*?)\[\/RATING\]/is',
			function($matches) use (&$extracted_data, $mode) {
				$val     = (float) $matches[1];
				$max     = !empty($matches[2]) ? (float) $matches[2] : ($val > 5.0 ? 10.0 : 5.0);
				$verdict = !empty($matches[3]) ? trim($matches[3]) : '';
				$body    = trim($matches[4]);

				$extracted_data['rating'] = array(
					'value'   => $val,
					'score'   => $val,
					'max'     => $max,
					'verdict' => $verdict,
					'content' => $body,
				);

				if ($mode === 'gutenberg') {
					$html = "\n<!-- wp:group {\"className\":\"aips-rating-box\",\"layout\":{\"type\":\"constrained\"}} -->\n";
					$html .= "<div class=\"wp-block-group aips-rating-box\">\n";
					$html .= "  <div class=\"aips-rating-badge\">\n";
					$html .= "    <span class=\"aips-rating-num\">" . esc_html((string) $val) . "</span>\n";
					$html .= "    <span class=\"aips-rating-scale\">/ " . esc_html((string) $max) . "</span>\n";
					$html .= "  </div>\n";

					if (!empty($verdict)) {
						$html .= "  <div class=\"aips-rating-verdict\"><strong>" . esc_html__('Verdict:', 'ai-post-scheduler') . "</strong> " . esc_html($verdict) . "</div>\n";
					}

					if (!empty($body)) {
						$html .= "  <div class=\"aips-rating-body\"><p>" . nl2br(esc_html($body)) . "</p></div>\n";
					}

					$html .= "</div>\n<!-- /wp:group -->\n";

					return $html;
				} else {
					return "\n[aips_rating value=\"" . esc_attr((string) $val) . "\" max=\"" . esc_attr((string) $max) . "\" verdict=\"" . esc_attr($verdict) . "\"]" . esc_html($body) . "[/aips_rating]\n";
				}
			},
			$content
		);

		// 3. Parse Pros & Cons: [PROS]...[/PROS] and [CONS]...[/CONS]
		$has_pros_cons = false;
		$pros_list = array();
		$cons_list = array();

		if (preg_match('/\[PROS\](.*?)\[\/PROS\]/is', $content, $p_matches)) {
			$has_pros_cons = true;
			$raw_pros = explode("\n", trim($p_matches[1]));

			foreach ($raw_pros as $line) {
				$line = trim(preg_replace('/^[-+*•0-9.)\s]+/', '', $line));

				if ($line !== '') {
					$pros_list[] = $line;
				}
			}

			$extracted_data['pros'] = $pros_list;
		}

		if (preg_match('/\[CONS\](.*?)\[\/CONS\]/is', $content, $c_matches)) {
			$has_pros_cons = true;
			$raw_cons = explode("\n", trim($c_matches[1]));

			foreach ($raw_cons as $line) {
				$line = trim(preg_replace('/^[-+*•0-9.)\s]+/', '', $line));

				if ($line !== '') {
					$cons_list[] = $line;
				}

			}

			$extracted_data['cons'] = $cons_list;
		}

		if ($has_pros_cons) {
			$content = preg_replace_callback(
				'/\[PROS\](.*?)\[\/PROS\]\s*(?:\[CONS\](.*?)\[\/CONS\])?|\[CONS\](.*?)\[\/CONS\]/is',
				function($matches) use ($pros_list, $cons_list, $mode) {
					if ($mode === 'gutenberg') {
						$html = "\n<!-- wp:columns {\"className\":\"aips-pros-cons-grid\"} -->\n";
						$html .= "<div class=\"wp-block-columns aips-pros-cons-grid\">\n";
						$html .= "  <!-- wp:column {\"className\":\"aips-pros-column\"} -->\n";
						$html .= "  <div class=\"wp-block-column aips-pros-column\">\n";
						$html .= "    <h4 class=\"aips-column-title aips-pros-title\"><span class=\"dashicons dashicons-yes-alt\"></span> " . esc_html__('Pros', 'ai-post-scheduler') . "</h4>\n";
						$html .= "    <ul class=\"aips-pros-list\">\n";

						foreach ($pros_list as $item) {
							$html .= "      <li>" . esc_html($item) . "</li>\n";
						}

						$html .= "    </ul>\n";
						$html .= "  </div>\n";
						$html .= "  <!-- /wp:column -->\n";

						$html .= "  <!-- wp:column {\"className\":\"aips-cons-column\"} -->\n";
						$html .= "  <div class=\"wp-block-column aips-cons-column\">\n";
						$html .= "    <h4 class=\"aips-column-title aips-cons-title\"><span class=\"dashicons dashicons-dismiss\"></span> " . esc_html__('Cons', 'ai-post-scheduler') . "</h4>\n";
						$html .= "    <ul class=\"aips-cons-list\">\n";

						foreach ($cons_list as $item) {
							$html .= "      <li>" . esc_html($item) . "</li>\n";
						}

						$html .= "    </ul>\n";
						$html .= "  </div>\n";
						$html .= "  <!-- /wp:column -->\n";
						$html .= "</div>\n<!-- /wp:columns -->\n";

						return $html;
					} else {
						$pros_attr = esc_attr(implode('|', $pros_list));
						$cons_attr = esc_attr(implode('|', $cons_list));

						return "\n[aips_pros_cons pros=\"{$pros_attr}\" cons=\"{$cons_attr}\"]\n";
					}
				},
				$content,
				1
			);

			// Clean up any orphan [CONS]...[/CONS] if already processed
			$content = preg_replace('/\[CONS\](.*?)\[\/CONS\]/is', '', $content);
		}

		// 4. Parse Step Lists: [STEP_LIST]...[STEP title="..."]...[/STEP][/STEP_LIST]
		$content = preg_replace_callback(
			'/\[STEP_LIST\](.*?)\[\/STEP_LIST\]/is',
			function($matches) use (&$extracted_data, $mode) {
				$inner = $matches[1];
				$steps = array();

				preg_match_all('/\[STEP(?:\s+title=["\'](.*?)["\'])?\s*\](.*?)\[\/STEP\]/is', $inner, $step_matches, PREG_SET_ORDER);

				$step_num = 1;
				foreach ($step_matches as $sm) {
					$step_title = !empty($sm[1]) ? trim($sm[1]) : '';
					$step_text  = trim($sm[2]);
					$steps[] = array(
						'step_number' => $step_num++,
						'title'       => $step_title,
						'text'        => $step_text,
						'description' => $step_text,
					);
				}

				if (empty($steps)) {
					// Fallback: parse numbered lines like "1. First Step: Do this initial step." or "1. Description"
					$lines = explode("\n", trim($inner));

					foreach ($lines as $line) {
						$line = trim($line);

						if ($line === '') {
							continue;
						}

						if (preg_match('/^(?:Step\s+)?\d+[\.:\)]\s*(?:[*_]{2})?([^:\n]+?)(?:[*_]{2})?:\s*(.*)$/i', $line, $lm)) {
							$steps[] = array(
								'step_number' => $step_num++,
								'title'       => trim($lm[1]),
								'text'        => trim($lm[2]),
								'description' => trim($lm[2]),
							);
						} elseif (preg_match('/^(?:Step\s+)?\d+[\.:\)]\s*(.*)$/i', $line, $lm)) {
							$steps[] = array(
								'step_number' => $step_num++,
								'title'       => sprintf(__('Step %d', 'ai-post-scheduler'), $step_num - 1),
								'text'        => trim($lm[1]),
								'description' => trim($lm[1]),
							);
						}
					}
				}

				$extracted_data['steps']     = $steps;
				$extracted_data['step_list'] = $steps;

				if ($mode === 'gutenberg') {
					$html = "\n<!-- wp:list {\"ordered\":true,\"className\":\"aips-step-list-block\"} -->\n";
					$html .= "<ol class=\"wp-block-list aips-step-list-block aips-step-list\">\n";

					foreach ($steps as $idx => $s) {
						$html .= "  <!-- wp:list-item -->\n";
						$html .= "  <li class=\"aips-step-item\">";
						if (!empty($s['title'])) {
							$html .= "<strong>" . esc_html($s['title']) . "</strong>: ";
						}
						$html .= esc_html($s['text']) . "</li>\n";
						$html .= "  <!-- /wp:list-item -->\n";
					}

					$html .= "</ol>\n<!-- /wp:list -->\n";

					return $html;
				} else {
					$shortcode_content = '';

					foreach ($steps as $s) {
						$shortcode_content .= '[aips_step title="' . esc_attr($s['title']) . '"]' . esc_html($s['text']) . '[/aips_step]';
					}

					return "\n[aips_step_list]{$shortcode_content}[/aips_step_list]\n";
				}
			},
			$content
		);

		// 5. Parse Callout Boxes: [CALLOUT type="..."]...[/CALLOUT]
		$content = preg_replace_callback(
			'/\[CALLOUT(?:\s+type=["\'](tip|warning|info|note)["\'])?\s*\](.*?)\[\/CALLOUT\]/is',
			function($matches) use ($mode) {
				$type = !empty($matches[1]) ? strtolower($matches[1]) : 'tip';
				$body = trim($matches[2]);

				if ($mode === 'gutenberg') {
					return "\n<!-- wp:group {\"className\":\"aips-callout aips-callout-{$type}\"} -->\n<div class=\"aips-callout aips-callout-{$type}\"><p>" . nl2br(esc_html($body)) . "</p></div>\n<!-- /wp:group -->\n";
				} else {
					return "\n[aips_callout type=\"{$type}\"]" . esc_html($body) . "[/aips_callout]\n";
				}
			},
			$content
		);

		// 6. Parse Comparison Tables: [TABLE]...[/TABLE]
		$content = preg_replace_callback(
			'/\[TABLE\](.*?)\[\/TABLE\]/is',
			function($matches) use ($mode) {
				$table_markdown = trim($matches[1]);
				$table_html = $this->render_markdown_table($table_markdown);

				if ($mode === 'gutenberg') {
					return "\n<!-- wp:table {\"className\":\"aips-comparison-table\"} -->\n<figure class=\"wp-block-table aips-comparison-table\">{$table_html}</figure>\n<!-- /wp:table -->\n";
				} else {
					return "\n[aips_table]{$table_html}[/aips_table]\n";
				}
			},
			$content
		);

		return array(
			'content'        => trim($content),
			'extracted_data' => $extracted_data,
		);
	}

	/**
	 * Detect whether the active theme or site environment uses the Block Editor.
	 *
	 * @return string 'gutenberg' or 'shortcode'.
	 */
	private function detect_editor_mode(): string {
		if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
			return 'gutenberg';
		}

		if (function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type('post')) {
			return 'gutenberg';
		}

		return 'gutenberg'; // Default modern WordPress standard
	}

	/**
	 * Simple helper to convert markdown table to clean HTML table.
	 *
	 * @param string $markdown Markdown table text.
	 * @return string HTML table string.
	 */
	private function render_markdown_table(string $markdown): string {
		$lines = array_filter(array_map('trim', explode("\n", $markdown)));

		if (empty($lines)) {
			return '<table></table>';
		}

		$html = '<table>';
		$is_header = true;

		foreach ($lines as $line) {
			if (preg_match('/^\|?\s*[-:]+[-| :]*\s*\|?$/', $line)) {
				// Separator line
				$is_header = false;

				continue;
			}

			$cells = array_values(array_filter(array_map('trim', explode('|', trim($line, '|'))), function($c) {
				return $c !== '';
			}));

			if (empty($cells)) {
				continue;
			}

			if ($is_header) {
				$html .= '<thead><tr>';

				foreach ($cells as $c) {
					$html .= '<th>' . esc_html($c) . '</th>';
				}

				$html .= '</tr></thead><tbody>';
				$is_header = false;
			} else {
				$html .= '<tr>';

				foreach ($cells as $c) {
					$html .= '<td>' . esc_html($c) . '</td>';
				}

				$html .= '</tr>';
			}
		}

		if (strpos($html, '<tbody>') !== false) {
			$html .= '</tbody>';
		}
		
		$html .= '</table>';

		return $html;
	}
}
