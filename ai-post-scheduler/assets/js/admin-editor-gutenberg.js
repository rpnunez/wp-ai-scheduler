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
	var useEffect = wp.element.useEffect;
	var Fragment = wp.element.Fragment;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Popover = wp.components.Popover;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var CheckboxControl = wp.components.CheckboxControl;
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
	   1. Document Settings Panels (Per-Component AI Edit Widgets)
	   ========================================================================== */
	function ComponentAIEditPanel(props) {
		var component = props.component;
		var label = props.label;
		var initialOverrides = (l10n.componentOverrides && l10n.componentOverrides[component]) || {};

		var [isRegenerating, setIsRegenerating] = useState(false);
		var [regeneratedDraft, setRegeneratedDraft] = useState(null);
		var [showDiff, setShowDiff] = useState(false);
		var [showRevisions, setShowRevisions] = useState(false);
		var [revisions, setRevisions] = useState([]);
		var [loadingRevisions, setLoadingRevisions] = useState(false);
		var [variants, setVariants] = useState([]);
		var [busyVariants, setBusyVariants] = useState(false);

		// Prompt Steering & Creativity state
		var [showPopover, setShowPopover] = useState(false);
		var [selectedPreset, setSelectedPreset] = useState('');
		var [customDirective, setCustomDirective] = useState('');
		var [showAdvanced, setShowAdvanced] = useState(false);
		var [modelOverride, setModelOverride] = useState(initialOverrides.model || '');
		var [temperatureOverride, setTemperatureOverride] = useState(initialOverrides.temperature !== undefined ? parseFloat(initialOverrides.temperature) : 0.7);
		var [syncDownstream, setSyncDownstream] = useState(true);
		var [isCustomized, setIsCustomized] = useState(!!initialOverrides.model || (initialOverrides.temperature !== undefined && parseFloat(initialOverrides.temperature) !== 0.7));

		var currentValue = wp.data.useSelect(function (select) {
			var editor = select('core/editor');
			if (!editor) {
				return '';
			}
			if ('title' === component) {
				return editor.getEditedPostAttribute('title') || '';
			} else if ('excerpt' === component) {
				return editor.getEditedPostAttribute('excerpt') || '';
			} else if ('content' === component) {
				return editor.getEditedPostAttribute('content') || '';
			} else if ('featured_image' === component) {
				return editor.getEditedPostAttribute('featured_media') || 0;
			}
			return '';
		}, [component]);

		function loadRevisions() {
			setLoadingRevisions(true);
			$.post(l10n.ajaxurl, {
				action: 'aips_get_component_revisions',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				component: component
			})
			.done(function (response) {
				if (response && response.success && response.data) {
					setRevisions(response.data.revisions || []);
				}
			})
			.always(function () {
				setLoadingRevisions(false);
			});
		}

		function handleRegenerate(isCascade) {
			setIsRegenerating(true);
			$.post(l10n.ajaxurl, {
				action: 'aips_regenerate_component',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				history_id: l10n.historyId || 0,
				component: component,
				current_value: currentValue,
				custom_directive: customDirective,
				directive_preset: selectedPreset,
				model_override: modelOverride,
				temperature_override: temperatureOverride,
				downstream_cascaded: isCascade ? 'true' : 'false'
			})
			.done(function (response) {
				if (response && response.success && response.data && response.data.new_value) {
					setRegeneratedDraft(response.data.new_value);
					setShowDiff(true);
					loadRevisions();
					notify((isCascade ? 'Cascaded alignment generated' : 'Draft generated') + ' for ' + label + '!');
				} else {
					notify((response && response.data && response.data.message) || l10n.labels.actionError, 'error');
				}
			})
			.fail(function () {
				notify(l10n.labels.actionError, 'error');
			})
			.always(function () {
				setIsRegenerating(false);
			});
		}

		function applyToGutenberg(val) {
			var dispatch = wp.data.dispatch('core/editor');
			if ('title' === component) {
				var textVal = typeof val === 'object' && val !== null ? (val.value || '') : String(val || '');
				dispatch.editPost({ title: textVal });
			} else if ('excerpt' === component) {
				var textVal = typeof val === 'object' && val !== null ? (val.value || '') : String(val || '');
				dispatch.editPost({ excerpt: textVal });
			} else if ('content' === component) {
				var textVal = typeof val === 'object' && val !== null ? (val.value || '') : String(val || '');
				if (wp.blocks && wp.blocks.parse) {
					dispatch.resetBlocks(wp.blocks.parse(textVal));
				} else {
					dispatch.editPost({ content: textVal });
				}
			} else if ('featured_image' === component) {
				var attachmentId = typeof val === 'object' && val !== null ? (val.attachment_id || val.id || 0) : parseInt(val, 10);
				if (attachmentId > 0) {
					dispatch.editPost({ featured_media: attachmentId });
				}
			}
		}

		function handleAcceptDiff() {
			if (!regeneratedDraft) {
				return;
			}
			applyToGutenberg(regeneratedDraft);

			var payloadComponents = {};
			if ('title' === component) {
				payloadComponents.title = typeof regeneratedDraft === 'object' ? regeneratedDraft.value : regeneratedDraft;
			} else if ('excerpt' === component) {
				payloadComponents.excerpt = typeof regeneratedDraft === 'object' ? regeneratedDraft.value : regeneratedDraft;
			} else if ('content' === component) {
				payloadComponents.content = typeof regeneratedDraft === 'object' ? regeneratedDraft.value : regeneratedDraft;
			} else if ('featured_image' === component) {
				payloadComponents.featured_image_id = typeof regeneratedDraft === 'object' ? regeneratedDraft.attachment_id : regeneratedDraft;
			}

			$.post(l10n.ajaxurl, {
				action: 'aips_save_post_components',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				components: payloadComponents
			});

			setShowDiff(false);
			setRegeneratedDraft(null);
			loadRevisions();
			notify('Updated ' + label + '!');

			// Trigger downstream cascade if enabled
			if (syncDownstream) {
				if ('title' === component || 'content' === component) {
					window.dispatchEvent(new CustomEvent('aips:regenerate_component', {
						detail: { component: 'excerpt', source: 'cascade' }
					}));
				}
			}
		}

		function handleDiscardDiff() {
			setShowDiff(false);
			setRegeneratedDraft(null);
		}

		function handleToggleRevisions() {
			var nextState = !showRevisions;
			setShowRevisions(nextState);
			if (nextState) {
				loadRevisions();
			}
		}

		function handleRestore(rev) {
			$.post(l10n.ajaxurl, {
				action: 'aips_restore_component_revision',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				component: component,
				revision_id: rev.id
			})
			.done(function (response) {
				if (response && response.success) {
					var restoredVal = response.data.value !== undefined ? response.data.value : rev.value;
					applyToGutenberg(restoredVal);
					loadRevisions();
					notify('Revision restored!');
				} else {
					notify((response && response.data && response.data.message) || l10n.labels.actionError, 'error');
				}
			})
			.fail(function () {
				notify(l10n.labels.actionError, 'error');
			});
		}

		function handleGenerateVariants() {
			setBusyVariants(true);
			$.post(l10n.ajaxurl, {
				action: 'aips_editor_generate_component_variants',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				component: component
			})
			.done(function (response) {
				if (response && response.success && response.data && response.data.variants) {
					setVariants(response.data.variants);
				} else {
					notify((response && response.data && response.data.message) || l10n.labels.actionError, 'error');
				}
			})
			.fail(function () {
				notify(l10n.labels.actionError, 'error');
			})
			.always(function () {
				setBusyVariants(false);
			});
		}

		function handleResetOverrides() {
			$.post(l10n.ajaxurl, {
				action: 'aips_reset_component_overrides',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				component: component
			}).done(function (res) {
				if (res && res.success && res.data) {
					setModelOverride('');
					var defTemp = parseFloat(res.data.default_temperature) || 0.7;
					setTemperatureOverride(defTemp);
					setIsCustomized(false);
					notify('Component overrides reset to template defaults.');
				}
			});
		}

		// Listen for cascading downstream triggers
		useEffect(function () {
			function onCascade(e) {
				if (e.detail && e.detail.component === component) {
					handleRegenerate(true);
				}
			}
			window.addEventListener('aips:regenerate_component', onCascade);
			return function () {
				window.removeEventListener('aips:regenerate_component', onCascade);
			};
		}, [component, currentValue, customDirective, selectedPreset, modelOverride, temperatureOverride]);

		var currentSnippet = typeof currentValue === 'string' ? currentValue : (currentValue ? String(currentValue) : 'Empty');
		var regenSnippet = '';
		if (regeneratedDraft) {
			if (typeof regeneratedDraft === 'string') {
				regenSnippet = regeneratedDraft;
			} else if (typeof regeneratedDraft === 'object') {
				regenSnippet = regeneratedDraft.url || regeneratedDraft.value || JSON.stringify(regeneratedDraft);
			}
		}

		var modelOptions = Object.keys(l10n.models || {}).map(function (k) {
			return { value: k, label: l10n.models[k] };
		});

		var presetsList = Object.keys(l10n.presets || {}).map(function (k) {
			return { key: k, label: l10n.presets[k].label, desc: l10n.presets[k].description };
		});

		return el('div', { className: 'aips-gutenberg-component-section', style: { padding: '4px 0' } },
			// Split Button Header
			el('div', { style: { display: 'flex', gap: '4px', marginBottom: '8px', alignItems: 'center' } },
				el(Button, {
					variant: 'primary',
					isBusy: isRegenerating,
					disabled: isRegenerating,
					onClick: function () { handleRegenerate(false); }
				}, isRegenerating ? (l10n.labels.generating || 'Generating...') : ('Re-generate ' + label)),
				el(Button, {
					variant: 'primary',
					icon: showPopover ? 'arrow-up-alt2' : 'arrow-down-alt2',
					title: 'Steering & Directives',
					onClick: function () { setShowPopover(!showPopover); }
				}),
				('title' === component || 'excerpt' === component) && el(Button, {
					variant: 'secondary',
					isBusy: busyVariants,
					disabled: busyVariants,
					onClick: handleGenerateVariants
				}, '3 Candidates')
			),

			// Prompt Steering Popover Box
			showPopover && el('div', { style: { border: '1px solid #dcdcde', borderRadius: '4px', background: '#fff', padding: '10px', marginBottom: '10px' } },
				el('div', { style: { fontSize: '11px', fontWeight: 'bold', color: '#50575e', marginBottom: '6px', textTransform: 'uppercase' } }, 'Quick Presets'),
				el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '4px', marginBottom: '8px' } },
					presetsList.map(function (p) {
						var isSelected = selectedPreset === p.key;
						return el(Button, {
							key: p.key,
							isSmall: true,
							variant: isSelected ? 'primary' : 'secondary',
							title: p.desc,
							onClick: function () {
								setSelectedPreset(isSelected ? '' : p.key);
							}
						}, p.label);
					})
				),
				el(TextControl, {
					placeholder: 'Custom directive (e.g. punchier, focus on SEO)...',
					value: customDirective,
					onChange: setCustomDirective
				}),
				el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '6px', marginTop: '6px' } },
					el(Button, {
						isSmall: true,
						variant: 'tertiary',
						onClick: function () {
							setSelectedPreset('');
							setCustomDirective('');
						}
					}, 'Clear'),
					el(Button, {
						isSmall: true,
						variant: 'primary',
						onClick: function () {
							setShowPopover(false);
							handleRegenerate(false);
						}
					}, 'Apply & Re-generate')
				)
			),

			// Advanced Directives & Model Accordion
			el('div', { style: { border: '1px solid #dcdcde', borderRadius: '4px', background: '#fdfdfd', marginBottom: '10px' } },
				el(Button, {
					variant: 'tertiary',
					style: { width: '100%', display: 'flex', justifyContent: 'space-between', padding: '6px 8px', fontSize: '12px', fontWeight: '600' },
					onClick: function () { setShowAdvanced(!showAdvanced); }
				},
					el('span', null,
						'Advanced Directives & Model',
						isCustomized && el('span', { style: { background: '#2271b1', color: '#fff', fontSize: '9px', padding: '1px 5px', borderRadius: '8px', marginLeft: '6px' } }, 'Customized')
					),
					el('span', { className: 'dashicons ' + (showAdvanced ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'), style: { fontSize: '14px', width: '14px', height: '14px' } })
				),
				showAdvanced && el('div', { style: { padding: '8px 10px', borderTop: '1px solid #f0f0f1', background: '#fff' } },
					el(SelectControl, {
						label: 'AI Model Override',
						value: modelOverride,
						options: modelOptions,
						onChange: function (val) {
							setModelOverride(val);
							setIsCustomized(true);
						}
					}),
					el(RangeControl, {
						label: 'Creativity / Temperature (' + temperatureOverride.toFixed(2) + ')',
						value: temperatureOverride,
						onChange: function (val) {
							setTemperatureOverride(val);
							setIsCustomized(true);
						},
						min: 0,
						max: 1,
						step: 0.05
					}),
					el('div', { style: { display: 'flex', justifyContent: 'flex-end', marginTop: '4px' } },
						el(Button, {
							variant: 'link',
							isDestructive: true,
							style: { fontSize: '11px' },
							onClick: handleResetOverrides
						}, 'Reset to Template Default')
					)
				)
			),

			// Side-by-side diff
			showDiff && el('div', { className: 'aips-diff-wrapper', style: { border: '1px solid #dcdcde', borderRadius: '4px', background: '#f6f7f7', padding: '8px', marginBottom: '12px' } },
				el('div', { style: { fontWeight: '600', fontSize: '11px', textTransform: 'uppercase', marginBottom: '6px' } }, 'Side-by-Side Comparison'),
				el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px', marginBottom: '8px' } },
					el('div', { style: { background: '#fff', border: '1px solid #dcdcde', borderRadius: '4px', padding: '6px', fontSize: '12px', maxHeight: '140px', overflowY: 'auto' } },
						el('div', { style: { fontSize: '10px', fontWeight: 'bold', color: '#646970', marginBottom: '4px' } }, 'CURRENT VERSION'),
						currentSnippet
					),
					el('div', { style: { background: '#edfaef', border: '1px solid #68de7c', borderRadius: '4px', padding: '6px', fontSize: '12px', maxHeight: '140px', overflowY: 'auto' } },
						el('div', { style: { fontSize: '10px', fontWeight: 'bold', color: '#007017', marginBottom: '4px' } }, 'NEWLY REGENERATED DRAFT'),
						regenSnippet
					)
				),
				el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '6px', alignItems: 'center' } },
					el(Button, { variant: 'primary', isSmall: true, onClick: handleAcceptDiff }, 'Accept & Replace'),
					el(Button, { variant: 'secondary', isSmall: true, onClick: handleDiscardDiff }, 'Discard'),
					('title' === component || 'content' === component) && el(CheckboxControl, {
						label: 'Sync Downstream (Auto-realign Excerpt)',
						checked: syncDownstream,
						onChange: setSyncDownstream,
						style: { marginLeft: 'auto', marginBottom: 0 }
					})
				)
			),

			// Candidates carousel
			variants.length > 0 && el('div', { className: 'aips-variant-carousel', style: { marginBottom: '12px' } },
				variants.map(function (variantText, idx) {
					return el('div', { key: idx, className: 'aips-variant-card', style: { background: '#f6f7f7', border: '1px solid #dcdcde', borderRadius: '4px', padding: '6px', marginBottom: '6px' } },
						el('div', { style: { fontSize: '11px', color: '#646970', fontWeight: '600', marginBottom: '4px' } }, 'Candidate ' + (idx + 1)),
						el('div', { style: { fontSize: '12px', marginBottom: '6px' } }, variantText),
						el(Button, {
							variant: 'primary',
							isSmall: true,
							onClick: function () {
								applyToGutenberg(variantText);
								notify('Applied candidate to ' + label + '!');
							}
						}, 'Apply')
					);
				})
			),

			// Revisions drawer toggle
			el('div', { style: { borderTop: '1px solid #f0f0f1', paddingTop: '8px' } },
				el(Button, {
					variant: 'secondary',
					isSmall: true,
					onClick: handleToggleRevisions
				}, showRevisions ? 'Hide Revisions' : 'View Revisions' + (revisions.length ? ' (' + revisions.length + ')' : '')),
				showRevisions && el('div', { style: { marginTop: '8px' } },
					loadingRevisions && el(Spinner, null),
					!loadingRevisions && revisions.length === 0 && el('p', { style: { color: '#646970', fontSize: '12px' } }, 'No revisions recorded.'),
					!loadingRevisions && revisions.length > 0 && el('div', null,
						revisions.map(function (rev, rIdx) {
							var revSnippet = typeof rev.value === 'string' ? rev.value.substring(0, 70) : JSON.stringify(rev.value);
							var revTime = rev.timestamp ? (new Date(rev.timestamp * 1000)).toLocaleTimeString() : '';
							return el('div', { key: rIdx, style: { background: '#fff', border: '1px solid #dcdcde', borderRadius: '4px', padding: '6px', marginBottom: '6px' } },
								el('div', { style: { display: 'flex', justifyContent: 'space-between', fontSize: '10px', color: '#646970', marginBottom: '4px' } },
									el('span', null, (rev.source === 'manual_edit' ? 'Manual' : 'AI') + ' ' + revTime),
									el(Button, { variant: 'link', isSmall: true, onClick: function () { handleRestore(rev); } }, 'Restore')
								),
								el('div', { style: { fontSize: '12px' } }, revSnippet)
							);
						})
					)
				)
			)
		);
	}

	// Register individual component panels in the Block Editor
	if (PluginDocumentSettingPanel) {
		wp.plugins.registerPlugin('aips-editor-title-panel', {
			render: function () {
				return el(PluginDocumentSettingPanel, {
					name: 'aips-editor-title-panel',
					title: 'AI Edit: Post Title',
					icon: 'heading'
				}, el(ComponentAIEditPanel, { component: 'title', label: 'Title' }));
			}
		});

		wp.plugins.registerPlugin('aips-editor-excerpt-panel', {
			render: function () {
				return el(PluginDocumentSettingPanel, {
					name: 'aips-editor-excerpt-panel',
					title: 'AI Edit: Post Excerpt',
					icon: 'text'
				}, el(ComponentAIEditPanel, { component: 'excerpt', label: 'Excerpt' }));
			}
		});

		wp.plugins.registerPlugin('aips-editor-content-panel', {
			render: function () {
				return el(PluginDocumentSettingPanel, {
					name: 'aips-editor-content-panel',
					title: 'AI Edit: Post Content',
					icon: 'editor-alignleft'
				}, el(ComponentAIEditPanel, { component: 'content', label: 'Content' }));
			}
		});

		wp.plugins.registerPlugin('aips-editor-image-panel', {
			render: function () {
				return el(PluginDocumentSettingPanel, {
					name: 'aips-editor-image-panel',
					title: 'AI Edit: Featured Image',
					icon: 'format-image'
				}, el(ComponentAIEditPanel, { component: 'featured_image', label: 'Featured Image' }));
			}
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
