<?php 
// No direct access.
defined('_JEXEC') or die;

// Get the classes needed
require_once JPATH_COMPONENT_SITE.'/helpers/Classes/PHPExcel/IOFactory.php';
require_once JPATH_COMPONENT_SITE.'/helpers/Defines.php';
require_once JPATH_COMPONENT_SITE.'/helpers/NewPhoto.php';
require_once JPATH_COMPONENT_SITE.'/helpers/MFNLogger.php';

if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR.'/components/com_virtuemart/helpers/config.php');


class Catalog {
	
	static $columnMap = array ("Category"=>0, "SKU"=>1, "Name"=>2, "Description"=>3, PHOTOGRAPH=>4, POSITION=>5, SOIL_MOISTURE=>6,
		FLOWER_COLOUR=>7, FLOWERING_PERIOD=>8, HEIGHT=>9, SPREAD=>10, POT_SIZE=>11, "UnitPrice"=>12, "QuantityPrice"=>13, "New"=>14, 
		AWARD=>15 );
	
	// A map for the Catalogs categories
	static $categoryMap = array (
		array ("sheetId"=>"1", "name"=>"Herbaceous", "dbId"=>null),
		array ("sheetId"=>"2", "name"=>"Ferns", "dbId"=>null),
		array ("sheetId"=>"3", "name"=>"Grasses", "dbId"=>null),
		array ("sheetId"=>"4", "name"=>"Shrubs", "dbId"=>null),
		array ("sheetId"=>"5", "name"=>"Climbers", "dbId"=>null)
	);
	
	
	static private function init ($db) {
		
		// TODO Ensure that there is a vendor id for MFN
		// TODO If there is no vendor then throw an exception
		
		// Populate the mapping table between the category number in the spreadsheet and the Virtuemart one
		// Use the category name in the array as a mechanism to join the information
		$dbCategories = Catalog::getProductCategories ($db);
		foreach (Catalog::$categoryMap as $key => $category) {
			$dbIndexFound = false;
			foreach ($dbCategories as $dbCategory) {
				if ($dbCategory['name'] == $category['name']) {
					Catalog::$categoryMap[$key]['dbId'] = $dbCategory['id'];
					$dbIndexFound = true; 
				}
			}
			
			if (!$dbIndexFound) {
				// Throw an exception for a missing Virtuemart category
				JLog::add('Did not find dbId for '.$category['name'], JLog::DEBUG, 'com_mfn');
				throw new Exception('Missing Virtuemart category named: '.$category['name']);
			}
		}
		
	}

	/*
	 * Read the spreadsheetinto an array in order to process it
	 */
	static private function read ($inputFileName) {
		//  Read in a file as a spreadsheet and return an array with all of its values
		$objReader = PHPExcel_IOFactory::createReaderForFile($inputFileName);
		$objReader->setReadDataOnly();
		$objPHPExcel = $objReader->load($inputFileName);
		$retVal = $objPHPExcel->getActiveSheet()->toArray(null,false,false,false);

		// Free up memory
		$objPHPExcel = null;
		$objReader = null;
		return $retVal;
	}
	
	

	/*
	 * Convert values in the spread sheet to the correct format to be stored in the DB
	 */
	static function convertSpreadSheet (&$data) {
		JLog::add('entry convertSpreadSheet()', JLog::DEBUG, 'com_mfn');
		
		$size = count($data);

		// Ignore the headings in the conversion
		for ($index = 1; $index < $size; ++$index) {
			
			// Convert the Photograph to a file name
			if (trim($data[$index][Catalog::$columnMap[PHOTOGRAPH]]) != '') {
				$data[$index][Catalog::$columnMap[PHOTOGRAPH]] = NewPhoto::toFileName($data[$index][Catalog::$columnMap[PHOTOGRAPH]]);
			}
			$data[$index][Catalog::$columnMap[AWARD]] = trim(strtolower($data[$index][Catalog::$columnMap[AWARD]]))  == 'y' ? true : false;
			$data[$index][Catalog::$columnMap["New"]] = trim(strtolower($data[$index][Catalog::$columnMap["New"]]))  == 'y' ? 1 : 0;
			$data[$index][Catalog::$columnMap[POSITION]] = Catalog::transformPosition($data[$index][Catalog::$columnMap[POSITION]]);
			//$data[$index][Catalog::$columnMap[SOIL_MOISTURE]] = Catalog::transformMoisture($data[$index][Catalog::$columnMap[SOIL_MOISTURE]]);
			$data[$index][Catalog::$columnMap[FLOWERING_PERIOD]] = Catalog::transformPeriod($data[$index][Catalog::$columnMap[FLOWERING_PERIOD]]);
		}
	}
	

	/*
	 * Validate the values in the spreadsheet and return
	*/
	static function isValidSpreadSheet ($data) {
		JLog::add('entry validateSpreadSheet()', JLog::DEBUG, 'com_mfn');
	
		$valid = true;
	
		$size = count($data);
		
		// Dump the data array to find errors
		JLog::add('$data = '.print_r($data, true), JLog::DEBUG, 'com_mfn');
		
	
		// Ignore the headings in the conversion
		for ($index = 1; $index < $size; ++$index) {
				
			// TODO Check that the category is correct
			
			// Check that the price is a float
			if (!filter_var($data[$index][Catalog::$columnMap["UnitPrice"]], FILTER_VALIDATE_FLOAT)) {
				$rowNum = $index+1;
				JFactory::getApplication()->
					enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_2', 
						'Row '.$rowNum.' in spreadsheet. UnitPrice '.$data[$index][Catalog::$columnMap["UnitPrice"]].
						' is not a valid currency amount'),
						'Error');
				$valid = false;
			}
	
			// Check that the quantity price is a float or blank
			if (trim($data[$index][Catalog::$columnMap["QuantityPrice"]]) != '' &&
				!filter_var($data[$index][Catalog::$columnMap["QuantityPrice"]], FILTER_VALIDATE_FLOAT)) {

				$rowNum = $index+1;
				JFactory::getApplication()->
					enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_2', 
						'Row '.$rowNum.' in spreadsheet. QuantityPrice '.$data[$index][Catalog::$columnMap["QuantityPrice"]].
						' is not a valid currency amount'),
						'Error');
				$valid = false;
			}
	
			// Check that award is a y or n and convert it to boolean
			if (!(trim(strtolower($data[$index][Catalog::$columnMap[AWARD]])) == 'y' ||
	  			trim(strtolower($data[$index][Catalog::$columnMap[AWARD]])) == 'n')) {

				$rowNum = $index+1;
				JFactory::getApplication()->
				enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_2', 
					'Row '.$rowNum.' in spreadsheet. Award column must have a value of y or n'),
					'Error');
				$valid = false;
			}
	
				
			// Check that new is a y or n
			if (!(trim(strtolower($data[$index][Catalog::$columnMap["New"]])) == 'y' ||
				trim(strtolower($data[$index][Catalog::$columnMap["New"]])) == 'n')) {

				$rowNum = $index+1;
				JFactory::getApplication()->
					enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_2', 
					'Row '.$rowNum.' in spreadsheet. New column must have a value of y or n'),
					'Error');
				$valid = false;
			}
	
			// Height should be integer
			if (!filter_var($data[$index][Catalog::$columnMap["Height"]], FILTER_VALIDATE_INT)) {
				$rowNum = $index+1;
				JFactory::getApplication()->
					enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_2', 
					'Row '.$rowNum.' in spreadsheet. Height column must be a number'),
					'Error');
				$valid = false;
			}

			// Spread should be integer
			if (!filter_var($data[$index][Catalog::$columnMap["Spread"]], FILTER_VALIDATE_INT)) {
				$rowNum = $index+1;
				JFactory::getApplication()->
					enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_2', 
					'Row '.$rowNum.' in spreadsheet. Spread column must be a number'),
					'Error');
				$valid = false;
			}
		}
	
		return 	$valid;
	}
	
	
	static function process ($inputFileName) {
		
		JLog::add('in Catalog::process', JLog::DEBUG, 'com_mfn');
		JLog::add('MEMORY='.memory_get_usage(), JLog::DEBUG, 'com_mfn');
		
		
		$index = 0;

		$db = JFactory::getDbo();

		Catalog::init($db);

		// Setup some common values
		$today = date('Y-m-d H:i:s');
		$user = JFactory::getUser();
		$user_id = $user->id;

		
		// ******************** TEST ************************************
		//
//		NewPhoto::addExistingPhotosToVirtuemart($user_id, $today, $db);
//		return;
		
		
		JLog::add('in Catalog::process $fileName='.$inputFileName, JLog::DEBUG, 'com_mfn');
		$data = Catalog::read($inputFileName);

		// Get a list of the custom fields
		$fields = Catalog::getProductCustomFieldIds ($db);

		// Validate the data in the spreadsheet
		if (!Catalog::isValidSpreadSheet($data)) {
			JFactory::getApplication()->
				enqueueMessage (JText::_('CATALOGUL_PROC_ERR_4'),	'Error');
			return -1;
		}
		
		// Convert all of the columns in the spreadsheet that are not in the correct format
		Catalog::convertSpreadSheet($data);
					
		// Unpublish all existing products
		Catalog::unpublish($today, $db);

		// Delete all media so we can only add the ones in the new catalog
		NewPhoto::deleteAllMediaForProducts($db);
		
		// Build a list of the SKU's in the spreadsheet
		$listOfSKUs = array();
		
		// For each row in spreadsheet, excluding first row of headings process the product 
		$size = count($data);
		for ($index = 1; $index < $size; ++$index) {

			$SKU = $data[$index][Catalog::$columnMap["SKU"]];

			// Only process the row if the SKU has not already been processed as this causes all kinds of
			// DB logic issues.
			if (Catalog::isUniqueSKU($listOfSKUs, $SKU)) {
				array_push($listOfSKUs, $SKU);
				try {
					Catalog::processProduct($data[$index], $today, $user_id, $fields, $db);
				} catch (Exception $e) {
					JFactory::getApplication()->
						enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_3',$index+1, $SKU, $e->getMessage(), 
							$e->getTraceAsString()),'Error');
				}
			} else {
				// Skip the row as the SKU is not unique
				JFactory::getApplication()->
					enqueueMessage (JText::sprintf('CATALOGUL_PROC_ERR_NON_UNIQUE_SKU',$SKU, $index+1),'Error');
			}
		}

		// Publish all products that were updated so they show in the catalog
		Catalog::publish($today, $db);

		// Free up the memory
		$data = NULL;

		JLog::add('MEMORY='.memory_get_usage(), JLog::DEBUG, 'com_mfn');
		
		return $index-1;
	}

	
	static function processProduct ($row, $today, $user_id, $fields, $db) {
		JLog::add('entry Catalog::processProduct, $row='.print_r($row,true), JLog::DEBUG, 'com_mfn');
		try {
			$db->transactionStart();
				
			$query = $db->getQuery(true);

			// Check to ensure that everything has a SKU
			if ($row[Catalog::$columnMap["SKU"]] == NULL) throw new Exception('Plant has missing SKU');
					
			// Find product ID based on product SKU
			$query
				->select('virtuemart_product_id')
				->from('#__virtuemart_products')
				->where('product_sku = "'.$row[Catalog::$columnMap["SKU"]].'"');
			$db->setQuery($query);
			$product_id = $db->loadResult();
			JLog::add('virtuemart_product_id='.$product_id.'for SKU='.$row[Catalog::$columnMap["SKU"]], JLog::DEBUG, 'com_mfn');
				
			if ($product_id != NULL) {
				// Product already exists
				Catalog::modifyProduct($row, $product_id, $today, $user_id, $fields, $db);
			} else {
				// Product needs to be created
				Catalog::createProduct($row, $today, $user_id, $fields, $db);
			}

			$db->transactionRollback();
			//$db->transactionCommit();

		} catch (Exception $e) {
			JLog::add($e->getMessage(), JLog::DEBUG, 'com_mfn');
			$db->transactionRollback();
			throw $e;
		}
	}
	
	static function createProduct ($row, $today, $user_id, $fields, $db) {
		
		JLog::add('In createProduct', JLog::DEBUG, 'com_mfn');

		$vendor_id = Catalog::getVendorId($row, NULL, $db);
		$vendor_currency = Catalog::getVendorCurrency($row, $vendor_id, $db);
		$manufacturer_id = Catalog::getManufacturerId ($row, $db);
		$product_id = Catalog::insertProduct ($row, $vendor_id, $today, $user_id, $db);
		Catalog::insertLanguage ($row, $product_id, $db);
		Catalog::insertPrice ($row, $product_id, $vendor_currency, $today, $user_id, $db);
		Catalog::insertManufacturerXRef ($row, $product_id, $manufacturer_id, $db); 
		Catalog::insertProductCustomFieldIds ($row, $product_id, $fields, $today, $user_id, $db);
		Catalog::insertProductCategory ($row, $product_id, $db);
		if (trim($row[Catalog::$columnMap[PHOTOGRAPH]]) != '') {
			if (!NewPhoto::createMediaForProduct ($row[Catalog::$columnMap[PHOTOGRAPH]], $product_id, $db)) {
				JFactory::getApplication()->
				enqueueMessage (JText::sprintf('CATALOGUL_PROC_WARNING', 
					'Photograph named '.$row[Catalog::$columnMap[PHOTOGRAPH]].
					' is referenced in the catalogue but has not been uploaded to the website'),
					'Warning');
			}
		}
	}
		
	static private function	getVendorId ($row, $product_id, $db) {
		$query = $db->getQuery(true);
		$query
			->select('IF (COUNT(virtuemart_vendor_id) = 0, 1, virtuemart_vendor_id) AS vendor_id')
			->from('#__virtuemart_products')
			->where('product_sku = "'.$row[Catalog::$columnMap["SKU"]].'"');
		$db->setQuery($query);
		$vendor_id = $db->loadResult();
		JLog::add('virtuemart_vendor_id='.$vendor_id.'for SKU='.$row[Catalog::$columnMap["SKU"]], JLog::DEBUG, 'com_mfn');
		
		return $vendor_id;
	}	
		
	static private function	getVendorCurrency ($row, $vendor_id, $db) {
		$query = $db->getQuery(true);
		$query
			->select('vendor_currency ')
			->from('#__virtuemart_vendors')
			->where('virtuemart_vendor_id = '.$vendor_id);
		$db->setQuery($query);
		$vendor_currency = $db->loadResult();
		JLog::add('vendor_currency='.$vendor_currency, JLog::DEBUG, 'com_mfn');
		
		return $vendor_currency;
	}
		
	static private function	getManufacturerId ($row, $db) {
		$query = $db->getQuery(true);
		$query
			->select('MIN(virtuemart_manufacturer_id)')
			->from('#__virtuemart_manufacturers');
		$db->setQuery($query);
		$manufacturer_id = $db->loadResult();
		JLog::add('manufacturer_id ='.$manufacturer_id, JLog::DEBUG, 'com_mfn');
		
		return $manufacturer_id;
	}
		
	static private function	insertProduct ($row, $vendor_id, $today, $user_id, $db) {
		
		$columns = array('virtuemart_vendor_id', 'product_sku', 'product_available_date',
				'created_on', 'created_by', 'modified_on', 'modified_by', 'published', 'product_special');
		$values = array($db->quote($vendor_id), $db->quote($row[Catalog::$columnMap["SKU"]]), $db->quote($today), $db->quote($today),
				$db->quote($user_id), $db->quote($today), $db->quote($user_id), 0, $db->quote($row[Catalog::$columnMap["New"]]));
		$query = $db->getQuery(true);
		$query
			->insert($db->quoteName('#__virtuemart_products'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		$db->setQuery($query);
		$db->execute();
		JLog::add('insert product values ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
		
		// Get the id of the newly inserted product
		$product_id = $db->insertid();		
		JLog::add('product_id = '.$product_id, JLog::DEBUG, 'com_mfn');
		return $product_id;
	}
		
	static private function	insertLanguage ($row, $product_id, $db) {
		$columns = array('virtuemart_product_id', 'product_s_desc', 'product_name', 'slug');

		// Removed the trophy symbol from the text then any leading or trailing spaces
//		$product_name = trim(preg_replace("/[^\w\s\d.,\/#!$%\^&\*;:{}=\-_`~()\'\"Ã‚Â¡Ã‚Â¿ÃƒâžÃƒÂ¤Ãƒâ‚¬ÃƒÂ Ãƒï¿½ÃƒÂ¡Ãƒâ€šÃƒÂ¢ÃƒÆ’ÃƒÂ£Ãƒâ€¦ÃƒÂ¥Ã‡ï¿½Ã‡Å½Ã„â€žÃ„â€¦Ã„â€šÃ„Æ’Ãƒâ€ ÃƒÂ¦Ã„â‚¬Ã„ï¿½Ãƒâ€¡ÃƒÂ§Ã„â€ Ã„â€¡Ã„Ë†Ã„â€°Ã„Å’Ã„ï¿½Ã„Å½Ã„â€˜Ã„ï¿½Ã„ï¿½ÃƒÂ°ÃƒË†ÃƒÂ¨Ãƒâ€°ÃƒÂ©ÃƒÅ ÃƒÂªÃƒâ€¹ÃƒÂ«Ã„Å¡Ã„â€ºÃ„ËœÃ„â„¢Ã„â€“Ã„â€”Ã„â€™Ã„â€œÃ„Å“Ã„ï¿½Ã„Â¢Ã„Â£Ã„Å¾Ã„Å¸Ã„Â¤Ã„Â¥ÃƒÅ’ÃƒÂ¬Ãƒï¿½ÃƒÂ­ÃƒÅ½ÃƒÂ®Ãƒï¿½ÃƒÂ¯Ã„Â±Ã„ÂªÃ„Â«Ã„Â®Ã„Â¯Ã„Â´Ã„ÂµÃ„Â¶Ã„Â·Ã„Â¹Ã„ÂºÃ„Â»Ã„Â¼Ã…ï¿½Ã…â€šÃ„Â½Ã„Â¾Ãƒâ€˜ÃƒÂ±Ã…Æ’Ã…â€žÃ…â€¡Ã…Ë†Ã…â€¦Ã…â€ Ãƒâ€“ÃƒÂ¶Ãƒâ€™ÃƒÂ²Ãƒâ€œÃƒÂ³Ãƒâ€�ÃƒÂ´Ãƒâ€¢ÃƒÂµÃ…ï¿½Ã…â€˜ÃƒËœÃƒÂ¸Ã…â€™Ã…â€œÃ…â€�Ã…â€¢Ã…ËœÃ…â„¢Ã¡ÂºÅ¾ÃƒÅ¸Ã…Å¡Ã…â€ºÃ…Å“Ã…ï¿½Ã…Å¾Ã…Å¸Ã…Â Ã…Â¡ÃˆËœÃˆâ„¢Ã…Â¤Ã…Â¥Ã…Â¢Ã…Â£ÃƒÅ¾ÃƒÂ¾ÃˆÅ¡Ãˆâ€ºÃƒÅ“ÃƒÂ¼Ãƒâ„¢ÃƒÂ¹ÃƒÅ¡ÃƒÂºÃƒâ€ºÃƒÂ»Ã…Â°Ã…Â±Ã…Â¨Ã…Â©Ã…Â²Ã…Â³Ã…Â®Ã…Â¯Ã…ÂªÃ…Â«Ã…Â´Ã…ÂµÃƒï¿½ÃƒÂ½Ã…Â¸ÃƒÂ¿Ã…Â¶Ã…Â·Ã…Â¹Ã…ÂºÃ…Â½Ã…Â¾Ã…Â»Ã…Â¼]/", '', $row[Catalog::$columnMap["Name"]]));
		$product_name = trim(preg_replace("/[^\w\d.,\/#!$%\^&\*;:{}=\-_`~()\'\"]+$/", '', trim($row[Catalog::$columnMap["Name"]])));
		
		$product_sku = preg_replace('/[^A-Za-z0-9\- \.]/', '', $row[Catalog::$columnMap["SKU"]]);
		$product_slug = preg_replace('/[^A-Za-z0-9\-]/', '-', strtolower($product_name).$product_sku);

// Removed because it does not remove quotes etc from slug which prevents the product from appearing in proiduct details page
//		$product_slug = str_replace(' ', '-', strtolower($product_name).$product_sku);
		$values = array($product_id, $db->quote($row[Catalog::$columnMap["Description"]]), $db->quote($product_name), $db->quote($product_slug));
		$query = $db->getQuery(true);
		$query
			->insert($db->quoteName('#__virtuemart_products_en_gb'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		$db->setQuery($query);
		$db->query();
		JLog::add('insert product name values ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
	}

	static private function	insertPrice ($row, $product_id, $vendor_currency, $today, $user_id, $db) {
		$columns = array('virtuemart_product_id','virtuemart_shoppergroup_id','product_price','override',
				'product_currency','price_quantity_start','price_quantity_end','created_on','created_by','modified_on','modified_by');
		$values = array($product_id, 0, $row[Catalog::$columnMap["UnitPrice"]], 0, $vendor_currency, 0, 0, $db->quote($today), 
				$user_id, $db->quote($today), $user_id);
		$query = $db->getQuery(true);
		$query
			->insert($db->quoteName('#__virtuemart_product_prices'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		$db->setQuery($query);
		$db->query();
		JLog::add('insert price values ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
	}

	static private function	insertManufacturerXRef ($row, $product_id, $manufacturer_id, $db) {
		$columns = array('virtuemart_product_id','virtuemart_manufacturer_id');
		$values = array($product_id, $manufacturer_id);
		$query = $db->getQuery(true);
		$query
			->insert('#__virtuemart_product_manufacturers')
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		$db->setQuery($query);
		$db->query();
		JLog::add('insert manfXref ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
	}	
	
	static private function	getProductCustomFieldIds ($db) {
		$query = $db->getQuery(true);
		$query
			->select('virtuemart_custom_id AS id,custom_title AS name')
			->from('#__virtuemart_customs')
			->where('field_type <> "R" AND field_type <> "Z"');
		$db->setQuery($query);
		$fields = $db->loadAssocList();
		JLog::add('custom fields= '.print_r($fields,true), JLog::DEBUG, 'com_mfn');
		return $fields;
	}	

	static private function	insertProductCustomFieldIds ($row, $product_id, $fields, $today, $user_id, $db) {
		$columns = array('virtuemart_product_id', 'virtuemart_custom_id', 'customfield_value', 'created_on', 'created_by', 'modified_on', 'modified_by');
		
		// For each custom field insert a row in the DB for this product
		foreach ($fields as $field) {
			
			// Check to ensure the field existing in the columnMap prior to inserting
			$values = array($product_id, $db->quote($field['id']), $db->quote($row[Catalog::$columnMap[$field['name']]]), $db->quote($today), 
					$user_id, $db->quote($today), $user_id);
			$query = $db->getQuery(true);
			$query
				->insert('#__virtuemart_product_customfields')
				->columns($db->quoteName($columns))
				->values(implode(',', $values));
			$db->setQuery($query);
			$db->query();
			JLog::add('insert custom field ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
		}
	}	

	
	static private function	getProductCategories ($db) {
		$query = $db->getQuery(true);
		$query
			->select('virtuemart_category_id AS id, category_name AS name')
			->from('#__virtuemart_categories_en_gb');
		$db->setQuery($query);
		$categories = $db->loadAssocList();
		JLog::add('categories = '.print_r($categories,true), JLog::DEBUG, 'com_mfn');
		return $categories;
	}

	
	static private function	insertProductCategory ($row, $product_id, $db) {
		
		$product_category = false;
		foreach (Catalog::$categoryMap as $category) {
			if ($category['sheetId'] == $row[Catalog::$columnMap['Category']]) {
				$product_category = $category['dbId'];
				break;
			}
		}
		
		if (!$product_category) throw new Exception('Missing or wrong category in spreadsheet for plant name: '.$row['Name']);
		JLog::add('product category found ='.$product_category, JLog::DEBUG, 'com_mfn');
		
		$columns = array('virtuemart_product_id','virtuemart_category_id','ordering');
		$values = array($product_id, $db->quote($product_category), 0);
		$query = $db->getQuery(true);
		$query
			->insert('#__virtuemart_product_categories')
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		$db->setQuery($query);
		$db->query();
		JLog::add('insert product category ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
	}
	
	
	static function modifyProduct ($row, $product_id, $today, $user_id, $fields, $db) {
	
		JLog::add('In modifyProduct', JLog::DEBUG, 'com_mfn');
		
		Catalog::updateProduct ($row, $product_id, $today, $user_id, $db);
		Catalog::updateLanguage ($row, $product_id, $db);
		Catalog::updatePrice ($row, $product_id, $today, $user_id, $db);
		Catalog::updateProductCustomFieldIds ($row, $product_id, $fields, $today, $user_id, $db);
		Catalog::updateProductCategory ($row, $product_id, $db);
		if (trim($row[Catalog::$columnMap[PHOTOGRAPH]]) != '') {
			if (!NewPhoto::createMediaForProduct ($row[Catalog::$columnMap[PHOTOGRAPH]], $product_id, $db)) {
				JFactory::getApplication()->
				enqueueMessage (JText::sprintf('CATALOGUL_PROC_WARNING', 
					'Photograph named '.$row[Catalog::$columnMap[PHOTOGRAPH]].
					' is referenced in the catalogue but has not been uploaded to the website'),
					'Warning');
			}
		}
	}

	
	static private function	updateProduct ($row, $product_id, $today, $user_id, $db) {

// Not needed as already converted earlier
//		$product_special = (strtolower($row[Catalog::$columnMap["New"]])  == 'y') ? 1 : 0;		
		$fields = array(
				$db->quoteName('modified_on') . ' = ' . $db->quote($today),
				$db->quoteName('modified_by') . ' = ' . $db->quote($user_id), 
				$db->quoteName('published') . ' = 0', 
				$db->quoteName('product_special') . ' = ' . $db->quote($row[Catalog::$columnMap["New"]])
		);
		$conditions = array(
				$db->quoteName('virtuemart_product_id') . ' = ' . $db->quote($product_id)
		);
		
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__virtuemart_products'))
			->set($fields)
			->where($conditions);
		$db->setQuery($query);
		$db->execute();
		JLog::add('update product values ='.implode(',', $fields).'New column contains='.$row[Catalog::$columnMap["New"]].' for $row='.print_r($row, true), JLog::DEBUG, 'com_mfn');
/*		
		$affectedRows = $db->getAffectedRows();
		if ($affectedRows != 1) {
			echo '<p>product values='.implode(',', $fields).'</p>';
			echo '<p>SQL='.print_r($query, true).'</p>';
			throw new Exception('Update of Product publish status failed. $product_id = '.$product_id.
				'updated rows = '.$affectedRows);
		}
*/
	}
		

	static private function	updateLanguage ($row, $product_id, $db) {
		//$product_name = preg_replace('/[^A-Za-z0-9\- \.]/', '', $row[Catalog::$columnMap["Name"]]);

//		Catalog::hex_dump($row[Catalog::$columnMap["Name"]]);
		
		// Removed the trophy symbol from the text then any leading or trailing spaces
		// Removed the trophy symbol from the text then any leading or trailing spaces
//		$product_name = trim(preg_replace("/[^\w\s\d.,\/#!$%\^&\*;:{}=\-_`~()\'\"Ã‚Â¡Ã‚Â¿ÃƒâžÃƒÂ¤Ãƒâ‚¬ÃƒÂ Ãƒï¿½ÃƒÂ¡Ãƒâ€šÃƒÂ¢ÃƒÆ’ÃƒÂ£Ãƒâ€¦ÃƒÂ¥Ã‡ï¿½Ã‡Å½Ã„â€žÃ„â€¦Ã„â€šÃ„Æ’Ãƒâ€ ÃƒÂ¦Ã„â‚¬Ã„ï¿½Ãƒâ€¡ÃƒÂ§Ã„â€ Ã„â€¡Ã„Ë†Ã„â€°Ã„Å’Ã„ï¿½Ã„Å½Ã„â€˜Ã„ï¿½Ã„ï¿½ÃƒÂ°ÃƒË†ÃƒÂ¨Ãƒâ€°ÃƒÂ©ÃƒÅ ÃƒÂªÃƒâ€¹ÃƒÂ«Ã„Å¡Ã„â€ºÃ„ËœÃ„â„¢Ã„â€“Ã„â€”Ã„â€™Ã„â€œÃ„Å“Ã„ï¿½Ã„Â¢Ã„Â£Ã„Å¾Ã„Å¸Ã„Â¤Ã„Â¥ÃƒÅ’ÃƒÂ¬Ãƒï¿½ÃƒÂ­ÃƒÅ½ÃƒÂ®Ãƒï¿½ÃƒÂ¯Ã„Â±Ã„ÂªÃ„Â«Ã„Â®Ã„Â¯Ã„Â´Ã„ÂµÃ„Â¶Ã„Â·Ã„Â¹Ã„ÂºÃ„Â»Ã„Â¼Ã…ï¿½Ã…â€šÃ„Â½Ã„Â¾Ãƒâ€˜ÃƒÂ±Ã…Æ’Ã…â€žÃ…â€¡Ã…Ë†Ã…â€¦Ã…â€ Ãƒâ€“ÃƒÂ¶Ãƒâ€™ÃƒÂ²Ãƒâ€œÃƒÂ³Ãƒâ€�ÃƒÂ´Ãƒâ€¢ÃƒÂµÃ…ï¿½Ã…â€˜ÃƒËœÃƒÂ¸Ã…â€™Ã…â€œÃ…â€�Ã…â€¢Ã…ËœÃ…â„¢Ã¡ÂºÅ¾ÃƒÅ¸Ã…Å¡Ã…â€ºÃ…Å“Ã…ï¿½Ã…Å¾Ã…Å¸Ã…Â Ã…Â¡ÃˆËœÃˆâ„¢Ã…Â¤Ã…Â¥Ã…Â¢Ã…Â£ÃƒÅ¾ÃƒÂ¾ÃˆÅ¡Ãˆâ€ºÃƒÅ“ÃƒÂ¼Ãƒâ„¢ÃƒÂ¹ÃƒÅ¡ÃƒÂºÃƒâ€ºÃƒÂ»Ã…Â°Ã…Â±Ã…Â¨Ã…Â©Ã…Â²Ã…Â³Ã…Â®Ã…Â¯Ã…ÂªÃ…Â«Ã…Â´Ã…ÂµÃƒï¿½ÃƒÂ½Ã…Â¸ÃƒÂ¿Ã…Â¶Ã…Â·Ã…Â¹Ã…ÂºÃ…Â½Ã…Â¾Ã…Â»Ã…Â¼]/", '', $row[Catalog::$columnMap["Name"]]));
		$product_name = trim(preg_replace("/[^\w\d.,\/#!$%\^&\*;:{}=\-_`~()\'\"]+$/", '', trim($row[Catalog::$columnMap["Name"]])));
		
		
//		$product_name = trim(preg_replace('/Ã®â‚¬ï¿½/', '', $row[Catalog::$columnMap["Name"]]));
//		Catalog::hex_dump($product_name);
		
		$product_sku = preg_replace('/[^A-Za-z0-9\- \.]/', '', $row[Catalog::$columnMap["SKU"]]);
		$product_slug = preg_replace('/[^A-Za-z0-9\-]/', '-', strtolower($product_name).$product_sku);

		//$product_slug = str_replace(' ', '-', strtolower($product_name).$product_sku);
		
		$fields = array(
				$db->quoteName('product_s_desc') . ' = ' . 
					$db->quote($row[Catalog::$columnMap["Description"]]),
				$db->quoteName('product_name') . ' = ' . $db->quote($product_name),
				$db->quoteName('slug') . ' = ' .  $db->quote($product_slug)
		);
		$conditions = array(
				$db->quoteName('virtuemart_product_id') . ' = ' . $db->quote($product_id)
		);
		
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__virtuemart_products_en_gb'))
			->set($fields)
			->where($conditions);
		$db->setQuery($query);
		$db->execute();
		JLog::add('update product name values ='.implode(',', $fields), JLog::DEBUG, 'com_mfn');
	}
		


	static private function	updatePrice ($row, $product_id, $today, $user_id, $db) {
	
		$fields = array(
				$db->quoteName('product_price') . ' = ' . $db->quote($row[Catalog::$columnMap["UnitPrice"]]),
				$db->quoteName('modified_on') . ' = ' . $db->quote($today),
				$db->quoteName('modified_by') . ' = ' . $db->quote($user_id)
		);
		$conditions = array(
				$db->quoteName('virtuemart_product_id') . ' = ' . $db->quote($product_id)
		);
	
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__virtuemart_product_prices'))
			->set($fields)
			->where($conditions);
		$db->setQuery($query);
		$db->execute();

		JLog::add('update product price values ='.implode(',', $fields), JLog::DEBUG, 'com_mfn');
/*
		$affectedRows = $db->getAffectedRows();
		if ($affectedRows != 1) throw new Exception('Update of Product Price failed. $product_id = '.
				$product_id.' for price = '.$row[Catalog::$columnMap["UnitPrice"]].
				'updated rows = '.$affectedRows);
*/
	}


	static private function	updateProductCustomFieldIds ($row, $product_id, $fields, $today, $user_id, $db) {
		// For each custom field update the appropriate row in the DB for this product
		foreach ($fields as $field) {
			$values = array(
					'customfield_value' . ' = ' . $db->quote($row[Catalog::$columnMap[$field['name']]]),
					'modified_on' . ' = ' . $db->quote($today),
					'modified_by' . ' = ' . $db->quote($user_id)
			);
			$conditions = array(
					'virtuemart_custom_id' . ' = ' . $db->quote($field['id']),
					'virtuemart_product_id' . ' = ' . $db->quote($product_id)
			);
			
			$query = $db->getQuery(true);
			$query
				->update($db->quoteName('#__virtuemart_product_customfields'))
				->set($values)
				->where($conditions);
			$db->setQuery($query);
			$db->execute();
			JLog::add('update custom field ='.implode(',', $values), JLog::DEBUG, 'com_mfn');
/*
			$affectedRows = $db->getAffectedRows();
			if ($affectedRows != 1) throw new Exception('Update of Product Custom Fields failed. $product_id = '.
					$product_id.' for field named = '.$row[Catalog::$columnMap[$field['name']]].
					'updated rows = '.$affectedRows);
*/
		}
	}	


	static private function	updateProductCategory ($row, $product_id, $db) {
		
		$product_category = false;
		foreach (Catalog::$categoryMap as $category) {
			if ($category['sheetId'] == $row[Catalog::$columnMap['Category']]) {
				$product_category = $category['dbId'];
				break;
			}
		}
		
		if (!$product_category) throw new Exception('Missing or wrong category in spreadsheet for plant name: '.$row['Name']);
		JLog::add('product category found ='.$product_category, JLog::DEBUG, 'com_mfn');
		
		$fields = array(
				$db->quoteName('virtuemart_category_id') . ' = ' . $db->quote($product_category)
		);
		$conditions = array(
				$db->quoteName('virtuemart_product_id') . ' = ' . $db->quote($product_id)
		);
		
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__virtuemart_product_categories'))
			->set($fields)
			->where($conditions);
		$db->setQuery($query);
		$db->execute();
		
		JLog::add('update product category ='.implode(',', $fields), JLog::DEBUG, 'com_mfn');
	}
	
	
	static private function unpublish($today, $db) {
		// Set all existing product status to unpublished
//		Catalog::setPublished(false, $today, $db);
		$published = 0;
		$fields = array(
				$db->quoteName('published') . ' = '. $db->quote($published)
		);
		
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__virtuemart_products'))
			->set($fields);
		$db->setQuery($query);
		$db->execute();
		$affectedRows = $db->getAffectedRows();
		JLog::add('unpublish #affected rows= '.$affectedRows, JLog::DEBUG, 'com_mfn');
		
	}

	
	static private function publish($today, $db) {
		// Sett all products just updated to published
//		Catalog::setPublished(true, $today, $db);
		$published = 1;
		$fields = array(
				$db->quoteName('published') . ' = '. $db->quote($published)
		);
		$conditions = array(
				$db->quoteName('modified_on') . ' = ' . $db->quote($today)
		);
		
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__virtuemart_products'))
			->set($fields)
			->where($conditions);
		$db->setQuery($query);
		$db->execute();
		$affectedRows = $db->getAffectedRows();
		JLog::add('publish #affected rows= '.$affectedRows, JLog::DEBUG, 'com_mfn');
	}

	static private function setPublished($state, $today, $db) {
		$published = ($state) ? 1 : 0;
		$fields = array(
				$db->quoteName('published') . ' = '. $db->quote($published)
		);
		$conditions = array(
				$db->quoteName('modified_on') . ' = ' . $db->quote($today)
		);
		
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__virtuemart_products'))
			->set($fields)
			->where($conditions);
		$db->setQuery($query);
		$db->execute();
		$affectedRows = $db->getAffectedRows();
		JLog::add('setPublished to $published='.$published.' affected '.$affectedRows.' rows', JLog::DEBUG, 'com_mfn');
	}
	
	static private function transformMoisture($value) {
		$pattern = '/[^[1-3|,]]*/';
		$tempVal = preg_replace($pattern,'',$value);
		$patterns = array();
		$patterns[0] = '/[1]+/';
		$patterns[1] = '/[2]+/';
		$patterns[2] = '/[3]+/';
		$replacements = array();
		$replacements[0] = ' Dry';
		$replacements[1] = ' Moist';
		$replacements[2] = ' Wet';
		$newString = preg_replace($patterns, $replacements, $tempVal);
		JLog::add('Catalog::transformMoisture from $value='.$value.' to $newString='.$newString, JLog::DEBUG, 'com_mfn');
		return trim($newString);
	}
	
	static private function transformPosition($value) {
		$pattern = '/[^[A-C|a-c|,]]*/';
		$tempVal = preg_replace($pattern,'',$value);
		//		printf("Position transform value=[%s], tempVal=[%s]<br>", $value, $tempVal);
		$patterns = array();
		$patterns[0] = '/[a|A]+/';
		$patterns[1] = '/[b|B]+/';
		$patterns[2] = '/[c|C]+/';
		$replacements = array();
		$replacements[0] = 'A';
		$replacements[1] = 'B';
		$replacements[2] = 'C';
		$newString = preg_replace($patterns, $replacements, $tempVal);
		JLog::add('Catalog::transformPosition from $value='.$value.' to $newString='.$newString, JLog::DEBUG, 'com_mfn');
		return trim($newString);
	}
	
	static private function transformPeriod($value) {

		// Match anything that is not a digit or a comma and remove it
		// Remove commas at the beginning and the end of the string
		$pattern = '/[^0-9,]+/';
		$newString = trim(preg_replace($pattern,'',$value),',');
		
		// Remove any leading or trailing commas
		
		
		// Find all the digitis for each month 
//		$pattern = '/[^[\d+|,]]*/';
//		$pattern = '/(\d+).*,(\d+)|(\d+)/';
		$pattern = '/\d+/';
		preg_match_all($pattern, $value, $matches);

		$newString = '';
		foreach ($matches[0] as $month) {
			if ($newString != '') $newString .= ',';
			$newString .= date("M", mktime(0, 0, 0, $month, 1, 2000));
		}
/*		
		if (count($matches)>2) {
			$newString = date("M", mktime(0, 0, 0, $matches[0][0], 1, 2000)).'-'.
				date("M", mktime(0, 0, 0, $matches[0][count($matches)-1], 1, 2000));
		} else if (count($matches)==2) {
			$newString = date("M", mktime(0, 0, 0, $matches[0][1], 1, 2000));
		} else {
			$newString = '';
		}
*/
		
/*
		$patterns = array();
		for ($i = 1; $i <= 12; $i++) {
			$patterns[$i-1] = '/(((?<=,)|(^))'.$i.'($|(?=,)))/';
		}
		$replacements = array();
		for ($i = 1; $i <= 12; $i++) {
			$replacements[$i-1] = date("M", mktime(0, 0, 0, $i, 1, 2000));
		}
		$newString = preg_replace($patterns, $replacements, $tempVal);
*/
		JLog::add('Catalog::transformPeriod from $value='.$value.' to $newString='.$newString, JLog::DEBUG, 'com_mfn');
		JLog::add('Catalog::transformPeriod $matches='.print_r($matches, true), JLog::DEBUG, 'com_mfn');
		return trim($newString);
	}
	
	static private function isUniqueSKU($listOfSKUs, $sku) {
		return in_array($sku, $listOfSKUs) ? false : true;
	}
	

	// Test routine to see the actual hex value of a string
	static function hex_dump($data, $newline="\n")
	{
		static $from = '';
		static $to = '';
	
		static $width = 16; # number of bytes per line
	
		static $pad = '.'; # padding for non-visible characters
	
		if ($from==='')
		{
			for ($i=0; $i<=0xFF; $i++)
			{
				$from .= chr($i);
				$to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
			}
		}
	
		$hex = str_split(bin2hex($data), $width*2);
		$chars = str_split(strtr($data, $from, $to), $width);
	
		$offset = 0;
		foreach ($hex as $i => $line)
		{
			echo sprintf('%6X',$offset).' : '.implode(' ', str_split($line,2)) . ' [' . $chars[$i] . ']' . $newline;
			$offset += $width;
		}
	}
	
}
