<?php
/**
 * Prompt Profile Diff Engine
 *
 * Pure, stateless, LCS-based (longest common subsequence) word-level diff
 * engine used to compare Prompt Profile version snapshots. Has no database
 * access and no external/Composer diff dependency.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Profile_Diff_Engine
 *
 * Produces structured segment diffs suitable for UI rendering as
 * green (add) / red (remove) / plain (equal) spans.
 */
class AIPS_Prompt_Profile_Diff_Engine {

	/**
	 * The 9 known stage field keys shared across Prompt Profiles.
	 *
	 * @var string[]
	 */
	const STAGE_KEYS = array(
		'title_prompt',
		'title_followup_prompt',
		'content_prompt',
		'excerpt_prompt',
		'excerpt_followup_prompt',
		'featured_image_prompt',
		'topic_ideas_prompt',
		'metadata_prompt',
		'taxonomy_prompt',
	);

	/**
	 * Diff two plain strings at word granularity.
	 *
	 * Splits on whitespace boundaries while preserving the original
	 * whitespace/punctuation in the output segments, and returns a
	 * structured, ordered array of segments:
	 *   [ 'type' => 'equal'|'add'|'remove', 'text' => '...' ]
	 *
	 * Adjacent tokens of the same type are merged into a single segment so
	 * the UI can render contiguous spans without extra bookkeeping.
	 *
	 * @param string $old_text Original/previous text.
	 * @param string $new_text New/current text.
	 * @return array<int, array{type:string, text:string}> Ordered diff segments.
	 */
	public function diff_strings($old_text, $new_text) {
		$old_text = (string) $old_text;
		$new_text = (string) $new_text;

		if ($old_text === $new_text) {
			if ($old_text === '') {
				return array();
			}
			return array(array('type' => 'equal', 'text' => $old_text));
		}

		$old_tokens = $this->tokenize($old_text);
		$new_tokens = $this->tokenize($new_text);

		$ops = $this->lcs_diff($old_tokens, $new_tokens);

		return $this->merge_segments($ops);
	}

	/**
	 * Diff two full version "stages" snapshots (arrays keyed by the 9 stage
	 * field names).
	 *
	 * For each stage key, returns whether the stage changed and, if so, the
	 * segment diff for that stage. Unchanged stages are included in compact
	 * form (changed => false, no segments) rather than omitted, so callers
	 * can render a complete, stable stage list.
	 *
	 * @param array $old_stages Associative array of stage_key => prompt text (previous state).
	 * @param array $new_stages Associative array of stage_key => prompt text (new state).
	 * @return array<string, array{changed:bool, segments:array}> Keyed by stage key.
	 */
	public function diff_stages(array $old_stages, array $new_stages) {
		$result = array();

		foreach (self::STAGE_KEYS as $stage_key) {
			$old_value = isset($old_stages[$stage_key]) ? (string) $old_stages[$stage_key] : '';
			$new_value = isset($new_stages[$stage_key]) ? (string) $new_stages[$stage_key] : '';

			if ($old_value === $new_value) {
				$result[$stage_key] = array(
					'changed'  => false,
					'segments' => array(),
				);
				continue;
			}

			$result[$stage_key] = array(
				'changed'  => true,
				'segments' => $this->diff_strings($old_value, $new_value),
			);
		}

		return $result;
	}

	/**
	 * Return only the stage keys that changed between two stage snapshots.
	 *
	 * Convenience helper for building human-readable change summaries
	 * (e.g. "Changed: Post Title, Content Shell").
	 *
	 * @param array $old_stages Previous stage snapshot.
	 * @param array $new_stages New stage snapshot.
	 * @return string[] List of changed stage keys, in STAGE_KEYS order.
	 */
	public function get_changed_stage_keys(array $old_stages, array $new_stages) {
		$changed = array();

		foreach (self::STAGE_KEYS as $stage_key) {
			$old_value = isset($old_stages[$stage_key]) ? (string) $old_stages[$stage_key] : '';
			$new_value = isset($new_stages[$stage_key]) ? (string) $new_stages[$stage_key] : '';

			if ($old_value !== $new_value) {
				$changed[] = $stage_key;
			}
		}

		return $changed;
	}

	/**
	 * Split text into word-level tokens, preserving whitespace runs as their
	 * own tokens so segments can be reassembled byte-for-byte.
	 *
	 * @param string $text Raw text.
	 * @return string[] Ordered list of tokens (non-whitespace and whitespace runs).
	 */
	private function tokenize($text) {
		if ($text === '') {
			return array();
		}

		$pieces = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		if (!is_array($pieces)) {
			return array($text);
		}

		$tokens = array();
		foreach ($pieces as $piece) {
			if ($piece !== '') {
				$tokens[] = $piece;
			}
		}

		return $tokens;
	}

	/**
	 * Compute a word-level LCS diff between two token lists.
	 *
	 * Standard O(n*m) dynamic-programming LCS table followed by a backtrack
	 * that emits an ordered list of 'equal' | 'add' | 'remove' token ops.
	 *
	 * @param string[] $old_tokens Tokens from the original text.
	 * @param string[] $new_tokens Tokens from the new text.
	 * @return array<int, array{type:string, text:string}> Ordered per-token ops.
	 */
	private function lcs_diff(array $old_tokens, array $new_tokens) {
		$n = count($old_tokens);
		$m = count($new_tokens);

		// dp[i][j] = length of the LCS of old_tokens[i..n) and new_tokens[j..m).
		$dp = array_fill(0, $n + 1, null);
		for ($i = 0; $i <= $n; $i++) {
			$dp[$i] = array_fill(0, $m + 1, 0);
		}

		for ($i = $n - 1; $i >= 0; $i--) {
			for ($j = $m - 1; $j >= 0; $j--) {
				if ($old_tokens[$i] === $new_tokens[$j]) {
					$dp[$i][$j] = $dp[$i + 1][$j + 1] + 1;
				} else {
					$dp[$i][$j] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
				}
			}
		}

		$ops = array();
		$i = 0;
		$j = 0;

		while ($i < $n && $j < $m) {
			if ($old_tokens[$i] === $new_tokens[$j]) {
				$ops[] = array('type' => 'equal', 'text' => $old_tokens[$i]);
				$i++;
				$j++;
			} elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
				$ops[] = array('type' => 'remove', 'text' => $old_tokens[$i]);
				$i++;
			} else {
				$ops[] = array('type' => 'add', 'text' => $new_tokens[$j]);
				$j++;
			}
		}

		while ($i < $n) {
			$ops[] = array('type' => 'remove', 'text' => $old_tokens[$i]);
			$i++;
		}

		while ($j < $m) {
			$ops[] = array('type' => 'add', 'text' => $new_tokens[$j]);
			$j++;
		}

		return $ops;
	}

	/**
	 * Merge consecutive per-token ops of the same type into single segments.
	 *
	 * @param array<int, array{type:string, text:string}> $ops Per-token ops from lcs_diff().
	 * @return array<int, array{type:string, text:string}> Merged segments.
	 */
	private function merge_segments(array $ops) {
		$segments = array();

		foreach ($ops as $op) {
			$last_index = count($segments) - 1;
			if ($last_index >= 0 && $segments[$last_index]['type'] === $op['type']) {
				$segments[$last_index]['text'] .= $op['text'];
			} else {
				$segments[] = $op;
			}
		}

		return $segments;
	}
}
