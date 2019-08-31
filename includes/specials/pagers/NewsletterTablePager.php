<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

/**
 * @license GPL-2.0-or-later
 * @author Tina Johnson
 * @author Brian Wolff <bawolff+wn@gmail.com>
 * @author Tony Thomas <01tonythomas@gmail.com>
 */
class NewsletterTablePager extends TablePager {

	/** Added to offset for sorting reasons */
	const EXTRAINT = 150000000;

	/**
	 * @var string[]
	 */
	private $fieldNames;

	/**
	 * @var string
	 */
	private $option;

	/** @var string */
	protected $mode;

	/** @var Newsletter[] */
	private $newslettersArray;

	public function __construct( IContextSource $context = null, IDatabase $readDb = null ) {
		if ( $readDb !== null ) {
			$this->mDb = $readDb;
		}
		// Because we mIndexField is not unique
		// we need the last one.
		$this->setIncludeOffset( true );
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

	/**
	 * Get the query for newsletters for which the user is subscribed to.
	 *
	 * This is either run directly or as part as a union. It's done as part of
	 * a union to avoid expensive filesort.
	 *
	 * @param string $offset The indexpager offset (Number of subscribers)
	 * @param int $limit
	 * @param bool $descending Ascending or descending?
	 * @param string $secondaryOffset For tiebreaking the order (nl_name)
	 *
	 * @return string
	 */
	private function getSubscribedQuery( $offset, $limit, $descending, $secondaryOffset ) {
		// XXX Hacky
		$oldIndex = $this->mIndexField;
		$this->mIndexField = 'nl_subscriber_count';
		$this->mode = 'subscribed';
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) =
			$this->buildQueryInfo( $offset, $limit, $descending );

		if ( $secondaryOffset !== false ) {
			$conds[] = $this->getSecondaryOrderBy( $descending, $offset, $secondaryOffset );
		}
		if ( !$this->mDb->unionSupportsOrderAndLimit() ) {
			// Sqlite is going to be inefficient
			unset( $options['ORDER BY'] );
			unset( $options['LIMIT'] );
		}
		$subscribedPart = $this->mDb->selectSQLText(
			$tables, $fields, $conds, $fname, $options, $join_conds
		);
		$this->mIndexField = $oldIndex;
		return $subscribedPart;
	}

	/**
	 * Add paging conditions for tie-breaking
	 *
	 * @param string $desc
	 * @param int $offset
	 * @param int $secondaryOffset
	 * @return mixed
	 */
	private function getSecondaryOrderBy( $desc, $offset, $secondaryOffset ) {
		$operator = $this->getOp( $desc );
		return $this->mDb->makeList( [
			'nl_subscriber_count ' . $operator . $this->mDb->addQuotes( $offset ),
			$this->mDb->makeList( [
				'nl_subscriber_count' => $offset,
				'nl_name' . ( $desc ? '>' : '<' ) . $this->mDb->addQuotes( $secondaryOffset )
			], LIST_AND )
		], LIST_OR );
	}

	/*
	 * Get the query for newsletters for which the user is not subscribed to.
	 *
	 * This is either run directly or as part as a union. its
	 * done as part of a union to avoid expensive filesort.
	 *
	 * @param string $offset The indexpager offset (Number of subscribers)
	 * @param int $limit
	 * @param bool $descending Ascending or descending?
	 * @param string $secondaryOffset For tiebreaking the order (nl_name)
	 */
	private function getUnsubscribedQuery( $offset, $limit, $descending, $secondaryOffset ) {
		// XXX Hacky
		$oldIndex = $this->mIndexField;
		$this->mIndexField = 'nl_subscriber_count';
		$this->mode = 'unsubscribed';
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) =
			$this->buildQueryInfo( $offset, $limit, $descending );
		if ( $secondaryOffset !== false ) {
			$conds[] = $this->getSecondaryOrderBy( $descending, $offset, $secondaryOffset );
		}
		if ( !$this->mDb->unionSupportsOrderAndLimit() ) {
			// Sqlite is going to be inefficient
			unset( $options['ORDER BY'] );
			unset( $options['LIMIT'] );
		}
		$unsubscribedPart = $this->mDb->selectSQLText(
			$tables, $fields, $conds, $fname, $options, $join_conds
		);
		$this->mIndexField = $oldIndex;
		return $unsubscribedPart;
	}

	/**
	 * Operator for paging.
	 *
	 * @param bool $desc Descending vs Ascending.
	 * @return string
	 */
	private function getOp( $desc ) {
		if ( $desc ) {
			return '>';
		} else {
			return '<';
		}
	}

	/**
	 * Hacky stuff with offset in order to actually use two separate queries unioned, sorted on
	 * multiple fields, instead of one query like IndexPager expects.
	 *
	 * @param string $offset
	 * @param int $limit
	 * @param bool $descending
	 * @return mixed
	 */
	public function reallyDoQuery( $offset, $limit, $descending ) {
		$pipePos = strpos( $offset, '|' );
		if ( $pipePos !== false ) {
			$realOffset = substr( $offset, 1, $pipePos - 1 ) - self::EXTRAINT;
			$secondaryOffset = substr( $offset, $pipePos + 1 );
		} elseif ( strlen( $offset ) >= 2 ) {
			$realOffset = substr( $offset, 1 ) - self::EXTRAINT;
			$secondaryOffset = false;
		} else {
			$realOffset = 0;
			$secondaryOffset = false;
		}
		$offsetMode = $offset === '' ? '' : substr( $offset, 0, 1 );
		if ( $this->option == 'all' && (
				( $offsetMode === '' ) ||
				( $offsetMode === 'U' && !$descending ) ||
				( $offsetMode === 'S' && $descending )
			) ) {
			$subscribedPart = $this->getSubscribedQuery(
				$descending ? $realOffset : 0,
				$limit,
				$descending,
				$descending ? $secondaryOffset : false
			);
			$unsubscribedPart = $this->getUnsubscribedQuery(
				$descending ? 0 : $realOffset,
				$limit,
				$descending,
				$descending ? false : $secondaryOffset
			);
			$combinedResult = $this->mDb->unionQueries(
				[ $subscribedPart, $unsubscribedPart ],
				true
			);
			// For some reason, this is the opposite of what
			// you would expect.
			$dir = $descending ? 'ASC' : 'DESC';
			$combinedResult .= " ORDER BY sort $dir LIMIT " . (int)$limit;
			return $this->mDb->query( $combinedResult, __METHOD__ );
		} elseif ( $this->option === 'subscribed' || $offsetMode === 'S' ) {
			return $this->mDb->query(
				$this->getSubscribedQuery(
					$realOffset, $limit, $descending, $secondaryOffset
				),
				__METHOD__
			);

		} else {
			// unsubscribed, or we are out of subscribed results.
			return $this->mDb->query(
				$this->getUnsubscribedQuery(
					$realOffset, $limit, $descending, $secondaryOffset
				), __METHOD__
			);
		}
	}

	/**
	 * @param \Wikimedia\Rdbms\IResultWrapper $result
	 */
	public function preprocessResults( $result ) {
		foreach ( $result as $res ) {
			$this->newslettersArray[$res->nl_id] = Newsletter::newFromID( (int)$res->nl_id );
		}
		parent::preprocessResults( $result );
	}

	/**
	 * @return array
	 */
	public function getQueryInfo() {
		$userId = $this->getUser()->getId();
		$info = [
			'tables' => [ 'nl_newsletters', 'nl_subscriptions' ],
			'fields' => [
				'nl_name',
				'nl_desc',
				'nl_id',
				'nl_subscriber_count',
				'nls_subscriber_id'
			],
		];
		$info['conds'] = [ 'nl_active' => 1 ];

		if ( $this->mode == "unsubscribed" ) {
			$info['fields']['sort'] = $this->mDb->buildConcat(
				[ '"U"', 'nl_subscriber_count+' . self::EXTRAINT, '"|"',  'nl_name' ]
			);
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
			$info['fields']['sort'] = $this->mDb->buildConcat(
				[ '"S"', 'nl_subscriber_count+' . self::EXTRAINT, '"|"',  'nl_name' ]
			);
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
		$services = MediaWikiServices::getInstance();
		$linkRenderer = $services->getLinkRenderer();
		$contLang = $services->getContentLanguage();
		$id = $this->mCurrentRow->nl_id;
		$newsletter = $this->newslettersArray[(int)$id];
		switch ( $field ) {
			case 'nl_name':
				$title = Title::makeTitleSafe( NS_NEWSLETTER, $newsletter->getName() );
				if ( $title ) {
					return $linkRenderer->makeKnownLink( $title, $value );
				} else {
					return htmlspecialchars( $value );
				}
			case 'nl_desc':
				return htmlspecialchars( $contLang->truncateForVisual( $value, 644 ) );
			case 'subscriber_count':
				return Html::element(
					'span',
					[ 'id' => "nl-count-$id" ],
					$contLang->formatNum( -(int)$this->mCurrentRow->nl_subscriber_count )
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

	/**
	 * @param string $field
	 * @param mixed $value
	 * @return array
	 */
	public function getCellAttrs( $field, $value ) {
		$ret = parent::getCellAttrs( $field, $value );
		return $ret;
	}

	public function getDefaultSort() {
		return 'sort';
	}

	public function isFieldSortable( $field ) {
		return false;
	}

	public function setUserOption( $value ) {
		$this->option = $value;
	}

}
