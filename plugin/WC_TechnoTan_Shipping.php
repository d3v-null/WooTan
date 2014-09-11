<?php
global $woocommerce;

class WC_TechnoTan_Shipping extends WC_Shipping_Method {

	public function __construct(){
		require_once('Wootan_Plugin.php');
		$this->wootan = new Wootan_Plugin();

		$this->id = 'TechnoTan_Shipping';
		$this->method_title	= __( 'TechnoTan Shipping' );
		$this->method_description = __( "Send by TechnoTan's road or air shipping otions" );

		$this->enabled = "yes";
		$this->title   = __( 'TechnoTan Shipping' );

		$this->service_pref_option = $this->id.'_service_preferences';
		$this->matched_suburb_option = $this->id.'_matched_suburb';
        $this->matched_state_option = $this->id.'_matched_state';        

        add_action( 'woocommerce_update_options_shipping_'.$this->id, array( $this, 'process_admin_options' ) );
        // add_action( 'woocommerce_update_options_shipping_'.$this->id, array( $this, 'process_service_preferences' ) );

        $this->init();

	}

	function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->enabled 		= $this->get_option( 'enabled' );
		$this->sender_loc	= array(
			'postCode' => $this->get_option( 'sender_pcode' ),
		);
	}

	function init_form_fields() {
		$this->form_fields = array(
			'enabled'	=> array(
				'title'	=> __('Enable/Disable'),
				'type'	=> 'checkbox',
				'label' => __('Enable this shipping method'),
				'default' => 'no',
			),
			'sender_pcode' => array(
				'title'	=> __("Sender's Post Code"),
				'type'	=> 'text',
				'description' => __('Postcode of the location from which packages are being despatched from'),
				'desc_tip'	=> true,
				'default'	=> ''
			),
		);
	}

	// TODO: Overwrite this
	// public function admin_options() {
	// }

	function calculate_shipping( $package ) {
		If(WP_DEBUG) error_log("-> destination: ".serialize($package['destination']));

		$this->add_rate(
			array(
				'id' => 'road_under10',
				'label'	=> 'TechnoTan Road Shipping Under 10kg',
				'cost'	=> '19.95',
				'calc_tax' => 'per_item',
			)
		);
	}
}