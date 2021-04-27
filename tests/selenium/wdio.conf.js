'use strict';

const fs = require( 'fs' ),
	saveScreenshot = require( 'wdio-mediawiki' ).saveScreenshot,
	logPath = process.env.LOG_DIR || `${__dirname}/log`;

exports.config = {
	// ======
	// Custom WDIO config specific to MediaWiki
	// ======
	// Use in a test as `browser.options.<key>`.
	// Defaults are for convenience with MediaWiki-Vagrant

	// Wiki admin
	username: process.env.MEDIAWIKI_USER || 'Admin',
	password: process.env.MEDIAWIKI_PASSWORD || 'vagrant',

	// Base for browser.url() and Page#openTitle()
	baseUrl: ( process.env.MW_SERVER || 'http://127.0.0.1:8080' ) + (
		process.env.MW_SCRIPT_PATH || '/w'
	),

	// ==================
	// Test Files
	// ==================
	specs: [
		`${__dirname}/specs/*.js`
	],

	// ============
	// Capabilities
	// ============

	// How many instances of the same capability (browser) may be started at the same time.
	maxInstances: 1,

	capabilities: [ {
		// For Chrome/Chromium https://sites.google.com/a/chromium.org/chromedriver/capabilities
		browserName: 'chrome',
		maxInstances: 1,
		'goog:chromeOptions': {
			// If DISPLAY is set, assume developer asked non-headless or CI with Xvfb.
			// Otherwise, use --headless (added in Chrome 59)
			// https://chromium.googlesource.com/chromium/src/+/59.0.3030.0/headless/README.md
			args: [
				...( process.env.DISPLAY ? [] : [ '--headless' ] ),
				// Chrome sandbox does not work in Docker
				...( fs.existsSync( '/.dockerenv' ) ? [ '--no-sandbox' ] : [] )
			]
		}
	} ],

	// ===================
	// Test Configurations
	// ===================

	// Enabling synchronous mode (via the wdio-sync package), means specs don't have to
	// use Promise#then() or await for browser commands, such as like `brower.element()`.
	// Instead, it will automatically pause JavaScript execution until th command finishes.
	//
	// For non-browser commands (such as MWBot and other promises), this means you
	// have to use `browser.call()` to make sure WDIO waits for it before the next
	// browser command.
	sync: true,

	// Level of logging verbosity: silent | verbose | command | data | result | error
	logLevel: 'error',

	// Enables colors for log output.
	coloredLogs: true,

	// Warns when a deprecated command is used
	deprecationWarnings: true,

	// Stop the tests once a certain number of failed tests have been recorded.
	// Default is 0 - don't bail, run all tests.
	bail: 0,

	// Setting this enables automatic screenshots for when a browser command fails
	// It is also used by afterTest for capturig failed assertions.
	screenshotPath: logPath,

	// Default timeout for each waitFor* command.
	waitforTimeout: 10 * 1000,

	// Framework you want to run your specs with.
	// See also: http://webdriver.io/guide/testrunner/frameworks.html
	framework: 'mocha',

	// Test reporter for stdout.
	// See:
	// https://webdriver.io/docs/spec-reporter
	// https://webdriver.io/docs/junit-reporter
	reporters: [
		'spec',
		[ 'junit', {
			outputDir: logPath,
			outputFileFormat: function () {
				const makeFilenameDate = new Date().toISOString().replace( /[:.]/g, '-' );
				return `WDIO.xunit-${makeFilenameDate}.xml`;
			}
		} ]
	],

	// Options to be passed to Mocha.
	// See the full list at http://mochajs.org/
	mochaOpts: {
		ui: 'bdd',
		timeout: 60 * 1000
	},

	// =====
	// Hooks
	// =====
	// See also: http://webdriver.io/guide/testrunner/configurationfile.html

	/**
	 * Save a screenshot when test fails.
	 *
	 * @param {Object} test Mocha Test object
	 * @return {void}
	 *
	 */
	afterTest: function ( test ) {
		if ( !test.passed ) {
			const filePath = saveScreenshot( test.title );
			console.log( `\n\tScreenshot: ${filePath}\n` );
		}
	}
};
