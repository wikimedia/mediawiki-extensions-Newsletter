const Page = require( 'wdio-mediawiki/Page' );

class NewsletterPage extends Page {
	get title() { return browser.element( '#firstHeading' ); }
	open() {
		super.openTitle( 'Special:Newsletters' );
	}
}
module.exports = new NewsletterPage();
