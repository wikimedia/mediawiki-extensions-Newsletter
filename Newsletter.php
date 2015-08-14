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
	'author'         => array( 'Siebrand Mazeland', 'Tina Johnson' ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Newsletter',
	'descriptionmsg' => 'newsletter-desc',
	'version'        => '1.2.0',
);

$wgMessagesDirs['Newsletter'] = __DIR__ . '/i18n';

$wgAutoloadClasses['NewsletterHooks'] = __DIR__ . '/Newsletter.hooks.php';
$wgAutoloadClasses['SpecialNewsletterCreate'] = __DIR__ . '/includes/SpecialNewsletterCreate.php';
$wgAutoloadClasses['SpecialNewsletterManage'] = __DIR__ . '/includes/SpecialNewsletterManage.php';
$wgAutoloadClasses['SpecialNewsletters'] = __DIR__ . '/includes/SpecialNewsletters.php';
$wgAutoloadClasses['EchoNewsletterFormatter'] = __DIR__ . '/includes/EchoNewsletterFormatter.php';
$wgAutoloadClasses['NewsletterTablePager'] = __DIR__ . '/includes/SpecialNewsletters.php';
$wgAutoloadClasses['ApiNewsletter'] = __DIR__ . '/includes/ApiNewsletter.php';
$wgAutoloadClasses['ApiNewsletterManage'] = __DIR__ . '/includes/ApiNewsletterManage.php';
$wgAutoloadClasses['NewsletterManageTable'] = __DIR__ . '/includes/SpecialNewsletterManage.php';

$wgSpecialPages['NewsletterCreate'] = 'SpecialNewsletterCreate';
$wgSpecialPages['NewsletterManage'] = 'SpecialNewsletterManage';
$wgSpecialPages['Newsletters'] = 'SpecialNewsletters';

$wgAPIModules['newsletterapi'] = 'ApiNewsletter';
$wgAPIModules['newslettermanageapi'] = 'ApiNewsletterManage';

$wgResourceModules['ext.newsletter'] = array(
	'scripts' => 'modules/ext.newsletter.js',
	'dependencies' => array(
		'jquery.cookie',
		'jquery.tabIndex',
		'mediawiki.jqueryMsg',
		'mediawiki.api',
		'jquery.confirmable'
	),

	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Newsletter',

);

$wgResourceModules['ext.newslettermanage'] = array(
	'scripts' => 'modules/ext.newslettermanage.js',
	'dependencies' => array(
		'jquery.cookie',
		'jquery.tabIndex',
		'mediawiki.jqueryMsg',
		'mediawiki.api',
		'jquery.confirmable'
	),

	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Newsletter',

);

//Register Hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'NewsletterHooks::onLoadExtensionSchemaUpdates';
$wgHooks['BeforeCreateEchoEvent'][] = 'NewsletterHooks::onBeforeCreateEchoEvent';
$wgHooks['EchoGetDefaultNotifiedUsers'][] = 'NewsletterHooks::onEchoGetDefaultNotifiedUsers';
$wgHooks['UnitTestsList'][] = 'NewsletterHooks::onUnitTestsList';
