<?php
/**
 * Stress Test: Ability cases
 *
 * The `abilities` group of Diagnostics > Stress Test. These cases exercise the
 * WordPress Abilities integration (adapter, catalog, approval gate) against the
 * abilities actually registered on this site, without creating any data and
 * without needing an AI provider configured in AIPS (the abilities bring their
 * own).
 *
 * AIPS_Stress_Test_Service owns the page, the runner and the result envelope;
 * this class only supplies case definitions and runs a case, returning the same
 * result fragment the other cases return (status, summary, ai_value,
 * plugin_value, error) plus `ability_calls`.
 *
 * Cases that need an ability that is not installed pass with a "Skipped"
 * summary rather than failing: a missing optional ability is not a defect.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.21
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Stress_Test_Ability_Cases {

	const GROUP = 'abilities';

	/**
	 * Calls made by the core invocation case.
	 */
	const CORE_INVOCATIONS = 20;

	const CORE_SITE_INFO        = 'core/get-site-info';
	const CORE_ENVIRONMENT_INFO = 'core/get-environment-info';
	const AI_TITLE              = 'ai/title-generation';
	const AI_SUMMARIZATION      = 'ai/summarization';

	/**
	 * Article the real AI abilities are asked about.
	 */
	const SAMPLE_ARTICLE = 'Repairing a flat bicycle tyre takes four steps: remove the wheel, find the puncture, patch the inner tube, and refit the tyre without pinching the tube. Carry a spare tube and a small pump so a flat never ends your ride.';

	/**
	 * @var AIPS_Ability_Service
	 */
	private $abilities;

	/**
	 * @var AIPS_Ability_Catalog_Service
	 */
	private $catalog;

	/**
	 * @param AIPS_Ability_Service|null         $abilities Ability adapter (tests).
	 * @param AIPS_Ability_Catalog_Service|null $catalog   Ability catalog (tests).
	 */
	public function __construct(?AIPS_Ability_Service $abilities = null, ?AIPS_Ability_Catalog_Service $catalog = null) {
		$this->abilities = $abilities ?: new AIPS_Ability_Service();
		$this->catalog   = $catalog ?: new AIPS_Ability_Catalog_Service($this->abilities);
	}

	// -----------------------------------------------------------------------
	// Definitions
	// -----------------------------------------------------------------------

	/**
	 * Whether any ability provider is usable here, so the group is worth showing.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return !is_wp_error($this->abilities->list_available());
	}

	/**
	 * Case definitions for the stress test catalogue.
	 *
	 * Empty when no ability provider is available. `needs_ai` is false because
	 * these cases never use AIPS's own AI provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_definitions(): array {
		if (!$this->is_available()) {
			return array();
		}

		return array(
			array(
				'id'          => 'ability_discovery',
				'label'       => __('Abilities: Discovery', 'ai-post-scheduler'),
				'description' => __('Lists every registered ability through the adapter and catalog, and reports counts by provider and category, how many need approval, and how long discovery took.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => false,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'ability_invoke_core',
				'label'       => __('Abilities: Invoke Core Abilities', 'ai-post-scheduler'),
				'description' => sprintf(
					/* translators: 1: number of calls, 2: ability slug */
					__('Calls %2$s %1$d times to measure latency and error rate, then reads the environment info the current user is allowed to see. Read-only; no AI.', 'ai-post-scheduler'),
					self::CORE_INVOCATIONS,
					self::CORE_SITE_INFO
				),
				'group'       => self::GROUP,
				'creates'     => false,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'ability_invoke_ai',
				'label'       => __('Abilities: Invoke AI Abilities', 'ai-post-scheduler'),
				'description' => __('Makes one real call each to ai/title-generation and ai/summarization (when the WordPress AI plugin provides them) and checks the answer against the ability\'s output schema. Uses the provider configured for the WordPress AI plugin.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => false,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'ability_destructive_gate',
				'label'       => __('Abilities: Destructive-Ability Gate', 'ai-post-scheduler'),
				'description' => __('Runs read-only, non-destructive, unannotated and destructive stand-in abilities through the approval gate with and without "Allow destructive abilities" and checks each is allowed or blocked as designed. Nothing is invoked and no workflow is created.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => false,
				'needs_ai'    => false,
			),
		);
	}

	/**
	 * Whether a case id belongs to this group.
	 *
	 * @param string $case_id Case identifier.
	 * @return bool
	 */
	public function has_case(string $case_id): bool {
		foreach ($this->get_definitions() as $definition) {
			if ($definition['id'] === $case_id) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Run one case.
	 *
	 * @param string $case_id Case identifier.
	 * @return array<string,mixed> Result fragment; includes `ability_calls`.
	 */
	public function run(string $case_id): array {
		$calls_before = AIPS_Ability_Service::get_invocation_count();

		switch ($case_id) {
			case 'ability_discovery':
				$result = $this->case_discovery();
				break;
			case 'ability_invoke_core':
				$result = $this->case_invoke_core();
				break;
			case 'ability_invoke_ai':
				$result = $this->case_invoke_ai();
				break;
			case 'ability_destructive_gate':
				$result = $this->case_destructive_gate();
				break;
			default:
				$result = $this->failed(__('Case is not implemented.', 'ai-post-scheduler'));
		}

		$result['ability_calls'] = max(0, AIPS_Ability_Service::get_invocation_count() - $calls_before);

		return $result;
	}

	// -----------------------------------------------------------------------
	// Cases
	// -----------------------------------------------------------------------

	/**
	 * Discovery: what is registered, and how it is classified.
	 *
	 * @return array<string,mixed>
	 */
	private function case_discovery(): array {
		$started   = microtime(true);
		$abilities = $this->catalog->list_abilities();
		$ms        = (microtime(true) - $started) * 1000;

		if (is_wp_error($abilities)) {
			return $this->failed($abilities->get_error_message());
		}

		if (empty($abilities)) {
			return $this->failed(__('No abilities are registered on this site.', 'ai-post-scheduler'));
		}

		$by_provider = array();
		$by_category = array();
		$approval    = 0;
		$unannotated = 0;
		$unavailable = 0;
		$listing     = array();

		foreach ($abilities as $ability) {
			$by_provider[$ability['provider']] = ($by_provider[$ability['provider']] ?? 0) + 1;
			$by_category[$ability['category']] = ($by_category[$ability['category']] ?? 0) + 1;

			if (!empty($ability['requires_approval'])) {
				$approval++;
			}

			if (null === $ability['destructive_state']) {
				$unannotated++;
			}

			if (false === $ability['is_available']) {
				$unavailable++;
			}

			$listing[] = array(
				'name'              => $ability['name'],
				'provider'          => $ability['provider'],
				'category'          => $ability['category'],
				'requires_approval' => !empty($ability['requires_approval']),
				'has_input_schema'  => !empty($ability['input_schema']),
			);
		}

		ksort($by_provider);
		ksort($by_category);

		return array(
			'status'       => 'passed',
			'summary'      => sprintf(
				/* translators: 1: ability count, 2: provider count, 3: category count, 4: approval count, 5: milliseconds */
				__('%1$d abilities from %2$d provider(s) in %3$d categories; %4$d need approval. Discovery took %5$d ms.', 'ai-post-scheduler'),
				count($abilities),
				count($by_provider),
				count($by_category),
				$approval,
				(int) round($ms)
			),
			'ai_value'     => $listing,
			'plugin_value' => array(
				'abilities'         => count($abilities),
				'by_provider'       => $by_provider,
				'by_category'       => $by_category,
				'require_approval'  => $approval,
				'no_annotation'     => $unannotated,
				'unavailable'       => $unavailable,
				'discovery_ms'      => (int) round($ms),
			),
		);
	}

	/**
	 * Invoke core abilities repeatedly and report latency.
	 *
	 * @return array<string,mixed>
	 */
	private function case_invoke_core(): array {
		if (true !== $this->abilities->is_available(self::CORE_SITE_INFO)) {
			return $this->skipped(sprintf(
				/* translators: %s: ability slug */
				__('%s is not registered on this site.', 'ai-post-scheduler'),
				self::CORE_SITE_INFO
			));
		}

		$timings = array();
		$errors  = array();
		$last    = null;

		for ($i = 0; $i < self::CORE_INVOCATIONS; $i++) {
			$started = microtime(true);
			$result  = $this->abilities->invoke(self::CORE_SITE_INFO, array());
			$timings[] = (microtime(true) - $started) * 1000;

			if (is_wp_error($result)) {
				$errors[$result->get_error_code()] = ($errors[$result->get_error_code()] ?? 0) + 1;
			} else {
				$last = $result;
			}
		}

		$environment = null;

		if (true === $this->abilities->is_available(self::CORE_ENVIRONMENT_INFO)) {
			$env = $this->abilities->invoke(self::CORE_ENVIRONMENT_INFO, array());
			$environment = is_wp_error($env)
				? array('error' => $env->get_error_code())
				: array('ok' => true, 'fields' => array_keys($env));
		}

		sort($timings);
		$stats = array(
			'calls'  => count($timings),
			'errors' => array_sum($errors),
			'min_ms' => round($timings[0], 2),
			'p50_ms' => round($this->percentile($timings, 50), 2),
			'p95_ms' => round($this->percentile($timings, 95), 2),
			'max_ms' => round(end($timings), 2),
		);

		$plugin_value = array(
			'latency'     => $stats,
			'error_codes' => $errors,
			'environment' => $environment,
		);

		if (!empty($errors)) {
			return array(
				'status'       => 'failed',
				'error'        => sprintf(
					/* translators: 1: failed calls, 2: total calls */
					__('%1$d of %2$d calls failed.', 'ai-post-scheduler'),
					array_sum($errors),
					count($timings)
				),
				'summary'      => sprintf(
					/* translators: 1: failed calls, 2: total calls */
					__('%1$d of %2$d calls failed.', 'ai-post-scheduler'),
					array_sum($errors),
					count($timings)
				),
				'ai_value'     => $last,
				'plugin_value' => $plugin_value,
			);
		}

		return array(
			'status'       => 'passed',
			'summary'      => sprintf(
				/* translators: 1: calls, 2: median ms, 3: 95th percentile ms */
				__('%1$d calls, no errors. Median %2$s ms, 95th percentile %3$s ms.', 'ai-post-scheduler'),
				count($timings),
				$stats['p50_ms'],
				$stats['p95_ms']
			),
			'ai_value'     => $last,
			'plugin_value' => $plugin_value,
		);
	}

	/**
	 * Call the real AI abilities, when installed.
	 *
	 * @return array<string,mixed>
	 */
	private function case_invoke_ai(): array {
		$targets = array(
			self::AI_TITLE         => array('content' => self::SAMPLE_ARTICLE),
			self::AI_SUMMARIZATION => array('content' => self::SAMPLE_ARTICLE),
		);

		$outcomes = array();
		$outputs  = array();
		$failed   = array();

		foreach ($targets as $slug => $payload) {
			if (true !== $this->abilities->is_available($slug)) {
				$outcomes[$slug] = array('status' => 'not_installed');
				continue;
			}

			$started = microtime(true);
			$result  = $this->abilities->invoke($slug, $payload);
			$ms      = (int) round((microtime(true) - $started) * 1000);

			if (is_wp_error($result)) {
				$outcomes[$slug] = array('status' => 'error', 'code' => $result->get_error_code(), 'ms' => $ms);
				$failed[]        = $slug . ': ' . $result->get_error_message();
				continue;
			}

			$missing = $this->missing_output_keys($slug, $result);
			$outputs[$slug]  = $result;
			$outcomes[$slug] = array('status' => empty($missing) ? 'ok' : 'unexpected_shape', 'ms' => $ms, 'missing' => $missing);

			if (!empty($missing)) {
				$failed[] = sprintf(
					/* translators: 1: ability slug, 2: missing keys */
					__('%1$s answered without the declared output field(s): %2$s', 'ai-post-scheduler'),
					$slug,
					implode(', ', $missing)
				);
			}
		}

		$ran = count(array_filter($outcomes, function ($outcome) {
			return $outcome['status'] !== 'not_installed';
		}));

		if (!empty($failed)) {
			return array(
				'status'       => 'failed',
				'error'        => implode(' | ', $failed),
				'summary'      => implode(' | ', $failed),
				'ai_value'     => $outputs,
				'plugin_value' => $outcomes,
			);
		}

		if (0 === $ran) {
			return $this->skipped(__('Neither ai/title-generation nor ai/summarization is registered (install and enable the WordPress AI plugin).', 'ai-post-scheduler'), $outcomes);
		}

		return array(
			'status'       => 'passed',
			'summary'      => sprintf(
				/* translators: %d: number of AI abilities called */
				_n('%d AI ability answered with its declared output.', '%d AI abilities answered with their declared output.', $ran, 'ai-post-scheduler'),
				$ran
			),
			'ai_value'     => $outputs,
			'plugin_value' => $outcomes,
		);
	}

	/**
	 * The approval gate against stand-in abilities of every annotation shape.
	 *
	 * Uses the same normalization and gate method the workflow executor uses,
	 * but never invokes anything: the stand-ins only describe themselves.
	 *
	 * @return array<string,mixed>
	 */
	private function case_destructive_gate(): array {
		$stand_ins = array(
			'readonly'        => array('annotations' => array('readonly' => true), 'expect_blocked' => false, 'declared_destructive' => false),
			'non_destructive' => array('annotations' => array('destructive' => false), 'expect_blocked' => false, 'declared_destructive' => false),
			'unannotated'     => array('annotations' => array(), 'expect_blocked' => true, 'declared_destructive' => false),
			'destructive'     => array('annotations' => array('destructive' => true), 'expect_blocked' => true, 'declared_destructive' => true),
		);

		$executor = new AIPS_Ability_Workflow_Executor(null, $this->catalog, $this->abilities, null, null, null, null, '__return_true');
		$rows     = array();
		$wrong    = array();

		// The setting combinations: nothing granted, "Allow destructive abilities", and
		// the ability listed in the workflow's approved abilities.
		$settings = array(
			'none'     => array('allow' => false, 'approved' => false),
			'allow'    => array('allow' => true, 'approved' => false),
			'approved' => array('allow' => false, 'approved' => true),
		);

		foreach ($stand_ins as $name => $stand_in) {
			$ability = $this->catalog->normalize_ability(array('slug' => 'stress/' . $name, 'annotations' => $stand_in['annotations']));

			foreach ($settings as $setting => $grant) {
				$error   = $executor->get_approval_error($ability, $grant['allow'], $grant['approved'] ? array('stress/' . $name) : array());
				$blocked = null !== $error;
				// Approving covers an unannotated ability, never one declared destructive.
				$covered = $grant['allow'] || ($grant['approved'] && !$stand_in['declared_destructive']);
				$expect  = $stand_in['expect_blocked'] && !$covered;

				$rows[] = array(
					'ability'                     => $name,
					'setting'                     => $setting,
					'allow_destructive_abilities' => $grant['allow'],
					'approved'                    => $grant['approved'],
					'blocked'                     => $blocked,
					'expected_blocked'            => $expect,
					'error_code'                  => $error ? $error->get_error_code() : null,
				);

				if ($blocked !== $expect) {
					$wrong[] = sprintf('%s (%s)', $name, $setting);
				}
			}
		}

		if (!empty($wrong)) {
			return array(
				'status'       => 'failed',
				'error'        => sprintf(
					/* translators: %s: list of combinations */
					__('The approval gate gave the wrong answer for: %s', 'ai-post-scheduler'),
					implode(', ', $wrong)
				),
				'summary'      => __('The approval gate does not match its design.', 'ai-post-scheduler'),
				'ai_value'     => null,
				'plugin_value' => $rows,
			);
		}

		return array(
			'status'       => 'passed',
			'summary'      => sprintf(
				/* translators: %d: number of combinations */
				__('All %d ability/setting combinations were allowed or blocked as designed.', 'ai-post-scheduler'),
				count($rows)
			),
			'ai_value'     => null,
			'plugin_value' => $rows,
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Output properties an ability declares that its answer lacks.
	 *
	 * @param string $slug   Ability slug.
	 * @param mixed  $result Ability output.
	 * @return string[]
	 */
	private function missing_output_keys(string $slug, $result): array {
		$schema = $this->catalog->get_output_schema($slug);

		if (is_wp_error($schema) || empty($schema['properties']) || !is_array($result)) {
			return array();
		}

		return array_values(array_diff(array_keys($schema['properties']), array_keys($result)));
	}

	/**
	 * Nearest-rank percentile of an ascending list.
	 *
	 * @param float[] $sorted     Ascending values.
	 * @param int     $percentile 1-100.
	 * @return float
	 */
	private function percentile(array $sorted, int $percentile): float {
		$index = (int) ceil(($percentile / 100) * count($sorted)) - 1;

		return (float) $sorted[max(0, min(count($sorted) - 1, $index))];
	}

	/**
	 * @param string $message Failure message.
	 * @return array<string,mixed>
	 */
	private function failed(string $message): array {
		return array(
			'status'       => 'failed',
			'error'        => $message,
			'summary'      => $message,
			'ai_value'     => null,
			'plugin_value' => null,
		);
	}

	/**
	 * A case that could not run because an optional ability is missing. It
	 * passes (nothing is broken) and says so.
	 *
	 * @param string     $reason  Why it was skipped.
	 * @param mixed|null $details Optional details.
	 * @return array<string,mixed>
	 */
	private function skipped(string $reason, $details = null): array {
		return array(
			'status'       => 'passed',
			/* translators: %s: reason */
			'summary'      => sprintf(__('Skipped: %s', 'ai-post-scheduler'), $reason),
			'ai_value'     => null,
			'plugin_value' => $details,
			'artifacts'    => array('skipped' => true),
		);
	}
}
