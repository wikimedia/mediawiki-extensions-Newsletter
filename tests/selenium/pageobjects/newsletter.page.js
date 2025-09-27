import Page from 'wdio-mediawiki/Page';

class NewsletterPage extends Page {
	get title() {
		return $( '#firstHeading' );
	}

	async open() {
		return super.openTitle( 'Special:Newsletters' );
	}
}

export default new NewsletterPage();
