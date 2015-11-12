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

	/**
	 * @var array List of newsletters for which the current user is a publisher
	 */
	private $newslettersOfPublisher;

	public function __construct( IContextSource $context = null, IDatabase $readDb = null ) {
		if ( $readDb !== null ) {
			$this->mDb = $readDb;
		}
		parent::__construct( $context );
	}

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = array(
				'nl_id' => $this->msg( 'newsletter-manage-header-name' )->escaped(),
				'nlp_publisher_id' => $this->msg( 'newsletter-manage-header-publisher' )->escaped(),
				'permissions' => $this->msg( 'newsletter-manage-header-permissions' )->escaped(),
				'action' => $this->msg( 'newsletter-manage-header-action' )->escaped(),
			);
		}
		return $this->fieldNames;
	}

	public function getQueryInfo() {

		$this->newslettersOfPublisher = $this->mDb->selectFieldValues(
			'nl_publishers',
			'nlp_newsletter_id',
			array( 'nlp_publisher_id' => $this->getUser()->getId() ),
			__METHOD__
		);

		return array(
			'tables' => array( 'nl_newsletters', 'nl_publishers' ),
			'fields' => array(
				'nl_id',
				'nl_name',
				'nlp_publisher_id',
			),
			'join_conds' => array(
				'nl_newsletters' => array( 'LEFT JOIN', 'nlp_newsletter_id = nl_id' ),
			),
		);
	}

	public function formatValue( $field, $value ) {
		static $previous;
		$isPublisher = in_array( $this->mCurrentRow->nl_id, $this->newslettersOfPublisher );

		switch ( $field ) {
			case 'nl_id':
				if ( $previous === $value ) {
					return null;
				} else {
					$previous = $value;
					return htmlspecialchars( $this->mCurrentRow->nl_name );
				}

			case 'nlp_publisher_id':
				return htmlspecialchars( User::newFromId( $value )->getName() );

			case 'permissions' :
				return HTML::element(
						'input',
						array(
							'type' => 'checkbox',
							'disabled' => 'true',
							'id' => 'newslettermanage',
							'checked' => $isPublisher ? true : false,
						)
					) . $this->msg( 'newsletter-publisher-radiobutton-label' )->escaped();

			case 'action':
				if ( $isPublisher || $this->getUser()->isAllowed( 'newsletter-manage' ) ) {
					return HTML::element(
						'input',
						array(
							'type' => 'button',
							'value' => 'Remove', // @todo needs i18n
							'name' => $previous,
							'id' => $this->getUser()->getId(),
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
		return 'nl_id';
	}

	public function isFieldSortable( $field ) {
		return false;
	}

}
