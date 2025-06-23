import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { productCount } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Search Settings', 'ai-style-finder')}>
					<RangeControl
						label={__('Number of Products to Display', 'ai-style-finder')}
						value={productCount}
						onChange={(value) => setAttributes({ productCount: value })}
						min={3}
						max={12}
						step={3}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps()}>
				<h3>{__('AI Style Finder', 'ai-style-finder')}</h3>
				<p>{__('Configure search settings in the sidebar panel.', 'ai-style-finder')}</p>
				<p>{__(`Will display ${productCount} products.`, 'ai-style-finder')}</p>
			</div>
		</>
	);
}
