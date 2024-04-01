<?php

namespace MediaWiki\Extension\Newsletter\Logging;

use LogFormatter;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Message;

/**
 * Log formatter for Extension:Newsletter log messages
 *
 * @license GPL-2.0-or-later
 * @author Tyler Romeo
 */
class NewsletterLogFormatter extends LogFormatter {

	/**
	 * Reformat the target as a user link if the target was a user
	 * @return array
	 */
	public function getMessageParameters() {
		if ( $this->parsedParameters !== null ) {
			return $this->parsedParameters;
		}

		$params = parent::getMessageParameters();
		if ( $this->entry->getTarget()->inNamespace( NS_USER ) ) {
			$user = User::newFromName( $this->entry->getTarget()->getText() );
			if ( $user ) {
				$params[2] = Message::rawParam( $this->makeUserLink( $user ) );
				$params[6] = $user->getName();
			}
		}

		if ( $this->entry->getSubtype() === 'issue-added' && isset( $params[5] ) ) {
			$params[5] = Message::rawParam( $this->makePageLink( Title::newFromText( $params[5] ) ) );
		}

		ksort( $params );
		$this->parsedParameters = $params;
		return $params;
	}

	/**
	 * Format an additional parameter type "newsletter-link", whose value is a
	 * newsletter ID and name separated by a colon, into a link
	 * @param string $type
	 * @param string $value
	 * @return string|array
	 */
	public function formatParameterValue( $type, $value ) {
		if ( $type !== 'newsletter-link' ) {
			return parent::formatParameterValue( $type, $value );
		}

		[ $id, $name ] = explode( ':', $value, 2 );
		$title = SpecialPage::getTitleFor( 'Newsletter', $id );
		if ( !$this->plaintext ) {
			return Message::rawParam( $this->getLinkRenderer()->makeLink(
				$title,
				$name,
				[]
			) );
		} else {
			return "[[{$title->getPrefixedText()}|$name]]";
		}
	}

}
