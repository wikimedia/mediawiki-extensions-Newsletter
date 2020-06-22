'use strict';

const assert = require( 'assert' ),
	NewsletterPage = require( '../pageobjects/newsletter.page' );

describe( 'Newsletter', function () {
	it( 'page should exist on installation', function () {
		NewsletterPage.open();
		assert.equal( NewsletterPage.title.getText(), 'Newsletters' );
	} );
} );
