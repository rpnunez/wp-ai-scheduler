<?php
/**
 * Ability Workflow Condition Evaluator
 *
 * Pure logic for evaluating and validating AND/OR condition trees used by
 * workflow steps. No DB, HTTP, or ability calls — safe to unit test in
 * isolation and reused by both the executor (runtime) and the document
 * validator (save-time validation).
 *
 * Condition tree shape:
 *   array(
 *       'operator' => 'AND'|'OR',
 *       'rules'    => array(
 *           array( 'left' => '{{steps.outline.output.sections_count}}', 'operator' => '>', 'right' => 0 ),
 *           array( 'operator' => 'OR', 'rules' => array( ... ) ), // nested group
 *       ),
 *   )
 *
 * An empty tree (no rules) always evaluates to true — a step with no
 * conditions always runs.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Condition_Evaluator
 */
class AIPS_Ability_Workflow_Condition_Evaluator {

	/**
	 * Supported rule operators.
	 */
	const RULE_OPERATORS = array(
		'equals',
		'not_equals',
		'contains',
		'not_contains',
		'greater_than',
		'less_than',
		'is_empty',
		'is_not_empty',
		'in',
		'not_in',
		// Symbolic aliases accepted from the spec's example document.
		'>',
		'<',
		'==',
		'!=',
	);

	/**
	 * @var AIPS_Ability_Workflow_Variable_Resolver
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Ability_Workflow_Variable_Resolver|null $resolver Variable resolver.
	 */
	public function __construct( ?AIPS_Ability_Workflow_Variable_Resolver $resolver = null ) {
		$this->resolver = $resolver ?: new AIPS_Ability_Workflow_Variable_Resolver();
	}

	/**
	 * Evaluate a condition tree against a variable bag.
	 *
	 * @param array $condition_tree Condition tree (see class docblock).
	 * @param array $variables      Variable bag (trigger/steps).
	 * @return bool
	 */
	public function evaluate( array $condition_tree, array $variables ): bool {
		if ( empty( $condition_tree ) || empty( $condition_tree['rules'] ) ) {
			return true;
		}

		$operator = strtoupper( isset( $condition_tree['operator'] ) ? (string) $condition_tree['operator'] : 'AND' );
		$results  = array();

		foreach ( $condition_tree['rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$results[] = $this->is_nested_group( $rule )
				? $this->evaluate( $rule, $variables )
				: $this->evaluate_rule( $rule, $variables );
		}

		if ( empty( $results ) ) {
			return true;
		}

		return 'OR' === $operator ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	/**
	 * Evaluate a single leaf rule.
	 *
	 * @param array $rule      { left, operator, right }.
	 * @param array $variables Variable bag.
	 * @return bool
	 */
	public function evaluate_rule( array $rule, array $variables ): bool {
		$left     = $this->resolve_operand( $rule['left'] ?? null, $variables );
		$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : 'equals';

		if ( in_array( $operator, array( 'is_empty' ), true ) ) {
			return $this->is_empty_value( $left );
		}

		if ( in_array( $operator, array( 'is_not_empty' ), true ) ) {
			return ! $this->is_empty_value( $left );
		}

		$right = $this->resolve_operand( $rule['right'] ?? null, $variables );

		switch ( $operator ) {
			case 'equals':
			case '==':
				return $this->loose_compare( $left, $right ) === 0;
			case 'not_equals':
			case '!=':
				return $this->loose_compare( $left, $right ) !== 0;
			case 'contains':
				return is_string( $left ) && is_scalar( $right ) && false !== strpos( $left, (string) $right );
			case 'not_contains':
				return ! ( is_string( $left ) && is_scalar( $right ) && false !== strpos( $left, (string) $right ) );
			case 'greater_than':
			case '>':
				return is_numeric( $left ) && is_numeric( $right ) && (float) $left > (float) $right;
			case 'less_than':
			case '<':
				return is_numeric( $left ) && is_numeric( $right ) && (float) $left < (float) $right;
			case 'in':
				return in_array( $left, $this->to_list( $right ), false );
			case 'not_in':
				return ! in_array( $left, $this->to_list( $right ), false );
		}

		return false;
	}

	/**
	 * Structurally validate a condition tree (does not evaluate it).
	 *
	 * @param array $condition_tree Condition tree to validate.
	 * @return true|WP_Error
	 */
	public function validate_condition_tree( array $condition_tree ) {
		if ( empty( $condition_tree ) ) {
			return true;
		}

		if ( isset( $condition_tree['operator'] ) && ! in_array( strtoupper( (string) $condition_tree['operator'] ), array( 'AND', 'OR' ), true ) ) {
			return new WP_Error( 'ability_workflow_condition_invalid', __( 'Condition group operator must be AND or OR.', 'ai-post-scheduler' ) );
		}

		if ( ! isset( $condition_tree['rules'] ) ) {
			return true;
		}

		if ( ! is_array( $condition_tree['rules'] ) ) {
			return new WP_Error( 'ability_workflow_condition_invalid', __( 'Condition rules must be an array.', 'ai-post-scheduler' ) );
		}

		foreach ( $condition_tree['rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				return new WP_Error( 'ability_workflow_condition_invalid', __( 'Each condition rule must be an array.', 'ai-post-scheduler' ) );
			}

			if ( $this->is_nested_group( $rule ) ) {
				$nested_result = $this->validate_condition_tree( $rule );
				if ( is_wp_error( $nested_result ) ) {
					return $nested_result;
				}
				continue;
			}

			if ( ! array_key_exists( 'left', $rule ) || ! isset( $rule['operator'] ) ) {
				return new WP_Error( 'ability_workflow_condition_invalid', __( 'Each condition rule requires "left" and "operator".', 'ai-post-scheduler' ) );
			}

			if ( ! in_array( (string) $rule['operator'], self::RULE_OPERATORS, true ) ) {
				return new WP_Error(
					'ability_workflow_condition_invalid',
					/* translators: %s: operator name */
					sprintf( __( 'Unsupported condition operator "%s".', 'ai-post-scheduler' ), $rule['operator'] )
				);
			}

			if ( is_string( $rule['left'] ) && false !== strpos( $rule['left'], '{{' ) ) {
				$token_result = $this->resolver->validate_token_string( $rule['left'] );
				if ( is_wp_error( $token_result ) ) {
					return $token_result;
				}
			}
		}

		return true;
	}

	/**
	 * Whether a rule array is a nested condition group rather than a leaf rule.
	 *
	 * @param array $rule Rule array.
	 * @return bool
	 */
	private function is_nested_group( array $rule ): bool {
		return isset( $rule['rules'] ) && is_array( $rule['rules'] );
	}

	/**
	 * Resolve a rule operand, following {{...}} tokens when the operand is a string.
	 *
	 * @param mixed $value     Raw operand.
	 * @param array $variables Variable bag.
	 * @return mixed
	 */
	private function resolve_operand( $value, array $variables ) {
		if ( is_string( $value ) ) {
			return $this->resolver->resolve( $value, $variables );
		}

		return $value;
	}

	/**
	 * Whether a resolved value should be treated as empty.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private function is_empty_value( $value ): bool {
		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return null === $value || '' === $value;
	}

	/**
	 * Loosely compare two scalar/array values, treating numeric strings as numbers.
	 *
	 * @param mixed $left  Left operand.
	 * @param mixed $right Right operand.
	 * @return int -1, 0, or 1.
	 */
	private function loose_compare( $left, $right ): int {
		if ( is_numeric( $left ) && is_numeric( $right ) ) {
			return (float) $left <=> (float) $right;
		}

		return (string) $left <=> (string) $right;
	}

	/**
	 * Coerce a value into a list for in/not_in comparisons.
	 *
	 * @param mixed $value Raw value — array, comma-separated string, or scalar.
	 * @return array
	 */
	private function to_list( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return array_map( 'trim', explode( ',', $value ) );
		}

		return array( $value );
	}
}
