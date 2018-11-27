<?php  if(!defined('ABSPATH')) { die(); }
/*
Plugin Name: Object Relationships
Plugin URI: https://github.com/ClassicPress-research/object-relationships/
Description: adds one-to-many and many-to-many relationships between Objects (e.g posts, users, terms, comments, sites, etc)
Version: 0.0.1
Author: Greg Schoppe
Author URI: https://gschoppe.com
Text Domain:
Domain Path: /languages
*/

require_once('includes/class-wp-relationship.php');
require_once('includes/class-wp-relationship-type.php');
require_once('includes/class-wp-relationship-query.php');
require_once('includes/class-query-extensions.php');
require_once('includes/relationship-helpers.php');

register_activation_hook( __FILE__, array( 'WPRelationship', 'install' ) );
