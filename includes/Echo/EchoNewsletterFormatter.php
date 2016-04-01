<?php

/**
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class EchoNewsletterFormatter extends EchoBasicFormatter {

	/**
	 * @param EchoEvent $event
	 * @param string $param
	 * @param Message $message
	 * @param User $user
	 */
	protected function processParam( EchoEvent $event, $param, Message $message, User $user ) {
		if ( $param === 'newsletter' ) {
			$this->processParamEscaped( $message, $event->getExtraParam( 'newsletter-name' ) );
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
	protected function getLinkParams( EchoEvent $event, User $user, $destination ) {
		$target = null;
		$query = array();
		switch ( $destination ) {
			case 'new-issue':
				// Placeholder for T119090 - currently the same as 'title'
				$target = $event->getTitle();
				break;
			case 'newsletter':
				$target = SpecialPage::getTitleFor( 'Newsletter', $event->getExtraParam( 'newsletter-id' ) );
				break;
			default:
				throw new Exception( __METHOD__ . ': $destination must be "new-issue" or "newsletter" (got: ' . $destination . ')' );
		}

		return array( $target, $query );
	}

}
