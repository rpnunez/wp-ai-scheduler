import BaseModel from './base';
import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Topic Model
 */
export const TopicModel = BaseModel.extend({
	defaults: {
		id: '',
		author_id: '',
		topic_title: '',
		topic_description: '',
		topic_rationale: '',
		status: 'pending',
		reviewed_at: '',
		reviewed_by: '',
		post_id: ''
	},

	actionMap: {
		read: 'aips_edit_topic', // Or custom endpoint
		update: 'aips_edit_topic',
		delete: 'aips_delete_topic'
	},

	parse(resp) {
		if (resp && resp.topic) {
			return resp.topic;
		}
		return resp;
	}
});

/**
 * Topic Collection
 */
export const TopicCollection = Backbone.Collection.extend({
	model: TopicModel,

	sync(method, collection, options) {
		options = options || {};
		options.url = (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl;
		options.type = 'POST';

		const requestData = Object.assign(
			{
				action: 'aips_get_author_topics',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				author_id: options.author_id || '',
				status: options.status || 'pending'
			},
			options.data || {}
		);

		options.data = requestData;

		const success = options.success;
		options.success = function(resp) {
			if (resp && resp.success) {
				if (success) {
					// We return the topics array to the collection populator
					success(resp.data.topics || []);
					// Trigger a custom event to pass status counts if needed
					collection.trigger('sync:counts', resp.data.status_counts || {});
				}
			} else {
				if (options.error) {
					options.error(resp || { message: 'Request failed' });
				}
			}
		};

		return $.ajax(options);
	}
});
