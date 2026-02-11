# AI Product Finder

A WordPress Gutenberg block that enables AI-powered e-commerce product search using vector embeddings and LLM integration.

![Demo](assets/demo.gif)

## Overview

AI Product Finder enables customers to search using natural language descriptions instead of traditional keyword matching. The plugin uses vector similarity search and large language models to deliver relevant product results from your WooCommerce catalog based on meaning rather than keywords.

## How it Works

### Setup

The plugin requires a [Pinecone](https://www.pinecone.io/) account for vector storage and search, and an [OpenAI](https://openai.com/) account for generating product explanations. Once configured under **Settings > AI Product Finder**, sync your WooCommerce catalog to Pinecone using the built-in catalog sync.

For detailed installation and configuration steps, see [readme.txt](./readme.txt).

### Semantic Search and Vector Databases

**Traditional keyword search** looks for exact word matches. If you search for "cozy winter clothes" it only finds products with those exact words in the title or description.

**Semantic search** understands meaning and context. It knows that "cozy" relates to "warm," "comfortable," and "soft," and that "winter clothes" includes hoodies, sweaters, and jackets - even if those exact words aren't in the product name.

**Vector databases** make this possible by converting text into mathematical representations called embeddings. Each product description becomes a list of numbers that capture its semantic meaning. Similar products have similar number patterns, allowing the database to find related items based on meaning rather than just keywords.

**Pinecone** is a cloud-based vector database where we can upload our product catalog and get instant semantic search capabilities. Each product's name, description, and attributes are processed through Pinecone and converted into an embedding that captures its semantic meaning. These embeddings are stored for fast similarity search.

**Indexing Process:** When you sync your catalog from the settings page, the plugin reads your WooCommerce products directly and constructs rich text representations combining product names, descriptions, categories, attributes, and tags. These are batch-processed through Pinecone's `llama-text-embed-v2` embedding model to generate 1024-dimensional vectors. The vectors are uploaded to Pinecone along with product metadata (name, description, price, SKU, categories, attributes, etc.) into an auto-generated index configured for cosine similarity search to find semantically related products.

![Pinecone index](assets/pinecone-index.png)

### The AI-powered Search Process

When a customer searches for "cozy hoodie for winter":

**Step 1: Generate Query Embedding**
```php
$pinecone->generate_embedding("cozy hoodie for winter");
// API Call: Pinecone text embedding API with the query string
// Returns: 1024-dimensional vector representing the search intent
```

**Step 2: Vector Similarity Search**
```php
$pinecone->search($embedding, 3);
// API Call: Pinecone vector database with the embedding from previous step
// Returns: Top 3 products with highest similarity scores
```

**Step 3: Generate Explanations**
```php
$openai->explain_matches($query, $products);
// API Call: GPT-4o mini with structured prompt 
// Input: User query + product details for the matched products
// Returns: JSON with explanations for each product match
```

## Technical Highlights

* **Dynamic Gutenberg Block** - Server-side PHP rendering with JavaScript enhancement.

* **Block Attributes & Controls** - Editable block title using RichText component.

* **WordPress REST API Integration**
   - Custom namespace endpoint `POST /wp-json/ai-product-finder/v1/search`.
   - Structured JSON responses with search results and AI explanations.
   - Input validation and WP_Error handling.

* **WooCommerce Integration**
   - Map results from the index to WooCommerce products using SKU.
   - Enrich results with up-to-date WooCommerce data such as pricing, images, and URLs.
   - Product results link to the product URL.

* **Service Class Architecture** - Dedicated `AI_Product_Finder_Pinecone_Service` and `AI_Product_Finder_OpenAI_Service` classes to separate external API operations from the core logic.

## License

This code is licensed under GPL v2 or later license. See [LICENSE](./LICENSE).
