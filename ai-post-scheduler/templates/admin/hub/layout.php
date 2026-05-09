<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap aips-wrap">
	<div class="aips-page-container aips-hub-page" data-aips-hub-page="<?php echo esc_attr($hub['slug']); ?>" data-aips-active-tab="<?php echo esc_attr($active_tab_key); ?>"<?php echo !empty($hub['render_active_only']) ? ' data-aips-hub-server-tabs="true"' : ''; ?>>
		<div class="aips-page-header aips-hub-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php echo esc_html($hub['page_title']); ?></h1>
					<p class="aips-page-description"><?php echo esc_html($hub['description']); ?></p>
				</div>
			</div>
		</div>

		<nav class="aips-tab-nav aips-hub-tab-nav" aria-label="<?php echo esc_attr($hub['page_title']); ?>" data-aips-hub-tabs>
			<?php foreach ($tabs as $tab) : ?>
				<?php
				$tab_key   = $tab['key'];
				$panel_id  = 'aips-hub-' . sanitize_html_class($hub['slug'] . '-' . $tab_key) . '-tab';
				$tab_url   = add_query_arg(
					array(
						'page' => $hub['slug'],
						'tab'  => $tab_key,
					),
					admin_url('admin.php')
				);
				$is_active = ($active_tab_key === $tab_key);
				?>
				<a
					href="<?php echo esc_url($tab_url); ?>"
					class="aips-hub-tab-link<?php echo $is_active ? ' active' : ''; ?>"
					data-aips-hub-tab-link
					data-tab="<?php echo esc_attr($panel_id); ?>"
					data-tab-key="<?php echo esc_attr($tab_key); ?>"
					role="tab"
					aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
					aria-controls="<?php echo esc_attr($panel_id); ?>"
					tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
				>
					<?php echo esc_html($tab['label']); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="aips-hub-panels">
			<?php foreach ($tabs as $tab) : ?>
				<?php
				$panel_id  = 'aips-hub-' . sanitize_html_class($hub['slug'] . '-' . $tab['key']) . '-tab';
				$is_active = ($active_tab_key === $tab['key']);
				?>
				<?php if (!empty($hub['render_active_only']) && !$is_active) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<section
					id="<?php echo esc_attr($panel_id); ?>"
					class="aips-hub-panel aips-tab-content<?php echo $is_active ? ' active' : ''; ?>"
					data-aips-hub-panel
					<?php echo (!empty($hub['render_active_only']) || $is_active) ? '' : 'hidden="hidden"'; ?>
					aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
					role="tabpanel"
				>
					<?php include $tab['partial']; ?>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
</div>
