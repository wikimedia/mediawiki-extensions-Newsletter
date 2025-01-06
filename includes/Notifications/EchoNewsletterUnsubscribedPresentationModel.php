<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

class EchoNewsletterUnsubscribedPresentationModel extends BaseNewsletterPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getNewsletterUrl(),
			'label' => $this->msg( 'newsletter-notification-unsubscribed' )
				->params( $this->getNewsletterName() )
		];
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		[ $agentFormattedName, $agentGenderName ] = $this->getAgentForOutput();
		$msg = $this->msg( 'newsletter-notification-unsubscribed' );
		$msg->params( $this->getNewsletterName() );
		return $msg;
	}

}
