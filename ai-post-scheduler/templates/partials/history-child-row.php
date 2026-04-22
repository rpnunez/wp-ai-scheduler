<?php
/**
 * Partial template: History child row (nested under a parent operation).
 *
 * Variables:
 *   $item {object} Child history record.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status       = isset( $item->status ) ? $item->status : 'processing';
$status_label = ucfirst( $status );

switch ( $status ) {
	case 'completed':
		$status_label = __( 'Completed', 'ai-post-scheduler' );
		break;
	case 'error':
	case 'failed':
		$status_label = __( 'Error', 'ai-post-scheduler' );
		$status       = 'error';
		break;
	case 'processing':
		$status_label = __( 'Processing', 'ai-post-scheduler' );
		break;
}

$title = ! empty( $item->generated_title )
	? $item->generated_title
	: ( ! empty( $item->template_name ) ? $item->template_name : __( '(no title)', 'ai-post-scheduler' ) );

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
?>
<tr class="aips-history-child-row aips-history-status-<?php echo esc_attr( $status ); ?>"
	data-parent-id="<?php echo esc_attr( (int) $item->parent_id ); ?>">
	<td class="aips-col-toggle"></td>
	<td class="aips-col-operation aips-cell-indent">
		<?php echo esc_html( $title ); ?>
	</td>
	<td class="aips-col-children">—</td>
	<td class="aips-col-status">
		<span class="aips-status-badge aips-status-<?php echo esc_attr( $status ); ?>">
			<?php echo esc_html( $status_label ); ?>
		</span>
	</td>
	<td class="aips-col-trigger">—</td>
	<td class="aips-col-date">—</td>
	<td class="aips-col-duration"><?php echo esc_html( $duration_text ); ?></td>
	<td class="aips-col-actions">
		<button class="button-link aips-view-history-logs"
			data-history-id="<?php echo esc_attr( (int) $item->id ); ?>">
			<?php esc_html_e( 'View', 'ai-post-scheduler' ); ?>
		</button>
		<?php if ( ! empty( $item->post_id ) ) : ?>
			<a href="<?php echo esc_url( get_edit_post_link( (int) $item->post_id, 'raw' ) ); ?>"
				class="button-link" target="_blank">
				<?php esc_html_e( 'Edit Post', 'ai-post-scheduler' ); ?>
			</a>
		<?php endif; ?>
	</td>
</tr>
