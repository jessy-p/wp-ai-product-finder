import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { blockTitle, resultCount } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Search Settings', 'ai-product-finder')}>
					<TextControl
						label={__('Number of Results', 'ai-product-finder')}
						type="number"
						value={resultCount}
						onChange={(value) => setAttributes({ resultCount: parseInt(value) || 3 })}
						min={1}
						max={20}
						help={__('Number of products to show in search results', 'ai-product-finder')}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps()}>
				<RichText
					tagName="h3"
					className="ai-product-finder-title"
					value={blockTitle}
					onChange={(value) => setAttributes({ blockTitle: value })}
					placeholder={__('Enter block title...', 'ai-product-finder')}
				/>
				<p>{__('AI-powered product search will appear here on the frontend.', 'ai-product-finder')}</p>
				<p><small>{__(`Will show ${resultCount} search results`, 'ai-product-finder')}</small></p>
			</div>
		</>
	);
}
