<?php

/**
 * Log formatter for Extension:Newsletter log messages
 *
 * @license GNU GPL v2+
 * @author Tyler Romeo
 */
class NewsletterLogFormatter extends LogFormatter {
	/**
	 * Reformat the target as a user link if the target was a user
	 */
	public function getMessageParameters() {
		if ( isset( $this->parsedParameters ) ) {
			return $this->parsedParameters;
		}

		$params = parent::getMessageParameters();
		if ( $this->entry->getTarget()->inNamespace( NS_USER ) ) {
			$user = User::newFromName( $this->entry->getTarget()->getText() );
			$params[2] = Message::rawParam( $this->makeUserLink( $user ) );
			$params[6] = $user->getName();
		}

		if ( $this->entry->getSubtype() === 'issue-added' && isset( $params[5] ) ) {
			$params[5] = Message::rawParam( $this->makePageLink( Title::newFromText( $params[5] ) ) );
		}

		ksort($params);
		$this->parsedParameters = $params;
		return $params;
	}

	/**
	 * Format an additional parameter type "newsletter-link", whose value is a
	 * newsletter ID and name separated by a colon, into a link
	 */
	public function formatParameterValue( $type, $value ) {
		if ( $type !== 'newsletter-link' ) {
			return parent::formatParameterValue( $type, $value );
		}

		list( $id, $name ) = explode( ':', $value, 2 );
		$title = SpecialPage::getTitleFor( 'Newsletter', $id );
		if ( !$this->plaintext ) {
			return Message::rawParam( Linker::link(
				$title,
				htmlspecialchars( $name ),
				[]
			) );
		} else {
			return "[[{$title->getPrefixedText()}|$name]]";
		}
	}
}
