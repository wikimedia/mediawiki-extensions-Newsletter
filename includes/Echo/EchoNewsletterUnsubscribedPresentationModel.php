<?php

class EchoNewsletterUnsubscribedPresentationModel extends BaseNewsletterPresentationModel {

	public function getIconType() {
		return 'site';
	}

	public function getPrimaryLink() {
		return array(
			'url' => $this->getSpecialNewsletterUrl(),
			'label' => $this->msg( 'newsletter-notification-unsubscribed' )
				->params( $this->getNewsletterName() )
		);
	}

	public function getHeaderMessage() {
		list( $agentFormattedName, $agentGenderName ) = $this->getAgentForOutput();
		$msg = $this->msg( 'newsletter-notification-unsubscribed' );
		$msg->params( $this->getNewsletterName() );
		return $msg;
	}
}
