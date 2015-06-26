<?php
/**
 * Special page for subscribing/un-subscribing a newsletter
 *
 */
class SpecialNewsletters extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Newsletters' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->requireLogin();
		$subscribeNewsletterArray = $this->getSubscribeFormFields();

		# Create HTML form
		$subscribeNewsletterForm = new HTMLForm( $subscribeNewsletterArray, $this->getContext(), 'subscribenewsletterform' );
		$subscribeNewsletterForm->setSubmitCallback( array( 'SpecialNewsletters', 'onSubscribe' ) );
		$subscribeNewsletterForm->setWrapperLegendMsg( 'newsletter-subscribe-section' );
		$subscribeNewsletterForm->setSubmitText( $this->msg( 'subscribe-button-label' )->text() );
		$subscribeNewsletterForm->show();

		$userSubscriptionsArray = $this->getSubscriptionsFormFields( $this->getUser()->getId() );
		$userSubscriptionsForm = new HTMLForm( $userSubscriptionsArray, $this->getContext(), 'usersubscriptionsform' );
		$userSubscriptionsForm->setSubmitCallback( array( 'SpecialNewsletters', 'onUnSubscribe' ) );
		$userSubscriptionsForm->setWrapperLegendMsg( 'newsletter-unsubscribe-section' );
		$userSubscriptionsForm->setSubmitText( $this->msg( 'unsubscribe-button-label' )->text() );
		$userSubscriptionsForm->show();
	}

	/**
	 * Function to get user entries from HTML form for subscribing to a newsletter
	 *
	 * @return array
	 */
	protected function getSubscribeFormFields() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_name'),
			'',
			__METHOD__
		);
		$newsletterNames = array();
		$defaultOption = array( '' => null );
		foreach( $res as $row ) {
			$newsletterNames[$row->nl_name] = $row->nl_name;
		}

		return array(
			'available-newsletters' => array(
				'required' => true,
				'type' => 'select',
				'label' => $this->msg( 'available-newsletters-field-label' )->text(),
				'options' => array_merge( $defaultOption, $newsletterNames ),
			),
			'subscriber' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId()
			)
		);
	}

	/**
	 * Perform insert query on subscriptions table with data retrieved from HTML
	 * form when a user subscribes to a newsletter
	 *
	 * @param array $formData The data entered by user in the form
	 * @return bool
	 */
	static function onSubscribe( array $formData ) {
		if ( isset( $formData['available-newsletters'] ) && isset( $formData['subscriber'] ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			//get newsletter id user is subscribing to
			$res = $dbr->select(
				'nl_newsletters',
				array('nl_id'),
				array('nl_name' => $formData['available-newsletters']),
				__METHOD__
			);
			foreach ( $res as $row ) {
				$newsletterId = $row->nl_id;
			}
			if ( isset( $newsletterId ) ) {
				$dbw = wfGetDB( DB_MASTER );
				$rowData = array(
					'newsletter_id' => $newsletterId,
					'subscriber_id' => $formData['subscriber'],
				);
				try {
					$dbw->insert( 'nl_subscriptions', $rowData, __METHOD__ );
				} catch ( DBQueryError $e ) {
					return 'You are already subscribed to this newsletter!';
				}
				RequestContext::getMain()->getOutput()->addWikiMsg( 'newsletter-subscribe-confirmation' );

				return true;
			} else {
				return 'Invalid newsletter name entered. Please try again';
			}
		}

		return false;
	}

	/**
	 *Get user entries from HTML form to un-subscribe from newsletters
	 *
	 * @param integer $id User id of logged in user
	 * @return array
	 */
	protected function getSubscriptionsFormFields( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		//get newsletter ids to which user is subscribed to
		$res = $dbr->select(
			'nl_subscriptions',
			array( 'newsletter_id' ),
			array( 'subscriber_id' => $id ),
			__METHOD__
		);
		$newsletterIds = array();
		foreach( $res as $row ) {
			$newsletterIds[] = $row->newsletter_id;
		}

		$newsletterNames = array();
		$defaultOption = array( '' => null );
		//get newsletter names
		foreach ( $newsletterIds as $value ) {
			$result = $dbr->select(
				'nl_newsletters',
				array( 'nl_name' ),
				array( 'nl_id' => $value ),
				__METHOD__
			);
			foreach( $result as $row ) {
				$newsletterNames[$row->nl_name] = $row->nl_name;
			}
		}
		return array(
			'subscribed-newsletters' => array(
				'required' => true,
				'type' => 'select',
				'label' => $this->msg( 'subscribed-newsletters-field-label' )->text(),
				'options' => array_merge( $defaultOption, $newsletterNames )
			),
			'un-subscriber' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId()
			)
		);
	}

	/**
	 * Perform deletion on subscriptions table when a user un-subscribes
	 *
	 * @param array $formData The data entered by user in the form
	 * @return bool
	 */
	static function onUnSubscribe( array $formData ) {
		if ( isset( $formData['subscribed-newsletters'] ) && isset( $formData['un-subscriber'] ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			//remove entry from subscriptions table
			$res = $dbr->select(
				'nl_newsletters',
				array( 'nl_id' ),
				array( 'nl_name' => $formData['subscribed-newsletters'] ),
				__METHOD__
			);
			foreach ( $res as $row ) {
				$newsletterId = $row->nl_id;
			}
			if ( isset ( $newsletterId ) ) {
				$dbw = wfGetDB( DB_MASTER );
				$rowData = array(
					'newsletter_id' => $newsletterId,
					'subscriber_id' => $formData['un-subscriber'],
				);
				$dbw->delete( 'nl_subscriptions', $rowData, __METHOD__ );
				RequestContext::getMain()->getOutput()->addWikiMsg( 'newsletter-unsubscribe-confirmation' );

				return true;
			} else {
				return 'Invalid newsletter name entered. Please try again';
			}
		}

		return false;
	}
}