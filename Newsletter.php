<?php
/*
 * @file
 * @author Siebrand Mazeland
 * @copyright Copyright © 2013 Siebrand Mazeland
 * @license GPL-2.0+
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install the Newsletter extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/Newsletter/Newsletter.php" );
EOT;
	exit( 1 );
}

$wgExtensionCredits[ 'other' ][ ] = array(
	'path'           => __FILE__,
	'name'           => 'Newsletter',
	'author'         => array( 'Siebrand Mazeland', ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Newsletter',
	'descriptionmsg' => 'newsletter-desc',
	'version'        => '1.1.0',
);

$wgMessagesDirs[''] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Newsletter'] = __DIR__ . '/Newsletter.i18n.php';

$wgAutoloadClasses['NewsletterPreferences'] = __DIR__ . '/Newsletter.hooks.php';

$wgHooks['GetPreferences'][] = 'NewsletterPreferences::onGetPreferences';
