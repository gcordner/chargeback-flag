<?php
/**
 * Plugin Name: Chargeback Flag for Customers
 * Plugin URI: https://geoffcordner.net/
 * Description: This plugin adds a 'chargeback' flag to user profiles and changes the order status for those users.
 * Version: 1.0
 * Author: Geoff Cordner
 * Author URI: https://geoffcordner.net/
 * Text Domain: chargeback-flag
 */

/**
 * Add extra field 'Chargeback' to the user profile.
 *
 * @param WP_User $user User object.
 */
function add_extra_chargeback_field( $user ) {
	// Create a nonce field.
	wp_nonce_field( 'save_chargeback_nonce', 'chargeback_nonce' );
	?>
	<h3><?php esc_html_e( 'Extra profile information', 'blank' ); ?></h3>

	<table class="form-table">
		<tr>
			<th><label for="chargeback"><?php esc_html_e( 'Chargeback' ); ?></label></th>
			<td>
				<input type="checkbox" name="chargeback" id="chargeback" value="yes" <?php checked( get_the_author_meta( 'chargeback', $user->ID ), 'yes' ); ?> /><br />
				<span class="description"><?php esc_html_e( 'Check if the customer has a chargeback.' ); ?></span>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'add_extra_chargeback_field' );
add_action( 'edit_user_profile', 'add_extra_chargeback_field' );

/**
 * Save the value of the 'Chargeback' field from the user profile.
 *
 * @param int $user_id ID of the user being saved.
 */
function save_extra_chargeback_field( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}

	// Verify the nonce field.
	if ( ! isset( $_POST['chargeback_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['chargeback_nonce'] ) ), 'save_chargeback_nonce' ) ) {
		return false;
	}

	$chargeback_status = isset( $_POST['chargeback'] ) ? sanitize_key( wp_unslash( $_POST['chargeback'] ) ) : 'no';
	update_user_meta( $user_id, 'chargeback', sanitize_text_field( $chargeback_status ) );
}
add_action( 'personal_options_update', 'save_extra_chargeback_field' );
add_action( 'edit_user_profile_update', 'save_extra_chargeback_field' );


/**
 * Change order status to 'on hold' for users with chargebacks.
 *
 * @param string   $status Order status.
 * @param int      $order_id Order ID.
 * @param WC_Order $order Order object.
 * @return string
 */
function custom_update_order_status( $status, $order_id, $order ) {
	// Get the user ID.
	$user_id = $order->get_user_id();

	// Get the user meta.
	$chargeback_status = get_user_meta( $user_id, 'chargeback', true );

	if ( 'yes' === $chargeback_status ) {
		// Add a note to the order.
		$order->add_order_note( __( 'Placed on hold because of prior chargebacks', 'text-domain' ) );

		// Return 'on-hold' status.
		return 'on-hold';
	}

	return $status;
}
add_filter( 'woocommerce_payment_complete_order_status', 'custom_update_order_status', 10, 3 );
