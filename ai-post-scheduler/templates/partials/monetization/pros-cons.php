<?php
/**
 * Pros & Cons Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var string   $title Section title.
 * @var string[] $pros  List of positive points.
 * @var string[] $cons  List of negative points.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aips-pros-cons-box">
	<?php if ( ! empty( $title ) ) : ?>
		<h4 class="aips-pros-cons-title"><?php echo esc_html( $title ); ?></h4>
	<?php endif; ?>
	<div class="aips-pros-cons-columns">
		<?php if ( ! empty( $pros ) ) : ?>
			<div class="aips-pros-col">
				<h5 class="aips-col-header pros-header">
					<span class="aips-icon">✓</span> <?php esc_html_e( 'The Good', 'ai-post-scheduler' ); ?>
				</h5>
				<ul class="aips-list pros-list">
					<?php foreach ( $pros as $pro ) : ?>
						<li><?php echo esc_html( $pro ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $cons ) ) : ?>
			<div class="aips-cons-col">
				<h5 class="aips-col-header cons-header">
					<span class="aips-icon">✗</span> <?php esc_html_e( 'The Bad', 'ai-post-scheduler' ); ?>
				</h5>
				<ul class="aips-list cons-list">
					<?php foreach ( $cons as $con ) : ?>
						<li><?php echo esc_html( $con ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
