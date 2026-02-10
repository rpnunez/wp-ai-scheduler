/**
 * Post Filters Component
 *
 * Handles filtering by status, search, and template.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, TextControl, SelectControl } from '@wordpress/components';
import { search } from '@wordpress/icons';

const PostFilters = ({ filters, templates, onFilterChange }) => {
	const [searchInput, setSearchInput] = useState(filters.search);

	const handleSearchSubmit = (e) => {
		e.preventDefault();
		onFilterChange({ search: searchInput });
	};

	const handleStatusChange = (status) => {
		onFilterChange({ status });
	};

	const handleTemplateChange = (template_id) => {
		onFilterChange({ template_id: parseInt(template_id, 10) });
	};

	const statusOptions = [
		{ value: 'all', label: __('All', 'ai-post-scheduler') },
		{ value: 'draft', label: __('Draft', 'ai-post-scheduler') },
		{ value: 'pending', label: __('Pending Review', 'ai-post-scheduler') },
		{ value: 'published', label: __('Published', 'ai-post-scheduler') },
	];

	const templateOptions = [
		{ value: 0, label: __('All Templates', 'ai-post-scheduler') },
		...templates.map(template => ({
			value: template.id,
			label: template.name,
		})),
	];

	return (
		<div className="aips-post-filters">
			<div className="aips-filter-row">
				{/* Status Filter */}
				<div className="aips-filter-tabs">
					{statusOptions.map(option => (
						<button
							key={option.value}
							className={`aips-filter-tab ${filters.status === option.value ? 'active' : ''}`}
							onClick={() => handleStatusChange(option.value)}
						>
							{option.label}
						</button>
					))}
				</div>

				{/* Search */}
				<form className="aips-search-form" onSubmit={handleSearchSubmit}>
					<TextControl
						placeholder={__('Search posts...', 'ai-post-scheduler')}
						value={searchInput}
						onChange={setSearchInput}
					/>
					<Button
						type="submit"
						icon={search}
						variant="secondary"
					>
						{__('Search', 'ai-post-scheduler')}
					</Button>
				</form>
			</div>

			{/* Template Filter */}
			{templates.length > 0 && (
				<div className="aips-filter-row">
					<SelectControl
						label={__('Filter by Template', 'ai-post-scheduler')}
						value={filters.template_id}
						options={templateOptions}
						onChange={handleTemplateChange}
					/>
				</div>
			)}
		</div>
	);
};

export default PostFilters;
