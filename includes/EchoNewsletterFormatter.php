<?php

class EchoNewsletterFormatter extends EchoBasicFormatter {

	/**
	 * @param EchoEvent $event
	 * @param string $param
	 * @param Message $message
	 * @param User $user
	 */
	protected function processParam( $event, $param, $message, $user ) {
		if ( $param === 'newsletter' ) {
			$message->params( $event->getExtraParam( 'newsletter' ) );
		} elseif ( $param === 'title' ) {
			$message->params( $event->getExtraParam( 'issuePageTitle' ) );
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}

	/**
	 * Set target URL for primary link of notification
	 *
	 * @param EchoEvent $event
	 * @param User $user The user receiving the notification
	 * @param string $destination The destination type for the link
	 *
	 * @return array including target URL
	 */
	protected function getLinkParams( $event, $user, $destination ) {
		if ( $destination === 'new-issue' ) {
			return array(
				Title::makeTitle(
					$event->getExtraParam( 'issuePageNamespace' ),
					$event->getExtraParam( 'issuePageTitle' )
				),
				array(),
			);
		} else {
			return parent::getLinkParams( $event, $user, $destination );
		}
	}

}
