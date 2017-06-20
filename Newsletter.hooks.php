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
		$notificationCategories['newsletter'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-newsletter',
		];

		$notifications['newsletter-announce'] = [
			'category' => 'newsletter',
			'section' => 'alert',
			'primary-link' => [
				'message' => 'newsletter-notification-link-text-new-issue',
				'destination' => 'new-issue'
			],
			'secondary-link' => [
				'message' => 'newsletter-notification-link-text-view-newsletter',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				'EchoNewsletterUserLocator::locateNewsletterSubscribedUsers',
			],
			'presentation-model' => 'EchoNewsletterPresentationModel',
			'title-message' => 'newsletter-notification-title',
			'title-params' => [ 'newsletter-name', 'title', 'agent', 'user' ],
			'flyout-message' => 'newsletter-notification-flyout',
			'flyout-params' => [ 'newsletter-name', 'agent', 'user' ],
			'payload' => [ 'summary' ],
			'email-subject-message' => 'newsletter-email-subject',
			'email-subject-params' => [ 'newsletter-name' ],
			'email-body-batch-message' => 'newsletter-email-batch-body',
			'email-body-batch-params' =>  [ 'newsletter-name', 'agent', 'user' ],
		];

		$notifications['newsletter-newpublisher'] = [
			'category' => 'newsletter',
			'primary-link' => [
				'message' => 'newsletter-notification-link-text-new-publisher',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				[ 'EchoUserLocator::locateFromEventExtra', [ 'new-publishers-id' ] ]
			],
			'presentation-model' => 'EchoNewsletterPublisherPresentationModel',
			'title-message' => 'newsletter-notification-new-publisher-title',
			'title-params' => [ 'newsletter-name', 'agent' ],
			'flyout-message' => 'newsletter-notification-new-publisher-flyout',
			'flyout-params' => [ 'newsletter-name', 'agent' ],
		];
		$notifications['newsletter-subscribed'] = [
			'category' => 'newsletter',
			'primary-link' => [
				'message' => 'newsletter-notification-subscribed',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				[ 'EchoUserLocator::locateFromEventExtra', [ 'new-subscribers-id' ] ]
			],
			'presentation-model' => 'EchoNewsletterSubscribedPresentationModel',
			'title-message' => 'newsletter-notification-subscribed',
			'title-params' => [ 'newsletter-name' ],
		];
		$notifications['newsletter-unsubscribed'] = [
			'category' => 'newsletter',
			'primary-link' => [
				'message' => 'newsletter-notification-unsubscribed',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				[ 'EchoUserLocator::locateFromEventExtra', [ 'removed-subscribers-id' ] ]
			],
			'presentation-model' => 'EchoNewsletterUnsubscribedPresentationModel',
			'title-message' => 'newsletter-notification-unsubscribed',
			'title-params' => [ 'newsletter-name' ],
		];

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
		$updater->addExtensionField( 'nl_newsletters', 'nl_active',
			__DIR__ . '/sql/nl_newsletters-add-active.sql' );
		$updater->dropExtensionIndex( 'nl_newsletters', 'nl_main_page_id',
			__DIR__ . '/sql/nl_main_page_id-drop-index.sql' );
		$updater->addExtensionIndex( 'nl_newsletters', 'nl_main_page_active',
			__DIR__ . '/sql/nl_newsletters-add-unique.sql' );
		$updater->addExtensionField( 'nl_newsletters', 'nl_subscriber_count',
			__DIR__ . '/sql/nl_newsletters-add-subscriber_count.sql' );
		$updater->dropExtensionIndex( 'nl_newsletters', 'nl_active_name',
			__DIR__ . '/sql/nl_newsletter-drop-nl-active_name.sql' );
		$updater->addExtensionIndex( 'nl_newsletters', 'nl_active_subscriber_name',
			__DIR__ . '/sql/nl_newsletter-add-nl_active_subscriber_name.sql' );

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
		$ourFiles = [];
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
		$updateFields[] = [ 'nl_publishers', 'nlp_publisher_id' ];
		$updateFields[] = [ 'nl_subscriptions', 'nls_subscriber_id' ];

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
	public static function onArticleDelete(
		&$wikiPage,
		&$user,
		&$reason,
		&$error,
		Status &$status,
		$suppress
	) {
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
					throw new ErrorPageError( 'newsletter-mainpage-in-use', 'newsletter-mainpage-in-use-title' );
				}
			}
			$success = $store->restoreNewsletter( $newsletterName );
			if ( $success ) {
				return true;
			}
		}
		// Throw error message
		throw new ErrorPageError(
			'newsletter-restore-failure-title',
			wfMessage( 'newsletter-restore-failure', $newsletterName )
		);
		return true;
	}

	/**
	 * @param Title $title
	 * @param Title $newtitle
	 * @param User $user
	 * @return bool
	 * @throws MWException
	 */
	public static function onTitleMove( Title $title, Title $newtitle, User $user ) {
		if ( $newtitle->inNamespace( NS_NEWSLETTER ) ) {
			$newsletter = Newsletter::newFromName( $title->getText() );
			if ( $newsletter ) {
				NewsletterStore::getDefaultInstance()->updateName( $newsletter->getId(), $newtitle->getText() );
			} else {
				throw new MWException( 'Cannot find newsletter with name \"' . $title->getText() . '\"' );
			}
		}
		return true;
	}

	/**
	 * @param string $contentModel ID of the content model in question
	 * @param Title $title the Title in question.
	 * @param $ok Output parameter, whether it is OK to use $contentModel on $title.
	 * @return bool
	 */
	public static function onContentModelCanBeUsedOn( $contentModel, Title $title, &$ok ) {
		if ( $title->inNamespace( NS_NEWSLETTER ) && $contentModel != 'NewsletterContent' ) {
			$ok = false;
		} elseif ( !$title->inNamespace( NS_NEWSLETTER ) && $contentModel == 'NewsletterContent' ) {
			$ok = false;
		}
		return true;
	}

	/**
	 * @param RequestContext $context object implementing the IContextSource interface.
	 * @param Content $content content of the edit box, as a Content object.
	 * @param Status $status Status object to represent errors, etc.
	 * @param $summary string Edit summary for page
	 * @param User $user the User object representing the user whois performing the edit.
	 * @param $minoredit bool whether the edit was marked as minor by the user.
	 * @return bool
	 * @throws ThrottledError
	 */
	public static function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) {
		global $wgUser;
		if ( !$context->getTitle()->inNamespace( NS_NEWSLETTER ) ) {
			return;
		}
		if ( !$context->getTitle()->hasContentModel( 'NewsletterContent' ) ||
			( !$content instanceof NewsletterContent )
		) {
			return;
		}
		if ( $wgUser->pingLimiter( 'newsletter' ) ) {
			// Default user access level for creating a newsletter is quite low
			// so add a throttle here to prevent abuse (eg. mass vandalism spree)
			throw new ThrottledError;
		}
		$newsletter = Newsletter::newFromName( $context->getTitle()->getText() );

		// Validate API Edit parameters
		$formData = [
			'Name' => $context->getTitle()->getText(),
			'Description' => $content->getDescription(),
			'MainPage' => $content->getMainPage(),
		];
		$validator = new NewsletterValidator( $formData );
		$validation = $validator->validate( !$newsletter );
		if ( !$validation->isGood() ) {
			// Invalid input was entered
			return false;
		}
		$mainPageId = $content->getMainPage()->getArticleID();
		$store = NewsletterStore::getDefaultInstance();
		if ( !$newsletter || ( $newsletter && $newsletter->getPageId() !== $mainPageId ) ) {
			$rows = $store->newsletterExistsForMainPage( $mainPageId );
			foreach ( $rows as $row ) {
				if ( (int)$row->nl_main_page_id === $mainPageId && (int)$row->nl_active === 1 ) {
					$status->newFatal( 'newsletter-mainpage-in-use' );
					return false;
				}
			}
		}

		return true;
	}
}
