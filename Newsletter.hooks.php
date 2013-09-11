<?php
/**
 * Contains classes for addition of extension specific preference settings.
 *
 * @file
 * @author Siebrand Mazeland
 * @copyright Copyright © 2013 Siebrand Mazeland
 * @license GPL-2.0+
 */

/**
 * Class to add Newsletter specific preference settings.
 */
class NewsletterPreferences {
	/**
	 * Add 'newsletter-pref-newsletter' preference.
	 *
	 * @param $user User
	 * @param $preferences array
	 * @return bool true
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		global $wgEnableEmail, $wgEnotifRevealEditorAddress;

		// Only show if email is enabled and user has a confirmed email address.
		if ( $wgEnableEmail && $user->isEmailConfirmed() ) {
			// 'newsletter-pref-newsletter' is used as opt-in for
			// users with a confirmed email address
			$prefs = array(
				'newsletter-newsletter' => array(
					'type' => 'toggle',
					'section' => 'personal/email',
					'label-message' => 'newsletter-pref-newsletter'
				)
			);

			// Add setting after 'enotifrevealaddr'.
			$preferences = wfArrayInsertAfter( $preferences, $prefs,
				$wgEnotifRevealEditorAddress ? 'enotifrevealaddr' : 'enotifminoredits' );
		}

		return true;
	}
}
