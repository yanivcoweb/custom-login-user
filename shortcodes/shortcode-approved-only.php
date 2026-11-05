<?php

defined( 'ABSPATH' ) || exit;

/**
 * Shows the enclosed content to approved users only, and a message to everyone else.
 *
 *     [clu_approved_only]Price list[/clu_approved_only]
 *     [clu_approved_only message="Members see the prices here."]...[/clu_approved_only]
 *     [clu_approved_only message=""]Nothing shows for others.[/clu_approved_only]
 *
 * @param array|string $attributes Shortcode attributes.
 * @param string|null  $content    The enclosed content.
 * @return string
 */
add_shortcode( 'clu_approved_only', 'clu_render_approved_only' );
function clu_render_approved_only( $attributes, $content = null ) {
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }

    $post_id = (int) get_the_ID();

    if ( clu_user_can_view_restricted( $post_id ) ) {
        return do_shortcode( (string) $content );
    }

    $attributes = shortcode_atts( [ 'message' => null ], $attributes );

    return $attributes['message'] === null ? clu_restricted_message( $post_id ) : wp_kses_post( $attributes['message'] );
}
