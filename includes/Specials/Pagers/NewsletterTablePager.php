<?php

namespace MediaWiki\Extension\Newsletter\Specials\Pagers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Extension\Newsletter\Specials\SpecialNewsletter;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\TablePager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @license GPL-2.0-or-later
 * @author Tina Johnson
 * @author Brian Wolff <bawolff+wn@gmail.com>
 * @author Tony Thomas <01tonythomas@gmail.com>
 */
class NewsletterTablePager extends TablePager {

	/** Added to offset for sorting reasons */
	private const EXTRAINT = 150000000;

	/**
	 * @var (string|null)[]
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

	public function __construct( ?IContextSource $context = null, ?IDatabase $readDb = null ) {
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

			if ( $this->getUser()->isRegistered() ) {
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
	 * @param int $offset The indexpager offset (Number of subscribers)
	 * @param int $limit
	 * @param bool $descending Ascending or descending?
	 * @param string|false $secondaryOffset For tiebreaking the order (nl_name)
	 *
	 * @return SelectQueryBuilder
	 */
	private function getSubscribedQuery( $offset, $limit, $descending, $secondaryOffset ) {
		// XXX Hacky
		$oldIndex = $this->mIndexField;
		$this->mIndexField = 'nl_subscriber_count';
		$this->mode = 'subscribed';
		[ $tables, $fields, $conds, $fname, $options, $join_conds ] =
			$this->buildQueryInfo( $offset, $limit, $descending );

		if ( $secondaryOffset !== false ) {
			$conds[] = $this->getSecondaryOrderBy( $descending, $offset, $secondaryOffset );
		}
		if ( !$this->mDb->unionSupportsOrderAndLimit() ) {
			// Sqlite is going to be inefficient
			unset( $options['ORDER BY'] );
			unset( $options['LIMIT'] );
		}
		$subscribedPart = $this->mDb->newSelectQueryBuilder()
			->tables( $tables )
			->fields( $fields )
			->where( $conds )
			->caller( $fname )
			->options( $options )
			->joinConds( $join_conds );
		$this->mIndexField = $oldIndex;
		return $subscribedPart;
	}

	/**
	 * Add paging conditions for tie-breaking
	 *
	 * @param bool $desc
	 * @param int $offset
	 * @param string|false $secondaryOffset
	 * @return string raw sql
	 */
	private function getSecondaryOrderBy( $desc, $offset, $secondaryOffset ) {
		return $this->mDb->buildComparison(
			$this->getOp( $desc ),
			[
				'nl_subscriber_count' => $offset,
				'nl_name' => $secondaryOffset,
			]
		);
	}

	/**
	 * Get the query for newsletters for which the user is not subscribed to.
	 *
	 * This is either run directly or as part as a union. its
	 * done as part of a union to avoid expensive filesort.
	 *
	 * @param int $offset The indexpager offset (Number of subscribers)
	 * @param int $limit
	 * @param bool $descending Ascending or descending?
	 * @param string|false $secondaryOffset For tiebreaking the order (nl_name)
	 * @return SelectQueryBuilder
	 */
	private function getUnsubscribedQuery( $offset, $limit, $descending, $secondaryOffset ) {
		// XXX Hacky
		$oldIndex = $this->mIndexField;
		$this->mIndexField = 'nl_subscriber_count';
		$this->mode = 'unsubscribed';
		[ $tables, $fields, $conds, $fname, $options, $join_conds ] =
			$this->buildQueryInfo( $offset, $limit, $descending );
		if ( $secondaryOffset !== false ) {
			$conds[] = $this->getSecondaryOrderBy( $descending, $offset, $secondaryOffset );
		}
		if ( !$this->mDb->unionSupportsOrderAndLimit() ) {
			// Sqlite is going to be inefficient
			unset( $options['ORDER BY'] );
			unset( $options['LIMIT'] );
		}
		$unsubscribedPart = $this->mDb->newSelectQueryBuilder()
			->tables( $tables )
			->fields( $fields )
			->where( $conds )
			->caller( $fname )
			->options( $options )
			->joinConds( $join_conds );
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
			$realOffset = (int)substr( $offset, 1, $pipePos - 1 ) - self::EXTRAINT;
			$secondaryOffset = substr( $offset, $pipePos + 1 );
		} elseif ( strlen( $offset ) >= 2 ) {
			$realOffset = (int)substr( $offset, 1 ) - self::EXTRAINT;
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
			$unionQueryBuilder = $this->mDb->newUnionQueryBuilder()
				->add( $subscribedPart )->add( $unsubscribedPart )
				->all();
			// For some reason, this is the opposite of what
			// you would expect.
			$dir = $descending ? 'ASC' : 'DESC';
			return $unionQueryBuilder->orderBy( 'sort', $dir )
				->limit( (int)$limit )
				->caller( __METHOD__ )
				->fetchResultSet();
		} elseif ( $this->option === 'subscribed' || $offsetMode === 'S' ) {
			return $this->getSubscribedQuery(
					$realOffset, $limit, $descending, $secondaryOffset
				)->fetchResultSet();

		} else {
			// unsubscribed, or we are out of subscribed results.
			return $this->getUnsubscribedQuery(
					$realOffset, $limit, $descending, $secondaryOffset
				)->fetchResultSet();
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
			$info['fields']['sort'] = $this->mDb->buildConcat( [
				$this->mDb->addQuotes( 'U' ),
				'nl_subscriber_count+' . self::EXTRAINT,
				$this->mDb->addQuotes( '|' ),
				'nl_name',
			] );
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
			$info['fields']['sort'] = $this->mDb->buildConcat( [
				$this->mDb->addQuotes( 'S' ),
				'nl_subscriber_count+' . self::EXTRAINT,
				$this->mDb->addQuotes( '|' ),
				'nl_name',
			] );
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
			case 'action':
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
		return parent::getCellAttrs( $field, $value );
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
