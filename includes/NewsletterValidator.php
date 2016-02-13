<?php
/**
 * Handles validation for newsletters
 */

class NewsletterValidator {

	private static $requiredData = array(
		'Name',
		'Description',
		'MainPage',
	);

	public function __construct( $fields ) {
		$this->data = $fields;
	}

	/**
	 * Check whether all input have proper values
	 *
	 * @return Status fatal if invalid, good otherwise
	 */
	public function validate() {
		// Check whether required fields are not empty
		foreach( self::$requiredData as $field ) {
			if ( trim( $this->data[ $field ] ) === '' ) {
				return Status::newFatal( 'newsletter-input-required' );
			}
		}

		// Prevents random nonsensical characters in newsletter names
		// and also adds a length limit
		// (uses Title's rules now - maybe use our own?)
		$name = Title::makeTitleSafe( NS_MAIN, $this->data['Name'] );
		if ( !$name ) {
			return Status::newFatal( 'newsletter-invalid-name' );
		}

		if ( strlen ( $this->data['Description'] ) < 30 ) {
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
