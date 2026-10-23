<?php
/**
 * FTC Affiliate Disclosure Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var string $text Disclosure text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aips-affiliate-disclosure">
	<span class="aips-disclosure-icon">ℹ</span> <?php echo esc_html( $text ); ?>
</div>
