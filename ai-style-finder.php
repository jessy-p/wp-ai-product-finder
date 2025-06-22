<?php
/**
 * Plugin Name:       AI Style Finder
 * Description:       Example block scaffolded with Create Block tool.
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

if (! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}

/**
 * Initialize AI Style Finder block registration
 */
function create_block_ai_style_finder_block_init()
{
    $manifest_data = include __DIR__ . '/build/blocks-manifest.php';
    foreach ( array_keys($manifest_data) as $block_type ) {
        register_block_type(
            __DIR__ . "/build/{$block_type}", array(
            'render_callback' => 'render_ai_style_finder_block'
            ) 
        );
    }
}
add_action('init', 'create_block_ai_style_finder_block_init');

/**
 * Register REST API endpoint for AI search
 */
function register_ai_style_finder_api() {
	register_rest_route('ai-style-finder/v1', '/search', array(
		'methods' => 'POST',
		'callback' => 'handle_ai_search_request',
	));
}
add_action('rest_api_init', 'register_ai_style_finder_api');

/**
 * Handle AI search API request
 */
function handle_ai_search_request($request) {
	$query = $request->get_param('query');
	
	// Basic validation
	if (empty($query)) {
		return new WP_Error('missing_query', 'Query parameter is required', array('status' => 400));
	}
	
	// Return simple success response
	return array(
		'success' => true,
		'query' => $query,
		'message' => 'Search API endpoint working! Received: ' . $query
	);
}

/**
 * Server-side render function for AI Style Finder block
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Block content.
 * 
 * @return string Rendered block HTML.
 */
function render_ai_style_finder_block( $attributes, $content )
{
    return '<div class="wp-block-create-block-ai-style-finder">
		<h3 class="ai-style-finder-title">AI Style Finder</h3>
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
				<button class="suggestion-chip">Gym clothes that look good for brunch after</button>
				<button class="suggestion-chip">Lightweight breathable shirt for summer runs</button>
				<button class="suggestion-chip">Eco-friendly workout clothes</button>
				<button class="suggestion-chip">Performance gear recommended by experts</button>
			</div>
		</div>
	</div>';
}
