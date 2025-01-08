'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class NewsletterPage extends Page {
	get title() {
		return $( '#firstHeading' );
	}

	async open() {
		return super.openTitle( 'Special:Newsletters' );
	}
}
module.exports = new NewsletterPage();
