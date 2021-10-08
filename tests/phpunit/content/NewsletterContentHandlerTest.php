<?php

/**
 * @covers NewsletterContentHandler
 */
class NewsletterContentHandlerTest extends MediaWikiTestCase {

	public function testGetParserOutput() {
		$expectedText = 'Foo';
		$newsletterTitle = "Newsletter:Test";
		$text = '{
			"description": "' . $expectedText . '",
			"mainpage": "UTPage",
			"publishers": [
				"UTSysop"
			]
		}';
		$title = Title::newFromText( $newsletterTitle );
		$content = new NewsletterContent( $text );

		$contentRenderer = $this->getServiceContainer()->getContentRenderer();

		$output = $contentRenderer->getParserOutput( $content, $title );
		$this->assertStringContainsString( $expectedText, $output->getText() );
	}
}
