=== AI Style Finder ===
Contributors:      The WordPress Contributors
Tags:              block, ai, search, woocommerce, semantic, vector, ecommerce
Tested up to:      6.7
Stable tag:        0.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 6.7
Requires PHP:      7.4

AI-powered semantic product search block that uses vector embeddings to find products based on natural language descriptions.

== Description ==

AI Style Finder is a demonstration Gutenberg block showcasing AI-powered e-commerce product search capabilities using vector embeddings and large language models.
Instead of traditional keyword matching, customers can describe what they're looking for and get relevant product recommendations using semantic search using Pinecone vector database.
It integrates with LLM to help customers understand how the matched products match their needs.

== Requirements ==

* WordPress 6.7 or higher
* PHP 7.4 or higher
* WooCommerce plugin (active)
* Pinecone account and API key 
* OpenAI API key
* Product data indexed in Pinecone vector database

== Installation ==

**Basic Installation:**

1. Upload the plugin files to the `/wp-content/plugins/ai-style-finder` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Add the AI Style Finder block to any page or post using the Gutenberg editor

**Configuration:**

1. Add your API keys and Pinecone Index URL to wp-config.php:
   ```
   define('AI_STYLE_FINDER_PINECONE_API_KEY', 'your-pinecone-api-key');
   define('AI_STYLE_FINDER_PINECONE_INDEX_URL', 'your-pinecone-index-url');
   define('AI_STYLE_FINDER_OPENAI_API_KEY', 'your-openai-api-key');
   ```

2. Ensure your WooCommerce products have SKUs that match your Pinecone vector database entries

3. Test the block by adding it to a page and performing a search

== Changelog ==

= 0.1.0 =
* Initial release
