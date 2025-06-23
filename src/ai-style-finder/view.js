/**
 * JavaScript code that runs in the front-end
 * on posts/pages that contain this block.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

document.addEventListener('DOMContentLoaded', function() {
	const blocks = document.querySelectorAll('.wp-block-create-block-ai-style-finder');
	
	blocks.forEach(function(block) {
		const searchInput = block.querySelector('.ai-search-input');
		const searchButton = block.querySelector('.search-button');
		const chips = block.querySelectorAll('.suggestion-chip');
		
		function performSearch() {
			const query = searchInput.value.trim();
			if (query) {
				// Loading state
				searchButton.disabled = true;
				searchButton.textContent = '...';
				searchInput.disabled = true;
				searchInput.value = 'Searching...';
				searchInput.style.color = '#999';

				console.log('Calling API for:', query);
				
				fetch('/wp-json/ai-style-finder/v1/search', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({
						query: query
					})
				})
				.then(response => response.json())
				.then(data => {
					console.log('API Response:', data);
					if (data.success && data.results) {
						displayProductResults(block, data.results, data.explanations);
					} else {
						showNoResults(block);
					}
				})
				.catch(error => {
					console.error('API Error:', error);
					showError(block, 'Search failed. Please try again.');
				})
				.finally(() => {
					// Reset Loading state
					searchButton.disabled = false;
					searchButton.textContent = '🔍';
					searchInput.disabled = false;
					searchInput.value = query;
					searchInput.style.color = '';
				});
			}
		}
		
		chips.forEach(function(chip) {
			chip.addEventListener('click', function() {
				const chipText = chip.textContent;
				searchInput.value = chipText;
				searchInput.focus();
			});
		});
		
		// Search on Enter key
		searchInput.addEventListener('keydown', function(e) {
			if (e.key === 'Enter') {
				performSearch();
			}
		});
		
		// Search on button click
		searchButton.addEventListener('click', function() {
			performSearch();
		});
		
	});
	
	/**
	 * Create a product card from template
	 */
	function buildProductCard(product, explanation) {
		const template = document.getElementById('product-card-template');
		if (!template) {
			console.error('Product card template not found');
			return null;
		}
		
		const clone = template.content.cloneNode(true);
		
		// Use WooCommerce data, else fall back to metadata
		const productData = product.wc_data || product.metadata;
		
		const image = clone.querySelector('.product-image');
		if (productData.image_url) {
			image.src = productData.image_url;
			image.alt = productData.name || 'Product image';
		} else {
			image.style.display = 'none';
		}
		
		const name = clone.querySelector('.product-name');
		if (productData.name) {
			name.innerHTML = productData.name;
		} else {
			name.textContent = 'Product';
		}
		
		const price = clone.querySelector('.product-price');
		if (productData.price_html) {
			price.innerHTML = productData.price_html; // WC formatted price is safe
		} else if (productData.price) {
			price.textContent = productData.price;
		}
		
		const explanationEl = clone.querySelector('.product-explanation');
		if (explanation) {
			explanationEl.innerHTML = explanation;
		} else {
			explanationEl.textContent = 'Great match for your search.';
		}
		
		const card = clone.querySelector('.product-card');
		if (productData.product_url) {
			card.addEventListener('click', function() {
				window.open(productData.product_url, '_blank');
			});
		}
		
		return clone;
	}
	
	/**
	 * Display product search results
	 */
	function displayProductResults(block, results, explanations) {
		const resultsContainer = block.querySelector('.search-results');
		
		resultsContainer.innerHTML = '';
		results.forEach(function(product) {
			const explanation = explanations[product.id] || '';
			const card = buildProductCard(product, explanation);
			if (card) {
				resultsContainer.appendChild(card);
			}
		});
		resultsContainer.classList.add('show');
	}
	
	/**
	 * Show no results message
	 */
	function showNoResults(block) {
		const resultsContainer = block.querySelector('.search-results');
		resultsContainer.innerHTML = '<div class="no-results">No products found. Try a different search term.</div>';
		resultsContainer.classList.add('show');
	}
	
	/**
	 * Show error message
	 */
	function showError(block, message) {
		const resultsContainer = block.querySelector('.search-results');
		resultsContainer.innerHTML = '<div class="error-message">' + message + '</div>';
		resultsContainer.classList.add('show');
	}
});
