<?php

namespace MediaWiki\Extension\Newsletter;

use MediaWiki\Notification\AgentAware;
use MediaWiki\Notification\Notification;
use MediaWiki\User\UserIdentity;

/**
 * A Notification about a newsletter action, with no associated page.
 */
class NewsletterAgentNotification extends Notification implements AgentAware {

	private UserIdentity $agent;

	public function __construct( string $type, UserIdentity $agent, array $extra ) {
		parent::__construct( $type, $extra );
		$this->agent = $agent;
	}

	public function getAgent(): UserIdentity {
		return $this->agent;
	}

}
