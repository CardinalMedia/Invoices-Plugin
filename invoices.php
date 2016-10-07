<?php

  /*
		Plugin Name: Simple Invoices
	*/

	require_once  __DIR__ . '/lib/vendor/CMB2/init.php';
	require_once  __DIR__ . '/lib/vendor/Stripe/init.php';


	class Invoices_Api {


		function __construct(){

			add_action( 'init', array($this, 'invoice_content_types'), 0 );

			add_action( 'cmb2_admin_init', array($this, 'invoice_fields' ) );

			add_action( 'rest_api_init', array($this, 'invoice_register_routes'));

      add_action( 'wp_enqueue_scripts', array( $this, 'invoice_frontend_scripts' ) );

      register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
      register_activation_hook( __FILE__, array( $this, 'invoice_flush_rewrites' ) );

		}

    function invoice_frontend_scripts(){

      wp_enqueue_style( 'invoice/style', plugin_dir_url( __FILE__ ) . 'assets/css/invoices.css', false, null );

      wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', [], null, true);
      wp_enqueue_script( 'invoice/script', plugin_dir_url( __FILE__ ) . 'assets/js/invoices.js', ['jQuery', 'stripe'], null, true);

    }

    function invoice_flush_rewrites() {
	// call your CPT registration function here (it should also be hooked into 'init')
      $this->invoice_content_types();
      flush_rewrite_rules();
    }

		function create_gift_subscription(WP_REST_Request $request){

  		$order = $request->get_json_params();

  		\Stripe\Stripe::setApiKey('');

  		try {
        $charge = \Stripe\Charge::create(array(
          "amount" => 2999,
          "currency" => "usd",
          "receipt_email" => $order['sender']['senderEmail'],
          "source" => $order['sender']['stripeToken'], // obtained with Stripe.js
          "description" => 'Subcription for ' . $order['recipient']['recipientFirstName'] . ' ' . $order['recipient']['recipientLastName'] . '. From: ' .  $order['sender']['senderFullName']
        ));
      } catch (\Stripe\Error\ApiConnection $e) {
        // Network problem, perhaps try again.

         $response = new WP_REST_Response(  $e->jsonBody );
         $response->set_status( 403 );

        return $response;

      } catch (\Stripe\Error\Api $e) {

        $response = new WP_REST_Response(  $e->jsonBody );
        $response->set_status( 403 );

        return $response;

      }  catch (\Stripe\Error\InvalidRequest $e) {

        $response = new WP_REST_Response(  $e->jsonBody );
        $response->set_status( 403 );

        return $response;

      } catch (\Stripe\Error\Card $e) {

        $response = new WP_REST_Response(  $e->jsonBody );
        $response->set_status( 403 );

        return $response;

      }

      if($charge){

        $post_id = wp_insert_post(array(
      		'post_title' => 'To: ' . $order['recipient']['recipientFirstName'] . ' ' . $order['recipient']['recipientLastName'],
      		'post_type' => 'gift_subscriptions',
      		'meta_input' => array(
        		'_sub_sender_full_name' => $order['sender']['senderFullName'],
        		'_sub_sender_email' => $order['sender']['senderEmail'],
        		'_sub_sender_stripe_charge_id' => $charge['id']
      		)
    		), false);

    		  ob_start();

    		?>

    		  <table>
      		  <tr>
              <td>Recipient Name</td>
              <td><?php echo $order['recipient']['recipientFirstName']; ?> <?php echo $order['recipient']['recipientLastName']; ?></td>
            </tr>
            <tr>
              <td>Recipient Address Line One</td>
              <td><?php echo $order['recipient']['recipientAddressOne']; ?></td>
            </tr>
            <tr>
              <td>Recipient Address Line Two</td>
              <td><?php echo $order['recipient']['recipientAddressTwo']; ?></td>
            </tr>
            <tr>
              <td>Recipient City</td>
              <td><?php echo $order['recipient']['recipientCity']; ?></td>
            </tr>
            <tr>
              <td>Recipient State</td>
              <td><?php echo $order['recipient']['recipientState']; ?></td>
            </tr>
            <tr>
              <td>Recipient Zip</td>
              <td><?php echo $order['recipient']['recipientZip']; ?></td>
            </tr>
            <tr>
              <td>
              </td>
            </tr>
            <tr>
              <td>Sender Name </td>
              <td><?php echo $order['sender']['senderFullName']; ?></td>
            </tr>
            <tr>
              <td>Sender Email</td>
              <td><?php echo $order['sender']['senderEmail']; ?></td>
            </tr>
            <tr>
              <td>Stripe Charge ID</td>
              <td><?php echo $charge['id']; ?></td>
    		  </table>

    		<?php

      		$msg = ob_get_contents();
          $clean = ob_end_clean();

    		// wp_mail('jcauley@ourstate.com', 'Holiday Gift Guide Gift Subscription', $msg);

    		ob_start();

    		?>

    		<table style="max-width: 600px;" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="td1" style="width: 280px;" valign="middle"><img style="max-width: 100%; height: auto;" src="https://www.ourstate.com/wp-content/uploads/2015/01/image001.jpg" alt="" width="280" /></td>
<td class="td2" style="padding-bottom: 15px; font-size: 13px;" align="right" valign="bottom">
<div class="d2"><span class="s1">Phone: 800-948-1409</span></div>
<div class="d2"><a href="mailto:circulation@ourstate.com">circulation@ourstate.com</a></div></td>
</tr>
<tr>
<td class="td3" style="border-bottom: 1px solid #ddd; height: 26px;" colspan="2" valign="middle"></td>
</tr>
<tr>
<td class="td3" style="height: 25px;" colspan="2" valign="middle"></td>
</tr>
<tr>
<td class="td3" colspan="2" valign="middle">
<p class="p2"><span class="s1"><b>Order Confirmation</b></span></p>
</td>
</tr>
<tr>
<td class="td3" colspan="2" valign="middle">&nbsp;</td>
</tr>
<tr>
<td class="td3" colspan="2" valign="middle">
<p class="p2"><span class="s1">Dear <?php echo $order['sender']['senderFullName']; ?>,</span></p>
<p class="p2"><span class="s1">This email confirms that your order was received by Our State Magazine. We will process your order shortly and the subscription will begin with the next available issue. Please contact us if you have any questions about your order.</span></p>
<p class="p2"><span class="s1">If you ordered a gift subscription, a gift card and envelope will be mailed to you in the next 7-10 days. If you need a gift card right away, please click <a href="http://www.ourstate.com/subscription-gift-cards" target="_blank"><span class="s3">here</span></a> to view our gift card collection.</span></p>
<p class="p2"><span class="s1">Thanks for choosing <em>Our State</em> Magazine!</span></p>
</td>
</tr>
</tbody>
</table>

    		<?php

    		$confirmation = ob_get_contents();
    		$clean = ob_end_clean();
    		wp_mail($order['sender']['senderEmail'], 'Gift Subscription Confirmation', $confirmation);



        $response = new WP_REST_Response( array(
          'success' => array(
            'post_id' => $post_id
        ) ) );

        $response->set_status( 201 );

        return $response;

      }

		}

		function stripe_webhooks(WP_REST_Request $request){

  		global $wpdb;

  		$charge = $request->get_json_params();

  		\Stripe\Stripe::setApiKey('');


      $orders = null;

  		$charge_id = $charge['data']['object']['id'];
      $status = null;

      if($charge['type'] === 'charge.failed'){
    		$status = $charge['data']['object']['status'];
  		}

      if($charge['type'] === 'charge.succeeded'){
    		$status = $charge['data']['object']['status'];
  		}

  		if($charge['type'] === 'charge.refunded'){
    		$status = 'refunded';
  		}

      $orders = $wpdb->get_results("SELECT * FROM $wpdb->postmeta
WHERE meta_key = '_sub_sender_stripe_charge_id' AND  meta_value = '$charge_id' LIMIT 1", ARRAY_A);

      if( $orders ){
        update_post_meta( $orders[0]['post_id'], '_sub_sender_stripe_charge_status', $status);
      }


  		$response = new WP_REST_Response(
    		array( '100' => '100')
  		);
      $response->set_status( 200 );

      return $response;

		}

		function sub_register_routes(){
  		$version = '1';
			$namespace = '/invoices/v' . $version;

			register_rest_route( $namespace, '/pay/', array(
				'methods' => 'POST',
				'callback' => array( $this, 'create_gift_subscription'),
			) );

			register_rest_route( $namespace, '/stripe/', array(
				'methods' => 'POST',
				'callback' => array( $this, 'stripe_webhooks'),
			) );

		}

		function invoice_content_types(){

  		register_post_type(
				'simple_invoice',
				array(
					'public'				=> true,
					'show_ui'				=> true,
					'hierarchical'			=> true,
					'show_in_admin_bar'		=> false,
					'menu_icon'				=> 'dashicons-cart',
					'show_in_rest'			=> false,
					'supports'				=> array(
						'title',
            'editor'
					),
          'rewrite' => array(
            'slug' => 'invoices',
            'with_front' => true,
          ),
          'has_archive' => false,
					'labels'				=> array(
						'name'					=> 'Invoices',
						'singular_name'			=> 'Invoice',
						'not_found'				=> 'No Invoice found',
						'add_new'				=> 'Add new Invoice',
						'add_new_item'			=> 'Add New Invoice',
						'edit_item'				=> 'Edit Invoice',
						'new_item'				=> 'New Invoice',
						'view_item'				=> 'View Invoice'
					)
				)
			);


		}

		function invoice_fields(){

      $prefix = '_invoice_details_';

      $order_fields = new_cmb2_box( array(
				'id'            => 'invoice_order_metaboxes',
				'title'         => __( 'Order Info', 'cmb2' ),
				'object_types'  => array( 'simple_invoice' ),
				'context'       => 'normal',
				'priority'      => 'high',
				'show_names'    => true,
			) );

      $order_fields->add_field( array(
        'id'			=> $prefix . 'order_price',
        'name'			=> 'Order Price',
        'type'			=> 'text_money'
      ) );

      $order_fields->add_field( array(
        'id'			=> $prefix . 'order_price',
        'name'			=> 'Order Price',
        'type'			=> 'text_money'
      ) );

  		$prefix = '_invoice_submitted_';

			$invoice_fields = new_cmb2_box( array(
				'id'            => 'invoice_metaboxes',
				'title'         => __( 'Payment Info', 'cmb2' ),
				'object_types'  => array( 'simple_invoice' ),
				'context'       => 'normal',
				'priority'      => 'high',
				'show_names'    => true,
			) );

			$invoice_fields->add_field( array(
				'id'			=> $prefix . 'full_name',
				'name'			=> 'Sender Name',
				'type'			=> 'text',
				'attributes' => array(
    			 'disabled' => true
  			 )
			) );

			$invoice_fields->add_field( array(
				'id'			=> $prefix . 'email',
				'name'			=> 'Sender Email',
				'type'			=> 'text',
				'attributes' => array(
    			 'disabled' => true
  			 )
			) );

      $invoice_fields->add_field( array(
				'id'			=> $prefix . 'stripe_charge_id',
				'name'			=> 'Stripe Charge ID',
				'type'			=> 'hidden',
			) );

			$invoice_fields->add_field( array(
  			 'id' => $prefix . 'stripe_charge_status',
  			 'name' => 'Charge Status',
  			 'type' => 'text',
  			 'attributes' => array(
    			 'disabled' => true
  			 )
			) );


		}


  }

  $os_sub_api = new Invoices_Api();
