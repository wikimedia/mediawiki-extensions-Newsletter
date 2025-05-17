<?php

namespace MediaWiki\Extension\Newsletter\Logging;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @license GPL-2.0-or-later
 * @author Tyler Romeo
 * @author Addshore
 */
class NewsletterLogger {

	public function logPublisherAdded( Newsletter $newsletter, User $user ) {
		$log = new ManualLogEntry( 'newsletter', 'publisher-added' );
		$log->setPerformer( RequestContext::getMain()->getUser() );
		$log->setTarget( $user->getUserPage() );
		$log->setParameters( [
			'4:newsletter-link:nl_id' => "{$newsletter->getId()}:{$newsletter->getName()}"
		] );
		$log->setRelations( [ 'nl_id' => $newsletter->getId() ] );
		$log->publish( $log->insert() );
	}

	public function logPublisherRemoved( Newsletter $newsletter, User $user ) {
		$log = new ManualLogEntry( 'newsletter', 'publisher-removed' );
		$log->setPerformer( RequestContext::getMain()->getUser() );
		$log->setTarget( $user->getUserPage() );
		$log->setParameters( [
			'4:newsletter-link:nl_id' => "{$newsletter->getId()}:{$newsletter->getName()}"
		] );
		$log->setRelations( [ 'nl_id' => $newsletter->getId() ] );
		$log->publish( $log->insert() );
	}

	public function logNewsletterAdded( Newsletter $newsletter ) {
		$id = $newsletter->getId();
		$log = new ManualLogEntry( 'newsletter', 'newsletter-added' );
		$log->setPerformer( RequestContext::getMain()->getUser() );
		$log->setTarget( SpecialPage::getTitleFor( 'Newsletter', (string)$id ) );
		$log->setParameters( [
			'4:newsletter-link:nl_id' => "$id:{$newsletter->getName()}"
		] );
		$log->setRelations( [ 'nl_id' => $id ] );
		$log->publish( $log->insert() );
	}

	/**
	 * @param User $publisher
	 * @param Newsletter $newsletter
	 * @param Title $issueTitle
	 * @param int $issueId
	 * @param string $comment
	 */
	public function logNewIssue(
		User $publisher,
		Newsletter $newsletter,
		Title $issueTitle,
		$issueId,
		$comment
	) {
		$log = new ManualLogEntry( 'newsletter', 'issue-added' );
		$log->setPerformer( $publisher );
		$log->setTarget( SpecialPage::getTitleFor( 'Newsletter', (string)$newsletter->getId() ) );
		$log->setParameters( [
			'4:newsletter-link:nl_id' => "{$newsletter->getId()}:{$newsletter->getName()}",
			'5::nli_issue_id' => $issueId,
			'6::nl_issue_title' => $issueTitle->getFullText()
		] );
		$log->setComment( $comment );
		$log->setRelations( [ 'nl_id' => $newsletter->getId() ] );
		$log->publish( $log->insert() );
	}

}
