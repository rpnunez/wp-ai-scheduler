import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Article Structure Model
 */
export const StructureModel = BaseModel.extend({
	idAttribute: 'structure_id',

	defaults: {
		structure_id: '',
		name: '',
		description: '',
		sections: [],
		prompt_template: '',
		is_active: 1
	},

	actionMap: {
		create: 'aips_save_structure',
		read: 'aips_get_structure',
		update: 'aips_save_structure',
		delete: 'aips_delete_structure'
	},

	parse(resp) {
		if (resp && resp.structure) {
			const struct = resp.structure;
			// Convert sections to array if returned as string/json
			if (typeof struct.sections === 'string') {
				try {
					struct.sections = JSON.parse(struct.sections);
				} catch (e) {
					struct.sections = [];
				}
			}
			return struct;
		}
		return resp;
	}
});

/**
 * Structure Collection
 */
export const StructureCollection = Backbone.Collection.extend({
	model: StructureModel
});
