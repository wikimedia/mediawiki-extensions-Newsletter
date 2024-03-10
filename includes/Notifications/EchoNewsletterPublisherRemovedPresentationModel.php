<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

class EchoNewsletterPublisherRemovedPresentationModel extends BaseNewsletterPresentationModel {

	public function getIconType() {
		return 'site';
	}

	public function getPrimaryLink() {
		return [
			'url' => $this->getNewsletterUrl(),
			'label' => $this->msg( 'newsletter-notification-link-text-del-publisher' )->text()
		];
	}

	public function getHeaderMessage() {
		[ $agentFormattedName, $agentGenderName ] = $this->getAgentForOutput();
		$msg = $this->msg( 'notification-header-newsletter-delpublisher' );
		$msg->params( $this->getNewsletterName() );
		$msg->params( $this->getViewingUserForGender() );
		$msg->params( $agentGenderName );
		return $msg;
	}
}
