<?php
/**
 * History Modal Content Template
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (empty($container) || !is_array($container)) {
	echo '<p>' . esc_html__('No history data available.', 'ai-post-scheduler') . '</p>';
	return;
}

$logs = !empty($logs) && is_array($logs) ? $logs : array();

$normalize_phase_key = static function ($value) {
	return trim((string) preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $value))), '_');
};

$derive_ai_phase_key = static function ($log) use ($normalize_phase_key) {
	$details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
	$candidates = array(
		$details['phase'] ?? '',
		$details['component'] ?? '',
		$details['content_type'] ?? '',
		$details['request_type'] ?? '',
		$details['target'] ?? '',
		$details['section'] ?? '',
		$details['field'] ?? '',
		$details['item_type'] ?? '',
		$details['stage'] ?? '',
	);

	foreach ($candidates as $candidate) {
		if ($candidate !== '') {
			return $normalize_phase_key($candidate);
		}
	}

	$message = isset($details['message']) ? (string) $details['message'] : '';
	if ($message !== '' && preg_match('/for\s+(.+?)(?:[\.:]|$)/i', $message, $matches)) {
		return $normalize_phase_key($matches[1]);
	}

	$normalized_message = strtolower($message);
	if (strpos($normalized_message, 'title') !== false) {
		return 'post_title';
	}
	if (strpos($normalized_message, 'excerpt') !== false) {
		return 'post_excerpt';
	}
	if (strpos($normalized_message, 'featured image') !== false || strpos($normalized_message, 'image') !== false) {
		return 'featured_image';
	}
	if (strpos($normalized_message, 'content') !== false || strpos($normalized_message, 'article') !== false) {
		return 'post_content';
	}

	return 'general';
};

$humanize_ai_phase_label = static function ($phase_key) use ($normalize_phase_key) {
	$normalized = $normalize_phase_key($phase_key ?: 'general');
	$map = array(
		'post_title' => __('Post Title', 'ai-post-scheduler'),
		'title' => __('Post Title', 'ai-post-scheduler'),
		'post_content' => __('Post Content', 'ai-post-scheduler'),
		'content' => __('Post Content', 'ai-post-scheduler'),
		'article' => __('Post Content', 'ai-post-scheduler'),
		'body' => __('Post Content', 'ai-post-scheduler'),
		'post_excerpt' => __('Post Excerpt', 'ai-post-scheduler'),
		'excerpt' => __('Post Excerpt', 'ai-post-scheduler'),
		'featured_image' => __('Featured Image', 'ai-post-scheduler'),
		'image' => __('Featured Image', 'ai-post-scheduler'),
		'topic' => __('Topic', 'ai-post-scheduler'),
		'research' => __('Research', 'ai-post-scheduler'),
		'general' => __('General', 'ai-post-scheduler'),
	);

	if (isset($map[$normalized])) {
		return $map[$normalized];
	}

	return ucwords(str_replace('_', ' ', $normalized));
};

$extract_extra_details = static function ($log) {
	$details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
	unset($details['message'], $details['timestamp']);
	return $details;
};

$render_text = static function ($value) {
	return nl2br(esc_html((string) $value));
};

$render_json_scalar = static function ($value) use ($render_text) {
	if ($value === null) {
		return '<span class="aips-json-value aips-json-value-null">null</span>';
	}

	if (is_string($value)) {
		return '<span class="aips-json-value aips-json-value-string">"' . $render_text($value) . '"</span>';
	}

	if (is_bool($value)) {
		return '<span class="aips-json-value aips-json-value-boolean">' . esc_html($value ? 'true' : 'false') . '</span>';
	}

	if (is_numeric($value)) {
		return '<span class="aips-json-value aips-json-value-number">' . esc_html((string) $value) . '</span>';
	}

	return '<span class="aips-json-value">' . esc_html((string) $value) . '</span>';
};

$render_json_tree = null;
$render_json_tree = static function ($value, $label = null, $depth = 0) use (&$render_json_tree, $render_json_scalar) {
	$label_html = $label !== null
		? '<span class="aips-json-key">' . esc_html((string) $label) . '</span>: '
		: '';

	if (!is_array($value)) {
		return '<div class="aips-json-leaf">' . $label_html . $render_json_scalar($value) . '</div>';
	}

	if (empty($value)) {
		$is_list = array_values($value) === $value;
		return '<div class="aips-json-leaf">' . $label_html . '<span class="aips-json-value aips-json-value-empty">' . ($is_list ? '[]' : '{}') . '</span></div>';
	}

	$is_list = array_values($value) === $value;
	$summary = '<span class="aips-json-summary-label">' . $label_html . '</span>'
		. '<span class="aips-json-meta">' . ($is_list ? 'Array[' . count($value) . ']' : 'Object{' . count($value) . '}') . '</span>';

	$html = '<details class="aips-json-node"' . ($depth <= 1 ? ' open' : '') . '>';
	$html .= '<summary class="aips-json-summary">' . $summary . '</summary>';
	$html .= '<div class="aips-json-children">';

	foreach ($value as $child_key => $child_value) {
		$html .= $render_json_tree($child_value, $child_key, $depth + 1);
	}

	$html .= '</div></details>';

	return $html;
};

$render_detail_block = static function ($detail_id, $extra) use ($render_json_tree) {
	$raw_json = wp_json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	ob_start();
	?>
	<div class="aips-history-log-detail-actions">
		<button class="aips-btn aips-btn-sm aips-btn-ghost aips-log-toggle" data-target="#<?php echo esc_attr($detail_id); ?>" style="font-size:11px;">
			<?php esc_html_e('Show details', 'ai-post-scheduler'); ?>
		</button>
		<button class="aips-btn aips-btn-sm aips-btn-ghost" data-copy-target="#<?php echo esc_attr($detail_id); ?>" style="font-size:11px;margin-left:4px;">
			<?php esc_html_e('Copy', 'ai-post-scheduler'); ?>
		</button>
	</div>
	<div id="<?php echo esc_attr($detail_id); ?>" class="aips-history-log-detail-panel" style="display:none;margin-top:8px;">
		<div class="aips-json-tree-mode"><?php echo $render_json_tree($extra); ?></div>
		<div class="aips-json-raw-mode">
			<pre style="max-height:240px;overflow:auto;white-space:pre-wrap;font-size:11px;background:#f6f7f7;padding:8px;border-radius:4px;"><code><?php echo esc_html($raw_json); ?></code></pre>
		</div>
	</div>
	<?php
	return ob_get_clean();
};

$render_ai_section = static function ($log, $label, $detail_id) use ($extract_extra_details, $render_detail_block, $render_text) {
	$extra = $extract_extra_details($log);
	ob_start();
	?>
	<div class="aips-ai-log-section">
		<div class="aips-ai-log-section-header">
			<strong><?php echo esc_html($label); ?></strong>
			<?php if (!empty($log['timestamp'])): ?>
				<span class="aips-ai-log-section-time"><?php echo esc_html($log['timestamp']); ?></span>
			<?php endif; ?>
		</div>
		<?php if (!empty($log['details']['message'])): ?>
			<p style="margin:0 0 6px;"><?php echo $render_text($log['details']['message']); ?></p>
		<?php endif; ?>
		<?php if (!empty($extra)): ?>
			<?php echo $render_detail_block($detail_id, $extra); ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
};

$display_logs = array();
$used_responses = array();

foreach ($logs as $index => $log) {
	$is_ai_request = (string) ($log['history_type_id'] ?? '') === '5' || ($log['log_type'] ?? '') === 'ai_request';
	$is_ai_response = (string) ($log['history_type_id'] ?? '') === '6' || ($log['log_type'] ?? '') === 'ai_response';

	if ($is_ai_response && isset($used_responses[$index])) {
		continue;
	}

	if (!$is_ai_request) {
		$extra = $extract_extra_details($log);
		ob_start();
		if (!empty($log['details']['message'])) {
			?>
			<p style="margin:0 0 6px;"><?php echo $render_text($log['details']['message']); ?></p>
			<?php
		}
		if (!empty($extra)) {
			echo $render_detail_block('aips-log-detail-' . $index, $extra);
		}
		$details_html = ob_get_clean();
		$type_class = 'aips-badge-neutral';
		$history_type_id = (int) $log['history_type_id'];
		if ($history_type_id === 2) {
			$type_class = 'aips-badge-error';
		} elseif ($history_type_id === 3) {
			$type_class = 'aips-badge-warning';
		} elseif ($history_type_id === 4) {
			$type_class = 'aips-badge-info';
		} elseif ($history_type_id === 5 || $history_type_id === 6) {
			$type_class = 'aips-badge-ai';
		} elseif ($history_type_id === 8) {
			$type_class = 'aips-badge-success';
		}

		$display_logs[] = array(
			'timestamp' => $log['timestamp'],
			'type_label' => $log['type_label'],
			'type_class' => $type_class,
			'log_type' => $log['log_type'],
			'type_ids' => array((string) $log['history_type_id']),
			'details_html' => $details_html,
		);
		continue;
	}

	$phase_key = $derive_ai_phase_key($log);
	$response_log = null;
	$response_index = null;

	for ($search_index = $index + 1, $search_total = count($logs); $search_index < $search_total; $search_index++) {
		if (isset($used_responses[$search_index])) {
			continue;
		}

		$candidate = $logs[$search_index];
		$candidate_is_response = (string) ($candidate['history_type_id'] ?? '') === '6' || ($candidate['log_type'] ?? '') === 'ai_response';
		if ($candidate_is_response && $derive_ai_phase_key($candidate) === $phase_key) {
			$response_log = $candidate;
			$response_index = $search_index;
			break;
		}
	}

	if ($response_index !== null) {
		$used_responses[$response_index] = true;
	}

	$details_html = '<div class="aips-ai-log-pair">';
	$details_html .= $render_ai_section($log, __('AI Request', 'ai-post-scheduler'), 'aips-log-detail-' . $index . '-request');
	if ($response_log) {
		$details_html .= $render_ai_section($response_log, __('AI Response', 'ai-post-scheduler'), 'aips-log-detail-' . $index . '-response');
	}
	$details_html .= '</div>';

	$display_logs[] = array(
		'timestamp' => $log['timestamp'],
		'type_label' => __('AI Request / Response', 'ai-post-scheduler'),
		'type_class' => 'aips-badge-ai',
		'log_type' => $humanize_ai_phase_label($phase_key),
		'type_ids' => $response_log ? array('5', '6') : array('5'),
		'details_html' => $details_html,
	);
}

$filter_counts = array('all' => count($display_logs));
foreach ($display_logs as $display_log) {
	foreach ($display_log['type_ids'] as $type_id) {
		$filter_counts[$type_id] = isset($filter_counts[$type_id]) ? $filter_counts[$type_id] + 1 : 1;
	}
}

$type_labels = array(
	2 => AIPS_History_Type::get_label(2),
	3 => AIPS_History_Type::get_label(3),
	4 => AIPS_History_Type::get_label(4),
	5 => AIPS_History_Type::get_label(5),
	6 => AIPS_History_Type::get_label(6),
	8 => AIPS_History_Type::get_label(8),
	1 => AIPS_History_Type::get_label(1),
	7 => AIPS_History_Type::get_label(7),
	9 => AIPS_History_Type::get_label(9),
	10 => AIPS_History_Type::get_label(10),
);
?>

<div class="aips-history-log-renderer aips-json-viewer-enabled">
	<div class="aips-history-modal-toolbar">
		<label class="aips-history-json-toggle">
			<input type="checkbox" class="aips-json-viewer-toggle" checked>
			<span><?php esc_html_e('JSON Viewer', 'ai-post-scheduler'); ?></span>
		</label>
	</div>

	<h4 style="margin:0 0 8px;"><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></h4>
	<div class="aips-history-modal-summary">
		<table class="aips-table" style="width:100%;margin-bottom:20px;">
			<tbody>
				<tr><td style="font-weight:600;width:140px;"><?php esc_html_e('Container ID', 'ai-post-scheduler'); ?></td><td><?php echo esc_html($container['id']); ?></td></tr>
				<?php if (!empty($container['generated_title'])): ?><tr><td style="font-weight:600;"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></td><td><?php echo esc_html($container['generated_title']); ?></td></tr><?php endif; ?>
				<?php if (!empty($container['template_name'])): ?><tr><td style="font-weight:600;"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></td><td><?php echo esc_html($container['template_name']); ?></td></tr><?php endif; ?>
				<?php if (!empty($container['creation_method'])): ?><tr><td style="font-weight:600;"><?php esc_html_e('Method', 'ai-post-scheduler'); ?></td><td><?php echo esc_html(ucfirst(str_replace('_', ' ', $container['creation_method']))); ?></td></tr><?php endif; ?>
				<tr>
					<td style="font-weight:600;"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></td>
					<td>
						<?php $status_class = $container['status'] === 'completed' ? 'aips-badge-success' : ($container['status'] === 'failed' ? 'aips-badge-error' : 'aips-badge-neutral'); ?>
						<span class="aips-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($container['status']); ?></span>
					</td>
				</tr>
				<?php if (!empty($container['created_at'])): ?><tr><td style="font-weight:600;"><?php esc_html_e('Created', 'ai-post-scheduler'); ?></td><td><?php echo esc_html($container['created_at']); ?></td></tr><?php endif; ?>
				<?php if (!empty($container['completed_at'])): ?><tr><td style="font-weight:600;"><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></td><td><?php echo esc_html($container['completed_at']); ?></td></tr><?php endif; ?>
				<?php if (isset($container['duration_seconds']) && $container['duration_seconds'] !== null): ?><tr><td style="font-weight:600;"><?php esc_html_e('Duration', 'ai-post-scheduler'); ?></td><td><?php echo esc_html($container['duration_seconds'] < 60 ? sprintf(__('%d seconds', 'ai-post-scheduler'), $container['duration_seconds']) : sprintf(__('%d min %d sec', 'ai-post-scheduler'), intdiv((int) $container['duration_seconds'], 60), ((int) $container['duration_seconds']) % 60)); ?></td></tr><?php endif; ?>
				<?php if (!empty($container['error_message'])): ?><tr><td style="font-weight:600;color:#d32f2f;"><?php esc_html_e('Error', 'ai-post-scheduler'); ?></td><td style="color:#d32f2f;"><?php echo esc_html($container['error_message']); ?></td></tr><?php endif; ?>
				<?php if (!empty($container['post_id']) && !empty($container['post_url'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></td>
						<td>
							<a href="<?php echo esc_url($container['post_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(sprintf(__('View Post (ID: %d)', 'ai-post-scheduler'), $container['post_id'])); ?></a>
							<?php if (!empty($container['post_edit_url'])): ?>
								&nbsp;|&nbsp;<a href="<?php echo esc_url($container['post_edit_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php elseif (!empty($container['post_id'])): ?>
					<tr><td style="font-weight:600;"><?php esc_html_e('Post ID', 'ai-post-scheduler'); ?></td><td><?php echo esc_html($container['post_id']); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if (!empty($display_logs)): ?>
		<div class="aips-history-log-type-filter" style="margin:16px 0;padding:12px;background:#f5f5f5;border-radius:4px;">
			<span style="font-weight:600;margin-right:8px;"><?php esc_html_e('Filter:', 'ai-post-scheduler'); ?></span>
			<button class="aips-btn aips-btn-sm aips-btn-primary aips-log-type-filter-btn" data-type-id="all" style="margin-right:4px;"><?php echo esc_html(sprintf(__('All (%d)', 'ai-post-scheduler'), $filter_counts['all'])); ?></button>
			<?php foreach ($type_labels as $type_id => $type_label): ?>
				<?php if (empty($filter_counts[$type_id])) { continue; } ?>
				<button class="aips-btn aips-btn-sm aips-btn-ghost aips-log-type-filter-btn" data-type-id="<?php echo esc_attr($type_id); ?>" style="margin-right:4px;"><?php echo esc_html(sprintf(__('%s (%d)', 'ai-post-scheduler'), $type_label, $filter_counts[$type_id])); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<details class="aips-history-advanced-details" style="margin-top:16px;">
		<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Advanced Details', 'ai-post-scheduler'); ?></summary>
		<?php if (empty($display_logs)): ?>
			<p style="margin-top:12px;color:#666;"><?php esc_html_e('No log entries found for this container.', 'ai-post-scheduler'); ?></p>
		<?php else: ?>
			<h3 style="margin:12px 0 8px;"><?php esc_html_e('Log Entries', 'ai-post-scheduler'); ?> <span class="aips-badge aips-badge-neutral"><?php echo esc_html(count($display_logs)); ?></span></h3>
			<table class="aips-table aips-history-logs-table" style="width:100%;margin-top:12px;">
				<thead>
					<tr>
						<th style="width:150px;"><?php esc_html_e('Timestamp', 'ai-post-scheduler'); ?></th>
						<th style="width:130px;"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
						<th style="width:150px;"><?php esc_html_e('Log Type', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Details', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($display_logs as $display_log): ?>
						<tr data-type-ids="<?php echo esc_attr(implode(',', $display_log['type_ids'])); ?>">
							<td style="white-space:nowrap;font-size:12px;"><?php echo esc_html($display_log['timestamp']); ?></td>
							<td><span class="aips-badge <?php echo esc_attr($display_log['type_class']); ?>"><?php echo esc_html($display_log['type_label']); ?></span></td>
							<td style="font-size:12px;font-family:monospace;"><?php echo esc_html($display_log['log_type']); ?></td>
							<td><?php echo $display_log['details_html']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</details>
</div>
