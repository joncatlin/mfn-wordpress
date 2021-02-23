<?php

defined('_JEXEC') or die;
// 	<img src="'.JURI::base().'media/com_mfn/images/MANOR FARM LOGO.png'.'" width="100%" height="50%" />
// 			<td width="20%"><img src="'.JURI::base().'media/com_mfn/images/MFNQR.png'.'" /></td>
require_once JPATH_COMPONENT_SITE.DS.'helpers/Defines.php';
require_once JPATH_COMPONENT_SITE.DS.'helpers/MFNLogger.php';
require_once JPATH_BASE.DS.'3rdpartycomponents/mPDF/mpdf.php';
require_once JPATH_ROOT.'/components/com_mfn/helpers/MFNVirtuemartHelper.php';


class CatalogPDF {
	static $htmlHeaderPart1 = '<htmlpageheader name="mfnHeaderCat';
	static $htmlHeaderPart2 = '"><img src="';
	static $htmlHeaderPart2a = '" width="100%" />
	<table width="100%" style="font-family:helvetica; color: #000000; " >
		<tr>
			<td style="width:90%; margin: 0; padding: 0;">
				<p style="font-size: 9pt;">Address: Hellidon Road, Charwelton,Northants, NN11 3YZ</p>
				<p style="font-size: 9pt;">Tel: 01327 260285 Fax: 01327 263285 Email: gordon.catlin@manorfarmnurseries.com</p>
				<p style="font-size: 7pt;">All plants are offered subject to our normal terms and conditions as stated on our web site or in our curent catalogue</p>
				<p style="font-size: 7pt;">If you cannot find what you are looking for then please do not hesitate to contact us at the above address or visit our web site at www.manorfarmnurseries.com</p>
			</td>
			<td><img style="width:60px; height: 60px;" src="';
	static $htmlHeaderPart2b = '" /></td>
		</tr>
		<tr>
			<td style="font-size: 15pt; text-align: center;">
				<p>';
	
	static $htmlHeaderPart3 = '</p>
			</td>
		</tr>
	</table>
</htmlpageheader>
<sethtmlpageheader name="mfnHeaderCat';

	static $htmlHeaderPart4 = '" page="all" value="on" show-this-page="1" />';
	
	static $htmlFooter = '
<htmlpagefooter name="mfnFooter">
	<table width="100%" style="border-top: 1px solid #000000; vertical-align: bottom; font-family: serif; font-size: 9pt;
	    color: #000000; font-weight: bold;">
		<tr>
		    <td width="33%">Availability List</td>
			<td width="33%" align="center">Last Updated {DATE j F Y}</td>
		    <td width="33%" style="text-align: right;">{PAGENO}/{nbpg}</td>
	    </tr>
	</table>
</htmlpagefooter>
<sethtmlpagefooter name="mfnFooter" page="all" value="on" show-this-page="1" />';

	/*
	 * This function create the PDF
	 */
	static public function makePDF ($withPrices) {
		// Create the catalog data from the virtuemart database
		JLog::add('in CatalogPDF::makePDF', JLog::WARNING, 'com_mfn');
JLog::add('MEMORY PDF 1='.memory_get_usage(), JLog::WARNING, 'com_mfn');

		// Create the mPDF object so we can use it to write out the HTML
		$margin_header = 10;
		$margin_footer = 10;
		$margin_top = 55;
		$margin_bottom = 18;
		$margin_left = 15;
		$margin_right = 15;
		$default_font_size = 8;
		$default_font = 'sans-serif';
			
		$mpdf=new mPDF('c','A4',$default_font_size,$default_font,$margin_left,$margin_right,$margin_top,$margin_bottom,$margin_header,$margin_footer);
JLog::add('MEMORY PDF 5='.memory_get_usage(), JLog::WARNING, 'com_mfn');
		
		// Remove the old file
		if ($withPrices) {
			$file_name = JPATH_COMPONENT_SITE.DS.'tmp/AvailabilityListP.pdf';
		} else {
			$file_name = JPATH_COMPONENT_SITE.DS.'tmp/AvailabilityList.pdf';
		}

		// Delete any existing file
		if (file_exists($file_name)) unlink ($file_name);
		
		$mpdf->shrink_tables_to_fit=0; // Prevent table resizing
		
		// Create the document footer
		$documentHtml = CatalogPDF::$htmlFooter;
		$mpdf->WriteHTML($documentHtml, 2, true, false);
		
		try {
			$db = JFactory::getDbo();
JLog::add('MEMORY PDF 2='.memory_get_usage(), JLog::WARNING, 'com_mfn');
				
			$products = CatalogPDF::getData($db);
JLog::add('MEMORY PDF 3='.memory_get_usage(), JLog::WARNING, 'com_mfn');
				
			$firstpage = true;
			$category = '';
			$documentHtml = '';
			foreach ($products as $product) {
				// Check to see if the page title needs to be changed
				if ($product['category'] != $category) {
					
					// Finish the table and throw a new page if this is not the first page
					if (!$firstpage) {
						$documentHtml .= '</tbody></table><pagebreak />';
					}
					JLog::add('$category = '.$category.' $documentHtml = '.$documentHtml, JLog::DEBUG, 'com_mfn');
						
					// Write out the HTML
					$mpdf->WriteHTML($documentHtml, 2, false, false);

					// Set the new category
					$category = $product['category'];

					// Reset the HTML text we are making
					$documentHtml = '';
						
					// Set up the new header
					$documentHtml .= CatalogPDF::$htmlHeaderPart1.$category.CatalogPDF::$htmlHeaderPart2.
						JURI::base().'media/com_mfn/images/MANOR FARM One Line.png'.
						CatalogPDF::$htmlHeaderPart2a.JURI::base().'media/com_mfn/images/MFNQR.png'.
						CatalogPDF::$htmlHeaderPart2b.
						$category.CatalogPDF::$htmlHeaderPart3.$category.CatalogPDF::$htmlHeaderPart4;
					
					// Start the html table
					$documentHtml .= '<table style="table-layout: fixed; border-top: 2px solid #000000; width:100%"><thead>'.
					  '<tr><th style="width:40%">Name</th><th style="width:15%">Flower Colour</th>'.
					  '<th style="width:15%">Flowering Period</th><th style="width:10%; text-align: center;">Height</th>'.
					  '<th style="width:10%; text-align: center;">Pot Size</th>';
					if ($withPrices) $documentHtml .= '<th style="width:10%; text-align: center;">Unit Price</th>';
					$documentHtml .= '</tr></thead><tbody>';
						
					// Now not the first page
					$firstpage = false;

				}
				
				// Create a row in the html table for the catalog entry
				$documentHtml .= '<tr><td style="width:40%">'.$product['name'];

				if (CatalogPDF::getValue($product['custom_fields'],AWARD) == "1") {
					$documentHtml .='<img style="max-height:12;" src="'.JURI::base().'/'.ICON_PATH.'trophy6.png'.'"/>';
				}
				
				$documentHtml .= 
					'</td><td style="width:15%">'.CatalogPDF::getValue($product['custom_fields'],FLOWER_COLOUR).
					'</td><td style="width:15%; text-align: center;">'.CatalogPDF::getValue($product['custom_fields'],FLOWERING_PERIOD).
					'</td><td style="width:10%; text-align: center;">'.CatalogPDF::getValue($product['custom_fields'],HEIGHT).
					'</td><td style="width:10%; text-align: center;">'.CatalogPDF::getValue($product['custom_fields'],POT_SIZE);
				if ($withPrices) $documentHtml .= '</td><td style="width:10%; text-align: center;">'.number_format($product['price'],2);
				$documentHtml .= '</td></tr>';
			}
JLog::add('MEMORY PDF 99='.memory_get_usage(), JLog::WARNING, 'com_mfn');
				
			// Close the last table
			$documentHtml .= '</table>';
			$mpdf->WriteHTML($documentHtml, 2, false, true);
				
JLog::add('MEMORY PDF 4='.memory_get_usage(), JLog::WARNING, 'com_mfn');
/*				
			$margin_header = 10;
			$margin_footer = 10;
			$margin_top = 55;
			$margin_bottom = 18;
			$margin_left = 15;
			$margin_right = 15;
			$default_font_size = 8;
			$default_font = 'sans-serif';
			
			$mpdf=new mPDF('c','A4',$default_font_size,$default_font,$margin_left,$margin_right,$margin_top,$margin_bottom,$margin_header,$margin_footer);
JLog::add('MEMORY PDF 5='.memory_get_usage(), JLog::WARNING, 'com_mfn');
				
			// Remove the old file
			if ($withPrices) {
				$file_name = JPATH_COMPONENT_SITE.DS.'tmp/AvailabilityListP.pdf';
			} else {
				$file_name = JPATH_COMPONENT_SITE.DS.'tmp/AvailabilityList.pdf';
			}
			
			if (file_exists($file_name)) unlink ($file_name);

			$mpdf->shrink_tables_to_fit=0; // Prevent table resizing
*/
	//		$mpdf->mirrorMargins = 1; // Use different Odd/Even headers and footers and mirror margins
JLog::add('MEMORY PDF 6='.memory_get_usage(), JLog::WARNING, 'com_mfn');
//			$mpdf->WriteHTML($documentHtml);
JLog::add('MEMORY PDF 7='.memory_get_usage(), JLog::WARNING, 'com_mfn');
			$mpdf->Output($file_name, 'F');
JLog::add('MEMORY PDF 8='.memory_get_usage(), JLog::WARNING, 'com_mfn');
				
		} catch (Exception $e) {
			JLog::add('Caught exception: '.$e->getMessage(), JLog::DEBUG, 'com_mfn');
			$app = JFactory::getApplication ();
			$app->enqueueMessage ('CatalogPDF-Error: ' . $e->getMessage());
			
			// Free up the mpdf object
			$mpdf = null;
		}
	}
	
	static private function getData ($db) {
		JLog::add('in CatalogPDF::getData', JLog::DEBUG, 'com_mfn');
		
		// Get all of the published products
		$query = $db->getQuery(true);
/*
		$query
			->select(array('a.virtuemart_product_id', 'b.product_name as name', 'c.product_price as price', 'e.category_name as category'))
			->from($db->quoteName('#__virtuemart_products', 'a'))
			->join('INNER', $db->quoteName('#__virtuemart_products_en_gb', 'b') . ' ON (' . $db->quoteName('a.virtuemart_product_id') . ' = ' . $db->quoteName('b.virtuemart_product_id') . ')')
			->join('INNER', $db->quoteName('#__virtuemart_product_prices', 'c') . ' ON (' . $db->quoteName('a.virtuemart_product_id') . ' = ' . $db->quoteName('c.virtuemart_product_id') . ')')
			->join('INNER', $db->quoteName('#__virtuemart_product_categories', 'd') . ' ON (' . $db->quoteName('a.virtuemart_product_id') . ' = ' . $db->quoteName('d.virtuemart_product_id') . ')')
			->join('INNER', $db->quoteName('#__virtuemart_categories_en_gb', 'e') . ' ON (' . $db->quoteName('d.virtuemart_category_id') . ' = ' . $db->quoteName('e.virtuemart_category_id') . ')')
			->where($db->quoteName('a.published') . ' = 1')
			->order($db->quoteName('e.category_name') . ' ASC,' . $db->quoteName('b.product_name') . ' ASC');
*/
		$query
			->select(array('a.virtuemart_product_id', 'b.product_name as name', 'c.product_price as price', 'e.category_name as category'))
			->from($db->quoteName('#__virtuemart_products', 'a'))
			->join('INNER', $db->quoteName('#__virtuemart_products_en_gb', 'b') . ' ON (' . $db->quoteName('a.virtuemart_product_id') . ' = ' . $db->quoteName('b.virtuemart_product_id') . ')')
			->join('INNER', $db->quoteName('#__virtuemart_product_prices', 'c') . ' ON (' . $db->quoteName('a.virtuemart_product_id') . ' = ' . $db->quoteName('c.virtuemart_product_id') . ')')
			->join('INNER', $db->quoteName('#__virtuemart_product_categories', 'd') . ' ON (' . $db->quoteName('a.virtuemart_product_id') . ' = ' . $db->quoteName('d.virtuemart_product_id') . ')')
			->join('INNER', $db->quoteName('#__virtuemart_categories_en_gb', 'e') . ' ON (' . $db->quoteName('d.virtuemart_category_id') . ' = ' . $db->quoteName('e.virtuemart_category_id') . ')')
			->join('INNER', $db->quoteName('#__virtuemart_categories', 'f') . ' ON (' . $db->quoteName('d.virtuemart_category_id') . ' = ' . $db->quoteName('f.virtuemart_category_id') . ')')
			->where($db->quoteName('a.published') . ' = 1')
			->order($db->quoteName('f.ordering') . ' ASC,' . $db->quoteName('b.product_name') . ' ASC');
				
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		
		// Put the results in an associative array
		$results = $db->loadAssocList();		

		// For each published product get all the custom fields
		foreach ($results as &$product) {
			$query2 = $db->getQuery(true);
			$query2
				->select(array( 'a.customfield_value as value', 'b.custom_title as name'))
				->from($db->quoteName('#__virtuemart_product_customfields', 'a'))
				->join('INNER', $db->quoteName('#__virtuemart_customs', 'b') . ' ON (' . $db->quoteName('a.virtuemart_custom_id') . ' = ' . $db->quoteName('b.virtuemart_custom_id') . ')')
				->where($db->quoteName('a.virtuemart_product_id') . ' = ' . $product['virtuemart_product_id']);
				
			// Reset the query using our newly populated query object.
			$db->setQuery($query2);
			
			// Put the results in an associative array
			$custom_fields = $db->loadAssocList();
			$product['custom_fields'] = $custom_fields;
			
//			JLog::add('$product id = ' . $product['virtuemart_product_id'] . '$custom_fields = '.print_r($custom_fields, true), JLog::DEBUG, 'com_mfn');
		}
		
//		JLog::add('$results = '.print_r($results, true), JLog::DEBUG, 'com_mfn');
		
		return $results;
	}
	
	
	private static function getValue ($customFields, $fieldName) {
		foreach ($customFields as $field) {
			if ($field['name'] == $fieldName) {
				$value = mfnDisplayField($field['name'], $field['value']);
				JLog::add(__FUNCTION__.'Field name='.$fieldName.'value='.$value, JLog::DEBUG, 'com_mfn');
				return $value;
			}
		}
	}
}

?>
