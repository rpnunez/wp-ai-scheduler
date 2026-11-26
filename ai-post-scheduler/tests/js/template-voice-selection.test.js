const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../../assets/js/admin.js'), 'utf8');

test('missing template voice is inserted and selected immediately', function () {
	const method = source.match(/setTemplateVoice:\s*function\(voiceId, voiceName\)\s*\{([\s\S]*?)\n\s*\},\n\n\s*\/\*\*/);
	assert.ok(method, 'setTemplateVoice accepts the voice label returned with the template');
	assert.match(method[1], /append\(/, 'missing option is appended before the form can be saved');
	assert.match(method[1], /\.val\(value\)/, 'the appended option is selected immediately');
	assert.match(source, /AIPS\.setTemplateVoice\(t\.voice_id,\s*t\.voice_name\)/);
});
