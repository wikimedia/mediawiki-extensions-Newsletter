<?php

/**
 * Class representing a newsletter
 *
 * @license GNU GPL v2+
 * @author Adam Shorland
 */
class Newsletter {

	private $id;
	private $name;
	private $description;
	private $pageId;
	private $frequency;
	private $ownerId;

	/**
	 * @param int|null $id
	 * @param string $name
	 * @param string $description
	 * @param int $pageId
	 * @param string $frequency
	 * @param int $ownerId
	 */
	public function __construct( $id, $name, $description, $pageId, $frequency, $ownerId ) {
		$this->id = $id;
		$this->name = $name;
		$this->description = $description;
		$this->pageId = $pageId;
		$this->frequency = $frequency;
		$this->ownerId = $ownerId;
	}

	/**
	 * @return int|null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @return int
	 */
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * @return string
	 */
	public function getFrequency() {
		return $this->frequency;
	}

	/**
	 * @return int
	 */
	public function getOwnerId() {
		return $this->ownerId;
	}

}
