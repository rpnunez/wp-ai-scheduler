<?php
if (!defined('ABSPATH')) {
	exit;
}

// Get authors - only instantiate repository when needed
$authors_repository = null;
$topics_repository = null;
$logs_repository = null;
$structures_repository = null;
$authors = array();
$article_structures = array();

if (isset($_GET['page']) && $_GET['page'] === 'aips-authors') {
	$authors_repository = new AIPS_Authors_Repository();
	$authors = $authors_repository->get_all();

	if (!empty($authors)) {
		$topics_repository = new AIPS_Author_Topics_Repository();
		$logs_repository = new AIPS_Author_Topic_Logs_Repository();
	}

	// Load article structures for the dropdown
	$structures_repository = new AIPS_Article_Structure_Repository();
	$article_structures = $structures_repository->get_all(true); // Get active structures only
}

/**
 * Generate initials from name for avatar
 */
function aips_get_initials($name) {
	$words = explode(' ', $name);
	if (count($words) >= 2) {
		return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
	}
	return strtoupper(substr($name, 0, 2));
}

/**
 * Generate color for avatar based on name
 */
function aips_get_avatar_color($name) {
	$colors = array(
		'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
		'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
		'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
		'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
		'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
		'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
		'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
		'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
	);
	$index = abs(crc32($name)) % count($colors);
	return $colors[$index];
}
?>
<div class="wrap aips-wrap aips-authors-modern">
	<h1>
		<?php esc_html_e('Authors', 'ai-post-scheduler'); ?>
		<button class="page-title-action aips-add-author-btn"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></button>
	</h1>

	<div class="aips-authors-container">
		<?php if (!empty($authors)): ?>
			<div class="aips-authors-grid">
				<?php foreach ($authors as $author):
					$status_counts = $topics_repository->get_status_counts($author->id);
					$total_topics = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'];
					$posts = $logs_repository->get_generated_posts_by_author($author->id);
					$posts_count = count($posts);
					$initials = aips_get_initials($author->name);
					$avatar_color = aips_get_avatar_color($author->name);
				?>
					<div class="aips-author-card" data-author-id="<?php echo esc_attr($author->id); ?>">
						<div class="aips-author-card-header">
							<div class="aips-author-avatar" style="background: <?php echo esc_attr($avatar_color); ?>">
								<?php echo esc_html($initials); ?>
							</div>
							<div class="aips-author-info">
								<h3 class="aips-author-name"><?php echo esc_html($author->name); ?></h3>
								<p class="aips-author-field"><?php echo esc_html($author->field_niche); ?></p>
							</div>
							<div class="aips-author-status">
								<span class="aips-status-badge <?php echo $author->is_active ? 'active' : 'inactive'; ?>">
									<?php echo $author->is_active ? esc_html__('Active', 'ai-post-scheduler') : esc_html__('Inactive', 'ai-post-scheduler'); ?>
								</span>
							</div>
						</div>

						<div class="aips-author-stats">
							<div class="aips-stat approved">
								<span class="aips-stat-value"><?php echo esc_html($status_counts['approved']); ?></span>
								<span class="aips-stat-label"><?php esc_html_e('Approved', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-stat pending">
								<span class="aips-stat-value"><?php echo esc_html($status_counts['pending']); ?></span>
								<span class="aips-stat-label"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-stat rejected">
								<span class="aips-stat-value"><?php echo esc_html($status_counts['rejected']); ?></span>
								<span class="aips-stat-label"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-stat">
								<span class="aips-stat-value"><?php echo esc_html($posts_count); ?></span>
								<span class="aips-stat-label"><?php esc_html_e('Posts', 'ai-post-scheduler'); ?></span>
							</div>
						</div>

						<div class="aips-author-actions">
							<button class="aips-btn aips-btn-primary aips-btn-sm aips-author-card-view" 
									data-id="<?php echo esc_attr($author->id); ?>" 
									data-name="<?php echo esc_attr($author->name); ?>">
								<?php esc_html_e('View Topics', 'ai-post-scheduler'); ?>
							</button>
							<button class="aips-btn aips-btn-secondary aips-btn-sm aips-author-card-edit" 
									data-id="<?php echo esc_attr($author->id); ?>">
								<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
							</button>
							<button class="aips-btn aips-btn-secondary aips-btn-sm aips-author-card-generate" 
									data-id="<?php echo esc_attr($author->id); ?>">
								<?php esc_html_e('Generate Topics', 'ai-post-scheduler'); ?>
							</button>
							<button class="aips-btn aips-btn-danger aips-btn-sm aips-author-card-delete" 
									data-id="<?php echo esc_attr($author->id); ?>">
								<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="aips-empty-state-modern">
				<div class="aips-empty-state-icon">👤</div>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No Authors Yet', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-text"><?php esc_html_e('Create your first author to start generating topically diverse blog posts.', 'ai-post-scheduler'); ?></p>
				<button class="aips-btn aips-btn-primary aips-add-author-btn"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></button>
			</div>
		<?php endif; ?>
	</div>
</div>
