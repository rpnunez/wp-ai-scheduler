import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Template Model
 */
export const TemplateModel = BaseModel.extend({
	defaults: {
		id: '',
		name: '',
		description: '',
		prompt_template: '',
		title_prompt: '',
		post_quantity: 1,
		generate_featured_image: false,
		image_prompt: '',
		featured_image_source: 'ai_prompt',
		featured_image_unsplash_keywords: '',
		featured_image_media_ids: '',
		post_status: 'draft',
		post_category: [],
		post_tags: '',
		post_author: '',
		is_active: true,
		campaign_id: '',
		include_sources: false,
		source_group_ids: '[]'
	},

	actionMap: {
		create: 'aips_save_template',
		read: 'aips_get_template',
		update: 'aips_save_template',
		delete: 'aips_delete_template'
	},

	// Parse custom request fields
	parse(resp) {
		// If response is the wrapper database row, return it
		if (resp && resp.template) {
			return resp.template;
		}
		return resp;
	}
});

/**
 * Template Collection
 */
export const TemplateCollection = Backbone.Collection.extend({
	model: TemplateModel
});
