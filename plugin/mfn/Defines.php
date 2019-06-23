<?php


// No direct access.
defined('_JEXEC') or die;

// The name of the custom field in the catalogue
define("PHOTOGRAPH", "Photograph");
define("AWARD", "Award");
define("POSITION", "Position");
define("SOIL_MOISTURE", "Soil Moisture");
define("FLOWERING_PERIOD", "Flowering Period");
define("FLOWER_COLOUR", "Flower Colour");
define("HEIGHT", "Height");
define("POT_SIZE", "Pot Size");
define("SPREAD", "Spread");


// Set up all the constants for the photo directories
define("THUMBNAIL_PATH", "media/com_mfn/images/product/thumbnail/");
define("LOW_PATH", "media/com_mfn/images/product/low/");
define("MEDIUM_PATH", "media/com_mfn/images/product/medium/");
define("ORIGINAL_PATH", "media/com_mfn/images/product/original/");
define("TEMP_PATH", "media/com_mfn/images/product/temp/");

// Path to the icons
define("ICON_PATH", "media/com_mfn/images/icons/");

define("MEDIUM_SIZE", 400);
define("LOW_SIZE", 200);
define("THUMBNAIL_SIZE", 40);

define("HIGH_QUALITY", 100);
define("MEDIUM_QUALITY", 50);
define("LOW_QUALITY", 50);
define("THUMBNAIL_QUALITY", 50);

define("COPYRIGHT_FONT",JPATH_ROOT."/media/com_mfn/fonts/Tahoma.ttf");

// The delay between processing images
define("PAUSE_BETWEEN_IMAGE_PROCESSING", 0.5);

// The search string for a template reset
define("TEMPLATE_STARTS_WITH", "yoo_");

// Name of the directory within the site that stores the template overrides
define("TEMPLATE_OVERRIDES", "TEMPLATE_OVERRIDES");

// Names for the session variable that hold various pieces of information
define("MFN_CUSTOM_FIELDS", "MFN_CUSTOM_FIELDS");
define("MFN_CATEGORIES", "MFN_CATEGORIES");
define("MFN_POT_SIZES", "MFN_POT_SIZES");

// Translations For Flowering Position
define("SUN", "A");
define("SEMI", "B");
define("SHADE", "C");

// Translations for Soil Moisture
define("DRY", "1");
define("MOIST", "2");
define("WET", "3");

// The default product category to preselect for display when the user has
// not yet selected one
define("MFN_DEFAULT_CATEGORY", "Herbaceous");

/*
 * Code to get the custom fields and saveit in the session if it has not been defined
 */
function getCustomFields() {
	if(isset($_SESSION[MFN_CUSTOM_FIELDS])) {
		return $_SESSION[MFN_CUSTOM_FIELDS];
	} else {
		// Execute my own SQL to get the text for the custom fields
		try {
			$q = 'SELECT virtuemart_custom_id as value, custom_title as text, custom_desc as responsive, layout_pos '.
					'FROM `#__virtuemart_customs` where custom_parent_id=0 AND '.
					'field_type <> "R" AND field_type <> "Z" AND published=1 AND is_hidden=0 ORDER BY layout_pos';
			$db = JFactory::getDbo();
			$query = $db->setQuery($q);
			$temp = $db->loadAssocList ('text');
				
			// Store the headings for the custom fields in a session variable
			$_SESSION[MFN_CUSTOM_FIELDS] = $temp;
			return $temp;
		} catch (RuntimeException $e) {
			JLog::add('getCustomFields DB error = '.$e->getMessage(), JLog::DEBUG, 'com_mfn');
			JFactory::getApplication()->
				enqueueMessage (JText::_('VM_PRODUCTS_CUSTOM_FIELDS_ERR'), 'Error');
			return;
		}
	}
}

