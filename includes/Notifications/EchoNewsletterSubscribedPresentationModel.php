<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

class EchoNewsletterSubscribedPresentationModel extends BaseNewsletterPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getNewsletterUrl(),
			'label' => $this->msg( 'newsletter-notification-subscribed' )
				->params( $this->getNewsletterName() )
		];
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		[ $agentFormattedName, $agentGenderName ] = $this->getAgentForOutput();
		$msg = $this->msg( 'newsletter-notification-subscribed' );
		$msg->params( $this->getNewsletterName() );
		return $msg;
	}

}
