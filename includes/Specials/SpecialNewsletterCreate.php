<?php

namespace MediaWiki\Extension\Newsletter\Specials;

use MediaWiki\Extension\Newsletter\Content\NewsletterContentHandler;
use MediaWiki\Extension\Newsletter\NewsletterValidator;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use ThrottledError;

/**
 * Special page for creating newsletters
 *
 * @license GPL-2.0-or-later
 * @author Tina Johnson
 */
class SpecialNewsletterCreate extends FormSpecialPage {

	/**
	 * @var string
	 */
	protected $newsletterName;

	public function __construct() {
		parent::__construct( 'NewsletterCreate', 'newsletter-create' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->requireLogin();
		parent::execute( $par );
		$this->getOutput()->setSubtitle(
			$this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Newsletters' ),
				$this->msg( 'newsletter-subtitlelinks-list' )->text()
			)
		);
		$this->addHelpLink( 'Help:Extension:Newsletter' );
	}

	/**
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'newsletter-create-submit' );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		return [
			'name' => [
				'name' => 'newsletter',
				'type' => 'text',
				'required' => true,
				'label-message' => 'newsletter-name',
				'maxlength' => 120,
			],
			'mainpage' => [
				'exists' => true,
				'type' => 'title',
				'required' => true,
				'label-message' => 'newsletter-title',
			],
			'description' => [
				'type' => 'textarea',
				'required' => true,
				'label-message' => 'newsletter-desc',
				'rows' => 15,
				'maxlength' => 600000,
			],
		];
	}

	/**
	 * Do input validation, error handling and create a new newletter.
	 *
	 * @param array $input The data entered by user in the form
	 * @throws ThrottledError
	 * @return Status
	 */
	public function onSubmit( array $input ) {
		$data = [
			'Name' => trim( $input['name'] ),
			'Description' => trim( $input['description'] ),
			'MainPage' => Title::newFromText( $input['mainpage'] ),
		];

		$validator = new NewsletterValidator( $data );
		$validation = $validator->validate( true );
		if ( !$validation->isGood() ) {
			// Invalid input was entered
			return $validation;
		}

		$mainPageId = $data['MainPage']->getArticleID();

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'nl_name', 'nl_main_page_id', 'nl_active' ] )
			->from( 'nl_newsletters' )
			->where(
				$dbr->expr( 'nl_name', '=', $data['Name'] )->orExpr(
					$dbr->expr( 'nl_main_page_id', '=', $mainPageId )->and( 'nl_active', '=', 1 )
				)
			)
			->caller( __METHOD__ )
			->fetchResultSet();
		// Check whether another existing newsletter has the same name or main page
		foreach ( $rows as $row ) {
			if ( $row->nl_name === $data['Name'] ) {
				return Status::newFatal( 'newsletter-exist-error', $data['Name'] );
			} elseif ( (int)$row->nl_main_page_id === $mainPageId && (int)$row->nl_active === 1 ) {
				return Status::newFatal( 'newsletter-mainpage-in-use' );
			}
		}

		$user = $this->getUser();
		if ( $user->pingLimiter( 'newsletter' ) ) {
			// Default user access level for creating a newsletter is quite low
			// so add a throttle here to prevent abuse (eg. mass vandalism spree)
			throw new ThrottledError;
		}

		$this->newsletterName = $data['Name'];
		$title = Title::makeTitleSafe( NS_NEWSLETTER, $data['Name'] );
		$editSummaryMsg = $this->msg( 'newsletter-create-editsummary' );
		$result = NewsletterContentHandler::edit(
			$title,
			$data['Description'],
			$input['mainpage'],
			[ $user->getName() ],
			$editSummaryMsg->inContentLanguage()->plain(),
			$this->getContext()
		);
		return $result;
	}

	/** @inheritDoc */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'newsletter-create-confirmation', $this->newsletterName );
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

}
