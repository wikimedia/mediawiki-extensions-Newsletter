'use strict';

const assert = require( 'assert' ),
	NewsletterPage = require( '../pageobjects/newsletter.page' );

describe( 'Newsletter', function () {
	it( 'page should exist on installation', async function () {
		await NewsletterPage.open();
		assert.equal( await NewsletterPage.title.getText(), 'Newsletters' );
	} );
} );
