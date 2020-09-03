<?php
/**
 * Plugin Name:     Jadlog Tracking Code for WooCommerce
 * Description:     Adds Jadlog tracking to your WooCommerce store
 * Author:          Alysson Ailton da Silva
 * Author URI:      https://bitbucket.org/mostardals/
 * Text Domain:     woocommerce-jadlog-tracking
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         WC_Jadlog_Tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

new WC_Jadlog_Tracking();

/**
 * WooCommerce Jadlog Tracking main class.
 */
class WC_Jadlog_Tracking {

	/**
	 * Initialize the plugin public actions.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ), -1 );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'add_wc_jadlog_tracking_email' ), 10 );
		add_action( 'wp_ajax_woocommerce_jadlog_add_tracking_code', array( __CLASS__, 'ajax_add_tracking_code' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'my_account_jadlog_tracking_code' ), 2 );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'woocommerce-jadlog-tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 *  Add a custom email to the list of emails WooCommerce should load
	 *
	 * @since 0.1
	 * @param array $email_classes available email classes
	 * @return array filtered available email classes
	 */
	function add_wc_jadlog_tracking_email( $email_classes ) {
		require 'includes/class-wc-jadlog-tracking-email.php';

		$email_classes['WC_Jadlog_Tracking_Email'] = new WC_Jadlog_Tracking_Email();

		return $email_classes;
	}

	/**
	 * Register tracking code metabox.
	 */
	public static function register_metabox() {
		add_meta_box(
			'wc_jadlog_tracking',
			'Jadlog',
			array( __CLASS__, 'metabox_content' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Ajax - Add tracking code.
	 */
	public function ajax_add_tracking_code() {
		check_ajax_referer( 'jadlog' );

		$order = wc_get_order( $_POST['order_id'] );

		self::update_tracking_code( $order, $_POST['tracking_code'] );

		$tracking_code = self::get_tracking_code( $order );

		wp_send_json_success( $tracking_code );
	}

	/**
	 * Tracking code metabox content.
	 *
	 * @param WC_Post $post Post data.
	 */
	public static function metabox_content( $post ) {
		$tracking_code = self::get_tracking_code( $post->ID );

		if ( ! $tracking_code || '' === $tracking_code ) {
			$input_data = array(
				'label' => __( 'Add tracking code', 'woocommerce-jadlog-tracking' ),
				'class' => 'dashicons-plus',
				'value' => '',
			);
		} else {
			$input_data = array(
				'label' => __( 'Edit tracking code', 'woocommerce-jadlog-tracking' ),
				'class' => 'dashicons-edit',
				'value' => $tracking_code,
			);
		}

		wp_enqueue_style( 'woocommerce-jadlog-tracking', self::get_url() . 'assets/css/tracking.css', array() );
		wp_enqueue_script( 'woocommerce-jadlog-tracking', self::get_url() . 'assets/js/tracking.js', array( 'jquery' ) );
		?>
		<script type="text/javascript">
			var jadlog_order_id = <?php echo $post->ID; ?>;
			var jadlog_security_nonce = "<?php echo wp_create_nonce( 'jadlog' ); ?>";
		</script>
		<div class="jadlog-tracking-code">
			<fieldset>
				<label for="add-jadlog-code"><?php esc_html_e( $input_data['label'], 'woocommerce-jadlog-tracking' ); ?></label>
				<input type="text" id="add-jadlog-code" name="jadlog_tracking" value="<?php echo $input_data['value']; ?>" />
				<button type="button" class="button-secondary <?php echo $input_data['class']; ?>" aria-label="<?php esc_attr_e( $input_data['label'], 'woocommerce-jadlog-tracking' ); ?>"></button>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Get tracking code.
	 *
	 * @param  WC_Order|int $order Order ID or order data.
	 *
	 * @return string
	 */
	public static function get_tracking_code( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( method_exists( $order, 'get_meta' ) ) {
			return $order->get_meta( '_jadlog_tracking_code', true );
		} else {
			return get_post_meta( $order->ID, '_jadlog_tracking_code', true );
		}

		return false;
	}

	/**
	 * Update tracking code.
	 *
	 * @param  WC_Order|int $order         Order ID or order data.
	 * @param  string       $tracking_code Tracking code.
	 */
	public static function update_tracking_code( $order, $tracking_code ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		$old_tracking_code = self::get_tracking_code( $order );

		if ( empty( $tracking_code ) ) {

			 delete_post_meta( $order->id, '_jadlog_tracking_code' );

			 $order->add_order_note( sprintf( __( 'Removed Jadlog tracking code: %s', 'woocommerce-jadlog-tracking' ), $old_tracking_code ) );

		} else {

			update_post_meta( $order->id, '_jadlog_tracking_code', $tracking_code );

			if ( ! $old_tracking_code ) {
				$order->add_order_note( sprintf( __( 'Added Jadlog tracking code: %s', 'woocommerce-jadlog-tracking' ), $tracking_code ) );
				self::trigger_tracking_code_email( $order, $tracking_code );
			} else {
				$order->add_order_note( sprintf( __( 'Updated Jadlog tracking code to: %s', 'woocommerce-jadlog-tracking' ), $tracking_code ) );
			}
		}
	}

	/**
	 * Trigger tracking code email notification.
	 *
	 * @param WC_Order $order         Order data.
	 * @param string   $tracking_code The Jadlog tracking code.
	 */
	public static function trigger_tracking_code_email( $order, $tracking_code ) {
		$mailer       = WC()->mailer();
		$notification = $mailer->emails['WC_Jadlog_Tracking_Email'];

		if ( 'yes' === $notification->enabled ) {
			if ( method_exists( $order, 'get_id' ) ) {
				$notification->trigger( $order->get_id(), $order, $tracking_code );
			} else {
				$notification->trigger( $order->id, $order, $tracking_code );
			}
		}
	}

	/**
	 * Display the order tracking code in order details and the tracking history.
	 *
	 * @param WC_Order $order Order data.
	 */
	public static function my_account_jadlog_tracking_code( $order ) {
		$tracking_code = self::get_tracking_code( $order );

		if ( ! empty( $tracking_code ) ) {
			?>
			<h2 id="wc-jadlog-tracking" class="wc-jadlog-tracking__title">
				<?php esc_html_e( 'Jadlog delivery tracking', 'woocommerce-jadlog-tracking' ); ?>
			</h2>

			<p class="wc-jadlog-tracking__description"><?php echo esc_html( __( 'Tracking code:', 'woocommerce-jadlog-tracking' ) ); ?></p>

			<table class="wc-jadlog-tracking__table woocommerce-table shop_table shop_table_responsive">
				<tbody>
					<tr>
						<th><?php echo esc_html( $tracking_code ); ?></th>
						<td>
							<form method="POST" target="_blank" rel="nofollow noopener noreferrer" action="https://www.jadlog.com.br/siteDpd/tracking.jad" class="wc-jadlog-tracking__form">
								<input type="hidden" name="cte" value="<?php echo $tracking_code; ?>">
								<button class="wc-jadlog-tracking__button button">
									<?php esc_attr_e( 'Query on Jadlog', 'woocommerce-jadlog-tracking' ); ?>
								</button>
							</form>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		}
	}

	/**
	 * Get plugin url.
	 *
	 * @return string
	 */
	static function get_url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	static function get_path() {
		return plugin_dir_path( __FILE__ );
	}

}
