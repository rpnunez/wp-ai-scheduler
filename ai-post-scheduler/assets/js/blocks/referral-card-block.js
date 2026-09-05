/**
 * Gutenberg Block: Partner Referral Card / Discount Ribbon
 *
 * @package AI_Post_Scheduler
 * @since 3.7.2
 */

(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { createElement: el, useState, useEffect } = wp.element;
	const { InspectorControls } = wp.blockEditor || wp.editor || {};
	const { PanelBody, SelectControl, TextControl, Spinner, Notice } = wp.components;
	const __ = wp.i18n ? wp.i18n.__ : function (text) { return text; };

	registerBlockType('aips/referral-card', {
		apiVersion: 2,
		title: __('Partner Referral Promo Box', 'ai-post-scheduler'),
		description: __('Displays an interactive partner referral ribbon with coupon code and 1-click copy button.', 'ai-post-scheduler'),
		icon: 'share',
		category: 'widgets',
		keywords: [__('referral', 'ai-post-scheduler'), __('affiliate', 'ai-post-scheduler'), __('discount', 'ai-post-scheduler'), __('coupon', 'ai-post-scheduler')],
		attributes: {
			programId: {
				type: 'number',
				default: 0
			}
		},

		edit: function (props) {
			const { attributes, setAttributes } = props;
			const [programs, setPrograms] = useState([]);
			const [loading, setLoading] = useState(true);

			useEffect(function () {
				if (window.ajaxurl) {
					var nonce = (window.aipsMonetizationAdminConfig && window.aipsMonetizationAdminConfig.nonce) || '';
					jQuery.post(window.ajaxurl, {
						action: 'aips_get_referral_programs',
						nonce: nonce
					}, function (res) {
						setLoading(false);
						if (res.success && Array.isArray(res.data.programs)) {
							setPrograms(res.data.programs);
						}
					}).fail(function () {
						setLoading(false);
					});
				} else {
					setLoading(false);
				}
			}, []);

			const selectedProgram = programs.find(function (p) {
				return parseInt(p.id, 10) === attributes.programId;
			});

			const options = [{ label: __('-- Auto-Match by Post Topic --', 'ai-post-scheduler'), value: 0 }];
			programs.forEach(function (p) {
				options.push({
					label: p.partner_name + (p.promo_code ? ' (' + p.promo_code + ')' : ''),
					value: parseInt(p.id, 10)
				});
			});

			return el(
				'div',
				{ className: 'aips-block-referral-preview' },
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __('Referral Program Settings', 'ai-post-scheduler'), initialOpen: true },
						loading ? el(Spinner, {}) : el(
							SelectControl,
							{
								label: __('Select Partner Program', 'ai-post-scheduler'),
								value: attributes.programId,
								options: options,
								onChange: function (val) {
									setAttributes({ programId: parseInt(val, 10) || 0 });
								}
							}
						)
					)
				),
				el(
					'div',
					{
						style: {
							padding: '16px',
							border: '1px solid #10b981',
							background: '#f0fdf4',
							borderRadius: '6px',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'space-between',
							gap: '12px'
						}
					},
					el(
						'div',
						{},
						el('strong', { style: { display: 'block', color: '#065f46', fontSize: '15px' } }, selectedProgram ? selectedProgram.partner_name : __('Partner Referral Card [Auto Match]', 'ai-post-scheduler')),
						el('span', { style: { fontSize: '13px', color: '#047857' } }, selectedProgram && selectedProgram.discount_description ? selectedProgram.discount_description : __('High-converting referral promotion with 1-click copy code.', 'ai-post-scheduler'))
					),
					selectedProgram && selectedProgram.promo_code ? el(
						'span',
						{
							style: {
								fontFamily: 'monospace',
								background: '#ffffff',
								border: '1px dashed #059669',
								padding: '4px 8px',
								borderRadius: '4px',
								fontWeight: 'bold',
								color: '#047857'
							}
						},
						selectedProgram.promo_code
					) : null
				)
			);
		},

		save: function () {
			// Dynamic server-side rendered block via AIPS_Referral_Delivery_Service::render_block
			return null;
		}
	});

})(window.wp);
