<?php


include_once('Wootan_LifeCycle.php');
// include_once('WC_TechnoTan_Shipping.php');

class Wootan_Plugin extends Wootan_LifeCycle {

    private static $instance;
    private static $logger;

    public static function init() {
        if ( self::$instance == null ) {
            self::$instance = new Wootan_Plugin();
        }

        self::$logger = wc_get_logger();
    }

    public static function instance() {
        if ( self::$instance == null ) {
            self::init();
        }

        return self::$instance;
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=31
     * @return array of option meta data.
     */
    public function getOptionMetaData() {
        //  http://plugin.michael-simpson.com/?page_id=31
        return array(
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            'Donated' => array(__('I have donated to this plugin'), 'false', 'true'),
            'CanSeeSubmitData' => array(__('Can See Submission data'),
                                        'Administrator', 'Editor', 'Author', 'Contributor', 'Subscriber', 'Anyone'),
            'ShippingID' => array('custom_shipping')
        );
    }

//    protected function getOptionValueI18nString($optionValue) {
//        $i18nValue = parent::getOptionValueI18nString($optionValue);
//        return $i18nValue;
//    }

    protected function initOptions() {
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr) > 0) {
                    $this->addOption($key, $arr[0]);
                }
            }
        }
    }

    public function getPluginDisplayName() {
        return 'WooTan';
    }

    protected function getMainPluginFileName() {
        return 'wootan.php';
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Called by install() to create any database tables if needed.
     * Best Practice:
     * (1) Prefix all table names with $wpdb->prefix
     * (2) make table names lower case only
     * @return void
     */
    protected function installDatabaseTables() {
        //        global $wpdb;
        //        $tableName = $this->prefixTableName('mytable');
        //        $wpdb->query("CREATE TABLE IF NOT EXISTS `$tableName` (
        //            `id` INTEGER NOT NULL");
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Drop plugin-created tables on uninstall.
     * @return void
     */
    protected function unInstallDatabaseTables() {
        //        global $wpdb;
        //        $tableName = $this->prefixTableName('mytable');
        //        $wpdb->query("DROP TABLE IF EXISTS `$tableName`");
    }


    /**
     * Perform actions when upgrading from version X to version Y
     * See: http://plugin.michael-simpson.com/?page_id=35
     * @return void
     */
    public function upgrade() {
        $this->initOptions();
        $this->updateOption('ShippingID', 'custom_shipping');
    }

    /** fixes this issue where cookie is not generated until cart is created:
        https://github.com/woocommerce/woocommerce/issues/4920
     */
    function notice_session_fix() {
        global $woocommerce;
        if(!isset($woocommerce->session) || !$woocommerce->session->has_session()){
            do_action( 'woocommerce_set_cart_cookies',  true );
        }
    }

    function write_notice_once($message) {
        if(!wc_has_notice($message, 'notice')){
            wc_add_notice($message, 'notice');
        }
    }

    function write_danger_notice() {
        $message = __(
            'Sorry, you are not eligible for air freight options because '
            .'your cart contains items that cannot be shipped by air'
        );
        $this->write_notice_once($message);
    }

    function write_po_box_notice($ajax=false) {
        $message = __(
            'Sorry, you are not eligible for freight options because '
            .'your address is a PO box'
        );
        if($ajax){
            global $woocommerce;
            $woocommerce->add_error($message);
        }
        $this->write_notice_once($message);
    }

    // function write_shipping_total_notice() {
    //     $contents = WC()->cart->get_cart();
    //     foreach ($variable as $key => $value) {
    //         # code...
    //     }
    //     $message = __('Your total shipping amount is ');
    //     if($ajax){
    //         global $woocommerce;
    //         $woocommerce->add_error($message);
    //     }
    //     $this->write_notice_once($message);
    // }

    public function is_product_dangerous($_product) {
        // if(WOOTAN_DEBUG) error_log("---> testing danger of ".serialize($_product));
        if($_product instanceof WC_Product){
            $_product = $_product->get_id();
        }
        if($_product and is_numeric($_product)){
            $danger = get_post_meta($_product, 'wootan_danger', true);
            // if(WOOTAN_DEBUG) error_log("----> danger is ".serialize($danger));
            return $danger == "Y";
        }
        // if(WOOTAN_DEBUG) error_log("----> danger not found ".serialize($danger));
    }

    public function are_contents_dangerous($contents) {
        if(!is_array($contents)){
            return false;
        }
		foreach( $contents as $line ){
            $data = isset($line['data'])?$line['data']:array();

            $danger = $this->is_product_dangerous($line['product_id']);
			if($danger){
                return true;
            }

		}
		return false;
	}

    public function is_cart_dangerous() {
        $contents = WC()->cart->get_cart();
        return $this->are_contents_dangerous($contents);
    }

    public function is_address_po_box($line1, $line2='') {
        // if(WOOTAN_DEBUG) error_log("---> testing po box of ".serialize(array($line1, $line2)));

        //TODO: fix this
        $replace  = array(" ", ".", ",");
        $address  = strtolower( str_replace( $replace, '', $line1.$line2 ) );
        if(strstr($address, "pobox") !== false){
            // if(WOOTAN_DEBUG) error_log("----> contains po box");
            return true;
        }
    }

    public function is_customer_po_box() {
        $customer = WC()->customer;
        $shipping_address_1 = $customer->get_shipping_address();
        $shippihg_address_2 = $customer->get_shipping_address_2();
        return $this->is_address_po_box($shipping_address_1, $shippihg_address_2);
    }

    public function maybe_print_cart_messages() {
        if($this->is_customer_po_box()){
            $this->write_po_box_notice();
        }
        if($this->is_cart_dangerous()){
            $this->write_danger_notice();
        }
        // $this->write_shipping_total_notice();
    }

    public function maybe_do_ajax_cart_messages() {
        if($this->is_customer_po_box()){
            $this->write_po_box_notice(true);
        }
    }

    function help_tip( $tip, $content, $allow_html = false ) {

        if( $allow_html ) {
            $tip = wp_kses( $tip, wp_kses_allowed_html() );
            $tip = html_entity_decode($tip);
        } else {
            $tip = esc_attr( $tip );
        }

        // error_log("tip: ".serialize($tip));

        return  ''
            // . '<div class="wt-title-wrapper>"'
            . '<span class="wt-tooltip" >'
            // . '<span class="wt-title-content">'
            . $content
            // . '</span>'
            . '<i class="fa fa-question-circle" aria-hidden="true"></i>'
            // . '?'
            . '</span>'
            . '<div class="wt-tooltip-box">'. $tip . '</div>'
            // . '</div>'
            ;

            // . '<div class="wt-tooltip-handle" >'
            // . '<i class="fa fa-question-circle" aria-hidden="true"></i>'
            // . '<div class="wt-tooltip-box">'
            // . '<span class="wt-tooltip-text">'. $tip . '</span>'
            // . '</div>'
            // . '</div>';
    }

    public function add_shipping_method_title_tooltop($label, $rate=false){
        $meta = $rate->get_meta_data();
        if($meta && isset($meta['tooltip']) && ! empty($meta['tooltip'])){
            // error_log("meta: ".serialize($meta));
            $label = $this->help_tip($meta['tooltip'], $label, true);
            // $label .= $tip_html;
            // $label .= htmlspecialchars($tip_html);
        }
        // $label .= "</div>";
        return $label;
    }

    public function maybe_enqueue_enable_tooltip() {
        if(is_cart() || is_checkout()) {
            // $this->debug("enabling tooltip");
            // wp_register_style( 'bootstrap-css','https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css', false, null );
            // wp_register_script( 'bootstap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js', array('jquery'), null );
            // wp_register_script(
            //     'wt_enable_tooltip_js',
            //     plugins_url('/wootan/js/enable_tooltip.js'),
            //     array(
            //         'jquery', 'jquery-ui-core', 'jquery-ui-tooltip',
            //         'jquery-ui-widget', 'jquery-ui-position', 'jquery-effects-core',
            //         // 'bootstrap-js'
            //     ),
            //     0.1,
            //     true
            // );
            // wp_enqueue_script('bootstrap-js');
            // wp_enqueue_script('wt_enable_tooltip_js');
            // wp_enqueue_style('bootstrap-css');
            wp_register_style(
                'wt_tooltip_css',
                plugins_url('/wootan/css/tooltip.css')
                // array('bootstrap-css')
            );
            wp_enqueue_style('wt_tooltip_css');
        } else {
            // $this->debug("not enabling tooltip");
        }
    }

    public function addActionsAndFilters() {


        // Add options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        // add_action('admin_menu', array(&$this, 'addSettingsSubMenuPage'));

        // Example adding a script & style just for the options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        //        if (strpos($_SERVER['REQUEST_URI'], $this->getSettingsSlug()) !== false) {
        //            wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
        //            wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        }


        // Add Actions & Filters
        // http://plugin.michael-simpson.com/?page_id=37

        // register shipping method
        add_action(
            'woocommerce_shipping_init',
            function(){
                if( ! class_exists("WC_TechnoTan_Shipping") ){
                    include( "WC_TechnoTan_Shipping.php" );
                }
            }
        );

        $shipping_id = $this->getOption('ShippingID');
        add_filter(
            'woocommerce_shipping_methods',
            function( $methods ) use ($shipping_id){
                $methods[$shipping_id] = 'WC_TechnoTan_Shipping';
                return $methods;
            }
        );

        add_filter(
            'woocommerce_before_checkout_form',
            array($this, 'maybe_print_cart_messages')
        );

        add_filter(
            'woocommerce_before_cart_contents',
            array($this, 'maybe_print_cart_messages')
        );

        // add_filter(
        //     'woocommerce_after_checkout_validation',
        //     array($this, 'woocommerce_after_checkout_validation')
        // );

        add_filter(
            'woocommerce_cart_shipping_method_full_label',
            array($this, 'add_shipping_method_title_tooltop'),
            0,
            2
        );

        add_action(
            'wp_enqueue_scripts',
            array($this, 'maybe_enqueue_enable_tooltip'),
            99
        );

        /**
         * fix notice not displaying before session created
         */
        add_action(
            'woocommerce_add_to_cart',
            array($this, 'notice_session_fix')
        );


        // Adding scripts & styles to all pages
        // Examples:
        //        wp_enqueue_script('jquery');
        //        wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));


        // Register short codes
        // http://plugin.michael-simpson.com/?page_id=39


        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41

    }

    /**
     * Add a log entry.
     *
     * @param string $level One of the following:
     *     'emergency': System is unusable.
     *     'alert': Action must be taken immediately.
     *     'critical': Critical conditions.
     *     'error': Error conditions.
     *     'warning': Warning conditions.
     *     'notice': Normal but significant condition.
     *     'informational': Informational messages.
     *     'debug': Debug-level messages.
     * @param string $message Log message.
     * @param array $context Optional. Additional information for log handlers.
     */
    public function log($level, $message, array $context=array()){
        $default_context = array('source'=>$this->getPluginDisplayName());
        $context = array_merge($default_context, $context);

        self::$logger->log($level, $message, $context);
    }

    public function debug($message, array $context=array()){
        $this->log('debug', $message, $context);
    }


}
