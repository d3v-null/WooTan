<?php

if(!defined('WOOTAN_DEBUG'))
    define('WOOTAN_DEBUG', false);

class WC_TechnoTan_Shipping extends WC_Shipping_Method {

    /**
     * Constructor.
     */
    public function __construct( $instance_id = 0 ){
        if(class_exists("Lasercommerce_Tier_Tree")){
            $this->tree = Lasercommerce_Tier_Tree::instance();
        }
        if(class_exists("Wootan_Plugin")){
            $this->wootan = Wootan_Plugin::instance();
        }
        if(WOOTAN_DEBUG) $this->wootan->debug("BEGIN WC_TechnoTan_Shipping->__construct(\$instance_id=$instance_id)");

        $shipping_id = $this->wootan->getOption('ShippingID');
        $this->id                       = $shipping_id;
        if(WOOTAN_DEBUG) $this->wootan->debug("WC_TechnoTan_Shipping->__construct: \$this->id = $this->id");
        $this->instance_id              = absint( $instance_id );
        $this->method_title             = __( 'TechnoTan Custom Shipping' );
        $this->method_description       = __( "Send by TechnoTan's road or air shipping otions" );
        $this->supports              = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
            'settings'
        );

        $this->init();

        if(WOOTAN_DEBUG) $this->wootan->debug("END WC_TechnoTan_Shipping->__construct()");
    }

    /**
     * Initialize.
     */
    public function init() {
        if(WOOTAN_DEBUG) $this->wootan->debug("BEGIN WC_TechnoTan_Shipping->init()");

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // $this->init_instance_settings(); # Is this necessary?

        // Define user set variables
        $this->title = $this->get_option( 'title' );
        $this->enabled = $this->get_option( 'enabled' ); # is this necessary?
        $this->cubic_rate = floatval( $this->get_option('cubic_rate') );
        // if( $this->cubic_rate <= 0 ){
        //     // Sanity check
        //     $this->errors[] = "invalid cubic weight: $this->cubic_rate. Should be a number above zero";
        // }
        $this->tax_status         = $this->get_option( 'tax_status' );
        $this->retail_free_threshold = floatval( $this->get_option( 'retail_free_threshold' ));

        // Actions
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        if(WOOTAN_DEBUG) $this->wootan->debug("END WC_TechnoTan_Shipping->init()");
    }

    /**
     * Init form fields.
     */
    public function init_form_fields() {
        $this->instance_form_fields =  array(
            'title' => array(
                'title'         => __( 'Title' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default'     => __( 'TechnoTan Custom Shipping Instance' ),
                'desc_tip'    => true,
            ),
            'tax_status' => array(
                'title'         => __( 'Tax Status' ),
                'type'          => 'select',
                'class'         => 'wc-enhanced-select',
                'default'       => 'taxable',
                'options'       => array(
                    'taxable'     => __( 'Taxable' ),
                    'none'        => _x( 'None', 'Tax status' )
                )
            ),
            'dangerous' => array(
                'title'         => __( 'Allow Dangerous Items' ),
                'description'   => __( 'Allow items marked as dangerous to be shipped in this instance' ),
                'desc_tip'      => true,
                'type'          => 'select',
                'default'       => 'N',
                'options'       => array(
                    'Y'=>        __( 'Yes' ),
                    'N'=>        __( 'No' )
                )
            ),
            'include_roles' => array(
                'title'         => __( 'Roles Allowed'),
                'description'   => __( 'Allow these roles to see this shipping method. None = all roles can see'),
                'desc_tip'      => true,
                'type'          => 'role_list',
            ),
            'exclude_roles' => array(
                'title'         => __( 'Roles Excluded'),
                'description'   => __( 'Exclude these roles from seeing this shipping method. None = all roles can see'),
                'desc_tip'      => true,
                'type'          => 'role_list',
            ),
            'max_item_container' => array(
                'title'         => __( 'Max Item Container' ),
                'description'   => __( 'The container specifying the largest item that can fit in this shipping method'),
                'desc_tip'      => true,
                'type'          => 'container_list'
            ),
            'min_total_container' => array(
                'title'         => __( 'Minimum Total Container' ),
                'description'   => __( 'The order must be bigger than this container to be eligible for this shipping method'),
                'desc_tip'      => true,
                'type'          => 'container_list'
            ),
            'max_total_container' => array(
                'title'         => __( 'Max Total Container' ),
                'description'   => __( 'The order must be smaller than this container to be eligible for this shipping method'),
                'desc_tip'      => true,
                'type'          => 'container_list'
            ),
            'cost_base' => array(
                'title'         => __( 'Base Cost'),
                'type'          => 'price',
            ),
            'extra_cost' => array(
                'title'         => __( 'Extra Cost'),
                'description'   => __('Cost associated with each additional Minimum Total container'),
                'desc_tip'      => true,
            )
        );

        $this->form_fields = array(
            'enabled'    => array(
                'title'    => __('Enable/Disable'),
                'type'    => 'checkbox',
                'label' => __('Enable this shipping method'),
                'default' => 'yes',
            ),
            'retail_free_threshold' => array(
                'title'    => __("Retail Free Threshold"),
                'type'    => 'text',
                'description' => __('Value over which retail gets free shipping'),
                'desc_tip'    => true,
                'default'    => '50'
            ),
            'cubic_rate' => array(
                'title'    => __("Cubic Rate"),
                'type'    => 'nonnegative_number',
                'description' => __('Rate in kg / m^3'),
                'desc_tip'    => true,
                'default'    => '250'
            ),
            'containers' => array(
                'title' => __("Shipping Containers"),
                'type' => 'textarea',
                'description' => __('Containers used for sending items'),
                'desc_tip' => true,
                'default' =>
                    '{"AIRBAG1":{"max_kilo":1.1, "max_dim":[70, 165, 260]}, '
                    .'"AIRBAG3":{"max_kilo":2.7, "max_dim":[150, 160, 260]}, '
                    .'"AIRBAG5":{"max_kilo":4.5, "max_dim":[250, 170, 310]}, '
                    .'"AIRBAG6":{"max_kilo":5.5, "max_dim":[270, 275, 320]}, '
                    .'"LABEL1":{"max_kilo":1.1}, '
                    .'"LABEL3":{"max_kilo":2.7}, '
                    .'"LABEL5":{"max_kilo":4.5}, '
                    .'"LABEL10":{"max_kilo":9.5}, '
                    .'"LABEL20":{"max_kilo":19.2}'
                    .'}'
            )
        );
    }

    public function get_admin_options_html(){
        $response = parent::get_admin_options_html();
        $response .= "<h2>Test Results</h2>"
            ."<p><strong>CONTAINERS: </strong>".serialize($this->get_containers())."</p>"
            ."<p><strong>CONTAINER_OPTIONS: </strong>".serialize($this->get_container_options())."</p>"
            ."<p><strong>ROLES: </strong>".serialize($this->get_role_options())."</p>";
        return $response;
    }

    public function get_role_options(){
        global $wp_roles;

        $all_roles = $wp_roles->roles;
        $role_options = array();
        foreach( apply_filters('editable_roles', $all_roles) as $role => $details){
            $role_options[$role] = $details['name'];
        }
        return $role_options;
    }

    public function get_container_options(){
        $container_options = array(''=>'--');
        foreach ($this->get_containers() as $container => $details) {
            $container_name = __("max weight: ") . number_format_i18n($details['max_kilo']) . "kg";
            if(isset($details['max_dim'])){
                $container_name .= __("; max dims: ") . implode("x", $details['max_dim']) . "mm";
            }
            $container_name = "$container ( $container_name )";
            $container_options[$container] = $container_name;
        }
        return $container_options;
    }

    public function generate_role_list_html( $key, $data ) {
        $defaults = array(
            'options' => $this->get_role_options(),
            'default' => array()
        );

        $data  = wp_parse_args( $data, $defaults );

        $response = parent::generate_multiselect_html($key, $data);
        return $response;
    }

    public function validate_role_list_field( $key, $value ) {
        return parent::validate_multiselect_field($key, $value);
    }

    public function generate_container_list_html( $key, $data ) {
        $defaults = array(
            'options' => $this->get_container_options(),
            'default' => array()
        );

        $data  = wp_parse_args( $data, $defaults );

        $response = parent::generate_select_html($key, $data);
        return $response;
    }

    public function validate_container_list_field( $key, $value ) {
        return parent::validate_select_field($key, $value);
    }

    public function generate_nonnegative_number_html( $key, $data ) {
        return parent::generate_decimal_html($key, $data);
    }

    public function validate_nonnegative_number_field( $key, $value ) {
        $response = parent::validate_decimal_field($key, $value);
        if(is_null($response) or floatval($response) <= 0) {
            $this->errors[] = "invalid value for $key: $response. Not a nonnegative number";
            return '';
        } else {
            return $response;
        }
    }

    function calculate_shipping( $package=array() ) {
        $this->add_rate( array(
            'label' => $this->title,
            'package' => $package,
            'cost' => $this->cost_base,
        ) );
    }

    public function get_containers() {
        $containers_json = $this->get_option('containers');

        if(WOOTAN_DEBUG) $this->wootan->debug("containers_json: ".serialize($containers_json));

        $containers = json_decode($containers_json, true);

        // all containers must have either max_dim or max_cubic set
        if(WOOTAN_DEBUG) $this->wootan->debug("containers_pre: ".serialize($containers));

        foreach ($containers as $key => $container) {
            if(! isset($container['max_kilo'])){
                $this->errors[] = "invalid container: $key. No max_kilo set.";
                unset($containers[$key]);
                continue;
            }
            if(! isset($container['max_dim'])){
                $weight = floatval($container['max_kilo']);
                $container['max_cubic'] = $weight / $this->cubic_rate;
            }
        }
        if(WOOTAN_DEBUG) $this->wootan->debug("containers: ".serialize($containers));
        return $containers;
    }
}
//
// class SomeOtherBullshit {
//     public function get_methods(){
//         trigger_error("Deprecated function called: get_methods.", E_USER_NOTICE);
//         //sanity check
//
//
//         //helper callback functions
//         $elig_australia = function( $package ){
//             if(WOOTAN_DEBUG) $this->wootan->debug( 'testing australian eligibility');// of '.serialize($package) );
//             if( isset( $package['destination'] ) ) {
//                 if(WOOTAN_DEBUG) $this->wootan->debug( '-> destionation is set' );
//                 if( $package['destination']['country'] != 'AU'){
//                     if(WOOTAN_DEBUG) $this->wootan->debug( '-> destionation is not australia' );
//                     return false;
//                 }
//             } else {
//                 if(WOOTAN_DEBUG) $this->wootan->debug( '-> destionation is not set' );
//                 return false;
//             }
//
//             if(WOOTAN_DEBUG) $this->wootan->debug( 'passed Australian eligibility' );
//             return true;
//         };
//
//         $total_order_shipping = function( $package ){
//             if(WOOTAN_DEBUG) $this->wootan->debug( 'testing total order eligibility');// of '.serialize($package) );
//
//             $summary = $this->get_summary( $package['contents'] );
//             if(!$summary){
//                 return false;
//             } else {
//                 $cubic_rate = $this->cubic_rate;
//                 assert($cubic_rate > 0); //sanity check
//                 return max(array($summary['total_weight'], $summary['total_volume']/$cubic_rate));
//             }
//             // global $WC_TechnoTan_Shipping;
//             // if(!isset($WC_TechnoTan_Shipping)){
//             //     $WC_TechnoTan_Shipping = new WC_TechnoTan_Shipping();
//             // }
//             // $summary = $WC_TechnoTan_Shipping->get_summary( $package['contents'] );
//             // if(!$summary){
//             //     return false;
//             // } else {
//             //     $cubic_rate = $WC_TechnoTan_Shipping->cubic_rate;
//             //     assert($cubic_rate <> 0); //sanity check
//             //     return max(array($summary['total_weight'], $summary['total_volume']/$cubic_rate));
//             // }
//         };
//
//         $over_retail_threshold = function( $package ){
//             if(WOOTAN_DEBUG) $this->wootan->debug( 'testing retail threshold eligibility');// of '.serialize($package) );
//
//             $threshold = $this->retail_free_threshold;
//             if(WOOTAN_DEBUG) $this->wootan->debug( '-> retail threshold:'.serialize($threshold));// of '.serialize($package) );
//             $cost = $package['contents_cost'];
//             if(WOOTAN_DEBUG) $this->wootan->debug( '-> cost:'.serialize($cost));// of '.serialize($package) );
//
//             return ($cost >= $threshold);
//             // global $WC_TechnoTan_Shipping;
//             // if(!isset($WC_TechnoTan_Shipping)){
//             //     $WC_TechnoTan_Shipping = new WC_TechnoTan_Shipping();
//             // }
//             //
//             // return ($package['contents_cost'] >= $WC_TechnoTan_Shipping->retail_free_threshold);
//         } ;
//
//         return array(
//             'TT_WAA1' => array(
//                 'title' => __('Australia-Wide Wholesale Air Express - up to 1kg'),
//                 'dangerous' => 'N',
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'max_total_container' => 'LABEL1',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ){
//                     return 16.95;
//                 }
//             ),
//             'TT_WAA3' => array(
//                 'title' => __('Australia-Wide Wholesale Air Express - up to 3kg'),
//                 'dangerous' => 'N',
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'min_total_container' => 'LABEL1',
//                 'max_total_container' => 'LABEL3',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ){
//                     return 16.95;
//                 }
//             ),
//             'TT_WAA5' => array(
//                 'title' => __('Australia-Wide Wholesale Air Express - up to 5kg'),
//                 'dangerous' => 'N',
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'min_total_container' => 'LABEL3',
//                 'max_total_container' => 'LABEL5',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ){
//                     return 16.95;
//                 }
//             ),
//             'TT_WAA' => array(
//                 'title' => __('Australia-Wide Wholesale Air Express - 5kg+'),
//                 'dangerous' => 'N',
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'min_total_container' => 'LABEL5',
//                 'max_item_container' => 'LABEL5',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ) use ($total_order_shipping){
//                     $total_shipping = call_user_func($total_order_shipping, $package);
//                     if( $total_shipping ){
//                         return 4.95 + ceil($total_shipping / 5) * 12.95;
//                     } else {
//                         return false;
//                     }
//                 }
//             ),
//             'TT_WAR5'=> array(
//                 'title' => __('Australia-Wide Wholesale Road Freight - up to 5kg'),
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'max_total_container' => 'LABEL5',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ){
//                     return 16.95;
//                 }
//             ),
//             'TT_WAR10'=> array(
//                 'title' => __('Australia-Wide Wholesale Road Freight - 5-10kg'),
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'min_total_container' => 'LABEL5',
//                 'max_total_container' => 'LABEL10',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ){
//                     return 16.95;
//                 }
//             ),
//             'TT_WAR20'=> array(
//                 'title' => __('Australia-Wide Wholesale Road Freight - 10-20kg'),
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'min_total_container' => 'LABEL10',
//                 'max_total_container' => 'LABEL20',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ){
//                     return 19.95;
//                 }
//             ),
//             'TT_WAR'=> array(
//                 'title' => __('Australia-Wide Wholesale Road Freight - 20kg+'),
//                 'include_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'min_total_container' => 'LABEL20',
//                 'max_item_container' => 'LABEL20',
//                 'elig_fn' => $elig_australia,
//                 'cost_fn' => function( $package ) use ($total_order_shipping){
//                     $total_shipping = call_user_func($total_order_shipping, $package);
//                     if( $total_shipping ){
//                         return 19.95 + max( 0, ceil($total_shipping) - 20) * 1.1;
//                     } else {
//                         return false;
//                     }
//                 }
//             ),
//             'TT_RARF' => array(
//                 'title' => __('Free Australia-Wide Road Freight'),
//                 'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
//                     return (
//                         call_user_func( $elig_australia, $package ) and
//                         call_user_func( $over_retail_threshold, $package)
//                     );
//                 },
//                 'cost_fn' => function( $package ){
//                     return 0.0;
//                 }
//             ),
//             'TT_RAAF' => array(
//                 'title' => __('Free Australia-Wide Air Freight'),
//                 'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'max_item_container' => 'LABEL5',
//                 'dangerous' => 'N',
//                 'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
//                     return (
//                         call_user_func( $elig_australia, $package ) and
//                         call_user_func( $over_retail_threshold, $package)
//                     );
//                 },
//                 'cost_fn' => function( $package ){
//                     return 0.0;
//                 }
//             ),
//             'TT_RAR' => array(
//                 'title' => __('Australia-Wide Road Freight'),
//                 'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
//                     return (
//                         call_user_func( $elig_australia, $package ) and
//                         !call_user_func( $over_retail_threshold, $package)
//                     );
//                 },
//                 'cost_fn' => function( $package ){
//                     return 6.95;
//                 },
//                 'notify_shipping_upgrade' => true
//             ),
//             'TT_RAA' => array(
//                 'title' => __('Australia-Wide Air Freight'),
//                 'exclude_roles' => array('WN', 'WP', 'DN', 'DP', 'XWN', 'XWP', 'XDN', 'XDP'),
//                 'max_item_container' => 'LABEL5',
//                 'dangerous' => 'N',
//                 'elig_fn' => function($package) use ($elig_australia, $over_retail_threshold){
//                     return (
//                         call_user_func( $elig_australia, $package ) and
//                         !call_user_func( $over_retail_threshold, $package)
//                     );
//                 },
//                 'cost_fn' => function( $package ){
//                     return 6.95;
//                 }
//             ),
//         );
//     }
//
//     public function get_volume($dimensions){
//         return array_product(
//             array_map(
//                 function($dim){
//                     $meters = wc_get_dimension($dim, 'm');
//                     if(WOOTAN_DEBUG) $this->wootan->debug("-> converting $dim to meters: $meters");
//                     return $meters;
//                 },
//                 $dimensions
//             )
//         );
//     }
//
//     public function get_summary($contents){
//         if(WOOTAN_DEBUG) $this->wootan->debug("getting totals for contents: ".serialize($contents));
//         $dimension_unit = get_option( 'woocommerce_dimension_unit' );
//         if(WOOTAN_DEBUG) $this->wootan->debug("dimensions are in $dimension_unit");
//         $total_weight = 0;
//         $total_vol      = 0;
//
//         foreach($contents as $line){
//             if(WOOTAN_DEBUG) $this->wootan->debug("-> analysing line: ".$line['product_id']);
//             if($line['data']->has_weight()){
//                 $item_weight = wc_get_weight( $line['data']->get_weight(), 'kg');
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> item weight: $item_weight");
//                 $total_weight += $line['quantity'] * $item_weight;
//             } else {
//                 return false;
//             }
//             if($line['data']->has_dimensions()){
//                 $item_dim = explode(' x ', $line['data']->get_dimensions());
//                 $dimension_unit = get_option( 'woocommerce_dimension_unit' );
//                 $item_dim[2] = str_replace( ' '.$dimension_unit, '', $item_dim[2]);
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> item dim: ".serialize($item_dim));
//                 $item_vol = $this->get_volume($item_dim);
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> item vol: $item_vol");
//                 $total_vol += $line['quantity'] * $item_vol;
//             } else {
//                 return false;
//             }
//         }
//         if(WOOTAN_DEBUG) $this->wootan->debug("-> total weight: $total_weight, total volume: $total_vol");
//         return array(
//             'total_weight' => $total_weight,
//             'total_volume' => $total_vol,
//         );
//     }
//
//     public function fits_in_container($item, $container){
//         if(WOOTAN_DEBUG) $this->wootan->debug(
//             'testing eligibility of '.
//             serialize($item).
//             ' for container '.
//             serialize($container)
//         );
//         //weight eligibility
//         if(isset($container['max_kilo'])){
//             if(WOOTAN_DEBUG) $this->wootan->debug('-> testing weight eligibility');
//             if(isset($item['kilo'])){
//                 if($item['kilo'] > $container['max_kilo']){
//                     if(WOOTAN_DEBUG) $this->wootan->debug('--> does not fit, item too heavy');
//                     return false;
//                 } else {
//                     if(WOOTAN_DEBUG) $this->wootan->debug('--> fits!');
//                 }
//             } else {
//                 if(WOOTAN_DEBUG) $this->wootan->debug('--> no weight specified');
//                 return false;
//             }
//         }
//         //dim eligibility
//         if(isset($container['max_dim'])){
//             if(WOOTAN_DEBUG) $this->wootan->debug('-> testing dim eligibility');
//             if( isset($item['length']) and isset($item['width']) and isset($item['height']) ){
//                 $dim_item     = array(
//                     $item['length'],
//                     $item['width'],
//                     $item['height']
//                 );
//                 $dim_max     = $container['max_dim'];
//
//                 $fits = false;
//                 foreach( range(0,2) as $rot ){ //inefficient
//                     $fits = true;
//                     foreach( range(0,2) as $dim){
//                         if( $dim_item[$rot] > $dim_max[($rot + $dim)%3] ){
//                             $fits = false;
//                         }
//                     }
//                     if( $fits ) break;
//                 }
//
//                 if(!$fits){
//                     if(WOOTAN_DEBUG) $this->wootan->debug('--> does not fit');
//                     return false;
//                 } else {
//                     if(WOOTAN_DEBUG) $this->wootan->debug('--> fits!');
//                 }
//             } else {
//                 if(WOOTAN_DEBUG) $this->wootan->debug('--> dims not specified');
//                 return false;
//             }
//         }
//         //vol eligiblity
//         if(isset($container['max_cubic'])){
//             if(WOOTAN_DEBUG) $this->wootan->debug('-> testing vol eligibility');
//             if(isset($item['length']) and isset($item['width']) and isset($item['height'])){
//                 $item_dim = array($item['length'], $item['width'], $item['height']);
//                 if(WOOTAN_DEBUG) $this->wootan->debug('-> item_dim'.serialize($item_dim));
//                 $item_vol = $this->get_volume($item_dim);
//                 // $vol = ($item['length']/100)  * ($item['width']/100) * ($item['height']/100) ;
//                 $max_vol = $container['max_cubic'];
//                 if( $item_vol > $max_vol ){
//                     if(WOOTAN_DEBUG) $this->wootan->debug("--> does not fit, item too big: $item_vol > $max_vol");
//                     return false;
//                 }
//             } else {
//                 if(WOOTAN_DEBUG) $this->wootan->debug('--> dims not specified');
//                 return false;
//             }
//         }
//         if(WOOTAN_DEBUG) $this->wootan->debug('--> fits!') ;
//         return true;
//     }
//
//     function write_free_fright_notice($package) {
//         $package_total = $package['contents_cost'];
//         $threshold = $this->retail_free_threshold;
//         if($package_total < $threshold){
//             $difference = ceil($threshold - $package_total);
//             $message = "spend another $$difference  and get free shipping!";
//             $this->wootan->write_notice_once($message);
//         }
//     }
//
//     function is_package_dangerous($package) {
//         // foreach( $package['contents'] as $line ){
//         //     $data = $line['data'];
//         //     if(WOOTAN_DEBUG) $this->wootan->debug("---> testing danger of ".$data->post->post_title);
//         //     $danger = get_post_meta($data->post->ID, 'wootan_danger', true);
//         //     if(WOOTAN_DEBUG) $this->wootan->debug("----> danger is ".serialize($danger));
//         //     if( $danger == "Y" ){
//         //         return true;
//         //     }
//         // }
//         // return false;
//         $contents = isset($package['contents'])?$package['contents']:array();
//         return $this->wootan->are_contents_dangerous($contents);
//     }
//
//     function is_package_po_box($package){
//         $destination = isset($package['destination'])?$package['destination']:array();
//         $line1 = isset($destination['address'])?$destination['address']:'';
//         $line2 = isset($destination['address_2'])?$destination['address_2']:'';
//         return $this->wootan->is_address_po_box($line1, $line2);
//     }
//
//     function calculate_shipping( $package=array() ) {
//         if(WOOTAN_DEBUG) $this->wootan->debug("calculating shipping for ".serialize($package));
//
//         if($this->is_package_po_box($package)){
//             if(WOOTAN_DEBUG) $this->wootan->debug("-> package is PO, no shipping options");
//             return;
//         }
//
//         $wootan_containers     = $this->get_containers();
//         $wootan_methods     = $this->get_methods();
//
//         //determine precisely how many fucks to give
//         if(WOOTAN_DEBUG) $this->wootan->debug("-> determining number of fucks given");
//         $fucks_given = array();
//         foreach( $wootan_methods as $code => $method ){
//             if( array_intersect(
//                 array(
//                     'min_total_container',
//                     'max_total_container',
//                 ),
//                 array_keys($method)
//             ) ) {
//                 $fucks_given['summary'] = true;
//             }
//             if( array_intersect(
//                 array(
//                     'include_roles',
//                     'exclude_roles',
//                 ),
//                 array_keys($method)
//             ) ) {
//                 $fucks_given['tiers'] = true;
//             }
//         }
//         if(isset($fucks_given['summary'])){
//             if(WOOTAN_DEBUG) $this->wootan->debug("--> getting summary");
//             $summary = $this->get_summary($package['contents']);
//             if($summary){
//                 if(WOOTAN_DEBUG) $this->wootan->debug("---> summary is: ".serialize($summary));
//             } else {
//                 if(WOOTAN_DEBUG) $this->wootan->debug("---> cannot get summary");
//                 return;
//             }
//         }
//         if(isset($fucks_given['tiers'])){
//             if(WOOTAN_DEBUG) $this->wootan->debug("--> getting roles");
//             $user = new WP_User( $package['user']['ID'] );
//             global $Lasercommerce_Tier_Tree;
//             if (!isset($Lasercommerce_Tier_Tree)) {
//                 $Lasercommerce_Tier_Tree = new Lasercommerce_Tier_Tree();
//             }
//
//             $visibleTiers = $Lasercommerce_Tier_Tree->getVisibleTiers($user);
//             $visibleTierIDs = $Lasercommerce_Tier_Tree->getTierIDs($visibleTiers);
//             if(WOOTAN_DEBUG) $this->wootan->debug("---> visible tiers are: ".serialize($visibleTierIDs));
//         }
//
//         foreach( $wootan_methods as $code => $method ){
//             $name = isset($method['title'])?$method['title']:$code;
//
//             if(WOOTAN_DEBUG) $this->wootan->debug("");
//             if(WOOTAN_DEBUG) $this->wootan->debug("-> testing eligibility of ".$name);
//
//             //test dangerous
//             if (isset($method['dangerous']) and $method['dangerous'] == 'N'){
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> testing dangerous criteria");
//                 $dangerous = $this->is_package_dangerous($package);
//                 if( $dangerous) {
//                     if(WOOTAN_DEBUG) $this->wootan->debug("--> failed danger criteria");
//                     continue;
//                 } else {
//                     if(WOOTAN_DEBUG) $this->wootan->debug("--> passed danger criteria");
//                 }
//
//             }
//
//             if (isset($method['include_roles'])) {
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> testing include role criteria");
//                 if( array_intersect( $method['include_roles'], $visibleTierIDs ) ) {
//                     if(WOOTAN_DEBUG) $this->wootan->debug('---> user included');
//                 } else {
//                     if(WOOTAN_DEBUG) $this->wootan->debug('---> user not included');
//                     continue;
//                 }
//             }
//             if (isset($method['exclude_roles'])) {
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> testing exclude role criteria");
//                 if( array_intersect( $method['exclude_roles'], $visibleTierIDs) ) {
//                     if(WOOTAN_DEBUG) $this->wootan->debug('---> user excluded');
//                     continue;
//                 } else {
//                     if(WOOTAN_DEBUG) $this->wootan->debug('---> user not excluded');
//                 }
//             }
//
//             //test total containers
//             if (isset($method['min_total_container']) or isset($method['max_total_container'])) {
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> testing total_container criteria");
//
//                 $cube_length = pow($summary['total_volume'], 1.0/3.0) * 1000;
//
//                 $total_item = array(
//                     'kilo'         => $summary['total_weight'],
//                     'length'     => $cube_length,
//                     'width'     => $cube_length,
//                     'height'     => $cube_length,
//                 );
//
//                 if (isset($method['min_total_container'])) {
//                     $container = $method['min_total_container'];
//                     if(WOOTAN_DEBUG) $this->wootan->debug("--> testing min_total_container criteria: ".$method['min_total_container']);
//                     if(in_array($container, array_keys($wootan_containers))){
//                         $result = $this->fits_in_container($total_item, $wootan_containers[$container]);
//                     } else {
//                         if(WOOTAN_DEBUG) $this->wootan->debug("---> container does not exist: ".$container);
//                         continue;
//                     }
//                     if(!$result){
//                         if(WOOTAN_DEBUG) $this->wootan->debug("---> passed min_total_container criteria: ".$result);
//                     } else {
//                         if(WOOTAN_DEBUG) $this->wootan->debug("---> failed min_total_container criteria: ".$result);
//                         continue;
//                     }
//                 }
//                 if (isset($method['max_total_container'])) {
//                     $container = $method['max_total_container'];
//                     if(WOOTAN_DEBUG) $this->wootan->debug("--> testing max_total_container criteria: ".$container);
//                     if(in_array($container, array_keys($wootan_containers))){
//                         $result = $this->fits_in_container($total_item, $wootan_containers[$container]);
//                     } else {
//                         if(WOOTAN_DEBUG) $this->wootan->debug("---> container does not exist: ".$container);
//                         continue;
//                     }
//                     if($result){
//                         if(WOOTAN_DEBUG) $this->wootan->debug("---> passed max_total_container criteria: ".$result);
//                     } else {
//                         if(WOOTAN_DEBUG) $this->wootan->debug("---> failed max_total_container criteria: ".$result);
//                         continue;
//                     }
//                 }
//             }
//             if (isset($method['max_item_container'])){
//                 $container = $method['max_item_container'];
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> testing item_container criteria: ".$container);
//                 $fits = true;
//                 foreach ($package['contents'] as $line) {
//
//                     if(WOOTAN_DEBUG) $this->wootan->debug("---> analysing line: ".$line['product_id']);
//                     if($line['data']->has_weight()){
//                         $item_weight = wc_get_weight($line['data']->get_weight(), 'kg');
//                     } else {
//                         // throw exception because can't get weight
//                     }
//                     if($line['data']->has_dimensions()){
//                         $item_dim = explode(' x ', $line['data']->get_dimensions());
//                         $dimension_unit = get_option( 'woocommerce_dimension_unit' );
//                         $item_dim[2] = str_replace( ' '.$dimension_unit, '', $item_dim[2]);
//                     } else {
//                         // throw exception because can't get dimensions
//                     }
//                     $item = array(
//                         'kilo' => $item_weight,
//                         'length' => $item_dim[0],
//                         'width' => $item_dim[1],
//                         'height' => $item_dim[2]
//                     );
//                     if(in_array($container, array_keys($wootan_containers))){
//                         $result = $this->fits_in_container( $item, $wootan_containers[$container]);
//                         if($result){
//                             if(WOOTAN_DEBUG) $this->wootan->debug("---> passed max_item_container criteria: ".$result);
//                         } else {
//                             if(WOOTAN_DEBUG) $this->wootan->debug("---> failed max_item_container criteria: ".$result);
//                             $fits = false;
//                             break;
//                         }
//                     } else {
//                         if(WOOTAN_DEBUG) $this->wootan->debug("---> container does not exist: ".$container);
//                         $fits = false;
//                         break;
//                     }
//                 }
//                 if( !$fits ){
//                     continue;
//                 }
//             }
//             if (isset($method['elig_fn'])) {
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> testing eligibility criteria");
//                 $result = call_user_func($method['elig_fn'], $package);
//                 if($result){
//                     if(WOOTAN_DEBUG) $this->wootan->debug("---> passed eligibility criteria: ".serialize($result));
//                 } else {
//                     if(WOOTAN_DEBUG) $this->wootan->debug("---> failed eligibility criteria: ".serialize($result));
//                     continue;
//                 }
//             }
//
//             if (isset($method['notify_shipping_upgrade'])) {
//                 if(WOOTAN_DEBUG) $this->wootan->debug("--> adding shipping upgrade notification");
//                 $this->write_free_fright_notice($package);
//             }
//
//             //gauntlet passed, add rate
//             if(WOOTAN_DEBUG) $this->wootan->debug("-> method passed");
//
//
//             if( isset($method['cost_fn']) ){
//                 $cost = call_user_func($method['cost_fn'], $package);
//                 if(! is_numeric( $cost ) ){
//                     if(WOOTAN_DEBUG) $this->wootan->debug("-> cost could not be determined!");
//                     continue;
//                 }
//             } else {
//                 if(WOOTAN_DEBUG) $this->wootan->debug("-> No Cost function set!");
//                 continue;
//             }
//
//
//             $this->add_rate(
//                 array(
//                     'id' => $this->get_rate_id($code),
//                     'label'    => $name,
//                     'cost'    => $cost,
//                     'package' => $package,
//                     //'calc_tax' => 'per_item',
//                 )
//             );
//         }
//     }
// }
