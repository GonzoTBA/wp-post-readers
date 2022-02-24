<?php

/*
Plugin Name: Post Readers
Plugin URI: 
Description: Shows a list of users who read the post
Version: 0.1
Author: Javier Malonda
Author URI: 
License: GPL2
*/

// Install, on plugin activation, new table to relate posts and users.
global $pu_db_version;
$pu_db_version = '1.0';

function pu_install() {
	global $wpdb;
	global $pu_db_version;

	$table_name = $wpdb->prefix . 'posts_users';
	
	$charset_collate = $wpdb->get_charset_collate();

    // You must have two spaces between the words PRIMARY KEY and the definition of your primary key.
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id smallint NOT NULL,
        user_id smallint NOT NULL,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    // Execute DB delta changes. Actually create the table.
	dbDelta( $sql );

	add_option( 'pu_db_version', $pu_db_version );
}

register_activation_hook( __FILE__, 'pu_install' );


// Saves user_id in the post_meta table
function post_readers_save() {
    // Needed in order to access the db
    global $wpdb;

    // Get post_id - Hook init does not work. It has to be template_redirect.
    $post = get_post();
    $post_id = $post->ID;

    if ( is_singular() && is_user_logged_in() ) {
        // Get user ID
        $user_id = get_current_user_id();

        // Get user display name
        $user = get_user_by( 'id', $user_id );

        // Save user_id with post_id in posts_users table
        $table_name = $wpdb->prefix . 'posts_users';
        // Check if entry already exists - Returns ID if it does
        $checkIfExists = $wpdb->get_var( "SELECT ID FROM $table_name WHERE post_id = '$post_id' AND user_id = '$user_id' " );
        // If entry doesn't exist, insert.
        if ($checkIfExists == NULL) {
            $wpdb->replace($table_name, array(
                'post_id'   => $post_id,
                'user_id'   => $user_id,
                'time'      => current_time('mysql'),
            ));
        }
    }
}

add_action( 'template_redirect', 'post_readers_save');


// Shows the list of users who have read the post
function post_readers_show($content) {
    global $wpdb;

    if ( is_singular() && is_super_admin( $user_id = false ) ) {
        // Get all users who have read this post
        $post = get_post();
        $post_id = $post->ID;

        $table_name = $wpdb->prefix . 'posts_users';
        $rows = $wpdb->get_results( "SELECT user_id FROM $table_name WHERE `post_id` = $post_id " );

        // Show usernames
        $user_ids = array();
        foreach ($rows as $row) {
            $user_ids[] = $row->user_id;
        }

        $usernames = array();
        foreach ($user_ids as $user_id) {
            $usernames[] = get_user_by('id', $user_id)->display_name;
        }

        $usernames_text = implode(", ", $usernames);

		$post_readers = "<div class='post-users'>Usuarios que pusieron aquí su bandera: " . $usernames_text . "</div>";
		$full_content = $content . $post_readers;
	}
	else {
		$full_content = $content;
	}
        
	return $full_content;
}

add_filter("the_content", "post_readers_show");