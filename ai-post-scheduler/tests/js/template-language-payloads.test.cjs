const { test, describe } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

describe('Template language payload propagation in admin.js', () => {
    const adminJsPath = path.resolve(__dirname, '../../assets/js/admin.js');
    const adminJsCode = fs.readFileSync(adminJsPath, 'utf8');

    function createEnvironment(initialLanguage = 'es') {
        const ajaxCalls = [];
        const state = {
            '#template_id': '42',
            '#template_name': 'Test Template Name',
            '#template_description': 'Description',
            '#prompt_template': 'Write an article about {{topic}}',
            '#title_prompt': 'Write a title',
            '#voice_id': '1',
            '#article_structure_id': '2',
            '#post_quantity': '1',
            '#image_prompt': 'A featured image',
            '#featured_image_source': 'ai_prompt',
            '#featured_image_unsplash_keywords': '',
            '#featured_image_media_ids': '',
            '#template_post_type': 'post',
            '#post_status': 'draft',
            '#post_category': '0',
            '#post_tags': 'tag1, tag2',
            '#post_author': '1',
            '#template_language': initialLanguage,
        };

        const checkboxes = {
            '#generate_featured_image': false,
            '#include_sources': false,
            '#affiliate_links_enabled': false,
            '#is_active': true,
        };

        const formElements = {
            checkValidity: () => true,
            reportValidity: () => true,
        };

        function jQueryStub(selector) {
            if (typeof selector === 'function') {
                // $(document).ready(...)
                return;
            }

            if (selector === '#aips-template-form') {
                return [formElements];
            }

            const isChecked = checkboxes[selector] ?? false;

            const stub = {
                val: function(newVal) {
                    if (newVal !== undefined) {
                        state[selector] = newVal;
                        return stub;
                    }
                    return state[selector] !== undefined ? state[selector] : '';
                },
                is: function(pseudo) {
                    if (pseudo === ':checked') {
                        return isChecked;
                    }
                    return false;
                },
                prop: function() { return stub; },
                attr: function() { return ''; },
                show: function() { return stub; },
                hide: function() { return stub; },
                text: function() { return stub; },
                html: function() { return stub; },
                focus: function() { return stub; },
                find: function() { return stub; },
                addClass: function() { return stub; },
                removeClass: function() { return stub; },
                toggleClass: function() { return stub; },
                hasClass: function() { return true; },
                data: function() { return undefined; },
                each: function() { return stub; },
                on: function() { return stub; },
                off: function() { return stub; },
                ready: function() { return stub; },
            };

            return stub;
        }

        jQueryStub.ajax = function(options) {
            ajaxCalls.push(options);
        };

        jQueryStub.isArray = Array.isArray;
        jQueryStub.extend = Object.assign;

        const sandbox = {
            window: {},
            document: {
                addEventListener: () => {},
                querySelector: () => null,
            },
            console: console,
            $: jQueryStub,
            jQuery: jQueryStub,
            aipsAjax: {
                ajaxUrl: '/wp-admin/admin-ajax.php',
                nonce: 'test_nonce_123',
            },
            aipsAdminL10n: {
                saving: 'Saving...',
                generating: 'Generating...',
                errorTryAgain: 'Error, please try again.',
                generationFailed: 'Generation failed.',
            },
            aipsTemplatesL10n: {
                draftSaved: 'Draft saved.',
                failedToGeneratePreview: 'Failed to generate preview.',
                exampleTopic: 'Example Topic',
            },
            wp: {
                i18n: {
                    __: (s) => s,
                    _x: (s) => s,
                    _n: (s, p, n) => (n === 1 ? s : p),
                    sprintf: (format, ...args) => format,
                },
            },
        };

        sandbox.window = sandbox;
        vm.createContext(sandbox);
        vm.runInContext(adminJsCode, sandbox);

        // Stub out utilities and modal navigation helpers
        sandbox.AIPS.getFirstInvalidStep = () => null;
        sandbox.AIPS.Utilities = {
            setButtonLoading: () => {},
            resetButton: () => {},
            showToast: () => {},
        };

        return { sandbox, ajaxCalls, state };
    }

    test('saveTemplate sends configured language in AJAX payload', () => {
        const { sandbox, ajaxCalls } = createEnvironment('es');
        const AIPS = sandbox.AIPS;
        assert.ok(AIPS && typeof AIPS.saveTemplate === 'function', 'AIPS.saveTemplate exists');

        AIPS.saveTemplate.call({
            attr: () => '',
            prop: () => {},
            data: () => {},
            text: () => {},
        }, { preventDefault: () => {} });

        assert.equal(ajaxCalls.length, 1);
        assert.equal(ajaxCalls[0].data.action, 'aips_save_template');
        assert.equal(ajaxCalls[0].data.language, 'es');
    });

    test('saveDraftTemplate sends configured language in AJAX payload', () => {
        const { sandbox, ajaxCalls } = createEnvironment('fr');
        const AIPS = sandbox.AIPS;
        assert.ok(AIPS && typeof AIPS.saveDraftTemplate === 'function', 'AIPS.saveDraftTemplate exists');

        AIPS.saveDraftTemplate.call({
            attr: () => '',
            prop: () => {},
            data: () => {},
            html: () => {},
            text: () => {},
        }, { preventDefault: () => {} });

        assert.equal(ajaxCalls.length, 1);
        assert.equal(ajaxCalls[0].data.action, 'aips_save_template');
        assert.equal(ajaxCalls[0].data.language, 'fr');
    });

    test('testTemplate sends configured language in AJAX payload', () => {
        const { sandbox, ajaxCalls } = createEnvironment('de');
        const AIPS = sandbox.AIPS;
        assert.ok(AIPS && typeof AIPS.testTemplate === 'function', 'AIPS.testTemplate exists');

        AIPS.testTemplate.call({
            attr: () => '',
            prop: () => {},
            data: () => {},
            html: () => {},
            text: () => {},
        }, { preventDefault: () => {} });

        assert.equal(ajaxCalls.length, 1);
        assert.equal(ajaxCalls[0].data.action, 'aips_test_template');
        assert.equal(ajaxCalls[0].data.language, 'de');
    });

    test('previewPrompts sends configured language in AJAX payload', () => {
        const { sandbox, ajaxCalls } = createEnvironment('ja');
        const AIPS = sandbox.AIPS;
        assert.ok(AIPS && typeof AIPS.previewPrompts === 'function', 'AIPS.previewPrompts exists');

        AIPS.previewPrompts.call({
            attr: () => '',
            prop: () => {},
            data: () => {},
            text: () => {},
        }, { preventDefault: () => {} });

        assert.equal(ajaxCalls.length, 1);
        assert.equal(ajaxCalls[0].data.action, 'aips_preview_template_prompts');
        assert.equal(ajaxCalls[0].data.language, 'ja');
    });
});
