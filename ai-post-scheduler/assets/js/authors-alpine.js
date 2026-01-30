/**
 * Authors Management with Alpine.js
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

document.addEventListener('alpine:init', () => {
	Alpine.data('authorsApp', () => ({
		// State
		currentAuthorId: null,
		currentTopicId: null,
		topics: [],
		statusCounts: {
			pending: 0,
			approved: 0,
			rejected: 0
		},
		activeTab: 'pending',
		
		// Slide-over states
		topicsSlideoverOpen: false,
		authorSlideoverOpen: false,
		
		// Loading states
		loading: false,
		saving: false,
		
		// Author form data
		authorForm: {
			id: '',
			name: '',
			field_niche: '',
			keywords: '',
			details: '',
			description: '',
			topic_generation_frequency: 'weekly',
			topic_generation_quantity: 5,
			is_active: true
		},
		
		// Selected topics for bulk actions
		selectedTopics: [],
		selectAll: false,
		
		// Initialize
		init() {
			console.log('Alpine.js Authors App Initialized');
		},
		
		// Computed properties
		get filteredTopics() {
			return this.topics.filter(topic => topic.status === this.activeTab);
		},
		
		get hasSelectedTopics() {
			return this.selectedTopics.length > 0;
		},
		
		// Author actions
		openAddAuthor() {
			this.resetAuthorForm();
			this.authorSlideoverOpen = true;
		},
		
		async editAuthor(authorId) {
			this.currentAuthorId = authorId;
			this.loading = true;
			this.authorSlideoverOpen = true;
			
			try {
				const formData = new FormData();
				formData.append('action', 'aips_get_author');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('author_id', authorId);
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success && data.data.author) {
					const author = data.data.author;
					this.authorForm = {
						id: author.id,
						name: author.name,
						field_niche: author.field_niche,
						keywords: author.keywords || '',
						details: author.details || '',
						description: author.description || '',
						topic_generation_frequency: author.topic_generation_frequency || 'weekly',
						topic_generation_quantity: author.topic_generation_quantity || 5,
						is_active: !!author.is_active
					};
				} else {
					this.showError('Failed to load author data');
				}
			} catch (error) {
				console.error('Error loading author:', error);
				this.showError('Failed to load author data');
			} finally {
				this.loading = false;
			}
		},
		
		async saveAuthor() {
			this.saving = true;
			
			try {
				const formData = new FormData();
				formData.append('action', 'aips_save_author');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('author_id', this.authorForm.id);
				formData.append('name', this.authorForm.name);
				formData.append('field_niche', this.authorForm.field_niche);
				formData.append('keywords', this.authorForm.keywords);
				formData.append('details', this.authorForm.details);
				formData.append('description', this.authorForm.description);
				formData.append('topic_generation_frequency', this.authorForm.topic_generation_frequency);
				formData.append('topic_generation_quantity', this.authorForm.topic_generation_quantity);
				if (this.authorForm.is_active) {
					formData.append('is_active', '1');
				}
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.showSuccess('Author saved successfully');
					this.authorSlideoverOpen = false;
					// Reload the page to show updated data
					setTimeout(() => location.reload(), 500);
				} else {
					this.showError(data.data?.message || 'Failed to save author');
				}
			} catch (error) {
				console.error('Error saving author:', error);
				this.showError('Failed to save author');
			} finally {
				this.saving = false;
			}
		},
		
		async deleteAuthor(authorId) {
			if (!confirm(aipsAuthorsL10n.confirmDelete)) {
				return;
			}
			
			try {
				const formData = new FormData();
				formData.append('action', 'aips_delete_author');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('author_id', authorId);
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.showSuccess('Author deleted successfully');
					// Reload the page to show updated list
					setTimeout(() => location.reload(), 500);
				} else {
					this.showError(data.data?.message || 'Failed to delete author');
				}
			} catch (error) {
				console.error('Error deleting author:', error);
				this.showError('Failed to delete author');
			}
		},
		
		async generateTopicsNow(authorId) {
			if (!confirm('Generate topics for this author now?')) {
				return;
			}
			
			try {
				const formData = new FormData();
				formData.append('action', 'aips_generate_topics_now');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('author_id', authorId);
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.showSuccess('Topics generated successfully');
				} else {
					this.showError(data.data?.message || 'Failed to generate topics');
				}
			} catch (error) {
				console.error('Error generating topics:', error);
				this.showError('Failed to generate topics');
			}
		},
		
		// Topic actions
		async viewAuthorTopics(authorId, authorName) {
			this.currentAuthorId = authorId;
			this.loading = true;
			this.topicsSlideoverOpen = true;
			this.activeTab = 'pending';
			this.topics = [];
			this.selectedTopics = [];
			this.selectAll = false;
			
			try {
				const formData = new FormData();
				formData.append('action', 'aips_get_author_topics');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('author_id', authorId);
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.topics = data.data.topics || [];
					this.statusCounts = data.data.status_counts || {
						pending: 0,
						approved: 0,
						rejected: 0
					};
				} else {
					this.showError('Failed to load topics');
				}
			} catch (error) {
				console.error('Error loading topics:', error);
				this.showError('Failed to load topics');
			} finally {
				this.loading = false;
			}
		},
		
		switchTab(tab) {
			this.activeTab = tab;
			this.selectedTopics = [];
			this.selectAll = false;
		},
		
		toggleSelectAll() {
			if (this.selectAll) {
				this.selectedTopics = this.filteredTopics.map(t => t.id);
			} else {
				this.selectedTopics = [];
			}
		},
		
		toggleTopicSelection(topicId) {
			const index = this.selectedTopics.indexOf(topicId);
			if (index > -1) {
				this.selectedTopics.splice(index, 1);
			} else {
				this.selectedTopics.push(topicId);
			}
			// Update selectAll state
			this.selectAll = this.selectedTopics.length === this.filteredTopics.length;
		},
		
		async approveTopic(topicId) {
			await this.updateTopicStatus(topicId, 'approve');
		},
		
		async rejectTopic(topicId) {
			await this.updateTopicStatus(topicId, 'reject');
		},
		
		async deleteTopic(topicId) {
			if (!confirm('Are you sure you want to delete this topic?')) {
				return;
			}
			
			try {
				const formData = new FormData();
				formData.append('action', 'aips_delete_topic');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('topic_id', topicId);
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.showSuccess('Topic deleted successfully');
					// Remove topic from array
					this.topics = this.topics.filter(t => t.id !== topicId);
					// Update counts
					this.updateStatusCounts();
				} else {
					this.showError(data.data?.message || 'Failed to delete topic');
				}
			} catch (error) {
				console.error('Error deleting topic:', error);
				this.showError('Failed to delete topic');
			}
		},
		
		async updateTopicStatus(topicId, action) {
			try {
				const formData = new FormData();
				formData.append('action', 'aips_' + action + '_topic');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('topic_id', topicId);
				formData.append('reason', '');
				formData.append('reason_category', 'other');
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.showSuccess('Topic ' + action + 'd successfully');
					// Update topic in array
					const topic = this.topics.find(t => t.id === topicId);
					if (topic) {
						topic.status = action === 'approve' ? 'approved' : 'rejected';
					}
					// Update counts
					this.updateStatusCounts();
				} else {
					this.showError(data.data?.message || 'Failed to ' + action + ' topic');
				}
			} catch (error) {
				console.error('Error updating topic:', error);
				this.showError('Failed to ' + action + ' topic');
			}
		},
		
		async generatePostFromTopic(topicId) {
			if (!confirm('Generate a post from this topic now?')) {
				return;
			}
			
			try {
				const formData = new FormData();
				formData.append('action', 'aips_generate_post_from_topic');
				formData.append('nonce', aipsAuthorsL10n.nonce);
				formData.append('topic_id', topicId);
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.showSuccess('Post generated successfully');
				} else {
					this.showError(data.data?.message || 'Failed to generate post');
				}
			} catch (error) {
				console.error('Error generating post:', error);
				this.showError('Failed to generate post');
			}
		},
		
		async executeBulkAction(action) {
			if (!action) {
				alert('Please select a bulk action');
				return;
			}
			
			if (this.selectedTopics.length === 0) {
				alert('Please select at least one topic');
				return;
			}
			
			if (!confirm(`Are you sure you want to ${action} ${this.selectedTopics.length} topic(s)?`)) {
				return;
			}
			
			const actionMap = {
				'approve': 'aips_bulk_approve_topics',
				'reject': 'aips_bulk_reject_topics',
				'delete': 'aips_bulk_delete_topics'
			};
			
			try {
				const formData = new FormData();
				formData.append('action', actionMap[action]);
				formData.append('nonce', aipsAuthorsL10n.nonce);
				// WordPress expects array as separate entries
				this.selectedTopics.forEach(id => {
					formData.append('topic_ids[]', id);
				});
				
				const response = await fetch(ajaxurl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					this.showSuccess(data.data?.message || 'Bulk action completed successfully');
					// Reload topics
					this.viewAuthorTopics(this.currentAuthorId, 'Author');
				} else {
					this.showError(data.data?.message || 'Failed to execute bulk action');
				}
			} catch (error) {
				console.error('Error executing bulk action:', error);
				this.showError('Failed to execute bulk action');
			}
		},
		
		// Helper methods
		resetAuthorForm() {
			this.authorForm = {
				id: '',
				name: '',
				field_niche: '',
				keywords: '',
				details: '',
				description: '',
				topic_generation_frequency: 'weekly',
				topic_generation_quantity: 5,
				is_active: true
			};
			this.currentAuthorId = null;
		},
		
		updateStatusCounts() {
			this.statusCounts = {
				pending: this.topics.filter(t => t.status === 'pending').length,
				approved: this.topics.filter(t => t.status === 'approved').length,
				rejected: this.topics.filter(t => t.status === 'rejected').length
			};
		},
		
		closeSlideOver() {
			this.topicsSlideoverOpen = false;
			this.authorSlideoverOpen = false;
		},
		
		showSuccess(message) {
			// Use WordPress admin notices
			const notice = document.createElement('div');
			notice.className = 'notice notice-success is-dismissible';
			notice.innerHTML = '<p>' + message + '</p>';
			document.querySelector('.aips-wrap')?.prepend(notice);
			setTimeout(() => notice.remove(), 3000);
		},
		
		showError(message) {
			// Use WordPress admin notices
			const notice = document.createElement('div');
			notice.className = 'notice notice-error is-dismissible';
			notice.innerHTML = '<p>' + message + '</p>';
			document.querySelector('.aips-wrap')?.prepend(notice);
		},
		
		escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
		}
	}));
});
