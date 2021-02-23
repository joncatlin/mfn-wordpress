<?php
defined( 'ABSPATH' ) or die( 'Not authorized!' );






define("DEBUG_LOG_FILE", plugin_dir_path(__FILE__).'debug.log');
error_log('In mfn_photo_processing'.PHP_EOL, 3, DEBUG_LOG_FILE );
//error_log('WP_CONTENT_DIR='.print_r(WP_CONTENT_DIR, true).PHP_EOL, 3, DEBUG_LOG_FILE );

// Set up all the constants for the photo directories
define("MFN_GALLERY_PATH", WP_CONTENT_DIR."/gallery/all-plants/");

define("MFN_THUMBNAIL_PATH", WP_CONTENT_DIR."/temp/thumbnail/");
define("MFN_DETAIL_PATH", WP_CONTENT_DIR."/temp/detail/");


define("MFN_DETAIL_SIZE", 400);
define("MFN_THUMBNAIL_SIZE", 40);

define("MFN_DETAIL_QUALITY", 75);
define("MFN_THUMBNAIL_QUALITY", 50);

define("MFN_COPYRIGHT_FONT", "./Tahoma.ttf");


// **************************************************************************************************
// define the ngg_added_new_image callback 
function mfn_action_ngg_added_new_image( $image ) { 
    error_log('In mfn_action_ngg_added_new_image'.PHP_EOL.print_r($image, true).PHP_EOL, 3, DEBUG_LOG_FILE );

    // Copy the file to the correct place, overriding any existing image with the same name
    // Change the size and image quality
    // Add the copyright symbol to the image
    mfn_photo_process ($image->filename);
}
         
// add the action 
add_action( 'ngg_added_new_image', 'mfn_action_ngg_added_new_image', 10, 1 ); 

// **************************************************************************************************
function mfn_action_ngg_image_updated ( $image ) { 
    // make action magic happen here... 
    error_log('In mfn_action_ngg_image_updated'.PHP_EOL.print_r($image, true).PHP_EOL, 3, DEBUG_LOG_FILE );

    // NOT SURE WHAT TO DO HERE BECAUSE IF THE IMAGE NAME IS CHANGED HOW DO WE FIND THE PREVIOUS NAME
}
         
// add the action 
add_action( 'ngg_image_updated', 'mfn_action_ngg_image_updated', 10, 1 ); 

// **************************************************************************************************
// define the ngg_delete_picture callback 
function mfn_action_ngg_delete_picture( $this_pid, $image ) { 
    // make action magic happen here... 
    error_log('In mfn_action_ngg_delete_picture'.PHP_EOL.print_r($image, true).PHP_EOL, 3, DEBUG_LOG_FILE );

    $thumb_path = MFN_THUMBNAIL_PATH.$image->filename;
    $detail_path = MFN_DETAIL_PATH.$image->filename;

    if (file_exists($thumb_path)) unlink ($thumb_path);
    if (file_exists($detail_path)) unlink ($detail_path);
}
         
// add the action 
add_action( 'ngg_delete_picture', 'mfn_action_ngg_delete_picture', 10, 2 ); 

// **************************************************************************************************
// funcation to take a new photo and create the thumbnail and detail versions
function mfn_photo_process ($filename) {

    error_log('In mfn_process_photo'.PHP_EOL.print_r($filename, true).PHP_EOL, 3, DEBUG_LOG_FILE );

    $gallery_path = MFN_GALLERY_PATH.$filename;
//    error_log('$gallery_path='.print_r($gallery_path, true).PHP_EOL, 3, DEBUG_LOG_FILE );

    // Create the THUMBNAIL version
    $thumb_path = MFN_THUMBNAIL_PATH.$filename;
//    error_log('$thumb_path='.print_r($thumb_path, true).PHP_EOL, 3, DEBUG_LOG_FILE );
    if (file_exists($thumb_path)) unlink ($thumb_path);
    $img = mfn_photo_resize($gallery_path, MFN_THUMBNAIL_SIZE);
//    error_log('$img='.print_r($img, true).PHP_EOL, 3, DEBUG_LOG_FILE );
    $result = imagejpeg($img, $thumb_path, MFN_THUMBNAIL_QUALITY);
//    error_log('$result='.print_r($result, true).PHP_EOL, 3, DEBUG_LOG_FILE );
    
    // Create the DETAIL version
    $detail_path = MFN_DETAIL_PATH.$filename;
    if (file_exists($detail_path)) unlink ($detail_path);
    $img = mfn_photo_resize($gallery_path, MFN_DETAIL_SIZE);
    mfn_add_copyright($img);
    $result = imagejpeg($img, $detail_path, MFN_DETAIL_QUALITY);

    error_log('$result='.print_r($result, true).PHP_EOL, 3, DEBUG_LOG_FILE );

}


/*
* Create a new image from an existing image but with a diferent size. This
* function preserves the aspect ratio of the image.
*/
function mfn_photo_resize($filename, $newsize) {

    error_log('In mfn_photo_resize'.PHP_EOL.print_r($filename, true).PHP_EOL.print_r($newsize, true).PHP_EOL, 3, DEBUG_LOG_FILE );

    // Ensure the memory is enough to process large images
    ini_set('memory_limit', '1000M');
    
    // Get the source image
    $srcimg = imagecreatefromjpeg($filename);
    if (!$srcimg) {
        error_log('At loc#3'.PHP_EOL, 3, DEBUG_LOG_FILE );
        throw new Exception('Error creating image.' . $filename);
    }

    // Calculate the new dimensions for the image
    $sizes = mfn_photo_get_scaled_sizes($srcimg,$newsize);

    // Create the new image by resampling
    $newimg = imagecreatetruecolor($sizes['width'], $sizes['height']);
    imagecopyresampled($newimg, $srcimg, 0, 0, 0, 0, $sizes['width'], $sizes['height'], imagesx($srcimg), imagesy($srcimg));

    return $newimg;
}
	
	
/*
* Calculate the new dimensions of an image given its width and height and a new size
*/
function mfn_photo_get_scaled_sizes ($img, $height) {

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








// Convert the photo name to the name of the image file to be used
function mfn_translate_photo_name( $photo , $type) {
	$product_photo = preg_replace( '/[^A-Za-z0-9\- \.]/', '', $photo );
	$filename = str_replace( ' ', '_', strtolower( $product_photo ) );
	
	if ( !$filename === "" ) $filename .= '.jpg';

	switch ( $type ) {
		case "THUMB":
			return MFN_PHOTO_THUMB_PATH.$filename;
		case "DETAIL":
			return MFN_PHOTO_DETAIL_PATH.$filename;
		case "ORIGINAL":
			return MFN_PHOTO_ORIGINAL_PATH.$filename;
	}
	return MFN_PHOTO_ORIGINAL_PATH.$filename;
}


/*
* Put a copyright statement in an image
*/	
function mfn_add_copyright ($img) {
    
    error_log('In mfn_add_copyright'.PHP_EOL.print_r($img, true).PHP_EOL, 3, DEBUG_LOG_FILE );
    $font = MFN_COPYRIGHT_FONT;
    
    // Create the text to be placed in the image
    $text = 'Copyright '.chr(169).date('Y').' Manor Farm Nurseries';
    
    // Allocate some colors
    $white = imagecolorallocate($img, 255, 255, 255);
    $grey = imagecolorallocate($img, 128, 128, 128);
    $black = imagecolorallocate($img, 0, 0, 0);
    error_log('COPRIGHT#1'.PHP_EOL, 3, DEBUG_LOG_FILE );
    
    
    $imgwidth = imagesx($img);		
    $fontsize = ($imgwidth * 0.9) / strlen($text);
    $center = ceil($imgwidth / 2); 
    
    error_log('COPRIGHT#2'.PHP_EOL, 3, DEBUG_LOG_FILE );
    // Determine the bounds of the text
    $bbox = imagettfbbox($fontsize, 0, $font, $text);
    error_log('COPRIGHT#3'.PHP_EOL, 3, DEBUG_LOG_FILE );
    $x = $center - (($bbox[4] /2));
    $y = $fontsize + 10;
    
    imagettftext($img, $fontsize, 0, $x+1, $y+1, $white, $font, $text);
    error_log('COPRIGHT#4'.PHP_EOL, 3, DEBUG_LOG_FILE );
    imagettftext($img, $fontsize, 0, $x, $y, $black, $font, $text);
    error_log('COPRIGHT#5'.PHP_EOL, 3, DEBUG_LOG_FILE );

}


