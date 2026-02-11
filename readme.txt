=== AI Product Finder ===
Contributors:      JC
Tags:              block, ai, search, woocommerce, semantic
Tested up to:      6.9
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 6.7
Requires PHP:      7.4

AI-powered semantic product search block that uses vector embeddings to find products based on natural language descriptions.

== Description ==

AI Product Finder is a Gutenberg block that enables AI-powered e-commerce product search using vector embeddings and large language models.
Instead of traditional keyword matching, customers can describe what they're looking for and get relevant product recommendations via semantic search powered by Pinecone's vector database.
It uses generative AI to provide concise explanations of why each product matches the customer's search.

== Requirements ==

* WordPress 6.7 or higher
* PHP 7.4 or higher
* WooCommerce plugin (active)
* Pinecone API key 
* OpenAI API key

== Installation ==

**Basic Installation:**

1. Upload the plugin files to the `/wp-content/plugins/ai-product-finder` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Add the AI Product Finder block to any page or post using the Gutenberg editor

**Configuration:**

1. Configure Pinecone and OpenAI API keys on Settings page.
* Create an account at https://www.pinecone.io/ and get the API key, add it as Pinecone API Key.
* Create an OpenAI account at https://openai.com/ and get the API key, add it as OpenAI API Key.

2. Click on "Create Index" under Sync Catalog to Pinecone for your products to be uploaded to Pinecone and wait for the success message.

== Changelog ==

= 1.0.0 =
* Initial release
