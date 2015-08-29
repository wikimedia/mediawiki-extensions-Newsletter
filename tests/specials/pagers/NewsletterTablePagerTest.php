<?php

/**
 * @covers NewsletterTablePager
 *
 * @author Addshore
 */
class NewsletterTablePagerTest extends PHPUnit_Framework_TestCase {

	private $mockSeekCounter;

	/**
	 * @param stdClass[] $resultObjects
	 *
	 * @return PHPUnit_Framework_MockObject_MockObject|IDatabase
	 */
	private function getMockDatabase( array $resultObjects ) {
		$testCase = $this;
		$mockResult = $this->getMockBuilder( 'ResultWrapper' )
			->disableOriginalConstructor()
			->getMock();
		$mockResult->expects( $this->atLeastOnce() )
			->method( 'numRows' )
			->will( $this->returnValue( count( $resultObjects ) ) );
		$mockResult->expects( $this->any() )
			->method( 'seek' )
			->will( $this->returnCallback( function ( $seekTo ) use ( $testCase ) {
				$testCase->mockSeekCounter = $seekTo;
			} ) );
		$mockResult->expects( $this->any() )
			->method( 'fetchObject' )
			->will( $this->returnCallback( function () use ( $testCase, $resultObjects ) {
				if ( array_key_exists( $testCase->mockSeekCounter, $resultObjects ) ) {
					$obj = $resultObjects[$testCase->mockSeekCounter];
					$testCase->mockSeekCounter =+ 1;
					return $obj;
				}
				return false;
			} ) );
		$mockDb = $this->getMock( 'IDatabase' );
		$mockDb->expects( $this->atLeastOnce() )
			->method( 'select' )
			->will( $this->returnValue( $mockResult ) );
		return $mockDb;
	}

	private function arrayToObject( array $array ) {
		$obj = new stdClass();
		foreach ( $array as $key => $value ) {
			$obj->$key = $value;
		}
		return $obj;
	}

	private function getRowObject( $id, $name, $desc, $subscribers, $currentUserSubscribed ) {
		return $this->arrayToObject(
			array(
				'nl_id' => $id,
				'nl_name' => $name,
				'nl_desc' => $desc,
				'subscribers' => $subscribers,
				'current_user_subscribed' => $currentUserSubscribed,
			)
		);
	}

	public function provideTestTablePager() {
		$entryOneStrings = array(
			'<td class="TablePager_col_nl_name"><a href="#">Foo</a></td>',
			'<td class="TablePager_col_nl_desc">Bar</td>',
			'<td class="TablePager_col_subscriber_count"><input readonly="" id="newsletter-1" value="12" /></td>',
			'<input type="radio" name="nl_id-1" value="subscribe" checked="" />Yes',
			'<input type="radio" name="nl_id-1" value="unsubscribe" />No',
		);
		$entryTwoStrings = array(
			'<td class="TablePager_col_nl_name"><a href="#">SecondName</a></td>',
			'<td class="TablePager_col_nl_desc">SecondDesc</td>',
			'<td class="TablePager_col_subscriber_count"><input readonly="" id="newsletter-2" value="555" /></td>',
			'<input type="radio" name="nl_id-2" value="subscribe" />Yes',
			'<input type="radio" name="nl_id-2" value="unsubscribe" checked="" />No',
		);

		return array(
			array(
				array(),
				array( 'No results' ),
			),
			array(
				array(
					$this->getRowObject( 1, 'Foo', 'Bar', 12, true ),
				),
				$entryOneStrings,
			),
			array(
				array(
					$this->getRowObject( 1, 'Foo', 'Bar', 12, true ),
					$this->getRowObject( 2, 'SecondName', 'SecondDesc', 555, false ),
				),
				array_merge( $entryOneStrings, $entryTwoStrings ),
			),
		);
	}

	/**
	 * @dataProvider provideTestTablePager
	 */
	public function testStuff( array $dbObjects, array $expectedRegexMatches ) {
		$pager = new NewsletterTablePager( null, $this->getMockDatabase( $dbObjects ) );

		$text = $pager->getFullOutput()->getText();

		foreach ( $expectedRegexMatches as $expectedMatch ) {
			$this->assertContains( $expectedMatch, $text );
		}
	}

}
