<?php
/**
 * Plugin Name:       AI Product Finder
 * Description:       AI-powered Product Search
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            JC
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-product-finder
 *
 * @package AI_Product_Finder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-ai-product-finder-pinecone-service.php';
require_once __DIR__ . '/includes/class-ai-product-finder-openai-service.php';
require_once __DIR__ . '/includes/class-ai-product-finder-admin-settings.php';
require_once __DIR__ . '/includes/class-ai-product-finder-catalog-processor.php';

/**
 * Initialize admin settings if in admin area
 */
if ( is_admin() ) {
	new AI_Product_Finder_Admin_Settings();
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ai_product_finder_add_settings_link' );

/**
 * Add settings link to plugins page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function ai_product_finder_add_settings_link( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-product-finder-settings' ) ) . '">' . esc_html__( 'Settings', 'ai-product-finder' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}


/**
 * Initialize AI Product Finder block registration
 */
function ai_product_finder_block_init() {
	$manifest_data = include __DIR__ . '/build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		register_block_type(
			__DIR__ . "/build/{$block_type}",
			array(
				'render_callback' => 'ai_product_finder_render_block',
			)
		);
	}
}
add_action( 'init', 'ai_product_finder_block_init' );

/**
 * Register REST API endpoint for AI search
 */
function ai_product_finder_register_api() {
	register_rest_route(
		'ai-product-finder/v1',
		'/search',
		array(
			'methods'             => 'POST',
			'callback'            => 'ai_product_finder_handle_search_request',
			'permission_callback' => '__return_true',
			'args'                => array(
				'query' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'count' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param >= 1 && $param <= 20;
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'ai_product_finder_register_api' );

/**
 * Register AJAX handlers for catalog sync operations
 */
add_action( 'wp_ajax_ai_product_finder_create_index', 'ai_product_finder_handle_create_index_ajax' );
add_action( 'wp_ajax_ai_product_finder_update_index', 'ai_product_finder_handle_update_index_ajax' );
add_action( 'wp_ajax_ai_product_finder_get_index_info', 'ai_product_finder_handle_get_index_info_ajax' );

/**
 * Handle AI search API request
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return array|WP_Error Response array or WP_Error on failure.
 */
function ai_product_finder_handle_search_request( $request ) {
	$query = $request->get_param( 'query' );
	$count = $request->get_param( 'count' );

	if ( empty( $query ) ) {
		return new WP_Error( 'missing_query', 'Query parameter is required', array( 'status' => 400 ) );
	}

	$pinecone = new AI_Product_Finder_Pinecone_Service();
	$openai   = new AI_Product_Finder_OpenAI_Service();

	// STEP 1: Convert natural language to vector embedding.
	// Transform user query (e.g. "cozy hoodie") into a vector that captures semantic meaning.
	// Example: "Cozy Hoodie" → [-0.03546142578125,-0.0379638671875, ...] (1024-dimensional vector).
	$embedding_result = $pinecone->generate_embedding( $query );
	if ( is_wp_error( $embedding_result ) ) {
		return $embedding_result;
	}

	// STEP 2: Semantic similarity search in vector database.
	// Use the query embedding to find products with similar semantic meaning in the Pinecone index.
	$search_results = $pinecone->search( $embedding_result, $count );
	if ( is_wp_error( $search_results ) ) {
		return $search_results;
	}

	// STEP 3: Generate human-readable explanations for the semantic matches.
	// Use LLM to explain why each product matches the user's search intent.
	$explanations = $openai->explain_matches( $query, $search_results );

	// STEP 4: Enrich search results with live WooCommerce product data (prices, images, URLs, stock status).
	foreach ( $search_results as &$result ) {
		$sku = $result['metadata']['sku'] ?? null;
		if ( $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$product_id = wc_get_product_id_by_sku( $sku );
			$product    = wc_get_product( $product_id );

			if ( $product && $product->is_visible() ) {
				$result['wc_data'] = array(
					'name'              => $product->get_name(),
					'price_html'        => $product->get_price_html(),
					'image_url'         => wp_get_attachment_url( $product->get_image_id() ),
					'product_url'       => get_permalink( $product->get_id() ),
					'short_description' => $product->get_short_description(),
					'in_stock'          => $product->is_in_stock(),
					'on_sale'           => $product->is_on_sale(),
				);
			}
		}
	}

	return array(
		'success'      => true,
		'query'        => $query,
		'results'      => $search_results,
		'explanations' => $explanations,
		'count'        => count( $search_results ),
	);
}

/**
 * Render suggestion chip buttons from saved settings.
 *
 * @return string Chip button HTML.
 */
function ai_product_finder_render_chips() {
	$chips  = AI_Product_Finder_Admin_Settings::get_setting( 'suggestion_chips', AI_Product_Finder_Admin_Settings::get_default_chips() );
	$output = '';
	foreach ( $chips as $chip ) {
		if ( ! empty( $chip ) ) {
			$output .= '<button class="suggestion-chip">' . esc_html( $chip ) . '</button>';
		}
	}
	return $output;
}

/**
 * Server-side render function for AI Product Finder block
 *
 * @param array  $attributes Block attributes (unused).
 * @param string $content    Block content (unused).
 *
 * @return string Rendered block HTML.
 */
function ai_product_finder_render_block( $attributes, $content ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$block_title  = isset( $attributes['blockTitle'] ) ? $attributes['blockTitle'] : 'AI Product Finder';
	$result_count = isset( $attributes['resultCount'] ) ? intval( $attributes['resultCount'] ) : 3;

	return '<div class="wp-block-create-block-ai-product-finder" data-result-count="' . esc_attr( $result_count ) . '">
		<h3 class="ai-product-finder-title">' . wp_kses_post( $block_title ) . '</h3>
		<div class="ai-product-finder-search">
			<div class="search-input-container">
				<input
					type="text"
					class="ai-search-input"
					placeholder="' . esc_attr__( 'Describe what you are looking for...', 'ai-product-finder' ) . '"
				/>
				<button type="button" class="search-button">
					<span class="search-icon">🔍</span>
				</button>
			</div>
			<div class="ai-suggestion-chips">' .
				ai_product_finder_render_chips() . '
			</div>
		</div>
		<div class="search-results"></div>

		<template id="product-card-template">
			<div class="product-card">
				<div class="product-image-container">
					<img class="product-image" src="" alt="">
				</div>
				<div class="product-info">
					<h3 class="product-name"></h3>
					<div class="product-price"></div>
					<p class="product-explanation"></p>
				</div>
			</div>
		</template>
	</div>';
}

/**
 * Handle create index AJAX request
 */
function ai_product_finder_handle_create_index_ajax() {
	// Check nonce for security.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai_product_finder_sync_nonce' ) ) {
		wp_die( 'Invalid nonce', 'Security check', array( 'response' => 403 ) );
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions', 'Permission denied', array( 'response' => 403 ) );
	}

	$processor = new AI_Product_Finder_Catalog_Processor();
	$result    = $processor->create_index_and_upload();

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			)
		);
	}

	wp_send_json_success( $result );
}

/**
 * Handle update index AJAX request
 */
function ai_product_finder_handle_update_index_ajax() {
	// Check nonce for security.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai_product_finder_sync_nonce' ) ) {
		wp_die( 'Invalid nonce', 'Security check', array( 'response' => 403 ) );
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions', 'Permission denied', array( 'response' => 403 ) );
	}

	$processor = new AI_Product_Finder_Catalog_Processor();
	$result    = $processor->update_existing_index();

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			)
		);
	}

	wp_send_json_success( $result );
}

/**
 * Handle get index info AJAX request
 */
function ai_product_finder_handle_get_index_info_ajax() {
	// Check nonce for security.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai_product_finder_sync_nonce' ) ) {
		wp_die( 'Invalid nonce', 'Security check', array( 'response' => 403 ) );
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions', 'Permission denied', array( 'response' => 403 ) );
	}

	$index_name = get_option( 'ai_product_finder_active_index_name', '' );
	$index_url  = get_option( 'ai_product_finder_index_url', '' );

	wp_send_json_success(
		array(
			'index_name' => $index_name,
			'index_url'  => $index_url,
		)
	);
}
