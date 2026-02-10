/**
 * Generated Posts React App - Main Component
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Notice } from '@wordpress/components';
import PostFilters from './PostFilters';
import PostsList from './PostsList';
import SessionModal from './SessionModal';

const GeneratedPostsApp = () => {
	const [posts, setPosts] = useState([]);
	const [templates, setTemplates] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [filters, setFilters] = useState({
		status: 'all',
		search: '',
		template_id: 0,
	});
	const [pagination, setPagination] = useState({
		page: 1,
		per_page: 20,
		total: 0,
		pages: 0,
	});
	const [sessionModalOpen, setSessionModalOpen] = useState(false);
	const [sessionId, setSessionId] = useState(null);

	// Configure API fetch with nonce
	useEffect(() => {
		apiFetch.use(apiFetch.createNonceMiddleware(window.aipsGeneratedPostsReact.nonce));
		apiFetch.use(apiFetch.createRootURLMiddleware(window.aipsGeneratedPostsReact.restUrl));
	}, []);

	// Fetch templates for filter dropdown
	useEffect(() => {
		const fetchTemplates = async () => {
			try {
				const response = await apiFetch({
					path: '/templates',
				});
				setTemplates(response.templates || []);
			} catch (err) {
				console.error('Failed to fetch templates:', err);
			}
		};

		fetchTemplates();
	}, []);

	// Fetch posts when filters or pagination change
	useEffect(() => {
		const fetchPosts = async () => {
			setLoading(true);
			setError(null);

			try {
				const queryParams = new URLSearchParams({
					page: pagination.page,
					per_page: pagination.per_page,
					status: filters.status,
					search: filters.search,
					template_id: filters.template_id,
				});

				const response = await apiFetch({
					path: `/generated-posts?${queryParams.toString()}`,
				});

				setPosts(response.posts || []);
				setPagination(prev => ({
					...prev,
					total: response.total,
					pages: response.pages,
				}));
			} catch (err) {
				console.error('Failed to fetch posts:', err);
				setError(__('Failed to load generated posts. Please try again.', 'ai-post-scheduler'));
			} finally {
				setLoading(false);
			}
		};

		fetchPosts();
	}, [filters, pagination.page, pagination.per_page]);

	const handleFilterChange = (newFilters) => {
		setFilters(prev => ({ ...prev, ...newFilters }));
		setPagination(prev => ({ ...prev, page: 1 })); // Reset to page 1 on filter change
	};

	const handlePageChange = (newPage) => {
		setPagination(prev => ({ ...prev, page: newPage }));
		window.scrollTo({ top: 0, behavior: 'smooth' });
	};

	const handleViewSession = (historyId) => {
		setSessionId(historyId);
		setSessionModalOpen(true);
	};

	const handleDeletePost = async (postId) => {
		if (!confirm(__('Are you sure you want to delete this post?', 'ai-post-scheduler'))) {
			return;
		}

		try {
			await apiFetch({
				path: `/generated-posts/${postId}`,
				method: 'DELETE',
			});

			// Refresh the list
			setPosts(posts.filter(post => post.post_id !== postId));
			setPagination(prev => ({ ...prev, total: prev.total - 1 }));
		} catch (err) {
			console.error('Failed to delete post:', err);
			alert(__('Failed to delete post. Please try again.', 'ai-post-scheduler'));
		}
	};

	return (
		<div className="aips-generated-posts-react">
			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			<PostFilters
				filters={filters}
				templates={templates}
				onFilterChange={handleFilterChange}
			/>

			{loading ? (
				<div className="aips-loading-container">
					<Spinner />
					<p>{__('Loading posts...', 'ai-post-scheduler')}</p>
				</div>
			) : (
				<PostsList
					posts={posts}
					pagination={pagination}
					onPageChange={handlePageChange}
					onViewSession={handleViewSession}
					onDeletePost={handleDeletePost}
				/>
			)}

			{sessionModalOpen && (
				<SessionModal
					sessionId={sessionId}
					onClose={() => setSessionModalOpen(false)}
				/>
			)}
		</div>
	);
};

export default GeneratedPostsApp;
