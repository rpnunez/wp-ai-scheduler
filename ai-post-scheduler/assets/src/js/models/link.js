import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Internal Link Suggestion Model
 */
export const LinkModel = BaseModel.extend({
	defaults: {
		id: '',
		source_post_id: '',
		target_post_id: '',
		anchor_text: '',
		similarity_score: 0,
		status: 'pending'
	},

	actionMap: {
		update: 'aips_internal_links_update_status',
		delete: 'aips_internal_links_delete'
	}
});

/**
 * Internal Link Suggestion Collection
 */
export const LinkCollection = Backbone.Collection.extend({
	model: LinkModel,
	url: () => (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
});
