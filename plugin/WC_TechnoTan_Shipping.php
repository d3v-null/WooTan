<?php

include_once('Wootan_Plugin.php');

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
        // if(WOOTAN_DEBUG) error_log("BEGIN WC_TechnoTan_Shipping->__construct(\$instance_id=$instance_id)");

        $this->id                       = $this->wootan->getOption('ShippingID');
        // if(WOOTAN_DEBUG) error_log("WC_TechnoTan_Shipping->__construct: \$this->id = ".serialize($this->id));
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

        // if(WOOTAN_DEBUG) error_log("END WC_TechnoTan_Shipping->__construct()");
    }

    /**
     * Initialize.
     */
    public function init() {
        // if(WOOTAN_DEBUG) $this->wootan->debug("BEGIN WC_TechnoTan_Shipping->init()");

        $this->dimension_unit = get_option( 'woocommerce_dimension_unit', 'mm' );
        $this->weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
        $this->volume_unit = 'm^3';
        $this->currency = get_option( 'woocommerce_currency', 'AUD');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // $this->init_instance_settings(); # Is this necessary?

        // Define user set variables
        $this->title = $this->get_option( 'title' );
        $this->enabled = $this->get_option( 'enabled' ); # is this necessary?
        $this->cubic_rate = floatval( $this->get_option('cubic_rate') );
        if( floatval($this->get_option('override_cubic_rate')) ){
            $this->cubic_rate = floatval($this->get_option('override_cubic_rate'));
        }
        if( $this->cubic_rate <= 0 ){
            // Sanity check
            $message = "invalid cubic weight: $this->cubic_rate. Should be a number above zero";
            $this->wootan->debug("invalid cubic weight: $this->cubic_rate. Should be a number above zero");
            $this->errors[] = $message;
        }
        $this->tax_status         = $this->get_option( 'tax_status' );
        $this->retail_free_threshold = floatval( $this->get_option( 'retail_free_threshold' ));

        // Actions
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        if( !empty($this->errors) ){
            add_action( 'admin_notices', array($this, 'display_errors'), 999 );
        }


        if(WOOTAN_DEBUG) $this->wootan->debug("END WC_TechnoTan_Shipping->init()");
    }

    /**
     * Init form fields.
     */
    public function init_form_fields() {
        $this->instance_form_fields =  array(
            'title' => array(
                'title'         => __( 'Title' ),
                'type'          => 'text',
                'description'   => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default'       => __( 'TechnoTan Custom Shipping Instance' ),
                'desc_tip'      => true,
            ),
            'tooltip' => array(
                'title'         => __( 'Tooltip' ),
                'description'   => __( 'Information to show to the user when selecting shipping method' ),
                'type'          => 'tooltip',
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
            'po_box' => array(
                'title'         => __( 'Allow PO Box' ),
                'description'   => __( 'Allow items to be shipped to PO Boxes in this instance' ),
                'desc_tip'      => true,
                'type'          => 'select',
                'default'       => 'N',
                'options'       => array(
                    'Y'=>        __( 'Yes' ),
                    'N'=>        __( 'No' )
                )
            ),
            'include_roles' => array(
                'title'         => __( 'Roles Allowed' ),
                'description'   => __( 'Allow these roles to see this shipping method. None = all roles can see'),
                'desc_tip'      => true,
                'type'          => 'role_list',
            ),
            'exclude_roles' => array(
                'title'         => __( 'Roles Excluded' ),
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
            'min_order' => array(
                'title'         => __( 'Minimum Order Value' ),
                'description'   => __( 'The minimum total order value for which this method appears'),
                'desc_tip'      => true,
                'type'          => 'nonnegative_number',
            ),
            'max_order' => array(
                'title'         => __( 'Maximum Order Value'),
                'description'   => __( 'The maximum total order value for which this method appears'),
                'desc_tip'      => true,
                'type'          => 'nonnegative_number',
            ),
            'notify_free_shipping' => array(
                'title'         => __( 'Notify Free Shipping' ),
                'description'   => __( 'Notify the customer that they can get free shipping if their order is above max_order'),
                'desc_tip'      => true,
                'type'          => 'checkbox'
            ),
            'override_cubic_rate' => array(
                'title'         => __( 'Override Cubic Rate' ),
                'description'   => __( 'If set, this value will override the global cubic rate for this method only' ),
                'desc_tip'      => true,
                'type'          => 'nonnegative_number'
            ),
            'cost' => array(
                'title'         => __( 'Cost Formula' ),
                'type'          => 'text',
                'description'   => __( 'Enter the formula for calculating cost (excl. tax), e.g. <code>4 + 12.95 * [max_containers]</code>.' )
                                   . '<br/><br/>' . __( 'Where <code>[max_containers]</code> is the number of Maximum Item containers (or part thereof) required to fit the whole package.' )
                                   . '<br/>' .__( 'And <code>[shipping_weight]</code> is the greatest of the weight or equivalent volumetric weight determined by cubic_rate of the whole package.'),
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
            'cubic_rate' => array(
                'title'    => __("Cubic Rate"),
                'type'    => 'nonnegative_number',
                'description' => __('Rate ') . '( ' . $this->weight_unit . ' / '. $this->volume_unit . ' )',
                'desc_tip'    => true,
                'default'    => '250'
            ),
            'containers' => array(
                'title' => __("Shipping Containers"),
                'type' => 'textarea',
                'description' => __('Containers used for sending items.') . '<br/>'
                                 . __('If no dimensions or volume specified, dimensions will be restricted to the equivalent volumentric weight of the container determined by cubic rate.'),
                'desc_tip' => true,
                'default' =>
                    '{"AIRBAG1":{"weight":1.1, "dimensions":[70, 165, 260]}, '
                    .'"AIRBAG3":{"weight":2.7, "dimensions":[150, 160, 260]}, '
                    .'"AIRBAG5":{"weight":4.5, "dimensions":[250, 170, 310]}, '
                    .'"AIRBAG6":{"weight":5.5, "dimensions":[270, 275, 320]}, '
                    .'"LABEL1":{"weight":1.1}, '
                    .'"LABEL3":{"weight":2.7}, '
                    .'"LABEL5":{"weight":4.5}, '
                    .'"LABEL10":{"weight":9.5}, '
                    .'"LABEL20":{"weight":19.2}'
                    .'}'
            )
        );
    }

    // public function get_admin_options_html(){
    //     $response = parent::get_admin_options_html();
    //     ob_start();
    //     $this->display_errors();
    //     $errors_html = ob_get_clean();
    //     $response .= $errors_html;
    //     // $response = '<div><h2>Errors</h2>' . $errors_html . '</div><hr/>' . $response;
    //     // $response .= "<h2>Test Results</h2>"
    //     //     ."<p><strong>CONTAINERS: </strong>".serialize($this->get_containers())."</p>"
    //     //     ."<p><strong>CONTAINER_OPTIONS: </strong>".serialize($this->get_container_options())."</p>"
    //     //     ."<p><strong>ROLES: </strong>".serialize($this->get_role_options())."</p>";
    //     return $response;
    // }

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
        $containers = $this->get_containers();
        if( empty($containers) ){
            return array(''=>__('Please initialize containers in shipping settings'));
        }
        $container_options = array(''=>'--');
        foreach ($containers as $container => $details) {
            $container_name = __("max weight: ") . number_format_i18n($details['weight']) . $this->weight_unit;
            if(isset($details['dimensions'])){
                $container_name .= __("; max dims: ") . implode("x", $details['dimensions']) . $this->dimension_unit;
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
        // error_log("validating role_list field $key $value");
        $value = parent::validate_multiselect_field($key, $value);
        // error_log("returning $value");
        return $value;
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
        // error_log("validating container_list field $key $value");
        $value = parent::validate_select_field($key, $value);
        // error_log("returning $value");
        return $value;
    }

    public function generate_nonnegative_number_html( $key, $data ) {
        return parent::generate_decimal_html($key, $data);
    }

    public function generate_tooltip_html( $key, $data ) {
        return parent::generate_textarea_html($key, $data);
    }

    public function validate_tooltip_field( $key, $value ){
        // error_log("validating tooltip field $key $value");
        $value = is_null( $value ) ? '' : $value;
        $value = wp_kses( trim( stripslashes( $value ) ),
            array_merge(
                array(
                    'iframe' => array( 'src' => true, 'style' => true, 'id' => true, 'class' => true ),
                    'div' => array( 'align' => true, 'dir' => true, 'lang' => true, 'xml:lang' => true, 'class' => true, 'id' => true, 'style' => true, 'title' => true, 'role' => true )
                ),
                wp_kses_allowed_html( 'post' )
            )
        );
        // $value = htmlentities($value);
        // error_log("returning $value");
        return $value;
    }

    public function validate_nonnegative_number_field( $key, $value ) {
        // error_log("validating nonnegative_number html $key $value");
        $value = parent::validate_decimal_field($key, $value);
        if(is_null($value)){
            $value = '';
        } else if(floatval($value) < 0) {
            $message = "invalid value for $key: $value => ".floatval($value).". Not a nonnegative number";
            $this->wootan->debug($message);
            $this->errors[] = $message;
            $value = '';
        }
        // error_log("returning $value");
        return $value;
    }

    protected function evaluate_cost( $sum, $args = array() ) {
        include_once( WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php' );

        // Allow 3rd parties to process shipping cost arguments
        $defaults = array(
            'max_containers' => 1,
            'shipping_weight' => 0
        );
        $args           = wp_parse_args($args, $defaults);
        $args           = apply_filters( 'woocommerce_evaluate_shipping_cost_args', $args, $sum, $this );
        $locale         = localeconv();
        $decimals       = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );

        $sum = str_replace(
            array(
                '[max_containers]',
                '[shipping_weight]'
            ),
            array(
                $args['max_containers'],
                $args['shipping_weight']
            ),
            $sum
        );


        // Remove whitespace from string
        $sum = preg_replace( '/\s+/', '', $sum );

        // Remove locale from string
        $sum = str_replace( $decimals, '.', $sum );

        // Trim invalid start/end characters
        $sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

        // remove $ characters
        $sum = preg_replace('/\$/', '', $sum);

        // Do the math
        return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
    }

    public function get_containers() {
        $containers_json = $this->get_option('containers');

        // if(WOOTAN_DEBUG) $this->wootan->debug("containers_json: ".serialize($containers_json));

        $containers = json_decode($containers_json, true);

        // all containers must have either dimensions or volume set
        // if(WOOTAN_DEBUG) $this->wootan->debug("containers_pre: ".serialize($containers));
        if( empty($containers) ){
            return array();
        }
        foreach ($containers as $key => $container) {
            if(! isset($container['weight'])){
                $message = "invalid container: $key. No weight set.";
                $this->wootan->debug($message);
                $this->errors[] = $message;
                unset($containers[$key]);
                continue;
            } elseif ( !isset($container['dimensions']) && !isset($container['volume']) && $this->cubic_rate ){
                $weight = floatval($container['weight']);
                $container['volume'] = $weight / $this->cubic_rate;
            }
        }
        // if(WOOTAN_DEBUG) $this->wootan->debug("containers: ".serialize($containers));
        return $containers;
    }

    function is_package_po_box($package){
        $destination = isset($package['destination'])?$package['destination']:array();
        $line1 = isset($destination['address'])?$destination['address']:'';
        $line2 = isset($destination['address_2'])?$destination['address_2']:'';
        return $this->wootan->is_address_po_box($line1, $line2);
    }

    function is_package_dangerous($package) {
        $contents = isset($package['contents'])?$package['contents']:array();
        return $this->wootan->are_contents_dangerous($contents);
    }

    /**
     * Get Volume of product in SI units (cubic meters)
     */
    public function get_volume_si($dimensions){
        // if(WOOTAN_DEBUG) $this->wootan->debug("getting volume of ".serialize($dimensions));
        if( empty($dimensions) ){
            // if(WOOTAN_DEBUG) $this->wootan->debug("-> invalid dimensions supplied ");
            return false;
        }
        $volume = 1;
        foreach($dimensions as $dimension){
            $meters = wc_get_dimension($dimension, 'm', $this->dimension_unit);
            // if(WOOTAN_DEBUG) $this->wootan->debug("--> dimension $dimension in meters: ".serialize($meters));
            $volume = $volume * $meters;
        }
        // if(WOOTAN_DEBUG) $this->wootan->debug("-> final volume: $volume meters cubed");
        return $volume;
    }

    public function get_summary($contents){
        // if(WOOTAN_DEBUG) $this->wootan->debug("getting totals for contents: ".serialize($contents));
        // if(WOOTAN_DEBUG) $this->wootan->debug("dimensions are in $this->dimension_unit");
        $summary = array(
            'weight' => 0,
            'volume' => 0,
            'max_dim' => 0,
            'items' => array()
        );

        foreach($contents as $line){
            // if(WOOTAN_DEBUG) $this->wootan->debug("-> analysing line: ".$line['product_id']);
            // if(WOOTAN_DEBUG) $this->wootan->debug("--> line virtual: ".$line['data']->is_virtual());
            if($line['data']->is_virtual()){
                continue;
            }

            if($line['data']->has_weight()){
                $item_weight = wc_get_weight( $line['data']->get_weight(), $this->weight_unit);
                // if(WOOTAN_DEBUG) $this->wootan->debug("--> item weight: $item_weight");
                $summary['weight'] += $line['quantity'] * $item_weight;
            } else {
                $message = "can't get weigh of ". $line['product_id'];
                if(WOOTAN_DEBUG) $this->wootan->debug("--> failed to get summary - $message: ".serialize($line['data']));
                $this->errors[] = $message;
                return false;
            }
            if($line['data']->has_dimensions()){
                $item_dim = array_values($line['data']->get_dimensions(false));
                $item_dim = array_map('floatval', $item_dim);
                foreach ($item_dim as $dimension) {
                    if( $dimension > $summary['max_dim'] ){
                        $summary['max_dim'] = $dimension;
                    }
                }
                // if(WOOTAN_DEBUG) $this->wootan->debug("--> item dim: ".serialize($item_dim));
                $item_vol = $this->get_volume_si($item_dim);
                // if(WOOTAN_DEBUG) $this->wootan->debug("--> item vol: $item_vol");
                $summary['volume'] += $line['quantity'] * $item_vol;
            } else {
                $message = "can't get dimensions of ". $line['product_id'];
                if(WOOTAN_DEBUG) $this->wootan->debug("--> failed max_item_container criteria - $message ".serialize($line['data']));
                $this->errors[] = $message;
                return false;
            }
            $item = array(
                'weight' => $item_weight,
                'length' => $item_dim[0],
                'width' => $item_dim[1],
                'height' => $item_dim[2]
            );
            foreach (range(0, $line['quantity']) as $index) {
                $summary['items'][] = $item;
            }
        }
        // if(WOOTAN_DEBUG) $this->wootan->debug("-> summary: ".serialize($summary));
        return $summary;
    }

    /**
     * return whether an item fits in a container.
     */
    public function fits_in_container($item, $container){
        if(WOOTAN_DEBUG) $this->wootan->debug(
            'testing eligibility of '.
            serialize($item).
            ' for container '.
            serialize($container)
        );
        $remainder = $container;

        $dim_item = array(0,0,0);
        if( isset($container['volume']) || isset($container['dimensions']) ){
            if( isset($item['length']) and isset($item['width']) and isset($item['height']) ){
                $dim_item     = array(
                    $item['length'],
                    $item['width'],
                    $item['height']
                );
            } else {
                if(WOOTAN_DEBUG) $this->wootan->debug('--> dims not specified');
                return false;
            }
        }
        if(WOOTAN_DEBUG) $this->wootan->debug('-> item dims: '.serialize($dim_item));

        //weight eligibility
        if(isset($container['weight'])){
            if(WOOTAN_DEBUG) $this->wootan->debug('-> testing weight eligibility');
            if(isset($item['weight'])){
                if($item['weight'] > $container['weight']){
                    if(WOOTAN_DEBUG) $this->wootan->debug('--> does not fit, item too heavy');
                    return false;
                } else {
                    if(WOOTAN_DEBUG) $this->wootan->debug('--> fits!');
                    $remainder['weight'] -= $item['weight'];
                }
            } else {
                if(WOOTAN_DEBUG) $this->wootan->debug('--> no weight specified');
                return false;
            }
        }
        //dim eligibility
        if(isset($container['dimensions'])){
            if(WOOTAN_DEBUG) $this->wootan->debug('-> testing dim eligibility');
            $dim_max = $container['dimensions'];
            $best_remaining_dimensions = array(0,0,0);
            $fits = false;
            $reason = '';
            foreach( range(0,2) as $rotation ){
                // Rotate the dimensions of the item
                array_push($dim_item, array_shift($dim_item));
                $fits = true; // assume it fits until proven otherwise
                $remaining_dimensions = array();
                foreach (range(0,2) as $dimension ) {
                    if( $dim_item[$dimension] > $dim_max[$dimension] ){
                        $reason = "dimension too big: ". $dim_item[$dimension] . $this->dimension_unit;
                        $fits = false;
                        break;
                    }
                    $remaining_dimensions[] = $dim_max[$dimension] - $dim_item[$dimension];
                }
                if( !$fits ){
                    continue;
                }
                if( $this->get_volume_si($remaining_dimensions) > $this->get_volume_si($best_remaining_dimensions) ){
                    $best_remaining_dimensions = $remaining_dimensions;
                }
            }
            if(!$fits){
                if(WOOTAN_DEBUG) $this->wootan->debug('--> does not fit: '.$reason);
                return false;
            }
            if(WOOTAN_DEBUG) $this->wootan->debug('--> fits!');
            $remainder['dimensions'] = $best_remaining_dimensions;

        }
        //vol eligiblity
        if(isset($container['volume'])){
            if(WOOTAN_DEBUG) $this->wootan->debug('-> testing vol eligibility');
            $max_vol = $container['volume'];
            $item_vol = $this->get_volume_si($dim_item);
            if( $item_vol > $max_vol ){
                if(WOOTAN_DEBUG) $this->wootan->debug("--> does not fit, item too big: $item_vol > $max_vol");
                return false;
            }
            $remainder['volume'] -= $item_vol;
        }
        if(WOOTAN_DEBUG) $this->wootan->debug('--> fits!') ;
        return $remainder;
    }

    /**
     * Calculate the number of containers required to store a list of items
     * TODO: containers_required()
     */
    function containers_required( $items, $container ){
        // if(WOOTAN_DEBUG) $this->wootan->debug(
        //     'testing number of '
        //     . serialize($container)
        //     . ' containers required for items: '
        //     . serialize($items)
        // );
        $bin_capacities = array(
            $container
        );
        foreach ($items as $item) {
            $requires_new_bin = true;
            foreach( $bin_capacities as $bin_index => $bin_capacity ){
                $remainder = $this->fits_in_container($item, $bin_capacity);
                if( $remainder ){
                    $bin_capacities[$bin_index] = $remainder;
                    $requires_new_bin = false;
                    break;
                }
            }
            if( $requires_new_bin ){
                $remainder = $this->fits_in_container($item, $container);
                if($remainder){
                    $bin_capacities[] = $remainder;
                } else {
                    return false;
                }
            }
        }

        return count($bin_capacities);
    }

    function calculate_shipping( $package=array() ) {
        if(WOOTAN_DEBUG) $this->wootan->debug("calculating shipping for ".serialize($package)." in method: ".$this->title);

        $po_box_allowed = $this->get_option('po_box');
        if(! empty($po_box_allowed) and $po_box_allowed == 'N' ){
            if($this->is_package_po_box($package)){
                if(WOOTAN_DEBUG) $this->wootan->debug("-> package is PO but method does not allow PO.");
                return;
            }
        }

        $dangerous_allowed = $this->get_option('dangerous');
        if (! empty($dangerous_allowed) and $dangerous_allowed == 'N'){
            if(WOOTAN_DEBUG) $this->wootan->debug("--> testing dangerous criteria");
            $package_dangerous = $this->is_package_dangerous($package);
            if( $package_dangerous) {
                if(WOOTAN_DEBUG) $this->wootan->debug("--> failed danger criteria");
                return;
            } else {
                if(WOOTAN_DEBUG) $this->wootan->debug("--> passed danger criteria");
            }

        }

        if(WOOTAN_DEBUG) $this->wootan->debug("--> getting summary");
        $summary = $this->get_summary($package['contents']);
        if($summary){
            if(WOOTAN_DEBUG) $this->wootan->debug("---> summary is: ".serialize($summary));
        } else {
            if(WOOTAN_DEBUG) $this->wootan->debug("---> cannot get summary");
            return;
        }


        $container_limits = array();
        foreach(array('min_total', 'max_total', 'max_item') as $container_name){
            $container_limits[$container_name] = $this->get_option($container_name."_container");
        }

        if( ! empty( array_filter( array_values($container_limits))) ) {

            $wootan_containers = $this->get_containers();

            if( count($summary['items']) == 1 ) {
                $total_item = $summary['items'][0];
            } else if ($summary['max_dim']) {
                $length_m = wc_get_dimension($summary['max_dim'], 'm', $this->dimension_unit);
                $square_length = wc_get_dimension(pow($summary['volume'] / $length_m, 1.0/2.0), $this->dimension_unit, 'm');
                $total_item = array(
                    'weight'         => $summary['weight'],
                    'length'     => $summary['max_dim'],
                    'width'     => $square_length,
                    'height'     => $square_length,
                );
            } else {
                $cube_length = wc_get_dimension(pow($summary['volume'], 1.0/3.0), $this->dimension_unit, 'm');
                $total_item = array(
                    'weight'         => $summary['weight'],
                    'length'     => $cube_length,
                    'width'     => $cube_length,
                    'height'     => $cube_length,
                );
            }

            if( ! empty($container_limits['min_total']) ) {
                $container = $container_limits['min_total'];
                if(WOOTAN_DEBUG) $this->wootan->debug("--> testing min_total_container criteria: ".$container);
                if(in_array($container, array_keys($wootan_containers))){
                    $result = $this->fits_in_container($total_item, $wootan_containers[$container]);
                } else {
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> min_total_container does not exist: ".$container);
                    return;
                }
                if(!$result){
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> passed min_total_container criteria: ".serialize($result));
                } else {
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> failed min_total_container criteria: ".serialize($result));
                    return;
                }
            }

            if( ! empty($container_limits['max_total']) ) {
                $container = $container_limits['max_total'];
                if(WOOTAN_DEBUG) $this->wootan->debug("--> testing max_total_container criteria: ".$container);
                if(in_array($container, array_keys($wootan_containers))){
                    $result = $this->fits_in_container($total_item, $wootan_containers[$container]);
                } else {
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> max_total_container does not exist: ".$container);
                    return;
                }
                if($result){
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> passed max_total_container criteria: ".serialize($result));
                } else {
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> failed max_total_container criteria: ".serialize($result));
                    return;
                }
            }

            if( ! empty($container_limits['max_item']) ) {
                $container = $container_limits['max_item'];
                if(WOOTAN_DEBUG) $this->wootan->debug("--> testing max_item_container criteria: ".$container);
                $fits = true;
                foreach ($summary['items'] as $item) {

                    if(WOOTAN_DEBUG) $this->wootan->debug("---> analysing item: ".serialize($item));

                    if(in_array($container, array_keys($wootan_containers))){
                        $result = $this->fits_in_container( $item, $wootan_containers[$container]);
                        if($result){
                            if(WOOTAN_DEBUG) $this->wootan->debug("---> passed max_item_container criteria - fits in container ".serialize($result));
                        } else {
                            if(WOOTAN_DEBUG) $this->wootan->debug("---> failed max_item_container criteria - fits in container: ".serialize($result));
                            return;
                        }
                    } else {
                        if(WOOTAN_DEBUG) $this->wootan->debug("---> max_item_container does not exist: ".$container);
                        return;
                    }
                }
            }
        }

        $role_limits = array();
        foreach (array('include', 'exclude') as $key) {
            $role_limits[$key] = $this->get_option($key.'_roles');
        }
        if( ! empty( array_filter( array_values($role_limits))) ) {
            if(WOOTAN_DEBUG) $this->wootan->debug("--> getting roles");
            $user = new WP_User( $package['user']['ID'] );

            // bypass role limit checks if user is admin
            if( !user_can($user, 'manage_woocommerce') ){
                if($this->tree){
                  $visibleTiers = $this->tree->getVisibleTiers($user);
                  $visibleTierIDs = $this->tree->getTierIDs($visibleTiers);
                } else {
                  $visibleTierIDs = $user->roles;
                }
                if(WOOTAN_DEBUG) $this->wootan->debug("---> visible tiers are: ".serialize($visibleTierIDs));

                if (isset($role_limits['include'])) {
                    if(WOOTAN_DEBUG) $this->wootan->debug("--> testing include role criteria");
                    if( array_intersect( $role_limits['include'], $visibleTierIDs ) ) {
                        if(WOOTAN_DEBUG) $this->wootan->debug('---> user included');
                    } else {
                        if(WOOTAN_DEBUG) $this->wootan->debug('---> user not included');
                        return;
                    }
                }
                if (isset($role_limits['exclude'])) {
                    if(WOOTAN_DEBUG) $this->wootan->debug("--> testing exclude role criteria");
                    if( ! array_intersect( $role_limits['exclude'], $visibleTierIDs) ) {
                        if(WOOTAN_DEBUG) $this->wootan->debug('---> user not excluded');
                    } else {
                        if(WOOTAN_DEBUG) $this->wootan->debug('---> user excluded');
                        return;
                    }
                }
            }

        }

        # Min / max order value
        $order_limits = array();
        foreach( array('min', 'max') as $key ) {
            $order_limits[$key] = floatval($this->get_option($key . '_order'));
        }
        if( !empty( array_filter( array_values( $order_limits )))) {
            if(WOOTAN_DEBUG) $this->wootan->debug("--> getting order value");
            $order_value = floatval($package['contents_cost']);
            if( isset($order_limits['min']) && $order_limits['min'] > 0){
                if( $order_limits['min'] > $order_value ){
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> failed min_order criteria: ".serialize($order_value). " > " .serialize($order_limits['min']));
                    return;
                } else {
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> passed min_order criteria: ".serialize($order_value));
                }
            }
            if( isset($order_limits['max']) && $order_limits['max'] > 0 ){
                if( $order_limits['max'] <= $order_value ){
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> failed max_order criteria: ".serialize($order_value) . " > " .serialize($order_limits['max']));
                    return;
                } else {
                    if(WOOTAN_DEBUG) $this->wootan->debug("---> passed max_order criteria: ".serialize($order_value));
                }
                if( $this->get_option('notify_free_shipping') ) {
                    $difference = ceil($order_limits['max'] - $order_value);
                    $message = "spend another $$difference and get free shipping!";
                    $this->wootan->write_notice_once($message);
                }
            }
        }



        # Cost calculation
        $cost = 0;
        $cost_formula = $this->get_option( 'cost' );

        if ( '' !== $cost_formula ) {
            $cost_args = array();
            if( ! empty($container_limits['max_item']) ) {
                $cost_args['max_containers'] = $this->containers_required(
                    $summary['items'],
                    $wootan_containers[$container_limits['max_item']]
                );
                if(! $cost_args['max_containers']){
                    return;
                }
            }
            $cost_args['shipping_weight'] = max(array(
                $summary['weight'],
                $summary['volume'] * $this->cubic_rate
            ));

            $cost = $this->evaluate_cost(
                $cost_formula,
                $cost_args
            );
        }

        $rate = array(
            'label' => $this->title,
            'package' => $package,
            'cost' => $cost,
        ) ;

        if( $this->get_option('tooltip') ){
            $rate['meta_data'] = array();
            $rate['meta_data']['tooltip'] = esc_textarea($this->get_option('tooltip'));
        }

        if(WOOTAN_DEBUG) $this->wootan->debug("adding rate: ".serialize($rate));

        $this->add_rate( $rate );
    }
}
