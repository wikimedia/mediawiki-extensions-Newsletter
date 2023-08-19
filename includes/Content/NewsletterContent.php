<?php

namespace MediaWiki\Extension\Newsletter\Content;

use JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use User;

/**
 * @license GPL-2.0-or-later
 * @author tonythomas
 */
class NewsletterContent extends JsonContent {

	/**
	 * @var string|null
	 */
	private $description;

	/**
	 * @var Title
	 */
	private $mainPage;

	/**
	 * @var array|null
	 */
	protected $publishers;

	/**
	 * Whether $description and $targets have been populated
	 * @var bool
	 */
	private $decoded = false;

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, 'NewsletterContent' );
	}

	/**
	 * Validate username and make sure it exists
	 *
	 * @param string $userName
	 * @return bool
	 */
	private function validateUserName( $userName ) {
		$user = User::newFromName( $userName );
		if ( !$user ) {
			return false;
		}
		// If this user never existed
		if ( !$user->getId() ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		$this->decode();

		if ( !is_string( $this->description ) || !( $this->mainPage instanceof Title ) ||
			!is_array( $this->publishers )
		) {
			return false;
		}

		foreach ( $this->publishers as $publisher ) {
			if ( !$this->validateUserName( $publisher ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Decode the JSON encoded args
	 *
	 * @return bool
	 */
	protected function decode() {
		if ( $this->decoded ) {
			return true;
		}
		$jsonParse = $this->getData();
		$data = $jsonParse->isGood() ? $jsonParse->getValue() : null;

		if ( $data ) {
			$this->description = $data->description ?? null;
			$this->mainPage = !empty( $data->mainpage ) ? Title::newFromText( $data->mainpage ) :
				Title::makeTitle( NS_SPECIAL, 'Badtitle' );
			if ( isset( $data->publishers ) && is_array( $data->publishers ) ) {
				$this->publishers = [];
				foreach ( $data->publishers as $publisher ) {
					if ( !is_string( $publisher ) ) {
						$this->publishers = null;
						break;
					}
					$this->publishers[] = $publisher;
				}
			} else {
				$this->publishers = null;
			}
		}
		$this->decoded = true;
		return true;
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		$this->decode();
		return $this->description;
	}

	/**
	 * @return Title
	 */
	public function getMainPage() {
		$this->decode();
		return $this->mainPage;
	}

	/**
	 * @return array
	 */
	public function getPublishers() {
		$this->decode();
		return $this->publishers;
	}

	/**
	 * Override TextContent::getTextForSummary
	 * @param int $maxLength Maximum length, in characters (not bytes).
	 * @return string
	 */
	public function getTextForSummary( $maxLength = 250 ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$truncatedtext = $contLang->truncateForVisual(
			preg_replace( "/[\n\r]/", ' ',  $this->getDescription() ), max( 0, $maxLength )
		);

		return $truncatedtext;
	}

}
