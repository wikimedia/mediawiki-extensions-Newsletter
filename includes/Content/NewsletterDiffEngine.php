<?php

namespace MediaWiki\Extension\Newsletter\Content;

use Content;
use DifferenceEngine;
use Exception;
use Html;

class NewsletterDiffEngine extends DifferenceEngine {

	public function generateContentDiffBody( Content $old, Content $new ) {
		if ( !( $old instanceof NewsletterContent )
			|| !( $new instanceof NewsletterContent )
		) {
			throw new Exception( 'Cannot diff content types other than NewsletterContent' );
		}

		$output = '';

		$descDiff = $this->generateTextDiffBody(
			$old->getDescription(), $new->getDescription()
		);

		if ( $descDiff ) {
			if ( trim( $descDiff ) !== '' ) {
				$output .= Html::openElement( 'tr' );
				$output .= Html::openElement( 'td',
					[ 'colspan' => 4, 'id' => 'mw-newsletter-diffdescheader' ] );
				$output .= Html::element( 'h4', [],
					$this->msg( 'newsletter-diff-descheader' )->text() );
				$output .= Html::closeElement( 'td' );
				$output .= Html::closeElement( 'tr' );
				$output .= $descDiff;
			}
		}

		$mainPageDiff = $this->generateTextDiffBody(
			$old->getMainPage()->getFullText(), $new->getMainPage()->getFullText()
		);

		if ( $mainPageDiff ) {
			if ( trim( $mainPageDiff ) !== '' ) {
				$output .= Html::openElement( 'tr' );
				$output .= Html::openElement( 'td',
					[ 'colspan' => 4, 'id' => 'mw-newsletter-diffmainpageheader' ] );
				$output .= Html::element( 'h4', [],
					$this->msg( 'newsletter-diff-mainpageheader' )->text() );
				$output .= Html::closeElement( 'td' );
				$output .= Html::closeElement( 'tr' );
				$output .= $mainPageDiff;
			}
		}

		$publishersDiff = $this->generateTextDiffBody(
			implode( "\n", $old->getPublishers() ),
			implode( "\n", $new->getPublishers() )
		);

		if ( trim( $publishersDiff ) !== '' ) {
			$output .= Html::openElement( 'tr' );
			$output .= Html::openElement( 'td',
				[ 'colspan' => 4, 'id' => 'mw-newsletter-diffpublishersheader' ] );
			$output .= Html::element( 'h4', [],
				$this->msg( 'newsletter-diff-publishersheader' )->text() );
			$output .= Html::closeElement( 'td' );
			$output .= Html::closeElement( 'tr' );
			$output .= $publishersDiff;
		}

		return $output;
	}

}
