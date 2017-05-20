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

	protected $mode;

	public function __construct( IContextSource $context = null, Database $readDb = null ) {
		if ( $readDb !== null ) {
			$this->mDb = $readDb;
		}
		parent::__construct( $context );
	}

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = [
				'nl_name' => $this->msg( 'newsletter-header-name' )->text(),
				'nl_desc' => $this->msg( 'newsletter-header-description' )->text(),
				'subscriber_count' => $this->msg( 'newsletter-header-subscriber_count' )->text(),
			];

			if ( $this->getUser()->isLoggedIn() ) {
				// Only logged-in users can (un)subscribe
				$this->fieldNames['action'] = null;
			}
		}

		return $this->fieldNames;
	}

	private function getSubscribedQuery( $offset, $limit, $descending ) {
		// XXX Hacky
		$oldIndex = $this->mIndexField;
		$this->mIndexField = 'nl_name';
		$this->mode = 'subscribed';
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) =
			$this->buildQueryInfo( $offset, $limit, $descending );
		$subscribedPart = $this->mDb->selectSQLText(
			$tables, $fields, $conds, $fname, $options, $join_conds
		);
		$this->mIndexField = $oldIndex;
		return $subscribedPart;
	}

	private function getUnsubscribedQuery( $offset, $limit, $descending ) {
		// XXX Hacky
		$oldIndex = $this->mIndexField;
		$this->mIndexField = 'nl_name';
		$this->mode = 'unsubscribed';
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) =
			$this->buildQueryInfo( $offset, $limit, $descending );
		$unsubscribedPart = $this->mDb->selectSQLText(
			$tables, $fields, $conds, $fname, $options, $join_conds
		);
		$this->mIndexField = $oldIndex;
		return $unsubscribedPart;
	}

	public function reallyDoQuery( $offset, $limit, $descending ) {
		$realOffset = substr( $offset, 1 );
		if ( $this->option === 'subscribed' ) {
			return $this->mDb->query(
				$this->getSubscribedQuery(
					$realOffset, $limit, $descending
				),
				__METHOD__
			);
		} elseif ( $this->option == 'all' && substr( $offset, 0, 1 ) !== 'U' ) {
			$subscribedPart = $this->getSubscribedQuery( $realOffset, $limit, $descending );
			$unsubscribedPart = $this->getUnsubscribedQuery( '', $limit, $descending );
			$combinedResult = $this->mDb->unionQueries(
				[ $subscribedPart, $unsubscribedPart ],
				true
			);
			$combinedResult .= " ORDER BY nls_subscriber_id DESC LIMIT " . (int)
				$limit . ";";
			return $this->mDb->query( $combinedResult, __METHOD__ );
		} else {
			// unsubscribed, or we are out of subscribed results.
			return $this->mDb->query(
				$this->getUnsubscribedQuery(
					$realOffset, $limit, $descending
				), __METHOD__
			);
		}
	}

	public function getQueryInfo() {
		$userId = $this->getUser()->getId();
		$tblSubscriptions = $this->mDb->tableName( 'nl_subscriptions' );

		$info = [
			'tables' => [ 'nl_newsletters', 'nl_subscriptions' ],
			'fields' => [
				'nl_name',
				'nl_desc',
				'nl_id',
				'subscribers' => "( SELECT COUNT(*) FROM $tblSubscriptions WHERE nls_newsletter_id = nl_id )",
			    'nls_subscriber_id'
			],
		];
		$info['conds'] = [ 'nl_active' => 1 ];

		if ( $this->mode == "unsubscribed" ) {
			$info['fields']['sort'] = $this->mDb->buildConcat( [ '"U"', 'nl_name' ] );
			$info['join_conds'] = [
				'nl_subscriptions' => [
					'LEFT OUTER JOIN',
					[
						'nl_id=nls_newsletter_id',
						'nls_subscriber_id' => $userId
					]
				]
			];
			$info['conds']['nls_subscriber_id'] = null;
		} elseif ( $this->mode == "subscribed" ) {
			$info['fields']['sort'] = $this->mDb->buildConcat( [ '"S"', 'nl_name' ] );
			$info['join_conds'] = [
				'nl_subscriptions' => [
					'INNER JOIN',
					[
						'nl_id=nls_newsletter_id',
						'nls_subscriber_id' => $userId
					]
				]
			];
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
					[ 'id' => "nl-count-$id" ],
					$this->mCurrentRow->subscribers
				);
			case 'action' :
				if ( $this->mCurrentRow->nls_subscriber_id ) {
					$title = SpecialPage::getTitleFor(
						'Newsletter', $id . '/' . SpecialNewsletter::NEWSLETTER_UNSUBSCRIBE
					);
					$link = $linkRenderer->makeKnownLink( $title,
						$this->msg( 'newsletter-unsubscribe-button' )->text(),
						[
							'class' => 'newsletter-subscription newsletter-subscribed',
							'data-mw-newsletter-id' => $id
						]
					);
				} else {
					$title = SpecialPage::getTitleFor(
						'Newsletter', $id . '/' . SpecialNewsletter::NEWSLETTER_SUBSCRIBE
					);
					$link = $linkRenderer->makeKnownLink(
						$title,
						$this->msg( 'newsletter-subscribe-button' )->text(),
						[
							'class' => 'newsletter-subscription newsletter-unsubscribed',
							'data-mw-newsletter-id' => $id
						]
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
		$this->mDefaultDirection = IndexPager::DIR_ASCENDING;
		return 'sort';
	}

	public function isFieldSortable( $field ) {
		return false;
	}

	public function setUserOption( $value ) {
		$this->option = $value;
	}

}
