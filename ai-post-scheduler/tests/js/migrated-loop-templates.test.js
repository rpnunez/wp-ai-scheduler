/**
 * Regression tests for the three templates migrated from a hand-rolled JS
 * loop (building HTML by calling AIPS.Templates.render() once per item and
 * concatenating) to a native `<# _.each() #>` evaluate block, per
 * https://github.com/rpnunez/wp-ai-scheduler/issues/2273.
 *
 * Each template is read from its real PHP partial and stripped of PHP tags
 * (which only emit static, translated attribute text unrelated to the
 * templating logic under test), then exercised through the same
 * `AIPS.Templates.render()` entry point the admin JS call sites use.
 */
const fs = require('fs');
const path = require('path');
const { loadTemplates, defineTemplate } = require('./helpers/load-templates');

function extractTemplate(partialPath, id) {
	const source = fs.readFileSync(path.join(__dirname, '..', '..', partialPath), 'utf8');
	const pattern = new RegExp(`<script type="text/html" id="${id}">([\\s\\S]*?)<\\/script>`);
	const match = source.match(pattern);

	if (!match) {
		throw new Error(`Template #${id} not found in ${partialPath}`);
	}

	return match[1].replace(/<\?php[\s\S]*?\?>/g, '');
}

describe('migrated loop templates', () => {
	let AIPSTemplates;

	beforeEach(() => {
		document.body.innerHTML = '';
		delete window.wp;
		AIPSTemplates = loadTemplates();
	});

	it('aips-tmpl-planner-topic-item renders one escaped row per topic', () => {
		defineTemplate('aips-tmpl-planner-topic-item', extractTemplate('templates/admin/planner.php', 'aips-tmpl-planner-topic-item'));

		const html = AIPSTemplates.render('aips-tmpl-planner-topic-item', {
			topics: ['<script>alert(1)</script>', 'Topic B'],
		});

		expect((html.match(/class="topic-item"/g) || []).length).toBe(2);
		expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
		expect(html).toContain('value="Topic B"');
		expect(html).not.toContain('<script>alert(1)</script>');
	});

	it('aips-tmpl-ai-assist-history-item renders one row per history item', () => {
		defineTemplate(
			'aips-tmpl-ai-assist-history-item',
			extractTemplate('templates/partials/ai-assistance.php', 'aips-tmpl-ai-assist-history-item')
		);

		const html = AIPSTemplates.render('aips-tmpl-ai-assist-history-item', {
			items: [
				{ id: 1, response: 'Hello <b>', created_at: 'today', fieldId: 'f1', context: 'ctx' },
				{ id: 2, response: 'World', created_at: 'yesterday', fieldId: 'f1', context: 'ctx2' },
			],
		});

		expect((html.match(/aips-ai-assist-history-item/g) || []).length).toBe(2);
		expect(html).toContain('Hello &lt;b&gt;');
		expect(html).toContain('data-record-id="1"');
		expect(html).toContain('data-record-id="2"');
	});

	it('aips-tmpl-il-page-btn renders one button per pagination descriptor', () => {
		defineTemplate('aips-tmpl-il-page-btn', extractTemplate('templates/admin/internal-links.php', 'aips-tmpl-il-page-btn'));

		const html = AIPSTemplates.render('aips-tmpl-il-page-btn', {
			buttons: [
				{ page: 1, classes: 'aips-btn-secondary', label: '«' },
				{ page: 2, classes: 'aips-btn-primary', label: 2 },
				{ page: 3, classes: 'aips-btn-secondary', label: 3 },
			],
		});

		expect((html.match(/data-page="/g) || []).length).toBe(3);
		expect(html).toContain('data-page="2"');
		expect(html).toContain('aips-btn-primary');
	});
});
