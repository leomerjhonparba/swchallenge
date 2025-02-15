<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmProPost {
	public static function save_post( $action, $entry, $form ) {
		if ( $entry->post_id ) {
			$post = get_post( $entry->post_id, ARRAY_A );
			unset( $post['post_content'] );
			$new_post = self::setup_post( $action, $entry, $form );
			self::insert_post( $entry, $new_post, $post, $form, $action );
		} else {
			self::create_post( $entry, $form, $action );
		}
	}

	/**
	 * Creates post from entry.
	 *
	 * @param int|object   $entry
	 * @param int|object   $form
	 * @param false|object $action
	 * @return int|null
	 */
	public static function create_post( $entry, $form, $action = false ) {
		global $wpdb;

		$entry_id = is_object( $entry ) ? $entry->id : $entry;
		$form_id  = is_object( $form ) ? $form->id : $form;

		if ( ! $action ) {
			$action = FrmFormAction::get_action_for_form( $form_id, 'wppost', 1 );

			if ( ! $action ) {
				return null;
			}
		}

		if ( ! is_object( $entry ) ) {
			$entry = FrmEntry::getOne( $entry, true );
		}

		$post              = self::setup_post( $action, $entry, $form );
		$post['post_type'] = $action->post_content['post_type'];
		$status            = ! empty( $post['post_status'] );

		if ( ! $status && $action && in_array( $action->post_content['post_status'], array( 'pending', 'publish' ), true ) ) {
			$post['post_status'] = $action->post_content['post_status'];
		}

		if ( ! empty( $action->post_content['display_id'] ) ) {
			$post['post_custom']['frm_display_id'] = $action->post_content['display_id'];
		} elseif ( ! is_numeric( $action->post_content['post_content'] ) ) {
			// Do not set frm_display_id if the content is mapped to a single field
			// check for auto view and set frm_display_id - for reverse compatibility
			$display = FrmProDisplay::get_auto_custom_display( compact( 'form_id', 'entry_id' ) );
			if ( $display ) {
				$post['post_custom']['frm_display_id'] = $display->ID;
			}
		}

		$post_id = self::insert_post( $entry, $post, array(), $form, $action );
		return $post_id;
	}

	/**
	 * @param array      $new_post
	 * @param array|null $post
	 * @param mixed      $form
	 */
	public static function insert_post( $entry, $new_post, $post, $form = false, $action = false ) {
		if ( ! $action ) {
			$action = FrmFormAction::get_action_for_form( $form->id, 'wppost', 1 );

			if ( ! $action ) {
				return;
			}
		}

		$post_fields = self::get_post_fields( $new_post, 'insert_post' );

		$editing = true;
		if ( empty( $post ) ) {
			$editing = false;
			$post    = array();
		}

		foreach ( $post_fields as $post_field ) {
			if ( isset( $new_post[ $post_field ] ) ) {
				$post[ $post_field ] = $new_post[ $post_field ];
			}
			unset( $post_field );
		}
		unset( $post_fields );

		$dyn_content = '';
		self::post_value_overrides( $post, $new_post, $editing, $form, $entry, $dyn_content );

		$post = apply_filters(
			'frm_before_create_post',
			$post,
			array(
				'form'  => $form,
				'entry' => $entry,
			) 
		);

		$post_ID = wp_insert_post( $post );

		if ( is_wp_error( $post_ID ) || empty( $post_ID ) ) {
			return;
		}

		self::save_taxonomies( $new_post, $post_ID );
		self::link_post_attachments( $post_ID, $editing );
		self::save_post_meta( $new_post, $post_ID, $post );
		self::save_post_id_to_entry( $post_ID, $entry, $editing );
		// Make sure save_post_id_to_entry stays above save_dynamic_content because
		// save_dynamic_content needs updated entry object from save_post_id_to_entry
		self::save_dynamic_content( $post, $post_ID, $dyn_content, $form, $entry );
		self::delete_duplicated_meta( $action, $entry );

		return $post_ID;
	}

	public static function destroy_post( $entry_id, $entry = false ) {
		global $wpdb;

		if ( $entry ) {
			$post_id = $entry->post_id;
		} else {
			$post_id = FrmDb::get_var( $wpdb->prefix . 'frm_items', array( 'id' => $entry_id ), 'post_id' );
		}

		// delete child entries
		$child_entries = FrmDb::get_col( $wpdb->prefix . 'frm_items', array( 'parent_item_id' => $entry_id ) );
		foreach ( $child_entries as $child_entry ) {
			FrmEntry::destroy( $child_entry );
		}

		// Trigger delete actions for parent entry
		FrmProFormActionsController::trigger_delete_actions( $entry_id, $entry );

		if ( $post_id ) {
			wp_delete_post( $post_id );
		}
	}

	/**
	 * Insert all post variables into the post array.
	 *
	 * @param WP_Post  $action
	 * @param stdClass $entry
	 * @param stdClass $form
	 * @return array
	 */
	public static function setup_post( $action, $entry, $form ) {
		$temp_fields = FrmField::get_all_for_form( $form->id, '', 'include' );
		$fields      = array();
		foreach ( $temp_fields as $f ) {
			$fields[ $f->id ] = $f;
			unset( $f );
		}
		unset( $temp_fields );

		$new_post = array(
			'post_custom'   => array(),
			'taxonomies'    => array(),
			'post_category' => array(),
		);

		self::populate_post_author( $new_post );
		self::populate_post_fields( $action, $entry, $new_post );
		self::populate_custom_fields( $action, $entry, $fields, $new_post );
		self::populate_taxonomies( $action, $entry, $fields, $new_post );

		if ( is_numeric( $action->post_content['post_content'] ) ) {
			// When post content is created from a field value, do not allow shortcodes from user input.
			FrmFieldsHelper::sanitize_embedded_shortcodes( compact( 'entry' ), $new_post['post_content'] );
		}

		if ( $action->post_content['comment_status'] ) {
			$new_post['comment_status'] = $action->post_content['comment_status'];
		}

		$new_post = apply_filters( 'frm_new_post', $new_post, compact( 'form', 'action', 'entry' ) );

		return $new_post;
	}

	/**
	 * @param array $post
	 * @return void
	 */
	private static function populate_post_author( &$post ) {
		$new_author = FrmAppHelper::get_post_param( 'frm_user_id', 0, 'absint' );
		if ( ! isset( $post['post_author'] ) && $new_author ) {
			$post['post_author'] = $new_author;
		}
	}

	/**
	 * @param WP_Post $action
	 * @param stdClass $entry
	 */
	private static function populate_post_fields( $action, $entry, &$new_post ) {
		$post_fields = self::get_post_fields( $new_post, 'post_fields' );

		$combined_metas = self::get_combined_metas( $entry );

		foreach ( $post_fields as $setting_name ) {
			if ( ! is_numeric( $action->post_content[ $setting_name ] ) ) {
				continue;
			}

			if ( 'post_parent' === $setting_name ) {
				/**
				 * Filter the post parent of post created from Create Post action.
				 *
				 * @since 4.10.01
				 *
				 * @param int|string $post_parent Post parent ID.
				 * @param array      $args        Argument contains the action and entry objects.
				 */
				$new_post[ $setting_name ] = apply_filters( 'frm_post_parent', $action->post_content[ $setting_name ], compact( 'action', 'entry' ) );
			} else {
				$new_post[ $setting_name ] = isset( $combined_metas[ $action->post_content[ $setting_name ] ] ) ? $combined_metas[ $action->post_content[ $setting_name ] ] : '';
			}

			if ( 'post_date' === $setting_name ) {
				$new_post[ $setting_name ] = FrmProAppHelper::maybe_convert_to_db_date( $new_post[ $setting_name ], 'Y-m-d H:i:s' );
			}

			unset( $setting_name );
		}
	}

	/**
	 * Returns combined entry metas from an entry and its child entries.
	 *
	 * @since 6.8
	 *
	 * @param object $entry
	 * @return array
	 */
	private static function get_combined_metas( $entry ) {
		global $wpdb;

		$metas = FrmDb::get_results(
			$wpdb->prefix . 'frm_item_metas m INNER JOIN ' . $wpdb->prefix . 'frm_items i ON i.id = m.item_id',
			array(
				'i.parent_item_id' => $entry->id,
			),
			'm.field_id, m.meta_value'
		);

		$child_entries = array_column( $metas, 'meta_value', 'field_id' );

		return $entry->metas + $child_entries;
	}

	/**
	 * Make sure all post fields get included in the new post.
	 * Add the fields dynamically if they are included in the post.
	 *
	 * @since 2.0.2
	 *
	 * @param string $function
	 *
	 * @psalm-param 'insert_post'|'post_fields' $function
	 */
	private static function get_post_fields( $new_post, $function ) {
		$post_fields = array(
			'post_content',
			'post_excerpt',
			'post_title',
			'post_name',
			'post_date',
			'post_status',
			'post_password',
			'post_parent',
			'menu_order',
		);

		if ( $function === 'insert_post' ) {
			$post_fields    = array_merge( $post_fields, array( 'post_author', 'post_type', 'post_category' ) );
			$extra_fields   = array_keys( $new_post );
			$exclude_fields = array( 'post_custom', 'taxonomies', 'post_category' );
			$extra_fields   = array_diff( $extra_fields, $exclude_fields, $post_fields );
			$post_fields    = array_merge( $post_fields, $extra_fields );
		}

		return $post_fields;
	}

	/**
	 * Add custom fields to the post array
	 *
	 * @param WP_Post $action
	 * @param stdClass $entry
	 * @param array $fields
	 */
	private static function populate_custom_fields( $action, $entry, $fields, &$new_post ) {
		$combined_metas = self::get_combined_metas( $entry );
		// populate custom fields
		foreach ( $action->post_content['post_custom_fields'] as $custom_field ) {
			if ( empty( $custom_field['field_id'] ) || empty( $custom_field['meta_name'] ) || ! isset( $fields[ $custom_field['field_id'] ] ) ) {
				continue;
			}

			$value = isset( $combined_metas[ $custom_field['field_id'] ] ) ? $combined_metas[ $custom_field['field_id'] ] : '';

			if ( $fields[ $custom_field['field_id'] ]->type === 'date' ) {
				$value = FrmProAppHelper::maybe_convert_to_db_date( $value );
			}

			if ( isset( $new_post['post_custom'][ $custom_field['meta_name'] ] ) ) {
				$new_post['post_custom'][ $custom_field['meta_name'] ]   = (array) $new_post['post_custom'][ $custom_field['meta_name'] ];
				$new_post['post_custom'][ $custom_field['meta_name'] ][] = $value;
			} else {
				$new_post['post_custom'][ $custom_field['meta_name'] ] = $value;
			}

			unset( $value );
		}
	}

	/**
	 * @param WP_Post $action
	 * @param stdClass $entry
	 * @param array $fields
	 */
	private static function populate_taxonomies( $action, $entry, $fields, &$new_post ) {
		foreach ( $action->post_content['post_category'] as $taxonomy ) {
			if ( empty( $taxonomy['field_id'] ) || empty( $taxonomy['meta_name'] ) ) {
				continue;
			}

			$tax_type = ! empty( $taxonomy['meta_name'] ) ? $taxonomy['meta_name'] : 'frm_tag';
			$value    = isset( $entry->metas[ $taxonomy['field_id'] ] ) ? $entry->metas[ $taxonomy['field_id'] ] : '';

			if ( isset( $fields[ $taxonomy['field_id'] ] ) && $fields[ $taxonomy['field_id'] ]->type === 'tag' ) {
				$value = trim( $value );
				$value = array_map( 'trim', explode( ',', $value ) );

				if ( is_taxonomy_hierarchical( $tax_type ) ) {
					//create the term or check to see if it exists
					$terms = array();
					foreach ( $value as $v ) {
						$term_id = term_exists( $v, $tax_type );

						// create new terms if they don't exist
						if ( ! $term_id ) {
							$term_id = wp_insert_term( $v, $tax_type );
						}

						if ( $term_id && is_array( $term_id ) ) {
							$term_id = $term_id['term_id'];
						}

						if ( is_numeric( $term_id ) ) {
							$terms[ $term_id ] = $v;
						}

						unset( $term_id, $v );
					}

					$value = $terms;
					unset( $terms );
				}

				if ( isset( $new_post['taxonomies'][ $tax_type ] ) ) {
					$new_post['taxonomies'][ $tax_type ] += $value;
				} else {
					$new_post['taxonomies'][ $tax_type ] = $value;
				}
			} else {
				$value = (array) $value;

				// change text to numeric ids while importing
				if ( defined( 'WP_IMPORTING' ) ) {
					foreach ( $value as $k => $val ) {
						if ( empty( $val ) ) {
							continue;
						}

						$term = term_exists( $val, $fields[ $taxonomy['field_id'] ]->field_options['taxonomy'] );
						if ( $term ) {
							$value[ $k ] = is_array( $term ) ? $term['term_id'] : $term;
						}

						unset( $k, $val, $term );
					}
				}

				if ( 'category' === $tax_type ) {
					if ( ! empty( $value ) ) {
						$new_post['post_category'] = array_merge( $new_post['post_category'], $value );
					}
				} else {
					$new_value = array();
					foreach ( $value as $val ) {
						if ( $val == 0 ) {
							continue;
						}

						$new_value[ $val ] = self::get_taxonomy_term_name_from_id( $val, $fields[ $taxonomy['field_id'] ]->field_options['taxonomy'] );
					}

					self::fill_taxonomies( $new_post['taxonomies'], $tax_type, $new_value );
				}
			}
		}
	}

	/**
	 * Get the taxonomy name from the ID
	 * If no term is retrieved, the ID will be returned
	 *
	 * @since 2.02.06
	 * @param int|string $term_id
	 * @param string $taxonomy
	 * @return string
	 */
	public static function get_taxonomy_term_name_from_id( $term_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );

		if ( $term && ! isset( $term->errors ) ) {
			$value = $term->name;
		} else {
			$value = $term_id;
		}

		return $value;
	}

	/**
	 * @param array    $taxonomies
	 * @param string   $tax_type
	 * @param string[] $new_value
	 * @return void
	 */
	private static function fill_taxonomies( &$taxonomies, $tax_type, $new_value ) {
		if ( isset( $taxonomies[ $tax_type ] ) ) {
			foreach ( (array) $new_value as $new_key => $new_name ) {
				$taxonomies[ $tax_type ][ $new_key ] = $new_name;
			}
		} else {
			$taxonomies[ $tax_type ] = $new_value;
		}
	}

	/**
	 * Override the post content and date format
	 *
	 * @param bool   $editing
	 * @param string $dyn_content
	 */
	private static function post_value_overrides( &$post, $new_post, $editing, $form, $entry, &$dyn_content ) {
		//if empty post content and auto display, then save compiled post content
		$default_display = isset( $new_post['post_custom']['frm_display_id'] ) ? $new_post['post_custom']['frm_display_id'] : 0;
		$display_id      = $editing ? get_post_meta( $post['ID'], 'frm_display_id', true ) : $default_display;

		if ( ! isset( $post['post_content'] ) && $display_id ) {
			self::update_post_content_if_view_exists( $post, $display_id, $form, $entry, $dyn_content );
		}

		if ( ! empty( $post['post_date'] ) ) {
			// set post date gmt if post date is set
			$post['post_date_gmt'] = get_gmt_from_date( $post['post_date'] );
		}
	}

	private static function update_post_content_if_view_exists( &$post, $display_id, $form, $entry, &$dyn_content ) {
		if ( is_callable( 'FrmViewsDisplaysHelper::update_post_content_if_view_exists' ) ) {
			FrmViewsDisplaysHelper::update_post_content_if_view_exists( $post, $display_id, $form, $entry, $dyn_content );
		}
	}

	/**
	 * Add taxonomies after save in case user doesn't have permissions
	 *
	 * @param array $new_post
	 * @param int   $post_ID
	 * @return void
	 */
	private static function save_taxonomies( $new_post, $post_ID ) {
		if ( ! isset( $new_post['taxonomies'] ) || ! is_array( $new_post['taxonomies'] ) ) {
			return;
		}

		foreach ( $new_post['taxonomies'] as $taxonomy => $tags ) {
			// If setting hierarchical taxonomy or post_format, use IDs
			if ( is_taxonomy_hierarchical( $taxonomy ) || $taxonomy === 'post_format' ) {
				$tags = array_keys( $tags );
			}

			wp_set_post_terms( $post_ID, $tags, $taxonomy );

			unset( $taxonomy, $tags );
		}
	}

	/**
	 * @param int  $post_ID
	 * @param bool $editing
	 * @return void
	 */
	private static function link_post_attachments( $post_ID, $editing ) {
		global $frm_vars, $wpdb;

		$exclude_attached = array();
		if ( ! empty( $frm_vars['media_id'] ) ) {
			foreach ( (array) $frm_vars['media_id'] as $media_id ) {
				$exclude_attached = array_merge( $exclude_attached, (array) $media_id );

				if ( is_array( $media_id ) ) {
					$attach_string = array_filter( $media_id );
					if ( ! empty( $attach_string ) ) {
						$where = array(
							'post_type' => 'attachment',
							'ID'        => $attach_string,
						);
						FrmDb::get_where_clause_and_values( $where );
						array_unshift( $where['values'], $post_ID );

						// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->posts . ' SET post_parent = %d' . $where['where'], $where['values'] ) );

						foreach ( $media_id as $m ) {
							delete_post_meta( $m, '_frm_file' );
							clean_attachment_cache( $m );
							unset( $m );
						}
					}
				} else {
					$wpdb->update(
						$wpdb->posts,
						array( 'post_parent' => $post_ID ),
						array(
							'ID'        => $media_id,
							'post_type' => 'attachment',
						) 
					);
					delete_post_meta( $media_id, '_frm_file' );
					clean_attachment_cache( $media_id );
				}
			}
		}

		self::unlink_post_attachments( $post_ID, $editing, $exclude_attached );
	}

	/**
	 * @param array $exclude_attached
	 * @return void
	 */
	private static function unlink_post_attachments( $post_ID, $editing, $exclude_attached ) {
		if ( ! $editing ) {
			return;
		}

		$args = array(
			'post_type'   => 'attachment',
			'numberposts' => -1,
			'post_status' => null,
			'post_parent' => $post_ID,
			'exclude'     => $exclude_attached,
		);

		global $wpdb;

		$attachments = get_posts( $args );
		foreach ( $attachments as $attachment ) {
			$wpdb->update( $wpdb->posts, array( 'post_parent' => null ), array( 'ID' => $attachment->ID ) );
			update_post_meta( $attachment->ID, '_frm_file', 1 );
		}
	}

	/**
	 * Saves post meta.
	 *
	 * @since 6.10 Added the third param.
	 *
	 * @param array $new_post         Post data passed to FrmProPost::insert_post().
	 * @param int   $post_ID          Created post ID.
	 * @param array $insert_post_data Processed post data passed to wp_insert_post().
	 * @return void
	 */
	private static function save_post_meta( $new_post, $post_ID, $insert_post_data = array() ) {
		foreach ( $new_post['post_custom'] as $post_data => $value ) {
			if ( isset( $insert_post_data['meta_input'][ $post_data ] ) ) {
				// This meta is created in the wp_insert_post() function, so we don't need to create it here.
				continue;
			}

			if ( $value == '' ) {
				delete_post_meta( $post_ID, $post_data );
			} else {
				$is_acf_field = self::maybe_save_acf_field( $post_data, $value, $post_ID );
				if ( ! $is_acf_field ) {
					update_post_meta( $post_ID, $post_data, $value );
				}
			}

			unset( $post_data, $value );
		}

		global $user_ID;
		update_post_meta( $post_ID, '_edit_last', $user_ID );
	}

	/**
	 * @return bool true if an acf field was saved.
	 */
	private static function maybe_save_acf_field( $key, $value, $post_ID ) {
		$is_acf_field = false;

		if ( ! self::is_acf_field( $post_ID, $key ) ) {
			return $is_acf_field;
		}

		$acf_field_key = FrmDb::get_var(
			'posts',
			array(
				'post_excerpt' => substr( $key, 1 ),
				'post_type'    => 'acf-field',
			),
			'post_name'
		);
		if ( $acf_field_key ) {
			$is_acf_field = true;
			update_field( $acf_field_key, $value, $post_ID );
		}

		return $is_acf_field;
	}

	/**
	 * @param int    $post_id
	 * @param string $key
	 * @return bool
	 */
	public static function is_acf_field( $post_id, $key ) {
		if ( ! function_exists( 'get_field_objects' ) ) {
			// never try to save an acf field if ACF is not active.
			return false;
		}

		if ( ! $key || '_' !== $key[0] ) {
			return false;
		}

		$field_objects = get_field_objects( $post_id );
		if ( ! $field_objects ) {
			// no ACF fields assigned to post type.
			return false;
		}

		$acf_meta_key = substr( $key, 1 );
		if ( ! isset( $field_objects[ $acf_meta_key ] ) ) {
			// meta is not assigned to this post.
			return false;
		}

		return true;
	}

	/**
	 * Save post_id with the entry
	 * If entry was updated, get updated entry object
	 *
	 * @param int      $post_ID
	 * @param stdClass $entry
	 * @param bool     $editing
	 * @return void
	 */
	private static function save_post_id_to_entry( $post_ID, &$entry, $editing ) {
		if ( $editing ) {
			return;
		}

		global $wpdb;
		$updated = $wpdb->update( $wpdb->prefix . 'frm_items', array( 'post_id' => $post_ID ), array( 'id' => $entry->id ) );
		if ( $updated ) {
			wp_cache_delete( $entry->id, 'frm_entry' );
			wp_cache_delete( $entry->id . '_nometa', 'frm_entry' );
			// Save new post ID for later use
			$entry->post_id = $post_ID;
		}
	}

	/**
	 * Update dynamic content after all post fields are updated
	 *
	 * @param int $post_ID
	 *
	 * @return void
	 */
	private static function save_dynamic_content( $post, $post_ID, $dyn_content, $form, $entry ) {
		if ( $dyn_content == '' ) {
			return;
		}

		$new_content = apply_filters( 'frm_content', $dyn_content, $form, $entry );
		if ( $new_content != $post['post_content'] ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new_content ), array( 'ID' => $post_ID ) );
		}
	}

	/**
	 * Delete entry meta so it won't be duplicated
	 *
	 * @param object $action
	 * @param object $entry
	 * @return void
	 */
	private static function delete_duplicated_meta( $action, $entry ) {
		global $wpdb;

		$filtered_settings = self::get_post_field_settings( $action->post_content );

		$field_ids = array();
		self::get_post_field_ids_from_settings( $filtered_settings, $field_ids );

		if ( ! empty( $field_ids ) ) {
			$where = array(
				'item_id'  => $entry->id,
				'field_id' => $field_ids,
			);
			FrmDb::get_where_clause_and_values( $where );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'frm_item_metas' . $where['where'], $where['values'] ) );
		}
	}

	/**
	 * Get the post field settings from a post action
	 *
	 * @since 2.2.11
	 *
	 * @param array $settings
	 * @return array $filtered
	 */
	private static function get_post_field_settings( $settings ) {
		$filtered = $settings;
		foreach ( $settings as $name => $value ) {
			if ( strpos( $name, 'post' ) !== 0 ) {
				unset( $filtered[ $name ] );
			}
		}

		return $filtered;
	}

	/**
	 * Get the field IDs from the post field settings
	 *
	 * @since 2.2.11
	 *
	 * @param array $settings
	 * @param array $field_ids
	 * @return void
	 */
	private static function get_post_field_ids_from_settings( $settings, &$field_ids ) {
		foreach ( $settings as $name => $value ) {

			if ( is_numeric( $value ) ) {
				$field_ids[] = $value;
			} elseif ( is_array( $value ) ) {
				if ( isset( $value['field_id'] ) && is_numeric( $value['field_id'] ) ) {
					$field_ids[] = $value['field_id'];
				} else {
					self::get_post_field_ids_from_settings( $value, $field_ids );
				}
			}
			unset( $name, $value );
		}
	}

	/**
	 * Get a category dropdown (for form builder, logic, or front-end)
	 *
	 * @since 2.02.07
	 * @param array $field Not multi-dimensional.
	 * @param array $args  Must include 'name', 'id', and 'location'.
	 * @return string
	 */
	public static function get_category_dropdown( $field, $args ) {
		if ( ! $field || ! isset( $args['location'] ) ) {
			return '';
		}

		$category_args = self::get_category_args( $field, $args );

		if ( empty( $category_args ) ) {
			return '';
		}

		$dropdown = wp_dropdown_categories( $category_args );

		if ( 'front' === $args['location'] ) {
			self::format_category_dropdown_for_front_end( $field, $args, $dropdown );
		} elseif ( 'form_builder' === $args['location'] ) {
			self::format_category_dropdown_for_form_builder( $field, $args, $dropdown );
		} elseif ( 'form_actions' === $args['location'] || 'field_logic' === $args['location'] ) {
			self::format_category_dropdown_for_logic( $args, $dropdown );
		}

		return $dropdown;
	}

	/**
	 * Format a category dropdown for a front-end form
	 *
	 * @since 2.02.07
	 * @param array $field
	 * @param array $args - must include placeholder_class, name, and id
	 * @param string $dropdown
	 */
	private static function format_category_dropdown_for_front_end( $field, $args, &$dropdown ) {
		// Add input HTML
		$add_html = FrmFieldsController::input_html( $field, false ) . FrmProFieldsController::input_html( $field, false );
		$dropdown = str_replace( " class='placeholder_class'", $add_html, $dropdown );

		// Set up hidden fields for read-only dropdown
		if ( FrmField::is_read_only( $field ) && ! FrmAppHelper::is_admin() ) {
			$dropdown = str_replace( "name='" . $args['name'] . "'", '', $dropdown );
			$dropdown = str_replace( "id='" . $args['id'] . "'", '', $dropdown );
		}

		self::select_saved_values_in_category_dropdown( $field, $dropdown );
	}

	/**
	 * Format a category dropdown form the form builder page
	 *
	 * @since 2.02.07
	 * @param array $field
	 * @param array $args - must include placeholder_class and id
	 * @param string $dropdown
	 */
	private static function format_category_dropdown_for_form_builder( $field, $args, &$dropdown ) {
		// Remove placeholder class
		$dropdown = str_replace( " class='placeholder_class'", '', $dropdown );

		// Remove id
		$dropdown = str_replace( "id='" . $args['id'] . "'", '', $dropdown );

		self::select_saved_values_in_category_dropdown( $field, $dropdown );
	}

	/**
	 * Format a category dropdown for logic (in field or action)
	 *
	 * @since 2.02.07
	 * @param array $args - must include id and placeholder_class
	 * @param string $dropdown
	 */
	private static function format_category_dropdown_for_logic( $args, &$dropdown ) {
		// Remove placeholder id
		$dropdown = str_replace( "id='" . $args['id'] . "'", '', $dropdown );

		// Remove placeholder class
		$dropdown = str_replace( " class='placeholder_class'", '', $dropdown );

		// Set first value in category dropdown to empty string instead of 0
		$dropdown = str_replace( "value='0'", 'value=""', $dropdown );
	}

	/**
	 * Make sure all saved values are selected, not just the first
	 * This is necessary because only one value can be passed into the wp_dropdown_categories() function
	 *
	 * @since 2.02.07
	 * @param array $field
	 * @param string $dropdown
	 */
	private static function select_saved_values_in_category_dropdown( $field, &$dropdown ) {
		if ( is_array( $field['value'] ) ) {
			$skip = true;
			foreach ( $field['value'] as $v ) {
				if ( $skip ) {
					$skip = false;
					continue;
				}
				$dropdown = str_replace( ' value="' . esc_attr( $v ) . '"', ' value="' . esc_attr( $v ) . '" selected="selected"', $dropdown );
				unset( $v );
			}
		}
	}

	/**
	 * Get the arguments that will be passed into the wp_dropdown_categories function
	 *
	 * @since 2.02.07
	 * @param array $field
	 * @param array $args - must include 'name', 'id', 'location', and 'placeholder_class'
	 * @return array
	 */
	private static function get_category_args( $field, $args ) {
		$show_option_all = isset( $args['show_option_all'] ) ? $args['show_option_all'] : ' ';

		$exclude = is_array( $field['exclude_cat'] ) ? implode( ',', $field['exclude_cat'] ) : $field['exclude_cat'];
		$exclude = apply_filters( 'frm_exclude_cats', $exclude, $field );

		if ( is_array( $field['value'] ) ) {
			if ( ! empty( $exclude ) ) {
				$field['value'] = array_diff( $field['value'], explode( ',', $exclude ) );
			}
			$selected = reset( $field['value'] );
		} else {
			$selected = $field['value'];
		}

		$tax_atts = array(
			'show_option_all' => $show_option_all,
			'hierarchical'    => 1,
			'name'            => $args['name'],
			'id'              => $args['id'],
			'exclude'         => $exclude,
			'class'           => 'placeholder_class',
			'selected'        => $selected,
			'hide_empty'      => false,
			'echo'            => 0,
			'orderby'         => 'name',
		);

		$tax_atts = apply_filters( 'frm_dropdown_cat', $tax_atts, $field );

		$post_type            = FrmProFormsHelper::post_type( $field['form_id'] );
		$tax_atts['taxonomy'] = FrmProAppHelper::get_custom_taxonomy( $post_type, $field );
		if ( ! $tax_atts['taxonomy'] ) {
			return array();
		}

		// If field type is dropdown (not Dynamic), exclude children when parent is excluded
		if ( $field['type'] !== 'data' && is_taxonomy_hierarchical( $tax_atts['taxonomy'] ) ) {
			$tax_atts['exclude_tree'] = $exclude;
		}

		return $tax_atts;
	}

	/**
	 * Duplicate post data when an entry is duplicated with a Post Action.
	 *
	 * @param int   $entry_id the id of the new duplicated entry.
	 * @param int   $form_id the form associated with the duplicated entry.
	 * @param array $args includes key "old_id" with the original entry id.
	 */
	public static function duplicate_post_data( $entry_id, $form_id, $args ) {
		$action = FrmFormAction::get_action_for_form( $form_id, 'wppost', 1 );

		if ( ! $action || empty( $args['old_id'] ) ) {
			return;
		}

		$original_entry_id      = absint( $args['old_id'] );
		$original_entry_post_id = FrmDb::get_var( 'frm_items', array( 'id' => $original_entry_id ), 'post_id' );

		if ( ! $original_entry_post_id ) {
			return;
		}

		self::create_duplicate_post( $entry_id, $original_entry_post_id );
	}

	/**
	 * @param int $entry_id
	 * @param int $original_post_id
	 */
	private static function create_duplicate_post( $entry_id, $original_post_id ) {
		$post = get_post( $original_post_id, ARRAY_A );
		unset( $post['ID'] );
		$duplicate_post_id = wp_insert_post( $post );

		self::update_associated_post_id_for_entry( $entry_id, $duplicate_post_id );
		self::duplicate_post_meta( $original_post_id, $duplicate_post_id );
	}

	/**
	 * @param int $original_post_id
	 * @param int $duplicate_post_id
	 */
	private static function duplicate_post_meta( $original_post_id, $duplicate_post_id ) {
		$post_meta = FrmDb::get_results( 'postmeta', array( 'post_id' => $original_post_id ), 'meta_key, meta_value' );
		foreach ( $post_meta as $row ) {
			add_post_meta( $duplicate_post_id, $row->meta_key, $row->meta_value );
		}
	}

	/**
	 * @param int $entry_id
	 * @param int $post_id
	 */
	private static function update_associated_post_id_for_entry( $entry_id, $post_id ) {
		global $wpdb;
		$data  = array(
			'post_id' => $post_id,
		);
		$where = array(
			'id' => $entry_id,
		);
		$wpdb->update( $wpdb->prefix . 'frm_items', $data, $where );
	}
}
