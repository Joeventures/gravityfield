<?php

if ( class_exists( "GFForms" ) ) {
	GFForms::include_feed_addon_framework();

	class GravityField extends GFFeedAddOn {
		protected $_version = '1.0';
		protected $_min_gravityforms_version = '2.0.2';
		protected $_slug = 'gravityfield';
		protected $_path = 'gravityfield/gravityfield.php';
		protected $_full_path = __FILE__;
		protected $_title = 'Fieldbook for Gravity Forms';
		protected $_short_title = 'Fieldbook';

		private static $_instance = null;

		// Fieldbook API Variables
		private $api_key;
		private $api_secret;
		private $book_id;

		function __construct() {
			parent::__construct();
			$this->api_key    = $this->get_plugin_setting( 'api_key' );
			$this->api_secret = $this->get_plugin_setting( 'api_secret' );
			$this->book_id    = $this->book_url_to_id();
		}

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new GravityField();
			}

			return self::$_instance;
		}

		/**
		 * Accept a book URL or book ID
		 *
		 * @param $book string
		 *
		 * @return string
		 */
		public function book_url_to_id() {
			$book = $this->get_plugin_setting( 'book_id' );
			if ( filter_var( $book, FILTER_VALIDATE_URL ) ) {
				$book = explode( '/', $book );
				$book = end( $book );
			}

			return $book;
		}

		/**
		 * Define the plugin settings
		 *
		 * @return array
		 */
		public function plugin_settings_fields() {
			return array(
				array(
					'title'  => 'API Settings',
					"fields" => array(
						array(
							'name'       => 'api_key',
							'label'      => 'API Key Username',
							'type'       => 'text',
							'input_type' => 'text',
							'class'      => 'medium',
						),
						array(
							'name'       => 'api_secret',
							'label'      => 'API Key Password',
							'type'       => 'text',
							'input_type' => 'text',
							'class'      => 'medium',
						),
						array(
							'name'       => 'book_id',
							'label'      => 'Base API URL', // Todo: allow Base API URL
							'type'       => 'text',
							'input_type' => 'text',
							'class'      => 'medium',
						),
					)
				),
			);
		}

		/**
		 * Get an array of tables in the Fieldbook Book
		 *
		 * @return array
		 */
		function get_tables() {
			$fb_connect = array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'book_id'    => $this->book_id
			);
			$fb         = new PhieldBook( $fb_connect );
			$tables     = $fb->get();

			return $tables;
		}

		/**
		 * Get an array of fields in a table, excluding linked fields
		 *
		 * @param $table string name of the table
		 *
		 * @return array
		 */
		function get_fields( $table ) {
			if ( ! $table ) {
				return array();
			}

			$fb_connect = array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'book_id'    => $this->book_id,
				'table'      => $table
			);
			$fb         = new PhieldBook( $fb_connect );
			$record     = $fb->search( array( 'limit' => 1 ) );
			$fields     = array_keys( $record['items'][0] );
			foreach ( $fields as $key => $field ) {
				if ( is_array( $record['items'][0][ $field ] ) ) {
					unset( $fields[ $key ] );
				}
			}

			return $fields;
		}

		function get_linking_fields( $table ) {
			if ( ! $table ) {
				return array();
			}

			$fb_connect = array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'book_id'    => $this->book_id,
				'table'      => $table
			);
			$fb         = new PhieldBook( $fb_connect );
			$record     = $fb->search( array( 'limit' => 1 ) );
			$fields     = array_keys( $record['items'][0] );
			foreach ( $fields as $key => $field ) {
				if ( ! is_array( $record['items'][0][ $field ] ) ) {
					unset( $fields[ $key ] );
				}
			}

			return $fields;
		}

		function get_all_fields( $table ) {
			if ( ! $table ) {
				return array();
			}

			$fb_connect = array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'book_id'    => $this->book_id,
				'table'      => $table
			);
			$fb         = new PhieldBook( $fb_connect );
			$record     = $fb->search( array( 'limit' => 1 ) );
			$fields     = array_keys( $record['items'][0] );

			return $fields;
		}

		/**
		 * Format the list of fields in the Fieldbook table into a proper field map
		 *
		 * @return array
		 */
		function map_fields( $table = null ) {
			$table     = is_null( $table ) ? $this->get_setting( 'table_name' ) : $table;
			$fields    = $this->get_fields( $table );
			$field_map = array();
			foreach ( $fields as $field ) {
				if ( 'id' == $field ) {
					continue;
				}
				if ( 'record_url' == $field ) {
					continue;
				}
				$field_map[] = array(
					'name'     => $field,
					'label'    => $field,
					'required' => false,
				);
			}

			return $field_map;
		}

		/**
		 * Get a list of Fieldbook fields in the specified feed
		 *
		 * @return array|void
		 */
		public function feed_fields_choices() {
			$feed_id = $this->get_setting( 'primary_feed' );
			if ( empty( $feed_id ) ) {
				return null;
			}
			$feed    = $this->get_feed( $feed_id );
			$choices = array();
			foreach ( $feed['meta'] as $key => $value ) {
				if ( ( substr( $key, 0, 14 ) == 'mapped_fields_' ) && ! empty( $value ) ) {
					$choice['label'] = ucwords( substr( $key, 14 ) );
					$choice['value'] = $value;
					array_push( $choices, $choice );
				}
			}

			return $choices;
		}

		/**
		 * Build the fields for the feed.
		 *
		 * @return array
		 */
		public function feed_settings_fields() {
			return array(
				array(
					// SECTION 1
					'title'  => 'Fieldbook Field Settings',
					'name'   => 'tableName',
					'fields' => array(
						$this->do_field_feed_type(),
						$this->do_field_table_name()
					),
				),
				array(
					// SECTION 2 ( dependency: table_name selected )
					'title'      => '',
					'dependency' => 'table_name',
					'fields'     => array(
						$this->g_fieldmap( 'mapped_fields', 'Map Fields', 'table_name' ),
						$this->do_field_feed_condition(),
					),
				),
				array(
					// SECTION 3 ( dependency: feed_type update )
					'title'       => 'Matching Field Criteria',
					'description' => 'Map the fields that should match an existing record.',
					'dependency'  => array( $this, 'check_update_field_dependency' ),
					'fields'      => array(
						$this->do_field_matching_fields()
					),
				),
				array(
					// SECTION 4 ( dependency: feed_type link )
					'title'       => 'Link Info',
					'description' => 'Specify the feed and field information.',
					'dependency'  => array( $this, 'check_linked_field_dependency' ),
					'fields'      => array(
						$this->do_field_primary_feed(),
						//$this->do_field_key_field()
					),
				),
				array( 'title' => '', 'fields' => array() )
			);
		}

		public function check_update_field_dependency() {
			$feed_type  = $this->get_setting( 'feed_type' );
			$table_name = $this->get_setting( 'table_name' );
			if ( ( 'update' == $feed_type || 'link' == $feed_type ) && $table_name ) {
				return true;
			} else {
				return false;
			}
		}

		public function check_linked_field_dependency() {
			$feed_type  = $this->get_setting( 'feed_type' );
			$table_name = $this->get_setting( 'table_name' );

			if ( 'link' == $feed_type && $table_name ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Define the fieldbook_list field type - a drop-down box of Fieldbook Tables
		 *
		 * @param $field
		 */
		public function settings_fieldbook_list( $field ) {
			$field['type']     = 'select';
			$field['onchange'] = 'jQuery(this).parents("form").submit();';
			$html              = $this->settings_select( $field, false );
			echo $html;
		}

		/**
		 * Only the Table Name column needs to go into the list of feeds for a GF form.
		 *
		 * @return array
		 */
		public function feed_list_columns() {
			return array(
				'table_name' => 'Table Name',
				'feed_type'  => 'Feed Type'
			);
		}

		/**
		 * Used to properly display the Table Name column in the list of feeds
		 *
		 * @param $feed
		 *
		 * @return string
		 */
		public function get_column_value_table_name( $feed ) {
			return '<b>' . ucfirst( rgars( $feed, 'meta/table_name' ) ) . '</b>';
		}

		public function get_column_value_feed_type( $feed ) {
			return ucfirst( rgars( $feed, 'meta/feed_type' ) );
		}

		/***************** PROCESS FEED BEGIN *****************/
		/**
		 * What to do when a form is submitted
		 *
		 * @param $feed
		 * @param $entry
		 * @param $form
		 */
		public function process_feed( $feed, $entry, $form ) {
			$mapped_fields = $this->get_field_map_fields( $feed, 'mapped_fields' );
			$data          = array();

			foreach ( $mapped_fields as $name => $field_id ) {
				if ( empty( $field_id ) ) {
					continue;
				}
				$field_value   = $this->get_field_value( $form, $entry, $field_id );
				$data[ $name ] = $field_value;
			}
			$fb_connect = array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'book_id'    => $this->book_id,
				'table'      => $feed['meta']['table_name']
			);
			$fb         = new PhieldBook( $fb_connect );
			$feed_type  = $feed['meta']['feed_type'];
			if ( 'create' == $feed_type ) {
				$fb->create( $data );
			} elseif ( 'update' == $feed_type ) {
				// Find a matching record
				$matching_fields = $this->get_field_map_fields( $feed, 'matching_fields' );
				$matching_data   = array();

				foreach ( $matching_fields as $name => $field_id ) {
					if ( empty( $field_id ) ) {
						continue;
					}
					$matching_data[ $name ] = $this->get_field_value( $form, $entry, $field_id );
				}

				$fb_search = new PhieldBook( $fb_connect );
				$update_id = $fb_search->search( $matching_data );

				if ( 0 != count( $update_id ) ) {
					// If there is a matching record, update it.
					$update_id               = $update_id[0]['id'];
					$fb_connect['record_id'] = $update_id;
					$fb_update               = new PhieldBook( $fb_connect );
					$fb_update->update( $data );
				} else {
					// Otherwise, create a new record
					$fb->create( $data );
				}
			} elseif ( 'link' == $feed_type ) {
				// Todo dry out the code
				$matching_fields = $this->get_field_map_fields( $feed, 'linked_fields' );
				$matching_data   = array();

				foreach ( $matching_fields as $name => $field_id ) {
					if ( empty( $field_id ) ) {
						continue;
					}
					$matching_data[ $name ] = $this->get_field_value( $form, $entry, $field_id );
				}

				$link_connect          = $fb_connect;
				$link_connect['table'] = $feed['meta']['linked_table'];
				$fb_search             = new PhieldBook( $link_connect );
				$link_id               = $fb_search->search( $matching_data );

				if ( 0 != count( $link_id ) ) {
					$link_id      = $link_id[0]['id'];
					$linked_table = $feed['meta']['linked_table'];

					$data[ $linked_table ] = array( array( 'id' => $link_id ) );
				}

				$doit = $fb->create( $data );
				echo '<pre>' . json_encode( $data ) . '</pre>';
			}
		}

		/**
		 * Checks to see if the current feed has a link feed pointing to it
		 */
		public function is_linked() {
			$feeds = $this->get_feeds( rgget( 'id' ) );
			foreach ( $feeds as $feed ) {
				if ( $feed['meta']['feed_type'] == 'link' && $_GET['fid'] == $feed['meta']['primary_feed'] ) {
					return true;
				}
			}

			return false;
		}

		/****************** PROCESS FEED END ******************/

		public function is_other_feeds_exist() {
			$feeds            = $this->get_feeds( rgget( 'id' ) );
			$feed_count_check = $_GET['fid'] == 0 ? 0 : 1;

			return ( count( $feeds ) > $feed_count_check ) ? true : false;
		}

		public function feeds_choices( $skip = null ) {
			$feeds   = $this->get_feeds( rgget( 'id' ) );
			$choices = array( array( 'label' => 'Select a Feed', 'value' => '' ) );
			foreach ( $feeds as $feed ) {
				$feed_type  = $feed['meta']['feed_type'];
				$feed_table = $feed['meta']['table_name'];
				$feed_ref   = $feed_type . ' - ' . $feed_table;
				$feed_id    = $feed['id'];
				if ( $skip == $feed_type ) {
					continue;
				}
				array_push( $choices, array( 'label' => ucwords( $feed_ref ), 'value' => $feed_id ) );
			}

			return $choices;
		}

		public function feed_fields() {
			$this->get_feed_settings_fields();
		}

		/******************* FIELDS GO HERE *******************/

		public function do_field_feed_type() {
			$choices[] = array(
				'label'   => 'Create',
				'name'    => 'create',
				'tooltip' => 'Each form entry will create a new record',
				'value'   => 'create'
			);
			$choices[] = array(
				'label'   => 'Update',
				'name'    => 'update',
				'value'   => 'update',
				'tooltip' => 'Each form entry will update an existing record, or create a new record if a match does not exist'
			);

			if ( $this->is_other_feeds_exist() ) {
				$choices[] = array(
					'label'   => 'Link',
					'name'    => 'link',
					'value'   => 'link',
					'tooltip' => 'Each form entry will update an existing record in a linked table, or create a new record if a match does not exist'
				);
			}

			return array(
				'label'      => 'Feed Type',
				'type'       => 'radio',
				'name'       => 'feed_type',
				'onchange'   => 'jQuery(this).parents("form").submit();',
				'horizontal' => true,
				'choices'    => $choices
			);
		}

		public function do_field_table_name() {
			$tables    = $this->get_tables();
			$choices[] = array( 'label' => 'Select a Fieldbook Table', 'value' => '' );
			foreach ( $tables as $table ) {
				$choices[] = array( 'label' => ucwords( $table ), 'value' => $table );
			}
			$label = $this->get_setting( 'feed_type' ) == 'link' ? 'Linked Table Name' : 'Table Name';

			return array(
				'label'      => $label,
				'type'       => 'fieldbook_list',
				'name'       => 'table_name',
				'tooltip'    => 'Select the Fieldbook Table',
				'choices'    => $choices,
				'dependency' => 'feed_type'
			);
		}

		/**
		 * @param string $name
		 * @param string $label
		 * @param string $table
		 * @param string $dependency
		 *
		 * @return array
		 */
		public function g_fieldmap( $name, $label, $table, $dependency = '' ) {
			$arr              = array();
			$arr['name']      = $name;
			$arr['label']     = $label;
			$arr['type']      = 'field_map';
			$arr['field_map'] = $this->map_fields( $this->get_setting( $table ) );
			if ( $dependency != '' ) {
				$arr['dependency'] = $dependency;
			}

			return $arr;
		}

		public function field_map_title() {
			return 'Fieldbook Field';
		}

		public function do_field_feed_condition() {
			return array(
				'type'           => 'feed_condition',
				'name'           => 'createcondition',
				'label'          => 'Create Condition',
				'checkbox_label' => 'Enable Condition',
				'instructions'   => 'Create a new record if'
			);
		}

		public function do_field_matching_fields() {
			return array(
				'name'      => 'matching_fields',
				'label'     => 'Matching Fields',
				'type'      => 'field_map',
				'field_map' => $this->map_fields()
			);
		}

		public function do_field_primary_feed() {
			return array(
				'name'     => 'primary_feed',
				'label'    => 'Primary Feed',
				'type'     => 'select',
//				'onchange' => 'jQuery(this).parents("form").submit();',
				'choices'  => $this->feeds_choices( 'link' )
			);
		}

		public function do_field_key_field() {
			return array(
				'name'       => 'key_field',
				'label'      => 'Key Field',
				'type'       => 'select',
				'dependency' => 'primary_feed',
				'choices'    => $this->feed_fields_choices()
			);
		}
	}

	new GravityField();
}