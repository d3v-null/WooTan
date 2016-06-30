<?php


include_once('Wootan_LifeCycle.php');

class Wootan_Plugin extends Wootan_LifeCycle {

    private static $instance;

    public static function init() {
        if ( self::$instance == null ) {
            self::$instance = new Wootan_Plugin();
        }
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
            'ATextInput' => array(__('Enter in some text', 'my-awesome-plugin')),
            'Donated' => array(__('I have donated to this plugin', 'my-awesome-plugin'), 'false', 'true'),
            'CanSeeSubmitData' => array(__('Can See Submission data', 'my-awesome-plugin'),
                                        'Administrator', 'Editor', 'Author', 'Contributor', 'Subscriber', 'Anyone')
        );
    }

//    protected function getOptionValueI18nString($optionValue) {
//        $i18nValue = parent::getOptionValueI18nString($optionValue);
//        return $i18nValue;
//    }

    // protected function initOptions() {
    //     $options = $this->getOptionMetaData();
    //     if (!empty($options)) {
    //         foreach ($options as $key => $arr) {
    //             if (is_array($arr) && count($arr > 1)) {
    //                 $this->addOption($key, $arr[1]);
    //             }
    //         }
    //     }
    // }

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
    }

    function write_notice_once($message) {
        if(!property_exists($this, 'printed_messages')){
            $this->printed_messages = array();
        }
        if(!in_array($message, $this->printed_messages)){
            wc_add_notice($message, $notice_type='notice');
            array_push($this->printed_messages, $message);
        }
    }

    function write_danger_notice() {
        $message = __(
            'Sorry, you are not eligible for air freight options because '
            .'your cart contains dangerous goods'
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

    public function is_product_dangerous($_product) {
        if(WOOTAN_DEBUG) error_log("---> testing danger of ".serialize($_product));
        if($_product instanceof WC_Product){
            $_product = $_product->get_id();
        }
        if($_product and is_numeric($_product)){
            $danger = get_post_meta($_product, 'wootan_danger', true);
            if(WOOTAN_DEBUG) error_log("----> danger is ".serialize($danger));
            return $danger == "Y";
        }
        if(WOOTAN_DEBUG) error_log("----> danger not found ".serialize($danger));
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
        if(WOOTAN_DEBUG) error_log("---> testing po box of ".serialize(array($line1, $line2)));

        //TODO: fix this
        $replace  = array(" ", ".", ",");
        $address  = strtolower( str_replace( $replace, '', $line1.$line2 ) );
        if(strstr($address, "pobox") !== false){
            if(WOOTAN_DEBUG) error_log("----> contains po box");
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
    }

    public function maybe_do_ajax_cart_messages() {
        if($this->is_customer_po_box()){
            $this->write_po_box_notice(true);
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
        add_filter(
            'woocommerce_shipping_methods',
            function( $methods ){
                require_once( dirname( __FILE__ ) .  "/WC_TechnoTan_Shipping.php" );
                $methods[] = 'WC_Technotan_Shipping';
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

        add_filter(
            'woocommerce_after_checkout_validation',
            array($this, 'woocommerce_after_checkout_validation')
        );

        add_filter(
            'woocommerce_shipping_methods',
            function($methods){
                $methods['TechnoTan_Shipping'] = 'WC_TechnoTan_Shipping';
                return $methods;
            }
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


}
