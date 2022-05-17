<?php
/**
 * Page for Bitazza Order Payment Status checking.
 *
 */

defined( 'ABSPATH' ) || exit;


// Register shortcode for execute payment callback from Bitazza
add_shortcode( 'bitazza_order_status', 'bitazza_order_status' );

function bitazza_order_status() {
    $ref_no = trim($_REQUEST["ref_no"]);
    $order_id = absint($ref_no);
    $order = new WC_Order($order_id);

    if ( ! $order ) {
        return;
    }

    $order_meta = get_post_meta($order_id);

    if(isset($order_meta['bitazza_trasaction_id'])){
        $bitazza_trasaction_id = $order_meta['bitazza_trasaction_id'][0];
        $bitazza_payment_url = "https://transfer.bitazza.com/merchant/invoice?id=". $bitazza_trasaction_id;
	}
	
	if(isset($order_meta['Currency'])){
        $currency_symbol = $order_meta['Currency'][0];
        $currency_amount = $order_meta['bitazza_amount'][0];
    }

    ob_start();

    $order_items           = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
    $show_purchase_note    = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
    $show_customer_details = is_user_logged_in() && $order->get_user_id() === get_current_user_id();
    $downloads             = $order->get_downloadable_items();
    $show_downloads        = $order->has_downloadable_item() && $order->is_download_permitted();

    if ( $show_downloads ) {
        wc_get_template(
            'order/order-downloads.php',
            array(
                'downloads'  => $downloads,
                'show_title' => true,
            )
        );
    }
    ?>

<script type="text/javascript">
	jQuery(document).ready(function($){

		$.get('/?wc-api=bitazza_payment_status&ref_no=<?php echo $order_id ?>', (data, status) => {
			// alert(data['status']);
		});
	});
</script>

<!-- <script>
window.open("<? echo $bitazza_payment_url ?>",'popUpWindow','height=700,width=700,left=50,top=50,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no, status=yes')
</script> -->

<section class="woocommerce-order-details">
	<?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

	<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'woocommerce' ); ?></h2>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

		<thead>
			<tr>
				<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
				<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			do_action( 'woocommerce_order_details_before_order_table_items', $order );

			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();

				wc_get_template(
					'order/order-details-item.php',
					array(
						'order'              => $order,
						'item_id'            => $item_id,
						'item'               => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'      => $product ? $product->get_purchase_note() : '',
						'product'            => $product,
					)
				);
			}

			do_action( 'woocommerce_order_details_after_order_table_items', $order );
			?>
		</tbody>

		<tfoot>
			<?php
			foreach ( $order->get_order_item_totals() as $key => $total ) {
				?>
					<tr>
						<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
						<td><?php echo ( 'payment_method' === $key ) ? esc_html( $total['value'] ) : wp_kses_post( $total['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
					<?php
			}
			?>
				<tr>
					<th><?=$currency_symbol ?></th>
					<td><span class="woocommerce-Price-amount amount"><?=$currency_amount ?></span> <a href="<?=$bitazza_payment_url ?>" target="_blank" class='button'>Paywith Crypto</a></td>
				</tr>
		</tfoot>
	</table>

    

	<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>
    
    <?php
    /**
     * Action hook fired after the order details.
     *
     * @since 4.4.0
     * @param WC_Order $order Order data.
     */
    // do_action( 'woocommerce_after_order_details', $order );

    // if ( $show_customer_details ) {
    //     wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
    // } 
    return ob_get_clean();

    // ob_start();
    // include_once __DIR__ . 'bitazza-order-template.php';
    // return ob_get_clean();
    // return "xxxx";
}