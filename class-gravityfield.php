<?php

if ( class_exists( "GFForms" ) ) {
	GFForms::include_feed_addon_framework();

	class GravityField extends GFFeedAddOn {
		protected $_version = '1.0';
		protected $_min_gravityforms_version = '2.0.2';
		protected $_slug = 'gravityfield';
		protected $_path = 'gravityfield/gravityfield.php';
		protected $_full_path = __FILE__;
		protected $_title = 'GravityField for FieldBook Integration';
		protected $_short_title = 'GravityField';

		private static $_instance = null;

		// FieldBook API Variables
		private $api_key;
		private $api_secret;
		private $book_id;

		function __construct() {
			parent::__construct();
			$this->api_key    = $this->get_plugin_setting( 'api_key' );
			$this->api_secret = $this->get_plugin_setting( 'api_secret' );
			$this->book_id    = $this->get_plugin_setting( 'book_id' );
		}

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new GravityField();
			}

			return self::$_instance;
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
							'label'      => 'API Key',
							'type'       => 'text',
							'input_type' => 'text',
							'class'      => 'medium',
						),
						array(
							'name'       => 'api_secret',
							'label'      => 'API Secret',
							'type'       => 'text',
							'input_type' => 'text',
							'class'      => 'medium',
						),
						array(
							'name'       => 'book_id',
							'label'      => 'Book ID',
							'type'       => 'text',
							'input_type' => 'text',
							'class'      => 'medium',
						),
					)
				),
			);
		}

		/**
		 * Get an array of tables in the FieldBook Book
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
		 * Get an array of fields in a table
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

			// Do not support linked fields for now
			// #todo Add support for linked fields
			foreach ( $fields as $key => $field ) {
				if ( is_array( $record['items'][0][ $field ] ) ) {
					unset( $fields[ $key ] );
				}
			}

			return $fields;
		}

		/**
		 * Format the list of fields in the Fieldbook table into a proper field map
		 *
		 * @return array
		 */
		function map_fields() {
			$table     = $this->get_setting( 'table_name' );
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
		 * Build the fields for the feed.
		 *
		 * @return array
		 */
		public function feed_settings_fields() {
			$tables    = $this->get_tables();
			$choices[] = array( 'label' => 'Select a FieldBook Table', 'value' => '' );
			foreach ( $tables as $table ) {
				$choices[] = array( 'label' => ucwords( $table ), 'value' => $table );
			}

			return array(
				array(
					'title'  => 'FieldBook Field Settings',
					'name'   => 'tableName',
					'fields' => array(
						array(
							'label'      => 'Feed Type',
							'type'       => 'radio',
							'name'       => 'feed_type',
							'onchange'   => 'jQuery(this).parents("form").submit();',
							'horizontal' => true,
							'choices'    => array(
								array(
									'name'    => 'create',
									'tooltip' => 'Each form entry will create a new record',
									'label'   => 'Create',
									'value'   => 'create'
								),
								array(
									'name'    => 'update',
									'tooltip' => 'Each form entry will update an existing record, or create a new record if a match does not exist',
									'label'   => 'Update',
									'value'   => 'update'
								),
								array(
									'name'    => 'link',
									'tooltip' => 'Each form entry will create a record linked to a record in another table',
									'label'   => 'Create Linked',
									'value'   => 'link'
								)
							)
						),
						array(
							'label'    => 'Table Name',
							'type'     => 'fieldbook_list',
							'name'     => 'table_name',
							'tooltip'  => 'Select the FieldBook Table',
							'choices'  => $choices,
							'required' => true
						)
					),
				),
				array(
					'title'      => '',
					// The table must first be selected for the remaining fields to display
					'dependency' => 'table_name',
					'fields'     => array(
						array(
							'name'      => 'mapped_fields',
							'label'     => 'Map Fields',
							'type'      => 'field_map',
							'field_map' => $this->map_fields()
						),
						// Limit the conditions where it's appropriate to create a record in Fieldbook
						// Todo: Add support for updating existing records - use radio option to choose
						//       between create or update
						array(
							'type'           => 'feed_condition',
							'name'           => 'createcondition',
							'label'          => 'Create Condition',
							'checkbox_label' => 'Enable Condition',
							'instructions'   => 'Create a new record if'
						),
					),
				),
				array(
					'title'       => 'Matching Field Criteria',
					'description' => 'Map the fields that should match an existing record.',
					'dependency'  => array(
						'field'  => 'feed_type',
						'values' => array( 'update' )
					),
					'fields'      => array(
						array(
							'name'      => 'matching_fields',
							'label'     => 'Matching Fields',
							'type'      => 'field_map',
							'field_map' => $this->map_fields()
						),
					),
				),
				array('title' => '', 'fields' => array())
			);
		}

		/**
		 * Define the fieldbook_list field type - a drop-down box of Fieldbook Tables
		 *
		 * @param $field
		 */
		public function settings_fieldbook_list( $field ) {
			$tables    = $this->get_tables();
			$choices[] = array( 'label' => 'Select a FieldBook Table', 'value' => '' );
			foreach ( $tables as $table ) {
				$choices[] = array( 'label' => ucwords( $table ), 'value' => $table );
			}
			$field['type']     = 'select';
			$field['choices']  = $choices;
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
				'table_name' => 'Table Name'
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

		/**
		 * What to do when a form is submitted
		 *
		 * Todo: Add support for updating existing records
		 * Todo: Add support for linked fields
		 *
		 * @param $feed
		 * @param $entry
		 * @param $form
		 */
		public function process_feed( $feed, $entry, $form ) {
			$mapped_fields = $this->get_field_map_fields( $feed, 'mapped_fields' );
			$data = array();

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
			$fb = new PhieldBook( $fb_connect );
			$feed_type = $feed['meta']['feed_type'];
			if ( 'create' == $feed_type ) {
				$fb->create( $data );
			} elseif ( 'update' == $feed_type ) {
				// Find a matching record
				$matching_fields = $this->get_field_map_fields( $feed, 'matching_fields' );
				$matching_data = array();

				foreach($matching_fields as $name => $field_id ) {
					if( empty($field_id)) continue;
					$matching_data[$name] = $this->get_field_value($form, $entry, $field_id);
				}

				$fb_search = new PhieldBook($fb_connect);
				$update_id = $fb_search->search($matching_data);

				if(0 != count($update_id)) {
					// If there is a matching record, update it.
					$update_id               = $update_id[0]['id'];
					$fb_connect['record_id'] = $update_id;
					$fb_update               = new PhieldBook( $fb_connect );
					$fb_update->update( $data );
				} else {
					// Otherwise, create a new record
					$fb->create($data);
				}
			}
		}
	}

	new GravityField();
}