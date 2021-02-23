<?php
// No direct access.
defined('_JEXEC') or die;

require_once JPATH_COMPONENT_SITE.'/helpers/Defines.php';
require_once JPATH_COMPONENT_SITE.'/helpers/MFNLogger.php';

class NewPhoto {
	
	public static function init() {
		
		JLog::add('entry NewPhoto::init()', JLog::DEBUG, 'com_mfn');
		
		$result = ini_set('max_execution_time', 400); //300 seconds = 5 minutes
		JLog::add('Set max_execution_time, $result='.$result, JLog::DEBUG, 'com_mfn');
		

		$db = JFactory::getDbo();
		$user = JFactory::getUser();
		$user_id = $user->id;
		$today = date('Y-m-d H:i:s');

		// Delete all photos from the DB
		NewPhoto::deleteAllMediaForProducts($db);
		
		// Get a list of all the files in the temp directory and regenerate them
		$inDir = dir(JPATH_ROOT.'/'.TEMP_PATH);
		
		$filesProcessed = 0;
		$file = "";

		// Get a handle to the Joomla! application object
		$application = JFactory::getApplication();
		
//		while (false !== ($file = $inDir->read())) {
		for ($i=0; false !== ($file = $inDir->read()) && $i < 200; $i++) {
			$suffix_pos = strrpos($file, '.');
			$endswith = strtolower($suffix_pos ? substr($file,$suffix_pos) : '');
			if ($endswith == '.jpg' || $endswith == '.jpeg') {

				$fileName = substr($file, 0, $suffix_pos);
				JLog::add('Found photo. $filename='.$fileName, JLog::DEBUG, 'com_mfn');
				$newName = NewPhoto::toFileName($fileName);

				// Rename the photo 
				if (!NewPhoto::existsOriginalPhoto($newName)) {
					if (rename(JPATH_ROOT.'/'.TEMP_PATH.$file, JPATH_ROOT.'/'.ORIGINAL_PATH.$newName)) {
						// Process the photo as if it were uploaded
						NewPhoto::processPhoto($user_id, $today, $db, $newName, true);

						// Inform the user which files were processed
						$application->enqueueMessage('Successfully processed file named '.$newName, 'Message');
						
						// Wait a short while to prevent maxing out resources in hosting environment
						sleep (PAUSE_BETWEEN_IMAGE_PROCESSING);
					} else {
						throw new Exception('Error moving image. From '.JPATH_ROOT.'/'.TEMP_PATH.$file.' to '.JPATH_ROOT.'/'.ORIGINAL_PATH.$newName);
					}
				}
			}
		}
	}

	static public function processPhoto ($user_id, $today, $db, $photoName, $overwrite) {

		JLog::add('entry NewPhoto::processPhoto(), user_id ='.$user_id.' $today='.$today.' $photoName='.$photoName.' $overwrite='.$overwrite, JLog::DEBUG, 'com_mfn');
		
		// Create THUMBNAIL resolution image
		if (!file_exists(JPATH_ROOT.'/'.THUMBNAIL_PATH.$photoName) || (file_exists(JPATH_ROOT.'/'.THUMBNAIL_PATH.$photoName) && $overwrite)) {
			if (file_exists(JPATH_ROOT.'/'.THUMBNAIL_PATH.$photoName)) unlink (JPATH_ROOT.'/'.THUMBNAIL_PATH.$photoName);
			$img = NewPhoto::resize(JPATH_ROOT.'/'.ORIGINAL_PATH, $photoName, THUMBNAIL_SIZE);
			imagejpeg($img, JPATH_ROOT.'/'.THUMBNAIL_PATH.$photoName, THUMBNAIL_QUALITY);
		}			
		
		// Create LOW resolution image
		if (!file_exists(JPATH_ROOT.'/'.LOW_PATH.$photoName) || (file_exists(JPATH_ROOT.'/'.LOW_PATH.$photoName) && $overwrite)) { 
			if (file_exists(JPATH_ROOT.'/'.LOW_PATH.$photoName)) unlink (JPATH_ROOT.'/'.LOW_PATH.$photoName);
			$img = NewPhoto::resize(JPATH_ROOT.'/'.ORIGINAL_PATH, $photoName, LOW_SIZE);
			NewPhoto::addCopyright($img);
			imagejpeg($img, JPATH_ROOT.'/'.LOW_PATH.$photoName, LOW_QUALITY);
		}
			
		// Create MEDIUM resolution image
		if (!file_exists(JPATH_ROOT.'/'.MEDIUM_PATH.$photoName) || (file_exists(JPATH_ROOT.'/'.MEDIUM_PATH.$photoName) && $overwrite)) {
			if (file_exists(JPATH_ROOT.'/'.MEDIUM_PATH.$photoName)) unlink (JPATH_ROOT.'/'.MEDIUM_PATH.$photoName);
			$img = NewPhoto::resize(JPATH_ROOT.'/'.ORIGINAL_PATH, $photoName, MEDIUM_SIZE);
			NewPhoto::addCopyright($img);
			imagejpeg($img, JPATH_ROOT.'/'.MEDIUM_PATH.$photoName, MEDIUM_QUALITY);
		}			

		// Create an entry in Virtuemart
		$media_id = NewPhoto::createInVirtuemart($user_id, $today, $db, $photoName);
		
		// Create links to photo for all matching catalog entries
		$product_ids = NewPhoto::existsProductWithPhotoName($db, $photoName);
		foreach ($product_ids as $id) {
			JLog::add('Processing matching product with photo name, product id='.$id.' with photoName '.$photoName, JLog::DEBUG, 'com_mfn');
			NewPhoto::linkProductWithMedia($id, $media_id, $db);
		}
	}

	
	public static function existsInVirtuemart($db, $file) {

		JLog::add('entry NewPhoto::existsInVirtuemart()', JLog::DEBUG, 'com_mfn');
		
		// Check to see if photo is already in the Virtuemart media database
		$query = $db->getQuery(true);
		$query
			->select('virtuemart_media_id')
			->from('#__virtuemart_medias')
			->where('file_title = '.$db->quote($file));
		
		$db->setQuery($query);
		$media_id = $db->loadResult();
		JLog::add('virtuemart_media_id='.$media_id, JLog::DEBUG, 'com_mfn');
		
		if ($media_id == NULL) return false;
		
	}


	public static function createInVirtuemart($user_id, $today, $db, $file) {

		JLog::add('entry NewPhoto::createInVirtuemart()', JLog::DEBUG, 'com_mfn');
		
		$columns = array('virtuemart_vendor_id', 'file_title', 'file_mimetype',
				'file_type', 'file_url', 'file_url_thumb', 'file_is_product_image', 'created_on', 'created_by', 'modified_on', 'modified_by');
		$values = array($db->quote('1'), $db->quote($file), $db->quote('image/jpeg'), $db->quote('product'), $db->quote(MEDIUM_PATH.$file), $db->quote(THUMBNAIL_PATH.$file),   
				$db->quote(1), $db->quote($today), $db->quote($user_id), $db->quote($today), $db->quote($user_id));
		$query = $db->getQuery(true);
		$query
			->insert($db->quoteName('#__virtuemart_medias'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		$db->setQuery($query);
		$db->execute();
		$media_id = $db->insertid();
		JLog::add('insert media values ='.implode(',', $values).' generated $media_id='.$media_id, JLog::DEBUG, 'com_mfn');
		
		return $media_id;
	}


	public static function existsProductWithPhotoName($db, $file) {

		JLog::add ('entry NewPhoto::existsProductWithPhotoName(), $file='.$file,JLog::DEBUG, 'com_mfn');
		
		// Find all products with a matching phot name and create entries in product_medias
		$query = $db->getQuery(true);
		$query
			->select('virtuemart_product_id')
			->from($db->quoteName('#__virtuemart_product_customfields', 'a'))
			->join('INNER', $db->quoteName('#__virtuemart_customs', 'b') . ' ON (' . $db->quoteName('a.virtuemart_custom_id') . ' = ' . $db->quoteName('b.virtuemart_custom_id') . ')')
			->where($db->quoteName('b.custom_title') . ' = '. $db->quote(PHOTOGRAPH) . ' AND ' . $db->quoteName('a.customfield_value') . ' = '. $db->quote($file));
		
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$ids = $db->loadColumn();
		JLog::add ('The catalog entries matching photo named '.$file.' are '.print_r(implode(',', $ids),true),JLog::DEBUG, 'com_mfn');
		
		return($ids);
	}


	/*
	 * Move a file to the Orginal folder and ensure the name is changed to be in the correct format
	 */	
	public static function saveOriginalPhoto ($from, $to) {
    	JLog::add('entry NewPhoto::saveOriginalPhoto(), $from='.$from.' $to='.$to, JLog::DEBUG, 'com_mfn');
    	$dest = JPATH_ROOT.'/'.ORIGINAL_PATH.$to;
    	
    	// Check to see if the original photo has been rotated and therefore needs altering
    	$exif = exif_read_data($from);
    	if(!empty($exif['Orientation'])) {
    		$img = imagecreatefromjpeg($from);
    		if (!$img) {
    			throw new Exception('Error creating image from uploaded file.' . $from);
    		}
    		
    		switch($exif['Orientation']) {
    			case 8:
    				$img = imagerotate($img,90,0);
    				break;
    			case 3:
    				$img = imagerotate($img,180,0);
    				break;
    			case 6:
    				$img = imagerotate($img,-90,0);
    				break;
    		}
    		
    		// Write the image to the destination
    		imagejpeg($img, $dest, HIGH_QUALITY);
    		
    	} else {
    		// No manipulation is required so move the file to the destination folder
    		if (!move_uploaded_file($from, $dest)) return false;
    	}

    	return true;
	}
	
	
	
	/*
	 * Check to see if an original photo already exists with the same name
	 */	
	public static function existsOriginalPhoto ($filename) {
    	JLog::add('entry NewPhoto::existsOriginalPhoto(), $filename='.$filename, JLog::DEBUG, 'com_mfn');
		return file_exists(JPATH_ROOT.'/'.ORIGINAL_PATH.$filename);
	}
	
	
	
	public static function existsPhotoWithFileName($db, $file) {

		JLog::add('entry NewPhoto::existsPhotoWithFileName $file = '.$file, JLog::DEBUG, 'com_mfn');
		$query = $db->getQuery(true);
		$query
			->select('virtuemart_media_id')
			->from('#__virtuemart_medias')
			->where('file_title = '.$db->quote($file));
		$db->setQuery($query);
		$media_id = $db->loadResult();
		JLog::add('media_id ='.$media_id, JLog::DEBUG, 'com_mfn');
		
		return($media_id);
	}
	
		
	public static function toFileName ($name) {
		$product_photo = preg_replace('/[^A-Za-z0-9\- \.]/', '', $name);
		return str_replace(' ', '-', strtolower($product_photo)) . '.jpg';
	}
	
	
	static public function	createMediaForProduct ($file_name, $product_id, $db) {
		JLog::add('entry NewPhoto::createMediaForProduct(), $photo_name='.$file_name.' $product_id='.$product_id, JLog::DEBUG, 'com_mfn');
		
		// Find a matching media entry for this plant in virtuemart_medias
		$media_id = NewPhoto::existsPhotoWithFileName($db, $file_name);
		
		// Only create a link if there is a matching media file for the photo
		if ($media_id) {
			NewPhoto::linkProductWithMedia($product_id, $media_id, $db);
			return true;
		}
		return false;
	}

	
	static private function linkProductWithMedia ($product_id, $media_id, $db) {
		JLog::add('entry NewPhoto::linkProductWithMedia(), $product_id='.$product_id.' $media_id='.$media_id, JLog::DEBUG, 'com_mfn');
		
		// Create the entry in the product_media table
		$columns = array('virtuemart_product_id','virtuemart_media_id','ordering');
		$values = array($product_id, $db->quote($media_id), 0);
		$query = $db->getQuery(true);
		$query
			->insert('#__virtuemart_product_medias')
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		$db->setQuery($query);
		$db->query();
		JLog::add('link product with media, values ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
	}

	
	static public function deleteAllMediaForProducts($db) {
	
		JLog::add('entry NewPhoto::deleteAllMediaForProducts()', JLog::DEBUG, 'com_mfn');

		// Delete all product entries
		$query = $db->getQuery(true);
		$conditions = array('a.virtuemart_media_id = b.virtuemart_media_id AND b.file_is_product_image = 1');
/*	
		$query
			->delete($db->quoteName('#__virtuemart_product_medias', 'a'))
    		->join('INNER', $db->quoteName('#__virtuemart_medias', 'b') . ' ON (' . $db->quoteName('a.virtuemart_media_id') . ' = ' . $db->quoteName('b.virtuemart_media_id') . ')')
			->where($conditions);
		$db->setQuery($query);
		$db->execute();
*/
		$query
		->delete('a USING #__virtuemart_product_medias a, #__virtuemart_medias b')
		->where($conditions);
		$db->setQuery($query);
		$db->execute();
		$affectedRows = $db->getAffectedRows();
		JLog::add('#__virtuemart_product_medias, $affectedRows='.$affectedRows, JLog::DEBUG, 'com_mfn');


		// Delete all media entries
/* DO NOT DELETE ALL THE KNOWN MEDIA RATHER JUST THE LINK BETWEEN THE PRODUCT AND THE IMAGE
		$query = $db->getQuery(true);
		$conditions = array(
				$db->quoteName('file_is_product_image') . ' = 1'
		);
		
		$query
			->delete($db->quoteName('#__virtuemart_medias'))
			->where($conditions);
		$db->setQuery($query);
		$db->execute();
		$affectedRows = $db->getAffectedRows();
		JLog::add('#__virtuemart_medias, $affectedRows='.$affectedRows, JLog::DEBUG, 'com_mfn');
*/

	}
	
	
	/*
	 * Create a new image from an existing image but with a diferent size. This
	* function preserves the aspect ratio of the image.
	*/
	public static function resize($sourcePath, $filename, $newsize) {
	
		JLog::add('entry NewPhoto::resize(), $sourcePath='.$sourcePath.', $filename='.$filename.', $newsizw='.$newsize, JLog::DEBUG, 'com_mfn');
		
		// Ensure the memory is enough to process large images
		ini_set('memory_limit', '1000M');
		
		// Get the source image
		$srcimg = imagecreatefromjpeg($sourcePath.$filename);
		if (!$srcimg) {
			throw new Exception('Error creating image.' . $sourcePath.$filename);
		}
		// Calculate the new dimensions for the image
		$sizes = NewPhoto::getScaledSizes($srcimg,$newsize);
	
		// Create the new image by resampling
		$newimg = imagecreatetruecolor($sizes['width'], $sizes['height']);
		imagecopyresampled($newimg, $srcimg, 0, 0, 0, 0, $sizes['width'], $sizes['height'], imagesx($srcimg), imagesy($srcimg));
	
		return $newimg;
	}
	
	
	/*
	 * Put a copyright statement in an image
	 */	
	public static function addCopyright ($img) {
		JLog::add('entry NewPhoto::addCopyright()', JLog::DEBUG, 'com_mfn');
		
		$font = COPYRIGHT_FONT;
		
		// Create the text to be placed in the image
		$text = 'Copyright '.chr(169).date('Y').' Manor Farm Nurseries';
		
		// Allocate some colors
		$white = imagecolorallocate($img, 255, 255, 255);
		$grey = imagecolorallocate($img, 128, 128, 128);
		$black = imagecolorallocate($img, 0, 0, 0);
		
		
		$imgwidth = imagesx($img);		
		$fontsize = ($imgwidth * 0.9) / strlen($text);
		$center = ceil($imgwidth / 2); 
		
		// Determine the bounds of the text
		$bbox = imagettfbbox($fontsize, 0, $font, $text);
 		$x = $center - (($bbox[4] /2));
 		$y = $fontsize + 10;
		
		imagettftext($img, $fontsize, 0, $x+1, $y+1, $white, $font, $text);
		imagettftext($img, $fontsize, 0, $x, $y, $black, $font, $text);

	}


	/*
	 * Calculate the new dimensions of an image given its width and height and a new size
	 */
	public static function getScaledSizes ($img, $height) {

		$sizes = array();

		// Get new dimensions and preserve the aspect ratio
		$myw = imagesx($img);		
		$myh = imagesy($img);
		
		//$scale = ($myh > $sourceWidth) ? $size / $myh : $size / $myw;
		$scale = $height / $myh;

		$sizes['width'] = $myw * $scale;		
		$sizes['height'] = $myh * $scale;

		return $sizes;
	}

	public static function addExistingPhotosToVirtuemart ($user_id, $today, $db) {
		JLog::add('entry NewPhoto::addExistingPhotosToVirtuemart()', JLog::DEBUG, 'com_mfn');
		
		// Scan the directory to find all the photos
		$path    = JPATH_ROOT.'/'.ORIGINAL_PATH.'*.jpg';

		foreach (glob($path) as $filename) {
			$file = basename($filename);
			if (!NewPhoto::existsInVirtuemart($db, $file)) {
				NewPhoto::createInVirtuemart($user_id, $today, $db, $file);
				echo "<p>Added $file to Virtuemart" . '</p>';
			}
		}
	}
}
?>