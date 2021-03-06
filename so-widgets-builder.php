<?php
/*
Plugin Name: SiteOrigin Widgets Builder
Description: An extension for the widgets bundle that lets you easily build custom widgets.
Version: dev
Text Domain: so-widgets-builder
Domain Path: /languages
Author: SiteOrigin
Author URI: https://siteorigin.com
Plugin URI: https://siteorigin.com/widgets-bundle/
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt
*/

define('SOW_BUILDER_VERSION', 'dev');

class SiteOrigin_Widgets_Builder {

	function __construct(){
		add_action( 'plugins_loaded', array( $this, 'initialize' ) );
	}

	static function single(){
		static $single;
		if( empty( $single ) ) {
			$single = new self();
		}
		return $single;
	}

	function initialize(){
		if(
			! defined( 'SOW_BUNDLE_VERSION' ) ||
			( SOW_BUNDLE_VERSION !== 'dev' && version_compare( SOW_BUNDLE_VERSION, '1.6.3', '<' ) ) ||
			version_compare( phpversion(), '5.4.0', '<' )
		) {
			add_action( 'admin_menu', array( $this, 'setup_diagnosis_page' ), 5 );
		}
		else {
			include plugin_dir_path( __FILE__ ) . 'inc/builder-form.class.php';
			include plugin_dir_path( __FILE__ ) . 'inc/custom-widget.class.php';

			add_action( 'init', array( $this, 'register_post_type' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post', array( $this, 'save_post' ) );
			add_action( 'widgets_init', array( $this, 'register_widgets' ) );

			add_action( 'wp_ajax_so_export_widgets', array( $this, 'export_widgets' ) );

			if( ! class_exists( 'Twig_Autoloader' ) ) {
				// Setup the autoloader for Twig
				require_once plugin_dir_path( __FILE__ ) . 'inc/Twig/Autoloader.php';
				Twig_Autoloader::register();
			}
		}
	}

	function register_post_type() {
		if( current_user_can( 'manage_options' ) ) {
			register_post_type( 'so-custom-widget', array(
				'label' => __( 'Widgets Builder', 'so-widgets-bundle' ),
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true,
				'supports' => array( 'title'  ),
				'show_in_menu' => 'tools.php',
			) );
		}
	}

	function add_meta_boxes(){
		add_meta_box(
			'so-widget-builder-settings',
			__( 'Custom Widget Settings', 'so-widgets-bundle' ),
			array( $this, 'meta_box_callback' ),
			'so-custom-widget',
			'normal',
			'default'
		);
	}

	function meta_box_callback( $post ){
		$settings = get_post_meta( $post->ID, 'so_custom_widget', true );
		$form_object = new SiteOrigin_Widgets_Builder_Form();
		$form_object->form( $settings );
		wp_nonce_field( 'save-custom-widget', '_so_nonce' );
	}

	function save_post( $post_id ){
		if( empty( $_POST['_so_nonce'] ) || ! wp_verify_nonce( $_POST['_so_nonce'], 'save-custom-widget' ) ) return;
		if( ! current_user_can( 'edit_post', $post_id ) ) return;
		if( empty( $_POST['so_custom_widget'] ) ) return;

		$custom_widget = get_post_meta( $post_id, 'so_custom_widget', true );
		$form_object = new SiteOrigin_Widgets_Builder_Form();
		$custom_widget = $form_object->update( $_POST['so_custom_widget'], $custom_widget );

		update_post_meta( $post_id, 'so_custom_widget', $custom_widget );
	}

	/**
	 * Register all the widgets created in this process
	 */
	function register_widgets(){
		global $wpdb;

		$results = $wpdb->get_results( "
			SELECT ID, post_title, post_name
			FROM $wpdb->posts
			WHERE post_type = 'so-custom-widget' AND post_status = 'publish'
		" );

		if( empty( $results ) ) return;

		global $wp_widget_factory;

		foreach( $results as $result ) {
			$custom_widget = get_post_meta( $result->ID, 'so_custom_widget', true );

			// Generate a widget class for this widget
			$widget_class = str_replace( '-', ' ', $result->post_name );
			$widget_class = str_replace( ' ', '', ucwords( $widget_class ) );
			$widget_class = 'SiteOrigin_Custom_Widget_' . $widget_class;

			$widget_obj = new SiteOrigin_Widget_Custom_Widget(
				'so-custom-' . $result->post_name,
				$widget_class,
				$result->post_title,
				$custom_widget[ 'description' ],
				$custom_widget
			);
			$wp_widget_factory->widgets[ $widget_class ] = $widget_obj;
		}
	}

	function setup_diagnosis_page(){
		add_submenu_page(
			'tools.php',
			__( 'SiteOrigin Widgets Builder Diagnoses', 'so-widgets-builder' ),
			__( 'Widgets Builder', 'so-widgets-builder' ),
			'manage_options',
			'sow-builder-diagnosis',
			array( $this, 'display_diagnosis_page' )
		);
	}

	function diagnosis_information(){
		$diagnosis = array();

		$diagnosis[] = __( 'Widgets Bundle Installed: ', 'so-widgets-builder' ) .
			( defined( 'SOW_BUNDLE_VERSION' ) ? __( 'Yes', 'so-widgets-builder' ) : '<strong>' . __( 'No', 'so-widgets-builder' ) . '</strong>' );

		if( ! defined( 'SOW_BUNDLE_VERSION' )  ) {
			$diagnosis[] = sprintf(
				__( '<strong>Note:</strong> Please install the %sSiteOrigin Widgets Bundle%s.', 'so-widgets-builder' ),
				'<a href="https://wordpress.org/plugins/so-widgets-bundle/" target="_blank">',
				'</a>'
			);
		}
		else {
			$diagnosis[] = __( 'Widgets Bundle Version: ', 'so-widgets-builder' ) . SOW_BUNDLE_VERSION;
			if( SOW_BUNDLE_VERSION !== 'dev' && version_compare( SOW_BUNDLE_VERSION, '1.6.3', '<' ) ) {
				$diagnosis[] = sprintf(
					__( '<strong>Note:</strong> Please update %sSiteOrigin Widgets Bundle%s.', 'so-widgets-builder' ),
					'<a href="' . admin_url( 'update-core.php' ) . '">',
					'</a>'
				);
			}
		}

		$diagnosis[] = __( 'PHP Version: ', 'so-widgets-builder' ) . phpversion();
		if( version_compare( phpversion(), '5.4.0', '<' ) ) {
			$diagnosis[] = __( '<strong>Note:</strong> PHP version 5.4.0 or above is required.', 'so-widgets-builder' );
		}

		return implode( "\n", $diagnosis );
	}

	function display_diagnosis_page(){
		include plugin_dir_path( __FILE__ ) . '/tpl/diagnosis.php';
	}

	function export_widgets(){
		global $wpdb;
		$results = $wpdb->get_results( "
			SELECT ID, post_title, post_name
			FROM $wpdb->posts
			WHERE post_type = 'so-custom-widget' AND post_status = 'publish'
		" );

		include plugin_dir_path( __FILE__ ) . 'inc/exporter-widget.class.php';

		foreach( $results as $result ) {
			$custom_widget = get_post_meta( $result->ID, 'so_custom_widget', true );

			// Generate a widget class for this widget
			$widget_class = str_replace( '-', ' ', $result->post_name );
			$widget_class = str_replace( ' ', '', ucwords( $widget_class ) );
			$widget_class = 'SiteOrigin_Custom_Widget_' . $widget_class;

			$widget_obj = new SiteOrigin_Widget_Exporter_Widget(
				'so-custom-' . $result->post_name,
				$widget_class,
				$result->post_title,
				$custom_widget[ 'description' ],
				$custom_widget
			);

			$code = $widget_obj->get_php_code( array(
				'widget_id' => $result->post_name,
				'widget_name' => $result->post_title,
				'widget_description' => $custom_widget['description'],
			) );

			echo '<pre>';
			echo htmlspecialchars( $code );
			echo '</pre>';
		}

		exit();
	}
}
SiteOrigin_Widgets_Builder::single();