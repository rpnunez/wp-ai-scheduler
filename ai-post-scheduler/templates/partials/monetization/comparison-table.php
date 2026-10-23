<?php
/**
 * Comparison Table Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var array  $items List of normalized product payloads with 'badge'.
 * @var string $title Table section title.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aips-comparison-wrapper">
	<?php if ( ! empty( $title ) ) : ?>
		<h3 class="aips-comparison-title"><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>

	<div class="aips-table-responsive">
		<table class="aips-comparison-table">
			<thead>
				<tr>
					<th class="aips-col-item"><?php esc_html_e( 'Product', 'ai-post-scheduler' ); ?></th>
					<th class="aips-col-rating"><?php esc_html_e( 'Rating', 'ai-post-scheduler' ); ?></th>
					<th class="aips-col-price"><?php esc_html_e( 'Price', 'ai-post-scheduler' ); ?></th>
					<th class="aips-col-action"><?php esc_html_e( 'Details', 'ai-post-scheduler' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr class="aips-table-row <?php echo ! empty( $item['badge'] ) ? 'aips-has-badge' : ''; ?>" data-product-id="<?php echo esc_attr( $item['id'] ); ?>">
						<td class="aips-product-cell">
							<?php if ( ! empty( $item['badge'] ) ) : ?>
								<span class="aips-table-badge"><?php echo esc_html( $item['badge'] ); ?></span>
							<?php endif; ?>
							<div class="aips-cell-content">
								<?php if ( ! empty( $item['image_url'] ) ) : ?>
									<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" class="aips-thumb-sm" loading="lazy" />
								<?php endif; ?>
								<span class="aips-item-name"><?php echo esc_html( $item['title'] ); ?></span>
							</div>
						</td>
						<td class="aips-rating-cell">
							<span class="aips-stars-sm">★</span> <?php echo esc_html( number_format( (float) $item['rating'], 1 ) ); ?>
						</td>
						<td class="aips-price-cell">
							<strong><?php echo esc_html( $item['formatted_price'] ); ?></strong>
						</td>
						<td class="aips-action-cell">
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
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
