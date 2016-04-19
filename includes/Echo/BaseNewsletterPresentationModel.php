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

	protected function getSpecialNewsletterUrl(){
		return SpecialPage::getTitleFor( 'Newsletter', $this->getNewsletterId() )->getFullUrl();
	}
}
