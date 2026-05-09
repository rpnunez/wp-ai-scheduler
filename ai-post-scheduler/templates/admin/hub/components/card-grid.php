<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<section class="aips-hub-section">
	<?php if (!empty($section_title) || !empty($section_description)) : ?>
		<div class="aips-hub-section-header">
			<?php if (!empty($section_title)) : ?>
				<h2 class="aips-hub-section-title"><?php echo esc_html($section_title); ?></h2>
			<?php endif; ?>
			<?php if (!empty($section_description)) : ?>
				<p class="aips-hub-section-description"><?php echo esc_html($section_description); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="aips-hub-card-grid">
		<?php foreach ($cards as $card) : ?>
			<article class="aips-hub-card">
				<?php if (!empty($card['eyebrow'])) : ?>
					<p class="aips-hub-card-eyebrow"><?php echo esc_html($card['eyebrow']); ?></p>
				<?php endif; ?>

				<h3 class="aips-hub-card-title"><?php echo esc_html($card['title']); ?></h3>
				<p class="aips-hub-card-description"><?php echo esc_html($card['description']); ?></p>

				<?php if (!empty($card['items']) && is_array($card['items'])) : ?>
					<ul class="aips-hub-card-list">
						<?php foreach ($card['items'] as $item) : ?>
							<li><?php echo esc_html($item); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if (!empty($card['actions']) && is_array($card['actions'])) : ?>
					<div class="aips-hub-card-actions">
						<?php foreach ($card['actions'] as $action) : ?>
							<a
								class="button<?php echo !empty($action['primary']) ? ' button-primary' : ''; ?>"
								href="<?php echo esc_url($action['href']); ?>"
							>
								<?php echo esc_html($action['label']); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
</section>
