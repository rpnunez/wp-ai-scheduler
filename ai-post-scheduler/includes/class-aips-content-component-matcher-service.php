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

	/**
	 * @var AIPS_Content_Component_Analytics_Repository
	 */
	private $analytics_repository;

	/**
	 * @var AIPS_Cache
	 */
	private $cache;

	public function __construct(
		?AIPS_Content_Components_Repository $components_repository = null,
		?AIPS_Content_Component_Rules_Repository $rules_repository = null,
		?AIPS_Content_Component_Analytics_Repository $analytics_repository = null
	) {
		$this->components_repository = $components_repository ?: new AIPS_Content_Components_Repository();
		$this->rules_repository      = $rules_repository ?: new AIPS_Content_Component_Rules_Repository();
		$this->analytics_repository  = $analytics_repository ?: new AIPS_Content_Component_Analytics_Repository();
		$this->cache                 = AIPS_Cache_Factory::named( 'aips_content_component_matcher' );
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
		if ( ! AIPS_Config::get_instance()->is_feature_enabled( 'content_components_engine', true ) ) {
			return array(
				'matched'  => array(),
				'rejected' => array(),
			);
		}

		$cache_key = $this->build_cache_key( $context );
		if ( $this->cache->has( $cache_key ) ) {
			return (array) $this->cache->get( $cache_key );
		}

		$started_at = microtime( true );
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
		$candidates         = array();
		$rejected           = array();
		$is_dry_run         = (bool) $context->get( 'is_dry_run', false );

		foreach ( $components as $component ) {
			$component_rules = $rules_by_component[ (int) $component->id ] ?? array();
			if ( empty( $component_rules ) ) {
				$rejected[] = array(
					'component' => $component,
					'reason'    => __( 'No enabled rules found for this component.', 'ai-post-scheduler' ),
				);
				continue;
			}

			foreach ( $component_rules as $rule ) {
				$rule_evaluation = $this->evaluate_rule( $rule, $component, $context );
				if ( ! $rule_evaluation['matched'] ) {
					$rejected[] = array(
						'component' => $component,
						'rule'      => $rule,
						'reason'    => $rule_evaluation['reason'],
					);
					if ( ! $is_dry_run && 'exclusion_blocked' === (string) ( $rule_evaluation['code'] ?? '' ) ) {
						$this->analytics_repository->record_event( (int) $component->id, 'skipped_exclusion' );
					}
					continue;
				}

				$candidates[] = array(
					'component' => $component,
					'rule'      => $rule,
					'priority'  => (int) $rule['priority'],
					'placement' => (string) ( $rule['placement'] ?? 'end_of_post' ),
					'score'     => 0,
					'rotation_score' => $this->get_rotation_score( $component, $rule, $context ),
				);
			}
		}

		$resolved = $this->arbitrate_candidates( $candidates, $rejected, $context );

		if ( ! $is_dry_run ) {
			foreach ( $resolved as $match ) {
				$this->analytics_repository->record_event( (int) $match['component']->id, 'matched' );
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

		$evaluation = array(
			'matched'  => $resolved,
			'rejected' => $rejected,
		);

		$this->cache->set( $cache_key, $evaluation, 300 );

		if ( AIPS_Telemetry::is_enabled() ) {
			AIPS_Telemetry::instance()->add_event(
				'content_components',
				array(
					'type'            => 'matcher_evaluation',
					'matched_count'   => count( $resolved ),
					'rejected_count'  => count( $rejected ),
					'candidate_count' => count( $candidates ),
					'elapsed_ms'      => round( ( microtime( true ) - $started_at ) * 1000, 2 ),
					'is_dry_run'      => $is_dry_run,
				)
			);
		}

		return $evaluation;
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
	 * @param array<string,mixed>                $rule Rule payload.
	 * @param object                             $component Component row.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return array<string,mixed>
	 */
	private function evaluate_rule( array $rule, $component, AIPS_Content_Component_Run_Context $context ) {
		if ( ! empty( $component->status ) && 'active' !== (string) $component->status ) {
			return array(
				'matched' => false,
				'code'    => 'inactive_status',
				'reason'  => __( 'Component status is not active.', 'ai-post-scheduler' ),
			);
		}

		if ( ! $this->evaluate_condition_set( $rule['conditions_json'] ?? array(), $component, $context, true ) ) {
			return array(
				'matched' => false,
				'code'    => 'conditions_unmatched',
				'reason'  => __( 'Rule conditions did not match this post context.', 'ai-post-scheduler' ),
			);
		}

		if ( $this->evaluate_condition_set( $rule['exclusions_json'] ?? array(), $component, $context, false ) ) {
			return array(
				'matched' => false,
				'code'    => 'exclusion_blocked',
				'reason'  => __( 'An exclusion rule blocked this component.', 'ai-post-scheduler' ),
			);
		}

		if ( ! $this->evaluate_date_window( $rule['date_window_json'] ?? array(), $context ) ) {
			return array(
				'matched' => false,
				'code'    => 'outside_date_window',
				'reason'  => __( 'The current date is outside the configured date window.', 'ai-post-scheduler' ),
			);
		}

		if ( ! $this->passes_frequency( $rule, $context ) ) {
			return array(
				'matched' => false,
				'code'    => 'frequency_limited',
				'reason'  => __( 'Frequency limits prevented another injection.', 'ai-post-scheduler' ),
			);
		}

		return array(
			'matched' => true,
			'code'    => 'matched',
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

	/**
	 * @param array<int,array<string,mixed>>               $candidates Candidate matches.
	 * @param array<int,array<string,mixed>>               $rejected Rejected collection.
	 * @param AIPS_Content_Component_Run_Context           $context Runtime context.
	 * @return array<int,array<string,mixed>>
	 */
	private function arbitrate_candidates( array $candidates, array &$rejected, AIPS_Content_Component_Run_Context $context ) {
		if ( empty( $candidates ) ) {
			return array();
		}

		$ranked = $this->resolve_variant_rotation( $candidates, $rejected );
		usort(
			$ranked,
			static function ( $left, $right ) {
				$priority_compare = (int) $right['priority'] <=> (int) $left['priority'];
				if ( 0 !== $priority_compare ) {
					return $priority_compare;
				}

				$score_compare = (float) $right['score'] <=> (float) $left['score'];
				if ( 0 !== $score_compare ) {
					return $score_compare;
				}

				$rotation_compare = (float) $right['rotation_score'] <=> (float) $left['rotation_score'];
				if ( 0 !== $rotation_compare ) {
					return $rotation_compare;
				}

				return (int) $left['component']->id <=> (int) $right['component']->id;
			}
		);

		$global_cap = max( 1, (int) AIPS_Config::get_instance()->get_option( 'aips_content_components_max_total', 3 ) );
		$resolved   = array();
		$type_usage = array();
		$slot_usage = array();
		$is_dry_run = (bool) $context->get( 'is_dry_run', false );

		foreach ( $ranked as $candidate ) {
			if ( count( $resolved ) >= $global_cap ) {
				$rejected[] = array(
					'component' => $candidate['component'],
					'rule'      => $candidate['rule'],
					'reason'    => __( 'Skipped because the per-post component cap was reached.', 'ai-post-scheduler' ),
				);
				if ( ! $is_dry_run ) {
					$this->analytics_repository->record_event( (int) $candidate['component']->id, 'skipped_conflict' );
				}
				continue;
			}

			$type = sanitize_key( (string) $candidate['component']->component_type );
			$slot = $this->slot_key_for_candidate( $candidate );

			$type_cap = $this->get_type_cap( $type );
			if ( isset( $type_usage[ $type ] ) && $type_usage[ $type ] >= $type_cap ) {
				$rejected[] = array(
					'component' => $candidate['component'],
					'rule'      => $candidate['rule'],
					'reason'    => __( 'Skipped because the component-type cap was reached.', 'ai-post-scheduler' ),
				);
				if ( ! $is_dry_run ) {
					$this->analytics_repository->record_event( (int) $candidate['component']->id, 'skipped_conflict' );
				}
				continue;
			}

			$slot_cap = $this->get_slot_cap( $type, $candidate['placement'] );
			if ( isset( $slot_usage[ $slot ] ) && $slot_usage[ $slot ] >= $slot_cap ) {
				$rejected[] = array(
					'component' => $candidate['component'],
					'rule'      => $candidate['rule'],
					'reason'    => __( 'Skipped because another component already occupies this slot.', 'ai-post-scheduler' ),
				);
				if ( ! $is_dry_run ) {
					$this->analytics_repository->record_event( (int) $candidate['component']->id, 'skipped_conflict' );
				}
				continue;
			}

			$resolved[]           = $candidate;
			$type_usage[ $type ]  = isset( $type_usage[ $type ] ) ? $type_usage[ $type ] + 1 : 1;
			$slot_usage[ $slot ]  = isset( $slot_usage[ $slot ] ) ? $slot_usage[ $slot ] + 1 : 1;
		}

		return $resolved;
	}

	/**
	 * Reduce equivalent candidates to one stable winner per slot/priority/type group.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidate matches.
	 * @param array<int,array<string,mixed>> $rejected Rejected collection.
	 * @return array<int,array<string,mixed>>
	 */
	private function resolve_variant_rotation( array $candidates, array &$rejected ) {
		$grouped = array();
		foreach ( $candidates as $candidate ) {
			$type      = sanitize_key( (string) $candidate['component']->component_type );
			$placement = $this->base_placement( $candidate['placement'] );
			$key       = $placement . '|' . $type . '|' . (int) $candidate['priority'];

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array();
			}
			$grouped[ $key ][] = $candidate;
		}

		$ranked = array();
		foreach ( $grouped as $group ) {
			if ( 1 === count( $group ) ) {
				$ranked[] = $group[0];
				continue;
			}

			usort(
				$group,
				static function ( $left, $right ) {
					$rotation_compare = (float) $right['rotation_score'] <=> (float) $left['rotation_score'];
					if ( 0 !== $rotation_compare ) {
						return $rotation_compare;
					}

					return (int) $left['component']->id <=> (int) $right['component']->id;
				}
			);

			$winner = array_shift( $group );
			$ranked[] = $winner;

			foreach ( $group as $loser ) {
				$rejected[] = array(
					'component' => $loser['component'],
					'rule'      => $loser['rule'],
					'reason'    => __( 'Skipped because an equivalent-priority variant won the rotation tie-breaker.', 'ai-post-scheduler' ),
				);
			}
		}

		return $ranked;
	}

	/**
	 * @param object                        $component Component row.
	 * @param array<string,mixed>           $rule Rule payload.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return float
	 */
	private function get_rotation_score( $component, array $rule, AIPS_Content_Component_Run_Context $context ) {
		$weight = 1.0;
		if ( isset( $rule['rotation_weight'] ) ) {
			$weight = max( 0.1, (float) $rule['rotation_weight'] );
		} elseif ( ! empty( $component->cta_payload ) ) {
			$payload = json_decode( (string) $component->cta_payload, true );
			if ( is_array( $payload ) && isset( $payload['rotation_weight'] ) ) {
				$weight = max( 0.1, (float) $payload['rotation_weight'] );
			}
		}

		$seed = implode(
			'|',
			array(
				(string) $context->get( 'post_id', 0 ),
				(string) $context->get( 'topic', '' ),
				(string) $rule['placement'],
				(string) $component->id,
			)
		);

		return $weight * ( hexdec( substr( hash( 'sha256', $seed ), 0, 8 ) ) / 4294967295 );
	}

	/**
	 * @param string $component_type Component type.
	 * @return int
	 */
	private function get_type_cap( $component_type ) {
		switch ( $component_type ) {
			case 'disclaimer':
				return 1;
			case 'internal_link_pod':
				return 1;
			default:
				return 99;
		}
	}

	/**
	 * @param string $component_type Component type.
	 * @param string $placement Placement string.
	 * @return int
	 */
	private function get_slot_cap( $component_type, $placement ) {
		$base_placement = $this->base_placement( $placement );

		if ( 'disclaimer' === $component_type ) {
			return 1;
		}

		if ( 'internal_link_pod' === $component_type ) {
			return 1;
		}

		if ( 'cta' === $component_type && 'end_of_post' === $base_placement ) {
			return 1;
		}

		return 99;
	}

	/**
	 * @param array<string,mixed> $candidate Candidate match payload.
	 * @return string
	 */
	private function slot_key_for_candidate( array $candidate ) {
		return $this->base_placement( (string) $candidate['placement'] ) . ':' . sanitize_key( (string) $candidate['component']->component_type );
	}

	/**
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return string
	 */
	private function build_cache_key( AIPS_Content_Component_Run_Context $context ) {
		return 'evaluation:' . md5( wp_json_encode( $this->build_cache_context_array( $context ) ) );
	}

	/**
	 * Build a normalized cache-key payload without storing the full content body.
	 *
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @return array<string,mixed>
	 */
	private function build_cache_context_array( AIPS_Content_Component_Run_Context $context ) {
		$cache_context = $context->to_array();
		$content       = '';

		if ( isset( $cache_context['content'] ) ) {
			$content = (string) $cache_context['content'];
			unset( $cache_context['content'] );
		}

		$cache_context['content_length'] = strlen( $content );
		$cache_context['has_headings']   = (bool) preg_match( '/<h[1-6]\b/i', $content );
		$cache_context['has_h2']         = (bool) preg_match( '/<h2\b/i', $content );
		$cache_context['content_hash']   = md5( $content );

		return $cache_context;
	}

	/**
	 * @param string $placement Placement string.
	 * @return string
	 */
	private function base_placement( $placement ) {
		$parts = explode( ':', (string) $placement );
		return sanitize_key( $parts[0] );
	}
}
