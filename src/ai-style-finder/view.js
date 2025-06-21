/**
 * JavaScript code that to run in the front-end
 * on posts/pages that contain this block.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

// Enhancement JavaScript for AI Style Finder
document.addEventListener('DOMContentLoaded', function() {
	const blocks = document.querySelectorAll('.wp-block-create-block-ai-style-finder');
	
	blocks.forEach(function(block) {
		const searchInput = block.querySelector('.ai-search-input');
		const searchButton = block.querySelector('.search-button');
		const chips = block.querySelectorAll('.suggestion-chip');
		
		// Function to perform search
		function performSearch() {
			const query = searchInput.value.trim();
			if (query) {
				// Show loading state
				searchButton.disabled = true;
				searchButton.textContent = '...';
				searchInput.disabled = true;
				searchInput.value = 'Searching...';
				searchInput.style.color = '#999';

				console.log('Searching for:', query);
				
				// will replace with API call
				setTimeout(() => {
					// Reset UI state
					searchButton.disabled = false;
					searchButton.textContent = '🔍';
					searchInput.disabled = false;
					searchInput.value = query;
					searchInput.style.color = '';
					
					console.log('Search complete for "' + query + '"');
				}, 1000);
			}
		}
		
		// Make chips clickable to fill search input
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
});
