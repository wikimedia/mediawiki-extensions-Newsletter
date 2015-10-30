<?php

/**
 * @license GNU GPL v2+
 * @author Glaisher
 */
class NewsletterLinksGenerator {

	/**
	 * Get links to newsletter special pages shown in the subtitle
	 *
	 * @return string
	 */
	public static function getSubtitleLinks() {
		global $wgLang;

		$pages = array(
			'list' => 'Newsletters',
			'create' => 'NewsletterCreate',
			'manage' => 'NewsletterManage',
		);

		$links = array();
		foreach ( $pages as $txt => $title ) {
			// 'newsletter-subtitlelinks-list'
			// 'newsletter-subtitlelinks-create'
			// 'newsletter-subtitlelinks-manage'
			$links[] = Linker::linkKnown(
				SpecialPage::getTitleFor( $title ),
				wfMessage( 'newsletter-subtitlelinks-' . $txt )->escaped()
			);
		}

		return wfMessage( 'parentheses' )->rawParams( $wgLang->pipeList( $links ) )->escaped();
	}

}
