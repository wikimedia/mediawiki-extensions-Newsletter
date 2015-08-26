<?php

class NewsletterTablePager extends TablePager {

	/**
	 * @see TablePager::getFieldnames
	 * @var array|null
	 */
	private $fieldNames = null;

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = array();
			foreach ( SpecialNewsletters::$fields as $field => $property ) {
				$this->fieldNames[$field] = $this->msg( "newsletter-header-$property" )->text();
			}
		}
		return $this->fieldNames;
	}

	public function getQueryInfo() {
		$info = array(
			'tables' => array( 'nl_newsletters' ),
			'fields' => array(
				'nl_name',
				'nl_desc',
				'nl_id',
			),
		);

		return $info;
	}

	public function formatValue( $field, $value ) {
		switch ( $field ) {
			case 'nl_name':
				$dbr = wfGetDB( DB_SLAVE );
				$res = $dbr->select(
					'nl_newsletters',
					array( 'nl_main_page_id' ),
					array( 'nl_name' => $value ),
					__METHOD__
				);

				$mainPageId = '';
				foreach ( $res as $row ) {
					$mainPageId = $row->nl_main_page_id;
				}

				$url = $mainPageId ? Title::newFromID( $mainPageId )->getFullURL() : "#";

				return '<a href="' . $url . '">' . $value . '</a>';
			case 'nl_desc':
				return $value;
			case 'subscriber_count':
				return HTML::element(
					'input',
					array(
						'type' => 'textbox',
						'readonly' => 'true',
						'id' => 'newsletter-' . $this->mCurrentRow->nl_id,
						'value' => in_array(
							$this->mCurrentRow->nl_id,
							SpecialNewsletters::$allSubscribedNewsletterId
						) ?
							SpecialNewsletters::$subscriberCount[$this->mCurrentRow->nl_id] : 0,

					)
				);
			case 'action' :
				$radioSubscribe = Html::element(
						'input',
						array(
							'type' => 'radio',
							'name' => 'nl_id-' . $this->mCurrentRow->nl_id,
							'value' => 'subscribe',
							'checked' => in_array(
								$this->mCurrentRow->nl_id,
								SpecialNewsletters::$subscribedNewsletterId
							) ? true : false,
						)
					) . $this->msg( 'newsletter-subscribe-button-label' );
				$radioUnSubscribe = Html::element(
						'input',
						array(
							'type' => 'radio',
							'name' => 'nl_id-' . $this->mCurrentRow->nl_id,
							'value' => 'unsubscribe',
							'checked' => in_array(
								$this->mCurrentRow->nl_id,
								SpecialNewsletters::$subscribedNewsletterId
							) ? false : true,
						)
					) . $this->msg( 'newsletter-unsubscribe-button-label' );

				return $radioSubscribe . $radioUnSubscribe;
		}
	}

	public function getDefaultSort() {
		return 'nl_name';
	}

	public function isFieldSortable( $field ) {
		return false;
	}

}
