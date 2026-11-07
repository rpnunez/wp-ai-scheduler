/**
 * AI Post Scheduler - Gutenberg Block Editor Integration
 *
 * Provides Document Sidebar Panel for post components (Title, Excerpt) and
 * inline Block Toolbar extension for paragraph/heading/list rewriting with
 * presets, custom directives, variant cards, and internal link weaving.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */
(function (wp, $) {
	'use strict';

	if (!wp || !wp.plugins || !wp.element || !wp.components || !wp.data) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var Fragment = wp.element.Fragment;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Popover = wp.components.Popover;
	var ToolbarButton = wp.components.ToolbarButton || wp.blockEditor.ToolbarButton;
	var BlockControls = wp.blockEditor.BlockControls;
	var PluginDocumentSettingPanel = wp.editPost ? wp.editPost.PluginDocumentSettingPanel : null;
	var addFilter = wp.hooks.addFilter;

	var l10n = window.aipsEditorL10n || {};

	function notify(message, type) {
		if (message && wp.data && wp.data.dispatch('core/notices')) {
			wp.data.dispatch('core/notices').createNotice(
				type || 'success',
				message,
				{ type: 'snackbar', isDismissible: true }
			);
		}
	}

	/* ==========================================================================
	   1. Document Settings Panel (Post-level: Title, Excerpt Variants)
	   ========================================================================== */
	function DocumentPanel() {
		var [titleVariants, setTitleVariants] = useState([]);
		var [excerptVariants, setExcerptVariants] = useState([]);
		var [busyComponent, setBusyComponent] = useState(null);

		function generateVariants(component) {
			setBusyComponent(component);
			$.post(l10n.ajaxurl, {
				action: 'aips_editor_generate_component_variants',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				component: component
			})
			.done(function (response) {
				if (response && response.success && response.data && response.data.variants) {
					if ('title' === component) {
						setTitleVariants(response.data.variants);
					} else {
						setExcerptVariants(response.data.variants);
					}
				} else {
					notify((response && response.data && response.data.message) || l10n.labels.actionError, 'error');
				}
			})
			.fail(function () {
				notify(l10n.labels.actionError, 'error');
			})
			.always(function () {
				setBusyComponent(null);
			});
		}

		function applyToPost(component, value) {
			if ('title' === component) {
				wp.data.dispatch('core/editor').editPost({ title: value });
			} else if ('excerpt' === component) {
				wp.data.dispatch('core/editor').editPost({ excerpt: value });
			}
			notify(l10n.labels.success || 'Updated post ' + component + '!');
		}

		function renderVariantList(component, list) {
			if (!list || !list.length) {
				return null;
			}
			return el('div', { key: component + '-list', className: 'aips-variant-carousel' },
				list.map(function (variantText, idx) {
					return el('div', { key: idx, className: 'aips-variant-card' },
						el('div', { className: 'aips-variant-card-header' }, 'Variant ' + (idx + 1)),
						el('div', { className: 'aips-variant-card-body' }, variantText),
						el(Button, {
							variant: 'primary',
							isSmall: true,
							onClick: function () { applyToPost(component, variantText); }
						}, l10n.labels.insert || 'Apply')
					);
				})
			);
		}

		return el(PluginDocumentSettingPanel, {
			name: 'aips-editor-post-panel',
			title: l10n.panelTitle || 'AI Edit & Directives',
			icon: 'update',
			className: 'aips-editor-gutenberg-panel'
		},
			el('div', { className: 'aips-editor-component-actions' },
				el(Button, {
					variant: 'secondary',
					isBusy: 'title' === busyComponent,
					disabled: !!busyComponent,
					onClick: function () { generateVariants('title'); }
				}, l10n.labels.titleVariants || 'Generate 3 Title Candidates'),
				renderVariantList('title', titleVariants),

				el(Button, {
					variant: 'secondary',
					isBusy: 'excerpt' === busyComponent,
					disabled: !!busyComponent,
					style: { marginTop: '10px' },
					onClick: function () { generateVariants('excerpt'); }
				}, l10n.labels.excerptVariants || 'Generate 3 Excerpt Candidates'),
				renderVariantList('excerpt', excerptVariants)
			)
		);
	}

	if (PluginDocumentSettingPanel) {
		wp.plugins.registerPlugin('aips-editor-panel', {
			render: DocumentPanel
		});
	}

	/* ==========================================================================
	   2. Inline Block Toolbar Extension (Block-level Directives & Links)
	   ========================================================================== */
	var SUPPORTED_BLOCKS = ['core/paragraph', 'core/heading', 'core/list-item'];

	var withAIEditControls = wp.compose.createHigherOrderComponent(function (BlockEdit) {
		return function (props) {
			if (SUPPORTED_BLOCKS.indexOf(props.name) === -1) {
				return el(BlockEdit, props);
			}

			var [isOpen, setIsOpen] = useState(false);
			var [busy, setBusy] = useState(false);
			var [variantsCount, setVariantsCount] = useState(1);
			var [customDirective, setCustomDirective] = useState('');
			var [candidates, setCandidates] = useState([]);
			var [wovenHtml, setWovenHtml] = useState(null);
			var [insertedLinks, setInsertedLinks] = useState([]);

			var contentText = props.attributes.content || '';

			function applyDirective(presetKey, custom) {
				setBusy(true);
				setCandidates([]);
				setWovenHtml(null);

				$.post(l10n.ajaxurl, {
					action: 'aips_editor_apply_directive',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					content: contentText,
					directive_preset: presetKey || '',
					custom_directive: custom || '',
					variants_count: variantsCount
				})
				.done(function (response) {
					if (response && response.success && response.data && response.data.variants) {
						setCandidates(response.data.variants);
					} else {
						notify((response && response.data && response.data.message) || l10n.labels.actionError, 'error');
					}
				})
				.fail(function () {
					notify(l10n.labels.actionError, 'error');
				})
				.always(function () {
					setBusy(false);
				});
			}

			function weaveLinks() {
				setBusy(true);
				setCandidates([]);
				setWovenHtml(null);

				$.post(l10n.ajaxurl, {
					action: 'aips_editor_weave_internal_links',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					content: contentText
				})
				.done(function (response) {
					if (response && response.success && response.data && response.data.modified_html) {
						setWovenHtml(response.data.modified_html);
						setInsertedLinks(response.data.inserted_links || []);
					} else {
						notify((response && response.data && response.data.message) || l10n.labels.actionError, 'error');
					}
				})
				.fail(function () {
					notify(l10n.labels.actionError, 'error');
				})
				.always(function () {
					setBusy(false);
				});
			}

			function applyContentToBlock(newContent) {
				props.setAttributes({ content: newContent });
				setIsOpen(false);
				setCandidates([]);
				setWovenHtml(null);
				notify(l10n.labels.success || 'Block updated!');
			}

			var presets = l10n.presets || {};

			return el(Fragment, null,
				el(BlockEdit, props),
				props.isSelected && el(BlockControls, null,
					el(ToolbarButton, {
						icon: 'update',
						label: l10n.labels.popoverTitle || 'AI Edit & Directives',
						onClick: function () { setIsOpen(!isOpen); }
					})
				),
				isOpen && el(Popover, {
					position: 'bottom center',
					onClose: function () { setIsOpen(false); }
				},
					el('div', { className: 'aips-editor-popover' },
						el('div', { className: 'aips-editor-popover-header' },
							el('h4', { className: 'aips-editor-popover-title' },
								el('span', { className: 'dashicons dashicons-update' }),
								l10n.labels.popoverTitle || 'AI Edit'
							),
							el(Button, {
								icon: 'no-alt',
								isSmall: true,
								onClick: function () { setIsOpen(false); }
							})
						),

						/* Preset Pills */
						el('div', { className: 'aips-preset-pills' },
							Object.keys(presets).map(function (key) {
								return el('button', {
									key: key,
									type: 'button',
									className: 'aips-preset-pill',
									disabled: busy,
									onClick: function () { applyDirective(key, customDirective); }
								}, presets[key].label);
							}),
							el('button', {
								key: 'weave',
								type: 'button',
								className: 'aips-preset-pill',
								disabled: busy,
								onClick: weaveLinks
							},
								el('span', { className: 'dashicons dashicons-admin-links', style: { fontSize: '12px', width: '12px', height: '12px' } }),
								l10n.labels.weaveBtn || 'Weave Links'
							)
						),

						/* Directive Input Bar */
						el('div', { className: 'aips-editor-input-row' },
							el('input', {
								type: 'text',
								className: 'aips-editor-input',
								placeholder: l10n.labels.instruction || 'Custom instruction...',
								value: customDirective,
								disabled: busy,
								onChange: function (e) { setCustomDirective(e.target.value); },
								onKeyPress: function (e) {
									if (e.which === 13 && customDirective.trim()) {
										applyDirective('', customDirective);
									}
								}
							}),
							el(Button, {
								variant: 'primary',
								isSmall: true,
								disabled: busy || !customDirective.trim(),
								onClick: function () { applyDirective('', customDirective); }
							}, 'Apply')
						),

						/* Variants Count Toggle (1 vs 3) */
						el('div', { className: 'aips-editor-options-row' },
							el('span', null, l10n.labels.variantCount || 'Candidates:'),
							el('div', { className: 'aips-variants-toggle' },
								el('button', {
									type: 'button',
									className: 'aips-var-btn' + (variantsCount === 1 ? ' is-active' : ''),
									onClick: function () { setVariantsCount(1); }
								}, '1'),
								el('button', {
									type: 'button',
									className: 'aips-var-btn' + (variantsCount === 3 ? ' is-active' : ''),
									onClick: function () { setVariantsCount(3); }
								}, '3')
							)
						),

						/* Loading Spinner */
						busy && el('div', { style: { textAlign: 'center', padding: '10px' } },
							el(Spinner, null),
							el('p', { style: { fontSize: '11px', margin: '5px 0 0', color: 'var(--aips-gray-500)' } },
								l10n.labels.generating || 'Generating with AI…'
							)
						),

						/* Multi-Variant Candidate Cards */
						candidates.length > 0 && el('div', { className: 'aips-variant-carousel' },
							candidates.map(function (candidate, idx) {
								return el('div', { key: idx, className: 'aips-variant-card' },
									el('div', { className: 'aips-variant-card-header' }, 'Variant ' + (idx + 1)),
									el('div', { className: 'aips-variant-card-body' }, candidate),
									el(Button, {
										variant: 'primary',
										isSmall: true,
										onClick: function () { applyContentToBlock(candidate); }
									}, l10n.labels.apply || 'Apply to Block')
								);
							})
						),

						/* Woven Internal Links Preview with Confirmation */
						wovenHtml && el('div', { className: 'aips-link-confirm-container' },
							el('div', { style: { fontSize: '11px', fontWeight: 'bold', marginBottom: '4px' } },
								'Woven Internal Links Preview:'
							),
							el('div', {
								style: { fontSize: '12px', background: 'var(--aips-white)', padding: '6px', borderRadius: '4px', marginBottom: '8px' },
								dangerouslySetInnerHTML: { __html: wovenHtml }
							}),
							insertedLinks.length > 0 && el('div', { style: { marginBottom: '8px' } },
								insertedLinks.map(function (link, lIdx) {
									return el('div', { key: lIdx, className: 'aips-link-item' },
										el('span', null, 'Anchor: ', el('strong', null, link.anchor)),
										el('span', { style: { fontSize: '10px', color: 'var(--aips-gray-500)' } }, link.title)
									);
								})
							),
							el('div', { style: { display: 'flex', gap: '6px' } },
								el(Button, {
									variant: 'primary',
									isSmall: true,
									onClick: function () { applyContentToBlock(wovenHtml); }
								}, l10n.labels.apply || 'Insert into Block'),
								el(Button, {
									variant: 'secondary',
									isSmall: true,
									onClick: function () { setWovenHtml(null); setInsertedLinks([]); }
								}, l10n.labels.dismiss || 'Dismiss')
							)
						)
					)
				)
			);
		};
	}, 'withAIEditControls');

	addFilter(
		'editor.BlockEdit',
		'aips/ai-edit-controls',
		withAIEditControls
	);
})(window.wp, window.jQuery);
