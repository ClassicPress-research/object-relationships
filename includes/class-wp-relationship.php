<?php if(!defined('ABSPATH')) { die(); }

if( !class_exists('WPRelationship') ) {
	class WPRelationship {
		public static function Instance() {
			static $instance = null;
			if ($instance === null) {
				$instance = new self();
			}
			return $instance;
		}

		protected function __construct() {
			$this->register_tables();
		}

		public function register_tables() {
			global $wpdb;

			$wpdb->tables[] = 'relationships';
			$wpdb->tables[] = 'relationshipmeta';
		}

		public function install() {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			$relate_table = "CREATE TABLE ".$wpdb->relationships." (\n".
							" relationship_id bigint(20) unsigned NOT NULL auto_increment,\n".
							" object_left_id bigint(20) unsigned NOT NULL default 0,\n".
							" object_right_id bigint(20) unsigned NOT NULL default 0,\n".
							" object_left_type varchar(255) NULL,\n".
							" object_right_type varchar(255) NULL,\n".
							" relationship_type varchar(255) NULL,\n".
							" object_left_order int(11) NOT NULL default 0,\n".
							" object_right_order int(11) NOT NULL default 0,\n".
							" PRIMARY KEY  (relationship_id),\n".
							" KEY object_left (object_left_id,object_left_type),\n".
							" KEY object_right (object_right_id,object_right_type)\n".
							") ".$charset_collate.";";
			$meta_table   = "CREATE TABLE ".$wpdb->relationshipmeta." (\n".
							" meta_id bigint(20) unsigned NOT NULL auto_increment,\n".
							" relationship_id bigint(20) unsigned NOT NULL default 0,\n".
							" meta_key varchar(255) NULL,\n".
							" meta_value longtext NULL,\n".
							" PRIMARY KEY  (meta_id),\n".
							" KEY relationship_id (relationship_id),\n".
							" KEY meta_key (meta_key)\n".
							") ".$charset_collate.";";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta( $relate_table );
			dbDelta( $meta_table );
		}

		public function get_relationship( $relationship_id = null ) {
			global $wpdb;
			if( !$relationship_id || !is_numeric( $relationship_id ) ) {
				return false;
			}
			$sql = "SELECT * FROM ".$wpdb->relationships." WHERE relationship_id=%d LIMIT 1";
			return $wpdb->get_row( $wpdb->prepare( $sql, $relationship_id ), ARRAY_A );
		}

		public function add_relationship( $args = array(), $error = false ) {
			return $this->update_relationship( $args );
		}

		public function update_relationship( $args = array(), $error = false ) {
			global $wpdb;
			// normalize all attributes
			$filtered_args = shortcode_atts( array(
				'relationship_type'  => 'related',
				'object_left_type'   => 'post',
				'object_left_id'     => 0,
				'object_right_type'  => 'post',
				'object_right_id'    => 0,
				'object_left_order'  => 'default',
				'object_right_order' => 'default',
				'meta'               => array(),
				'replace_meta'       => false,
				'bidirectional'      => true       // TODO: this has not yet been implemented
			), $args );
			// do all my input checks
			if( !$filtered_args['object_left_type'] || !$filtered_args['object_left_id'] || !is_numeric( $filtered_args['object_left_id'] ) ) {
				if( $error ) {
					return new WP_Error( 'relationship_update_error', __( 'Could not update relationship. Object 1 not properly defined', 'wp-relationships' ) );
				}
				return false;
			}
			if( !$filtered_args['object_right_type'] || !$filtered_args['object_right_id'] || !is_numeric( $filtered_args['object_right_id'] ) ) {
				if( $error ) {
					return new WP_Error( 'relationship_update_error', __( 'Could not update relationship. Object 2 not properly defined', 'wp-relationships' ) );
				}
				return false;
			}
			if( !$filtered_args['relationship_type'] ) {
				if( $error ) {
					return new WP_Error( 'relationship_update_error', __( 'Could not update relationship. Relationship type not defined', 'wp-relationships' ) );
				}
				return false;
			}

			if( $filtered_args['meta'] && !is_array( $filtered_args['meta'] ) ) {
				if( $error ) {
					return new WP_Error( 'relationship_update_error', __( 'Could not update relationship. Meta must be an array', 'wp-relationships' ) );
				}
				return false;
			}
			$data = array(
				'relationship_type'  => $filtered_args['relationship_type'],
			);
			// order ids, to prevent duplicate rows
			// TODO: only do this for bidirectional relationships
			if( $filtered_args['object_left_id'] <= $filtered_args['object_right_id'] ) {
				$data['object_left_type' ] = $filtered_args['object_left_type'];
				$data['object_left_id'   ] = $filtered_args['object_left_id'];
				$data['object_left_order'] = $filtered_args['object_left_order'];
				$data['object_right_type'] = $filtered_args['object_right_type'];
				$data['object_right_id'  ] = $filtered_args['object_right_id'];
				if( is_numeric( $filtered_args['object_left_order'] ) ) {
					$data['object_left_order'] = $filtered_args['object_left_order'];
				}
				if( is_numeric( $filtered_args['object_right_order'] ) ) {
					$data['object_right_order'] = $filtered_args['object_right_order'];
				}
			} else {
				$data['object_left_type' ] = $filtered_args['object_right_type'];
				$data['object_left_id'   ] = $filtered_args['object_right_id'];
				$data['object_right_type'] = $filtered_args['object_left_type'];
				$data['object_right_id'  ] = $filtered_args['object_left_id'];
				if( is_numeric( $filtered_args['object_left_order'] ) ) {
					$data['object_right_order'] = $filtered_args['object_left_order'];
				}
				if( is_numeric( $filtered_args['object_right_order'] ) ) {
					$data['object_left_order'] = $filtered_args['object_right_order'];
				}
			}
			// check if this pairing exists
			$sql = "SELECT relationship_id FROM ".$wpdb->relationships." WHERE".
				   " relationship_type=%s AND".
				   " object_left_type=%s AND object_left_id=%d AND".
				   " object_right_type=%s AND object_right_id=%d".
				   " LIMIT 1";
			$params = array(
				$data['relationship_type'],
				$data['object_left_type'],
				$data['object_left_id'],
				$data['object_right_type'],
				$data['object_right_id']
			);
			$id = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
			if( $id ) {
				// update
				$where = array(
					'relationship_id' => $id
				);
				$updated = $wpdb->update( $wpdb->relationships, $data, $where );
				if( !$updated ) {
					if( $error ) {
						return new WP_Error( 'relationship_update_error', __( 'Could not update relationship in the database', 'wp-relationships' ), $wpdb->last_error );
					}
					return false;
				}
				if( $filtered_args['replace_meta'] ) {
					$wpdb->delete( $wpdb->relationshipmeta, array(
						'relationship_id' => $id
					) );
				}
			} else {
				$inserted = $wpdb->insert( $wpdb->relationships, $data );
				if( !$inserted ) {
					if( $error ) {
						return new WP_Error( 'relationship_update_error', __( 'Could not insert relationship in the database', 'wp-relationships' ), $wpdb->last_error );
					}
					return false;
				}
				$id = $wpdb->insert_id;
			}
			// clear caches
			if( isset( $data['object_left_id'] ) && isset( $data['object_left_type'] ) ) {
				wp_cache_delete( $data['object_left_id'], $data['object_left_type'] . '_relationships');
			}
			if( isset( $data['object_right_id'] ) && isset( $data['object_right_type'] ) ) {
				wp_cache_delete( $data['object_right_id'], $data['object_right_type'] . '_relationships');
			}

			if( $filtered_args['meta'] ) {
				foreach( $filtered_args['meta'] as $key => $val ) {
					update_relationship_meta( $id, $key, $val );
				}
			}
			// TODO: Clear Meta Caches
			return $id;
		}

		public function delete_relationship( $args, $error = false ) {
			global $wpdb;
			$id = 0;
			$row = array();
			if( is_numeric( $args ) ) { // TODO: refactor awkward overloading of parameters
				$possible_id = $args;
				$sql = "SELECT * FROM ".$wpdb->relationships." WHERE relationship_id=%d LIMIT 1";
				$row = $wpdb->get_row( $wpdb->prepare( $sql, $possible_id ) );
				if( !$row || !isset( $row['relationship_id'] ) ) {
					if( $error ) {
						return new WP_Error( 'relationship_delete_error', __( 'Could not delete relationship. No matching relationship found', 'wp-relationships' ) );
					}
					return false;
				}
				$id = $row['relationship_id'];
			} else {
				$filtered_args = shortcode_atts( array(
					'relationship_type'  => 'related',
					'object_left_type'   => 'post',
					'object_left_id'     => 0,
					'object_right_type'  => 'post',
					'object_right_id'    => 0,
					'bidirectional'      => true        // TODO: this has not yet been implemented
				), $args );
				// do all my input checks
				if( !$filtered_args['object_left_type'] || !$filtered_args['object_left_id'] || !is_numeric( $filtered_args['object_left_id'] ) ) {
					if( $error ) {
						return new WP_Error( 'relationship_delete_error', __( 'Could not delete relationship. Object 1 not properly defined', 'wp-relationships' ) );
					}
					return false;
				}
				if( !$filtered_args['object_right_type'] || !$filtered_args['object_right_id'] || !is_numeric( $filtered_args['object_right_id'] ) ) {
					if( $error ) {
						return new WP_Error( 'relationship_delete_error', __( 'Could not delete relationship. Object 2 not properly defined', 'wp-relationships' ) );
					}
					return false;
				}
				if( !$filtered_args['relationship_type'] ) {
					if( $error ) {
						return new WP_Error( 'relationship_delete_error', __( 'Could not delete relationship. Relationship type not defined', 'wp-relationships' ) );
					}
					return false;
				}
				// strictly order the query parameters to match our standard
				// TODO: only order if bidirectional
				if( $filtered_args['object_left_id'] <= $filtered_args['object_right_id'] ) {
					$data['object_left_type' ] = $filtered_args['object_left_type'];
					$data['object_left_id'   ] = $filtered_args['object_left_id'];
					$data['object_right_type'] = $filtered_args['object_right_type'];
					$data['object_right_id'  ] = $filtered_args['object_right_id'];
				} else {
					$data['object_left_type' ] = $filtered_args['object_right_type'];
					$data['object_left_id'   ] = $filtered_args['object_right_id'];
					$data['object_right_type'] = $filtered_args['object_left_type'];
					$data['object_right_id'  ] = $filtered_args['object_left_id'];
				}
				// check if this pairing exists
				$sql = "SELECT * FROM ".$wpdb->relationships." WHERE".
					   " relationship_type=%s AND".
					   " object_left_type=%s AND object_left_id=%d AND".
					   " object_right_type=%s AND object_right_id=%d".
					   " LIMIT 1";
				$params = array(
					$data['relationship_type'],
					$data['object_left_type'],
					$data['object_left_id'],
					$data['object_right_type'],
					$data['object_right_id']
				);
				$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
				if( !$row || !isset( $row['relationship_id'] ) ) {
					if( $error ) {
						return new WP_Error( 'relationship_delete_error', __( 'Could not delete relationship. No matching relationship found', 'wp-relationships' ) );
					}
					return false;
				}
				$id = $row['relationship_id'];
			}
			if( !$id ) {
				if( $error ) {
					return new WP_Error( 'relationship_delete_error', __( 'Could not delete relationship. No matching relationship found', 'wp-relationships' ) );
				}
				return false;
			}
			// try deleting
			$deleted = $wpdb->delete( $wpdb->relationships, array(
				'relationship_id' => $id
			) );
			if( !$deleted ) {
				if( $error ) {
					return new WP_Error( 'relationship_delete_error', __( 'Could not delete relationship. No matching relationship found', 'wp-relationships' ) );
				}
				return false;
			}
			// clear caches
			if( isset( $row['object_left_id'] ) && isset( $row['object_left_type'] ) ) {
				wp_cache_delete( $row['object_left_id'], $row['object_left_type'] . '_relationships');
			}
			if( isset( $row['object_right_id'] ) && isset( $row['object_right_type'] ) ) {
				wp_cache_delete( $row['object_right_id'], $row['object_right_type'] . '_relationships');
			}
			// clear meta
			$wpdb->delete( $wpdb->relationshipmeta, array(
				'relationship_id' => $id
			) );

			// TODO: Clear Meta Caches
			return true;
		}

		public function set_relationships( $object_type = null, $object_id = null, $relationship_type = null, $relationships = array() ) { // TODO: add toggle for bidirectional
			if( !$object_type || !is_numeric( $object_id ) ) {
				return false;
			}
			if( $relationship_type ) {
				$relationships = array(
					$relationship_type => $relationships
				);
			}
			// TODO: Add shortcircuit filter?
			// get cached relationships
			$relationship_cache = wp_cache_get($object_id, $object_type . '_relationships');
			if( !$relationship_cache ) {
				$relationship_cache = $this->update_relationship_cache( $object_type, array( $object_id ) );
				$relationship_cache = $relationship_cache[$object_id];
			}
			$old_relationships = $relationship_cache;
			if( $relationships && is_array( $relationships ) ) {
				foreach( $relationships as $name => $values ) {
					if( !$values ) {
						continue;
					}
					$temp = array();
					if( isset( $old_relationships[$name] ) ) {
						$temp = $old_relationships[$name];
					}
					foreach( $values as $relationship ) {
						if( !is_array( $relationship ) ) {
							continue;
						}
						if( !isset( $relationship['object_type'] ) || !isset( $relationship['object_id'] ) ) {
							continue;
						}
						if( !isset( $relationship['meta'] ) ) {
							$relationship['meta'] = array();
						}
						$insert = array(
							'relationship_type'  => $name,
							'object_left_type'   => $object_type,
							'object_left_id'     => $object_id,
							'object_right_type'  => $relationship['object_type'],
							'object_right_id'    => $relationship['object_id'],
							'meta'               => $relationship['meta'],
							'replace_meta'       => true,
							'bidirectional'      => true        // TODO: this has not yet been implemented
						);
						if( isset( $relationship['object_left_order'] ) ) {
							$insert['object_left_order'] = $relationship['object_left_order'];
						}
						if( isset( $relationship['object_right_order'] ) ) {
							$insert['object_right_order'] = $relationship['object_right_order'];
						}
						$updated = $this->update_relationship( $insert );
						foreach( $temp as $i => $rel ) {
							if( ( $rel['object_type'] == $relationship['object_type'] ) && ( $rel['object_id'] == $relationship['object_id'] ) ) {
								unset( $temp[$i] );
							}
						}
					}
					// delete old relationships
					foreach( $temp as $rel ) {
						$deleted = $this->delete_relationship( $rel['relationship_id'] );
					}
				}
			}
		}

		public function get_relationships( $object_type = null, $object_id = null, $relationship_type = null ) {    // TODO: add toggle for bidirectional
			if( !$object_type || !is_numeric( $object_id ) ) {
				return false;
			}

			$object_id = absint( $object_id );
			if ( !$object_id ) {
				return false;
			}

			// TODO: Add shortcircuit filter?
			// get cached relationships
			$relationship_cache = wp_cache_get($object_id, $object_type . '_relationships');
			if( !$relationship_cache ) {
				$relationship_cache = $this->update_relationship_cache( $object_type, array( $object_id ) );
				$relationship_cache = $relationship_cache[$object_id];
			}

			if( !$relationship_type ) {
				return $relationship_cache;
			}

			if( isset( $relationship_cache[$relationship_type] ) ) {
				return $relationship_cache[$relationship_type];
			}

			return array();
		}

		public function update_relationship_cache( $object_type, $object_ids ) { // TODO: add toggle for bidirectional
			global $wpdb;

			if( !$object_type || !$object_ids ) {
				return false;
			}

			if( !is_array( $object_ids ) ) {
				$object_ids = preg_replace( '|[^0-9,]|', '', $object_ids );
				$object_ids = explode( ',', $object_ids );
			}

			$object_ids = array_map( 'intval', $object_ids );

			$cache_key = $object_type . '_relationships';
			$ids = array();
			$cache = array();
			foreach( $object_ids as $id ) {
				$cached_object = wp_cache_get( $id, $cache_key );
				if( false === $cached_object ) {
					$ids[] = $id;
				} else {
					$cache[$id] = $cached_object;
				}
			}

			if( empty( $ids ) ) {
				return $cache;
			}

			// Get meta info
			$id_list = join( ',', $ids );
			$query = "SELECT * FROM ".$wpdb->relationships." WHERE (\n".
					 "object_left_type=%s AND object_left_id IN (".$id_list."\n".
					 ") OR (\n".
					 "object_right_type=%s AND object_right_id IN (".$id_list."\n".
					 ") ORDER BY case \n".
					 "    when object_left_type=%s AND object_left_id IN(".$id_list.")  then object_left_order\n".
					 "    when object_right_type=%s AND object_right_id IN(".$id_list.")  then object_right_order\n".
					 "end ASC";
			$query = $wpdb->prepare( $query, array( $object_type, $object_type ) );
			$relate_list = $wpdb->get_results( $query, ARRAY_A );

			if( !empty( $relate_list ) ) {
				foreach( $relate_list as $row ) {
					$o1id = intval( $row['object_left_id'] );
					$o2id = intval( $row['object_right_id'] );
					$parsed_row = array();
					if( $row['object_left_type'] === $object_type && in_array( $o1id, $ids ) ) {
						$parsed_row[$o1id] = array(
							'relationship_id'   => $row['relationship_id'],
							'object_id'         => $o2id,
							'object_type'       => $row['object_right_type'],
							'relationship_type' => $row['relationship_type'],
						);
					}
					if( $row['object_right_type'] === $object_type && in_array( $o2id, $ids ) ) {
						$parsed_row[$o2id] = array(
							'relationship_id'   => $row['relationship_id'],
							'object_id'         => $o1id,
							'object_type'       => $row['object_left_type'],
							'relationship_type' => $row['relationship_type'],
						);
					}
					foreach( $parsed_row as $oid => $val ) {
						// Force subkeys to be array type:
						if( !isset( $cache[$oid] ) || !is_array( $cache[$oid] ) ) {
							$cache[$oid] = array();
						}
						$rtype = $val['relationship_type'];
						unset( $val['relationship_type'] );
						if( !isset( $cache[$oid][$rtype] ) || !is_array( $cache[$oid][$rtype] ) ) {
							$cache[$oid][$rtype] = array();
						}
						$cache[$oid][$rtype][] = $val;
					}
				}
			}

			foreach( $ids as $id ) {
				if( !isset( $cache[$id] ) ) {
					$cache[$id] = array();
				}
				wp_cache_add( $id, $cache[$id], $cache_key );
			}

			return $cache;
		}

	}
	WPRelationship::Instance();
}
