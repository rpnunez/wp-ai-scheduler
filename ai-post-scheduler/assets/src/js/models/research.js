import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Research Model
 */
export const ResearchModel = BaseModel.extend({
	defaults: {
		id: '',
		topic: '',
		score: 0,
		status: 'new',
		niche: '',
		keywords: [],
		researched_at: ''
	},

	actionMap: {
		delete: 'aips_delete_trending_topic'
	},

	destroy(options) {
		options = options || {};
		options.data = {
			action: this.actionMap.delete,
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			topic_id: this.id
		};
		options.type = 'POST';
		options.url = (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl;

		const success = options.success;
		options.success = function(resp) {
			if (resp && resp.success) {
				if (success) success(resp.data);
			} else {
				if (options.error) options.error(resp || { message: 'Delete failed' });
			}
		};

		return Backbone.sync.call(this, 'delete', this, options);
	}
});

/**
 * Research Collection
 */
export const ResearchCollection = Backbone.Collection.extend({
	model: ResearchModel,
	url: () => (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
});
