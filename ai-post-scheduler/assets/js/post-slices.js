/**
 * Admin Post Slices page JS.
 *
 * Handles add, edit, delete, toggle-active, and search interactions for the
 * Post Slices management UI. Backed by AIPS.Core.Model/Collection/View (see
 * assets/js/core/core-backbone.js) — the table starts server-rendered (no
 * extra AJAX round-trip on page load) and the collection is bootstrapped
 * from the initial rows' data-* attributes; every mutation after that goes
 * through the collection/model so the view re-renders itself instead of a
 * full page reload.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.PostSlices = {
		currentSliceId: 0,
		sliceCollection: null,
		slicesView: null,

		/**
		 * Bootstrap the page module.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
			this.bootstrapCollection();
		},

		/**
		 * Seed the collection from the server-rendered rows already in the
		 * DOM (no AJAX call on page load — the initial HTML stays as the
		 * first paint). The view only takes over rendering once the
		 * collection changes for the first time.
		 *
		 * @return {void}
		 */
		bootstrapCollection: function () {
			var initial = [];
			$('#aips-post-slices-table tbody tr').each(function () {
				var $row = $(this);
				initial.push({
					id:          parseInt($row.data('slice-id'), 10) || 0,
					name:        String($row.data('name') || ''),
					description: String($row.data('description') || ''),
					sort_order:  parseInt($row.data('sort-order'), 10) || 0,
					is_active:   parseInt($row.data('active'), 10) === 1 ? 1 : 0
				});
			});

			this.sliceCollection = new AIPS.PostSlices.SliceCollection(initial);
			this.slicesView = new AIPS.PostSlices.SlicesView({ collection: this.sliceCollection });
		},

		/**
		 * Register delegated event handlers.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			$(document).on('click', '#aips-add-post-slice-btn, #aips-add-post-slice-empty-btn', this.openAddModal.bind(this));
			$(document).on('click', '.aips-edit-post-slice', this.openEditModal.bind(this));
			$(document).on('click', '#aips-save-post-slice-btn', this.saveSlice.bind(this));
			$(document).on('click', '.aips-delete-post-slice', this.deleteSlice.bind(this));
			$(document).on('click', '.aips-toggle-post-slice', this.toggleSlice.bind(this));
			// Modal close (button + backdrop click + Escape) is handled globally by admin.js.
			$(document).on('input', '#aips-post-slice-search', this.filterSlices.bind(this));
			$(document).on('click', '#aips-post-slice-search-clear, #aips-post-slice-search-clear-2', this.clearSearch.bind(this));
		},

		/**
		 * Open the modal for a new post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openAddModal: function (e) {
			e.preventDefault();
			this.currentSliceId = 0;
			this.resetForm();
			AIPS.Core.Modal.open('#aips-post-slice-modal', {
				title: aipsPostSlicesL10n.addNewSlice,
				focusSelector: '#aips-post-slice-name'
			});
		},

		/**
		 * Open the modal for an existing post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openEditModal: function (e) {
			e.preventDefault();

			var id = parseInt($(e.currentTarget).data('id'), 10);
			var model = this.sliceCollection.get(id);
			if (!model) { return; }

			this.currentSliceId = id;

			AIPS.Core.Modal.populateFields('#aips-post-slice-modal', {
				'#aips-post-slice-id': id,
				'#aips-post-slice-name': model.get('name') || '',
				'#aips-post-slice-description': model.get('description') || '',
				'#aips-post-slice-sort-order': model.get('sort_order') || 0,
				'#aips-post-slice-is-active': parseInt(model.get('is_active'), 10) === 1
			});

			AIPS.Core.Modal.open('#aips-post-slice-modal', {
				title: aipsPostSlicesL10n.editSlice,
				focusSelector: '#aips-post-slice-name'
			});
		},

		/**
		 * Reset modal fields.
		 *
		 * @return {void}
		 */
		resetForm: function () {
			AIPS.Core.Modal.resetFields('#aips-post-slice-modal', {
				'#aips-post-slice-id': 0,
				'#aips-post-slice-name': '',
				'#aips-post-slice-description': '',
				'#aips-post-slice-sort-order': 0,
				'#aips-post-slice-is-active': true
			});
		},

		/**
		 * Create or update a post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		saveSlice: function (e) {
			e.preventDefault();

			var name = $('#aips-post-slice-name').val().trim();

			if (!name) {
				AIPS.Utilities.showToast(aipsPostSlicesL10n.nameRequired, 'error');
				$('#aips-post-slice-name').trigger('focus');
				return;
			}

			var attrs = {
				name:        name,
				description: $('#aips-post-slice-description').val().trim(),
				sort_order:  parseInt($('#aips-post-slice-sort-order').val(), 10) || 0,
				is_active:   $('#aips-post-slice-is-active').is(':checked') ? 1 : 0
			};

			var self = this;
			var isNew = !this.currentSliceId;
			var model = isNew ? new AIPS.PostSlices.SliceModel() : this.sliceCollection.get(this.currentSliceId);
			if (!model) { return; }

			model.save(attrs, {
				wait: true,
				$button: $('#aips-save-post-slice-btn'),
				loadingLabel: aipsPostSlicesL10n.saving,
				errorFallback: aipsPostSlicesL10n.saveFailed,
				// Backbone.Model#save calls success as (model, response, options) —
				// see the callback-shape note in core-backbone.js. No error
				// callback needed: ajaxRequest's own toastOnError already
				// surfaces failures (adding one here would double-toast).
				success: function (savedModel, response) {
					$('#aips-post-slice-modal').hide();
					AIPS.Utilities.showToast(response.message, 'success');
					if (isNew) {
						self.sliceCollection.add(savedModel);
					}
				}
			});
		},

		/**
		 * Delete a post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		deleteSlice: function (e) {
			e.preventDefault();

			var id = parseInt($(e.currentTarget).data('id'), 10);
			var model = this.sliceCollection.get(id);
			if (!model) { return; }

			AIPS.Core.Modal.confirmDelete({
				message: aipsPostSlicesL10n.deleteConfirm,
				onConfirm: function () {
					model.destroy({
						wait: true,
						errorFallback: aipsPostSlicesL10n.deleteFailed,
						// Backbone.Model#destroy calls success as (model, response, options).
						success: function (destroyedModel, response) {
							AIPS.Utilities.showToast(response.message, 'success');
						}
						// No manual row removal — Backbone removes the model on
						// success, which fires 'remove' on the collection, which
						// the view listens to and re-renders from.
					});
				}
			});
		},

		/**
		 * Toggle active status.
		 *
		 * Kept as a plain AIPS.Core.Http.ajaxRequest call (not routed through
		 * Backbone.sync) since aips_toggle_post_slice_active is a narrower
		 * action than the full create/update save shape — the model is
		 * updated client-side on success so the view still reacts to it.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleSlice: function (e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var id = parseInt($btn.data('id'), 10);
			var model = this.sliceCollection.get(id);
			if (!model) { return; }

			var isActive = parseInt(model.get('is_active'), 10);
			var newStatus = isActive === 1 ? 0 : 1;

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_toggle_post_slice_active',
				data: {
					slice_id:  id,
					is_active: newStatus,
				},
				errorFallback: aipsPostSlicesL10n.toggleFailed,
				onSuccess: function (data) {
					AIPS.Utilities.showToast(data.message, 'success');
					model.set('is_active', newStatus);
				}
			});
		},

		/**
		 * Filter the table by search text.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		filterSlices: function (e) {
			AIPS.Core.Table.filterRows({
				term: $(e.currentTarget).val(),
				$rows: $('#aips-post-slices-table tbody tr'),
				$clearButton: $('#aips-post-slice-search-clear'),
				$noResults: $('#aips-post-slice-search-no-results')
			});
		},

		/**
		 * Clear the search input.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		clearSearch: function (e) {
			e.preventDefault();
			$('#aips-post-slice-search').val('').trigger('input');
		},
	};

	// -----------------------------------------------------------------------
	// Model/Collection/View (pilot #2 — see assets/js/core/core-backbone.js
	// for the sync adapter these build on, and cache-monitor.js's Entries
	// tab for the first pilot). Unlike Cache Monitor, this backend supports
	// full CRUD, so create/update/delete all go through Backbone.sync;
	// only toggle-active stays a plain ajaxRequest call (see toggleSlice()).
	// -----------------------------------------------------------------------

	AIPS.PostSlices.SliceModel = AIPS.Core.Model.extend({
		idAttribute: 'id',
		idParam: 'slice_id', // ajax_save/delete_post_slice read $_POST['slice_id'], not 'id'.
		ajaxActions: {
			create: 'aips_save_post_slice',
			update: 'aips_save_post_slice', // Same action for both; the PHP side branches on slice_id.
			delete: 'aips_delete_post_slice'
		},
		// ajax_save_post_slice's response is {message, slice_id, slice}; unwrap
		// to the record itself so model.set() doesn't pick up the wrapper keys.
		parse: function (response) {
			return (response && response.slice) ? response.slice : {};
		}
	});

	AIPS.PostSlices.SliceCollection = AIPS.Core.Collection.extend({
		model: AIPS.PostSlices.SliceModel,
		resultsKey: 'slices',
		ajaxActions: { read: 'aips_get_post_slices' }
	});

	AIPS.PostSlices.SlicesView = AIPS.Core.View.extend({
		el: '#aips-post-slices-table tbody',
		templateId: 'aips-tmpl-post-slice-row',
		// The row template composes trusted sub-HTML (status badge, the
		// "no description" placeholder) rather than being flat scalar
		// fields, so it needs renderRaw() — buildRowData() below is
		// responsible for escaping every user-controlled value itself
		// before it reaches renderModel().
		rawTemplate: true,

		initialize: function () {
			// 'sync' alone would cover a fetch() (Backbone fires both 'reset'
			// and 'sync' for a {reset: true} fetch -- listening to both would
			// double-render); kept here since this collection is bootstrapped
			// from the DOM today rather than fetched, so 'sync'/'reset' don't
			// currently fire, but 'add'/'remove'/'change' do (create/delete/
			// toggle).
			this.listenTo(this.collection, 'add remove change sync', this.render);
		},

		render: function () {
			var hasItems = this.collection.length > 0;
			$('#aips-post-slices-table-wrapper').toggle(hasItems);
			$('#aips-post-slices-empty').toggle(!hasItems);

			if (hasItems) {
				var html = '';
				var sorted = this.collection.sortBy(function (model) {
					return parseInt(model.get('sort_order'), 10) || 0;
				});
				$.each(sorted, function (i, model) {
					html += this.renderModel(this.buildRowData(model));
				}.bind(this));
				this.$el.html(html);
			}

			this.updateSummary();
			return this;
		},

		/**
		 * Build the (already-escaped) template data for one row.
		 *
		 * @param {Backbone.Model} model
		 * @return {Object}
		 */
		buildRowData: function (model) {
			var esc = AIPS.Templates.escape;
			var isActive = parseInt(model.get('is_active'), 10) === 1;
			var name = esc(model.get('name') || '');
			var description = esc(model.get('description') || '');

			return {
				id: model.id,
				name: name,
				description: description,
				description_display: description
					? description
					: '<span class="cell-meta">' + esc(aipsPostSlicesL10n.noDescription) + '</span>',
				sort_order: model.get('sort_order') || 0,
				is_active: isActive ? 1 : 0,
				status_badge: isActive
					? '<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> ' + esc(aipsPostSlicesL10n.active) + '</span>'
					: '<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> ' + esc(aipsPostSlicesL10n.inactive) + '</span>',
				toggle_title: isActive ? aipsPostSlicesL10n.deactivate : aipsPostSlicesL10n.activate,
				toggle_icon_class: isActive ? 'dashicons-hidden' : 'dashicons-visibility'
			};
		},

		/**
		 * Refresh the Total/Active/Inactive summary cards from the
		 * collection's current state.
		 *
		 * @return {void}
		 */
		updateSummary: function () {
			var total = this.collection.length;
			var active = this.collection.filter(function (model) {
				return parseInt(model.get('is_active'), 10) === 1;
			}).length;

			$('#aips-post-slices-count-total').text(total);
			$('#aips-post-slices-count-active').text(active);
			$('#aips-post-slices-count-inactive').text(total - active);
		}
	});

	AIPS.initPostSlices = function () {
		AIPS.PostSlices.init();
	};

	$(document).ready(function () {
		AIPS.initPostSlices();
	});

})(jQuery);
