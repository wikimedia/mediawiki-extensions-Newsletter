import NewsletterPage from '../pageobjects/newsletter.page.js';

describe( 'Newsletter', () => {
	it( 'page should exist on installation', async () => {
		await NewsletterPage.open();
		await expect( await NewsletterPage.title ).toHaveText( 'Newsletters' );
	} );
} );
