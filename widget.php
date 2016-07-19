<?php

/**
 * Class SiteOrigin_Widget_CustomBuilt_Widget
 */
class SiteOrigin_Widget_CustomBuilt_Widget extends SiteOrigin_Widget {

	private $custom_options;

	function __construct( $id, $widget_class, $name, $description, $custom_options ) {
		$this->custom_options = $custom_options;
		$this->widget_class = $widget_class;

		parent::__construct(
			$id,
			$name,
			array(
				'description' => $description,
			),
			array( ),
			array( ),
			plugin_dir_path( __FILE__ )
		);
	}

	/**
	 * Initialize the form based on the custom_options.
	 *
	 * @return array
	 */
	function initialize_form(){
		// Convert the $custom_options into a form array
		$form = $this->generate_form_array( $this->custom_options[ 'fields' ] );
		if( $this->custom_options['has_title'] ) {
			$form = array_merge( array(
				'title' => array(
					'label' => __( 'Title', 'so-widgets-bundle' ),
					'type' => 'text',
				),
			), $form );
		}

		return $form;
	}

	/**
	 * Initialize the custom widget.
	 */
	function initialize() {
		if( ! empty( $this->custom_options[ 'scripts' ] ) ) {
			// Register the scripts
			$scripts = array();
			foreach( $this->custom_options[ 'scripts' ] as $script ) {
				if( empty( $script['file'] ) ) continue;

				$url = wp_get_attachment_url( $script['file'] );
				$file = get_attached_file( $script['file'] );

				if( empty( $url ) || empty( $file ) ) continue;

				$scripts[] = array(
					$this->id . '-script-' . intval( $script['file'] ),
					$url,
					$script['jquery'] ? array( 'jquery' ) : array( ),
					md5_file( $file )
				);
			}

			if( ! empty( $scripts ) ) {
				$this->register_frontend_scripts( $scripts );
			}
		}

		if( ! empty( $this->custom_options[ 'styles' ] ) ) {
			// Register the styles
			$styles = array();
			foreach( $this->custom_options[ 'styles' ] as $style ) {
				if( empty( $style['file'] ) ) continue;

				$url = wp_get_attachment_url( $style['file'] );
				$file = get_attached_file( $style['file'] );

				if( empty( $url ) || empty( $file ) ) continue;

				$styles[] = array(
					$this->id . '-script-' . intval( $style['file'] ),
					$url,
					$script['jquery'] ? array( 'jquery' ) : array( ),
					md5_file( $file )
				);
			}

			if( ! empty( $styles ) ) {
				$this->register_frontend_scripts( $styles );
			}
		}
	}

	/**
	 * Convert part of the custom_options array into a form array
	 *
	 * @param $custom_fields
	 * @return array
	 */
	private function generate_form_array( $custom_fields ) {
		$fields = array();

		foreach( $custom_fields as $cf ) {
			$cf_args = $cf;
			unset( $cf_args[ 'variable' ] );
			unset( $cf_args[ 'sub_fields' ] );
			$fields[ $cf[ 'variable' ] ] = $cf_args;

			if( $cf[ 'type' ] == 'repeater' || $cf['type'] == 'section' ) {
				$fields[ $cf[ 'variable' ] ][ 'fields' ] = $this->generate_form_array( $cf['sub_fields'] );
			}
		}

		return $fields;
	}

	function get_html_content( $instance, $args, $template_vars, $css_name ){
		$tpl = $this->custom_options[ 'template_code' ];

		// Process the code using Dust
		$twig = $this->get_twig( $tpl );
		$tpl = $twig->render( 'default.tpl', $instance );

		// Add the title field if there is one
		if( $this->custom_options[ 'has_title' ] && !empty( $instance['title'] ) ) {
			$tpl = $args[ 'before_title' ] . $instance['title'] . $args[ 'after_title' ] . $tpl;
		}

		return $tpl;
	}

	/**
	 * Get less variables based on the instance and LESS content.
	 *
	 * @param $instance
	 * @return array
	 */
	function get_less_variables( $instance ) {
		$less = $this->custom_options[ 'less_code' ];

		$return = array();

		preg_match_all( '/\@(.*?) *\:.*?;/', $less, $matches );

		if( !empty( $matches[0] ) ) {
			for( $i = 0; $i < count( $matches[0] ); $i++ ) {
				$parts = explode( '-', $matches[1][$i] );

				$value = null;

				foreach( $parts as $p ) {
					if( is_null( $value ) ) {
						if( isset( $instance[$p] ) ) {
							$value = $instance[$p];
						}
						else {
							$value = null;
							continue;
						}
					}
					else {
						if( isset( $value[$p] ) ) {
							$value = $value[$p];
						}
						else {
							$value = null;
							continue;
						}
					}
				}

				if( !is_null( $value ) && ! is_array( $value ) ) {
					$return[ $matches[1][$i] ] = $value;
				}
			}
		}
		return $return;
	}

	function get_less_content( $instance ){
		return $this->custom_options[ 'less_code' ];
	}

	function get_twig( $tpl ){
		$loader = new Twig_Loader_Array( array(
			'default.tpl' => $tpl,
		) );
		$twig = new Twig_Environment( $loader, array(
			'autoescape' => true,
		) );

		$twig->addFilter( new Twig_SimpleFilter('panels_render', function ( $panels_data ) {
			return function_exists( 'siteorigin_panels_render' ) ?
				siteorigin_panels_render( 'w'.substr( md5( json_encode( $panels_data ) ), 0, 8 ), true, $panels_data ) :
				__( 'Page builder is required to render this field.', 'so-widgets-builder' );
		} ) );

		$twig->addFilter( new Twig_SimpleFilter('image', function ( $id, $type = 'html', $size = 'full' ) {
			switch( $type ) {
				case 'html' :
					return wp_get_attachment_image( $id, $size );
					break;

				default :
					$src = wp_get_attachment_image_src( $id, $size );
					if( empty( $src ) ) return '';
					if( $type == 'src' ) {
						return $src[0];
					}
					else if( $type == 'width' ) {
						return $src[1];
					}
					else if( $type == 'height' ) {
						return $src[2];
					}
					break;
			}
		} ) );

		return $twig;
	}
}