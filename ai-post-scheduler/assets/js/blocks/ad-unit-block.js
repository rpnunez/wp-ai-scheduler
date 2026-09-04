/**
 * AIPS Ad Unit Gutenberg Block
 *
 * Provides a dedicated block allowing editors to insert a specific ad slot
 * directly into the block editor content.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element) {
		return;
	}

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	const { registerBlockType } = wp.blocks;
	const { createElement: el, useState, useEffect } = wp.element;
	const { InspectorControls } = wp.blockEditor || wp.editor || {};
	const { PanelBody, SelectControl, TextControl, Placeholder, Spinner } = wp.components;
	const apiFetch = wp.apiFetch;
	const __ = wp.i18n ? wp.i18n.__ : function (text) { return text; };

	/**
	 * @namespace AIPS.AdUnitBlock
	 */
	AIPS.AdUnitBlock = {

		/**
		 * Register block type.
		 */
		init: function () {
			registerBlockType('aips/ad-unit', {
				title: __('AIPS Ad Unit', 'ai-post-scheduler'),
				description: __('Insert an automated or custom ad slot into post content.', 'ai-post-scheduler'),
				icon: 'money-alt',
				category: 'widgets',
				attributes: {
					slotId: {
						type: 'number',
						default: 0
					},
					slotName: {
						type: 'string',
						default: ''
					}
				},

				edit: function (props) {
					const { attributes, setAttributes } = props;
					const [slots, setSlots] = useState([]);
					const [isLoading, setIsLoading] = useState(true);

					useEffect(function () {
						apiFetch({ path: '/aips/v1/monetization/slots' })
							.then(function (res) {
								if (res && res.slots) {
									setSlots(res.slots);
									// Default select first slot if not set
									if (!attributes.slotId && res.slots.length > 0) {
										setAttributes({
											slotId: res.slots[0].id,
											slotName: res.slots[0].name
										});
									}
								}
								setIsLoading(false);
							})
							.catch(function () {
								setIsLoading(false);
							});
					}, []);

					const slotOptions = slots.map(function (s) {
						return {
							label: s.name + ' (' + s.slot_type + ')',
							value: s.id
						};
					});

					const currentSlot = slots.find(function (s) {
						return s.id === attributes.slotId;
					});

					return el('div', { className: 'aips-ad-unit-block-editor-wrapper' },
						el(InspectorControls, {},
							el(PanelBody, { title: __('Ad Unit Settings', 'ai-post-scheduler'), initialOpen: true },
								isLoading ? el(Spinner) : el(SelectControl, {
									label: __('Select Ad Slot', 'ai-post-scheduler'),
									value: attributes.slotId,
									options: [{ label: __('-- Select Slot --', 'ai-post-scheduler'), value: 0 }].concat(slotOptions),
									onChange: function (val) {
										const selectedId = parseInt(val, 10);
										const selected = slots.find(function (s) { return s.id === selectedId; });
										setAttributes({
											slotId: selectedId,
											slotName: selected ? selected.name : ''
										});
									}
								})
							)
						),
						el(Placeholder, {
							icon: 'money-alt',
							label: __('AIPS Ad Unit Placement', 'ai-post-scheduler'),
							instructions: currentSlot
								? __('Slot: ', 'ai-post-scheduler') + currentSlot.name + ' [' + currentSlot.slot_type + ']'
								: __('Select an ad slot from the sidebar settings.', 'ai-post-scheduler')
						},
							isLoading && el(Spinner),
							!isLoading && el('div', { className: 'aips-ad-unit-placeholder-meta' },
								el('span', { className: 'aips-ad-preview-tag' }, currentSlot ? currentSlot.slot_type : 'Ad Slot'),
								el('span', { className: 'aips-ad-preview-device' }, currentSlot ? ('Device: ' + currentSlot.device_targeting) : '')
							)
						)
					);
				},

				save: function (props) {
					// Dynamic block rendered via PHP / shortcode
					return null;
				}
			});
		}
	};

	AIPS.AdUnitBlock.init();

})(window.wp);
