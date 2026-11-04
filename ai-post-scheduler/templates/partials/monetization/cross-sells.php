<?php
/**
 * Cross-Sells Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var string $title Section title.
 * @var array  $items List of normalized product payloads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aips-cross-sells-container">
	<?php if ( ! empty( $title ) ) : ?>
		<h4 class="aips-cross-sells-title"><?php echo esc_html( $title ); ?></h4>
	<?php endif; ?>
	<div class="aips-cross-sells-grid">
		<?php foreach ( $items as $item ) : ?>
			<div class="aips-cross-sell-item">
				<?php if ( ! empty( $item['image_url'] ) ) : ?>
					<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" class="aips-thumb-md" loading="lazy" />
				<?php endif; ?>
				<h5 class="aips-item-heading"><?php echo esc_html( $item['title'] ); ?></h5>
				<span class="aips-item-cost"><?php echo esc_html( $item['formatted_price'] ); ?></span>
				<a href="<?php echo esc_url( $item['add_to_cart_url'] ); ?>"
				   class="aips-btn aips-btn-sm aips-cta-button"
				   target="_blank"
				   rel="<?php echo $item['is_external'] ? 'nofollow sponsored noopener' : 'noopener'; ?>"
				   data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
				   data-product-title="<?php echo esc_attr( $item['title'] ); ?>"
				   data-product-price="<?php echo esc_attr( $item['price'] ); ?>"
				   data-action-type="<?php echo $item['is_external'] ? 'affiliate' : 'add_to_cart'; ?>">
					<?php echo esc_html( $item['button_text'] ); ?>
				</a>
			</div>
		<?php endforeach; ?>
	</div>
</div>
