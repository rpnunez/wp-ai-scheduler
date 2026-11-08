/**
 * Regression tests for Pending Review selection vs bulk actions.
 *
 * Cards → expanded List → Cards restores every selected ID, including rows the
 * smaller Cards page does not render. The count and publish/delete must both
 * use the checkboxes in the active layout.
 *
 * Run: node --test ai-post-scheduler/tests/js/pending-review-selection.test.js
 */

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const roots = [];

function createNode(tag, options) {
	const spec = options || {};
	const node = {
		__node: true,
		tag: tag,
		id: spec.id || '',
		classes: new Set(spec.classes || []),
		attrs: Object.assign({}, spec.attrs || {}),
		value: spec.value != null ? String(spec.value) : '',
		checked: !!spec.checked,
		indeterminate: false,
		dataStore: Object.assign({}, spec.data || {}),
		textContent: spec.text || '',
		children: [],
		parent: null,
	};
	return node;
}

function append(parent, child) {
	child.parent = parent;
	parent.children.push(child);
	return child;
}

function mount(node) {
	roots.push(node);
	return node;
}

function walk(node, visit) {
	visit(node);
	node.children.forEach(function (child) {
		walk(child, visit);
	});
}

function tokenParts(token) {
	const parts = [];
	const re = /\[[^\]]+\]|[#.]?[A-Za-z0-9_-]+/g;
	let match;
	while ((match = re.exec(token))) {
		parts.push(match[0]);
	}
	return parts;
}

function matchesSimple(node, part) {
	if (part.charAt(0) === '#') {
		return node.id === part.slice(1);
	}
	if (part.charAt(0) === '.') {
		return node.classes.has(part.slice(1));
	}
	let attr = part.match(/^\[([\w-]+)\^="([^"]*)"\]$/);
	if (attr) {
		const value = attr[1] === 'id' ? node.id : (node.attrs[attr[1]] || '');
		return String(value).indexOf(attr[2]) === 0;
	}
	attr = part.match(/^\[([\w-]+)="([^"]*)"\]$/);
	if (attr) {
		const value = attr[1] === 'id' ? node.id : (node.attrs[attr[1]] || '');
		return String(value) === attr[2];
	}
	return node.tag === part;
}

function matchesToken(node, token) {
	const parts = tokenParts(token);
	if (!parts.length) {
		return false;
	}
	return parts.every(function (part) {
		return matchesSimple(node, part);
	});
}

function matchesComplex(node, tokens) {
	if (!matchesToken(node, tokens[tokens.length - 1])) {
		return false;
	}
	let ancestor = node.parent;
	for (let i = tokens.length - 2; i >= 0; i--) {
		while (ancestor && !matchesToken(ancestor, tokens[i])) {
			ancestor = ancestor.parent;
		}
		if (!ancestor) {
			return false;
		}
		ancestor = ancestor.parent;
	}
	return true;
}

function queryAll(selector) {
	const found = [];
	const groups = selector.split(',').map(function (part) {
		return part.trim();
	}).filter(Boolean);
	groups.forEach(function (group) {
		const tokens = group.split(/\s+/);
		roots.forEach(function (root) {
			walk(root, function (node) {
				if (matchesComplex(node, tokens)) {
					found.push(node);
				}
			});
		});
	});
	return found;
}

function wrap(nodes) {
	const api = {
		length: nodes.length,
		each: function (fn) {
			nodes.forEach(function (node, index) {
				fn.call(node, index, node);
			});
			return api;
		},
		filter: function (arg) {
			if (arg === ':checked') {
				return wrap(nodes.filter(function (node) {
					return !!node.checked;
				}));
			}
			if (typeof arg === 'function') {
				return wrap(nodes.filter(function (node, index) {
					return !!arg.call(node, index, node);
				}));
			}
			return wrap([]);
		},
		val: function (value) {
			if (arguments.length === 0) {
				return nodes[0] ? nodes[0].value : undefined;
			}
			nodes.forEach(function (node) {
				node.value = String(value);
			});
			return api;
		},
		prop: function (name, value) {
			if (arguments.length < 2) {
				return nodes[0] ? nodes[0][name] : undefined;
			}
			nodes.forEach(function (node) {
				node[name] = value;
			});
			return api;
		},
		data: function (key) {
			if (!nodes[0]) {
				return undefined;
			}
			const store = nodes[0].dataStore;
			if (Object.prototype.hasOwnProperty.call(store, key)) {
				return store[key];
			}
			const camel = String(key).replace(/-([a-z])/g, function (_, letter) {
				return letter.toUpperCase();
			});
			return store[camel];
		},
		text: function (value) {
			if (arguments.length === 0) {
				return nodes[0] ? nodes[0].textContent : '';
			}
			nodes.forEach(function (node) {
				node.textContent = String(value);
			});
			return api;
		},
		toggle: function () {
			return api;
		},
		toggleClass: function (className, on) {
			nodes.forEach(function (node) {
				if (on) {
					node.classes.add(className);
				} else {
					node.classes.delete(className);
				}
			});
			return api;
		},
		addClass: function (className) {
			nodes.forEach(function (node) {
				node.classes.add(className);
			});
			return api;
		},
		removeClass: function (className) {
			nodes.forEach(function (node) {
				node.classes.delete(className);
			});
			return api;
		},
		attr: function (name, value) {
			if (arguments.length < 2) {
				return nodes[0] ? nodes[0].attrs[name] : undefined;
			}
			nodes.forEach(function (node) {
				node.attrs[name] = String(value);
			});
			return api;
		},
		hide: function () {
			return api;
		},
		show: function () {
			return api;
		},
		closest: function (selector) {
			const matches = [];
			nodes.forEach(function (node) {
				let current = node;
				while (current) {
					if (matchesToken(current, selector)) {
						matches.push(current);
						return;
					}
					current = current.parent;
				}
			});
			return wrap(matches);
		},
	};
	return api;
}

function $(arg) {
	if (arg && arg.__node) {
		return wrap([arg]);
	}
	if (typeof arg === 'string') {
		return wrap(queryAll(arg));
	}
	if (arg === global.document || arg === global.window) {
		return {
			ready: function () {
				return this;
			},
			on: function () {
				return this;
			},
			scrollTop: function () {
				return 0;
			},
			height: function () {
				return 800;
			},
		};
	}
	return wrap([]);
}

function installBrowser() {
	global.window = global;
	global.document = {};
	global.jQuery = $;
	global.localStorage = memoryStore();
	global.sessionStorage = memoryStore();
	const locationState = { _href: 'https://example.test/wp-admin/admin.php?page=aips-generated-posts&tab=aips-pending-review' };
	Object.defineProperty(locationState, 'href', {
		configurable: true,
		get: function () {
			return locationState._href;
		},
		set: function (value) {
			locationState._href = String(value);
		},
	});
	global.location = locationState;
	global.aipsPostReviewL10n = {
		confirmBulkPublish: 'Publish %d posts?',
		noPostsSelected: 'No posts selected',
		noActionSelected: 'No action selected',
	};
	global.AIPS = global.AIPS || {};
	global.AIPS.Utilities = {
		confirm: function (message) {
			global.AIPS.Utilities.lastConfirm = message;
		},
		showToast: function () {},
	};
}

function memoryStore() {
	const data = {};
	return {
		getItem: function (key) {
			return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
		},
		setItem: function (key, value) {
			data[key] = String(value);
		},
		removeItem: function (key) {
			delete data[key];
		},
	};
}

function checkbox(id) {
	return createNode('input', {
		classes: ['aips-post-checkbox'],
		value: String(id),
		data: { 'post-id': String(id), 'history-id': String(id) },
	});
}

function buildPage(options) {
	roots.length = 0;
	const spec = options || {};
	const cardIds = spec.cardIds || [];
	const listIds = spec.listIds || cardIds;
	const grid = mount(createNode('div', {
		id: 'aips-pending-posts-grid',
		classes: ['aips-pending-posts-grid'],
		data: {
			perPage: spec.perPage || 12,
			perPageMax: spec.perPageMax || 100,
			currentPage: 1,
			totalPages: 3,
		},
	}));
	cardIds.forEach(function (id) {
		const card = append(grid, createNode('div', { classes: ['aips-post-card'] }));
		append(card, checkbox(id));
	});

	const form = mount(createNode('form', { id: 'aips-post-review-form' }));
	const tbody = append(form, createNode('tbody'));
	listIds.forEach(function (id) {
		const row = append(tbody, createNode('tr'));
		append(row, checkbox(id));
	});

	mount(createNode('span', { id: 'aips-pending-total-count', text: '40' }));
	mount(createNode('span', { id: 'aips-top-selected-count', data: { label: 'selected' } }));
	mount(createNode('strong', { id: 'aips-floating-selected-count', text: '0' }));
	mount(createNode('strong', { id: 'aips-floating-total-count', text: '0' }));
	mount(createNode('div', { id: 'aips-floating-bulk-bar' }));
	mount(createNode('input', { id: 'aips-select-all-cards' }));
	mount(createNode('input', { id: 'aips-floating-select-all' }));
	mount(createNode('select', { id: 'bulk-action-selector-top', value: 'publish' }));
	mount(createNode('button', {
		classes: ['aips-view-toggle-btn'],
		attrs: { 'data-view': 'cards' },
	}));
	mount(createNode('button', {
		classes: ['aips-view-toggle-btn'],
		attrs: { 'data-view': 'list' },
	}));
	['aips-pending-posts-wrap', 'aips-pending-list-wrap', 'aips-pending-cards-count', 'aips-pending-list-pagination', 'aips-select-all-cards-wrap'].forEach(function (id) {
		mount(createNode('div', { id: id }));
	});
}

function selectedValues() {
	const values = [];
	AIPS.PostReview.selectedActionCheckboxes().each(function () {
		values.push(String($(this).val()));
	});
	return values;
}

installBrowser();
vm.runInThisContext(
	fs.readFileSync(path.join(__dirname, '../../assets/js/admin-post-review.js'), 'utf8'),
	{ filename: 'admin-post-review.js' }
);

function resetReview() {
	AIPS.PostReview.selectedPostIds = new Set();
	AIPS.PostReview.currentView = 'cards';
	AIPS.PostReview.listNeedsReload = false;
	AIPS.PostReview.currentPage = 1;
	AIPS.PostReview.totalPages = 1;
	AIPS.Utilities.lastConfirm = '';
	global.location.href = 'https://example.test/wp-admin/admin.php?page=aips-generated-posts&tab=aips-pending-review';
}

test('actionable ids are the intersection of the selection and the active layout', function () {
	assert.deepEqual(
		AIPS.PostReview.actionableIds(['1', '2', '3', '4'], ['1', '2']),
		['1', '2']
	);
	assert.deepEqual(
		AIPS.PostReview.actionableIds([3, 4], ['4', '5']),
		['4']
	);
});

test('Cards to expanded List to Cards drops ids the cards page no longer renders', function () {
	resetReview();
	// Destination of the round trip: review_per_page was removed, so only the
	// first page is rendered. The expanded-list selection is restored.
	buildPage({ cardIds: ['1', '2'], listIds: ['1', '2'], perPage: 2 });
	sessionStorage.setItem('aips_pending_review_selection', JSON.stringify(['1', '2', '3', '4']));
	localStorage.setItem('aips_pending_review_view', 'cards');

	AIPS.PostReview.init();

	assert.deepEqual(Array.from(AIPS.PostReview.selectedPostIds).sort(), ['1', '2']);
	assert.equal($('#aips-top-selected-count').text(), '2 selected');
	assert.equal($('#aips-floating-selected-count').text(), '2');
	assert.deepEqual(selectedValues(), ['1', '2']);

	AIPS.PostReview.onBulkAction({ preventDefault: function () {} });
	assert.equal(AIPS.Utilities.lastConfirm, 'Publish 2 posts?');
});

test('hidden list rows are not counted or published while Cards is active', function () {
	resetReview();
	buildPage({ cardIds: ['1', '2'], listIds: ['1', '2', '3', '4'], perPage: 2 });
	AIPS.PostReview.currentView = 'cards';
	AIPS.PostReview.selectedPostIds = new Set(['1', '2', '3', '4']);

	AIPS.PostReview.syncSelection();

	assert.deepEqual(Array.from(AIPS.PostReview.selectedPostIds).sort(), ['1', '2']);
	assert.equal($('#aips-top-selected-count').text(), '2 selected');
	assert.deepEqual(selectedValues(), ['1', '2']);

	const listValues = [];
	$('#aips-post-review-form tbody .aips-post-checkbox').each(function () {
		if ($(this).prop('checked')) {
			listValues.push(String($(this).val()));
		}
	});
	assert.deepEqual(listValues.sort(), ['1', '2']);

	AIPS.PostReview.onBulkAction({ preventDefault: function () {} });
	assert.equal(AIPS.Utilities.lastConfirm, 'Publish 2 posts?');
});

test('expanded List keeps every rendered selected row in the count and the bulk action', function () {
	resetReview();
	buildPage({ cardIds: ['1', '2'], listIds: ['1', '2', '3', '4'], perPage: 2 });
	AIPS.PostReview.currentView = 'list';
	AIPS.PostReview.selectedPostIds = new Set(['1', '2', '3', '4']);

	AIPS.PostReview.syncSelection();

	assert.deepEqual(Array.from(AIPS.PostReview.selectedPostIds).sort(), ['1', '2', '3', '4']);
	assert.equal($('#aips-top-selected-count').text(), '4 selected');
	assert.deepEqual(selectedValues(), ['1', '2', '3', '4']);

	AIPS.PostReview.onBulkAction({ preventDefault: function () {} });
	assert.equal(AIPS.Utilities.lastConfirm, 'Publish 4 posts?');
});

test('Cards to List clamps review_per_page to the shared maximum', function () {
	resetReview();
	const ids = [];
	for (let i = 1; i <= 150; i++) {
		ids.push(String(i));
	}
	buildPage({ cardIds: ids, listIds: [], perPage: 12, perPageMax: 100 });

	AIPS.PostReview.setView('list');

	const url = new URL(global.location.href);
	assert.equal(url.searchParams.get('review_per_page'), '100');
});

test('Cards to List keeps a loaded count under the maximum', function () {
	resetReview();
	const ids = [];
	for (let i = 1; i <= 40; i++) {
		ids.push(String(i));
	}
	buildPage({ cardIds: ids, listIds: [], perPage: 12, perPageMax: 100 });

	AIPS.PostReview.setView('list');

	const url = new URL(global.location.href);
	assert.equal(url.searchParams.get('review_per_page'), '40');
});
