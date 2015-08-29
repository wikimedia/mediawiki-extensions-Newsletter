<?php

/**
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class NewsletterManageTablePager extends TablePager {

	/**
	 * @var null|string[]
	 */
	private $fieldNames = null;

	public function __construct( IContextSource $context = null, IDatabase $readDb = null ) {
		if ( $readDb !== null ) {
			$this->mDb = $readDb;
		}
		parent::__construct( $context );
	}

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = array(
				'newsletter_id' => $this->msg( 'newsletter-manage-header-name' )->text(),
				'publisher_id' => $this->msg( 'newsletter-manage-header-publisher' )->text(),
				'permissions' => $this->msg( 'newsletter-manage-header-permissions' )->text(),
				'action' => $this->msg( 'newsletter-manage-header-action' )->text(),
			);
		}
		return $this->fieldNames;
	}

	public function getQueryInfo() {
		return array(
			'tables' => array( 'nl_publishers', 'nl_newsletters' ),
			'fields' => array(
				'newsletter_id',
				'publisher_id',
				'is_owner' => 'publisher_id = nl_owner_id',
			),
			'join_conds' => array(
				'nl_newsletters' => array( 'LEFT JOIN', 'newsletter_id = nl_id' ),
			),
		);
	}

	public function formatValue( $field, $value ) {
		static $previous;

		switch ( $field ) {
			case 'newsletter_id':
				if ( $previous === $value ) {

					return null;
				} else {
					$dbr = wfGetDB( DB_SLAVE );
					$res = $dbr->select(
						'nl_newsletters',
						array( 'nl_name' ),
						array( 'nl_id' => $value ),
						__METHOD__,
						array()
					);
					$newsletterName = null;
					foreach ( $res as $row ) {
						$newsletterName = $row->nl_name;
					}
					$previous = $value;

					return $newsletterName;
				}
			case 'publisher_id' :
				$user = User::newFromId( $value );

				return $user->getName();
			case 'permissions' :
				$radioOwner = HTML::element(
						'input',
						array(
							'type' => 'checkbox',
							'disabled' => 'true',
							'id' => 'newslettermanage',
							'checked' => $this->mCurrentRow->is_owner ? true : false,
						)
					) . $this->msg( 'newsletter-owner-radiobutton-label' );

				$radioPublisher = HTML::element(
						'input',
						array(
							'type' => 'checkbox',
							'disabled' => 'true',
							'id' => 'newslettermanage',
							'checked' => $this->mCurrentRow->is_owner ? false : true,
						)
					) . $this->msg( 'newsletter-publisher-radiobutton-label' );

				return $radioOwner . $radioPublisher;
			case 'action' :
				$isCurrentUser = $this->mCurrentRow->publisher_id == $this->getUser()->getId();

				if ( !$this->mCurrentRow->is_owner && !$isCurrentUser ) {
					return HTML::element(
						'input',
						array(
							'type' => 'button',
							'value' => 'Remove',
							'name' => $previous,
							'id' => $this->mCurrentRow->publisher_id,
						)
					);
				}
				return '';
		}
	}

	public function getCellAttrs( $field, $value ) {
		return array(
			'align' => 'center',
		);
	}

	public function getDefaultSort() {
		return 'newsletter_id';
	}

	public function isFieldSortable( $field ) {
		return false;
	}

}
