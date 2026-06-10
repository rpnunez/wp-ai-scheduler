<?php
/**
 * Automations Admin Template
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap aips-automations-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Automations', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Manage schedules, campaigns, templates, authors, sources, internal links, and taxonomy from one place.', 'ai-post-scheduler'); ?></p>
				</div>
				<?php if (!empty($tab_actions)) : ?>
					<div class="aips-page-actions">
						<?php foreach ($tab_actions as $tab_action) : ?>
							<?php
							$action_type = isset($tab_action['type']) ? $tab_action['type'] : 'button';
							$action_class = isset($tab_action['class']) ? $tab_action['class'] : 'aips-btn aips-btn-secondary';
							$action_icon = isset($tab_action['icon']) ? $tab_action['icon'] : '';
							$action_id = isset($tab_action['id']) ? $tab_action['id'] : '';
							$action_label = isset($tab_action['label']) ? $tab_action['label'] : '';
							$data_attrs = isset($tab_action['data_attrs']) && is_array($tab_action['data_attrs']) ? $tab_action['data_attrs'] : array();
							$data_attr_html = '';
							foreach ($data_attrs as $data_key => $data_value) {
								$data_attr_html .= ' data-' . esc_attr(sanitize_key($data_key)) . '="' . esc_attr($data_value) . '"';
							}
							?>
							<?php if ('link' === $action_type && !empty($tab_action['url'])) : ?>
								<a href="<?php echo esc_url($tab_action['url']); ?>" class="<?php echo esc_attr($action_class); ?>"<?php echo $action_id ? ' id="' . esc_attr($action_id) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $data_attr_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<?php if ($action_icon) : ?>
										<span class="dashicons <?php echo esc_attr($action_icon); ?>"></span>
									<?php endif; ?>
									<?php echo esc_html($action_label); ?>
								</a>
							<?php else : ?>
								<button type="button" class="<?php echo esc_attr($action_class); ?>"<?php echo $action_id ? ' id="' . esc_attr($action_id) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $data_attr_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<?php if ($action_icon) : ?>
										<span class="dashicons <?php echo esc_attr($action_icon); ?>"></span>
									<?php endif; ?>
									<?php echo esc_html($action_label); ?>
								</button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-tab-nav">
			<?php foreach ($tabs as $tab_key => $tab) : ?>
				<?php
				$tab_classes = 'aips-tab-link' . ($active_tab === $tab_key ? ' active' : '');
				if (!empty($tab['special'])) {
					$tab_classes .= ' aips-tab-link-special';
				}
				?>
				<a href="<?php echo esc_url($automations_controller->get_tab_url($tab_key)); ?>" class="<?php echo esc_attr($tab_classes); ?>">
					<?php echo esc_html($tab['label']); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="aips-automations-tab-content">
			<?php $automations_controller->render_tab_content($active_tab); ?>
		</div>
	</div>
</div>
