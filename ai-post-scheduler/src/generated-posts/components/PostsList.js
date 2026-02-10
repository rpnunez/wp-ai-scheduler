/**
 * Posts List Component
 *
 * Displays the table of generated posts with pagination.
 */

import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const PostsList = ({ posts, pagination, onPageChange, onViewSession, onDeletePost }) => {
	const getStatusBadgeClass = (status) => {
		const statusMap = {
			publish: 'published',
			draft: 'draft',
			pending: 'pending',
		};
		return statusMap[status] || 'default';
	};

	const renderPagination = () => {
		if (pagination.pages <= 1) {
			return null;
		}

		const pages = [];
		const currentPage = pagination.page;
		const totalPages = pagination.pages;

		// Always show first page
		pages.push(1);

		// Add pages around current page
		for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
			if (i > 1 && pages[pages.length - 1] !== i - 1) {
				pages.push('...');
			}
			pages.push(i);
		}

		// Always show last page
		if (totalPages > 1) {
			if (pages[pages.length - 1] !== totalPages - 1) {
				pages.push('...');
			}
			pages.push(totalPages);
		}

		return (
			<div className="aips-pagination">
				<Button
					variant="secondary"
					disabled={currentPage <= 1}
					onClick={() => onPageChange(currentPage - 1)}
				>
					{__('Previous', 'ai-post-scheduler')}
				</Button>

				<div className="aips-pagination-pages">
					{pages.map((page, index) =>
						page === '...' ? (
							<span key={`ellipsis-${index}`} className="aips-pagination-ellipsis">
								...
							</span>
						) : (
							<button
								key={page}
								className={`aips-pagination-page ${currentPage === page ? 'active' : ''}`}
								onClick={() => onPageChange(page)}
							>
								{page}
							</button>
						)
					)}
				</div>

				<Button
					variant="secondary"
					disabled={currentPage >= totalPages}
					onClick={() => onPageChange(currentPage + 1)}
				>
					{__('Next', 'ai-post-scheduler')}
				</Button>
			</div>
		);
	};

	if (posts.length === 0) {
		return (
			<div className="aips-empty-state">
				<p>{__('No generated posts found.', 'ai-post-scheduler')}</p>
			</div>
		);
	}

	return (
		<div className="aips-posts-list">
			<div className="aips-table-info">
				<span>
					{__('Total:', 'ai-post-scheduler')} {pagination.total} {__('posts', 'ai-post-scheduler')}
				</span>
			</div>

			<table className="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>{__('Title', 'ai-post-scheduler')}</th>
						<th>{__('Template', 'ai-post-scheduler')}</th>
						<th>{__('Author', 'ai-post-scheduler')}</th>
						<th>{__('Status', 'ai-post-scheduler')}</th>
						<th>{__('Date Generated', 'ai-post-scheduler')}</th>
						<th>{__('Actions', 'ai-post-scheduler')}</th>
					</tr>
				</thead>
				<tbody>
					{posts.map(post => (
						<tr key={post.id}>
							<td>
								<strong>
									<a href={post.edit_link} target="_blank" rel="noopener noreferrer">
										{post.title}
									</a>
								</strong>
							</td>
							<td>{post.template_name || __('N/A', 'ai-post-scheduler')}</td>
							<td>{post.author}</td>
							<td>
								<span className={`aips-status-badge aips-status-${getStatusBadgeClass(post.status)}`}>
									{post.status_label}
								</span>
							</td>
							<td>{post.date_generated_formatted}</td>
							<td className="aips-actions-cell">
								<Button
									variant="secondary"
									size="small"
									href={post.edit_link}
									target="_blank"
								>
									{__('Edit', 'ai-post-scheduler')}
								</Button>
								<Button
									variant="secondary"
									size="small"
									onClick={() => onViewSession(post.id)}
								>
									{__('View Session', 'ai-post-scheduler')}
								</Button>
								{post.status === 'publish' && (
									<Button
										variant="link"
										size="small"
										href={post.view_link}
										target="_blank"
									>
										{__('View', 'ai-post-scheduler')}
									</Button>
								)}
								<Button
									variant="link"
									size="small"
									isDestructive
									onClick={() => onDeletePost(post.post_id)}
								>
									{__('Delete', 'ai-post-scheduler')}
								</Button>
							</td>
						</tr>
					))}
				</tbody>
			</table>

			{renderPagination()}
		</div>
	);
};

export default PostsList;
