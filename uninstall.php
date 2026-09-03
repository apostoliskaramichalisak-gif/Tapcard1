<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Clean up post type
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'tapcard_profile'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'tapcard_%'");
