<?php

/**
 * @license GNU GPL v2+
 * @author Glaisher
 */
class NewsletterLinksGenerator {

	/**
	 * Get links to newsletter special pages shown in the subtitle
	 *
	 * @param IContextSource $context
	 *
	 * @return string
	 */
	public static function getSubtitleLinks( IContextSource $context ) {
		$pages = array(
			'list' => 'Newsletters',
			'create' => 'NewsletterCreate',
		);

		// @todo Don't show create link if the user doesn't have appropriate permissions
		$links = array();
		foreach ( $pages as $txt => $title ) {
			// 'newsletter-subtitlelinks-list'
			// 'newsletter-subtitlelinks-create'
			// 'newsletter-subtitlelinks-manage'
			$links[] = Linker::linkKnown(
				SpecialPage::getTitleFor( $title ),
				$context->msg( 'newsletter-subtitlelinks-' . $txt )->escaped()
			);
		}

		return $context->msg( 'parentheses' )->rawParams( $context->getLanguage()->pipeList( $links ) )->escaped();
	}

}
