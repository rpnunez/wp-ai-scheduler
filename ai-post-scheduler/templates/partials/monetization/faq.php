<?php
/**
 * FAQ Accordion Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 *
 * @var string $title     FAQ section title.
 * @var array  $faq_pairs List of array{question: string, answer: string}.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aips-faq-container">
	<?php if ( ! empty( $title ) ) : ?>
		<h3 class="aips-faq-main-title"><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>
	<div class="aips-faq-accordion">
		<?php foreach ( $faq_pairs as $idx => $pair ) : ?>
			<details class="aips-faq-item" <?php echo 0 === $idx ? 'open' : ''; ?>>
				<summary class="aips-faq-question">
					<strong><?php echo esc_html( $pair['question'] ); ?></strong>
				</summary>
				<div class="aips-faq-answer">
					<p><?php echo esc_html( $pair['answer'] ); ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</div>
