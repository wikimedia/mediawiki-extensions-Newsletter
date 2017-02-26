<?php

/**
 * @license GNU GPL v2+
 * @author Tina Johnson
 * @todo Optimize queries here
 */

use MediaWiki\MediaWikiServices;

class NewsletterTablePager extends TablePager {

	/**
	 * @var string[]
	 */
	private $fieldNames;

	/**
	 * @var string
	 */
	private $option;

	public function __construct( IContextSource $context = null, Database $readDb = null ) {
		if ( $readDb !== null ) {
			$this->mDb = $readDb;
		}
		parent::__construct( $context );
	}

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = array(
				'nl_name' => $this->msg( 'newsletter-header-name' )->escaped(),
				'nl_desc' => $this->msg( 'newsletter-header-description' )->escaped(),
				'subscriber_count' => $this->msg( 'newsletter-header-subscriber_count' )->escaped(),
			);

			if ( $this->getUser()->isLoggedIn() ) {
				// Only logged-in users can (un)subscribe
				$this->fieldNames['action'] = null;
			}
		}

		return $this->fieldNames;
	}

	public function getQueryInfo() {
		//TODO we could probably just retrieve all subscribers IDs as a string here.

		$userId = $this->getUser()->getId();
		$tblSubscriptions = $this->mDb->tableName( 'nl_subscriptions' );

		$info = array(
			'tables' => array( 'nl_newsletters' ),
			'fields' => array(
				'nl_name',
				'nl_desc',
				'nl_id',
				'subscribers' => "( SELECT COUNT(*) FROM $tblSubscriptions WHERE nls_newsletter_id = nl_id )",
			),
			'options' => array( 'DISTINCT nl_id' ),
		);

		$info['conds'] = array( 'nl_active = 1' );
		if ( $this->option == 'subscribed' ) {
			$info['conds'][] = ( $this->mDb->addQuotes( $userId ) .
				" IN (SELECT nls_subscriber_id FROM $tblSubscriptions WHERE nls_newsletter_id = nl_id )" );
		} elseif ( $this->option == 'unsubscribed' ) {
			$info['conds'][] = ( $this->mDb->addQuotes( $userId ) .
				" NOT IN (SELECT nls_subscriber_id FROM $tblSubscriptions WHERE nls_newsletter_id = nl_id )" );
		}

		if ( $this->getUser()->isLoggedIn() ) {
			$info['fields']['current_user_subscribed'] = $this->mDb->addQuotes( $userId ) .
				" IN (SELECT nls_subscriber_id FROM $tblSubscriptions WHERE nls_newsletter_id = nl_id )";
		}

		return $info;
	}

	public function formatValue( $field, $value ) {
		global $wgContLang;

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$id = $this->mCurrentRow->nl_id;
		$newsletter = Newsletter::newFromID( (int)$id );
		switch ( $field ) {
			case 'nl_name':
				$title = Title::makeTitleSafe( NS_NEWSLETTER, $newsletter->getName() );
				if ( $title ) {
					return $linkRenderer->makeKnownLink( $title, $value );
				} else {
					return htmlspecialchars( $value );
				}
			case 'nl_desc':
				return htmlspecialchars( $wgContLang->truncate( $value, 644 ) );
			case 'subscriber_count':
				return Html::element(
					'span',
					array( 'id' => "nl-count-$id" ),
					$this->mCurrentRow->subscribers
				);
			case 'action' :
				if ( $this->mCurrentRow->current_user_subscribed ) {
					$title = SpecialPage::getTitleFor(
						'Newsletter', $id . '/' . SpecialNewsletter::NEWSLETTER_UNSUBSCRIBE
					);
					$link = $linkRenderer->makeKnownLink( $title,
						$this->msg( 'newsletter-unsubscribe-button' )->text(),
						array(
							'class' => 'newsletter-subscription newsletter-subscribed',
							'data-newsletter-id' => $id
						)
					);
				} else {
					$title = SpecialPage::getTitleFor(
						'Newsletter', $id . '/' . SpecialNewsletter::NEWSLETTER_SUBSCRIBE
					);
					$link = $linkRenderer->makeKnownLink(
						$title,
						$this->msg( 'newsletter-subscribe-button' )->text(),
						array(
							'class' => 'newsletter-subscription newsletter-unsubscribed',
							'data-newsletter-id' => $id
						)
					);
				}

				return $link;
		}
	}

	/*
	 * @return array
	 */
	public function getCellAttrs( $field, $value ) {
		$ret = parent::getCellAttrs( $field, $value );
		// @todo use CSS, not inline HTML
		switch ( $field ) {
			case 'nl_name':
				$ret['width'] = '20%';
				break;
			case 'nl_desc':
				$ret['width'] = '40%';
				break;
			case 'subscriber_count':
				$ret['width'] = '5%';
				break;
			case 'action':
				$ret['width'] = '20%';
				break;
		}

		return $ret;
	}

	public function getDefaultSort() {
		$this->mDefaultDirection = IndexPager::DIR_DESCENDING;
		$sort = $this->getUser()->isLoggedIn() ? 'current_user_subscribed' : 'nl_name';
		return $sort;
	}

	public function isFieldSortable( $field ) {
		return false;
	}

	public function setUserOption( $value ) {
		$this->option = $value;
	}

}
