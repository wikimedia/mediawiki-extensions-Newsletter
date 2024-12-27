<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

/**
 * Class that returns structured data for the newsletter echo events.
 * @see https://www.mediawiki.org/wiki/Echo_%28Notifications%29/New_formatter_system
 */
class EchoNewsletterAnnouncePresentationModel extends BaseNewsletterPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function canRender() {
		return (bool)$this->event->getTitle() && parent::canRender();
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->event->getTitle()->getFullURL(),
			'label' => $this->msg( 'newsletter-notification-link-text-new-issue' )
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		return [
			[
				'url' => $this->getNewsletterUrl(),
				'label' => $this->msg( 'newsletter-notification-link-text-view-newsletter' ),
				'prioritized' => true,
			],
			[
				'url' => $this->getNewsletterUnsubscribeUrl(),
				'label' => $this->msg( 'newsletter-notification-link-text-unsubscribe-newsletter' ),
				'prioritized' => false,
			],
		];
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// Add the newsletter name
		return $msg->params( $this->getNewsletterName() );
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		return $this->msg( 'notification-body-newsletter-announce' )
			->params( $this->event->getExtraParam( 'section-text' ) );
	}

}
