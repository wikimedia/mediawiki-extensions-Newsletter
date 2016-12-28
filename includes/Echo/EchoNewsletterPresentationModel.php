<?php

/**
 * Class that returns structured data for the newsletter echo events.
 * @see https://www.mediawiki.org/wiki/Echo_%28Notifications%29/New_formatter_system
 */
class EchoNewsletterPresentationModel extends BaseNewsletterPresentationModel {

	public function getIconType() {
		return 'site';
	}

	public function canRender() {
		return (bool)$this->event->getTitle() && parent::canRender();
	}

	public function getPrimaryLink() {
		return array(
			'url' => $this->event->getTitle()->getFullURL(),
			'label' => $this->msg( 'newsletter-notification-link-text-new-issue' )
		);
	}

	public function getSecondaryLinks() {
		return array(
			array(
				'url' => $this->getNewsletterUrl(),
				'label' => $this->msg( 'newsletter-notification-link-text-view-newsletter' )
			),
		);
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// Add the newsletter name
		return $msg->params( $this->getNewsletterName() );
	}

	public function getBodyMessage() {
		return $this->msg( 'notification-body-newsletter-announce' )
			->params( $this->event->getExtraParam( 'section-text' ) );
	}
}
