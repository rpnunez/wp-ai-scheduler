<?php
/**
 * Content Component Rule Summary Service
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Component_Rule_Summary_Service {

	/**
	 * Create a readable summary from the component and rules payload.
	 *
	 * @param array<string,mixed> $component Component-like payload.
	 * @param array<string,mixed> $rules Rules payload.
	 * @return string
	 */
	public function summarize( array $component, array $rules ) {
		$title     = ! empty( $component['title'] ) ? (string) $component['title'] : __( 'This component', 'ai-post-scheduler' );
		$placement = $this->describe_action( $rules['action'] ?? 'add_at_end' );
		$logic     = isset( $rules['logic'] ) && 'or' === sanitize_key( (string) $rules['logic'] ) ? 'or' : 'and';
		$conditions = array();

		foreach ( (array) ( $rules['conditions'] ?? array() ) as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}
			$conditions[] = $this->describe_condition( $condition );
		}

		$summary = sprintf(
			/* translators: 1: component title, 2: placement text */
			__( 'Inject %1$s %2$s', 'ai-post-scheduler' ),
			'"' . $title . '"',
			$placement
		);

		if ( ! empty( $conditions ) ) {
			$join_word = 'or' === $logic ? __( ' or ', 'ai-post-scheduler' ) : __( ' and ', 'ai-post-scheduler' );
			$summary  .= ' ' . __( 'when', 'ai-post-scheduler' ) . ' ' . implode( $join_word, $conditions );
		}

		$date_window = isset( $rules['date_window'] ) && is_array( $rules['date_window'] ) ? $rules['date_window'] : array();
		if ( ! empty( $date_window['start'] ) || ! empty( $date_window['end'] ) ) {
			$summary .= ' ' . $this->describe_date_window( $date_window );
		}

		return trim( $summary ) . '.';
	}

	/**
	 * @param string $action Action key.
	 * @return string
	 */
	private function describe_action( $action ) {
		switch ( sanitize_key( (string) $action ) ) {
			case 'prepend_intro':
				return __( 'after the introduction', 'ai-post-scheduler' );
			case 'add_middle_paragraph':
				return __( 'after the 2nd H2', 'ai-post-scheduler' );
			case 'add_before_first_heading':
				return __( 'before the first heading', 'ai-post-scheduler' );
			case 'replace_summary':
				return __( 'before the conclusion', 'ai-post-scheduler' );
			case 'add_at_end':
			default:
				return __( 'at the end of the post', 'ai-post-scheduler' );
		}
	}

	/**
	 * @param array<string,mixed> $condition Condition payload.
	 * @return string
	 */
	private function describe_condition( array $condition ) {
		$field    = isset( $condition['field'] ) ? sanitize_key( (string) $condition['field'] ) : 'category';
		$operator = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'is';
		$values   = array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						return trim( (string) $value );
					},
					(array) ( $condition['values'] ?? array() )
				)
			)
		);
		$value_text = implode( ', ', $values );

		$field_labels = array(
			'category'         => __( 'category', 'ai-post-scheduler' ),
			'tag'              => __( 'tag', 'ai-post-scheduler' ),
			'post_type'        => __( 'post type', 'ai-post-scheduler' ),
			'keyword'          => __( 'keyword', 'ai-post-scheduler' ),
			'title_contains'   => __( 'title', 'ai-post-scheduler' ),
			'author_persona'   => __( 'author persona', 'ai-post-scheduler' ),
			'persona'          => __( 'author persona', 'ai-post-scheduler' ),
			'region'           => __( 'region', 'ai-post-scheduler' ),
			'locale'           => __( 'locale', 'ai-post-scheduler' ),
			'content_length'   => __( 'content length', 'ai-post-scheduler' ),
			'heading_presence' => __( 'heading presence', 'ai-post-scheduler' ),
			'has_h2'           => __( 'H2 headings', 'ai-post-scheduler' ),
		);

		$operator_labels = array(
			'is'               => __( 'is', 'ai-post-scheduler' ),
			'is_not'           => __( 'is not', 'ai-post-scheduler' ),
			'contains'         => __( 'contains', 'ai-post-scheduler' ),
			'does_not_contain' => __( 'does not contain', 'ai-post-scheduler' ),
			'starts_with'      => __( 'starts with', 'ai-post-scheduler' ),
			'ends_with'        => __( 'ends with', 'ai-post-scheduler' ),
			'gte'              => __( 'is at least', 'ai-post-scheduler' ),
			'lte'              => __( 'is at most', 'ai-post-scheduler' ),
			'gt'               => __( 'is greater than', 'ai-post-scheduler' ),
			'lt'               => __( 'is less than', 'ai-post-scheduler' ),
		);

		return sprintf(
			'%1$s %2$s "%3$s"',
			$field_labels[ $field ] ?? $field,
			$operator_labels[ $operator ] ?? $operator,
			$value_text
		);
	}

	/**
	 * @param array<string,mixed> $date_window Date window payload.
	 * @return string
	 */
	private function describe_date_window( array $date_window ) {
		$start = ! empty( $date_window['start'] ) ? (string) $date_window['start'] : '';
		$end   = ! empty( $date_window['end'] ) ? (string) $date_window['end'] : '';

		if ( $start && $end ) {
			return sprintf( __( 'between %1$s and %2$s', 'ai-post-scheduler' ), $start, $end );
		}

		if ( $start ) {
			return sprintf( __( 'starting %s', 'ai-post-scheduler' ), $start );
		}

		return sprintf( __( 'until %s', 'ai-post-scheduler' ), $end );
	}
}
