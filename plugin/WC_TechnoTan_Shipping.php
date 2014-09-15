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

	public function get_methods(){
		$elig_australia = function( $package ){
			if(WP_DEBUG) error_log( 'testing australian eligibility');// of '.serialize($package) );
			if( isset( $package['destination'] ) ) {
				if(WP_DEBUG) error_log( '-> destionation is set' );
				if( $package['destination']['country'] != 'AU'){
					if(WP_DEBUG) error_log( '-> destionation is not australia' );
					return false;
				}
			} else {
				if(WP_DEBUG) error_log( '-> destionation is not set' );
				return false;
			}

			if(WP_DEBUG) error_log( 'passed Australian eligibility' );
			return true;
		};

		return array(
			'TT_WAA1' => array(
				'title' => __('Australia-Wide Wholesale Priority Freight'),
				'dangerous' => 'N',
				'include_roles' => array('wholesale_buyer'),
				'max_total_container' => 'AIRLABEL1',
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 16.95;
				}
			),
			'TT_RAR' => array(
				'title' => __('Australia-Wide Road Freight'),
				'exclude_roles' => array('wholesale_buyer'),
				'elig_fn' => $elig_australia,
				'cost_fn' => function( $package ){
					return 6.95;
				}
			)
		);		
	}

	public function get_containers() {
		return array(
			'AIRLABEL5' => array(
				'max_kilo' 	=> 5,
				'max_cubic'	=> 0.02,
			),
			'AIRBAG5' => array(
				'max_kilo'	=> 5,
				'max_dim'	=> array(250, 170, 310),
			),
			'AIRLABEL3' => array(
				'max_kilo' 	=> 3,
				'max_cubic' => 0.012,
			),
			'AIRBAG3' => array(
				'max_kilo' 	=> 3,
				'max_dim' 	=> array(150,160,260),
			),
			'AIRLABEL1' => array(
				'max_kilo' 	=> 1,
				'max_cubic' => 0.0004,
			),
			'AIRBAG1' => array(
				'max_kilo' 	=> 1,
				'max_dim'	=> array(70, 165, 260)
			)
		);
	}

	public function fits_in_container($item, $container){
		if(WP_DEBUG) error_log(
			'testing eligibility of '.
			serialize($item).
			' for container '.
			serialize($container)
		);
		//weight eligibility
		if(isset($container['max_kilo'])){
			if(WP_DEBUG) error_log('-> testing weight eligibility');
			if(isset($item['kilo'])){
				if($item['kilo'] > $container['max_kilo']){
					if(WP_DEBUG) error_log('--> does not fit, item too heavy');
					return false;
				} else {
					if(WP_DEBUG) error_log('--> fits!');
				}
			} else {
				if(WP_DEBUG) error_log('--> no weight specified');
				return false;
			}
		}
		//dim eligibility
		if(isset($container['max_dim'])){
			if(WP_DEBUG) error_log('-> testing dim eligibility');
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
					if(WP_DEBUG) error_log('--> does not fit');
					return false;
				} else {
					if(WP_DEBUG) error_log('--> fits!');
				}
			} else {
				if(WP_DEBUG) error_log('--> dims not specified');
				return false;
			}
		}
		//vol eligiblity
		if(isset($container['max_cubic'])){
			if(WP_DEBUG) error_log('-> testing vol eligibility');
			if(isset($item['length']) and isset($item['width']) and isset($item['height'])){
				$vol = $item['length'] * $item['width'] * $item['height'];
				if( $vol > $container['max_cubic'] ){
					if(WP_DEBUG) error_log('--> does not fit, item too big');
					return false;
				}
			} else {
				if(WP_DEBUG) error_log('--> dims not specified');
				return false;
			}
		}
		if(WP_DEBUG) error_log('-> fits!') ;
		return true;
	}

	// TODO: Overwrite this
	// public function admin_options() {
	// }

	function calculate_shipping( $package ) {
		If(WP_DEBUG) error_log("calculating shipping for ".serialize($package));

		$wootan_containers 	= $this->get_containers();
		$wootan_methods 	= $this->get_methods();

		foreach( $wootan_methods as $code => $method ){
			$name = isset($method['title'])?$method['title']:$code;

			If(WP_DEBUG) error_log("-> testing eligibility of ".$name);
			
			//test dangerous
			if (isset($method['dangerous']) and $method['dangerous'] == 'N'){
				If(WP_DEBUG) error_log("--> testing dangerous criteria");
				//TODO: 
				foreach( $package['contents'] as $line ){
					$data = $line['data'];
					If(WP_DEBUG) error_log("---> testing danger of ".$data->post->post_title);
					$danger = get_post_meta($data->post->ID, 'wootan_dangerous');
					If(WP_DEBUG) error_log("----> danger is ".$danger);
				}

			}
			//do we care about their role?
			if( isset($method['include_roles']) or isset($method['exclude_roles'])){
				If(WP_DEBUG) error_log("--> testing role criteria");
				$user = new WP_User( $package['user']['ID'] );

				global $Lasercommerce_Tier_Tree;
		        if (!isset($Lasercommerce_Tier_Tree)) {
		            $Lasercommerce_Tier_Tree = new Lasercommerce_Tier_Tree();
		        }

				if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
					If(WP_DEBUG) error_log("---> user roles are: ".serialize($user->roles));
					$visible = $Lasercommerce_Tier_Tree->getAvailableTiers($user->roles);
					If(WP_DEBUG) error_log("---> visible roles are: ".serialize($visible));
					if (isset($method['include_roles'])) {
						$user_included = false;
						foreach( $method['include_roles'] as $role ){
							If(WP_DEBUG) error_log("---> is $role in ".serialize($visible));
							
							if ( in_array( $role, $visible ) ){
								$user_included = true;
							}
						}
						if (!$user_included) {
							if(WP_DEBUG) error_log('---> user not included');
							continue;
						}
					}
					if (isset($method['exclude_roles'])) {
						$user_excluded = false;
						foreach( $method['exclude_roles'] as $role ){
							if( in_array( $role, $visible )){
								$user_excluded = true;
							}
						}
						if( $user_excluded ){
							if(WP_DEBUG) error_log('---> user is excluded');
							continue;
						}
					}
				} else {
					If(WP_DEBUG) error_log("---> roles not set");
					if (isset($method['include_roles'])) {
						continue;
					}
				}
			}
			//test total containers
			if (isset($method['min_total_container']) or isset($method['max_total_container'])) {
				If(WP_DEBUG) error_log("--> testing total_container criteria");
				
				//get total weight, volume
				$total_weight = 0;
				$total_vol	  = 0;
				foreach($package['contents'] as $line){
					If(WP_DEBUG) error_log("---> analysing line: ".serialize($line));
		            if($line['data']->has_weight()){
		                $item_weight = $line['quantity'] * $line['data']->get_weight();
		                $total_weight += wc_get_weight($item_weight, 'kg');
		            } else {
		                // throw exception because can't get weight
		            }
		            if($line['data']->has_dimensions()){
		                $item_dim = explode(' x ', $line['data']->get_dimensions());
		                $dimension_unit = get_option( 'woocommerce_dimension_unit' );
		                $item_dim[2] = str_replace( ' '.$dimension_unit, '', $item_dim[2]); 
		                $item_vol = array_product(
		                	array_map(
		                		function($dim){
		                			return wc_get_dimension($dim, 'm');
	                			}, 
	                			$item_dim
                			)
	                	);
		                $total_vol += $line['quantity'] * $item_vol;
		            } else {
		                // throw exception because can't get dimensions
		            }

				}

				$item = array(
					'kilo' => $total_weight,
					'cubic' => $total_vol
				);

				if (isset($method['min_total_container'])) {
					$container = $method['min_total_container'];					
					If(WP_DEBUG) error_log("--> testing min_total_container criteria: ".$method['min_total_container']);
					if(in_array($container, array_keys($wootan_containers))){
						$result = $this->fits_in_container($item, $wootan_containers[$container]);
					} else {
						If(WP_DEBUG) error_log("---> container does not exist: ".$container);
						continue;
					}
					if($result){
						if(WP_DEBUG) error_log("---> passed min_total_container criteria: ".$result);
					} else {
						if(WP_DEBUG) error_log("---> failed min_total_container criteria: ".$result);
						continue;
					}


				}
				if (isset($method['max_total_container'])) {
					$container = $method['max_total_container'];
					If(WP_DEBUG) error_log("--> testing max_total_container criteria: ".$container);
					if(in_array($container, array_keys($wootan_containers))){
						$result = $this->fits_in_container($item, $wootan_containers[$container]);
					} else {
						If(WP_DEBUG) error_log("---> container does not exist: ".$container);
						continue;
					}
					if($result){
						if(WP_DEBUG) error_log("---> passed max_total_container criteria: ".$result);
					} else {
						if(WP_DEBUG) error_log("---> failed max_total_container criteria: ".$result);
						continue;
					}
				}
			}
			if (isset($method['elig_fn'])) {
				If(WP_DEBUG) error_log("--> testing eligibility criteria");
				$result = call_user_func($method['elig_fn'], $package);
				if($result){
					if(WP_DEBUG) error_log("---> passed eligibility criteria: ".$result);
				} else {
					if(WP_DEBUG) error_log("---> failed eligibility criteria: ".$result);
					continue;
				}
			}

			//gauntlet passed, add rate
			If(WP_DEBUG) error_log("-> method passed");

			
			if( isset($method['cost_fn']) ){
				$cost = call_user_func($method['cost_fn'], $package);
			} else {
				If(WP_DEBUG) error_log("-> No Cost function set!");
				continue;
			}


			$this->add_rate(
				array(
					'id' => $code,
					'label'	=> $name,
					'cost'	=> $cost,
					//'calc_tax' => 'per_item',
				)
			);
			
		}

	}
}