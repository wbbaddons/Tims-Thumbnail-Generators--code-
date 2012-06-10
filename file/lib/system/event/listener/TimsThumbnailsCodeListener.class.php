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
	private $colors = array(0x000000);
	
	/**
	 * @see	\wcf\system\event\IEventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		// imagick is currently not supported
		if (IMAGE_ADAPTER_TYPE == 'imagick') return;
		
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
		switch ($this->eventObj->eventAttachment->fileType) {
			case 'text/x-php':
				$this->eventObj->eventData['hasThumbnail'] = true;
			default:
				return;
		}
	}
	
	/**
	 * Actually generate the thumbnail.
	 */
	public function generateThumbnail() {
		// someone else already grabbed this one
		if (count($this->eventObj->eventData)) return;
		
		switch ($this->eventObj->eventAttachment->fileType) {
			// TODO: Filter by extension as well, java is recognized as text/x-c++
			case 'text/x-php':
				$className = '\wcf\system\bbcode\highlighter\PhpHighlighter';
			break;
			case 'text/x-c++':
				$className = '\wcf\system\bbcode\highlighter\CHighlighter';
			break;
			default:
				return;
		}
		
		$code = $className::getInstance()->highlight(file_get_contents($this->eventObj->eventAttachment->getLocation()));
		// remove empty tags, shorten closing tags, replace tabs with 4 spaces
		$code = str_replace(array('<span>', '</span>', '	'), array('', '<>', '    '), $code);
		// Regex to remove everything unneeded from tags
		$regex = new \wcf\system\Regex('<span class="hl([^"]*)">');
		$code = $regex->replace($result, '<\1>');
		
		// split code at newlines
		$code = explode("\n", \wcf\util\StringUtil::unifyNewlines($result));
		
		// load data
		$tinyAdapter = \wcf\system\image\ImageHandler::getInstance()->getAdapter();
		$adapter = \wcf\system\image\ImageHandler::getInstance()->getAdapter();
		$file = file($this->eventObj->eventAttachment->getLocation());
		
		$tinyAdapter->createEmptyImage(144, 144);
		$adapter->createEmptyImage(ATTACHMENT_THUMBNAIL_WIDTH, ATTACHMENT_THUMBNAIL_HEIGHT);
		$tinyAdapter->setColor(0x00, 0x00, 0x00);
		$adapter->setColor(0x00, 0x00, 0x00);
		
		$y = 0;
		$x = 5;
		// and parse our code
		foreach ($result as $line) {
			// operate on the line
			while ($line != '') {
				// search the next tag
				$nextTag = strpos($line, '<');
				
				// only text remains, move the nextTag behind the line
				if ($nextTag === false) {
					$nextTag = strlen($line);
				}
				
				// nextTag is at the beginning of the line
				if ($nextTag === 0) {
					// Back to old color
					if (substr($line, 0, 2) == '<>') {
						$line = substr($line, 2);
						if (count($this->colors) > 1) array_pop($this->colors);
					}
					// add a new color to our stack
					else {
						$nextTagEnd = strpos($line, '>');
						$tag = substr($line, 1, $nextTagEnd);
						$line = substr($line, $nextTagEnd + 1);
						
						// TODO: Use "normal" colors
						array_push($this->colors, hexdec(substr(md5($tag), 12, 6)));
					}
				}
				else {
					$text = substr($line, 0, $nextTag);
					$text = \wcf\util\StringUtil::decodeHTML($text);
					$line = substr($line, $nextTag);
					
					// set color
					$tinyAdapter->setColor((end($this->colors) >> 16 & 0xFF), (end($this->colors) >> 8 & 0xFF), (end($this->colors) >> 0 & 0xFF));
					$adapter->setColor((end($this->colors) >> 16 & 0xFF), (end($this->colors) >> 8 & 0xFF), (end($this->colors) >> 0 & 0xFF));
					
					// draw text
					$tinyAdapter->drawText($text, $x, $y);
					$adapter->drawText($text, $x, $y);
					
					// advance to the next empty space
					$x += strlen($text) * 8;
				}
			}
			
			// advance to next line
			$y += 13;
			$x = 5;
		}
		
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
