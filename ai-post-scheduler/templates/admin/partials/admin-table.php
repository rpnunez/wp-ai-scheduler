<?php
/**
 * Admin Table Partial Component
 *
 * Renders a standardized, responsive high-density data table supporting progressive disclosure:
 * - Select All / Row Checkboxes
 * - Bulk Action Toolbar (selection counter & action dropdown)
 * - Row Action Kebab Menu
 * - Expandable Row Details Drawer
 * - Filter Persistence markers
 *
 * @var array<string, mixed> $args Configuration arguments passed from AIPS_Admin_UI_Primitives::render_table().
 *
 * @package AI_Post_Scheduler
 * @since   3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$table_id           = isset($args['id']) ? $args['id'] : '';
$table_class        = isset($args['class']) ? 'aips-table ' . $args['class'] : 'aips-table';
$columns            = isset($args['columns']) && is_array($args['columns']) ? $args['columns'] : array();
$rows               = isset($args['rows']) && is_array($args['rows']) ? $args['rows'] : array();
$bulk_actions       = isset($args['bulk_actions']) && is_array($args['bulk_actions']) ? $args['bulk_actions'] : array();
$persist_filters    = !isset($args['persist_filters']) || (bool) $args['persist_filters'];
$empty_state        = isset($args['empty_state']) && is_array($args['empty_state']) ? $args['empty_state'] : array();
$footer_count       = isset($args['footer_count']) ? $args['footer_count'] : null;
$pagination         = isset($args['pagination']) ? $args['pagination'] : null;
$aria_label         = isset($args['aria_label']) ? $args['aria_label'] : __('Data Table', 'ai-post-scheduler');
$has_bulk           = !empty($bulk_actions);
$has_expandable     = false;

foreach ($rows as $r) {
	if (!empty($r['details'])) {
		$has_expandable = true;
		break;
	}
}
?>

<div class="aips-table-wrap" <?php echo !empty($table_id) ? 'id="' . esc_attr($table_id . '-wrap') . '"' : ''; ?>
     data-persist-filters="<?php echo $persist_filters ? 'true' : 'false'; ?>"
     <?php echo !empty($table_id) ? 'data-table-id="' . esc_attr($table_id) . '"' : ''; ?>>

	<?php if ($has_bulk) : ?>
		<div class="aips-table-bulk-toolbar" id="<?php echo esc_attr($table_id ? $table_id . '-bulk-toolbar' : 'aips-bulk-toolbar'); ?>" hidden>
			<div class="aips-bulk-toolbar-info">
				<span class="aips-bulk-selected-count">0</span>
				<span><?php esc_html_e('items selected', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-bulk-toolbar-actions">
				<label for="<?php echo esc_attr($table_id ? $table_id . '-bulk-action' : 'aips-bulk-action'); ?>" class="screen-reader-text">
					<?php esc_html_e('Select bulk action', 'ai-post-scheduler'); ?>
				</label>
				<select id="<?php echo esc_attr($table_id ? $table_id . '-bulk-action' : 'aips-bulk-action'); ?>" class="aips-form-select aips-bulk-action-select">
					<option value=""><?php esc_html_e('Bulk actions', 'ai-post-scheduler'); ?></option>
					<?php foreach ($bulk_actions as $action_key => $action_label) : ?>
						<option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-apply-bulk-action">
					<?php esc_html_e('Apply', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<?php if (!empty($rows)) : ?>
		<table class="<?php echo esc_attr($table_class); ?>" <?php echo !empty($table_id) ? 'id="' . esc_attr($table_id) . '"' : ''; ?> aria-label="<?php echo esc_attr($aria_label); ?>">
			<thead>
				<tr>
					<?php if ($has_bulk) : ?>
						<th scope="col" class="aips-col-cb">
							<input type="checkbox" class="aips-select-all-cb" id="<?php echo esc_attr($table_id ? $table_id . '-select-all' : 'aips-select-all'); ?>" aria-label="<?php esc_attr_e('Select All Items', 'ai-post-scheduler'); ?>">
						</th>
					<?php endif; ?>
					<?php if ($has_expandable) : ?>
						<th scope="col" class="aips-col-expand">
							<span class="screen-reader-text"><?php esc_html_e('Expand details', 'ai-post-scheduler'); ?></span>
						</th>
					<?php endif; ?>
					<?php foreach ($columns as $col_key => $col_data) :
						$col_label = is_array($col_data) ? (isset($col_data['label']) ? $col_data['label'] : '') : $col_data;
						$col_class = is_array($col_data) && isset($col_data['class']) ? ' ' . $col_data['class'] : '';
					?>
						<th scope="col" class="<?php echo esc_attr('aips-col-' . sanitize_html_class($col_key) . $col_class); ?>">
							<?php echo esc_html($col_label); ?>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $row_index => $row) :
					$row_id        = isset($row['id']) ? $row['id'] : 'row-' . $row_index;
					$row_class     = isset($row['class']) ? ' ' . $row['class'] : '';
					$row_details   = isset($row['details']) ? $row['details'] : '';
					$row_cells     = isset($row['cells']) && is_array($row['cells']) ? $row['cells'] : array();
					$row_actions   = isset($row['actions']) && is_array($row['actions']) ? $row['actions'] : array();
					$row_cb_val    = isset($row['checkbox_value']) ? $row['checkbox_value'] : $row_id;
					$row_cb_label  = isset($row['checkbox_label']) ? $row['checkbox_label'] : sprintf(__('Select item %s', 'ai-post-scheduler'), $row_id);
				?>
					<tr class="aips-table-row<?php echo esc_attr($row_class); ?>" id="<?php echo esc_attr('aips-row-' . $row_id); ?>" data-row-id="<?php echo esc_attr($row_id); ?>">
						<?php if ($has_bulk) : ?>
							<td class="aips-col-cb">
								<input type="checkbox" class="aips-row-cb" value="<?php echo esc_attr($row_cb_val); ?>" aria-label="<?php echo esc_attr($row_cb_label); ?>">
							</td>
						<?php endif; ?>

						<?php if ($has_expandable) : ?>
							<td class="aips-col-expand">
								<?php if (!empty($row_details)) : ?>
									<button type="button" class="aips-btn-icon aips-row-expand-toggle"
											aria-expanded="false"
											aria-controls="<?php echo esc_attr('aips-details-' . $row_id); ?>"
											aria-label="<?php esc_attr_e('Toggle row details', 'ai-post-scheduler'); ?>"
											title="<?php esc_attr_e('Expand details', 'ai-post-scheduler'); ?>">
										<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
									</button>
								<?php endif; ?>
							</td>
						<?php endif; ?>

						<?php foreach ($columns as $col_key => $col_data) :
							$cell_content = isset($row_cells[$col_key]) ? $row_cells[$col_key] : '';
							if ('actions' === $col_key && !empty($row_actions)) {
								$primary_action  = isset($row_actions['primary']) ? $row_actions['primary'] : null;
								$overflow_actions = isset($row_actions['overflow']) && is_array($row_actions['overflow']) ? $row_actions['overflow'] : array();
								?>
								<td class="cell-actions">
									<div class="cell-actions-wrapper">
										<div class="aips-row-action-group">
											<?php if ($primary_action) :
												$p_label = isset($primary_action['label']) ? $primary_action['label'] : '';
												$p_icon  = isset($primary_action['icon']) ? $primary_action['icon'] : '';
												$p_class = isset($primary_action['class']) ? $primary_action['class'] : 'aips-btn aips-btn-sm aips-btn-secondary';
												$p_url   = isset($primary_action['url']) ? $primary_action['url'] : '';
												$p_attrs = isset($primary_action['data_attrs']) ? $primary_action['data_attrs'] : array();
												$p_attr_str = '';
												foreach ($p_attrs as $ak => $av) {
													$p_attr_str .= ' data-' . esc_attr($ak) . '="' . esc_attr($av) . '"';
												}
											?>
												<?php if (!empty($p_url)) : ?>
													<a href="<?php echo esc_url($p_url); ?>" class="<?php echo esc_attr($p_class); ?>"<?php echo $p_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
														<?php if (!empty($p_icon)) : ?>
															<span class="dashicons <?php echo esc_attr($p_icon); ?>" aria-hidden="true"></span>
														<?php endif; ?>
														<?php echo esc_html($p_label); ?>
													</a>
												<?php else : ?>
													<button type="button" class="<?php echo esc_attr($p_class); ?>"<?php echo $p_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
														<?php if (!empty($p_icon)) : ?>
															<span class="dashicons <?php echo esc_attr($p_icon); ?>" aria-hidden="true"></span>
														<?php endif; ?>
														<?php echo esc_html($p_label); ?>
													</button>
												<?php endif; ?>
											<?php endif; ?>

											<?php if (!empty($overflow_actions)) : ?>
												<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
														aria-haspopup="true"
														aria-expanded="false"
														aria-controls="<?php echo esc_attr('aips-row-menu-' . $row_id); ?>"
														aria-label="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>"
														title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
												</button>
											<?php endif; ?>
										</div>

										<?php if (!empty($overflow_actions)) : ?>
											<div id="<?php echo esc_attr('aips-row-menu-' . $row_id); ?>" class="aips-row-action-menu" hidden>
												<?php foreach ($overflow_actions as $o_action) :
													$o_label = isset($o_action['label']) ? $o_action['label'] : '';
													$o_icon  = isset($o_action['icon']) ? $o_action['icon'] : '';
													$o_class = isset($o_action['class']) ? $o_action['class'] : 'aips-row-action-item';
													$o_url   = isset($o_action['url']) ? $o_action['url'] : '';
													$o_attrs = isset($o_action['data_attrs']) ? $o_action['data_attrs'] : array();
													$o_attr_str = '';
													foreach ($o_attrs as $oak => $oav) {
														$o_attr_str .= ' data-' . esc_attr($oak) . '="' . esc_attr($oav) . '"';
													}
												?>
													<?php if (!empty($o_url)) : ?>
														<a href="<?php echo esc_url($o_url); ?>" class="<?php echo esc_attr($o_class); ?>"<?php echo $o_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
															<?php if (!empty($o_icon)) : ?>
																<span class="dashicons <?php echo esc_attr($o_icon); ?>" aria-hidden="true"></span>
															<?php endif; ?>
															<span><?php echo esc_html($o_label); ?></span>
														</a>
													<?php else : ?>
														<button type="button" class="<?php echo esc_attr($o_class); ?>"<?php echo $o_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
															<?php if (!empty($o_icon)) : ?>
																<span class="dashicons <?php echo esc_attr($o_icon); ?>" aria-hidden="true"></span>
															<?php endif; ?>
															<span><?php echo esc_html($o_label); ?></span>
														</button>
													<?php endif; ?>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>
								</td>
							<?php } else { ?>
								<td class="<?php echo esc_attr('aips-col-' . sanitize_html_class($col_key)); ?>">
									<?php echo $cell_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</td>
							<?php } ?>
						<?php endforeach; ?>
					</tr>

					<?php if (!empty($row_details)) :
						$total_cols = count($columns) + ($has_bulk ? 1 : 0) + ($has_expandable ? 1 : 0);
					?>
						<tr class="aips-row-details" id="<?php echo esc_attr('aips-details-' . $row_id); ?>" hidden>
							<td colspan="<?php echo esc_attr($total_cols); ?>">
								<div class="aips-row-details-content">
									<?php echo $row_details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<?php
		if (!empty($empty_state)) {
			AIPS_Admin_UI_Primitives::render_empty_state($empty_state);
		} else {
			AIPS_Admin_UI_Primitives::render_empty_state(array(
				'icon'    => 'dashicons-database',
				'title'   => __('No Items Found', 'ai-post-scheduler'),
				'message' => __('No records exist or match your current filter parameters.', 'ai-post-scheduler'),
			));
		}
		?>
	<?php endif; ?>

	<?php if ($footer_count !== null || !empty($pagination)) : ?>
		<div class="tablenav aips-table-footer">
			<?php if ($footer_count !== null) : ?>
				<span class="aips-table-footer-count">
					<?php echo esc_html($footer_count); ?>
				</span>
			<?php endif; ?>
			<?php if (!empty($pagination)) : ?>
				<div class="aips-table-pagination">
					<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
