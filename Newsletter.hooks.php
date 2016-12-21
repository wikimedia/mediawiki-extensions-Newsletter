<?php

/**
 * Class to add Hooks used by Newsletter.
 */
class NewsletterHooks {

	/**
	 * Function to be called before EchoEvent
	 *
	 * @param array $notifications Echo notifications
	 * @param array $notificationCategories Echo notification categories
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories ) {
		$notificationCategories['newsletter'] = array(
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-newsletter',
		);

		$notifications['newsletter-announce'] = array(
			'category' => 'newsletter',
			'section' => 'alert',
			'primary-link' => array(
				'message' => 'newsletter-notification-link-text-new-issue',
				'destination' => 'new-issue'
			),
			'secondary-link' => array(
				'message' => 'newsletter-notification-link-text-view-newsletter',
				'destination' => 'newsletter'
			),
			'user-locators' => array(
				'EchoNewsletterUserLocator::locateNewsletterSubscribedUsers',
			),
			'presentation-model' => 'EchoNewsletterPresentationModel',
			'title-message' => 'newsletter-notification-title',
			'title-params' => array( 'newsletter-name', 'title', 'agent', 'user' ),
			'flyout-message' => 'newsletter-notification-flyout',
			'flyout-params' => array( 'newsletter-name', 'agent', 'user' ),
			'payload' => array( 'summary' ),
			'email-subject-message' => 'newsletter-email-subject',
			'email-subject-params' => array( 'newsletter-name' ),
			'email-body-batch-message' => 'newsletter-email-batch-body',
			'email-body-batch-params' =>  array( 'newsletter-name', 'agent', 'user' ),
		);

		$notifications['newsletter-newpublisher'] = array(
			'category' => 'newsletter',
			'primary-link' => array(
				'message' => 'newsletter-notification-link-text-new-publisher',
				'destination' => 'newsletter'
			),
			'user-locators' => array(
				array( 'EchoUserLocator::locateFromEventExtra', array( 'new-publishers-id' ) )
			),
			'presentation-model' => 'EchoNewsletterPublisherPresentationModel',
			'title-message' => 'newsletter-notification-new-publisher-title',
			'title-params' => array( 'newsletter-name', 'agent' ),
			'flyout-message' => 'newsletter-notification-new-publisher-flyout',
			'flyout-params' => array( 'newsletter-name', 'agent' ),
		);

		return true;
	}

	/**
	 * Allows to add our own error message to LoginForm
	 *
	 * @param array $messages
	 */
	public static function onLoginFormValidErrorMessages( &$messages ) {
		$messages[] = 'newsletter-subscribe-loginrequired'; // on Special:Newsletter/id/subscribe
	}

	/**
	 * Add tables to Database
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'nl_newsletters', __DIR__ . '/sql/nl_newsletters.sql' );
		$updater->addExtensionTable( 'nl_issues', __DIR__ . '/sql/nl_issues.sql' );
		$updater->addExtensionTable( 'nl_subscriptions', __DIR__ . '/sql/nl_subscriptions.sql' );
		$updater->addExtensionTable( 'nl_publishers', __DIR__ . '/sql/nl_publishers.sql' );
		$updater->addExtensionField( 'nl_newsletters', 'nl_active', __DIR__ . '/sql/nl_newsletters-add-active.sql' );
		$updater->dropExtensionIndex( 'nl_newsletters', 'nl_main_page_id', __DIR__ . '/sql/nl_main_page_id-drop-index.sql' );
		$updater->addExtensionIndex( 'nl_newsletters', 'nl_main_page_active', __DIR__ . '/sql/nl_newsletters-add-unique.sql' );

		return true;
	}

	/**
	 * Handler for UnitTestsList hook.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 * @param &$files Array of unit test files
	 * @return bool true in all cases
	 */
	public static function onUnitTestsList( &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/tests/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = array();
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );

		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Tables that Extension:UserMerge needs to update
	 *
	 * @param array $updateFields
	 * @return bool
	 */
	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = array( 'nl_publishers', 'nlp_publisher_id' );
		$updateFields[] = array( 'nl_subscriptions', 'nls_subscriber_id' );

		return true;
	}

	/**
	 * @param EditPage $editPage
	 * @return bool
	 */
	public static function onAlternateEdit( EditPage $editPage ) {
		$out = $editPage->getContext()->getOutput();
		$title = $editPage->getTitle();

		if ( $title->inNamespace( NS_NEWSLETTER ) ) {
			if ( $title->hasContentModel( 'NewsletterContent' ) ) {
				$newsletter = Newsletter::newFromName( $title->getText() );
				if ( $newsletter ) {
					$title = SpecialPage::getTitleFor( 'Newsletter', $newsletter->getId() . '/' . 'manage' );
					$out->redirect( $title->getFullURL() );
				}
			}
			return false;
		}

		return true;
	}

	/**
	 * @param Article $article
	 * @param User $user
	 * @return bool
	 * @throws ReadOnlyError
	 */
	public static function onCustomEditor( Article $article, User $user ) {
		if ( !$article->getTitle()->inNamespace( NS_NEWSLETTER ) ) {
			return true;
		}

		$newsletter = Newsletter::newFromName( $article->getTitle()->getText() );
		if ( $newsletter ) {
			// A newsletter exists in that title, lets redirect to manage page
			$editPage = new NewsletterEditPage( $article->getContext(), $newsletter );
			$editPage->edit();
			return false;
		}

		$editPage = new NewsletterEditPage( $article->getContext() );
		$editPage->edit();
		return false;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $reason
	 * @param string $error
	 * @param Status $status
	 * @param $suppress
	 * @return bool
	 * @throws PermissionsError
	 */
	public static function onArticleDelete( &$wikiPage, &$user, &$reason, &$error, Status &$status, $suppress) {
		if ( !$wikiPage->getTitle()->inNamespace( NS_NEWSLETTER ) ) {
			return true;
		}
		$newsletter = Newsletter::newFromName( $wikiPage->getTitle()->getText() );
		if ( $newsletter ) {
			if ( !$newsletter->canDelete( $user ) ) {
				throw new PermissionsError( 'newsletter-delete' );
			}
			$success = NewsletterStore::getDefaultInstance()
				->deleteNewsletter( $newsletter, $reason );
			if ( $success ) {
				$status->setOK( $success );
				return true;
			} else {
				// Show error message and allow resubmitting in case of failure
				$status->error( 'newsletter-delete-failure', $newsletter->getName() );
				return false;
			}
		}
		return false;
	}

	/**
	 * @param PageArchive $archive
	 * @param Title $title
	 * @return bool
	 */
	public static function onUndeleteForm( PageArchive &$archive, Title $title ) {
		if ( !$title->inNamespace( NS_NEWSLETTER ) ) {
			return true;
		}
		$user = RequestContext::getMain()->getUser();
		$newsletterName = $title->getText();
		$newsletter = Newsletter::newFromName( $newsletterName, false );
		if ( $newsletter ) {
			if ( !$newsletter->canRestore( $user ) ) {
				throw new PermissionsError( 'newsletter-restore' );
			}
			$store = NewsletterStore::getDefaultInstance();
			$rows = $store->newsletterExistsForMainPage( $newsletter->getPageId() );

			foreach ( $rows as $row ) {
				if ( (int)$row->nl_main_page_id === $newsletter->getPageId() && (int)$row->nl_active === 1 ) {
					throw new ErrorPageError( 'newsletter-mainpage-in-use','newsletter-mainpage-in-use' );
				}
			}
			$success = $store->restoreNewsletter( $newsletterName );
			if ( $success ) {
				return true;
			}
		}
		// Show error message and allow resubmitting in case of failure
		return Status::newFatal( 'newsletter-restore-failure', $newsletterName );
	}

		/**
		 * @param Title $title
		 * @param Title $newtitle
		 * @param User $user
		 * @return bool
		 */
		public static function onTitleMove( Title $title, Title $newtitle, User $user ) {
			if ( $newtitle->inNamespace( NS_NEWSLETTER ) ) {
				$newsletter = Newsletter::newFromName( $title->getText() );
				if ( $newsletter ) {
					NewsletterStore::getDefaultInstance()->updateName( $newsletter->getId(), $newtitle->getText() );
				} else {
					throw new MWException( 'Cannot find newsletter with name \"' + $title->getText() + '\"' );
				}
			}
			return true;
		}
}
