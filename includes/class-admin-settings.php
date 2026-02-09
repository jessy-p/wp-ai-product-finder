<?php
/**
 * Admin Settings Class
 *
 * @package AI_Product_Finder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Settings Class for managing plugin configuration.
 */
class AI_Product_Finder_Admin_Settings {

	/**
	 * Option name for storing settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ai_product_finder_settings';

	/**
	 * Initialize the admin settings.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu item.
	 */
	public function add_admin_menu() {
		add_options_page(
			'AI Product Finder Settings',
			'AI Product Finder',
			'manage_options',
			'ai-product-finder-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'settings_page_ai-product-finder-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'ai-product-finder-admin',
			plugin_dir_url( __DIR__ ) . 'assets/admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'ai-product-finder-admin',
			'aiProductFinderAjax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ai_product_finder_sync_nonce' ),
			)
		);

		wp_add_inline_style( 'wp-admin', '
			.sync-buttons-container { margin-bottom: 10px; }
			.sync-buttons-container .button { margin-right: 10px; }
			.sync-status { padding: 10px; background: #f1f1f1; border-radius: 3px; }
			.sync-status.loading { background: #fff3cd; border-left: 4px solid #ffc107; }
			.sync-status.success { background: #d4edda; border-left: 4px solid #28a745; }
			.sync-status.error { background: #f8d7da; border-left: 4px solid #dc3545; }
		' );
	}

	/**
	 * Initialize settings sections and fields.
	 */
	public function init_settings() {
		register_setting(
			'ai_product_finder_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		// API Configuration Section
		add_settings_section(
			'ai_product_finder_api_section',
			'API Configuration',
			array( $this, 'render_api_section' ),
			'ai-product-finder-settings'
		);

		add_settings_field(
			'pinecone_api_key',
			'Pinecone API Key',
			array( $this, 'render_text_field' ),
			'ai-product-finder-settings',
			'ai_product_finder_api_section',
			array(
				'field_name' => 'pinecone_api_key',
				'field_type' => 'password',
				'description' => 'Enter your Pinecone API key for vector operations.',
			)
		);

		add_settings_field(
			'openai_api_key',
			'OpenAI API Key',
			array( $this, 'render_text_field' ),
			'ai-product-finder-settings',
			'ai_product_finder_api_section',
			array(
				'field_name' => 'openai_api_key',
				'field_type' => 'password',
				'description' => 'Enter your OpenAI API key for generating product explanations.',
			)
		);

		// Index Information Section
		add_settings_section(
			'ai_product_finder_index_section',
			'Index Information',
			array( $this, 'render_index_section' ),
			'ai-product-finder-settings'
		);

		add_settings_field(
			'active_index_name',
			'Active Index Name',
			array( $this, 'render_readonly_field' ),
			'ai-product-finder-settings',
			'ai_product_finder_index_section',
			array(
				'field_name' => 'active_index_name',
				'description' => 'Current active Pinecone index name (auto-generated when you create an index).',
			)
		);

		add_settings_field(
			'pinecone_index_url',
			'Pinecone Index URL',
			array( $this, 'render_readonly_field' ),
			'ai-product-finder-settings',
			'ai_product_finder_index_section',
			array(
				'field_name' => 'pinecone_index_url',
				'description' => 'Complete Pinecone index URL (auto-generated when you create an index).',
			)
		);


		// Catalog Sync Section
		add_settings_section(
			'ai_product_finder_sync_section',
			'Sync Catalog to Pinecone',
			array( $this, 'render_sync_section' ),
			'ai-product-finder-settings'
		);

		add_settings_field(
			'catalog_sync_actions',
			'Catalog Actions',
			array( $this, 'render_sync_buttons' ),
			'ai-product-finder-settings',
			'ai_product_finder_sync_section'
		);
	}

	/**
	 * Render API configuration section description.
	 */
	public function render_api_section() {
		echo '<p>Configure your API keys for Pinecone and OpenAI services.</p>';
	}

	/**
	 * Render index information section description.
	 */
	public function render_index_section() {
		echo '<p>Information about your current Pinecone index. These fields are automatically populated when you create an index.</p>';
	}


	/**
	 * Render catalog sync section description.
	 */
	public function render_sync_section() {
		echo '<p>Sync your WooCommerce products to Pinecone for AI-powered search. Make sure to configure your API settings above first.</p>';
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value = isset( $options[ $args['field_name'] ] ) ? $options[ $args['field_name'] ] : '';
		$field_type = isset( $args['field_type'] ) ? $args['field_type'] : 'text';

		echo sprintf(
			'<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
			esc_attr( $field_type ),
			esc_attr( $args['field_name'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $args['field_name'] ),
			esc_attr( $value )
		);

		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render readonly field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_readonly_field( $args ) {
		// Read from separate options for index info
		if ( $args['field_name'] === 'active_index_name' ) {
			$value = get_option( 'ai_product_finder_active_index_name', '' );
		} elseif ( $args['field_name'] === 'pinecone_index_url' ) {
			$value = get_option( 'ai_product_finder_index_url', '' );
		} else {
			$options = get_option( self::OPTION_NAME, array() );
			$value = isset( $options[ $args['field_name'] ] ) ? $options[ $args['field_name'] ] : '';
		}

		echo sprintf(
			'<input type="text" id="%s" name="%s[%s]" value="%s" class="regular-text" readonly style="background: #f1f1f1;" />',
			esc_attr( $args['field_name'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $args['field_name'] ),
			esc_attr( $value )
		);

		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value = isset( $options[ $args['field_name'] ] ) ? $options[ $args['field_name'] ] : 3;
		$min = isset( $args['min'] ) ? $args['min'] : 1;
		$max = isset( $args['max'] ) ? $args['max'] : 100;

		echo sprintf(
			'<input type="number" id="%s" name="%s[%s]" value="%s" min="%s" max="%s" class="small-text" />',
			esc_attr( $args['field_name'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $args['field_name'] ),
			esc_attr( $value ),
			esc_attr( $min ),
			esc_attr( $max )
		);

		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render sync buttons.
	 */
	public function render_sync_buttons() {
		?>
		<div class="sync-buttons-container">
			<button type="button" id="create-index-btn" class="button button-primary">
				Create Index
			</button>
			<button type="button" id="update-index-btn" class="button button-secondary">
				Update Index
			</button>
		</div>
		<p class="description">
			<strong>Create Index:</strong> Creates a new Pinecone index and uploads all WooCommerce products.<br>
			<strong>Update Index:</strong> Updates existing index with current product data.
		</p>
		<div id="sync-status" class="sync-status" style="margin-top: 15px;"></div>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>AI Product Finder Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ai_product_finder_settings_group' );
				do_settings_sections( 'ai-product-finder-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public function sanitize_settings( $input ) {
		// Get existing settings to preserve programmatically set values
		$existing_settings = get_option( self::OPTION_NAME, array() );
		$sanitized = $existing_settings;

		if ( isset( $input['pinecone_api_key'] ) ) {
			$sanitized['pinecone_api_key'] = sanitize_text_field( $input['pinecone_api_key'] );
		}

		if ( isset( $input['openai_api_key'] ) ) {
			$sanitized['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
		}

		// Always preserve readonly fields
		if ( isset( $existing_settings['active_index_name'] ) ) {
			$sanitized['active_index_name'] = $existing_settings['active_index_name'];
		}

		if ( isset( $existing_settings['pinecone_index_url'] ) ) {
			$sanitized['pinecone_index_url'] = $existing_settings['pinecone_index_url'];
		}

		return $sanitized;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed Setting value or default.
	 */
	public static function get_setting( $key, $default = '' ) {
		$options = get_option( self::OPTION_NAME, array() );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * Get all settings.
	 *
	 * @return array All settings.
	 */
	public static function get_all_settings() {
		return get_option( self::OPTION_NAME, array() );
	}
}