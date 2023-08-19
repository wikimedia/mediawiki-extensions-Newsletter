<?php

namespace MediaWiki\Extension\Newsletter;

use MediaWiki\Title\Title;
use Status;

/**
 * Handles validation for newsletters
 */
class NewsletterValidator {

	/** @var array */
	private $data;

	/** @var string[] */
	private static $requiredDataOnCreate = [
		'Name',
		'Description',
		'MainPage',
	];

	/** @var string[] */
	private static $requiredDataOnEdit = [
		'Description',
		'MainPage',
	];

	/**
	 * @param array $fields
	 */
	public function __construct( array $fields ) {
		$this->data = $fields;
	}

	/**
	 * Check whether all input have proper values
	 *
	 * @param bool $new
	 * @return Status fatal if invalid, good otherwise
	 */
	public function validate( $new ) {
		$requiredFields = $new ? self::$requiredDataOnCreate : self::$requiredDataOnEdit;
		// Check whether required fields are not empty
		foreach ( $requiredFields as $field ) {
			if ( !isset( $this->data[ $field ] ) || trim( $this->data[ $field ] ) === '' ) {
				return Status::newFatal( 'newsletter-input-required' );
			}
		}

		if ( $new ) {
			// Prevents random nonsensical characters in newsletter names
			// and also adds a length limit
			// (uses Title's rules now - maybe use our own?)
			$name = Title::makeTitleSafe( NS_MAIN, $this->data['Name'] );
			if ( !$name ) {
				return Status::newFatal( 'newsletter-invalid-name' );
			}
		}
		if ( strlen( $this->data['Description'] ) < 30 ) {
			// Should this limit be lowered?
			return Status::newFatal( 'newsletter-create-short-description-error' );
		}

		$mainTitle = $this->data['MainPage'];
		if ( !$mainTitle ) {
			return Status::newFatal( 'newsletter-create-mainpage-error' );
		}

		if ( !$mainTitle->canExist() ) {
			return Status::newFatal( 'newsletter-create-mainpage-error' );
		}

		if ( !$mainTitle->exists() ) {
			return Status::newFatal( 'newsletter-mainpage-non-existent' );
		}

		return Status::newGood();
	}

}
