<?php

/**
 * @license GNU GPL v2+
 * @author Tina Johnson
 * @todo Optimize queries here
 */
class NewsletterTablePager extends TablePager {

	/**
	 * @var string[]
	 */
	private $fieldNames;

	/**
	 * @var string
	 */
	private $option;

	public function __construct( IContextSource $context = null, IDatabase $readDb = null ) {
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
				$this->fieldNames['action'] = $this->msg( 'newsletter-header-action' )->escaped();
			}
		}

		return $this->fieldNames;
	}

	public function getQueryInfo() {
		//TODO we could probably just retrieve all subscribers IDs as a string here.

		$userId = $this->getUser()->getId();
		$info = array(
			'tables' => array( 'nl_newsletters' ),
			'fields' => array(
				'nl_name',
				'nl_desc',
				'nl_id',
				'subscribers' => ( '( SELECT COUNT(*) FROM nl_subscriptions WHERE nls_newsletter_id = nl_id )' ),
			),
			'options' => array( 'DISTINCT nl_id' ),
		);

		if ( $this->option == 'subscribed' ) {
			$info['conds'] = array( $this->mDb->addQuotes( $userId ) .
				' IN (SELECT nls_subscriber_id FROM nl_subscriptions WHERE nls_newsletter_id = nl_id )' );
		} else if ( $this->option == 'unsubscribed' ) {
			$info['conds'] = array( $this->mDb->addQuotes( $userId ) .
				' NOT IN (SELECT nls_subscriber_id FROM nl_subscriptions WHERE nls_newsletter_id = nl_id )' );
		} else {
			$info['conds'] = null;
		}

		if ( $this->getUser()->isLoggedIn() ) {
			$info['fields']['current_user_subscribed'] = $this->mDb->addQuotes( $userId ) .
				' IN (SELECT nls_subscriber_id FROM nl_subscriptions WHERE nls_newsletter_id = nl_id )';
		}

		return $info;
	}

	public function formatValue( $field, $value ) {
		$id = $this->mCurrentRow->nl_id;
		switch ( $field ) {
			case 'nl_name':
				$title = SpecialPage::getTitleFor( 'Newsletter', $id );
				if ( $title ) {
					return Linker::linkKnown( $title, htmlspecialchars( $value ) );
				} else {
					return htmlspecialchars( $value );
				}
			case 'nl_desc':
				return htmlspecialchars( $value );
			case 'subscriber_count':
				return HTML::element(
					'span',
					array( 'id' => "nl-count-$id" ),
					$this->mCurrentRow->subscribers
				);
			case 'action' :
				if ( $this->mCurrentRow->current_user_subscribed ) {
					$title = SpecialPage::getTitleFor( 'Newsletter', $id . '/' . SpecialNewsletter::NEWSLETTER_UNSUBSCRIBE );
					$link = Linker::linkKnown( $title,
						$this->msg( 'newsletter-unsubscribe-button' )->escaped(),
						array(
							'class' => 'newsletter-subscription newsletter-subscribed',
							'id' => "nl-$id"
						)
					);
				} else {
					$title = SpecialPage::getTitleFor( 'Newsletter', $id . '/' . SpecialNewsletter::NEWSLETTER_SUBSCRIBE );
					$link = Linker::linkKnown(
						$title,
						$this->msg( 'newsletter-subscribe-button' )->escaped(),
						array(
							'class' => 'newsletter-subscription newsletter-unsubscribed',
							'id' => "nl-$id"
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
		switch( $field ) {
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
