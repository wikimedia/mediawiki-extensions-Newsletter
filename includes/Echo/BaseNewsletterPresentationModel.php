<?php

abstract class BaseNewsletterPresentationModel extends EchoEventPresentationModel{
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
		return Title::makeTitleSafe( NS_NEWSLETTER, $this->getNewsletterName() )->getFullURL();
	}
}
