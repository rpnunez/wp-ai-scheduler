<?php
/**
 * Product Card Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var array  $item        Normalized product payload.
 * @var string $badge       Badge text.
 * @var string $layout      Layout type: 'card', 'compact', 'horizontal'.
 * @var string $button_text Button text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aips-product-card aips-layout-<?php echo esc_attr( $layout ); ?>" data-product-id="<?php echo esc_attr( $item['id'] ); ?>">
	<?php if ( ! empty( $badge ) ) : ?>
		<div class="aips-product-badge"><?php echo esc_html( $badge ); ?></div>
	<?php endif; ?>

	<div class="aips-card-inner">
		<?php if ( ! empty( $item['image_url'] ) ) : ?>
			<div class="aips-product-image-wrap">
				<a href="<?php echo esc_url( $item['permalink'] ); ?>" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy" class="aips-product-img" />
				</a>
			</div>
		<?php endif; ?>

		<div class="aips-product-details">
			<h3 class="aips-product-title">
				<a href="<?php echo esc_url( $item['permalink'] ); ?>" class="aips-product-link">
					<?php echo esc_html( $item['title'] ); ?>
				</a>
			</h3>

			<?php if ( ! empty( $item['rating'] ) ) : ?>
				<div class="aips-product-rating" aria-label="<?php echo esc_attr( sprintf( __( 'Rating: %s out of 5', 'ai-post-scheduler' ), $item['rating'] ) ); ?>">
					<span class="aips-stars">
						<?php
						$rating = min( 5, max( 0, (float) $item['rating'] ) );
						for ( $i = 1; $i <= 5; $i++ ) {
							echo ( $rating >= $i ) ? '★' : ( ( $rating >= $i - 0.5 ) ? '⯨' : '☆' );
						}
						?>
					</span>
					<span class="aips-rating-val"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $item['short_description'] ) ) : ?>
				<div class="aips-product-desc">
					<?php echo wp_kses_post( wpautop( $item['short_description'] ) ); ?>
				</div>
			<?php endif; ?>

			<div class="aips-product-action-row">
				<div class="aips-price-box">
					<?php if ( ! empty( $item['formatted_price'] ) ) : ?>
						<span class="aips-current-price"><?php echo esc_html( $item['formatted_price'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $item['in_stock'] ) ) : ?>
						<span class="aips-stock-badge in-stock"><?php esc_html_e( 'In Stock', 'ai-post-scheduler' ); ?></span>
					<?php endif; ?>
				</div>

				<a href="<?php echo esc_url( $item['add_to_cart_url'] ); ?>"
				   class="aips-btn aips-btn-primary aips-cta-button"
				   target="_blank"
				   rel="<?php echo $item['is_external'] ? 'nofollow sponsored noopener' : 'noopener'; ?>"
				   data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
				   data-product-title="<?php echo esc_attr( $item['title'] ); ?>"
				   data-product-price="<?php echo esc_attr( $item['price'] ); ?>"
				   data-action-type="<?php echo $item['is_external'] ? 'affiliate' : 'add_to_cart'; ?>">
					<?php echo esc_html( $button_text ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
