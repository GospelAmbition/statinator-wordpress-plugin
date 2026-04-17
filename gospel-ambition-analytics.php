<?php
/**
 * Plugin Name: Gospel Ambition Analytics
 * Plugin URI: https://github.com/GospelAmbition/statinator-wordpress-plugin
 * Description: Connects to the Statinator analytics server for event tracking.
 * Version: 1.0.0
 * Author: Gospel Ambition
 * Author URI: https://gospelambition.org
 * License: GPL-2.0+
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * Configuration via wp-config.php:
 *
 *   define('GO_ANALYTICS_PROJECT_ID', 'prayer_global');
 *   define('GO_ANALYTICS_API_KEY', 'uuid-from-statinator-admin');
 *   define('GO_ANALYTICS_ENDPOINT', 'https://statinator.prayer.global');
 *   define('GO_ANALYTICS_STORAGE_MODE', 'local'); // optional, defaults to 'session'
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue the Statinator tracking script on every page.
 */
add_action( 'wp_enqueue_scripts', 'go_analytics_enqueue_script' );

function go_analytics_enqueue_script() {
    $project_id = defined( 'GO_ANALYTICS_PROJECT_ID' ) ? GO_ANALYTICS_PROJECT_ID : '';
    $endpoint = defined( 'GO_ANALYTICS_ENDPOINT' ) ? GO_ANALYTICS_ENDPOINT : '';

    if ( empty( $project_id ) || empty( $endpoint ) ) {
        return;
    }

    $src = rtrim( $endpoint, '/' ) . '/api/script.js';

    wp_enqueue_script( 'go-analytics', $src, [], null, [ 'strategy' => 'defer' ] );
}

/**
 * Add data-project and data-storage attributes to the tracking script tag.
 */
add_filter( 'script_loader_tag', 'go_analytics_script_attributes', 10, 3 );

function go_analytics_script_attributes( $tag, $handle, $src ) {
    if ( $handle !== 'go-analytics' ) {
        return $tag;
    }

    $project_id = defined( 'GO_ANALYTICS_PROJECT_ID' ) ? GO_ANALYTICS_PROJECT_ID : '';
    $storage = defined( 'GO_ANALYTICS_STORAGE_MODE' ) ? GO_ANALYTICS_STORAGE_MODE : 'session';
    $hash_key = defined( 'GO_ANALYTICS_HASH_KEY' ) ? GO_ANALYTICS_HASH_KEY : '';

    $attrs = ' data-project="' . esc_attr( $project_id ) . '" data-storage="' . esc_attr( $storage ) . '"';
    if ( ! empty( $hash_key ) ) {
        $attrs .= ' data-hash-key="' . esc_attr( $hash_key ) . '"';
    }

    $tag = str_replace( ' src=', $attrs . ' src=', $tag );

    return $tag;
}

/**
 * Send a server-side event to the Statinator analytics API.
 *
 * Non-blocking — uses wp_remote_post with blocking => false.
 *
 * @param string $event_type  Event name (e.g., 'campaign_signup', 'global_lap_completed')
 * @param array  $metadata    Key-value pairs (campaign_id, people_group_slug, etc.)
 * @param mixed  $value       Optional numeric value
 */
function go_analytics_track( string $event_type, array $metadata = [], $value = null ): void {
    $api_key = defined( 'GO_ANALYTICS_API_KEY' ) ? GO_ANALYTICS_API_KEY : '';
    $endpoint = defined( 'GO_ANALYTICS_ENDPOINT' ) ? GO_ANALYTICS_ENDPOINT : '';
    $project_id = defined( 'GO_ANALYTICS_PROJECT_ID' ) ? GO_ANALYTICS_PROJECT_ID : '';

    if ( empty( $api_key ) || empty( $endpoint ) || empty( $project_id ) ) {
        return;
    }

    $language = go_analytics_get_language();
    $anonymous_hash = apply_filters( 'go_analytics_anonymous_hash', null );

    $user_hash = null;
    $user_id = get_current_user_id();
    if ( $user_id ) {
        $user = get_userdata( $user_id );
        if ( $user ) {
            $user_hash = hash( 'sha256', strtolower( $user->user_email ) );
        }
    }

    $payload = [
        'project_id'     => $project_id,
        'event_type'     => $event_type,
        'hostname'       => wp_parse_url( home_url(), PHP_URL_HOST ),
        'language'       => $language,
        'anonymous_hash' => $anonymous_hash ?: null,
        'user_hash'      => $user_hash,
        'metadata'       => ! empty( $metadata ) ? $metadata : null,
        'value'          => $value,
    ];

    $url = rtrim( $endpoint, '/' ) . '/api/events';

    wp_remote_post( $url, [
        'headers'  => [
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_key,
        ],
        'body'     => wp_json_encode( $payload ),
        'timeout'  => 5,
        'blocking' => false,
    ] );

    do_action( 'go_analytics_event', $event_type, $metadata, $value );
}

/**
 * Get the current language from Polylang or WordPress locale.
 */
function go_analytics_get_language(): string {
    if ( function_exists( 'pll_current_language' ) ) {
        return pll_current_language( 'slug' ) ?: 'en';
    }

    $locale = get_locale();
    return substr( $locale, 0, 2 );
}

/**
 * Auto-track user login with email hash for cross-project linking.
 */
add_action( 'wp_login', 'go_analytics_track_login', 10, 2 );

function go_analytics_track_login( $user_login, $user ) {
    $email_hash = hash( 'sha256', strtolower( $user->user_email ) );
    go_analytics_track( 'user_login', [ 'email_hash' => $email_hash ] );
}

/**
 * Auto-track user registration with email hash for cross-project linking.
 */
add_action( 'user_register', 'go_analytics_track_registration' );

function go_analytics_track_registration( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }
    $email_hash = hash( 'sha256', strtolower( $user->user_email ) );
    go_analytics_track( 'user_registered', [ 'email_hash' => $email_hash ] );
}
