/**
 * Generated Posts React App - Entry Point
 *
 * Main entry point for the React-based Generated Posts admin page.
 */

import { render } from '@wordpress/element';
import GeneratedPostsApp from './components/GeneratedPostsApp';
import './style.scss';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const rootElement = document.getElementById('aips-generated-posts-root');
	
	if (rootElement) {
		render(<GeneratedPostsApp />, rootElement);
	}
});
