const { loadTemplates, defineTemplate } = require('./helpers/load-templates');

describe('AIPS.Templates', () => {
	let AIPSTemplates;

	beforeEach(() => {
		document.body.innerHTML = '';
		delete window.wp;
		AIPSTemplates = loadTemplates();
	});

	describe('legacy {{key}} templates', () => {
		beforeEach(() => {
			defineTemplate('aips-tmpl-card', '<div><h4>{{name}}</h4><p>{{bio}}</p></div>');
		});

		it('render() HTML-escapes values', () => {
			const html = AIPSTemplates.render('aips-tmpl-card', { name: '<b>Alex</b>', bio: 'Loves "quotes"' });

			expect(html).toContain('&lt;b&gt;Alex&lt;/b&gt;');
			expect(html).toContain('&quot;quotes&quot;');
			expect(html).not.toContain('<b>Alex</b>');
		});

		it('renderRaw() does not escape values', () => {
			const html = AIPSTemplates.renderRaw('aips-tmpl-card', { name: '<b>Alex</b>', bio: 'raw' });

			expect(html).toContain('<b>Alex</b>');
		});

		it('render() treats a missing key as empty rather than throwing', () => {
			const html = AIPSTemplates.render('aips-tmpl-card', { name: 'Alex' });

			expect(html).toContain('<h4>Alex</h4>');
			expect(html).toContain('<p></p>');
		});
	});

	describe('ID resolution', () => {
		it('resolves the legacy full-element-ID convention', () => {
			defineTemplate('aips-tmpl-silo-card', '<div>{{title}}</div>');

			expect(AIPSTemplates.has('aips-tmpl-silo-card')).toBe(true);
			expect(AIPSTemplates.render('aips-tmpl-silo-card', { title: 'Hi' })).toBe('<div>Hi</div>');
		});

		it('resolves the native wp.template `tmpl-{id}` convention', () => {
			defineTemplate('tmpl-silo-card', '<div>{{ data.title }}</div>');

			expect(AIPSTemplates.has('silo-card')).toBe(true);
			expect(AIPSTemplates.render('silo-card', { title: 'Hi' })).toBe('<div>Hi</div>');
		});

		it('returns an empty string and false for a template that does not exist', () => {
			expect(AIPSTemplates.render('does-not-exist', {})).toBe('');
			expect(AIPSTemplates.renderRaw('does-not-exist', {})).toBe('');
			expect(AIPSTemplates.get('does-not-exist')).toBe('');
			expect(AIPSTemplates.has('does-not-exist')).toBe(false);
		});
	});

	describe('native wp.template syntax', () => {
		it('supports dotted data references, raw interpolation, and evaluate blocks', () => {
			defineTemplate(
				'aips-tmpl-native',
				'<div>{{ data.title }}<# if ( data.items.length ) { #>' +
					'<# _.each( data.items, function( item ) { #><span>{{ item }}</span><# } ); #>' +
					'<# } #>{{{ data.rawHtml }}}</div>'
			);

			const html = AIPSTemplates.render('aips-tmpl-native', {
				title: 'Hi',
				items: ['a', 'b'],
				rawHtml: '<em>raw</em>',
			});

			expect(html).toBe('<div>Hi<span>a</span><span>b</span><em>raw</em></div>');
		});

		it('uses wp.template.options when WordPress core has defined them', () => {
			window.wp = {
				template: {
					options: {
						evaluate: /<#([\s\S]+?)#>/g,
						interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
						escape: /\{\{([^\{][\s\S]+?)\}\}/g,
						variable: 'data',
					},
				},
			};
			AIPSTemplates = loadTemplates();
			defineTemplate('aips-tmpl-native', '<span>{{ data.title }}</span>');

			expect(AIPSTemplates.render('aips-tmpl-native', { title: 'From wp.template.options' })).toBe(
				'<span>From wp.template.options</span>'
			);
		});
	});

	describe('caching', () => {
		it('clearCache() forces a re-read of the template element', () => {
			defineTemplate('aips-tmpl-card', '<div>{{name}}</div>');
			expect(AIPSTemplates.render('aips-tmpl-card', { name: 'first' })).toBe('<div>first</div>');

			document.getElementById('aips-tmpl-card').innerHTML = '<div>{{name}} (updated)</div>';
			expect(AIPSTemplates.render('aips-tmpl-card', { name: 'first' })).toBe('<div>first</div>');

			AIPSTemplates.clearCache();
			expect(AIPSTemplates.render('aips-tmpl-card', { name: 'first' })).toBe('<div>first (updated)</div>');
		});
	});

	describe('escape()', () => {
		it('escapes the standard HTML-unsafe characters', () => {
			expect(AIPSTemplates.escape(`<script>&"'</script>`)).toBe(
				'&lt;script&gt;&amp;&quot;&#039;&lt;/script&gt;'
			);
		});
	});
});
