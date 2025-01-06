<?php

namespace MediaWiki\Extension\Newsletter\Content;

use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use SlotDiffRenderer;
use TextSlotDiffRenderer;

class NewsletterSlotDiffRenderer extends SlotDiffRenderer {
	/** @var TextSlotDiffRenderer */
	private $textSlotDiffRenderer;

	/** @var \MessageLocalizer */
	private $localizer;

	public function __construct(
		TextSlotDiffRenderer $textSlotDiffRenderer,
		\MessageLocalizer $localizer
	) {
		$this->textSlotDiffRenderer = $textSlotDiffRenderer;
		$this->localizer = $localizer;
	}

	public function getTablePrefix( IContextSource $context, Title $newTitle ): array {
		return $this->textSlotDiffRenderer->getTablePrefix( $context, $newTitle );
	}

	/** @inheritDoc */
	public function getExtraCacheKeys() {
		return $this->textSlotDiffRenderer->getExtraCacheKeys();
	}

	public function addModules( OutputPage $output ) {
		$this->textSlotDiffRenderer->addModules( $output );
	}

	/** @inheritDoc */
	public function getDiff( ?Content $oldContent = null, ?Content $newContent = null ) {
		$this->normalizeContents( $oldContent, $newContent, [ NewsletterContent::class ] );
		/** @var NewsletterContent $oldContent */
		'@phan-var NewsletterContent $oldContent';
		/** @var NewsletterContent $newContent */
		'@phan-var NewsletterContent $newContent';

		$output = '';

		$descDiff = $this->textSlotDiffRenderer->getTextDiff(
			$oldContent->getDescription(), $newContent->getDescription()
		);
		if ( trim( $descDiff ) !== '' ) {
			$output .= Html::openElement( 'tr' );
			$output .= Html::openElement( 'td',
				[ 'colspan' => 4, 'id' => 'mw-newsletter-diffdescheader' ] );
			$output .= Html::element( 'h4', [],
				$this->localizer->msg( 'newsletter-diff-descheader' )->text() );
			$output .= Html::closeElement( 'td' );
			$output .= Html::closeElement( 'tr' );
			$output .= $descDiff;
		}

		$mainPageDiff = $this->textSlotDiffRenderer->getTextDiff(
			$oldContent->getMainPage()->getFullText(), $newContent->getMainPage()->getFullText()
		);

		if ( $mainPageDiff !== '' ) {
			$output .= Html::openElement( 'tr' );
			$output .= Html::openElement( 'td',
				[ 'colspan' => 4, 'id' => 'mw-newsletter-diffmainpageheader' ] );
			$output .= Html::element( 'h4', [],
				$this->localizer->msg( 'newsletter-diff-mainpageheader' )->text() );
			$output .= Html::closeElement( 'td' );
			$output .= Html::closeElement( 'tr' );
			$output .= $mainPageDiff;
		}

		$publishersDiff = $this->textSlotDiffRenderer->getTextDiff(
			implode( "\n", $oldContent->getPublishers() ),
			implode( "\n", $newContent->getPublishers() )
		);

		if ( $publishersDiff !== '' ) {
			$output .= Html::openElement( 'tr' );
			$output .= Html::openElement( 'td',
				[ 'colspan' => 4, 'id' => 'mw-newsletter-diffpublishersheader' ] );
			$output .= Html::element( 'h4', [],
				$this->localizer->msg( 'newsletter-diff-publishersheader' )->text() );
			$output .= Html::closeElement( 'td' );
			$output .= Html::closeElement( 'tr' );
			$output .= $publishersDiff;
		}

		return $output;
	}
}
