<?php
$namespaceNames = [];

/**
 * namespace constant defined in extension.json
 */
if ( !defined( 'NS_NEWSLETTER' ) ) {
	define( 'NS_NEWSLETTER', 5500 );
	define( 'NS_NEWSLETTER_TALK', 5501 );
}

$namespaceNames['en'] = [
	NS_NEWSLETTER => 'Newsletter',
	NS_NEWSLETTER_TALK => 'Newsletter_talk',
];

$namespaceNames['ko'] = [
	NS_NEWSLETTER => '뉴스레터',
	NS_NEWSLETTER_TALK => '뉴스레터토론',
];
