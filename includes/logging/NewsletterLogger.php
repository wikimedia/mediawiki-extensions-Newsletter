<?php

/**
 * @license GNU GPL v2+
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
		$log->setTarget( SpecialPage::getTitleFor( 'Newsletter', $id ) );
		$log->setParameters( [
			'4:newsletter-link:nl_id' => "$id:{$newsletter->getName()}"
		] );
		$log->setRelations( [ 'nl_id' => $id ] );
		$log->publish( $log->insert() );
	}

	public function logNewIssue( User $publisher, Newsletter $newsletter, Title $issueTitle,
	                             $issueId, $comment ) {
		$log = new ManualLogEntry( 'newsletter', 'issue-added' );
		$log->setPerformer( $publisher );
		$log->setTarget( SpecialPage::getTitleFor( 'Newsletter', $newsletter->getId() ) );
		$log->setParameters( [
			'4:newsletter-link:nl_id' => "{$newsletter->getId()}:{$newsletter->getName()}",
			'5::nli_issue_id' => $issueId,
			'6::nl_issue_title' => $issueTitle->getPrefixedText(),
		] );
		$log->setComment( $comment );
		$log->setRelations( [ 'nl_id' => $newsletter->getId() ] );
		$log->publish( $log->insert() );
	}

}
