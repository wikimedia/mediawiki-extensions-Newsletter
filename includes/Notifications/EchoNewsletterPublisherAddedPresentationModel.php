<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

class EchoNewsletterPublisherAddedPresentationModel extends BaseNewsletterPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getNewsletterUrl(),
			'label' => $this->msg( 'newsletter-notification-link-text-new-publisher' )->text()
		];
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		[ $agentFormattedName, $agentGenderName ] = $this->getAgentForOutput();
		$msg = $this->msg( 'notification-header-newsletter-newpublisher' );
		$msg->params( $this->getNewsletterName() );
		$msg->params( $this->getViewingUserForGender() );
		$msg->params( $agentGenderName );
		return $msg;
	}

}
