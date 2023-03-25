<?php
namespace MediaWiki\Extension\Newsletter;

use Article;
use Content;
use DatabaseUpdater;
use EchoUserLocator;
use EditPage;
use IContextSource;
use MediaWiki\Extension\Newsletter\Content\NewsletterContent;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterPublisherAddedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterPublisherRemovedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterSubscribedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterUnsubscribedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterUserLocator;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MWException;
use PermissionsError;
use ReadOnlyError;
use SkinTemplate;
use SpecialPage;
use Status;
use StatusValue;
use ThrottledError;
use Title;
use User;
use WikiPage;

/**
 * Class to add Hooks used by Newsletter.
 */
class Hooks {

	/**
	 * Function to be called before EchoEvent
	 *
	 * @param array[] &$notifications Echo notifications
	 * @param array[] &$notificationCategories Echo notification categories
	 */
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories ) {
		$notificationCategories['newsletter'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-newsletter',
		];

		$notifications['newsletter-announce'] = [
			'category' => 'newsletter',
			'section' => 'message',
			'primary-link' => [
				'message' => 'newsletter-notification-link-text-new-issue',
				'destination' => 'new-issue'
			],
			'secondary-link' => [
				'message' => 'newsletter-notification-link-text-view-newsletter',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				[ [ EchoNewsletterUserLocator::class, 'locateNewsletterSubscribedUsers' ] ],
			],
			'canNotifyAgent' => true,
			'presentation-model' => EchoNewsletterPresentationModel::class,
			'title-message' => 'newsletter-notification-title',
			'title-params' => [ 'newsletter-name', 'title', 'agent', 'user' ],
			'flyout-message' => 'newsletter-notification-flyout',
			'flyout-params' => [ 'newsletter-name', 'agent', 'user' ],
			'payload' => [ 'summary' ],
			'email-subject-message' => 'newsletter-email-subject',
			'email-subject-params' => [ 'newsletter-name' ],
			'email-body-batch-message' => 'newsletter-email-batch-body',
			'email-body-batch-params' => [ 'newsletter-name', 'agent', 'user' ],
		];

		$notifications['newsletter-newpublisher'] = [
			'category' => 'newsletter',
			'primary-link' => [
				'message' => 'newsletter-notification-link-text-new-publisher',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				[ [ EchoUserLocator::class, 'locateFromEventExtra' ], [ 'new-publishers-id' ] ]
			],
			'presentation-model' => EchoNewsletterPublisherAddedPresentationModel::class,
			'title-message' => 'newsletter-notification-new-publisher-title',
			'title-params' => [ 'newsletter-name', 'agent' ],
			'flyout-message' => 'newsletter-notification-new-publisher-flyout',
			'flyout-params' => [ 'newsletter-name', 'agent' ],
		];
		$notifications['newsletter-delpublisher'] = [
			'category' => 'newsletter',
			'primary-link' => [
				'message' => 'newsletter-notification-link-text-del-publisher',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				[ [ EchoUserLocator::class, 'locateFromEventExtra' ], [ 'del-publishers-id' ] ]
			],
			'presentation-model' => EchoNewsletterPublisherRemovedPresentationModel::class,
			'title-message' => 'newsletter-notification-del-publisher-title',
			'title-params' => [ 'newsletter-name', 'agent' ],
			'flyout-message' => 'newsletter-notification-del-publisher-flyout',
			'flyout-params' => [ 'newsletter-name', 'agent' ],
		];
		$notifications['newsletter-subscribed'] = [
			'category' => 'newsletter',
			'primary-link' => [
				'message' => 'newsletter-notification-subscribed',
				'destination' => 'newsletter'
			],
			'user-locators' => [
				[ [ EchoUserLocator::class, 'locateFromEventExtra' ], [ 'new-subscribers-id' ] ]
			],
			'presentation-model' => EchoNewsletterSubscribedPresentationModel::class,
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
				[ [ EchoUserLocator::class, 'locateFromEventExtra' ], [ 'removed-subscribers-id' ] ]
			],
			'presentation-model' => EchoNewsletterUnsubscribedPresentationModel::class,
			'title-message' => 'newsletter-notification-unsubscribed',
			'title-params' => [ 'newsletter-name' ],
		];
	}

	/**
	 * Allows to add our own error message to LoginForm
	 *
	 * @param array &$messages
	 */
	public static function onLoginFormValidErrorMessages( &$messages ) {
		// on Special:Newsletter/id/subscribe
		$messages[] = 'newsletter-subscribe-loginrequired';
	}

	/**
	 * Add tables to Database
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$type = $updater->getDB()->getType();
		$updater->addExtensionTable( 'nl_newsletters', __DIR__ . '/../sql/' . $type . '/tables-generated.sql' );
	}

	/**
	 * Tables that Extension:UserMerge needs to update
	 *
	 * @param array &$updateFields
	 */
	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = [ 'nl_publishers', 'nlp_publisher_id' ];
		$updateFields[] = [ 'nl_subscriptions', 'nls_subscriber_id' ];
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
	 * @param WikiPage &$wikiPage
	 * @param User &$user
	 * @param string &$reason
	 * @param string &$error
	 * @param Status &$status
	 * @param bool $suppress
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
			NewsletterStore::getDefaultInstance()->deleteNewsletter( $newsletter );
			$status->setOK( true );
		}
		return false;
	}

	/**
	 * @param ProperPageIdentity $page
	 * @param Authority $performer
	 * @param string $reason
	 * @param bool $unsuppress
	 * @param array $timestamps
	 * @param array $fileVersions
	 * @param StatusValue $status
	 * @return bool|void
	 */
	public static function onPageUndelete(
		ProperPageIdentity $page,
		Authority $performer,
		string $reason,
		bool $unsuppress,
		array $timestamps,
		array $fileVersions,
		StatusValue $status
	) {
		$title = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $page )->getTitle();
		if ( !$title->inNamespace( NS_NEWSLETTER ) ) {
			return;
		}
		$newsletterName = $title->getText();
		$newsletter = Newsletter::newFromName( $newsletterName, false );
		if ( $newsletter ) {
			if ( !$newsletter->canRestore( $performer ) ) {
				$status->merge( User::newFatalPermissionDeniedStatus( 'newsletter-restore' ) );
				return false;
			}
			$store = NewsletterStore::getDefaultInstance();
			$rows = $store->newsletterExistsForMainPage( $newsletter->getPageId() );
			foreach ( $rows as $row ) {
				if ( (int)$row->nl_main_page_id === $newsletter->getPageId() && (int)$row->nl_active === 1 ) {
					$status->fatal( 'newsletter-mainpage-in-use' );
					return false;
				}
			}
			$store->restoreNewsletter( $newsletterName );
		}
	}

	/**
	 * @param Title $title
	 * @param Title $newtitle
	 * @param User $user
	 * @param string $reason
	 * @param Status $status
	 * @throws MWException
	 */
	public static function onTitleMove(
		Title $title,
		Title $newtitle,
		User $user,
		$reason,
		Status $status
	) {
		if ( $newtitle->inNamespace( NS_NEWSLETTER ) ) {
			$newsletter = Newsletter::newFromName( $title->getText() );
			if ( $newsletter ) {
				NewsletterStore::getDefaultInstance()->updateName( $newsletter->getId(), $newtitle->getText() );
			} else {
				throw new MWException( 'Cannot find newsletter with name \"' . $title->getText() . '\"' );
			}
		}
	}

	/**
	 * @param string $contentModel ID of the content model in question
	 * @param Title $title the Title in question.
	 * @param bool &$ok Output parameter, whether it is OK to use $contentModel on $title.
	 */
	public static function onContentModelCanBeUsedOn( $contentModel, Title $title, &$ok ) {
		if ( $title->inNamespace( NS_NEWSLETTER ) && $contentModel != 'NewsletterContent' ) {
			$ok = false;
		} elseif ( !$title->inNamespace( NS_NEWSLETTER ) && $contentModel == 'NewsletterContent' ) {
			$ok = false;
		}
	}

	/**
	 * @param IContextSource $context object implementing the IContextSource interface.
	 * @param Content $content content of the edit box, as a Content object.
	 * @param Status $status Status object to represent errors, etc.
	 * @param string $summary Edit summary for page
	 * @param User $user the User object representing the user who is performing the edit.
	 * @param bool $minoredit whether the edit was marked as minor by the user.
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
		if ( !$context->getTitle()->inNamespace( NS_NEWSLETTER ) ) {
			return true;
		}
		if ( !$context->getTitle()->hasContentModel( 'NewsletterContent' ) ||
			( !$content instanceof NewsletterContent )
		) {
			return true;
		}
		if ( $user->pingLimiter( 'newsletter' ) ) {
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
			$status->merge( $validation );
			// Invalid input was entered
			return false;
		}
		$mainPageId = $content->getMainPage()->getArticleID();
		$store = NewsletterStore::getDefaultInstance();
		if ( !$newsletter || $newsletter->getPageId() !== $mainPageId ) {
			$rows = $store->newsletterExistsForMainPage( $mainPageId );
			foreach ( $rows as $row ) {
				if ( (int)$row->nl_main_page_id === $mainPageId && (int)$row->nl_active === 1 ) {
					$status->fatal( 'newsletter-mainpage-in-use' );
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Hide the View Source tab in the Newsletter namespace for users who do not have any
	 * view permission ('newsletter-*')
	 *
	 * @param SkinTemplate $skinTemplate The skin template on which the UI is built.
	 * @param array &$links Navigation links.
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ) {
		if ( $skinTemplate->getTitle()->inNamespace( NS_NEWSLETTER ) ) {
			unset( $links['views']['viewsource'] );
		}
	}

	/**
	 * @param Title $title The title that permissions are being checked for
	 * @param User $user The User object representing the user who is attempting to perform the action
	 * @param string $action The action attempting to be performed
	 * @param bool &$result Output parameter, set to a string to signify that the action isn't allowed
	 * @return bool
	 */
	public static function onGetUserPermissionsErrors( Title $title, User $user, $action, &$result ) {
		if ( !$title->inNamespace( NS_NEWSLETTER ) ) {
			return true;
		}
		if ( $action === 'edit' ) {
			if ( $title->exists() ) {
				$newsletter = Newsletter::newFromName( $title->getText() );
				if ( !$newsletter->canManage( $user ) ) {
					// This case can only trigger when using the API - the UI won't display an edit form at all
					$result = "newsletter-api-error-nopermissions";
					return false;
				}
			}
		} elseif ( $action === 'create' ) {
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			if ( !$permissionManager->userHasRight( $user, 'newsletter-create' ) ) {
				// This case can only trigger when using the API - the UI will display the standard
				// "The action you have requested is limited to users in the group <groupnames>" error
				$result = "newsletter-api-error-nocreate";
				return false;
			}
		}
	}
}
