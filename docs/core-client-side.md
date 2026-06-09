# Core Client-Side Guide & Documentation

This guide describes the client-side architecture of the `wp-ai-scheduler` plugin. It outlines how to prepare the dev environment, compile assets using Vite/ESBuild, write components using Backbone.js and Underscore.js, and maintain styling consistency using BEM CSS with design tokens.

---

## 1. Preparing the Development Environment

The client-side build system is powered by Node.js and Vite/ESBuild. All source assets are located under `assets/src/`, and compiled distribution assets are outputted to `assets/dist/`.

### Prerequisites
- **Node.js** (v18+ recommended)
- **npm** (comes with Node.js)

### Installation
From the plugin subdirectory `ai-post-scheduler/` (the application root), install the Node dependencies:

```bash
cd ai-post-scheduler
npm install
```

This installs Vite, PostCSS, Autoprefixer, and related compiler dependencies.

*Note: Runtime libraries like **jQuery**, **Backbone.js**, and **Underscore.js** are provided globally by the WordPress Core and are externalized in the build configuration (`vite.config.js`) to keep bundle sizes minimal.*

---

## 2. Compilation and Build Workflows

Vite compiles and minifies both JavaScript and CSS into single assets:
- JavaScript: `assets/dist/js/aips-admin.min.js`
- CSS: `assets/dist/css/aips-admin.min.css`

### Development Mode (Hot Rebuild / Watcher)
To start the Vite compiler in watch mode for local development:

```bash
cd ai-post-scheduler
npm run dev
```

Vite will watch files in `assets/src/` and automatically recompile distribution files in real-time as you save changes.

### Production Build
To generate the final optimized, minified production assets:

```bash
cd ai-post-scheduler
npm run build
```

This runs `vite build`, minifying scripts using ESBuild and processing/autoprefixing styles using PostCSS.

---

## 3. IDE Integration for Rapid Development

For the fastest feedback loops, configure your code editor to run the watcher and build processes automatically.

### VS Code Integration

We configure tasks in `.vscode/tasks.json` so you can launch the client-side watcher from the VS Code Command Palette.

1. Create or open `.vscode/tasks.json` in the workspace root.
2. Add a task to run the dev script:
   ```json
   {
     "version": "2.0.0",
     "tasks": [
       {
         "label": "Watch Client Assets",
         "type": "shell",
         "command": "npm run dev",
         "options": {
           "cwd": "${workspaceFolder}/ai-post-scheduler"
         },
         "runOptions": {
           "runOn": "folderOpen"
         },
         "presentation": {
           "reveal": "always",
           "panel": "shared"
         },
         "problemMatcher": []
       }
     ]
   }
   ```
3. To run it manually: Press `Ctrl+Shift+P` (or `Cmd+Shift+P` on macOS), select **Tasks: Run Task**, and choose **Watch Client Assets**.

### PHPStorm Integration

To automate compilation in PHPStorm, use the built-in **npm tool window** or configure a **File Watcher**.

#### Option A: Running the watcher script
1. Right-click `ai-post-scheduler/package.json` and select **Show npm Scripts**.
2. Double-click the **dev** script to launch the watcher process.
3. The process runs in the background and logs outputs in the terminal tool window.

#### Option B: Automatic build on save (File Watcher)
1. Go to **Settings/Preferences | Tools | File Watchers**.
2. Click **+** and select **custom**.
3. Configure the watcher:
   - **File type:** JavaScript or Stylesheet
   - **Program:** `npm` (or `npm.cmd` on Windows)
   - **Arguments:** `run build`
   - **Working Directory:** `$ProjectFileDir$/ai-post-scheduler`
   - **Output paths to refresh:** `$ProjectFileDir$/ai-post-scheduler/assets/dist/`

---

## 4. Debugging Client-Side Code

Because assets are compiled and minified, use browser DevTools (Chrome DevTools / Firefox Developer Tools) to debug scripts.

- **Source Maps:** Vite is configured to compile source maps. In your browser DevTools under the **Sources** tab, navigate to the `webpack://` or `vite://` space (or locate the `assets/src/js/` directory) to view the unminified ES6 source files.
- **Breakpoints:** Set breakpoints directly in the unminified source files in DevTools.
- **Logging:** You can trace custom events by watching `window.AIPS.mediator` or calling `console.log()` in development scripts.

---

## 5. Architectural Guide & Conventions

### JavaScript: Backbone MVC Pattern
The client-side JavaScript utilizes a Model-View architecture. Do not write vanilla jQuery event handlers that query random DOM nodes.

#### Backbone.Events (Mediator)
Use the global event bus `AIPS.mediator` to trigger events across views:
```javascript
import mediator from '../utils/mediator';

// Trigger an event when a template changes
mediator.trigger('template:updated', templateModel);

// Listen to an event in another view
this.listenTo(mediator, 'template:updated', this.onTemplateUpdated);
```

#### Backbone Models & Collections
Models extend `AIPS.Models.Base` to automatically inherit nonce headers and WordPress AJAX endpoints:
```javascript
import BaseModel from './base';

export const TemplateModel = BaseModel.extend({
	defaults: {
		id: null,
		name: '',
		content_prompt: ''
	},
	// Path to the AJAX controller action
	syncAction: 'aips_save_template'
});
```

#### Backbone Views
Views bind UI actions and render updates declaratively:
```javascript
export const TemplatesView = Backbone.View.extend({
	el: '#aips-templates-container',
	events: {
		'click .aips-btn--primary': 'openWizard',
		'click .aips-delete-btn': 'deleteItem'
	},
	initialize() {
		this.listenTo(this.collection, 'sync reset', this.render);
	},
	render() {
		// Render logic using templates
	}
});
```

#### Underscore HTML Templates
Always use `AIPS.Templates.render(id, data)` for rendering dynamic HTML. Templates remain defined inside PHP template partials (e.g. `templates/admin/authors.php`) using `<script type="text/html">` to maintain native PHP translation wrapper (`__('text', 'ai-post-scheduler')`) compatibility:
```html
<script type="text/html" id="tmpl-aips-author-row">
	<tr class="aips-table-row">
		<td>{{ name }}</td>
		<td>{{ field_niche }}</td>
		<td>
			<button class="aips-btn aips-btn--secondary aips-delete-btn" data-id="{{ id }}">
				<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
			</button>
		</td>
	</tr>
</script>
```

### CSS: BEM Layouts with Design Tokens
To avoid style discrepancies across pages, style rules are defined as design tokens and BEM layout components.

#### Design Tokens (`assets/src/css/_variables.css`)
Contains the central source of truth for colors, margins, radii, and fonts:
```css
:root {
	--aips-color-primary: #6366f1;
	--aips-color-danger: #ef4444;
	--aips-border-radius: 6px;
	--aips-spacing-sm: 8px;
	--aips-spacing-md: 16px;
}
```

#### BEM Shared Components (`assets/src/css/_components.css`)
Common layout blocks (buttons, cards, inputs, modals, toasts) are styled here:
```css
/* Block */
.aips-btn {
	padding: 8px 16px;
	border-radius: var(--aips-border-radius);
	border: 1px solid transparent;
	transition: background-color 0.2s ease;
}

/* Modifiers */
.aips-btn--primary {
	background-color: var(--aips-color-primary);
	color: #fff;
}
.aips-btn--secondary {
	background-color: var(--wp-admin-theme-color, #2271b1);
	color: #fff;
}
```

---

## 6. Migration Reference

### Asset Enqueueing in PHP
- **Before:** Each page loaded its own JS/CSS assets dynamically via `wp_enqueue_script` and `wp_enqueue_style` checks.
- **Now:** A single minified JS and CSS file are enqueued globally for all plugin sub-pages.
- **Before:** Localization was targeted at specific handles (e.g., `wp_localize_script('aips-admin-post-slices', ...)`).
- **Now:** All translation stores register on the unified `'aips-admin-script'` handle.

#### Example Diff (`class-aips-admin-assets.php`):
```diff
-wp_enqueue_style(
-    'aips-post-slices-style',
-    AIPS_PLUGIN_URL . 'assets/css/post-slices.css',
-    array('aips-admin-style'),
-    AIPS_VERSION
-);
-wp_enqueue_script(
-    'aips-admin-post-slices',
-    AIPS_PLUGIN_URL . 'assets/js/admin-post-slices.js',
-    array('jquery', 'aips-admin-script'),
-    AIPS_VERSION,
-    true
-);
-wp_localize_script('aips-admin-post-slices', 'aipsPostSlicesL10n', $data);
+wp_localize_script('aips-admin-script', 'aipsPostSlicesL10n', $data);
```

### Template Compilation
- **Before:** Custom regex parser in `assets/js/templates.js` (`AIPS.Templates`).
- **Now:** Native Underscore template compilation (`_.template`).
- **Before:** Regex interpolating variables manually.
- **Now:** Underscore settings overridden to parse double-curly brackets `{{ var }}` to protect standard WP translations.
