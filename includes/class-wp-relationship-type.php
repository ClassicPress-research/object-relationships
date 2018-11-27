<?php if(!defined('ABSPATH')) { die(); }

/*
TODO: This Class will offer the ability to register a relationship type,
along with the different object types and subtypes associated with it,
similar to registering a taxonomy, configuring here will optionally add
metaboxes to the related objects to control their relationships

NOTE: a proposed format for registration appears below

$args = array(
	'label' => 'User Favorites'
	'labels' => array(
		// to be decided
	),
	'public' => true,
	'show_in_rest' => true,
	'show_in_quick_edit' => true,
	'meta_box_cb' => null,
	'description' => '',
	'bidirectional' => true,
	'left_max' => -1,
	'right_max' => -1,
);
register_relationship(
	'user_favorites', // string - name of relationship
	'user:editor',    // string/array - left object type and subtype
	'post:post',      // string/array - right object type and subtype
	$args
);

NOTE: How can we incorporate meta fields into registration
and standard UI, as seen in posts-2-posts?
Fields API? Something like WooCommerce form input definitions?
*/
