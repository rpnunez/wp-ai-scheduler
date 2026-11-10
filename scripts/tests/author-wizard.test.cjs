/* Run with Node and Playwright available on NODE_PATH; requires PHP. Uses Edge when present, otherwise Chromium. */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');

async function launchBrowser() {
	try {
		return await chromium.launch({ channel: 'msedge', headless: true });
	} catch (err) {
		return await chromium.launch({ headless: true });
	}
}

test('Author wizard preserves values, validates hidden steps, and supports direct editing', async () => {
	const browser = await launchBrowser();
	try {
		const page = await browser.newPage();
		await page.setContent(execFileSync('php', [path.join(__dirname, 'author-wizard-fixture.php')], { encoding: 'utf8' }));
		await page.addStyleTag({ path: path.join(root, 'ai-post-scheduler/assets/css/design-tokens.css') });
		await page.addStyleTag({ path: path.join(root, 'ai-post-scheduler/assets/css/admin.css') });
		await page.addStyleTag({ path: path.join(root, 'ai-post-scheduler/assets/css/authors.css') });
		await page.addScriptTag({ path: path.join(root, 'ai-post-scheduler/vendor/phpunit/php-code-coverage/src/Report/Html/Renderer/Template/js/jquery.min.js') });
		await page.addScriptTag({ path: path.join(root, 'ai-post-scheduler/assets/js/utilities.js') });
		await page.evaluate(() => {
			window.ajaxurl = '/ajax';
			window.aipsAuthorsL10n = { addNewAuthor: 'Add New Author', editAuthor: 'Edit Author', saveAuthor: 'Save Author', saveChanges: 'Save Changes', notSpecified: 'Not specified', enabled: 'Enabled', disabled: 'Disabled', requiredField: 'Please complete this required field.', sourceGroupsRequired: 'Select a source group.', nonce: 'test' };
			window.AIPS.Utilities.showToast = () => {};
			window.AIPS.refreshContentPanel = () => {};
			window.requests = [];
			jQuery.fx.off = true;
			jQuery.ajax = options => { window.requests.push(options.data); return jQuery.Deferred().resolve(); };
		});
		await page.addScriptTag({ path: path.join(root, 'ai-post-scheduler/assets/js/authors.js') });
		await page.locator('.aips-add-author-btn').click();
		assert.equal(await page.locator('.aips-author-step:visible').count(), 1);
		await page.locator('.aips-author-next').click();
		assert.equal(await page.locator('#author_name').evaluate(el => el === document.activeElement), true);
		await page.locator('#author_name').fill('<img src=x onerror=alert(1)>');
		await page.locator('#author_field_niche').fill('Engineering');
		await page.locator('#author_name').press('Enter');
		assert.equal(await page.locator('.aips-author-step:visible').getAttribute('data-step'), '2');
		await page.locator('#voice_tone').fill('Friendly');
		await page.locator('.aips-author-next').click();
		await page.locator('#author_include_sources').check();
		await page.locator('.aips-author-next').click();
		assert.equal(await page.locator('.aips-author-step:visible').getAttribute('data-step'), '3');
		await page.locator('.aips-author-source-group-cb').check();
		await page.locator('.aips-author-next').click();
		await page.locator('#author_max_posts_per_topic').fill('11');
		await page.locator('.aips-author-next').click();
		assert.equal(await page.locator('.aips-author-step:visible').getAttribute('data-step'), '4');
		await page.locator('#author_max_posts_per_topic').fill('2');
		await page.locator('.aips-author-next').click();
		assert.match(await page.locator('#aips-author-review').innerText(), /Friendly/);
		assert.match(await page.locator('#aips-author-review').innerText(), /Engineering/);
		assert.doesNotMatch(await page.locator('#aips-author-review').innerText(), /Generate with AI Prompt/);
		assert.equal(await page.locator('#aips-author-review img').count(), 0);
		await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished)));
		if (process.env.AIPS_WIZARD_SCREENSHOTS) await page.screenshot({ path: path.join(root, '.artifacts/author-wizard-review.png') });
		await page.locator('.aips-author-save').click();
		assert.match(await page.evaluate(() => requests[0]), /voice_tone=Friendly/);
		assert.match(await page.evaluate(() => requests[0]), /source_group_ids%5B%5D=7/);
		assert.doesNotMatch(await page.evaluate(() => requests[0]), /topic_auto_approval_min_score=/);
		// Editing uses the real AJAX hydration path, then saves from any step.
		await page.evaluate(() => {
			jQuery.ajax = options => {
				if (options.data.action === 'aips_get_author') options.success({ success: true, data: { author: { id: 9, name: 'Existing', field_niche: 'Science', voice_tone: 'Precise', language: 'en', topic_generation_quantity: 5, topic_generation_frequency: 'weekly', post_generation_frequency: 'daily', is_active: 1, source_group_ids: '[7]', include_sources: 1 } } });
				else window.requests.push(options.data);
				return jQuery.Deferred().resolve();
			};
		});
		await page.locator('.aips-modal-close').first().click();
		await page.locator('.aips-edit-author').click();
		await page.locator('.aips-author-step-link[data-step="4"]').click();
		assert.equal(await page.locator('.aips-author-save').isVisible(), true);
		await page.evaluate(() => { document.querySelector('#author_name').value = ''; });
		await page.locator('.aips-author-save').click();
		assert.equal(await page.locator('.aips-author-step:visible').getAttribute('data-step'), '1');
		assert.equal(await page.locator('.aips-author-save').isEnabled(), true, 'Validation must not leave Save locked');
		await page.locator('#author_name').fill('Updated');
		await page.locator('.aips-author-save').click();
		assert.match(await page.evaluate(() => requests.at(-1)), /author_id=9/);
		assert.match(await page.evaluate(() => requests.at(-1)), /voice_tone=Precise/);
		await page.evaluate(() => {
			jQuery.ajax = options => {
				window.requests.push(options.data);
				window.pendingRequest = jQuery.Deferred();
				window.failRequest = () => { options.error(); window.pendingRequest.reject(); };
				return window.pendingRequest;
			};
		});
		const beforePending = await page.evaluate(() => requests.length);
		await page.locator('.aips-author-save').click();
		await page.evaluate(() => jQuery('#aips-author-form').trigger('submit'));
		assert.equal(await page.evaluate(() => requests.length), beforePending + 1, 'Ignore duplicate submits during a request');
		await page.evaluate(() => window.failRequest());
		assert.equal(await page.locator('.aips-author-save').isEnabled(), true, 'A failed request must unlock Save');
		assert.equal(await page.locator('#author_name').inputValue(), 'Updated');
		await page.locator('.aips-modal-close').first().click();
		await page.locator('.aips-add-author-btn').click();
		assert.equal(await page.locator('#author_name').inputValue(), '');
		assert.equal(await page.locator('.aips-author-save').isVisible(), false);
		await page.setViewportSize({ width: 390, height: 844 });
		assert.equal(await page.locator('.aips-author-progress').evaluate(el => el.scrollWidth <= el.clientWidth), true);
		await page.locator('.aips-author-step-link[data-step="1"]').focus();
		await page.keyboard.press('Enter');
		assert.equal(await page.locator('#aips-author-heading-1').evaluate(el => el === document.activeElement), true);
		await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished)));
		if (process.env.AIPS_WIZARD_SCREENSHOTS) await page.screenshot({ path: path.join(root, '.artifacts/author-wizard-mobile.png') });
	} finally {
		await browser.close();
	}
});
