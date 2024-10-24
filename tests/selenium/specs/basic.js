'use strict';

const NewsletterPage = require( '../pageobjects/newsletter.page' );

describe( 'Newsletter', () => {
	it( 'page should exist on installation', async () => {
		await NewsletterPage.open();
		await expect( await NewsletterPage.title ).toHaveText( 'Newsletters' );
	} );
} );
