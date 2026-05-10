<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap aips-wrap">
	<div class="aips-page-container aips-hub-page" data-aips-hub-page="<?php echo esc_attr($hub['slug']); ?>" data-aips-active-tab="<?php echo esc_attr($active_tab_key); ?>"<?php echo !empty($hub['render_active_only']) ? ' data-aips-hub-server-tabs="true"' : ''; ?>>
		<div class="aips-page-header aips-hub-header">
			<?php if (!empty($context_breadcrumbs)) : ?>
				<nav class="aips-hub-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'ai-post-scheduler'); ?>">
					<?php foreach ($context_breadcrumbs as $index => $crumb) : ?>
						<?php if ($index > 0) : ?>
							<span class="aips-hub-breadcrumb-separator" aria-hidden="true">&rsaquo;</span>
						<?php endif; ?>
						<?php if (!empty($crumb['url'])) : ?>
							<a href="<?php echo esc_url($crumb['url']); ?>" class="aips-hub-breadcrumb-link"><?php echo esc_html($crumb['label']); ?></a>
						<?php else : ?>
							<span class="aips-hub-breadcrumb-current"><?php echo esc_html($crumb['label']); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<div class="aips-hub-header-body">
				<div class="aips-hub-header-copy">
					<p class="aips-hub-eyebrow"><?php echo esc_html($context_eyebrow); ?></p>
					<h1 class="aips-hub-context-title"><?php echo esc_html($context_title); ?></h1>
					<p class="aips-hub-context-description"><?php echo esc_html($context_description); ?></p>
				</div>
				<?php if (!empty($context_actions)) : ?>
					<div class="aips-hub-context-actions">
						<?php foreach ($context_actions as $action) : ?>
							<?php
							$attributes = '';
							if (!empty($action['attributes']) && is_array($action['attributes'])) {
								foreach ($action['attributes'] as $attribute_name => $attribute_value) {
									$attributes .= ' ' . sanitize_key($attribute_name) . '="' . esc_attr($attribute_value) . '"';
								}
							}
							?>
							<?php if (!empty($action['url'])) : ?>
								<a href="<?php echo esc_url($action['url']); ?>" class="<?php echo esc_attr($action['class']); ?>"<?php echo !empty($action['id']) ? ' id="' . esc_attr($action['id']) . '"' : ''; ?><?php echo $attributes; ?>>
									<?php if (!empty($action['icon'])) : ?>
										<span class="dashicons <?php echo esc_attr($action['icon']); ?>"></span>
									<?php endif; ?>
									<?php echo esc_html($action['label']); ?>
								</a>
							<?php else : ?>
								<button type="button" class="<?php echo esc_attr($action['class']); ?>"<?php echo !empty($action['id']) ? ' id="' . esc_attr($action['id']) . '"' : ''; ?><?php echo $attributes; ?>>
									<?php if (!empty($action['icon'])) : ?>
										<span class="dashicons <?php echo esc_attr($action['icon']); ?>"></span>
									<?php endif; ?>
									<?php echo esc_html($action['label']); ?>
								</button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if (!empty($context_metrics)) : ?>
				<div class="aips-hub-metrics" aria-label="<?php esc_attr_e('Workspace summary', 'ai-post-scheduler'); ?>">
					<?php foreach ($context_metrics as $metric) : ?>
						<?php
						$metric_tone = !empty($metric['tone']) ? ' aips-hub-metric--' . sanitize_html_class($metric['tone']) : '';
						?>
						<?php if (!empty($metric['url'])) : ?>
							<a href="<?php echo esc_url($metric['url']); ?>" class="aips-hub-metric<?php echo esc_attr($metric_tone); ?>">
								<span class="aips-hub-metric-value"<?php echo !empty($metric['id']) ? ' id="' . esc_attr($metric['id']) . '"' : ''; ?>><?php echo esc_html($metric['value']); ?></span>
								<span class="aips-hub-metric-label"><?php echo esc_html($metric['label']); ?></span>
							</a>
						<?php else : ?>
							<div class="aips-hub-metric<?php echo esc_attr($metric_tone); ?>">
								<span class="aips-hub-metric-value"<?php echo !empty($metric['id']) ? ' id="' . esc_attr($metric['id']) . '"' : ''; ?>><?php echo esc_html($metric['value']); ?></span>
								<span class="aips-hub-metric-label"><?php echo esc_html($metric['label']); ?></span>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<nav class="aips-tab-nav aips-hub-tab-nav" aria-label="<?php echo esc_attr($hub['page_title']); ?>" data-aips-hub-tabs>
			<?php foreach ($tabs as $tab) : ?>
				<?php
				$tab_key        = $tab['key'];
				$panel_id       = 'aips-hub-' . sanitize_html_class($hub['slug'] . '-' . $tab_key) . '-tab';
				$tab_subtabs    = isset($tab['subtabs']) && is_array($tab['subtabs']) ? $tab['subtabs'] : array();
				$default_subtab = !empty($tab_subtabs[0]['key']) ? $tab_subtabs[0]['key'] : '';
				$tab_args       = array(
					'page' => $hub['slug'],
					'tab'  => $tab_key,
				);
				$is_active      = ($active_tab_key === $tab_key);

				if (!empty($default_subtab)) {
					$tab_args['subtab'] = $default_subtab;
				}

				$tab_url = add_query_arg($tab_args, admin_url('admin.php'));
				?>
				<div class="aips-hub-tab-item<?php echo !empty($tab_subtabs) ? ' has-submenu' : ''; ?>">
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
						<span class="aips-hub-tab-text-group">
							<span class="aips-hub-tab-label"><?php echo esc_html($tab['label']); ?></span>
							<?php if ($is_active && !empty($tab_subtabs) && !empty($active_subtab_key)) : ?>
								<?php foreach ($tab_subtabs as $subtab_label_candidate) : ?>
									<?php if ($subtab_label_candidate['key'] === $active_subtab_key) : ?>
										<span class="aips-hub-tab-current-child">/ <?php echo esc_html($subtab_label_candidate['label']); ?></span>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</span>
						<?php if (!empty($tab_subtabs)) : ?>
							<span class="dashicons dashicons-arrow-down-alt2 aips-hub-tab-caret" aria-hidden="true"></span>
						<?php endif; ?>
					</a>

					<?php if (!empty($tab_subtabs)) : ?>
						<div class="aips-hub-tab-submenu">
							<?php foreach ($tab_subtabs as $subtab) : ?>
								<?php
								$subtab_url = add_query_arg(
									array(
										'page'   => $hub['slug'],
										'tab'    => $tab_key,
										'subtab' => $subtab['key'],
									),
									admin_url('admin.php')
								);
								$is_subtab_active = ($is_active && $active_subtab_key === $subtab['key']);
								?>
								<a href="<?php echo esc_url($subtab_url); ?>" class="aips-hub-tab-submenu-link<?php echo $is_subtab_active ? ' active' : ''; ?>">
									<?php echo esc_html($subtab['label']); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
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
					<div class="aips-hub-panel-frame">
						<?php include $tab['partial']; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
</div>
