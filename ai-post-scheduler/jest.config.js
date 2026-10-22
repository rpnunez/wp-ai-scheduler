/** @type {import('jest').Config} */
module.exports = {
	testEnvironment: 'jsdom',
	rootDir: __dirname,
	testMatch: ['<rootDir>/tests/js/**/*.test.js'],
	setupFiles: ['<rootDir>/tests/js/setup.js'],
	verbose: true,
};
