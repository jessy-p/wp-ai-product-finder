<?php
/**
 * Pinecone Service Class
 *
 * @package AI_Style_Finder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinecone Service Class for handling vector operations.
 */
class Pinecone_Service {

	/**
	 * Pinecone API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Pinecone index URL.
	 *
	 * @var string
	 */
	private $index_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key   = defined( 'AI_STYLE_FINDER_PINECONE_API_KEY' ) ? AI_STYLE_FINDER_PINECONE_API_KEY : '';
		$this->index_url = defined( 'AI_STYLE_FINDER_PINECONE_INDEX_URL' ) ? AI_STYLE_FINDER_PINECONE_INDEX_URL : '';
	}

	/**
	 * Generate embedding from text query using Pinecone Embed API.
	 *
	 * @param string $query The text query to embed.
	 * @return array|WP_Error The embedding vector or WP_Error on failure.
	 */
	public function generate_embedding( $query ) {
		$response = wp_remote_post(
			'https://api.pinecone.io/embed',
			array(
				'headers' => array(
					'Api-Key'                => $this->api_key,
					'Content-Type'           => 'application/json',
					'X-Pinecone-API-Version' => '2025-04',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'llama-text-embed-v2',
						'inputs'     => array(
							array( 'text' => $query ),
						),
						'parameters' => array(
							'input_type' => 'query',
						),
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['data'][0]['values'] ) ) {
			return $data['data'][0]['values'];
		}

		return new WP_Error( 'embedding_failed', 'Failed to generate embedding: ' . $body );
	}

	/**
	 * Search Pinecone with embedding vector.
	 *
	 * @param array $embedding The embedding vector to search with.
	 * @param int   $top_k     Number of results to return (default 6).
	 * @return array|WP_Error Array of matches or WP_Error on failure.
	 */
	public function search( $embedding, $top_k = 6 ) {
		$response = wp_remote_post(
			$this->index_url,
			array(
				'headers' => array(
					'Api-Key'      => $this->api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'vector'          => $embedding,
						'topK'            => $top_k,
						'includeMetadata' => true,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['matches'] ) ) {
			return $data['matches'];
		}

		return new WP_Error( 'pinecone_search_failed', 'Failed to search Pinecone: ' . $body );
	}
}
