<?php
/**
 * OpenAI Service Class
 *
 * @package AI_Product_Finder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Service Class for handling LLM API requests.
 */
class OpenAI_Service {

	/**
	 * OpenAI API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key = AI_Product_Finder_Admin_Settings::get_setting( 'openai_api_key' );
	}

	/**
	 * Get LLM explanations for multiple products in a single API call.
	 *
	 * @param string $query The search query.
	 * @param array  $products Array of product data.
	 * @return array Array of explanations keyed by product ID.
	 */
	public function explain_matches( $query, $products ) {
		$product_list = '';
		$json_example = array();

		foreach ( $products as $index => $product ) {
			$product_id = isset( $product['id'] ) ? $product['id'] : 'product_' . $index;
			$metadata   = isset( $product['metadata'] ) ? $product['metadata'] : array();

			$name        = isset( $metadata['name'] ) ? $metadata['name'] : 'Product ' . ( $index + 1 );
			$category    = isset( $metadata['category'] ) ? $metadata['category'] : '';
			$description = isset( $metadata['description'] ) ? $metadata['description'] : '';

			$product_list .= "Product ID: $product_id\n";
			$product_list .= "Name: $name\n";
			$product_list .= "Category: $category\n";
			$product_list .= "Description: $description\n\n";

			$json_example[ $product_id ] = 'explanation here';
		}

		$json_output_format = wp_json_encode( $json_example, JSON_PRETTY_PRINT );

		$prompt = "User searched for: \"$query\"

Here are the matching products:

$product_list

For each product, explain in 1 concise sentence why it matches the user's search. Make each explanation unique and focus on different aspects (style, features, materials, etc.).

Return your response in this exact JSON format:
$json_output_format";

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-4o-mini',
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => 'You are a helpful shopping assistant. Return explanations in the exact JSON format requested. Be concise and focus on why each product matches the search.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'max_tokens'  => 300,
						'temperature' => 0.7,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->generate_fallback_explanations( array_keys( $json_example ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = trim( $data['choices'][0]['message']['content'] );

			$explanations = json_decode( $content, true );
			if ( $explanations && is_array( $explanations ) ) {
				return $explanations;
			}
		}

		return $this->generate_fallback_explanations( array_keys( $json_example ) );
	}

	/**
	 * Generate fallback explanations for products.
	 *
	 * @param array $product_ids Array of product IDs.
	 * @return array Array of fallback explanations.
	 */
	private function generate_fallback_explanations( $product_ids ) {
		$fallback = array();
		foreach ( $product_ids as $id ) {
			$fallback[ $id ] = 'This product matches your search criteria.';
		}
		return $fallback;
	}
}
