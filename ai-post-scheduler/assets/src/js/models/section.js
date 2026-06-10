import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Prompt Section Model
 */
export const SectionModel = BaseModel.extend({
	idAttribute: 'section_id',

	defaults: {
		section_id: '',
		name: '',
		section_key: '',
		description: '',
		content: '',
		is_active: 1
	},

	actionMap: {
		create: 'aips_save_prompt_section',
		read: 'aips_get_prompt_section',
		update: 'aips_save_prompt_section',
		delete: 'aips_delete_prompt_section'
	},

	parse(resp) {
		if (resp && resp.section) {
			return resp.section;
		}
		return resp;
	}
});

/**
 * Section Collection
 */
export const SectionCollection = Backbone.Collection.extend({
	model: SectionModel
});
