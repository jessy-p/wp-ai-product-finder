<?php
/**
 * Plugin Name:       AI Style Finder
 * Description:       AI-powered Product Search
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-style-finder
 *
 * @package CreateBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-pinecone-service.php';
require_once __DIR__ . '/includes/class-openai-service.php';

/**
 * Initialize AI Style Finder block registration
 */
function create_block_ai_style_finder_block_init() {
	$manifest_data = include __DIR__ . '/build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		register_block_type(
			__DIR__ . "/build/{$block_type}",
			array(
				'render_callback' => 'render_ai_style_finder_block',
			)
		);
	}
}
add_action( 'init', 'create_block_ai_style_finder_block_init' );

/**
 * Register REST API endpoint for AI search
 */
function register_ai_style_finder_api() {
	register_rest_route(
		'ai-style-finder/v1',
		'/search',
		array(
			'methods'  => 'POST',
			'callback' => 'handle_ai_search_request',
		)
	);
}
add_action( 'rest_api_init', 'register_ai_style_finder_api' );

/**
 * Handle AI search API request
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return array|WP_Error Response array or WP_Error on failure.
 */
function handle_ai_search_request( $request ) {
	$query = $request->get_param( 'query' );

	if ( empty( $query ) ) {
		return new WP_Error( 'missing_query', 'Query parameter is required', array( 'status' => 400 ) );
	}

	$pinecone = new Pinecone_Service();
	$openai   = new OpenAI_Service();

	// STEP 1: Convert natural language to vector embedding.
	// Transform user query (e.g. "cozy hoodie") into a vector that captures semantic meaning.
	// Example: "Cozy Hoodie" → [-0.03546142578125,-0.0379638671875, ...] (1024-dimensional vector).
	$embedding_result = $pinecone->generate_embedding( $query );
	if ( is_wp_error( $embedding_result ) ) {
		return $embedding_result;
	}

	// STEP 2: Semantic similarity search in vector database.
	// Use the query embedding to find products with similar semantic meaning in the Pinecone index.
	$search_results = $pinecone->search( $embedding_result, 3 );
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
 * Server-side render function for AI Style Finder block
 *
 * @param array  $attributes Block attributes (unused).
 * @param string $content    Block content (unused).
 *
 * @return string Rendered block HTML.
 */
function render_ai_style_finder_block( $attributes, $content ) {
	$block_title = isset( $attributes['blockTitle'] ) ? $attributes['blockTitle'] : 'AI Style Finder';
	
	return '<div class="wp-block-create-block-ai-style-finder">
		<h3 class="ai-style-finder-title">' . wp_kses_post( $block_title ) . '</h3>
		<div class="ai-style-finder-search">
			<div class="search-input-container">
				<input 
					type="text" 
					class="ai-search-input" 
					placeholder="Describe what you are looking for..."
				/>
				<button type="button" class="search-button">
					<span class="search-icon">🔍</span>
				</button>
			</div>
			<div class="ai-suggestion-chips">
				<button class="suggestion-chip">Cozy black hoodie for chilly days</button>
				<button class="suggestion-chip">Comfortable yoga pants for stretching</button>
				<button class="suggestion-chip">Women\'s stylish tank for gym workouts</button>
				<button class="suggestion-chip">Eco-friendly gear that looks premium</button>
				<button class="suggestion-chip">Lightweight jacket for outdoor activities</button>
				<button class="suggestion-chip">Expert-recommended performance pieces</button>
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
