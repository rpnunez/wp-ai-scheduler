<?php
/**
 * Post Insights Sidebar Meta Box Template (Classic Editor).
 *
 * @package AI_Post_Scheduler
 * @since 3.6.7
 *
 * @var WP_Post $post
 * @var array   $insights Compiled post insights data.
 */

if (!defined('ABSPATH')) {
	exit;
}

$post_id          = (int) $post->ID;
$embedding        = isset($insights['embedding']) ? $insights['embedding'] : array();
$is_indexed       = !empty($embedding['is_indexed']);
$history          = isset($insights['history']) ? $insights['history'] : null;
$cluster          = isset($insights['cluster']) ? $insights['cluster'] : null;
$top_duplicates   = isset($insights['top_duplicates']) ? $insights['top_duplicates'] : array();
$max_similarity   = isset($insights['max_similarity_pct']) ? (int) $insights['max_similarity_pct'] : 0;
$overall_risk     = isset($insights['overall_risk']) ? $insights['overall_risk'] : 'clean';
$overall_label    = isset($insights['overall_label']) ? $insights['overall_label'] : __('Clean', 'ai-post-scheduler');
?>

<div class="aips-metabox-wrap" id="aips-post-insights-metabox-content" data-post-id="<?php echo esc_attr((string) $post_id); ?>">

	<!-- Top Status & Risk Banner -->
	<div class="aips-insight-risk-row">
		<span class="aips-risk-pill aips-risk-<?php echo esc_attr($overall_risk); ?>">
			<span class="dashicons dashicons-shield" aria-hidden="true"></span>
			<strong><?php echo esc_html($overall_label); ?></strong>
			<?php if ($max_similarity > 0) : ?>
				(<?php echo esc_html((string) $max_similarity); ?>%)
			<?php endif; ?>
		</span>

		<?php if (!empty($cluster['is_pillar'])) : ?>
			<span class="aips-badge aips-badge-primary aips-pillar-tag" title="<?php esc_attr_e('Designated Pillar Post for this topic cluster', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-star-filled aips-pillar-icon" aria-hidden="true"></span>
				<?php esc_html_e('Pillar Post', 'ai-post-scheduler'); ?>
			</span>
		<?php endif; ?>
	</div>

	<!-- Vector Indexing Status Section -->
	<div class="aips-insight-section">
		<h4 class="aips-insight-section-title">
			<span class="dashicons dashicons-database" aria-hidden="true"></span>
			<?php esc_html_e('Vector Embedding', 'ai-post-scheduler'); ?>
		</h4>
		<div class="aips-insight-detail">
			<?php if ($is_indexed) : ?>
				<p class="aips-insight-text">
					<strong class="aips-text-success"><?php esc_html_e('Indexed', 'ai-post-scheduler'); ?></strong>
					<span class="aips-insight-meta">(<?php echo esc_html((string) $embedding['dimensions']); ?> dims, <?php echo esc_html($embedding['indexed_str']); ?>)</span>
				</p>
			<?php else : ?>
				<p class="aips-insight-text">
					<span class="aips-text-muted"><?php esc_html_e('Not yet indexed into vector store.', 'ai-post-scheduler'); ?></span>
				</p>
			<?php endif; ?>

			<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-reindex-post-btn" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php echo $is_indexed ? esc_html__('Re-Index Vector', 'ai-post-scheduler') : esc_html__('Index Now', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>

	<!-- Post Cluster & Topic Pillar -->
	<div class="aips-insight-section">
		<h4 class="aips-insight-section-title">
			<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			<?php esc_html_e('Post Cluster', 'ai-post-scheduler'); ?>
		</h4>
		<div class="aips-insight-detail">
			<?php if ($cluster) : ?>
				<p class="aips-insight-text">
					<strong><?php echo esc_html($cluster['name']); ?></strong>
					<span class="aips-insight-meta">(<?php echo esc_html((string) $cluster['post_count']); ?> <?php esc_html_e('posts', 'ai-post-scheduler'); ?>)</span>
				</p>
				<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-toggle-pillar-btn <?php echo !empty($cluster['is_pillar']) ? 'active' : ''; ?>" data-cluster-id="<?php echo esc_attr($cluster['cluster_id']); ?>" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
					<span class="dashicons <?php echo !empty($cluster['is_pillar']) ? 'dashicons-star-filled' : 'dashicons-star-empty'; ?>" aria-hidden="true"></span>
					<?php echo !empty($cluster['is_pillar']) ? esc_html__('Designated Pillar', 'ai-post-scheduler') : esc_html__('Set as Pillar', 'ai-post-scheduler'); ?>
				</button>
			<?php else : ?>
				<p class="aips-insight-text aips-text-muted">
					<em><?php esc_html_e('Not assigned to any cluster yet.', 'ai-post-scheduler'); ?></em>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Top Duplicate Candidates -->
	<div class="aips-insight-section">
		<h4 class="aips-insight-section-title">
			<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
			<?php esc_html_e('Semantic Duplicate Risk', 'ai-post-scheduler'); ?>
		</h4>
		<div class="aips-insight-detail">
			<?php if (!empty($top_duplicates)) : ?>
				<ul class="aips-duplicate-candidates-list">
					<?php foreach ($top_duplicates as $dup) : ?>
						<li class="aips-duplicate-candidate-item">
							<div class="aips-dup-header">
								<span class="aips-risk-badge aips-risk-<?php echo esc_attr($dup['risk_level']); ?>">
									<?php echo esc_html((string) $dup['similarity_pct']); ?>%
								</span>
								<a href="<?php echo esc_url($dup['edit_url']); ?>" class="aips-dup-title" title="<?php esc_attr_e('Edit Post', 'ai-post-scheduler'); ?>">
									<?php echo esc_html($dup['title']); ?>
								</a>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="aips-insight-text aips-text-muted">
					<?php esc_html_e('No conflicting high-similarity duplicate posts detected.', 'ai-post-scheduler'); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Generation Context (if generated via AIPS) -->
	<?php if ($history) : ?>
		<div class="aips-insight-section aips-generation-context-section">
			<h4 class="aips-insight-section-title">
				<span class="dashicons dashicons-art" aria-hidden="true"></span>
				<?php esc_html_e('AI Generation Context', 'ai-post-scheduler'); ?>
			</h4>
			<div class="aips-context-grid">
				<?php if (!empty($history['author_name'])) : ?>
					<div class="aips-context-item">
						<span class="aips-context-label"><?php esc_html_e('Author:', 'ai-post-scheduler'); ?></span>
						<strong class="aips-context-value"><?php echo esc_html($history['author_name']); ?></strong>
					</div>
				<?php endif; ?>

				<?php if (!empty($history['template_title'])) : ?>
					<div class="aips-context-item">
						<span class="aips-context-label"><?php esc_html_e('Template:', 'ai-post-scheduler'); ?></span>
						<span class="aips-context-value"><?php echo esc_html($history['template_title']); ?></span>
					</div>
				<?php endif; ?>

				<?php if (!empty($history['topic_title'])) : ?>
					<div class="aips-context-item">
						<span class="aips-context-label"><?php esc_html_e('Topic:', 'ai-post-scheduler'); ?></span>
						<span class="aips-context-value"><em><?php echo esc_html($history['topic_title']); ?></em></span>
					</div>
				<?php endif; ?>

				<div class="aips-context-item">
					<span class="aips-context-label"><?php esc_html_e('Generated:', 'ai-post-scheduler'); ?></span>
					<span class="aips-context-value"><?php echo esc_html($history['created_str']); ?></span>
				</div>

				<?php if (!empty($history['tokens_used'])) : ?>
					<div class="aips-context-item">
						<span class="aips-context-label"><?php esc_html_e('Tokens / Cost:', 'ai-post-scheduler'); ?></span>
						<span class="aips-context-value"><?php echo esc_html(number_format_i18n($history['tokens_used'])); ?> tok (<?php echo esc_html('$' . number_format($history['cost'], 4)); ?>)</span>
					</div>
				<?php endif; ?>
			</div>

			<?php if (!empty($history['history_url'])) : ?>
				<div class="aips-insight-footer-action">
					<a href="<?php echo esc_url($history['history_url']); ?>" class="aips-btn aips-btn-xs aips-btn-secondary aips-open-history-modal" data-history-id="<?php echo esc_attr((string) $history['id']); ?>" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
						<span class="dashicons dashicons-backup" aria-hidden="true"></span>
						<?php esc_html_e('View AI Generation History', 'ai-post-scheduler'); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

</div>
