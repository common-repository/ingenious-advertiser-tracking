<?php
/**
 * Extends the WC_Settings_Page class
 *
 * @link       https://i19s.com
 * @since      1.0.0
 *
 * @package    Ingenious_Woocommerce
 * @subpackage Ingenious_Woocommerce/admin
 */


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Ingenious_WooCommerce_WC_Settings' ) ) {

    /**
     * Settings class
     *
     * @since 1.0.0
     */
    class Ingenious_WooCommerce_WC_Settings extends WC_Settings_Page {

        /**
         * Constructor
         * @since  1.0
         */
        public function __construct() {

            $this->id    = 'ingenious-woocommerce';
            $this->label = __( 'Ingenious Advertiser Tracking', 'ingenious-woocommerce' );

            // Define all hooks instead of inheriting from parent                    
            add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
            add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
            add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
            add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );

        }


        /**
         * Get sections.
         *
         * @return array
         */
        public function get_sections() {
            $sections = array(
                '' => __( 'Settings', 'ingenious-woocommerce' ),
                'log' => __( 'Log', 'ingenious-woocommerce' ),
                'results' => __( 'Sale Results', 'ingenious-woocommerce' )
            );

            return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
        }


        /**
         * Get settings array
         *
         * @return array
         */
        public function get_settings() {

            global $current_section;
            $prefix = 'ingenious_woocommerce_';
            $settings = array();

            switch ($current_section) {
                case 'log':
                    $settings = array(
                        array()
                    );
                    break;
                default:
                    $settings = array(
                        array(
                            'name' => __('Ingenious Id`s', 'ingenious-woocommerce'),
                            'type' => 'title',
                            'id' => $prefix . 'Ingenious Id´s'
                        ),
                        array(
                            'id' => $prefix . 'advertiser_id',
                            'name' => __('Advertiser Id', 'ingenious-woocommerce'),
                            'type' => 'text',
                            'desc_tip' => __('')
                        ),
                        array(
                            'id' => $prefix . 'tracking_domain',
                            'name' => __('Tracking Domain', 'ingenious-woocommerce'),
                            'type' => 'text',
                            'desc_tip' => __('')
                        ),
                    );
            }

            return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
        }

        /**
         * Output the settings
         */
        public function output() {
            global $current_section;

            switch ($current_section) {
                case 'results':
                    include 'partials/ingenious-woocommerce-settings-results.php';
                    break;
                default:
                    $settings = $this->get_settings();
                    WC_Admin_Settings::output_fields( $settings );
            }

        }

        /**
         * Save settings
         *
         * @since 1.0
         */
        public function save() {
            $settings = $this->get_settings();

            WC_Admin_Settings::save_fields( $settings );
        }

    }

}

return new Ingenious_WooCommerce_WC_Settings();
