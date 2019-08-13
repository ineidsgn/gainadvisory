<?php
/*
Template Name: Verification Letter
*/

//* Remove site header elements
remove_action( 'genesis_header', 'genesis_header_markup_open', 5 );
remove_action( 'genesis_header', 'genesis_do_header' );
remove_action( 'genesis_header', 'genesis_header_markup_close', 15 );

//* Remove navigation
remove_theme_support( 'genesis-menus' );

//* Remove breadcrumbs
remove_action( 'genesis_before_loop', 'genesis_do_breadcrumbs' );

remove_action( 'genesis_before_content_sidebar_wrap', 'corporate_hero_section' );

add_action('genesis_entry_content','gag_v_letter');

//* Remove site footer widgets
remove_theme_support( 'genesis-footer-widgets' );

remove_action( 'genesis_footer', 'corporate_footer_credits', 14 );

//* Remove site footer elements
remove_action( 'genesis_footer', 'genesis_footer_markup_open', 5 );
remove_action( 'genesis_footer', 'genesis_do_footer' );
remove_action( 'genesis_footer', 'genesis_footer_markup_close', 15 );

genesis();