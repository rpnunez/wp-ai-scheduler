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
	 * Name prefix of the workflows the workflow cases create. Cleanup finds
	 * leftovers by it.
	 */
	const WORKFLOW_PREFIX = '[aips-stress] ';

	/**
	 * Most slices a case may spend driving one run before it gives up.
	 */
	const MAX_SLICES = 40;

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
			array(
				'id'          => 'workflow_two_step',
				'label'       => __('Abilities: Two-Step Workflow', 'ai-post-scheduler'),
				'description' => __('Creates a temporary two-step workflow from fixture abilities, where the second step reads a number and a text from the first, runs it through the workflow executor, and checks both steps completed with the values (and their types) passed on, and that exactly one History record was written and closed. The workflow, its runs and the History record are removed afterwards. Needs Ability Workflows to be enabled.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => true,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'workflow_retry_backoff',
				'label'       => __('Abilities: Workflow Retry and Backoff', 'ai-post-scheduler'),
				'description' => __('Runs a workflow whose step fails once with a transient error and has one retry with a one second backoff. Checks the step is parked, is not retried before it is due, is retried once when due, and the run completes with one History record. Takes about two seconds. Needs Ability Workflows to be enabled.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => true,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'workflow_cancel',
				'label'       => __('Abilities: Workflow Cancel', 'ai-post-scheduler'),
				'description' => __('Runs a three-step workflow and cancels it while the first step is running. Checks the later steps never run, the run is cancelled and its History record is closed as terminated. Needs Ability Workflows to be enabled.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => true,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'lock_and_takeover',
				'label'       => __('Abilities: Run Locks', 'ai-post-scheduler'),
				'description' => __('Checks the atomic lock used to keep two workers off the same workflow run: a second worker is refused, a stranger cannot refresh or release it, and an expired lock is taken over by exactly one new owner while the old owner loses. Uses its own lock key and takes about two seconds.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => false,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'workflow_concurrency',
				'label'       => __('Abilities: Workflow Concurrency', 'ai-post-scheduler'),
				'description' => __('Queues ten runs of a temporary workflow and processes them while another worker holds one run lock. Checks the locked run is reported busy and its step does not run, then finishes once released, every run completes, and no run has a duplicate step run. Needs Ability Workflows to be enabled.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => true,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'enrichment_queue',
				'label'       => __('Abilities: Enrichment Queue', 'ai-post-scheduler'),
				'description' => __('Creates five private posts with featured images, queues them, enriches each once with a fixture alt-text ability, and checks every post got alt text, left the queue, and the current user was restored. Only its own posts are touched, never posts waiting on the real queue. Delete Data removes the posts.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => true,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'process_soak',
				'label'       => __('Abilities: Workflow and Enrichment Soak', 'ai-post-scheduler'),
				'description' => __('Runs 100 workflow runs and 50 post enrichments with fixture abilities, one after another through the executor and the enrichment service, and checks every run completed, every ability call is accounted for, nothing is left queued, and the user is restored. Reports throughput. Can take a minute. Needs Ability Workflows to be enabled. Delete Data removes the posts.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => true,
				'needs_ai'    => false,
			),
			array(
				'id'          => 'large_payload',
				'label'       => __('Abilities: Large Payload', 'ai-post-scheduler'),
				'description' => __('A step returns about 1 MB. Checks the stored output is shortened below the cap with the large value replaced by a marker and the small fields kept, and that the next step can still read a small field. Needs Ability Workflows to be enabled.', 'ai-post-scheduler'),
				'group'       => self::GROUP,
				'creates'     => true,
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
			case 'workflow_two_step':
				$result = $this->case_workflow_two_step();
				break;
			case 'workflow_retry_backoff':
				$result = $this->case_workflow_retry_backoff();
				break;
			case 'workflow_cancel':
				$result = $this->case_workflow_cancel();
				break;
			case 'lock_and_takeover':
				$result = $this->case_lock_and_takeover();
				break;
			case 'workflow_concurrency':
				$result = $this->case_workflow_concurrency();
				break;
			case 'enrichment_queue':
				$result = $this->case_enrichment_queue();
				break;
			case 'process_soak':
				$result = $this->case_process_soak();
				break;
			case 'large_payload':
				$result = $this->case_large_payload();
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
	// Workflow cases
	// -----------------------------------------------------------------------

	/**
	 * Two steps, the second reading the first's output with its types intact.
	 *
	 * @return array<string,mixed>
	 */
	private function case_workflow_two_step(): array {
		$skip = $this->workflow_skip();
		if (null !== $skip) {
			return $skip;
		}

		$abilities = array(
			'aips-stress/source' => function () {
				return array('name' => 'Stress Site', 'count' => 3);
			},
			'aips-stress/echo'   => function ($payload) {
				return array('received' => $payload);
			},
		);

		return $this->with_workflow('workflow_two_step', $abilities, array(
			array('step_key' => 'source', 'ability_name' => 'aips-stress/source'),
			array(
				'step_key'     => 'echo',
				'ability_name' => 'aips-stress/echo',
				'depends_on'   => array('source'),
				'input_map'    => array(
					'name'  => '{{steps.source.output.name}}',
					'count' => '{{steps.source.output.count}}',
				),
			),
		), function (AIPS_Ability_Workflow_Executor $executor, int $workflow_id): array {
			$started = microtime(true);
			$run_id  = $executor->dispatch_run($workflow_id, array('type' => 'stress_test'));

			if (is_wp_error($run_id)) {
				return $this->failed($run_id->get_error_message());
			}

			$drive = $this->drive($executor, (int) $run_id);
			$ms    = (microtime(true) - $started) * 1000;
			$run   = $this->repository()->get_run((int) $run_id);
			$steps = $this->step_runs_by_key((int) $run_id);

			$problems = array();
			if ('finished' !== $drive['state'] || !$run || 'completed' !== $run->status) {
				$problems[] = sprintf(
					/* translators: %s: run status */
					__('The run ended as "%s", not completed.', 'ai-post-scheduler'),
					$run ? $run->status : 'missing'
				);
			}

			foreach (array('source', 'echo') as $key) {
				if (!isset($steps[$key]) || 'completed' !== $steps[$key]->status) {
					/* translators: %s: step key */
					$problems[] = sprintf(__('Step "%s" did not complete.', 'ai-post-scheduler'), $key);
				}
			}

			$received = isset($steps['echo']) && is_array($steps['echo']->output_snapshot) && isset($steps['echo']->output_snapshot['received'])
				? $steps['echo']->output_snapshot['received']
				: array();

			if (!isset($received['count']) || 3 !== $received['count']) {
				$problems[] = __('The number from step one was not passed on as a number.', 'ai-post-scheduler');
			}

			if (!isset($received['name']) || 'Stress Site' !== $received['name']) {
				$problems[] = __('The text from step one was not passed on.', 'ai-post-scheduler');
			}

			$history = $this->history_status($run);
			if ('completed' !== $history['status']) {
				/* translators: %s: History status */
				$problems[] = sprintf(__('The History record is "%s", not completed.', 'ai-post-scheduler'), $history['status']);
			}

			$metrics = array(
				'run_status'  => $run ? $run->status : null,
				'slices'      => $drive['slices'],
				'duration_ms' => round($ms, 1),
				'history'     => $history,
				'received'    => $received,
			);

			if (!empty($problems)) {
				return $this->failed_with($problems, $metrics);
			}

			return array(
				'status'       => 'passed',
				'summary'      => sprintf(
					/* translators: 1: milliseconds, 2: slices */
					__('Both steps completed in %1$d ms over %2$d slice(s); values and types were passed on; one History record.', 'ai-post-scheduler'),
					(int) round($ms),
					$drive['slices']
				),
				'ai_value'     => null,
				'plugin_value' => $metrics,
			);
		});
	}

	/**
	 * A transient failure that is parked, left alone until due, then retried.
	 *
	 * @return array<string,mixed>
	 */
	private function case_workflow_retry_backoff(): array {
		$skip = $this->workflow_skip();
		if (null !== $skip) {
			return $skip;
		}

		$attempts  = 0;
		$abilities = array(
			'aips-stress/flaky' => function () use (&$attempts) {
				$attempts++;

				if (1 === $attempts) {
					return new WP_Error('aips_stress_transient', 'Simulated transient failure.');
				}

				return array('ok' => true);
			},
		);

		return $this->with_workflow('workflow_retry_backoff', $abilities, array(
			array(
				'step_key'     => 'flaky',
				'ability_name' => 'aips-stress/flaky',
				'retry_policy' => array('attempts' => 1, 'backoff_seconds' => 1),
			),
		), function (AIPS_Ability_Workflow_Executor $executor, int $workflow_id) use (&$attempts): array {
			$run_id = $executor->dispatch_run($workflow_id, array('type' => 'stress_test'));

			if (is_wp_error($run_id)) {
				return $this->failed($run_id->get_error_message());
			}

			$run_id   = (int) $run_id;
			$problems = array();

			$first = $executor->process_run($run_id);
			$step  = $this->step_runs_by_key($run_id)['flaky'] ?? null;

			if (1 !== $attempts) {
				$problems[] = __('The first slice should make exactly one attempt.', 'ai-post-scheduler');
			}

			if (!$step || 'pending' !== $step->status || empty($step->error['next_retry_at'])) {
				$problems[] = __('The failed step was not parked for a retry.', 'ai-post-scheduler');
			}

			if ('continue' !== $first['state'] || (int) $first['retry_at'] < time()) {
				$problems[] = __('The executor did not report when the retry is due.', 'ai-post-scheduler');
			}

			// Asking again straight away must not retry early.
			$executor->process_run($run_id);
			if (1 !== $attempts) {
				$problems[] = __('The step was retried before its backoff elapsed.', 'ai-post-scheduler');
			}

			$drive = $this->drive($executor, $run_id);
			$run   = $this->repository()->get_run($run_id);
			$step  = $this->step_runs_by_key($run_id)['flaky'] ?? null;

			if (2 !== $attempts) {
				$problems[] = sprintf(
					/* translators: %d: attempts */
					__('Expected two attempts in total, saw %d.', 'ai-post-scheduler'),
					$attempts
				);
			}

			if (!$run || 'completed' !== $run->status || !$step || 'completed' !== $step->status) {
				$problems[] = __('The run did not complete after the retry.', 'ai-post-scheduler');
			}

			$history = $this->history_status($run);
			if ('completed' !== $history['status']) {
				/* translators: %s: History status */
				$problems[] = sprintf(__('The History record is "%s", not completed.', 'ai-post-scheduler'), $history['status']);
			}

			$metrics = array(
				'attempts'   => $attempts,
				'run_status' => $run ? $run->status : null,
				'slices'     => $drive['slices'] + 2,
				'history'    => $history,
			);

			if (!empty($problems)) {
				return $this->failed_with($problems, $metrics);
			}

			return array(
				'status'       => 'passed',
				'summary'      => __('The failed step was parked, not retried early, retried once when due, and the run completed with one History record.', 'ai-post-scheduler'),
				'ai_value'     => null,
				'plugin_value' => $metrics,
			);
		});
	}

	/**
	 * A run cancelled while its first step is running.
	 *
	 * @return array<string,mixed>
	 */
	private function case_workflow_cancel(): array {
		$skip = $this->workflow_skip();
		if (null !== $skip) {
			return $skip;
		}

		$later_calls = 0;
		$cancel      = null;
		$abilities   = array(
			'aips-stress/cancel_now' => function () use (&$cancel) {
				if (is_callable($cancel)) {
					call_user_func($cancel);
				}

				return array('ok' => true);
			},
			'aips-stress/later'      => function () use (&$later_calls) {
				$later_calls++;

				return array('ok' => true);
			},
		);

		return $this->with_workflow('workflow_cancel', $abilities, array(
			array('step_key' => 'first', 'ability_name' => 'aips-stress/cancel_now'),
			array('step_key' => 'second', 'ability_name' => 'aips-stress/later', 'depends_on' => array('first')),
			array('step_key' => 'third', 'ability_name' => 'aips-stress/later', 'depends_on' => array('second')),
		), function (AIPS_Ability_Workflow_Executor $executor, int $workflow_id) use (&$later_calls, &$cancel): array {
			$run_id = $executor->dispatch_run($workflow_id, array('type' => 'stress_test'));

			if (is_wp_error($run_id)) {
				return $this->failed($run_id->get_error_message());
			}

			$run_id = (int) $run_id;
			$cancel = function () use ($executor, $run_id) {
				$executor->cancel_run($run_id);
			};

			$executor->process_run($run_id);

			$run      = $this->repository()->get_run($run_id);
			$steps    = $this->step_runs_by_key($run_id);
			$history  = $this->history_status($run);
			$problems = array();

			if (!$run || 'cancelled' !== $run->status) {
				$problems[] = sprintf(
					/* translators: %s: run status */
					__('The run ended as "%s", not cancelled.', 'ai-post-scheduler'),
					$run ? $run->status : 'missing'
				);
			}

			if (0 !== $later_calls) {
				$problems[] = sprintf(
					/* translators: %d: number of calls */
					__('%d step(s) ran after the cancel.', 'ai-post-scheduler'),
					$later_calls
				);
			}

			if (isset($steps['second']) || isset($steps['third'])) {
				$problems[] = __('Steps after the cancel left step runs behind.', 'ai-post-scheduler');
			}

			if ('terminated' !== $history['status']) {
				/* translators: %s: History status */
				$problems[] = sprintf(__('The History record is "%s", not terminated.', 'ai-post-scheduler'), $history['status']);
			}

			$metrics = array(
				'run_status'  => $run ? $run->status : null,
				'step_runs'   => array_keys($steps),
				'later_calls' => $later_calls,
				'history'     => $history,
			);

			if (!empty($problems)) {
				return $this->failed_with($problems, $metrics);
			}

			return array(
				'status'       => 'passed',
				'summary'      => __('The run was cancelled during the first step; the later steps never ran and the History record was closed as terminated.', 'ai-post-scheduler'),
				'ai_value'     => null,
				'plugin_value' => $metrics,
			);
		});
	}

	// -----------------------------------------------------------------------
	// Slice C and D cases: locks, concurrency, enrichment, soak, large payload
	// -----------------------------------------------------------------------

	/**
	 * Lock contention, wrong-owner release, and takeover of an expired lock.
	 *
	 * Uses its own option-backed lock key, never a real run's.
	 *
	 * @return array<string,mixed>
	 */
	private function case_lock_and_takeover(): array {
		$repository = $this->repository();
		$key        = 'aips_stress_lock_' . wp_generate_uuid4();
		$tokens     = array('a' => wp_generate_uuid4(), 'b' => wp_generate_uuid4(), 'c' => wp_generate_uuid4());
		$problems   = array();
		$checks     = array();

		try {
			$checks['first_acquire']          = $repository->acquire_lock($key, $tokens['a'], 60);
			$checks['second_acquire_blocked'] = !$repository->acquire_lock($key, $tokens['b'], 60);
			$checks['owner_refresh']          = $repository->refresh_lock($key, $tokens['a'], 60);
			$checks['stranger_refresh_fails'] = !$repository->refresh_lock($key, $tokens['b'], 60);

			$repository->release_lock($key, $tokens['b']);
			$checks['stranger_release_ignored'] = !$repository->acquire_lock($key, $tokens['c'], 60);

			$repository->release_lock($key, $tokens['a']);
			$checks['acquire_after_release'] = $repository->acquire_lock($key, $tokens['c'], 1);

			// Let the one-second lock expire, then another worker takes it over.
			usleep(2200000);
			$checks['expired_takeover']        = $repository->acquire_lock($key, $tokens['b'], 60);
			$checks['previous_owner_loses_cas'] = !$repository->refresh_lock($key, $tokens['c'], 60);
			$checks['new_owner_refresh']        = $repository->refresh_lock($key, $tokens['b'], 60);
		} catch (Throwable $e) {
			$problems[] = $e->getMessage();
		} finally {
			foreach ($tokens as $token) {
				$repository->release_lock($key, $token);
			}
			delete_option($key);
		}

		foreach ($checks as $name => $ok) {
			if (!$ok) {
				/* translators: %s: name of the lock check that failed */
				$problems[] = sprintf(__('Lock check "%s" failed.', 'ai-post-scheduler'), $name);
			}
		}

		if (!empty($problems)) {
			return $this->failed_with($problems, $checks);
		}

		return array(
			'status'       => 'passed',
			'summary'      => sprintf(
				/* translators: %d: number of lock checks */
				__('All %d lock checks gave exactly one winner as designed.', 'ai-post-scheduler'),
				count($checks)
			),
			'ai_value'     => null,
			'plugin_value' => $checks,
		);
	}

	/**
	 * Ten queued runs with a second worker holding one run's lock.
	 *
	 * @return array<string,mixed>
	 */
	private function case_workflow_concurrency(): array {
		$skip = $this->workflow_skip();
		if (null !== $skip) {
			return $skip;
		}

		$calls     = 0;
		$abilities = array(
			'aips-stress/noop' => function () use (&$calls) {
				$calls++;

				return array('ok' => true);
			},
		);

		return $this->with_workflow('workflow_concurrency', $abilities, array(
			array('step_key' => 'only', 'ability_name' => 'aips-stress/noop'),
		), function (AIPS_Ability_Workflow_Executor $executor, int $workflow_id) use (&$calls): array {
			$repository = $this->repository();
			$total      = 10;
			$run_ids    = array();

			for ($i = 0; $i < $total; $i++) {
				$run_id = $executor->dispatch_run($workflow_id, array('type' => 'stress_test', 'n' => $i));

				if (is_wp_error($run_id)) {
					return $this->failed($run_id->get_error_message());
				}

				$run_ids[] = (int) $run_id;
			}

			$held_run = $run_ids[2];
			$key      = $executor->get_run_lock_key($held_run);
			$token    = wp_generate_uuid4();
			$problems = array();
			$started  = microtime(true);
			$states   = array();

			if (!$repository->acquire_lock($key, $token, 120)) {
				return $this->failed(__('Could not place the second worker lock.', 'ai-post-scheduler'));
			}

			try {
				foreach ($run_ids as $run_id) {
					$outcome          = $executor->process_run($run_id);
					$states[$run_id] = $outcome['state'];
				}
			} finally {
				$repository->release_lock($key, $token);
			}

			if ('busy' !== ($states[$held_run] ?? '')) {
				$problems[] = __('The run held by another worker was not reported busy.', 'ai-post-scheduler');
			}

			if ($total - 1 !== $calls) {
				$problems[] = sprintf(
					/* translators: 1: expected calls, 2: actual calls */
					__('Expected %1$d step calls while one run was locked, saw %2$d.', 'ai-post-scheduler'),
					$total - 1,
					$calls
				);
			}

			$after = $executor->process_run($held_run);

			if ('finished' !== $after['state']) {
				$problems[] = __('The previously busy run did not finish after the lock was released.', 'ai-post-scheduler');
			}

			$completed = 0;
			$duplicate = 0;

			foreach ($run_ids as $run_id) {
				$run = $repository->get_run($run_id);

				if ($run && 'completed' === $run->status) {
					$completed++;
				}

				if (count($repository->get_step_runs($run_id)) !== 1) {
					$duplicate++;
				}
			}

			if ($total !== $completed) {
				$problems[] = sprintf(
					/* translators: 1: completed runs, 2: total runs */
					__('%1$d of %2$d runs completed.', 'ai-post-scheduler'),
					$completed,
					$total
				);
			}

			if ($duplicate > 0) {
				$problems[] = __('A run has a duplicate or missing step run.', 'ai-post-scheduler');
			}

			$seconds = max(0.001, microtime(true) - $started);
			$metrics = array(
				'runs'            => $total,
				'completed'       => $completed,
				'step_calls'      => $calls,
				'busy_run'        => $held_run,
				'runs_per_second' => round($total / $seconds, 1),
			);

			if (!empty($problems)) {
				return $this->failed_with($problems, $metrics);
			}

			return array(
				'status'       => 'passed',
				'summary'      => sprintf(
					/* translators: 1: runs, 2: runs per second */
					__('%1$d runs completed (%2$s runs/s); the run held by another worker was reported busy, then finished once released; no duplicate step runs.', 'ai-post-scheduler'),
					$total,
					number_format_i18n($metrics['runs_per_second'], 1)
				),
				'ai_value'     => null,
				'plugin_value' => $metrics,
			);
		});
	}

	/**
	 * Five private posts through the enrichment queue and service with a fixture alt-text ability.
	 *
	 * The case never drives the real queue: it enriches only the posts it made, so
	 * generated posts waiting on the site are not touched.
	 *
	 * @return array<string,mixed>
	 */
	private function case_enrichment_queue(): array {
		return $this->with_fixture_provider(
			array(AIPS_Ability_Enrichment_Service::ALT_TEXT_ABILITY => function () {
				return array('alt_text' => 'Stress test alt text');
			}),
			function (AIPS_Ability_Service $abilities): array {
				$user_id = get_current_user_id();
				$count   = 5;
				$posts   = $this->make_enrichment_posts($count);
				$queue   = new AIPS_Ability_Enrichment_Queue_Repository();
				$service = new AIPS_Ability_Enrichment_Service($abilities, new AIPS_Ability_Catalog_Service($abilities));
				$started = microtime(true);
				$problems = array();
				$applied  = 0;

				foreach ($posts as $post_id => $attachment_id) {
					$queue->enqueue($post_id, $user_id);
				}

				foreach ($posts as $post_id => $attachment_id) {
					$outcomes = $service->enrich_post($post_id, $user_id);
					$queue->dequeue($post_id);

					if (isset($outcomes['alt_text']) && 'applied' === $outcomes['alt_text']) {
						$applied++;
					}

					if ('Stress test alt text' !== get_post_meta($attachment_id, '_wp_attachment_image_alt', true)) {
						/* translators: %d: post ID */
						$problems[] = sprintf(__('Post %d did not get its alt text.', 'ai-post-scheduler'), $post_id);
					}

					if ('' !== (string) get_post_meta($post_id, AIPS_Ability_Enrichment_Queue_Repository::META_KEY, true)) {
						/* translators: %d: post ID */
						$problems[] = sprintf(__('Post %d is still queued.', 'ai-post-scheduler'), $post_id);
					}
				}

				if (get_current_user_id() !== $user_id) {
					$problems[] = __('The current user was not restored after enrichment.', 'ai-post-scheduler');
				}

				$metrics = array(
					'posts'       => $count,
					'applied'     => $applied,
					'duration_ms' => round((microtime(true) - $started) * 1000, 1),
				);

				if ($applied !== $count) {
					$problems[] = sprintf(
						/* translators: 1: posts that got alt text, 2: posts */
						__('Only %1$d of %2$d posts were enriched.', 'ai-post-scheduler'),
						$applied,
						$count
					);
				}

				if (!empty($problems)) {
					return $this->failed_with($problems, $metrics);
				}

				return array(
					'status'       => 'passed',
					'summary'      => sprintf(
						/* translators: %d: number of posts */
						__('%d private posts were queued, enriched once each and left the queue; the current user was restored. Delete Data removes the posts.', 'ai-post-scheduler'),
						$count
					),
					'ai_value'     => null,
					'plugin_value' => $metrics,
				);
			}
		);
	}

	/**
	 * 100 workflow runs and 50 post enrichments with fixture abilities, end to end.
	 *
	 * Drives the executor and enrichment service directly (not the managed
	 * background processes, which also advance real queued work on the site).
	 *
	 * @return array<string,mixed>
	 */
	private function case_process_soak(): array {
		$skip = $this->workflow_skip();
		if (null !== $skip) {
			return $skip;
		}

		$workflow_calls = 0;
		$alt_calls      = 0;
		$abilities      = array(
			'aips-stress/noop'                                  => function () use (&$workflow_calls) {
				$workflow_calls++;

				return array('ok' => true);
			},
			AIPS_Ability_Enrichment_Service::ALT_TEXT_ABILITY => function () use (&$alt_calls) {
				$alt_calls++;

				return array('alt_text' => 'Stress soak alt text');
			},
		);

		return $this->with_workflow('process_soak', $abilities, array(
			array('step_key' => 'only', 'ability_name' => 'aips-stress/noop'),
		), function (AIPS_Ability_Workflow_Executor $executor, int $workflow_id, AIPS_Ability_Service $fixture_abilities) use (&$workflow_calls, &$alt_calls): array {
			$run_total  = 100;
			$post_total = 50;
			$budget     = 100; // seconds
			$started    = microtime(true);
			$repository = $this->repository();
			$user_id    = get_current_user_id();
			$problems   = array();
			$run_ids    = array();

			for ($i = 0; $i < $run_total; $i++) {
				$run_id = $executor->dispatch_run($workflow_id, array('type' => 'stress_test', 'n' => $i));

				if (is_wp_error($run_id)) {
					return $this->failed($run_id->get_error_message());
				}

				$run_ids[] = (int) $run_id;
			}

			$slices = 0;
			foreach ($run_ids as $run_id) {
				if (microtime(true) - $started > $budget) {
					$problems[] = __('The soak ran out of time before every run finished.', 'ai-post-scheduler');
					break;
				}

				$slices += $this->drive($executor, $run_id)['slices'];
			}

			$run_seconds = microtime(true) - $started;
			$completed   = 0;

			foreach ($run_ids as $run_id) {
				$run = $repository->get_run($run_id);

				if ($run && 'completed' === $run->status) {
					$completed++;
				}
			}

			$posts   = $this->make_enrichment_posts($post_total);
			$queue   = new AIPS_Ability_Enrichment_Queue_Repository();
			$service = new AIPS_Ability_Enrichment_Service($fixture_abilities, new AIPS_Ability_Catalog_Service($fixture_abilities));
			$applied = 0;

			foreach ($posts as $post_id => $attachment_id) {
				$queue->enqueue($post_id, $user_id);
			}

			foreach ($posts as $post_id => $attachment_id) {
				if (microtime(true) - $started > $budget) {
					$problems[] = __('The soak ran out of time before every post was enriched.', 'ai-post-scheduler');
					break;
				}

				$outcomes = $service->enrich_post($post_id, $user_id);
				$queue->dequeue($post_id);

				if (isset($outcomes['alt_text']) && 'applied' === $outcomes['alt_text']) {
					$applied++;
				}
			}

			$queued_left = 0;
			foreach (array_keys($posts) as $post_id) {
				if ('' !== (string) get_post_meta($post_id, AIPS_Ability_Enrichment_Queue_Repository::META_KEY, true)) {
					$queued_left++;
				}
			}

			if ($completed !== $run_total) {
				$problems[] = sprintf(
					/* translators: 1: completed runs, 2: total runs */
					__('%1$d of %2$d runs completed.', 'ai-post-scheduler'),
					$completed,
					$run_total
				);
			}

			if ($run_total !== $workflow_calls) {
				$problems[] = sprintf(
					/* translators: 1: expected calls, 2: actual calls */
					__('Expected %1$d workflow step calls, saw %2$d.', 'ai-post-scheduler'),
					$run_total,
					$workflow_calls
				);
			}

			if ($applied !== $post_total || $alt_calls !== $post_total) {
				$problems[] = sprintf(
					/* translators: 1: enriched posts, 2: ability calls, 3: posts */
					__('%1$d posts were enriched with %2$d ability calls; expected %3$d of each.', 'ai-post-scheduler'),
					$applied,
					$alt_calls,
					$post_total
				);
			}

			if ($queued_left > 0) {
				$problems[] = sprintf(
					/* translators: %d: number of posts */
					__('%d posts are still queued.', 'ai-post-scheduler'),
					$queued_left
				);
			}

			if (get_current_user_id() !== $user_id) {
				$problems[] = __('The current user was not restored.', 'ai-post-scheduler');
			}

			$total_seconds = max(0.001, microtime(true) - $started);
			$metrics       = array(
				'runs'                 => $run_total,
				'runs_completed'       => $completed,
				'run_slices'           => $slices,
				'runs_per_second'      => round($run_total / max(0.001, $run_seconds), 1),
				'posts'                => $post_total,
				'posts_enriched'       => $applied,
				'workflow_step_calls'  => $workflow_calls,
				'alt_text_calls'       => $alt_calls,
				'duration_seconds'     => round($total_seconds, 2),
			);

			if (!empty($problems)) {
				return $this->failed_with($problems, $metrics);
			}

			return array(
				'status'       => 'passed',
				'summary'      => sprintf(
					/* translators: 1: runs, 2: posts, 3: seconds */
					__('%1$d workflow runs and %2$d post enrichments finished in %3$s s with every call accounted for and nothing left queued. Delete Data removes the posts.', 'ai-post-scheduler'),
					$run_total,
					$post_total,
					number_format_i18n($total_seconds, 1)
				),
				'ai_value'     => null,
				'plugin_value' => $metrics,
			);
		});
	}

	/**
	 * A step returning about 1 MB: stored shortened, while later steps still read what they need.
	 *
	 * @return array<string,mixed>
	 */
	private function case_large_payload(): array {
		$skip = $this->workflow_skip();
		if (null !== $skip) {
			return $skip;
		}

		$abilities = array(
			'aips-stress/big'  => function () {
				return array('title' => 'Small field', 'items' => range(1, 150000));
			},
			'aips-stress/echo' => function ($payload) {
				return array('received' => $payload);
			},
		);

		return $this->with_workflow('large_payload', $abilities, array(
			array('step_key' => 'big', 'ability_name' => 'aips-stress/big'),
			array(
				'step_key'     => 'after',
				'ability_name' => 'aips-stress/echo',
				'depends_on'   => array('big'),
				'input_map'    => array('title' => '{{steps.big.output.title}}'),
			),
		), function (AIPS_Ability_Workflow_Executor $executor, int $workflow_id): array {
			$run_id = $executor->dispatch_run($workflow_id, array('type' => 'stress_test'));

			if (is_wp_error($run_id)) {
				return $this->failed($run_id->get_error_message());
			}

			$run_id   = (int) $run_id;
			$drive    = $this->drive($executor, $run_id);
			$run      = $this->repository()->get_run($run_id);
			$steps    = $this->step_runs_by_key($run_id);
			$stored   = isset($steps['big']) && is_array($steps['big']->output_snapshot) ? $steps['big']->output_snapshot : array();
			$received = isset($steps['after']) && is_array($steps['after']->output_snapshot) ? ($steps['after']->output_snapshot['received'] ?? array()) : array();
			$bytes    = strlen((string) wp_json_encode($stored));
			$problems = array();

			if ('finished' !== $drive['state'] || !$run || 'completed' !== $run->status) {
				$problems[] = __('The run did not complete.', 'ai-post-scheduler');
			}

			if ($bytes > AIPS_Ability_Workflow_Repository::SNAPSHOT_MAX_BYTES) {
				$problems[] = sprintf(
					/* translators: %d: stored size in bytes */
					__('The stored output is %d bytes, above the cap.', 'ai-post-scheduler'),
					$bytes
				);
			}

			if (empty($stored['items']['_truncated']) || !in_array('items', (array) ($stored['_truncated'] ?? array()), true)) {
				$problems[] = __('The large value was not replaced by a truncation marker.', 'ai-post-scheduler');
			}

			if (($stored['title'] ?? '') !== 'Small field') {
				$problems[] = __('A small field was lost when the output was shortened.', 'ai-post-scheduler');
			}

			if (($received['title'] ?? '') !== 'Small field') {
				$problems[] = __('The next step could not read the small field.', 'ai-post-scheduler');
			}

			$metrics = array(
				'run_status'     => $run ? $run->status : null,
				'stored_bytes'   => $bytes,
				'cap_bytes'      => AIPS_Ability_Workflow_Repository::SNAPSHOT_MAX_BYTES,
				'truncated_keys' => $stored['_truncated'] ?? array(),
			);

			if (!empty($problems)) {
				return $this->failed_with($problems, $metrics);
			}

			return array(
				'status'       => 'passed',
				'summary'      => sprintf(
					/* translators: 1: stored bytes, 2: cap in bytes */
					__('A step output of about 1 MB was stored as %1$d bytes (cap %2$d) with the large value replaced by a marker, and the next step still read the small field.', 'ai-post-scheduler'),
					$bytes,
					AIPS_Ability_Workflow_Repository::SNAPSHOT_MAX_BYTES
				),
				'ai_value'     => null,
				'plugin_value' => $metrics,
			);
		});
	}

	/**
	 * Run a callback with fixture abilities (no workflow involved).
	 *
	 * @param array    $abilities Fixture slug => callable(payload).
	 * @param callable $body      function (AIPS_Ability_Service $abilities): array
	 * @return array<string,mixed>
	 */
	private function with_fixture_provider(array $abilities, callable $body): array {
		$provider = function () use ($abilities) {
			return array(
				'name'   => 'aips-stress-fixture',
				'list'   => function () use ($abilities) {
					$list = array();

					foreach (array_keys($abilities) as $slug) {
						$list[$slug] = array('slug' => $slug, 'annotations' => array('readonly' => true));
					}

					return $list;
				},
				'invoke' => function ($slug, $payload) use ($abilities) {
					return call_user_func($abilities[$slug], $payload);
				},
			);
		};

		add_filter('aips_ability_provider', $provider, 999);

		try {
			return $body(new AIPS_Ability_Service());
		} catch (Throwable $e) {
			return $this->failed($e->getMessage());
		} finally {
			remove_filter('aips_ability_provider', $provider, 999);
		}
	}

	/**
	 * Create private, marked posts that each have a featured image without alt text.
	 *
	 * Posts and image attachments carry the Stress Test markers, so Delete Data removes them.
	 *
	 * @param int $count How many posts.
	 * @return array<int,int> Post ID => attachment ID.
	 */
	private function make_enrichment_posts(int $count): array {
		$posts = array();

		for ($i = 1; $i <= $count; $i++) {
			$post_id = wp_insert_post(array(
				'post_title'  => 'Stress enrichment post ' . $i,
				'post_status' => 'private',
				'post_type'   => 'post',
			));

			if (!$post_id || is_wp_error($post_id)) {
				continue;
			}

			update_post_meta($post_id, AIPS_Stress_Test_Service::TEST_POST_META, 1);

			$attachment_id = wp_insert_attachment(array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Stress image ' . $i,
				'post_status'    => 'inherit',
			), '', $post_id);

			if ($attachment_id && !is_wp_error($attachment_id)) {
				update_post_meta($attachment_id, AIPS_Stress_Test_Service::TEST_ATTACHMENT_META, 1);
				update_post_meta($post_id, '_thumbnail_id', $attachment_id);
				$posts[(int) $post_id] = (int) $attachment_id;
			}
		}

		return $posts;
	}

	// -----------------------------------------------------------------------
	// Workflow case support
	// -----------------------------------------------------------------------

	/**
	 * A result that skips a workflow case when workflows are switched off.
	 *
	 * @return array<string,mixed>|null Null when the case may run.
	 */
	private function workflow_skip(): ?array {
		if (!AIPS_Ability_Workflows_Feature::is_enabled()) {
			return $this->skipped(__('Ability Workflows are disabled (Settings > Ability Workflows).', 'ai-post-scheduler'));
		}

		return null;
	}

	/**
	 * Run a callback against a temporary workflow driven by fixture abilities.
	 *
	 * The fixtures replace the ability provider only for the duration of the
	 * call, and the workflow, its runs and their History rows are removed in
	 * every outcome, so the real abilities on the site are never touched.
	 *
	 * @param string   $case_id   Case id, used in the workflow name.
	 * @param array    $abilities Fixture slug => callable(payload).
	 * @param array    $steps     Step definitions.
	 * @param callable $body      function (AIPS_Ability_Workflow_Executor $executor, int $workflow_id): array
	 * @return array<string,mixed>
	 */
	private function with_workflow(string $case_id, array $abilities, array $steps, callable $body): array {
		$provider = function () use ($abilities) {
			return array(
				'name'   => 'aips-stress-fixture',
				'list'   => function () use ($abilities) {
					$list = array();

					foreach (array_keys($abilities) as $slug) {
						$list[$slug] = array('slug' => $slug, 'annotations' => array('readonly' => true));
					}

					return $list;
				},
				'invoke' => function ($slug, $payload) use ($abilities) {
					return call_user_func($abilities[$slug], $payload);
				},
			);
		};

		$repository  = $this->repository();
		$workflow_id = 0;
		add_filter('aips_ability_provider', $provider, 999);

		try {
			$fixture_abilities = new AIPS_Ability_Service();
			$executor          = new AIPS_Ability_Workflow_Executor(
				$repository,
				new AIPS_Ability_Catalog_Service($fixture_abilities),
				$fixture_abilities,
				null,
				null,
				null,
				null,
				'__return_true'
			);

			$workflow_id = $repository->create_workflow(array(
				'name'     => self::WORKFLOW_PREFIX . $case_id,
				'status'   => 'active',
				'settings' => array('stress_test' => true),
			));

			if (is_wp_error($workflow_id)) {
				return $this->failed($workflow_id->get_error_message());
			}

			$saved = $repository->save_steps((int) $workflow_id, $steps);

			if (is_wp_error($saved)) {
				return $this->failed($saved->get_error_message());
			}

			return $body($executor, (int) $workflow_id, $fixture_abilities);
		} catch (Throwable $e) {
			return $this->failed($e->getMessage());
		} finally {
			remove_filter('aips_ability_provider', $provider, 999);

			if ($workflow_id && !is_wp_error($workflow_id)) {
				$this->remove_workflow((int) $workflow_id);
			}
		}
	}

	/**
	 * Process a run until it finishes, waiting out retry backoffs.
	 *
	 * @param AIPS_Ability_Workflow_Executor $executor Executor.
	 * @param int                            $run_id   Run ID.
	 * @return array{state:string, slices:int}
	 */
	private function drive(AIPS_Ability_Workflow_Executor $executor, int $run_id): array {
		$state  = 'continue';
		$slices = 0;

		while ($slices < self::MAX_SLICES) {
			$outcome = $executor->process_run($run_id);
			$state   = $outcome['state'];
			$slices++;

			if ('finished' === $state) {
				break;
			}

			$wait = (int) $outcome['retry_at'] - time();
			if ($wait > 0) {
				usleep((int) (min($wait, 3) * 1000000) + 100000);
			}
		}

		return array('state' => $state, 'slices' => $slices);
	}

	/**
	 * Step runs of a run, keyed by step key.
	 *
	 * @param int $run_id Run ID.
	 * @return array<string,AIPS_Ability_Workflow_Step_Run>
	 */
	private function step_runs_by_key(int $run_id): array {
		$keyed = array();

		foreach ($this->repository()->get_step_runs($run_id) as $step_run) {
			$keyed[$step_run->step_key] = $step_run;
		}

		return $keyed;
	}

	/**
	 * The History record of a run: its ID and status.
	 *
	 * @param AIPS_Ability_Workflow_Run|null $run Run.
	 * @return array{id:int, status:string}
	 */
	private function history_status($run): array {
		$history_id = $run ? (int) $run->history_id : 0;
		$row        = $history_id > 0 ? AIPS_History_Repository::instance()->get_by_id($history_id) : null;

		return array('id' => $history_id, 'status' => $row ? (string) $row->status : 'missing');
	}

	/**
	 * Delete a stress workflow, its runs and their History rows.
	 *
	 * @param int $workflow_id Workflow ID.
	 */
	private function remove_workflow(int $workflow_id): void {
		$removed = $this->repository()->delete_workflow_with_runs($workflow_id);

		if (!empty($removed['history_ids'])) {
			AIPS_History_Repository::instance()->delete_bulk($removed['history_ids']);
		}
	}

	/**
	 * Number of stress workflows left behind (for example by a request that died).
	 *
	 * @return int
	 */
	public function count_leftovers(): int {
		return count($this->repository()->get_workflow_ids_by_name_prefix(self::WORKFLOW_PREFIX));
	}

	/**
	 * Delete stress workflows left behind, with their runs and History rows.
	 *
	 * @return int Workflows removed.
	 */
	public function cleanup(): int {
		$removed = 0;

		foreach ($this->repository()->get_workflow_ids_by_name_prefix(self::WORKFLOW_PREFIX) as $workflow_id) {
			$this->remove_workflow($workflow_id);
			$removed++;
		}

		return $removed;
	}

	/**
	 * @return AIPS_Ability_Workflow_Repository
	 */
	private function repository(): AIPS_Ability_Workflow_Repository {
		return AIPS_Ability_Workflow_Repository::instance();
	}

	/**
	 * A failed result listing every problem, with the metrics gathered.
	 *
	 * @param string[] $problems What went wrong.
	 * @param mixed    $metrics  Details to show.
	 * @return array<string,mixed>
	 */
	private function failed_with(array $problems, $metrics): array {
		$message = implode(' ', $problems);

		return array(
			'status'       => 'failed',
			'error'        => $message,
			'summary'      => $message,
			'ai_value'     => null,
			'plugin_value' => $metrics,
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
