<?php
/**
 * Partial template: History parent (operation) row.
 *
 * Variables:
 *   $item          {object} Top-level history record.
 *   $child_summary {object} Aggregate stats from AIPS_History_Repository::get_child_summary().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$op_label = AIPS_History_Operation_Type::get_label( isset( $item->creation_method ) ? $item->creation_method : '' );

// Roll-up status: error if any child failed, completed if all done, else processing.
$total_children   = isset( $child_summary->total ) ? (int) $child_summary->total : 0;
$failed_children  = isset( $child_summary->failed_count ) ? (int) $child_summary->failed_count : 0;
$done_children    = isset( $child_summary->completed_count ) ? (int) $child_summary->completed_count : 0;

if ( $failed_children > 0 ) {
	$rollup_status       = 'error';
	$rollup_status_label = __( 'Partial/Error', 'ai-post-scheduler' );
} elseif ( $item->status === 'completed' ) {
	$rollup_status       = 'completed';
	$rollup_status_label = __( 'Completed', 'ai-post-scheduler' );
} elseif ( $item->status === 'processing' ) {
	$rollup_status       = 'processing';
	$rollup_status_label = __( 'In Progress', 'ai-post-scheduler' );
} else {
	$rollup_status       = esc_html( $item->status );
	$rollup_status_label = esc_html( ucfirst( $item->status ) );
}

// Trigger label.
$trigger = isset( $item->trigger_name ) ? $item->trigger_name : '';
switch ( $trigger ) {
	case 'cron':
		$trigger_label = __( 'Scheduled', 'ai-post-scheduler' );
		break;
	case 'ajax_bulk_generate_topics':
	case 'ajax_bulk_generate_from_queue':
		$trigger_label = __( 'Manual', 'ai-post-scheduler' );
		break;
	default:
		$trigger_label = $trigger ? esc_html( $trigger ) : __( 'Auto', 'ai-post-scheduler' );
}

// Duration.
$duration_text = '—';
if ( ! empty( $item->created_at ) && ! empty( $item->completed_at ) ) {
	$start = (int) $item->created_at;
	$end   = (int) $item->completed_at;
	if ( $start > 0 && $end >= $start ) {
		$seconds = $end - $start;
		if ( $seconds < 60 ) {
			/* translators: %d: seconds */
			$duration_text = sprintf( __( '%ds', 'ai-post-scheduler' ), $seconds );
		} else {
			/* translators: 1: minutes, 2: seconds */
			$duration_text = sprintf( __( '%dm %ds', 'ai-post-scheduler' ), floor( $seconds / 60 ), $seconds % 60 );
		}
	}
}

// Date.
$date_format    = get_option( 'date_format' );
$time_format    = get_option( 'time_format' );
$formatted_date = $item->created_at ? date_i18n( $date_format . ' ' . $time_format, (int) $item->created_at ) : '—';

// Child summary text.
$children_text = $total_children > 0
	? sprintf(
		/* translators: 1: done count, 2: total */
		__( '%1$d / %2$d', 'ai-post-scheduler' ),
		$done_children,
		$total_children
	)
	: '—';
?>
<tr class="aips-history-parent-row aips-history-status-<?php echo esc_attr( $rollup_status ); ?>"
	data-id="<?php echo esc_attr( (int) $item->id ); ?>"
	data-expanded="0">
	<td class="aips-col-toggle">
		<?php if ( $total_children > 0 ) : ?>
			<button class="aips-toggle-children button-link" aria-label="<?php esc_attr_e( 'Expand children', 'ai-post-scheduler' ); ?>">
				<span class="dashicons dashicons-arrow-right"></span>
			</button>
		<?php endif; ?>
	</td>
	<td class="aips-col-operation">
		<strong><?php echo esc_html( $op_label ); ?></strong>
	</td>
	<td class="aips-col-children"><?php echo esc_html( $children_text ); ?></td>
	<td class="aips-col-status">
		<span class="aips-status-badge aips-status-<?php echo esc_attr( $rollup_status ); ?>">
			<?php echo esc_html( $rollup_status_label ); ?>
		</span>
	</td>
	<td class="aips-col-trigger"><?php echo esc_html( $trigger_label ); ?></td>
	<td class="aips-col-date"><?php echo esc_html( $formatted_date ); ?></td>
	<td class="aips-col-duration"><?php echo esc_html( $duration_text ); ?></td>
	<td class="aips-col-actions">
		<button class="button-link aips-view-history-logs"
			data-history-id="<?php echo esc_attr( (int) $item->id ); ?>">
			<?php esc_html_e( 'View', 'ai-post-scheduler' ); ?>
		</button>
	</td>
</tr>
