<?php

class EchoNewsletterPublisherPresentationModel extends EchoEventPresentationModel{

	public function getIconType() {
		return 'site';
	}

	public function canRender() {
		// @todo Make this true for existing newsletters only
		return (bool)SpecialPage::getTitleFor( 'Newsletter',
			$this->event->getExtraParam( 'newsletter-id' ) . '/' . SpecialNewsletter::NEWSLETTER_MANAGE );
	}

	public function getPrimaryLink() {
		$subUrl = $this->event->getExtraParam( 'newsletter-id' );
		return array(
			'url' => SpecialPage::getTitleFor( 'Newsletter', $subUrl )->getFullUrl(),
			'label' => $this->msg( 'newsletter-notification-link-text-new-publisher' )->text()
		);
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// Add the newsletter name
		return $msg->params( $this->event->getExtraParam( 'newsletter-name' ) );
	}
}