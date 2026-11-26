<?php
/**
 * Summarized Digest Items List Template
 *
 * Renders the grouped tables for batched notification digest items.
 *
 * Expected variables:
 *   @var array $grouped Grouped notification items keyed by type slug, each having 'label' and 'items'.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

if (empty($grouped) || !is_array($grouped)) {
	return;
}

foreach ($grouped as $type_key => $group) :
	$group_count = !empty($group['items']) && is_array($group['items']) ? count($group['items']) : 0;
	$group_label = !empty($group['label']) ? $group['label'] : ucwords(str_replace('_', ' ', (string) $type_key));
?>
<div style="margin-top:20px;margin-bottom:8px;">
	<h3 style="margin:0 0 10px 0;font-size:15px;color:#1d2327;border-bottom:2px solid #2271b1;padding-bottom:4px;">
		<?php echo esc_html($group_label); ?>
		<span style="font-weight:normal;color:#646970;font-size:13px;">(<?php echo esc_html($group_count); ?>)</span>
	</h3>
	<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #dcdcde;border-collapse:collapse;background:#ffffff;margin-bottom:15px;border-radius:4px;overflow:hidden;">
		<?php
		if (!empty($group['items']) && is_array($group['items'])) :
			foreach ($group['items'] as $idx => $entry) :
				$bg_color     = ($idx % 2 === 0) ? '#ffffff' : '#f6f7f7';
				$entry_title  = !empty($entry['title']) ? $entry['title'] : __('Untitled', 'ai-post-scheduler');
				$entry_meta   = isset($entry['meta']) && is_array($entry['meta']) ? $entry['meta'] : array();
				$entry_url    = !empty($entry['url']) ? $entry['url'] : '';
				$status_label = !empty($entry_meta['post_status_label']) ? $entry_meta['post_status_label'] : (!empty($entry_meta['post_status']) ? ucfirst($entry_meta['post_status']) : '');
				$source_label = !empty($entry_meta['source_label']) ? $entry_meta['source_label'] : '';
		?>
		<tr style="background:<?php echo esc_attr($bg_color); ?>;border-bottom:1px solid #dcdcde;">
			<td style="padding:10px 12px;font-size:14px;color:#1d2327;">
				<strong><?php echo esc_html($entry_title); ?></strong>
				<?php if ($source_label || $status_label) : ?>
					<div style="font-size:12px;color:#646970;margin-top:3px;">
						<?php if ($source_label) : ?>
							<span><?php echo esc_html($source_label); ?></span>
						<?php endif; ?>
						<?php if ($source_label && $status_label) : ?>
							&middot;
						<?php endif; ?>
						<?php if ($status_label) : ?>
							<span style="display:inline-block;padding:1px 6px;border-radius:3px;background:#e2e4e7;font-size:11px;font-weight:600;color:#2c3338;"><?php echo esc_html($status_label); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</td>
			<td style="padding:10px 12px;text-align:right;white-space:nowrap;font-size:13px;">
				<?php if (!empty($entry_url)) : ?>
					<a href="<?php echo esc_url($entry_url); ?>" style="display:inline-block;padding:4px 10px;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:3px;font-weight:500;font-size:12px;"><?php echo esc_html__('View / Edit', 'ai-post-scheduler'); ?></a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
			endforeach;
		endif;
		?>
	</table>
</div>
<?php endforeach; ?>
