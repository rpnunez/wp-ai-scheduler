<?php
/**
 * Automations Admin Template (Vertical Sidebar Rail Layout)
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Automations_Controller $automations_controller */
/** @var string $active_tab */
/** @var array<string, array{label:string, icon:string, description?:string, special?:bool}> $tabs */
/** @var array<int, array<string, mixed>> $tab_actions */
?>
<div class="wrap aips-wrap aips-automations-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-rest-api" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;color:#2271b1;"></span>
						<?php esc_html_e('Automations', 'ai-post-scheduler'); ?>
					</h1>
					<p class="aips-page-description">
						<?php esc_html_e('Orchestrate generation schedules, goal-based campaigns, authors, data sources, monetization, and SEO linking.', 'ai-post-scheduler'); ?>
					</p>
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

		<!-- Vertical Sidebar Rail Layout -->
		<div class="aips-rail-layout">
			<nav class="aips-rail-sidebar" aria-label="<?php esc_attr_e('Automations Navigation', 'ai-post-scheduler'); ?>">
				<ul class="aips-rail-nav">
					<?php foreach ($tabs as $tab_key => $tab) : ?>
						<?php
						$is_active = ($active_tab === $tab_key);
						$item_classes = 'aips-rail-item' . ($is_active ? ' active' : '');
						if (!empty($tab['special'])) {
							$item_classes .= ' aips-rail-item-special';
						}
						?>
						<li>
							<a href="<?php echo esc_url($automations_controller->get_tab_url($tab_key)); ?>" class="<?php echo esc_attr($item_classes); ?>">
								<span class="dashicons <?php echo esc_attr($tab['icon']); ?> aips-rail-icon"></span>
								<span class="aips-rail-text">
									<span class="aips-rail-title"><?php echo esc_html($tab['label']); ?></span>
									<?php if (!empty($tab['description'])) : ?>
										<span class="aips-rail-desc"><?php echo esc_html($tab['description']); ?></span>
									<?php endif; ?>
								</span>
								<span class="dashicons dashicons-arrow-right-alt2 aips-rail-arrow"></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<main class="aips-rail-main">
				<div class="aips-automations-stage">
					<?php $automations_controller->render_tab_content($active_tab); ?>
				</div>
			</main>
		</div>

	</div>
</div>
