<?php
defined( 'ABSPATH' ) or die( 'Not authorized!' );
/**
 * Plugin Name: Manor Farm Nurseries
 * Plugin URI:  none
 * Description: This plugin is specific to the company site. It contains a number of functions to modify the behavior of the site.
 * Version:     1.1.24
 * Author:      Jon Catlin
 * Author URI:  none
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: destini.com
 */

// Includes
include( dirname(__FILE__).'/catalog-pdf.php' );

/**
 * Function to be called before an import of the catalog begins
 */
function mfn_before_import($import_id) {
	error_log('In mfn_before_import'.PHP_EOL, 3, './debug.log' );
}
add_action('pmxi_before_xml_import', 'mfn_before_import', 10, 1);


/**
 * Function to be called after an import of the catalog begins
 */
function mfn_after_import($import_id) {
	error_log('In mfn_after_import'.PHP_EOL, 3, './debug.log' );

	// Create the PDF catalogs
	CatalogPDF::makePDF(true);
	CatalogPDF::makePDF(false);
}
add_action('pmxi_after_xml_import', 'mfn_after_import', 10, 1);


/**
 * Function to be called when the plugin is activated
 */
function mfn_activate() {

    // If the availability lists are missing generate them
	if (!file_exists(ABSPATH . 'wp-content/uploads/AvailabilityListP.pdf')) CatalogPDF::makePDF(true);
	if (!file_exists(ABSPATH . 'wp-content/uploads/AvailabilityList.pdf')) CatalogPDF::makePDF(false);
}
register_activation_hook( __FILE__, 'mfn_activate' );


/**
 * Defines for the rest of the code
 */
// Translations For Flowering Position
define("SUN", "A");
define("SEMI", "B");
define("SHADE", "C");

// Translations for Soil Moisture
define("DRY", "1");
define("MOIST", "2");
define("WET", "3");

// Get the url of the site 
$site_url = get_site_url( null, '', null );

// Media path to icons
define("MEDIA_PATH", $site_url."/wp-content/uploads/");
define("PHOTO_THUMB_PATH", $site_url."/wp-content/plant_photos/thumbnail/");
define("PHOTO_DETAIL_PATH", $site_url."/wp-content/plant_photos/medium/");
define("PHOTO_ORIGINAL_PATH", $site_url."/wp-content/plant_photos/original/");
define("DEFAULT_PHOTO", "no-photo");

/**
 * Hide product price based on user role (or lack thereof).
 */
function mfn_hide_prices_user_role( $price ) {
	$current_user = wp_get_current_user();
	$allowed_roles = array( 'customer', 'administrator' );
	if ( array_intersect( $current_user->roles, $allowed_roles ) ) {
		return $price;
	} else {
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		add_filter( 'woocommerce_is_purchasable', '__return_false');
		return '<a href="' . get_permalink(wc_get_page_id('myaccount')) . '">Login for prices</a>';
//		return '';
	}
}

add_filter( 'woocommerce_get_price_html', 'mfn_hide_prices_user_role' ); // Hide product price

// Cart
add_filter( 'woocommerce_cart_item_price', 'mfn_hide_prices_user_role' ); // Hide cart item price
add_filter( 'woocommerce_cart_item_subtotal', 'mfn_hide_prices_user_role' ); // Hide cart total price

// Checkout totals
add_filter( 'woocommerce_cart_subtotal', 'mfn_hide_prices_user_role' ); // Hide cart subtotal price
add_filter( 'woocommerce_cart_total', 'mfn_hide_prices_user_role' ); // Hide cart total price


/**
 * Override for the quick view pro display action
 */
function mfn_quick_view_pro_override ($product) {

	// Get the product name
	$name = $product->get_title();
    $product_id = $item['product_id'];

	$product_details = $product->get_data();
    $description = $product_details['description'];
    $height = $product_details['height'];
	
	$attributes = $product->get_attributes();
	$flower_colour = $attributes['pa_flower-colour'];
	$spread = $attribute['pa_spread'];
	$flowering_period = $attribute['pa_flowering-period'];
	$height = $attribute['pa_height'];
	$pot_size = $attribute['pa_pot-size'];

/*
Array ( [0] => pa_spread [1] => pa_position [2] => pa_soil-moisture [3] => pa_flower-colour 
[4] => pa_flowering-period [5] => pa_height [6] => pa_pot-size [7] => pa_colour-group [8] => pa_categories )
*/

	// Get the ACF fields we want to show
	$photo = get_field( 'photo' );
	$position = get_field( 'position' );
	$moisture = get_field( 'soil_moisture' );
	$display_name = get_field( 'display_name' );
	
//	print_r(array_keys($attributes));

?>

	<h5><?php echo $display_name ?></h5>
	<p><?php echo $description ?></p>

<?php
	echo $product->list_attributes();
}
add_action( 'mfn_quick_view_pro_quick_view_product_details', 'mfn_quick_view_pro_override' );


/**
 * Override for the quick view pro display action for the image
 */
function mfn_quick_view_pro_override_image ($product) {

	// Get the ACF fields we want to show
	$photo = get_field( 'photo' );

//	<img src="http://mfn-wp/wp-content/plugins/woocommerce/assets/images/placeholder.png" alt="Awaiting product image" class="wp-post-image">

?>
	<div class="woocommerce-product-gallery__image--placeholder">
		<?php echo $photo; ?>
	</div>

<?php
}
add_action( 'mfn_quick_view_pro_quick_view_before_product_details', 'mfn_quick_view_pro_override_image' );

/**
 * This function should be fired when wp all import uploads an image
 */
function mfn_gallery_image($pid, $attid, $image_filepath) {
    $attachment = get_post($attid);

	// Log thye file we are processing
	$pluginlog = plugin_dir_path(__FILE__).'debug.log';
	$message = 'In mfn_gallery_image, $image_filepath='.$image_filepath.PHP_EOL;
//	error_log($message, 3, $pluginlog);

	// Determine if this is a new file or a copy as only watermark a new file
	// New files end in -1.jpg
	$endString1 = "-1.jpg";
	$endString2 = "-1-1.jpg";
	$length1 = strlen($endString1);
	$length2 = strlen($endString2);
    if ((substr($image_filepath, -$length1) === $endString1) or (substr($image_filepath, -$length2) === $endString2)) {
//		error_log('File is a copy so ignore it'.PHP_EOL, 3, $pluginlog);
	} else {
//		error_log('File is new so water mark it'.PHP_EOL, 3, $pluginlog);

		// remove the ending file type of .jpg
		$len = strlen('.jpg');
		$find_name = substr($image_filepath, 0, -$len).'-*.jpg';

		// Add the copyright to the original
		addCopyright( $image_filepath );

		// find all files that are a match -*.jpg which are different resolutions
		// Then copright them as well
		foreach (glob($find_name) as $filename) {
			addCopyright( $filename );
		}
	}

}
add_action('pmxi_gallery_image', 'mfn_gallery_image', 10, 3);

/*
	* Put a copyright statement in an image
	*/	
function addCopyright ($filename) {

	// Log all the variables
	$pluginlog = plugin_dir_path(__FILE__).'debug.log';

	$font = plugin_dir_path(__FILE__).'Tahoma.ttf';
	
	// Create the text to be placed in the image
	$text = 'Copyright '.chr(169).date('Y').' Manor Farm Nurseries';
	
	// Ensure the memory is enough to process large images
	ini_set('memory_limit', '1000M');

	$message = 'In mfn_addCopyright, Getting the image from the file'.PHP_EOL;
//	error_log($message, 3, $pluginlog);

	// Get the source image
	$img = imagecreatefromjpeg($filename);
	if (!img) {
		$message = 'In mfn_addCopyright, Create image from jpeg FAILED, $filename='.$filename.PHP_EOL;
//		error_log($message, 3, $pluginlog);
		return;
	}

	// Allocate some colors
	$white = imagecolorallocate($img, 255, 255, 255);
	$grey = imagecolorallocate($img, 128, 128, 128);
	$black = imagecolorallocate($img, 0, 0, 0);
	
	$imgwidth = imagesx($img);		

	$fontsize = ($imgwidth * 0.9) / strlen($text);

	// If the font size drops below 6 then ignore the file as it is too small
	if ($fontsize < 6) {
		$message = 'In addCopyright, font size too small so ignoring image for copyright $fontsize='.$fontsize.PHP_EOL;
//		error_log($message, 3, $pluginlog);

			// Free up memory
			imagedestroy($img);

			return;
	}
	$center = ceil($imgwidth / 2); 
	
	// Determine the bounds of the text
	$bbox = imagettfbbox($fontsize, 0, $font, $text);
	$x = $center - (($bbox[4] /2));
	$y = $fontsize + 10;

	// Write the copyright banner
	imagettftext($img, $fontsize, 0, $x+1, $y+1, $white, $font, $text);
	imagettftext($img, $fontsize, 0, $x, $y, $black, $font, $text);

	// Output the image to the same location and the same name
	if ( imagejpeg( $img, $filename )) {
		$message = 'In mfn_addCopyright, Final image saved successfully back to the same file'.PHP_EOL;
//		error_log($message, 3, $pluginlog);
	} else {
		$message = 'In mfn_addCopyright, FAILED to save image back to same file'.PHP_EOL;
//		error_log($message, 3, $pluginlog);
	}

	// Free up memory
	imagedestroy($img);
}






/**
 * The following are functions used during the product import process to alter the data so it is displayed properly
 */
// Convert the category into something that is a text representation of the numeric category
function mfn_translate_category( $cat ) {

	switch ($cat) {
		case 1:
			return "Herbaceous";
		case 2:
			return "Ferns";
		case 3:
			return "Grasses";
		case 4:
			return "Shrubs";
		case 5:
			return "Climbers";
	}
	return "";
}

// Convert the photo name to the name of the image file to be used
function mfn_translate_photograph( $photo , $type) {
	$product_photo = preg_replace( '/[^A-Za-z0-9\- \.]/', '', $photo );
	$filename = str_replace( ' ', '_', strtolower( $product_photo ) );
	
	if ( !$filename === "" ) $filename .= '.jpg';

	switch ( $type ) {
		case "THUMB":
			return PHOTO_THUMB_PATH.$filename;
		case "DETAIL":
			return PHOTO_DETAIL_PATH.$filename;
		case "ORIGINAL":
			return PHOTO_ORIGINAL_PATH.$filename;
	}
	return PHOTO_ORIGINAL_PATH.$filename;
}


// Convert the plant name to have the Trophy symbol and remove the old special character at the end of the name
function mfn_translate_name( $name, $award ) {

// Remove this so that any special characters in the name are OK. Gordon is removing the trophy character
//	$new_name = preg_replace( '/[[:^ascii:]]/', '', $name );
	$new_name = trim($name);

	if (strtoupper($award) === "Y") {
		return 	$new_name .= '<img src="'.MEDIA_PATH.'trophy6.png" alt="" style="max-width: 20px">';
	} else {
		return 	$new_name;
	}
}

// Convert the flowering period to the names of the months
function mfn_translate_flowering_period( $value ) {
	$pattern = '/[^0-9,]+/';
	$newString = trim(preg_replace($pattern,'',$value),',');
	$pattern = '/\d+/';
	preg_match_all($pattern, $value, $matches, PREG_PATTERN_ORDER); 

	// if more than one match take the first and last and place a '-' between them
	if (count ($matches[0]) > 1) {
		return date("M", mktime(0, 0, 0, current($matches[0]), 1, 2000)).
			"-".date("M", mktime(0, 0, 0, end($matches[0]), 1, 2000));
	} else {
		return date("M", mktime(0, 0, 0, current($matches[0]), 1, 2000));
	}
}

/*
 * Helper function to display the Position. This function
 * transforms the positions into icons that represent them
 */
function mfn_translate_position_to_images ($value) {
	$v = strtoupper($value);

	$retString = "";
	if (strpos ($v, SUN) !== false) {
		$retString .= '<img src="'.MEDIA_PATH.'sun20.png" alt="" class="MFNIcon">';
	}
	if (strpos ($v, SEMI) !== false) {
		$retString .= '<img src="'.MEDIA_PATH.'semi20.png" alt="" class="MFNIcon">';
	} 
	if (strpos ($v, SHADE) !== false) {
		$retString .= '<img src="'.MEDIA_PATH.'shade20.png" alt="" class="MFNIcon">';
	}
	return $retString;
}


/*
 * Helper function to display the Position. This function
 * transforms the positions into text
 */
function mfn_translate_position_to_text ($value) {
	$v = strtoupper($value);

	$retString = "";
	if (strpos ($v, SUN) !== false) {
		$retString .= 'Sun ';
	}
	if (strpos ($v, SEMI) !== false) {
		$retString .= 'Semi-shade ';
	} 
	if (strpos ($v, SHADE) !== false) {
		$retString .= 'Shade';
	}
	return $retString;
}


/*
 * Helper function to display the Moisture. This function
 * transforms the soil moisture into an icons that represent them
 */
function mfn_translate_moisture_to_images ($value) {
	$v = strtoupper($value);

	$retString = "";
	if (strpos ($v, DRY) !== false) {
		$retString .= '<img src="'.MEDIA_PATH.'dry.png" alt="" class="MFNIcon">';
	}
	if (strpos ($v, MOIST) !== false) {
		$retString .= '<img src="'.MEDIA_PATH.'moist.png" alt="" class="MFNIcon">';
	} 
	if (strpos ($v, WET) !== false) {
		$retString .= '<img src="'.MEDIA_PATH.'wet.png" alt="" class="MFNIcon">';
	}
	return $retString;
}


/*
 * Helper function to display the Moisture. This function
 * transforms the soil moisture into text
 */
function mfn_translate_moisture_to_text ($value) {
	$v = strtoupper($value);

	$retString = "";
	if (strpos ($v, DRY) !== false) {
		$retString .= 'Dry ';
	}
	if (strpos ($v, MOIST) !== false) {
		$retString .= 'Moist ';
	} 
	if (strpos ($v, WET) !== false) {
		$retString .= 'Wet';
	}
	return $retString;
}


// Convert fields with 'y' and 'n' to 'Yes' and 'No'
function mfn_translate_yn ( $value ) {
	$v = strtoupper($value);
	if ($v === "Y") {
		return "Yes";
	} else if ($v === "N") {
		return "No";
	}
	return "";
}


// Convert fields with 'y' and 'n' to 'Yes' and 'No'
function mfn_translate_award ( $value ) {
	if (strtoupper($value) === "Y") {
		return 	'<img src="'.MEDIA_PATH.'trophy6.png" alt="" class="">';
	} else {
		return 	'';
	}
}

/* Not needed to override the trophy styling. Added it to the tranform of the product name
// Load the mfn CSS last so it can take precedence over a woocommerce style for the trophy symbol
function mfn_load_css(){
	$url_css = '/wp-content/plugins/mfn/mfn.css';
	wp_enqueue_style('mfn-custom-theme', $url_css);
}
// (the higher the number the lowest Priority)
add_action('wp_enqueue_scripts', 'mfn_load_css', 5000);

*/


/**
 * Set a minimum order amount for checkout
 */
function mfn_minimum_order_amount() {
    // Set this variable to specify a minimum order value
    $minimum = 50;

    if ( WC()->cart->total < $minimum ) {

        if( is_cart() ) {

            wc_print_notice( 
                sprintf( 'Your current order total is %s. We are sorry but we can only accept orders of %s or greater.' , 
                    wc_price( WC()->cart->total ), 
                    wc_price( $minimum )
                ), 'error' 
            );

        } else {

            wc_add_notice( 
                sprintf( 'Your current order total is %s. We are sorry but we can only accept orders of %s or greater. Please return to the cart and add more items.' , 
                    wc_price( WC()->cart->total ), 
                    wc_price( $minimum )
                ), 'error' 
            );

        }
    }
}
add_action( 'woocommerce_checkout_process', 'mfn_minimum_order_amount' );
add_action( 'woocommerce_before_cart' , 'mfn_minimum_order_amount' );
