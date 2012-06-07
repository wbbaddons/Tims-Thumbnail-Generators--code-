<?php
namespace wcf\system\event\listener;

/**
 * Generates thumbnails for sourcecode.
 *
 * @author 	Tim Düsterhus
 * @copyright	2012 Tim Düsterhus
 * @license	Creative Commons Attribution-NonCommercial-ShareAlike <http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode>
 * @package	be.bastelstu.wcf.thumbnailGenerators.code
 * @subpackage	system.event.listener
 */
class TimsThumbnailsCodeListener implements \wcf\system\event\IEventListener {
	private $eventObj = null;
	
	/**
	 * @see	\wcf\system\event\IEventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		$this->eventObj = $eventObj;
		switch ($eventName) {
			case 'checkThumbnail':
			case 'generateThumbnail':
				$this->$eventName();
			default:
				return;
		}
	}
	
	/**
	 * Registers the files for thumbnail-creation
	 */
	public function checkThumbnail() {
		// TODO: Match the correct files
	}
	
	/**
	 * Actually generate the thumbnail.
	 */
	public function generateThumbnail() {
		// someone else already grabbed this one
		if (count($this->eventObj->eventData)) return;
		
		// TODO: Match the correct files
		if (true) return;
		
		// load data
		$tinyAdapter = \wcf\system\image\ImageHandler::getInstance()->getAdapter();
		$adapter = \wcf\system\image\ImageHandler::getInstance()->getAdapter();
		$file = file($this->eventObj->eventAttachment->getLocation());
		
		// initialize our drawing sheeps
		$tinyAdapter->createEmptyImage(144, 144);
		$adapter->createEmptyImage(ATTACHMENT_THUMBNAIL_WIDTH, ATTACHMENT_THUMBNAIL_HEIGHT);
		$tinyAdapter->setColor(0x00, 0x00, 0x00);
		$adapter->setColor(0x00, 0x00, 0x00);
		
		// TODO: Draw the picture
		
		// and create the images
		$tinyThumbnailLocation = $this->eventObj->eventAttachment->getTinyThumbnailLocation();
		$thumbnailLocation = $this->eventObj->eventAttachment->getThumbnailLocation();
		
		$tinyAdapter->writeImage($tinyThumbnailLocation.'.png');
		rename($tinyThumbnailLocation.'.png', $tinyThumbnailLocation);
		$adapter->writeImage($thumbnailLocation.'.png');
		rename($thumbnailLocation.'.png', $thumbnailLocation);
		
		// calculate the thumbnail data
		$updateData = array();
		if (file_exists($tinyThumbnailLocation) && ($imageData = @getImageSize($tinyThumbnailLocation)) !== false) {
			$updateData['tinyThumbnailType'] = $imageData['mime'];
			$updateData['tinyThumbnailSize'] = @filesize($tinyThumbnailLocation);
			$updateData['tinyThumbnailWidth'] = $imageData[0];
			$updateData['tinyThumbnailHeight'] = $imageData[1];
		}
		
		if (file_exists($thumbnailLocation) && ($imageData = @getImageSize($thumbnailLocation)) !== false) {
			$updateData['thumbnailType'] = $imageData['mime'];
			$updateData['thumbnailSize'] = @filesize($thumbnailLocation);
			$updateData['thumbnailWidth'] = $imageData[0];
			$updateData['thumbnailHeight'] = $imageData[1];
		}
		
		$this->eventObj->eventData = $updateData;
	}
}
