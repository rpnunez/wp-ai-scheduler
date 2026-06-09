import Backbone from 'backbone';
import { AuthorModel } from '../models/author';

/**
 * Authors View Controller (Wrapper / Delegator)
 */
export const AuthorsView = Backbone.View.extend({
	el: 'body',

	events: {
		// Author CRUD
		'click .aips-add-author-btn': 'openAddModal',
		'click .aips-edit-author': 'editAuthor',
		'click .aips-save-author': 'saveAuthor',
		'click .aips-delete-author': 'deleteAuthor',
		'click .aips-generate-topics-now': 'generateTopicsNow',
		'click .aips-generate-author-posts-now': 'generateAuthorPostsNow',

		// Topic approvals & feedback
		'click .aips-approve-topic': 'approveTopic',
		'click .aips-reject-topic': 'rejectTopic',
		'click .aips-delete-topic': 'deleteTopic',
		'click .aips-edit-topic': 'editTopic',
		'click .aips-save-topic': 'saveTopic',
		'click .aips-cancel-edit-topic': 'cancelEditTopic',
		'click .aips-generate-post-now': 'generatePostNow',
		'click .aips-view-topic-log': 'viewTopicLog',

		// Row expansions
		'click .aips-row-action-overflow-toggle': 'onRowActionOverflowToggle',
		'click .aips-row-action-menu .aips-row-action-item': 'onRowActionItemClick',
		'click .aips-topic-expand-btn': 'toggleTopicDetail',
		'click .topic-title-cell': 'onTopicTitleCellClick',

		// Suggest authors & bulk operations
		'click #aips-suggest-authors-btn': 'openSuggestModal',
		'click .aips-import-suggested-author': 'importSuggestedAuthor',
		'click .aips-bulk-action-execute': 'executeBulkAction',
		'click #aips-authors-bulk-apply': 'executeAuthorsBulkAction',

		// Queue
		'click .aips-queue-bulk-action-execute': 'executeQueueBulkAction',
		'click .aips-queue-select-all': 'toggleQueueSelectAll',
		'click #aips-queue-reload-btn': 'loadQueueTopics'
	},

	initialize() {
		this.model = new AuthorModel();
	},

	openAddModal(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.openAddModal(e);
		}
	},

	editAuthor(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.editAuthor(e);
		}
	},

	saveAuthor(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.saveAuthor(e);
		}
	},

	deleteAuthor(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.deleteAuthor(e);
		}
	},

	generateTopicsNow(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.generateTopicsNow(e);
		}
	},

	generateAuthorPostsNow(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.generateAuthorPostsNow(e);
		}
	},

	approveTopic(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.approveTopic(e);
		}
	},

	rejectTopic(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.rejectTopic(e);
		}
	},

	deleteTopic(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.deleteTopic(e);
		}
	},

	editTopic(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.editTopic(e);
		}
	},

	saveTopic(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.saveTopic(e);
		}
	},

	cancelEditTopic(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.cancelEditTopic(e);
		}
	},

	generatePostNow(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.generatePostNow(e);
		}
	},

	viewTopicLog(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.viewTopicLog(e);
		}
	},

	onRowActionOverflowToggle(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.onRowActionOverflowToggle(e);
		}
	},

	onRowActionItemClick(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.onRowActionItemClick(e);
		}
	},

	toggleTopicDetail(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.toggleTopicDetail(e);
		}
	},

	onTopicTitleCellClick(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.onTopicTitleCellClick(e);
		}
	},

	openSuggestModal(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.openSuggestModal(e);
		}
	},

	importSuggestedAuthor(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.importSuggestedAuthor(e);
		}
	},

	executeBulkAction(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.executeBulkAction(e);
		}
	},

	executeAuthorsBulkAction(e) {
		if (window.AIPS && window.AIPS.AuthorsModule) {
			window.AIPS.AuthorsModule.executeAuthorsBulkAction(e);
		}
	},

	executeQueueBulkAction(e) {
		if (window.AIPS && window.AIPS.GenerationQueueModule) {
			window.AIPS.GenerationQueueModule.executeQueueBulkAction(e);
		}
	},

	toggleQueueSelectAll(e) {
		if (window.AIPS && window.AIPS.GenerationQueueModule) {
			window.AIPS.GenerationQueueModule.toggleQueueSelectAll(e);
		}
	},

	loadQueueTopics(e) {
		if (window.AIPS && window.AIPS.GenerationQueueModule) {
			window.AIPS.GenerationQueueModule.loadQueueTopics(e);
		}
	}
});
