import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Reusable Base View for Modal container wrappers.
 */
export const BaseModalView = Backbone.View.extend({
	events: {
		'click .aips-modal-close': 'close',
		'click .aips-modal-cancel': 'close',
		'click .aips-modal-backdrop': 'onBackdropClick'
	},

	initialize() {
		$(document).on('keydown', this.handleKeyDown.bind(this));
	},

	open(e) {
		if (e) e.preventDefault();
		this.$el.fadeIn(200);
		this.trigger('open');
	},

	close(e) {
		if (e) e.preventDefault();
		this.$el.fadeOut(200);
		this.trigger('close');
	},

	onBackdropClick(e) {
		if ($(e.target).hasClass('aips-modal-backdrop') || $(e.target).hasClass('aips-modal-wrapper')) {
			this.close();
		}
	},

	handleKeyDown(e) {
		if (e.key === 'Escape' && this.$el.is(':visible')) {
			this.close();
		}
	},

	remove() {
		$(document).off('keydown', this.handleKeyDown.bind(this));
		Backbone.View.prototype.remove.call(this);
	}
});
