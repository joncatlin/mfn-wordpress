<?php
defined( 'ABSPATH' ) or die( 'Not authorized!' );

//require_once JPATH_COMPONENT_SITE.DS.'helpers/Defines.php';
//require_once JPATH_COMPONENT_SITE.DS.'helpers/MFNLogger.php';
//require_once JPATH_BASE.DS.'3rdpartycomponents/mPDF/mpdf.php';
//require_once JPATH_ROOT.'/components/com_mfn/helpers/MFNVirtuemartHelper.php';


//define( 'MPDF_PLUGIN_PATH', plugin_dir_path( 'wp-mpdf' ) );
include( ABSPATH . 'wp-content/plugins/wp-mpdf/mpdf/mpdf.php' );


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
		error_log('In makePDF, $withPrices='.$withPrices.PHP_EOL, 3, './debug.log' );

		// Set the MFN_UPLOADS_DIR name
		$tmp = wp_upload_dir();
		$upload_dir = $tmp['basedir'];

		// Create the mPDF object so we can use it to write out the HTML
		$margin_header = 10;
		$margin_footer = 10;
		$margin_top = 55;
		$margin_bottom = 18;
		$margin_left = 15;
		$margin_right = 15;
		$default_font_size = 8;
		$default_font = 'sans-serif';

		//$mpdf=new mPDF('c','A4',$default_font_size,$default_font,$margin_left,$margin_right,$margin_top,$margin_bottom,$margin_header,$margin_footer);
		$mpdf=new Mpdf('c','A4',$default_font_size,$default_font,$margin_left,$margin_right,$margin_top,$margin_bottom,$margin_header,$margin_footer);

		// Remove the old file
		if ($withPrices) {
			$file_name = ABSPATH . 'wp-content/uploads/AvailabilityListP.pdf';
		} else {
			$file_name = ABSPATH . 'wp-content/uploads/AvailabilityList.pdf';
		}

		// Delete any existing file
		if (file_exists($file_name)) unlink ($file_name);
		
		$mpdf->shrink_tables_to_fit=0; // Prevent table resizing
		
		// Create the document footer
		$documentHtml = CatalogPDF::$htmlFooter;
		$mpdf->WriteHTML($documentHtml, 2, true, false);


		// Get all product categories
		$args = array(
			   'taxonomy'     => 'product_cat',
			   'orderby'      => 'name',
			   'order'		  => 'asc',
			   'hierarchical' => 1,
			   'title_li'     => '',
			   'hide_empty'   => 0
		);

		error_log('Getting all categores'.PHP_EOL, 3, './debug.log' );

		try {
			$firstpage = true;
			$category = '';
			$documentHtml = '';
	
			$all_categories = get_categories( $args );

error_log('All categories = '.print_r($all_categories, true), 3, './debug.log' );


			foreach ($all_categories as $cat) 
			{
error_log('$cat = '.print_r($cat, true), 3, './debug.log' );

				if($cat->category_parent == 0 && $cat->count > 0) 
				{
					// Set the new category
					$category = $cat->name;

					// Get all the products for this castegory
					$prod_args = array(
						'category' => array( $cat->slug ),
						'status' => 'publish',
						'orderby' => 'name',
						'order' => 'asc',
						'limit' => -1
					);
					$products = wc_get_products( $prod_args );
	
					// Finish the table and throw a new page if this is not the first page
					if (!$firstpage) {
						$documentHtml .= '</tbody></table><pagebreak />';
					}
	
					// Write out the HTML
					$mpdf->WriteHTML($documentHtml, 2, false, false);
	
					// Reset the HTML text we are making
					$documentHtml = '';
					
					// Set up the new header
					$documentHtml .= CatalogPDF::$htmlHeaderPart1.$category.CatalogPDF::$htmlHeaderPart2.
						$upload_dir.'/MANOR-FARM-One-Line.png'.
						CatalogPDF::$htmlHeaderPart2a.$upload_dir.'/MFNQR.png'.
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

					foreach ($products as $product) {
						// Create a row in the html table for the catalog entry

						if ($product->get_attribute( 'pa_award' ) == "Yes") {
							// Remove any trophy symbol as it needs to be replaced with a smaller one
							$name = $product->get_name();
							$pos = strpos( $name, "<img");
							if ($pos > 0)
								$name_minus_trophy = substr ($name, 0, $pos);
							else
								$name_minus_trophy = $name;

							$documentHtml .= '<tr><td style="width:40%">'.$name_minus_trophy;

							$documentHtml .='<img style="max-height:12;" src="'.$upload_dir.'/trophy6.png'.'"/>';
						} else {
							$documentHtml .= '<tr><td style="width:40%">'.$product->get_name();
						}
						
						$documentHtml .= 
							'</td><td style="width:15%">'.$product->get_attribute( 'pa_flower-colour' ).
							'</td><td style="width:15%; text-align: center;">'.$product->get_attribute( 'pa_flowering-period' ).
							'</td><td style="width:10%; text-align: center;">'.$product->get_attribute( 'pa_height' ).
							'</td><td style="width:10%; text-align: center;">'.$product->get_attribute( 'pa_pot-size' );
						if ($withPrices) $documentHtml .= '</td><td style="width:10%; text-align: center;">'.$product->get_price_html();
						$documentHtml .= '</td></tr>';
					}						
				}       
			}

			// Finish the table and throw a new page if this is not the first page
			$documentHtml .= '</tbody></table>';

			// Write out the HTML
			$mpdf->WriteHTML($documentHtml, 2, false, false);

			// Write out the PDF			
			$mpdf->Output($file_name, 'F');

		} catch (Exception $e) {
			// JLog::add('Caught exception: '.$e->getMessage(), JLog::DEBUG, 'com_mfn');
			// $app = JFactory::getApplication ();
			// $app->enqueueMessage ('CatalogPDF-Error: ' . $e->getMessage());
			
			// Free up the mpdf object
			$mpdf = null;
		}
	}
}

?>
