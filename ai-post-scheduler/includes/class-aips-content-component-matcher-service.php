<?php
/**
 * Content Component Matcher Service
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Component_Matcher_Service {

	/**
	 * @var AIPS_Content_Components_Repository
	 */
	private $components_repository;

	/**
	 * @var AIPS_Content_Component_Rules_Repository
	 */
	private $rules_repository;

	public function __construct(
		?AIPS_Content_Components_Repository $components_repository = null,
		?AIPS_Content_Component_Rules_Repository $rules_repository = null
	) {
		$this->components_repository = $components_repository ?: new AIPS_Content_Components_Repository();
		$this->rules_repository      = $rules_repository ?: new AIPS_Content_Component_Rules_Repository();
	}

	/**
	 * Resolve a sorted injection plan from active components and rules.
	 *
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return array<int,array<string,mixed>>
	 */
	public function resolve_plan( AIPS_Content_Component_Run_Context $context ) {
		$evaluation = $this->evaluate_components_detailed( $context );
		return $evaluation['matched'];
	}

	/**
	 * Evaluate all active components with match/reject reasons for dry-run UX.
	 *
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function evaluate_components_detailed( AIPS_Content_Component_Run_Context $context ) {
		$components = $this->components_repository->get_active_components();
		if ( empty( $components ) ) {
			return array(
				'matched'  => array(),
				'rejected' => array(),
			);
		}

		$component_ids = array_map(
			static function ( $component ) {
				return (int) $component->id;
			},
			$components
		);
		$rules_by_component = $this->rules_repository->get_enabled_rules_for_component_ids( $component_ids );
		$resolved         = array();
		$rejected         = array();
		$disclaimer_taken = false;

		foreach ( $components as $component ) {
			$component_rules = $rules_by_component[ (int) $component->id ] ?? array();
			if ( empty( $component_rules ) ) {
				$rejected[] = array(
					'component' => $component,
					'reason'    => __( 'No enabled rules found for this component.', 'ai-post-scheduler' ),
				);
				continue;
			}

			$component_matched = false;
			foreach ( $component_rules as $rule ) {
				$rule_evaluation = $this->evaluate_rule( $rule, $component, $context );
				if ( ! $rule_evaluation['matched'] ) {
					$rejected[] = array(
						'component' => $component,
						'rule'      => $rule,
						'reason'    => $rule_evaluation['reason'],
					);
					continue;
				}

				if ( 'disclaimer' === (string) $component->component_type ) {
					if ( $disclaimer_taken ) {
						$rejected[] = array(
							'component' => $component,
							'rule'      => $rule,
							'reason'    => __( 'Skipped because another disclaimer already occupies this slot.', 'ai-post-scheduler' ),
						);
						continue;
					}
					$disclaimer_taken = true;
				}

				$component_matched = true;
				$resolved[] = array(
					'component' => $component,
					'rule'      => $rule,
					'priority'  => (int) $rule['priority'],
				);
			}

		}

		usort(
			$resolved,
			static function ( $left, $right ) {
				$priority_compare = (int) $right['priority'] <=> (int) $left['priority'];
				if ( 0 !== $priority_compare ) {
					return $priority_compare;
				}

				return (int) $left['component']->id <=> (int) $right['component']->id;
			}
		);

		return array(
			'matched'  => $resolved,
			'rejected' => $rejected,
		);
	}

	/**
	 * Public wrapper for ad-hoc dry-run evaluation of one component/rule pair.
	 *
	 * @param object                          $component Component-like object.
	 * @param array<string,mixed>             $rule Normalized Phase 1 rule record.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return array<string,mixed>
	 */
	public function evaluate_component_rule( $component, array $rule, AIPS_Content_Component_Run_Context $context ) {
		return $this->evaluate_rule( $rule, $component, $context );
	}

	/**
	 * Evaluate a rule against the run context.
	 *
	 * @param array<string,mixed>             $rule Rule payload.
	 * @param object                          $component Component row.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return bool
	 */
	private function evaluate_rule( array $rule, $component, AIPS_Content_Component_Run_Context $context ) {
		if ( ! empty( $component->status ) && 'active' !== (string) $component->status ) {
			return array(
				'matched' => false,
				'reason'  => __( 'Component status is not active.', 'ai-post-scheduler' ),
			);
		}

		if ( ! $this->evaluate_condition_set( $rule['conditions_json'] ?? array(), $component, $context, true ) ) {
			return array(
				'matched' => false,
				'reason'  => __( 'Rule conditions did not match this post context.', 'ai-post-scheduler' ),
			);
		}

		if ( $this->evaluate_condition_set( $rule['exclusions_json'] ?? array(), $component, $context, false ) ) {
			return array(
				'matched' => false,
				'reason'  => __( 'An exclusion rule blocked this component.', 'ai-post-scheduler' ),
			);
		}

		if ( ! $this->evaluate_date_window( $rule['date_window_json'] ?? array(), $context ) ) {
			return array(
				'matched' => false,
				'reason'  => __( 'The current date is outside the configured date window.', 'ai-post-scheduler' ),
			);
		}

		if ( ! $this->passes_frequency( $rule, $context ) ) {
			return array(
				'matched' => false,
				'reason'  => __( 'Frequency limits prevented another injection.', 'ai-post-scheduler' ),
			);
		}

		return array(
			'matched' => true,
			'reason'  => __( 'Matched.', 'ai-post-scheduler' ),
		);
	}

	/**
	 * Evaluate a condition set.
	 *
	 * @param array<string,mixed>             $condition_set Condition set.
	 * @param object                          $component Component row.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @param bool                            $empty_result Default value for empty sets.
	 * @return bool
	 */
	private function evaluate_condition_set( array $condition_set, $component, AIPS_Content_Component_Run_Context $context, $empty_result ) {
		$conditions = isset( $condition_set['conditions'] ) && is_array( $condition_set['conditions'] )
			? $condition_set['conditions']
			: array();

		if ( empty( $conditions ) ) {
			return $empty_result;
		}

		$logic   = isset( $condition_set['logic'] ) && 'or' === sanitize_key( (string) $condition_set['logic'] ) ? 'or' : 'and';
		$results = array();

		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}
			$results[] = $this->evaluate_condition( $condition, $component, $context );
		}

		if ( empty( $results ) ) {
			return $empty_result;
		}

		return 'or' === $logic
			? in_array( true, $results, true )
			: ! in_array( false, $results, true );
	}

	/**
	 * Evaluate one rule condition.
	 *
	 * @param array<string,mixed>             $condition Condition payload.
	 * @param object                          $component Component row.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return bool
	 */
	private function evaluate_condition( array $condition, $component, AIPS_Content_Component_Run_Context $context ) {
		$field    = isset( $condition['field'] ) ? sanitize_key( (string) $condition['field'] ) : '';
		$operator = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'is';
		$values   = array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						return strtolower( trim( sanitize_text_field( (string) $value ) ) );
					},
					(array) ( $condition['values'] ?? array() )
				)
			)
		);

		if ( 'category' === $field ) {
			return $this->match_token_list( (array) $context->get( 'category_tokens', array() ), $values, $operator );
		}

		if ( 'tag' === $field || 'policy_tag' === $field ) {
			$tokens = 'policy_tag' === $field ? (array) $context->get( 'policy_tags', array() ) : (array) $context->get( 'tag_tokens', array() );
			return $this->match_token_list( $tokens, $values, $operator );
		}

		if ( 'post_type' === $field ) {
			return $this->match_scalar( strtolower( (string) $context->get( 'post_type', '' ) ), $values, $operator );
		}

		if ( 'author_id' === $field ) {
			return $this->match_scalar( strtolower( (string) $context->get( 'author_id', 0 ) ), $values, $operator );
		}

		if ( 'author_persona' === $field || 'persona' === $field ) {
			return $this->match_text( strtolower( (string) $context->get( 'author_persona', '' ) ), $values, $operator );
		}

		if ( 'region' === $field ) {
			return $this->match_scalar( strtolower( (string) $context->get( 'region', '' ) ), $values, $operator );
		}

		if ( 'locale' === $field ) {
			return $this->match_scalar( strtolower( (string) $context->get( 'locale', '' ) ), $values, $operator );
		}

		if ( 'component_type' === $field ) {
			return $this->match_scalar( strtolower( (string) $component->component_type ), $values, $operator );
		}

		if ( 'title_contains' === $field || 'keyword' === $field || 'intent' === $field ) {
			return $this->match_text( strtolower( (string) $context->get( 'topic', '' ) ), $values, $operator );
		}

		if ( 'content_length' === $field ) {
			return $this->match_numeric( (int) $context->get( 'content_length', 0 ), $values, $operator );
		}

		if ( 'heading_presence' === $field || 'has_h2' === $field ) {
			$actual = 'has_h2' === $field ? (bool) $context->get( 'has_h2', false ) : (bool) $context->get( 'has_headings', false );
			return $this->match_boolean( $actual, $values, $operator );
		}

		return false;
	}

	/**
	 * @param string[] $haystack Context tokens.
	 * @param string[] $needles Expected values.
	 * @param string   $operator Operator.
	 * @return bool
	 */
	private function match_token_list( array $haystack, array $needles, $operator ) {
		$matches = count( array_intersect( $haystack, $needles ) ) > 0;

		if ( in_array( $operator, array( 'is_not', 'does_not_contain', 'exclude', 'not_in' ), true ) ) {
			return ! $matches;
		}

		return $matches;
	}

	/**
	 * @param string   $actual Context value.
	 * @param string[] $values Rule values.
	 * @param string   $operator Operator.
	 * @return bool
	 */
	private function match_scalar( $actual, array $values, $operator ) {
		$matches = in_array( $actual, $values, true );

		if ( in_array( $operator, array( 'is_not', 'not_equals', 'not_in' ), true ) ) {
			return ! $matches;
		}

		return $matches;
	}

	/**
	 * @param string   $actual Context text.
	 * @param string[] $values Rule values.
	 * @param string   $operator Operator.
	 * @return bool
	 */
	private function match_text( $actual, array $values, $operator ) {
		$matches = false;

		foreach ( $values as $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( 'starts_with' === $operator && 0 === strpos( $actual, $value ) ) {
				$matches = true;
				break;
			}
			if ( 'ends_with' === $operator && substr( $actual, -strlen( $value ) ) === $value ) {
				$matches = true;
				break;
			}
			if ( false !== strpos( $actual, $value ) ) {
				$matches = true;
				break;
			}
		}

		if ( in_array( $operator, array( 'is_not', 'does_not_contain', 'exclude' ), true ) ) {
			return ! $matches;
		}

		return $matches;
	}

	/**
	 * @param int      $actual Context number.
	 * @param string[] $values Rule values.
	 * @param string   $operator Operator.
	 * @return bool
	 */
	private function match_numeric( $actual, array $values, $operator ) {
		$expected = isset( $values[0] ) ? (int) $values[0] : 0;

		switch ( $operator ) {
			case 'gt':
				return $actual > $expected;
			case 'gte':
				return $actual >= $expected;
			case 'lt':
				return $actual < $expected;
			case 'lte':
				return $actual <= $expected;
			case 'is_not':
				return $actual !== $expected;
			default:
				return $actual === $expected;
		}
	}

	/**
	 * @param bool     $actual Context boolean.
	 * @param string[] $values Rule values.
	 * @param string   $operator Operator.
	 * @return bool
	 */
	private function match_boolean( $actual, array $values, $operator ) {
		$expected = isset( $values[0] ) ? in_array( $values[0], array( '1', 'true', 'yes' ), true ) : true;
		$matches  = $actual === $expected;

		if ( in_array( $operator, array( 'is_not', 'not_equals' ), true ) ) {
			return ! $matches;
		}

		return $matches;
	}

	/**
	 * Evaluate a rule date window.
	 *
	 * @param array<string,mixed>             $date_window Date window payload.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return bool
	 */
	private function evaluate_date_window( array $date_window, AIPS_Content_Component_Run_Context $context ) {
		if ( empty( $date_window ) ) {
			return true;
		}

		$timestamp = absint( $context->get( 'run_timestamp', AIPS_DateTime::now()->timestamp() ) );
		$timezone  = ! empty( $date_window['timezone'] ) ? (string) $date_window['timezone'] : (string) $context->get( 'site_timezone', 'UTC' );

		$start = $this->parse_date_window_boundary( $date_window['start'] ?? null, $timezone );
		$end   = $this->parse_date_window_boundary( $date_window['end'] ?? null, $timezone );

		if ( $start && $timestamp < $start ) {
			return false;
		}

		if ( $end && $timestamp > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * Parse a date window boundary to a timestamp.
	 *
	 * @param mixed  $raw Raw boundary value.
	 * @param string $timezone Timezone string.
	 * @return int
	 */
	private function parse_date_window_boundary( $raw, $timezone ) {
		if ( empty( $raw ) ) {
			return 0;
		}

		if ( is_numeric( $raw ) ) {
			return absint( $raw );
		}

		try {
			$dt = new DateTimeImmutable( (string) $raw, new DateTimeZone( $timezone ) );
			return $dt->getTimestamp();
		} catch ( Exception $exception ) {
			return 0;
		}
	}

	/**
	 * Enforce simple per-post frequency limits.
	 *
	 * @param array<string,mixed>             $rule Rule payload.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return bool
	 */
	private function passes_frequency( array $rule, AIPS_Content_Component_Run_Context $context ) {
		$mode = isset( $rule['frequency_mode'] ) ? sanitize_key( (string) $rule['frequency_mode'] ) : 'once_per_post';
		if ( 'unlimited' === $mode ) {
			return true;
		}

		$max_occurrences = max( 1, (int) ( $rule['max_occurrences'] ?? 1 ) );
		return $max_occurrences >= 1 && $context->get( 'post_id', 0 ) >= 0;
	}
}
