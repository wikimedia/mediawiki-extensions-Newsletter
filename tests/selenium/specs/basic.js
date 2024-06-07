'use strict';

const assert = require( 'assert' ),
	NewsletterPage = require( '../pageobjects/newsletter.page' );

describe( 'Newsletter', () => {
	it( 'page should exist on installation', async () => {
		await NewsletterPage.open();
		assert.equal( await NewsletterPage.title.getText(), 'Newsletters' );
	} );
} );
