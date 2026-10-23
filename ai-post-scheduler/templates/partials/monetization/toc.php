<?php
/**
 * Table of Contents Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var string $title TOC Title.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aips-toc-container" data-aips-toc="true">
	<div class="aips-toc-header"><?php echo esc_html( $title ); ?></div>
	<nav class="aips-toc-list"></nav>
</div>
