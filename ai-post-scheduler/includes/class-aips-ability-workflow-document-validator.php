<?php
/**
 * Ability Workflow Document Validator
 *
 * Validates a decoded workflow document (a workflow's steps + settings)
 * before it is persisted. Pure logic composed from the condition evaluator,
 * variable resolver, and (optionally) the ability catalog — no direct SQL.
 *
 * Used by the "save workflow steps" AJAX handler before calling
 * AIPS_Ability_Workflow_Repository::save_steps().
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Document_Validator
 */
class AIPS_Ability_Workflow_Document_Validator {

	/**
	 * Allowed on_success/on_failure strategies.
	 */
	const STRATEGIES = array( 'continue', 'stop', 'skip' );

	/**
	 * @var AIPS_Ability_Workflow_Condition_Evaluator
	 */
	private $condition_evaluator;

	/**
	 * @var AIPS_Ability_Workflow_Variable_Resolver
	 */
	private $resolver;

	/**
	 * @var AIPS_Ability_Catalog_Service|null
	 */
	private $catalog;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Ability_Workflow_Condition_Evaluator|null $condition_evaluator Condition evaluator.
	 * @param AIPS_Ability_Workflow_Variable_Resolver|null   $resolver            Variable resolver.
	 * @param AIPS_Ability_Catalog_Service|null              $catalog             Ability catalog service.
	 */
	public function __construct(
		?AIPS_Ability_Workflow_Condition_Evaluator $condition_evaluator = null,
		?AIPS_Ability_Workflow_Variable_Resolver $resolver = null,
		?AIPS_Ability_Catalog_Service $catalog = null
	) {
		$this->resolver            = $resolver ?: new AIPS_Ability_Workflow_Variable_Resolver();
		$this->condition_evaluator = $condition_evaluator ?: new AIPS_Ability_Workflow_Condition_Evaluator( $this->resolver );
		$this->catalog             = $catalog;
	}

	/**
	 * Validate a workflow document's steps.
	 *
	 * @param array $workflow_document {
	 *     @type array $steps Array of step field arrays.
	 * }
	 * @param bool  $skip_ability_check When true, skip catalog ability-availability checks
	 *                                  (useful offline/in tests where no provider is registered).
	 * @return true|WP_Error WP_Error data carries a list of per-field errors.
	 */
	public function validate( array $workflow_document, bool $skip_ability_check = false ) {
		$steps = isset( $workflow_document['steps'] ) && is_array( $workflow_document['steps'] ) ? $workflow_document['steps'] : array();
		$errors = array();

		$seen_keys     = array();
		$earlier_keys  = array();
		$earlier_alias = array();

		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) ) {
				$errors[] = sprintf( 'Step at index %d must be an array.', $index );
				continue;
			}

			$step_key = isset( $step['step_key'] ) ? (string) $step['step_key'] : '';

			if ( '' === $step_key ) {
				$errors[] = sprintf( 'Step at index %d is missing a step_key.', $index );
			} elseif ( isset( $seen_keys[ $step_key ] ) ) {
				$errors[] = sprintf( 'Duplicate step_key "%s".', $step_key );
			} else {
				$seen_keys[ $step_key ] = true;
			}

			if ( empty( $step['ability_name'] ) ) {
				$errors[] = sprintf( 'Step "%s" is missing an ability_name.', $step_key );
			} elseif ( ! $skip_ability_check && $this->catalog instanceof AIPS_Ability_Catalog_Service ) {
				$available = $this->catalog->validate_ability_available( (string) $step['ability_name'] );
				if ( is_wp_error( $available ) ) {
					$errors[] = sprintf( 'Step "%s": %s', $step_key, $available->get_error_message() );
				}
			}

			foreach ( array( 'depends_on' ) as $ref_field ) {
				if ( ! empty( $step[ $ref_field ] ) && is_array( $step[ $ref_field ] ) ) {
					foreach ( $step[ $ref_field ] as $dep_key ) {
						if ( ! in_array( (string) $dep_key, $earlier_keys, true ) ) {
							$errors[] = sprintf( 'Step "%s" depends_on unknown or later step "%s".', $step_key, $dep_key );
						}
					}
				}
			}

			if ( ! empty( $step['condition_tree'] ) && is_array( $step['condition_tree'] ) ) {
				$condition_result = $this->condition_evaluator->validate_condition_tree( $step['condition_tree'] );
				if ( is_wp_error( $condition_result ) ) {
					$errors[] = sprintf( 'Step "%s": %s', $step_key, $condition_result->get_error_message() );
				}
			}

			if ( ! empty( $step['input_map'] ) && is_array( $step['input_map'] ) ) {
				$input_map_errors = $this->validate_input_map( $step['input_map'], $earlier_alias );
				foreach ( $input_map_errors as $message ) {
					$errors[] = sprintf( 'Step "%s": %s', $step_key, $message );
				}
			}

			foreach ( array( 'on_success', 'on_failure' ) as $strategy_field ) {
				if ( ! empty( $step[ $strategy_field ]['strategy'] ) && ! in_array( $step[ $strategy_field ]['strategy'], self::STRATEGIES, true ) ) {
					$errors[] = sprintf( 'Step "%s" has an invalid %s.strategy "%s".', $step_key, $strategy_field, $step[ $strategy_field ]['strategy'] );
				}
			}

			if ( ! empty( $step['retry_policy'] ) && is_array( $step['retry_policy'] ) ) {
				foreach ( array( 'attempts', 'backoff_seconds' ) as $retry_field ) {
					if ( isset( $step['retry_policy'][ $retry_field ] ) && ( ! is_numeric( $step['retry_policy'][ $retry_field ] ) || (int) $step['retry_policy'][ $retry_field ] < 0 ) ) {
						$errors[] = sprintf( 'Step "%s" retry_policy.%s must be a non-negative integer.', $step_key, $retry_field );
					}
				}
			}

			if ( '' !== $step_key ) {
				$earlier_keys[] = $step_key;
			}
			if ( ! empty( $step['output_alias'] ) ) {
				$earlier_alias[] = (string) $step['output_alias'];
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'ability_workflow_validation_failed', __( 'Workflow validation failed.', 'ai-post-scheduler' ), array( 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Validate every token in an input map, ensuring tokens are structurally
	 * valid and — for steps.* tokens — reference only earlier steps' output_alias.
	 *
	 * @param array $input_map     Input map (dest field => template value or nested map).
	 * @param array $earlier_alias Output aliases seen so far, in step order.
	 * @return string[] Error messages, empty when valid.
	 */
	private function validate_input_map( array $input_map, array $earlier_alias ): array {
		$errors = array();

		array_walk_recursive(
			$input_map,
			function ( $value ) use ( &$errors, $earlier_alias ) {
				if ( ! is_string( $value ) || false === strpos( $value, '{{' ) ) {
					return;
				}

				foreach ( $this->resolver->extract_tokens( $value ) as $token ) {
					$token_result = $this->resolver->validate_token( $token );

					if ( is_wp_error( $token_result ) ) {
						$errors[] = $token_result->get_error_message();
						continue;
					}

					if ( 0 === strpos( $token, 'steps.' ) ) {
						$alias = explode( '.', $token )[1] ?? '';
						if ( ! in_array( $alias, $earlier_alias, true ) ) {
							$errors[] = sprintf( 'Input map references unknown or later step output alias "%s".', $alias );
						}
					}
				}
			}
		);

		return $errors;
	}
}
