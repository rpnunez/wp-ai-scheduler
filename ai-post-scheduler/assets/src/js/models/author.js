import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Author Model
 */
export const AuthorModel = BaseModel.extend({
	defaults: {
		id: '',
		name: '',
		niche: '',
		voice_id: '',
		structure_id: '',
		topics_count: 5,
		is_active: true,
		topic_prompt: '',
		article_prompt: '',
		post_status: 'draft',
		post_category: [],
		post_tags: '',
		schedule_value: 1,
		schedule_unit: 'days',
		start_time: '',
		writing_style: '',
		generation_mode: 'auto_publish',
		min_word_count: 1000,
		max_word_count: 2000,
		include_sources: false,
		source_group_ids: '[]'
	},

	actionMap: {
		create: 'aips_save_author',
		read: 'aips_get_author',
		update: 'aips_save_author',
		delete: 'aips_delete_author'
	},

	parse(resp) {
		if (resp && resp.author) {
			return resp.author;
		}
		return resp;
	}
});

/**
 * Author Collection
 */
export const AuthorCollection = Backbone.Collection.extend({
	model: AuthorModel
});
