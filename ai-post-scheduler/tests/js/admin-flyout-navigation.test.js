/**
 * Contract tests for the shared admin flyout module.
 *
 * Run: node --test ai-post-scheduler/tests/js/admin-flyout-navigation.test.js
 */

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const sourcePath = path.resolve(__dirname, '../../assets/js/admin-flyout-navigation.js');
const source = fs.readFileSync(sourcePath, 'utf8');

test('flyout module is valid JavaScript', function() {
	assert.doesNotThrow(function() {
		new vm.Script(source, { filename: sourcePath });
	});
});

test('flyout follows the established jQuery and AIPS module pattern', function() {
	assert.match(source, /\(function\(\$\)/);
	assert.match(source, /window\.AIPS = window\.AIPS \|\| \{\}/);
	assert.match(source, /AIPS\.AdminFlyoutNavigation = \{/);
	assert.match(source, /\}\)\(jQuery\);/);
});

test('flyout renders markup through AIPS Templates', function() {
	assert.match(source, /AIPS\.Templates\.render\('aips-tmpl-admin-flyout-item'/);
	assert.match(source, /AIPS\.Templates\.renderRaw\('aips-tmpl-admin-flyout-group'/);
	assert.match(source, /AIPS\.Templates\.renderRaw\('aips-tmpl-admin-flyout'/);
	assert.doesNotMatch(source, /'<(?:nav|section|a|span|div)\b/);
});

test('flyout includes hover intent, keyboard, responsive, and cleanup behavior', function() {
	assert.match(source, /config\.openDelay \|\| 120/);
	assert.match(source, /config\.closeDelay \|\| 200/);
	assert.match(source, /event\.key === 'Escape'/);
	assert.match(source, /event\.key === 'Home'/);
	assert.match(source, /event\.key === 'End'/);
	assert.match(source, /\(hover: none\) and \(pointer: coarse\)/);
	assert.match(source, /destroy: function\(\)/);
});
