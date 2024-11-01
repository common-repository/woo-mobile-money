<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/*
Plugin Name: Woocommerce Mobile Money
Plugin URI: https://www.socialpay.co.ug/
Description: Extends WooCommerce by Adding the Socialpay Uganda Gateway. Socialpay allows MTN Mobile Money, Airtel Money, Visa, Easypay Wallet and SocialPay Merchant Account Payment methods.
Version: 1.3
Author: socialpay256
Author URI: https://www.socialpay.co.ug/
*/

add_filter( 'woocommerce_payment_gateways', 'wmm_socialpay_gateway' );
function wmm_socialpay_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Socialpay';
		return $methods;
}

// inserts class gateway
function woocommerce_socialpay_init() {

	if (!class_exists('WC_Gateway_Socialpay')) {

class WC_Gateway_Socialpay extends WC_Payment_Gateway {
function __construct() {
	$this->id = "socialpay";
	$this->method_title = __( "Woocommerce Mobile Money", 'socialpay' );
	$this->method_description = __( "Woocommerce Mobile Money - extends woocommerce to allow mtn and airtel mobile money payments", 'socialpay' );
	$this->title = __( "Woocommerce Mobile Money", 'socialpay' );
	$this->icon = null;
	$this->has_fields = true;
	$this->init_form_fields();
	$this->init_settings();
	foreach ( $this->settings as $setting_key => $value ) {
		$this->$setting_key = $value;
	}
	if ( is_admin() ) {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}
    add_action('woocommerce_receipt_socialpay', array($this, 'socialpay_receipt_page'));
}


public function init_form_fields() {
	$this->form_fields = array(
		'enabled' => array(
			'title'		=> __( 'Enable / Disable', 'socialpay' ),
			'label'		=> __( 'Enable this payment gateway', 'socialpay' ),
			'type'		=> 'checkbox',
			'default'	=> 'no',
		),
		'title' => array(
			'title'		=> __( 'Title', 'socialpay' ),
			'type'		=> 'text',
			'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'socialpay' ),
			'default'	=> __( 'SocialPay', 'socialpay' ),
		),
		'description' => array(
			'title'		=> __( 'Description', 'socialpay' ),
			'type'		=> 'textarea',
			'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'socialpay' ),
			'default'	=> __( 'You will be redirected to socialpay to complete the payment', 'socialpay' ),
			'css'		=> 'max-width:350px;'
		),
		'api_key' => array(
			'title'		=> __( 'Socialpay Key', 'socialpay' ),
			'type'		=> 'text',
			'desc_tip'	=> __( 'This is the consumer Key provided by Socialpay', 'socialpay'),
		)

	);
}

function process_payment( $order_id ) {
	global $woocommerce;
      $order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);

}

function socialpay_receipt_page( $order ) {


		echo '<p>'.__($this->pay_description, 'woocommerce').'</p>';


		echo $this->generate_socialpay_form( $order );

	}


function generate_socialpay_form($order_id) {

global $woocommerce;
$order = wc_get_order( $order_id );
$api_login_id = $this->api_key;
$items = $order->get_items();

//items 1

$i= 0;

$item_array=array();

foreach($items as  $item){

if( $item['type']=='line_item' ){

$item_array[$i]['name']=$item['name'];
$product = new WC_Product($item['product_id'] );
$item_array[$i]['price']=$product->price;
$item_array[$i]['qty']=$item['qty'];
if($product->weight){
$item_array[$i]['weight']=$product->weight;
} else {
$item_array[$i]['weight']=0;
}
if($product->sku){
$item_array[$i]['pid']=$product->sku;
} else {
$item_array[$i]['pid']=$item['product_id'];
}
$i++;
}

}


//print_r($item_array);

//$item_array = (object) $item_array;

//buyer
$buyer=new stdClass();
$buyer->name=$order->billing_first_name;
$buyer->phonenumber=$order->billing_phone;
$buyer->phone=$order->billing_phone;
$buyer->email=$order->billing_email;
$buyer->address=$order->billing_address_1." ".$order->billing_address_2;
$buyer->city=$order->billing_city;



$cart=$item_array;

$apiOptions = new stdClass();
$apiOptions->cart=$cart;
$apiOptions->buyer=$buyer;
$apiOptions->custOrderId=$order_id;

//print_r($cart);

$plain_str=json_encode($apiOptions);
$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
$encrypted_string = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $api_login_id, $plain_str, MCRYPT_MODE_CBC, $iv);
$apiOptFnl=base64_encode($encrypted_string);

$output = "<form action='". wp_nonce_url('https://www.socialpay.co.ug/app/pay.html','socialpay_paylink')."' method='post'>
<input type='hidden' name='apiOptions' value='".urlencode($apiOptFnl)."'/>
<input type='hidden' name='consumer_key' value='".$api_login_id."' >
<input type='hidden' name='iv' value='".urlencode(base64_encode($iv))."'/>
<input type='hidden' name='thankYouUrl' value='".  wp_nonce_url(wc_get_endpoint_url( 'order-received', $order->id.'/?key='.$order->order_key, wc_get_page_permalink( 'checkout' ) ),'success_socialpay')."'/>
<input type='hidden' name='failUrl' value='".$order->get_cancel_order_url()."'/>

<input type='submit' value='Place Order'/>
</form>";

return $output;
    }
  }
 }
}


add_action('woocommerce_init', 'woocommerce_socialpay_init');

add_action('init','wmm_check_woo_data');

function wmm_check_woo_data(){
  //print_r($_POST);
  $txId=intval(sanitize_text_field($_POST['transactionId']));
  $st=sanitize_text_field($_POST['status']);
  if($txId>0 && $st=='success'){
  if (wp_verify_nonce( $_GET['_wpnonce'], 'success_socialpay' ) )
  {
  //$json_data = json_decode(base64_decode($_POST['options']));
  $order_id =  $txId;
  //print_r($_POST);
  if($order_id > 0){
  $order = new WC_Order( $order_id );
  $order->update_status('completed', __( 'Completed payment', 'woocommerce' ));  
  $order->reduce_order_stock();
  wc()->cart->empty_cart();
  $order->payment_complete();
  }
  }
  }
}


