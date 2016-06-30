<?php

//based off the WC_Shipping_Flat_Rate method on GitHub

global $woocommerce;

if(!defined('WOOTAN_DEBUG'))
	define('WOOTAN_DEBUG', false);

class WC_TechnoTan_Shipping extends WC_Shipping_Method {

	/**
	 * Constructor.
	 */
	public function __construct( $instance_id = 0 ){
		require_once('Wootan_Plugin.php');

		if(WOOTAN_DEBUG) error_log( 'wootan debugging enabled');

		$this->tree = Lasercommerce_Tier_Tree::instance();
		$this->wootan = Wootan_Plugin::instance();

		$this->id 					 = 'TechnoTan_Shipping';
		$this->instance_id 			 = absint( $instance_id );
		$this->method_title			 = __( 'TechnoTan Shipping' );
		$this->title   				 = __( 'TechnoTan Shipping' );
		$this->method_description 	 = __( "Send by TechnoTan's road or air shipping otions" );
		$this->supports              = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
			'settings'
		);
		$this->init();

        add_action( 'woocommerce_update_options_shipping_'.$this->id, array( $this, 'process_admin_options' ) );

	}

	/**
	 * init user set variables.
	 */
	function init() {
		$this->form_fields = $this->get_form_fields();
		$this->instance_form_fields = $this->get_instance_form_fields();
		$this->init_settings();
		$this->init_instance_settings();
		$this->enabled = $this->get_option('enabled');

		$this->cubic_rate = floatval( $this->get_instance_option('cubic_rate') );
		$this->retail_free_threshold = floatval( $this->get_instance_option( 'retail_free_threshold' ));
	}

	/**
	 * Get setting form fields for instances of this shipping method within zones.
	 * @return array
	 */
	public function get_instance_form_fields(){
		return array(
		    'tax_status' => array(
		        'title' 		=> __( 'Tax Status' ),
		        'type' 			=> 'select',
		        'class'         => 'wc-enhanced-select',
		        'default' 		=> 'taxable',
		        'options'		=> array(
		            'taxable' 	=> __( 'Taxable' ),
		            'none' 		=> _x( 'None', 'Tax status' )
		        )
		    ),
		    'retail_free_threshold' => array(
		        'title'	=> __("Retail Free Threshold"),
		        'type'	=> 'text',
		        'description' => __('Value over which retail gets free shipping'),
		        'desc_tip'	=> true,
		        'default'	=> '50'
		    ),
		    'cubic_rate' => array(
		        'title'	=> __("Cubic Rate"),
		        'type'	=> 'text',
		        'description' => __('Rate in kg / m^3'),
		        'desc_tip'	=> true,
		        'default'	=> '250'
		    ),
		);
	}

	public function get_form_fields(){
		return array(
			'enabled'	=> array(
			    'title'	=> __('Enable/Disable'),
			    'type'	=> 'checkbox',
			    'label' => __('Enable this shipping method'),
			    'default' => 'yes',
			)
		);
	}

	public function get_methods(){
		//sanity check


		//helper callback functions
		$elig_australia = function( $package ){
			if(WOOTAN_DEBUG) error_log( 'testing australian eligibility');// of '.serialize($package) );
			if( isset( $package['destination'] ) ) {
				if(WOOTAN_DEBUG) error_log( '-> destionation is set' );
				if( $package['destination']['country'] != 'AU'){
					if(WOOTAN_DEBUG) error_log( '-> destionation is not australia' );
					return false;
				}
			} else {
				if(WOOTAN_DEBUG) error_log( '-> destionation is not set' );
				return false;
			}

			if(WOOTAN_DEBUG) error_log( 'passed Australian eligibility' );
			return true;
		};

		$total_order_shipping = function( $package ){
			if(WOOTAN_DEBUG) error_log( 'testing total order eligibility');// of '.serialize($package) );

			$summary = $this->get_summary( $package['contents'] );
			if(!$summary){
				return false;
			} else {
				$cubic_rate = $this->cubic_rate;
				assert($cubic_rate <> 0); //sanity check
				return max(array($summary['total_weight'], $summary['total_volume']/$cubic_rate));
			}
			// global $WC_TechnoTan_Shipping;
			// if(!isset($WC_TechnoTan_Shipping)){
			// 	$WC_TechnoTan_Shipping = new WC_TechnoTan_Shipping();
			// }
			// $summary = $WC_TechnoTan_Shipping->get_summary( $package['contents'] );
			// if(!$summary){
			// 	return false;
			// } else {
			// 	$cubic_rate = $WC_TechnoTan_Shipping->cubic_rate;
			// 	assert($cubic_rate <> 0); //sanity check
			// 	return max(array($summary['total_weight'], $summary['total_volume']/$cubic_rate));
			// }
		};

		$over_retail_threshold = function( $package ){
			if(WOOTAN_DEBUG) error_log( 'testing retail threshold eligibility');// of '.serialize($package) );

			$threshold = $this->retail_free_threshold;
			if(WOOTAN_DEBUG) error_log( '-> retail threshold:'.serialize($threshold));// of '.serialize($package) );
			$cost = $package['contents_cost'];
			if(WOOTAN_DEBUG) error_log( '-> cost:'.serialize($cost));// of '.serialize($package) );

			return ($cost >= $threshold);
			// global $WC_TechnoTan_Shipping;
			// if(!isset($WC_TechnoTan_Shipping)){
			// 	$WC_TechnoTan_Shipping = new WC_TechnoTan_Shipping();
			// }
			//
			// return ($package['contents_cost'] >= $WC_TechnoTan_Shipping->retail_free_threshold);
		} ;

		return array(
			'TT_WAA1' => array(
				'title' => __('Australia-Wide Wholesale Air Express - up to 1kg'),
				'dangerous' => 'N',
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'max_total_container' => 'LABEL1',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 16.95;
				}
			),
			'TT_WAA3' => array(
				'title' => __('Australia-Wide Wholesale Air Express - up to 3kg'),
				'dangerous' => 'N',
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'min_total_container' => 'LABEL1',
				'max_total_container' => 'LABEL3',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 16.95;
				}
			),
			'TT_WAA5' => array(
				'title' => __('Australia-Wide Wholesale Air Express - up to 5kg'),
				'dangerous' => 'N',
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'min_total_container' => 'LABEL3',
				'max_total_container' => 'LABEL5',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 16.95;
				}
			),
			'TT_WAA' => array(
				'title' => __('Australia-Wide Wholesale Air Express - 5kg+'),
				'dangerous' => 'N',
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'min_total_container' => 'LABEL5',
				'max_item_container' => 'LABEL5',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ) use ($total_order_shipping){
					$total_shipping = call_user_func($total_order_shipping, $package);
					if( $total_shipping ){
						return 4.95 + ceil($total_shipping / 5) * 12.95;
					} else {
						return false;
					}
				}
			),
			'TT_WAR5'=> array(
				'title' => __('Australia-Wide Wholesale Road Freight - up to 5kg'),
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'max_total_container' => 'LABEL5',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 16.95;
				}
			),
			'TT_WAR10'=> array(
				'title' => __('Australia-Wide Wholesale Road Freight - 5-10kg'),
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'min_total_container' => 'LABEL5',
				'max_total_container' => 'LABEL10',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 16.95;
				}
			),
			'TT_WAR20'=> array(
				'title' => __('Australia-Wide Wholesale Road Freight - 10-20kg'),
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'min_total_container' => 'LABEL10',
				'max_total_container' => 'LABEL20',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 19.95;
				}
			),
			'TT_WAR'=> array(
				'title' => __('Australia-Wide Wholesale Road Freight - 20kg+'),
				'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'min_total_container' => 'LABEL20',
				'max_item_container' => 'LABEL20',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ) use ($total_order_shipping){
					$total_shipping = call_user_func($total_order_shipping, $package);
					if( $total_shipping ){
						return 19.95 + max( 0, ceil($total_shipping) - 20) * 1.1;
					} else {
						return false;
					}
				}
			),
			'TT_RARF' => array(
				'title' => __('Free Australia-Wide Road Freight'),
				'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
					return (
						call_user_func( $elig_australia, $package ) and
						call_user_func( $over_retail_threshold, $package)
					);
				},
				'cost_fn' => function( $package ){
					return 0.0;
				}
			),
			'TT_RAAF' => array(
				'title' => __('Free Australia-Wide Air Freight'),
				'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'max_item_container' => 'LABEL5',
				'dangerous' => 'N',
				'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
					return (
						call_user_func( $elig_australia, $package ) and
						call_user_func( $over_retail_threshold, $package)
					);
				},
				'cost_fn' => function( $package ){
					return 0.0;
				}
			),
			'TT_RAR' => array(
				'title' => __('Australia-Wide Road Freight'),
				'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
					return (
						call_user_func( $elig_australia, $package ) and
						!call_user_func( $over_retail_threshold, $package)
					);
				},
				'cost_fn' => function( $package ){
					return 6.95;
				},
				'notify_shipping_upgrade' => true
			),
			'TT_RAA' => array(
				'title' => __('Australia-Wide Air Freight'),
				'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
				'max_item_container' => 'LABEL5',
				'dangerous' => 'N',
				'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
					return (
						call_user_func( $elig_australia, $package ) and
						!call_user_func( $over_retail_threshold, $package)
					);
				},
				'cost_fn' => function( $package ){
					return 6.95;
				}
			),
		);
	}

	public function get_volume($dimensions){
		return array_product(
	    	array_map(
	    		function($dim){
	    			$meters = wc_get_dimension($dim, 'm');
	    			if(WOOTAN_DEBUG) error_log("-> converting $dim to meters: $meters");
	    			return $meters;
				},
				$dimensions
			)
		);
	}

	public function get_containers() {

		return array(
			'AIRBAG1' => array(
				'max_kilo' 	=> 1,
				'max_dim'	=> array(70, 165, 260)
			),
			'AIRBAG3' => array(
				'max_kilo' 	=> 3,
				'max_dim' 	=> array(150,160,260),
			),
			'AIRBAG5' => array(
				'max_kilo'	=> 5,
				'max_dim'	=> array(250, 170, 310),
			),
			'LABEL1' => array(
				'max_kilo' 	=> 1,
				'max_cubic' => 1 / $this->cubic_rate,
			),
			'LABEL3' => array(
				'max_kilo' 	=> 3,
				'max_cubic' => 3 / $this->cubic_rate,
			),
			'LABEL5' => array(
				'max_kilo' 	=> 5,
				'max_cubic'	=> 5 / $this->cubic_rate,
			),
			'LABEL10' => array(
				'max_kilo' 	=> 10,
				'max_cubic' => 10 / $this->cubic_rate,
			),
			'LABEL20' => array(
				'max_kilo' 	=> 20,
				'max_cubic' => 20 / $this->cubic_rate,
			)
		);
	}

	public function get_summary($contents){
		if(WOOTAN_DEBUG) error_log("getting totals for contents: ".serialize($contents));
		$dimension_unit = get_option( 'woocommerce_dimension_unit' );
		if(WOOTAN_DEBUG) error_log("dimensions are in $dimension_unit");
		$total_weight = 0;
		$total_vol	  = 0;

		foreach($contents as $line){
			if(WOOTAN_DEBUG) error_log("-> analysing line: ".$line['product_id']);
            if($line['data']->has_weight()){
                $item_weight = wc_get_weight( $line['data']->get_weight(), 'kg');
				if(WOOTAN_DEBUG) error_log("--> item weight: $item_weight");
                $total_weight += $line['quantity'] * $item_weight;
            } else {
                return false;
            }
            if($line['data']->has_dimensions()){
                $item_dim = explode(' x ', $line['data']->get_dimensions());
                $dimension_unit = get_option( 'woocommerce_dimension_unit' );
                $item_dim[2] = str_replace( ' '.$dimension_unit, '', $item_dim[2]);
				if(WOOTAN_DEBUG) error_log("--> item dim: ".serialize($item_dim));
                $item_vol = $this->get_volume($item_dim);
				if(WOOTAN_DEBUG) error_log("--> item vol: $item_vol");
                $total_vol += $line['quantity'] * $item_vol;
            } else {
                return false;
            }
		}
		if(WOOTAN_DEBUG) error_log("-> total weight: $total_weight, total volume: $total_vol");
		return array(
			'total_weight' => $total_weight,
			'total_volume' => $total_vol,
		);
	}

	public function fits_in_container($item, $container){
		if(WOOTAN_DEBUG) error_log(
			'testing eligibility of '.
			serialize($item).
			' for container '.
			serialize($container)
		);
		//weight eligibility
		if(isset($container['max_kilo'])){
			if(WOOTAN_DEBUG) error_log('-> testing weight eligibility');
			if(isset($item['kilo'])){
				if($item['kilo'] > $container['max_kilo']){
					if(WOOTAN_DEBUG) error_log('--> does not fit, item too heavy');
					return false;
				} else {
					if(WOOTAN_DEBUG) error_log('--> fits!');
				}
			} else {
				if(WOOTAN_DEBUG) error_log('--> no weight specified');
				return false;
			}
		}
		//dim eligibility
		if(isset($container['max_dim'])){
			if(WOOTAN_DEBUG) error_log('-> testing dim eligibility');
			if( isset($item['length']) and isset($item['width']) and isset($item['height']) ){
				$dim_item 	= array(
					$item['length'],
					$item['width'],
					$item['height']
				);
				$dim_max 	= $container['max_dim'];

				$fits = false;
				foreach( range(0,2) as $rot ){ //inefficient
					$fits = true;
					foreach( range(0,2) as $dim){
						if( $dim_item[$rot] > $dim_max[($rot + $dim)%3] ){
							$fits = false;
						}
					}
					if( $fits ) break;
				}

				if(!$fits){
					if(WOOTAN_DEBUG) error_log('--> does not fit');
					return false;
				} else {
					if(WOOTAN_DEBUG) error_log('--> fits!');
				}
			} else {
				if(WOOTAN_DEBUG) error_log('--> dims not specified');
				return false;
			}
		}
		//vol eligiblity
		if(isset($container['max_cubic'])){
			if(WOOTAN_DEBUG) error_log('-> testing vol eligibility');
			if(isset($item['length']) and isset($item['width']) and isset($item['height'])){
				$item_dim = array($item['length'], $item['width'], $item['height']);
				if(WOOTAN_DEBUG) error_log('-> item_dim'.serialize($item_dim));
				$item_vol = $this->get_volume($item_dim);
				// $vol = ($item['length']/100)  * ($item['width']/100) * ($item['height']/100) ;
				$max_vol = $container['max_cubic'];
				if( $item_vol > $max_vol ){
					if(WOOTAN_DEBUG) error_log("--> does not fit, item too big: $item_vol > $max_vol");
					return false;
				}
			} else {
				if(WOOTAN_DEBUG) error_log('--> dims not specified');
				return false;
			}
		}
		if(WOOTAN_DEBUG) error_log('--> fits!') ;
		return true;
	}

	// TODO: Overwrite this
	// public function admin_options() {
	// }

	function write_free_fright_notice($package) {
		$package_total = $package['contents_cost'];
		$threshold = $this->retail_free_threshold;
		if($package_total < $threshold){
			$difference = ceil($threshold - $package_total);
			$message = "spend another $$difference  and get free shipping!";
			$this->wootan->write_notice_once($message);
		}
	}

	function is_package_dangerous($package) {
		// foreach( $package['contents'] as $line ){
		// 	$data = $line['data'];
		// 	if(WOOTAN_DEBUG) error_log("---> testing danger of ".$data->post->post_title);
		// 	$danger = get_post_meta($data->post->ID, 'wootan_danger', true);
		// 	if(WOOTAN_DEBUG) error_log("----> danger is ".serialize($danger));
		// 	if( $danger == "Y" ){
		// 		return true;
		// 	}
		// }
		// return false;
		$contents = isset($package['contents'])?$package['contents']:array();
		return $this->wootan->are_contents_dangerous($contents);
	}

	function is_package_po_box($package){
		$destination = isset($package['destination'])?$package['destination']:array();
		$line1 = isset($destination['address'])?$destination['address']:'';
		$line2 = isset($destination['address_2'])?$destination['address_2']:'';
		return $this->wootan->is_address_po_box($line1, $line2);
	}

	function calculate_shipping( $package=array() ) {
		if(WOOTAN_DEBUG) error_log("calculating shipping for ".serialize($package));

		if($this->is_package_po_box($package)){
			if(WOOTAN_DEBUG) error_log("-> package is PO, no shipping options");
			return;
		}

		$wootan_containers 	= $this->get_containers();
		$wootan_methods 	= $this->get_methods();

		//determine precisely how many fucks to give
		if(WOOTAN_DEBUG) error_log("-> determining number of fucks given");
		$fucks_given = array();
		foreach( $wootan_methods as $code => $method ){
			if( array_intersect(
				array(
					'min_total_container',
					'max_total_container',
				),
				array_keys($method)
			) ) {
				$fucks_given['summary'] = true;
			}
			if( array_intersect(
				array(
					'include_roles',
					'exclude_roles',
				),
				array_keys($method)
			) ) {
				$fucks_given['tiers'] = true;
			}
		}
		if(isset($fucks_given['summary'])){
			if(WOOTAN_DEBUG) error_log("--> getting summary");
			$summary = $this->get_summary($package['contents']);
			if($summary){
				if(WOOTAN_DEBUG) error_log("---> summary is: ".serialize($summary));
			} else {
				if(WOOTAN_DEBUG) error_log("---> cannot get summary");
				return;
			}
		}
		if(isset($fucks_given['tiers'])){
			if(WOOTAN_DEBUG) error_log("--> getting roles");
			$user = new WP_User( $package['user']['ID'] );
			global $Lasercommerce_Tier_Tree;
	        if (!isset($Lasercommerce_Tier_Tree)) {
	            $Lasercommerce_Tier_Tree = new Lasercommerce_Tier_Tree();
	        }

			$visibleTiers = $Lasercommerce_Tier_Tree->getVisibleTiers($user);
			$visibleTierIDs = $Lasercommerce_Tier_Tree->getTierIDs($visibleTiers);
			if(WOOTAN_DEBUG) error_log("---> visible tiers are: ".serialize($visibleTierIDs));
		}

		foreach( $wootan_methods as $code => $method ){
			$name = isset($method['title'])?$method['title']:$code;

			if(WOOTAN_DEBUG) error_log("");
			if(WOOTAN_DEBUG) error_log("-> testing eligibility of ".$name);

			//test dangerous
			if (isset($method['dangerous']) and $method['dangerous'] == 'N'){
				if(WOOTAN_DEBUG) error_log("--> testing dangerous criteria");
				$dangerous = $this->is_package_dangerous($package);
				if( $dangerous) {
					if(WOOTAN_DEBUG) error_log("--> failed danger criteria");
					continue;
				} else {
					if(WOOTAN_DEBUG) error_log("--> passed danger criteria");
				}

			}

			if (isset($method['include_roles'])) {
				if(WOOTAN_DEBUG) error_log("--> testing include role criteria");
				if( array_intersect( $method['include_roles'], $visibleTierIDs ) ) {
					if(WOOTAN_DEBUG) error_log('---> user included');
				} else {
					if(WOOTAN_DEBUG) error_log('---> user not included');
					continue;
				}
			}
			if (isset($method['exclude_roles'])) {
				if(WOOTAN_DEBUG) error_log("--> testing exclude role criteria");
				if( array_intersect( $method['exclude_roles'], $visibleTierIDs) ) {
					if(WOOTAN_DEBUG) error_log('---> user excluded');
					continue;
				} else {
					if(WOOTAN_DEBUG) error_log('---> user not excluded');
				}
			}

			//test total containers
			if (isset($method['min_total_container']) or isset($method['max_total_container'])) {
				if(WOOTAN_DEBUG) error_log("--> testing total_container criteria");

				$cube_length = pow($summary['total_volume'], 1.0/3.0) * 1000;

				$total_item = array(
					'kilo' 		=> $summary['total_weight'],
					'length' 	=> $cube_length,
					'width' 	=> $cube_length,
					'height' 	=> $cube_length,
				);

				if (isset($method['min_total_container'])) {
					$container = $method['min_total_container'];
					if(WOOTAN_DEBUG) error_log("--> testing min_total_container criteria: ".$method['min_total_container']);
					if(in_array($container, array_keys($wootan_containers))){
						$result = $this->fits_in_container($total_item, $wootan_containers[$container]);
					} else {
						if(WOOTAN_DEBUG) error_log("---> container does not exist: ".$container);
						continue;
					}
					if(!$result){
						if(WOOTAN_DEBUG) error_log("---> passed min_total_container criteria: ".$result);
					} else {
						if(WOOTAN_DEBUG) error_log("---> failed min_total_container criteria: ".$result);
						continue;
					}
				}
				if (isset($method['max_total_container'])) {
					$container = $method['max_total_container'];
					if(WOOTAN_DEBUG) error_log("--> testing max_total_container criteria: ".$container);
					if(in_array($container, array_keys($wootan_containers))){
						$result = $this->fits_in_container($total_item, $wootan_containers[$container]);
					} else {
						if(WOOTAN_DEBUG) error_log("---> container does not exist: ".$container);
						continue;
					}
					if($result){
						if(WOOTAN_DEBUG) error_log("---> passed max_total_container criteria: ".$result);
					} else {
						if(WOOTAN_DEBUG) error_log("---> failed max_total_container criteria: ".$result);
						continue;
					}
				}
			}
			if (isset($method['max_item_container'])){
				$container = $method['max_item_container'];
				if(WOOTAN_DEBUG) error_log("--> testing item_container criteria: ".$container);
				$fits = true;
				foreach ($package['contents'] as $line) {

					if(WOOTAN_DEBUG) error_log("---> analysing line: ".$line['product_id']);
		            if($line['data']->has_weight()){
		                $item_weight = wc_get_weight($line['data']->get_weight(), 'kg');
		            } else {
		                // throw exception because can't get weight
		            }
		            if($line['data']->has_dimensions()){
		                $item_dim = explode(' x ', $line['data']->get_dimensions());
		                $dimension_unit = get_option( 'woocommerce_dimension_unit' );
		                $item_dim[2] = str_replace( ' '.$dimension_unit, '', $item_dim[2]);
		            } else {
		                // throw exception because can't get dimensions
		            }
		            $item = array(
		            	'kilo' => $item_weight,
		            	'length' => $item_dim[0],
		            	'width' => $item_dim[1],
		            	'height' => $item_dim[2]
	            	);
	            	if(in_array($container, array_keys($wootan_containers))){
		            	$result = $this->fits_in_container( $item, $wootan_containers[$container]);
						if($result){
							if(WOOTAN_DEBUG) error_log("---> passed max_item_container criteria: ".$result);
						} else {
							if(WOOTAN_DEBUG) error_log("---> failed max_item_container criteria: ".$result);
							$fits = false;
							break;
						}
	            	} else {
						if(WOOTAN_DEBUG) error_log("---> container does not exist: ".$container);
						$fits = false;
						break;
					}
				}
				if( !$fits ){
					continue;
				}
			}
			if (isset($method['elig_fn'])) {
				if(WOOTAN_DEBUG) error_log("--> testing eligibility criteria");
				$result = call_user_func($method['elig_fn'], $package);
				if($result){
					if(WOOTAN_DEBUG) error_log("---> passed eligibility criteria: ".serialize($result));
				} else {
					if(WOOTAN_DEBUG) error_log("---> failed eligibility criteria: ".serialize($result));
					continue;
				}
			}

			if (isset($method['notify_shipping_upgrade'])) {
				if(WOOTAN_DEBUG) error_log("--> adding shipping upgrade notification");
				$this->write_free_fright_notice($package);
			}

			//gauntlet passed, add rate
			if(WOOTAN_DEBUG) error_log("-> method passed");


			if( isset($method['cost_fn']) ){
				$cost = call_user_func($method['cost_fn'], $package);
				if(! is_numeric( $cost ) ){
					if(WOOTAN_DEBUG) error_log("-> cost could not be determined!");
					continue;
				}
			} else {
				if(WOOTAN_DEBUG) error_log("-> No Cost function set!");
				continue;
			}


			$this->add_rate(
				array(
					'id' => $this->get_rate_id($code),
					'label'	=> $name,
					'cost'	=> $cost,
					'package' => $package,
					//'calc_tax' => 'per_item',
				)
			);
		}
	}
}
