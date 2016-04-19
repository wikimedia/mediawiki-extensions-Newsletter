<?php

class EchoNewsletterPublisherPresentationModel extends BaseNewsletterPresentationModel {

	public function getIconType() {
		return 'site';
	}

	public function getPrimaryLink() {
		return array(
			'url' => $this->getSpecialNewsletterUrl(),
			'label' => $this->msg( 'newsletter-notification-link-text-new-publisher' )->text()
		);
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// Add the newsletter name
		return $msg->params( $this->event->getExtraParam( 'newsletter-name' ) );
	}
}
