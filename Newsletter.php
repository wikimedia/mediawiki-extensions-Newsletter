<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Newsletter' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Newsletter'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['NewsletterAlias'] = __DIR__ . '/Newsletter.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for Newsletter extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the Newsletter extension requires MediaWiki 1.31+' );
}
