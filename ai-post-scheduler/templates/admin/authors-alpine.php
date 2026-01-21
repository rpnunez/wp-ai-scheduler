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
<div class="wrap aips-wrap aips-authors-modern" x-data="authorsApp">
	<h1>
		<?php esc_html_e('Authors', 'ai-post-scheduler'); ?>
		<button class="page-title-action" @click="openAddAuthor()"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></button>
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
							<button class="aips-btn aips-btn-primary aips-btn-sm" 
									@click="viewAuthorTopics(<?php echo esc_js($author->id); ?>, '<?php echo esc_js($author->name); ?>')">
								<?php esc_html_e('View Topics', 'ai-post-scheduler'); ?>
							</button>
							<button class="aips-btn aips-btn-secondary aips-btn-sm" 
									@click="editAuthor(<?php echo esc_js($author->id); ?>)">
								<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
							</button>
							<button class="aips-btn aips-btn-secondary aips-btn-sm" 
									@click="generateTopicsNow(<?php echo esc_js($author->id); ?>)">
								<?php esc_html_e('Generate Topics', 'ai-post-scheduler'); ?>
							</button>
							<button class="aips-btn aips-btn-danger aips-btn-sm" 
									@click="deleteAuthor(<?php echo esc_js($author->id); ?>)">
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
				<button class="aips-btn aips-btn-primary" @click="openAddAuthor()"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></button>
			</div>
		<?php endif; ?>
	</div>

	<!-- Slide-over Overlay -->
	<div class="aips-slideover-overlay" 
		 x-show="topicsSlideoverOpen || authorSlideoverOpen" 
		 @click="closeSlideOver()"
		 x-transition:enter="transition ease-out duration-300"
		 x-transition:enter-start="opacity-0"
		 x-transition:enter-end="opacity-100"
		 x-transition:leave="transition ease-in duration-200"
		 x-transition:leave-start="opacity-100"
		 x-transition:leave-end="opacity-0"
		 style="display: none;"></div>

	<!-- Topics Slide-over -->
	<div class="aips-slideover" 
		 x-show="topicsSlideoverOpen"
		 x-transition:enter="transition ease-out duration-300 transform"
		 x-transition:enter-start="translate-x-full"
		 x-transition:enter-end="translate-x-0"
		 x-transition:leave="transition ease-in duration-200 transform"
		 x-transition:leave-start="translate-x-0"
		 x-transition:leave-end="translate-x-full"
		 style="display: none;">
		
		<div class="aips-slideover-header">
			<h2 class="aips-slideover-title">Topics</h2>
			<button class="aips-slideover-close" @click="closeSlideOver()" aria-label="Close">&times;</button>
		</div>
		
		<div class="aips-slideover-body">
			<!-- Loading State -->
			<div x-show="loading" style="text-align: center; padding: 40px;">
				<div class="aips-loading-spinner" style="margin: 0 auto;"></div>
				<p style="margin-top: 16px; color: #6b7280;">Loading topics...</p>
			</div>
			
			<!-- Topics Content -->
			<div x-show="!loading">
				<!-- Tabs -->
				<div class="aips-tabs">
					<button class="aips-tab" :class="{ 'active': activeTab === 'pending' }" @click="switchTab('pending')">
						Pending Review
						<span class="aips-tab-count" x-text="statusCounts.pending"></span>
					</button>
					<button class="aips-tab" :class="{ 'active': activeTab === 'approved' }" @click="switchTab('approved')">
						Approved
						<span class="aips-tab-count" x-text="statusCounts.approved"></span>
					</button>
					<button class="aips-tab" :class="{ 'active': activeTab === 'rejected' }" @click="switchTab('rejected')">
						Rejected
						<span class="aips-tab-count" x-text="statusCounts.rejected"></span>
					</button>
				</div>
				
				<!-- Bulk Actions Bar -->
				<div class="aips-bulk-actions-bar">
					<label style="display: flex; align-items: center; gap: 8px;">
						<input type="checkbox" x-model="selectAll" @change="toggleSelectAll()">
						<span style="font-size: 14px; color: #374151;">Select All</span>
					</label>
					<select class="aips-bulk-select" x-ref="bulkAction">
						<option value="">Bulk Actions</option>
						<option value="approve">Approve Selected</option>
						<option value="reject">Reject Selected</option>
						<option value="delete">Delete Selected</option>
					</select>
					<button class="aips-btn aips-btn-primary aips-btn-sm" 
							@click="executeBulkAction($refs.bulkAction.value)"
							:disabled="!hasSelectedTopics">
						Execute
					</button>
				</div>
				
				<!-- Topics Chips -->
				<div class="aips-topics-chips-container">
					<div x-show="filteredTopics.length === 0" class="aips-empty-state-modern">
						<div class="aips-empty-state-icon">📝</div>
						<h3 class="aips-empty-state-title" x-text="'No ' + activeTab + ' topics'"></h3>
						<p class="aips-empty-state-text" x-text="'There are no ' + activeTab + ' topics for this author.'"></p>
					</div>
					
					<div x-show="filteredTopics.length > 0" class="aips-topics-chips">
						<template x-for="topic in filteredTopics" :key="topic.id">
							<div class="aips-topic-chip" :class="topic.status">
								<input type="checkbox" 
									   class="aips-topic-chip-checkbox" 
									   :checked="selectedTopics.includes(topic.id)"
									   @change="toggleTopicSelection(topic.id)">
								<span class="aips-topic-chip-title" x-text="topic.topic_title" :title="topic.topic_title"></span>
								<span x-show="topic.post_count > 0" class="aips-topic-chip-count" x-text="topic.post_count"></span>
								
								<!-- Quick Actions -->
								<div class="aips-topic-chip-actions" style="margin-left: auto; display: flex; gap: 4px;">
									<template x-if="topic.status === 'pending'">
										<button class="aips-btn aips-btn-sm" 
												style="padding: 2px 6px; font-size: 11px; background: #059669; color: white;"
												@click.stop="approveTopic(topic.id)"
												title="Approve">✓</button>
									</template>
									<template x-if="topic.status === 'pending'">
										<button class="aips-btn aips-btn-sm" 
												style="padding: 2px 6px; font-size: 11px; background: #dc2626; color: white;"
												@click.stop="rejectTopic(topic.id)"
												title="Reject">✗</button>
									</template>
									<template x-if="topic.status === 'approved'">
										<button class="aips-btn aips-btn-sm" 
												style="padding: 2px 6px; font-size: 11px; background: #2563eb; color: white;"
												@click.stop="generatePostFromTopic(topic.id)"
												title="Generate Post">📝</button>
									</template>
									<button class="aips-btn aips-btn-sm" 
											style="padding: 2px 6px; font-size: 11px; background: #dc2626; color: white;"
											@click.stop="deleteTopic(topic.id)"
											title="Delete">🗑</button>
								</div>
							</div>
						</template>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Author Form Slide-over -->
	<div class="aips-slideover" 
		 x-show="authorSlideoverOpen"
		 x-transition:enter="transition ease-out duration-300 transform"
		 x-transition:enter-start="translate-x-full"
		 x-transition:enter-end="translate-x-0"
		 x-transition:leave="transition ease-in duration-200 transform"
		 x-transition:leave-start="translate-x-0"
		 x-transition:leave-end="translate-x-full"
		 style="display: none;">
		
		<div class="aips-slideover-header">
			<h2 class="aips-slideover-title" x-text="authorForm.id ? 'Edit Author' : 'Add New Author'"></h2>
			<button class="aips-slideover-close" @click="closeSlideOver()" aria-label="Close">&times;</button>
		</div>
		
		<div class="aips-slideover-body">
			<!-- Loading State -->
			<div x-show="loading" style="text-align: center; padding: 40px;">
				<div class="aips-loading-spinner" style="margin: 0 auto;"></div>
			</div>
			
			<!-- Author Form -->
			<form x-show="!loading" @submit.prevent="saveAuthor()">
				<div class="aips-form-group">
					<label class="aips-form-label">Name *</label>
					<input type="text" class="aips-form-input" x-model="authorForm.name" required>
				</div>
				
				<div class="aips-form-group">
					<label class="aips-form-label">Field/Niche *</label>
					<input type="text" class="aips-form-input" x-model="authorForm.field_niche" placeholder="e.g., PHP Programming" required>
					<p class="aips-form-description">The main topic or field this author covers</p>
				</div>
				
				<div class="aips-form-group">
					<label class="aips-form-label">Keywords</label>
					<input type="text" class="aips-form-input" x-model="authorForm.keywords" placeholder="e.g., Laravel, Symfony, Composer, PSR">
					<p class="aips-form-description">Comma-separated keywords to focus on when generating topics</p>
				</div>
				
				<div class="aips-form-group">
					<label class="aips-form-label">Details</label>
					<textarea class="aips-form-textarea" x-model="authorForm.details" rows="4" placeholder="Additional context or instructions for topic generation..."></textarea>
					<p class="aips-form-description">Additional context that will be included when generating topics</p>
				</div>
				
				<div class="aips-form-group">
					<label class="aips-form-label">Description</label>
					<textarea class="aips-form-textarea" x-model="authorForm.description" rows="3"></textarea>
				</div>
				
				<div class="aips-form-group">
					<label class="aips-form-label">Topic Generation Frequency</label>
					<select class="aips-form-select" x-model="authorForm.topic_generation_frequency">
						<option value="daily">Daily</option>
						<option value="weekly">Weekly</option>
						<option value="biweekly">Bi-weekly</option>
						<option value="monthly">Monthly</option>
					</select>
				</div>
				
				<div class="aips-form-group">
					<label class="aips-form-label">Number of Topics to Generate</label>
					<input type="number" class="aips-form-input" x-model.number="authorForm.topic_generation_quantity" min="1" max="20">
				</div>
				
				<div class="aips-form-group">
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" x-model="authorForm.is_active">
						<span class="aips-form-label" style="margin: 0;">Active</span>
					</label>
				</div>
				
				<div class="aips-slideover-footer" style="margin: 24px -24px -24px; padding: 24px; border-top: 1px solid #e5e7eb;">
					<button type="button" class="aips-btn aips-btn-secondary" @click="closeSlideOver()">Cancel</button>
					<button type="submit" class="aips-btn aips-btn-primary" :disabled="saving">
						<span x-show="!saving">Save Author</span>
						<span x-show="saving">Saving...</span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
