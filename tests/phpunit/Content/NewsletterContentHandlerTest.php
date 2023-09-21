<?php

use MediaWiki\Extension\Newsletter\Content\NewsletterContent;

/**
 * @covers \MediaWiki\Extension\Newsletter\Content\NewsletterContentHandler
 * @group Database
 */
class NewsletterContentHandlerTest extends MediaWikiIntegrationTestCase {

	public function testGetParserOutput() {
		$expectedText = 'Foo';
		$newsletterTitle = "Newsletter:Test";
		$mainpage = $this->getExistingTestPage()->getTitle()->getPrefixedText();
		$publisher = $this->getTestSysop()->getUser()->getName();
		$text = '{
			"description": "' . $expectedText . '",
			"mainpage": "' . $mainpage . '",
			"publishers": [
				"' . $publisher . '"
			]
		}';
		$title = Title::newFromText( $newsletterTitle );
		$content = new NewsletterContent( $text );

		$contentRenderer = $this->getServiceContainer()->getContentRenderer();

		$parserOutput = $contentRenderer->getParserOutput( $content, $title );
		$this->assertStringContainsString( $expectedText, $parserOutput->getText() );
	}
}
