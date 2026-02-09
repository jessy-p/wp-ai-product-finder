<?php
/**
 * Catalog Processor Class
 *
 * @package AI_Product_Finder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalog Processor Class for managing WooCommerce product sync to Pinecone.
 */
class AI_Product_Finder_Catalog_Processor {

	/**
	 * Pinecone API key.
	 *
	 * @var string
	 */
	private $pinecone_api_key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->pinecone_api_key = AI_Product_Finder_Admin_Settings::get_setting( 'pinecone_api_key' );
	}

	/**
	 * Generate a unique index name for this site.
	 *
	 * @return string Generated index name.
	 */
	public function generate_index_name() {
		$site_name = sanitize_title( get_bloginfo( 'name' ) );
		$timestamp = time();
		return $site_name . '-ai-finder-products-' . $timestamp;
	}

	/**
	 * Get the currently active index name from settings.
	 *
	 * @return string Active index name or empty string if not set.
	 */
	public function get_active_index_name() {
		return get_option( 'ai_product_finder_active_index_name', '' );
	}

	/**
	 * Set the active index name and URL in settings.
	 *
	 * @param string $index_name Index name to set as active.
	 * @param string $index_url Index URL to store.
	 */
	public function set_active_index_info( $index_name, $index_url = '' ) {
		// Store in separate option to avoid form conflicts
		update_option( 'ai_product_finder_active_index_name', $index_name );

		if ( $index_url !== '' ) {
			update_option( 'ai_product_finder_index_url', $index_url );
		}
	}

	/**
	 * Set the active index name in settings (legacy method).
	 *
	 * @param string $index_name Index name to set as active.
	 */
	public function set_active_index_name( $index_name ) {
		$this->set_active_index_info( $index_name );
	}

	/**
	 * Get index details from Pinecone API and set both name and URL.
	 *
	 * @param string $index_name Index name to get details for.
	 * @return array|WP_Error Success response or WP_Error on failure.
	 */
	public function set_index_url( $index_name ) {
		$response = wp_remote_get(
			'https://api.pinecone.io/indexes/' . $index_name,
			array(
				'headers' => array(
					'Api-Key' => $this->pinecone_api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['host'] ) ) {
			$index_url = 'https://' . $data['host'];

			// Set both index name and URL in one operation
			$this->set_active_index_info( $index_name, $index_url );

			return array( 'success' => true, 'index_url' => $index_url );
		}

		return new WP_Error( 'no_host_found', 'Could not retrieve index URL from Pinecone API response' );
	}

	/**
	 * Create a new Pinecone index.
	 *
	 * @param string $index_name Index name to create.
	 * @return array|WP_Error Success response or WP_Error on failure.
	 */
	public function create_pinecone_index( $index_name ) {
		$response = wp_remote_post(
			'https://api.pinecone.io/indexes',
			array(
				'headers' => array(
					'Api-Key'      => $this->pinecone_api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'name' => $index_name,
						'spec' => array(
							'serverless' => array(
								'cloud'  => 'aws',
								'region' => 'us-east-1',
							),
						),
						'dimension' => 1024,
						'metric'    => 'cosine',
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 201 !== $response_code && 409 !== $response_code ) {
			return new WP_Error(
				'index_creation_failed',
				'Failed to create Pinecone index: ' . $body,
				array( 'response_code' => $response_code )
			);
		}

		return array( 'success' => true, 'message' => 'Index created successfully' );
	}

	/**
	 * Wait for Pinecone index to be ready.
	 *
	 * @param string $index_name Index name to check.
	 * @param int    $max_wait_time Maximum time to wait in seconds.
	 * @return array|WP_Error Success response or WP_Error on timeout.
	 */
	public function wait_for_index_ready( $index_name, $max_wait_time = 300 ) {
		$start_time = time();

		while ( time() - $start_time < $max_wait_time ) {
			$response = wp_remote_get(
				'https://api.pinecone.io/indexes/' . $index_name,
				array(
					'headers' => array(
						'Api-Key' => $this->pinecone_api_key,
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['status']['ready'] ) && $data['status']['ready'] === true ) {
				return array( 'success' => true, 'message' => 'Index is ready' );
			}

			sleep( 5 );
		}

		return new WP_Error( 'index_timeout', 'Index creation timed out after ' . $max_wait_time . ' seconds' );
	}

	/**
	 * Load WooCommerce products and convert to searchable format.
	 *
	 * @return array Array of product data for indexing.
	 */
	public function load_woocommerce_products() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return new WP_Error( 'woocommerce_not_active', 'WooCommerce is not active' );
		}

		// Get all product types first to see what we have
		$all_products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		error_log( 'Total WooCommerce products found: ' . count( $all_products ) );

		// Log product types breakdown
		$type_counts = array();
		foreach ( $all_products as $product ) {
			$type = $product->get_type();
			$type_counts[ $type ] = isset( $type_counts[ $type ] ) ? $type_counts[ $type ] + 1 : 1;
		}

		foreach ( $type_counts as $type => $count ) {
			error_log( 'Product type "' . $type . '": ' . $count . ' products' );
		}

		// Get variable products only (like your Python script)
		$variable_products = wc_get_products(
			array(
				'status' => 'publish',
				'type'   => 'variable',
				'limit'  => -1,
			)
		);

		error_log( 'Variable products found: ' . count( $variable_products ) );

		// Also get simple products in case messenger bag is a simple product
		$simple_products = wc_get_products(
			array(
				'status' => 'publish',
				'type'   => 'simple',
				'limit'  => -1,
			)
		);

		error_log( 'Simple products found: ' . count( $simple_products ) );

		// Combine variable and simple products
		$products_to_index = array_merge( $variable_products, $simple_products );
		error_log( 'Total products to index: ' . count( $products_to_index ) );

		$processed_products = array();

		foreach ( $products_to_index as $product ) {
			$processed_product = $this->process_product_for_indexing( $product );
			error_log( 'Processing product: ID=' . $product->get_id() . ', Name="' . $product->get_name() . '", Type=' . $product->get_type() );
			$processed_products[] = $processed_product;
		}

		return $processed_products;
	}

	/**
	 * Process a single WooCommerce product for indexing.
	 *
	 * @param WC_Product $product WooCommerce product object.
	 * @return array Processed product data.
	 */
	private function process_product_for_indexing( $product ) {
		$product_text = $product->get_name() . ' ';

		// Add categories
		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		if ( ! empty( $categories ) ) {
			$product_text .= implode( ' ', $categories ) . ' ';
		}

		// Add description (clean HTML)
		$description = $product->get_description();
		if ( ! empty( $description ) ) {
			$clean_desc = wp_strip_all_tags( $description );
			$clean_desc = preg_replace( '/\s+/', ' ', $clean_desc );
			if ( strlen( $clean_desc ) > 200 ) {
				$clean_desc = substr( $clean_desc, 0, 200 ) . '...';
			}
			$product_text .= $clean_desc . ' ';
		}

		// Add short description
		$short_description = $product->get_short_description();
		if ( ! empty( $short_description ) ) {
			$product_text .= wp_strip_all_tags( $short_description ) . ' ';
		}

		// Add product tags
		$tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
		if ( ! empty( $tags ) ) {
			$product_text .= implode( ' ', $tags ) . ' ';
		}

		// Add weight-based descriptors
		$weight = $product->get_weight();
		if ( ! empty( $weight ) ) {
			$weight_value = floatval( $weight );
			if ( $weight_value <= 0.5 ) {
				$product_text .= 'lightweight ';
			} elseif ( $weight_value >= 2 ) {
				$product_text .= 'heavy-duty substantial ';
			}
		}

		// Add featured status
		$is_featured = $product->is_featured();
		if ( $is_featured ) {
			$product_text .= 'featured popular recommended bestseller ';
		}

		// Get product attributes
		$attributes = $product->get_attributes();
		$attributes_array = array();
		foreach ( $attributes as $attribute ) {
			if ( $attribute->is_taxonomy() ) {
				$terms = wp_get_post_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
				if ( ! empty( $terms ) ) {
					$attr_name = wc_attribute_label( $attribute->get_name() );
					$attr_values = implode( ', ', $terms );
					$attributes_array[ strtolower( $attr_name ) ] = $attr_values;
					$product_text .= $attr_name . ' ' . str_replace( ',', ' ', $attr_values ) . ' ';
				}
			}
		}

		return array(
			'id'         => (string) $product->get_id(),
			'name'       => $product->get_name(),
			'text'       => trim( $product_text ),
			'categories' => implode( ', ', $categories ),
			'price'      => $product->get_regular_price(),
			'sku'        => $product->get_sku(),
			'url'        => get_permalink( $product->get_id() ),
			'attributes' => $attributes_array,
			'weight'     => $weight,
			'featured'   => $is_featured,
			'tags'       => implode( ', ', $tags ),
		);
	}

	/**
	 * Generate embeddings for product texts in batches.
	 *
	 * @param array $texts Array of text strings to embed.
	 * @return array|WP_Error Array of embeddings or WP_Error on failure.
	 */
	public function generate_embeddings_batch( $texts ) {
		$all_embeddings = array();
		$batch_size = 90;

		for ( $i = 0; $i < count( $texts ); $i += $batch_size ) {
			$batch_texts = array_slice( $texts, $i, $batch_size );

			$inputs = array();
			foreach ( $batch_texts as $text ) {
				$inputs[] = array( 'text' => $text );
			}

			$response = wp_remote_post(
				'https://api.pinecone.io/embed',
				array(
					'headers' => array(
						'Api-Key'                => $this->pinecone_api_key,
						'Content-Type'           => 'application/json',
						'X-Pinecone-API-Version' => '2025-04',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => 'llama-text-embed-v2',
							'inputs'     => $inputs,
							'parameters' => array(
								'input_type' => 'passage',
							),
						)
					),
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! isset( $data['data'] ) ) {
				return new WP_Error( 'embedding_failed', 'Failed to generate embeddings: ' . $body );
			}

			foreach ( $data['data'] as $embedding ) {
				$all_embeddings[] = $embedding['values'];
			}

			// Rate limiting
			if ( $i + $batch_size < count( $texts ) ) {
				sleep( 1 );
			}
		}

		return $all_embeddings;
	}

	/**
	 * Upload products to Pinecone index.
	 *
	 * @param string $index_name Index name to upload to.
	 * @param array  $products Array of processed product data.
	 * @param array  $embeddings Array of embeddings matching products.
	 * @param string $index_url Optional index URL to use.
	 * @return array|WP_Error Success response or WP_Error on failure.
	 */
	public function upload_products_to_pinecone( $index_name, $products, $embeddings, $index_url = '' ) {
		$batch_size = 50;

		// Use provided URL or get stored URL
		if ( empty( $index_url ) ) {
			$index_url = get_option( 'ai_product_finder_index_url', '' );
		}

		if ( empty( $index_url ) ) {
			return new WP_Error( 'no_index_url', 'Index URL not found. Please create index first.' );
		}

		$upload_url = rtrim( $index_url, '/' ) . '/vectors/upsert';

		for ( $i = 0; $i < count( $products ); $i += $batch_size ) {
			$batch_products = array_slice( $products, $i, $batch_size );
			$batch_embeddings = array_slice( $embeddings, $i, $batch_size );

			$vectors = array();
			foreach ( $batch_products as $index => $product ) {
				$metadata = array(
					'name'       => $product['name'],
					'categories' => $product['categories'],
					'price'      => $product['price'],
					'sku'        => $product['sku'],
					'url'        => $product['url'],
					'text'       => substr( $product['text'], 0, 500 ), // Limit metadata size
					'featured'   => $product['featured'],
					'tags'       => $product['tags'],
				);

				// Add weight only if not null
				if ( ! empty( $product['weight'] ) ) {
					$metadata['weight'] = floatval( $product['weight'] );
				}

				// Add flattened attributes
				foreach ( $product['attributes'] as $attr_name => $attr_value ) {
					$metadata[ 'attr_' . $attr_name ] = $attr_value;
				}

				$vectors[] = array(
					'id'       => $product['id'],
					'values'   => $batch_embeddings[ $index ],
					'metadata' => $metadata,
				);
			}

			$response = wp_remote_post(
				$upload_url,
				array(
					'headers' => array(
						'Api-Key'      => $this->pinecone_api_key,
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode( array( 'vectors' => $vectors ) ),
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 !== $response_code ) {
				return new WP_Error(
					'upload_failed',
					'Failed to upload batch to Pinecone. Response Code: ' . $response_code . '. Error: ' . $body
				);
			}

			// Rate limiting between batches
			if ( $i + $batch_size < count( $products ) ) {
				sleep( 1 );
			}
		}

		return array( 'success' => true, 'message' => 'Products uploaded successfully' );
	}

	/**
	 * Create index and upload all products.
	 *
	 * @return array|WP_Error Success response or WP_Error on failure.
	 */
	public function create_index_and_upload() {
		// Generate new index name
		$index_name = $this->generate_index_name();

		// Create index
		$create_result = $this->create_pinecone_index( $index_name );
		if ( is_wp_error( $create_result ) ) {
			return $create_result;
		}

		// Wait for index to be ready
		$ready_result = $this->wait_for_index_ready( $index_name );
		if ( is_wp_error( $ready_result ) ) {
			return $ready_result;
		}

		// Get and set the index URL
		$url_result = $this->set_index_url( $index_name );
		if ( is_wp_error( $url_result ) ) {
			return $url_result;
		}

		// Load products
		$products = $this->load_woocommerce_products();
		if ( is_wp_error( $products ) ) {
			return $products;
		}

		if ( empty( $products ) ) {
			return new WP_Error( 'no_products', 'No products found to index' );
		}

		// Generate embeddings
		$texts = array_column( $products, 'text' );
		$embeddings = $this->generate_embeddings_batch( $texts );
		if ( is_wp_error( $embeddings ) ) {
			return $embeddings;
		}

		// Upload to Pinecone using the new index URL
		$upload_result = $this->upload_products_to_pinecone( $index_name, $products, $embeddings, $url_result['index_url'] );
		if ( is_wp_error( $upload_result ) ) {
			return $upload_result;
		}

		// Note: Index name and URL are already set by set_index_url() call above

		return array(
			'success' => true,
			'message' => 'Successfully created index and uploaded ' . count( $products ) . ' products',
			'index_name' => $index_name,
			'product_count' => count( $products ),
		);
	}

	/**
	 * Update existing index with current products.
	 *
	 * @return array|WP_Error Success response or WP_Error on failure.
	 */
	public function update_existing_index() {
		$index_name = $this->get_active_index_name();

		// If no stored index name, try to extract from stored URL
		if ( empty( $index_name ) ) {
			$index_url = get_option( 'ai_product_finder_index_url', '' );
			if ( ! empty( $index_url ) ) {
				// Extract index name from URL like: https://my-store-ai-finder-products-1739031845-abc123.svc.gcp-starter.pinecone.io
				$parsed_url = parse_url( $index_url );
				if ( isset( $parsed_url['host'] ) ) {
					$host_parts = explode( '.', $parsed_url['host'] );
					$index_name = $host_parts[0]; // First part should be the index name

					// Store it for future use
					$this->set_active_index_name( $index_name );
					error_log( 'Recovered index name from URL: ' . $index_name );
				}
			}
		}

		if ( empty( $index_name ) ) {
			return new WP_Error( 'no_active_index', 'No active index found. Please create an index first.' );
		}

		// Load products
		$products = $this->load_woocommerce_products();
		if ( is_wp_error( $products ) ) {
			return $products;
		}

		if ( empty( $products ) ) {
			return new WP_Error( 'no_products', 'No products found to index' );
		}

		// Generate embeddings
		$texts = array_column( $products, 'text' );
		$embeddings = $this->generate_embeddings_batch( $texts );
		if ( is_wp_error( $embeddings ) ) {
			return $embeddings;
		}

		// Upload to Pinecone
		$upload_result = $this->upload_products_to_pinecone( $index_name, $products, $embeddings );
		if ( is_wp_error( $upload_result ) ) {
			return $upload_result;
		}

		return array(
			'success' => true,
			'message' => 'Successfully updated index with ' . count( $products ) . ' products',
			'index_name' => $index_name,
			'product_count' => count( $products ),
		);
	}
}