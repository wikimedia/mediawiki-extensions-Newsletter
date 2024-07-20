<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use RuntimeException;

abstract class BaseNewsletterPresentationModel extends EchoEventPresentationModel {
	private const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';

	public function canRender() {
		$nl = Newsletter::newFromID( $this->getNewsletterId() );
		return (bool)$nl;
	}

	protected function getNewsletterId() {
		return (int)$this->event->getExtraParam( 'newsletter-id' );
	}

	protected function getNewsletterName() {
		return $this->event->getExtraParam( 'newsletter-name' );
	}

	protected function getNewsletterUrl() {
		$result = Title::makeTitleSafe(
			NS_NEWSLETTER,
			$this->getNewsletterName()
		)->getFullURL();
		if ( !$result ) {
			throw new RuntimeException( 'Cannot find newsletter with name \"' .
				$this->getNewsletterName() .
				'\"'
			);
		}
		return $result;
	}

	protected function getNewsletterUnsubscribeUrl() {
		return SpecialPage::getTitleFor( 'Newsletter', $this->getNewsletterId() . '/' .
			self::NEWSLETTER_UNSUBSCRIBE )->getFullURL();
	}

}
