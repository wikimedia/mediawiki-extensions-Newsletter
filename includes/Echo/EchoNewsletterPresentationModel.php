<?php

/**
 * Class that returns structured data for the newsletter echo events.
 * @see https://www.mediawiki.org/wiki/Echo_%28Notifications%29/New_formatter_system
 */
class EchoNewsletterPresentationModel extends EchoEventPresentationModel {

	public function getIconType() {
		return 'site';
	}

	public function canRender() {
		return (bool)$this->event->getTitle();
	}

	public function getPrimaryLink() {
		return array(
			'url' => $this->event->getTitle()->getFullUrl(),
			'label' => $this->msg( 'newsletter-notification-link-text-new-issue' )
		);
	}

	public function getSecondaryLinks() {
		return array(
			array(
				'url' => SpecialPage::getTitleFor( 'Newsletter', $this->event->getExtraParam( 'newsletter-id' ) )->getFullUrl(),
				'label' => $this->msg( 'newsletter-notification-link-text-view-newsletter' )
			),
		);
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// Add the newsletter name
		return $msg->params( $this->event->getExtraParam( 'newsletter-name' ) );
	}

	public function getBodyMessage() {
		return $this->msg( 'notification-body-newsletter-announce' )
			->params( $this->event->getExtraParam( 'section-text' ) );
	}
}
