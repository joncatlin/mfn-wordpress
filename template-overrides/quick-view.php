<?php
/**
 * The template for displaying the contents of the quick view modal.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/quick-view-pro/quick-view.php
 *
 * @version 1.0.0
 */

namespace Barn2\Plugin\WC_Quick_View_Pro;

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product_id		 = $product->get_id();
$modal_class	 = Util::get_modal_class( $product );
$data_attributes = Util::get_modal_data_attributes( $product ); // attributes escaped

do_action( 'wc_quick_view_pro_before_quick_view', $product );
?>
<div id="quick-view-<?php echo esc_attr( $product_id ); ?>" class="<?php echo esc_attr( $modal_class ); ?>" <?php echo $data_attributes; ?>>

	<?php if ( apply_filters( 'wc_quick_view_pro_can_view_quick_view_content', true, $product ) ) : ?>

		<?php do_action( 'wc_quick_view_pro_before_quick_view_product', $product ); ?>

		<div id="product-<?php echo esc_attr( $product_id ); ?>" <?php wc_product_class( apply_filters( 'wc_quick_view_pro_quick_view_product_class', array( 'wc-quick-view-product' ), $product ), $product_id ); ?>>
				<?php 
					do_action( 'wc_quick_view_pro_quick_view_before_product_details', $product ); 
				?>
				<?php if ( apply_filters( 'wc_quick_view_pro_show_product_details', true, $product ) ) : ?>

					<div class="wc-quick-view-product-summary summary entry-summary">
						<?php 
							// CHANGE BY JonC - removed the old action called 'wc_quick_view_pro_quick_view_product_details' and replaced it with the specific
							// action from the mfn plug in. THis is because I could not find a better mechanism to override it
							// The file is place at the top level of the child theme dir. I tried placing it in the WP docs suggested place but it did not get found
							do_action( 'mfn_quick_view_pro_quick_view_product_details', $product ); 
						?>
					</div>

				<?php endif; ?>
		</div>

		<?php do_action( 'wc_quick_view_pro_after_quick_view_product', $product ); ?>

	<?php else : ?>

		<?php do_action( 'wc_quick_view_pro_quick_view_content_hidden', $product ); ?>

	<?php endif; ?>
</div>

<?php
do_action( 'wc_quick_view_pro_after_quick_view', $product );
