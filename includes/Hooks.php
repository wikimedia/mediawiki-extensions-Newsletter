<?php

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\Newsletter;

use Article;
use Content;
use EchoUserLocator;
use IContextSource;
use MediaWiki\Content\Hook\ContentModelCanBeUsedOnHook;
use MediaWiki\Extension\Newsletter\Content\NewsletterContent;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterPublisherAddedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterPublisherRemovedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterSubscribedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterUnsubscribedPresentationModel;
use MediaWiki\Extension\Newsletter\Notifications\EchoNewsletterUserLocator;
use MediaWiki\Hook\CustomEditorHook;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Hook\TitleMoveHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleDeleteHook;
use MediaWiki\Page\Hook\PageUndeleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use PermissionsError;
use ReadOnlyError;
use RuntimeException;
use SkinTemplate;
use StatusValue;
use ThrottledError;
use WikiPage;

/**
 * Class to add Hooks used by Newsletter.
 */
class Hooks implements
	LoginFormValidErrorMessagesHook,
	CustomEditorHook,
	ArticleDeleteHook,
	PageUndeleteHook,
	TitleMoveHook,
	ContentModelCanBeUsedOnHook,
	EditFilterMergedContentHook,
	SkinTemplateNavigation__UniversalHook,
	GetUserPermissionsErrorsHook
{

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
	public function onLoginFormValidErrorMessages( array &$messages ) {
		// on Special:Newsletter/id/subscribe
		$messages[] = 'newsletter-subscribe-loginrequired';
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
	 * @param Article $article
	 * @param User $user
	 * @return bool
	 * @throws ReadOnlyError
	 */
	public function onCustomEditor( $article, $user ) {
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
	 * @param string &$reason
	 * @param string &$error
	 * @param Status &$status
	 * @param bool $suppress
	 * @throws PermissionsError
	 */
	public function onArticleDelete(
		WikiPage $wikiPage,
		User $user,
		&$reason,
		&$error,
		Status &$status,
		$suppress
	) {
		if ( !$wikiPage->getTitle()->inNamespace( NS_NEWSLETTER ) ) {
			return;
		}
		$newsletter = Newsletter::newFromName( $wikiPage->getTitle()->getText() );
		if ( $newsletter ) {
			if ( !$newsletter->canDelete( $user ) ) {
				throw new PermissionsError( 'newsletter-delete' );
			}
			NewsletterStore::getDefaultInstance()->deleteNewsletter( $newsletter );
		}
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
	public function onPageUndelete(
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
		} elseif ( !$title->exists() ) {
			// If the title exists, then there's no reason to block the undeletion
			// whatever you are doing is probably a bad idea, but won't cause any inconsistencies
			// since it will attach the disconnected revisions to the existing page
			$status->fatal( 'newsletter-orphan-revisions' );
			return false;
		}
	}

	/**
	 * @param Title $title
	 * @param Title $newtitle
	 * @param User $user
	 * @param string $reason
	 * @param Status &$status
	 */
	public function onTitleMove(
		Title $title,
		Title $newtitle,
		User $user,
		$reason,
		Status &$status
	) {
		if ( $newtitle->inNamespace( NS_NEWSLETTER ) ) {
			$newsletter = Newsletter::newFromName( $title->getText() );
			if ( $newsletter ) {
				NewsletterStore::getDefaultInstance()->updateName( $newsletter->getId(), $newtitle->getText() );
			} else {
				throw new RuntimeException( 'Cannot find newsletter with name \"' . $title->getText() . '\"' );
			}
		}
	}

	/**
	 * Enforce the invariant that all pages in the Newsletter namespace
	 * correspond to an actual newsletter in the database by preventing
	 * any other content models from being used there.
	 * @param string $contentModel ID of the content model in question
	 * @param Title $title the Title in question.
	 * @param bool &$ok Output parameter, whether it is OK to use $contentModel on $title.
	 */
	public function onContentModelCanBeUsedOn( $contentModel, $title, &$ok ) {
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
	public function onEditFilterMergedContent(
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
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void {
		if ( $skinTemplate->getTitle()->inNamespace( NS_NEWSLETTER ) ) {
			unset( $links['views']['viewsource'] );
		}
	}

	/**
	 * @param Title $title The title that permissions are being checked for
	 * @param User $user The User object representing the user who is attempting to perform the action
	 * @param string $action The action attempting to be performed
	 * @param string &$result Output parameter, set to a string to signify that the action isn't allowed
	 * @return bool
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
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
