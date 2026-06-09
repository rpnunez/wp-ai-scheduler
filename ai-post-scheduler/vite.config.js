import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
	build: {
		outDir: resolve(__dirname, 'assets/dist'),
		emptyOutDir: true,
		minify: 'esbuild',
		rollupOptions: {
			input: {
				admin: resolve(__dirname, 'assets/src/js/main.js')
			},
			external: ['jquery', 'backbone', 'underscore'],
			output: {
				entryFileNames: 'js/aips-[name].min.js',
				chunkFileNames: 'js/aips-[name].min.js',
				globals: {
					jquery: 'jQuery',
					backbone: 'Backbone',
					underscore: '_'
				},
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && assetInfo.name.endsWith('.css')) {
						return 'css/aips-admin.min.[ext]';
					}
					return 'assets/[name].[ext]';
				}
			}
		}
	}
});
