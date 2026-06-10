import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Voice Model
 */
export const VoiceModel = BaseModel.extend({
	idAttribute: 'voice_id',

	defaults: {
		voice_id: '',
		name: '',
		title_prompt: '',
		content_instructions: '',
		excerpt_instructions: '',
		is_active: 1
	},

	actionMap: {
		create: 'aips_save_voice',
		read: 'aips_get_voice',
		update: 'aips_save_voice',
		delete: 'aips_delete_voice'
	},

	parse(resp) {
		if (resp && resp.voice) {
			return resp.voice;
		}
		return resp;
	}
});

/**
 * Voice Collection
 */
export const VoiceCollection = Backbone.Collection.extend({
	model: VoiceModel
});
