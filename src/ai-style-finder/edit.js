import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { blockTitle } = attributes;

	return (
		<div {...useBlockProps()}>
			<RichText
				tagName="h3"
				className="ai-style-finder-title"
				value={blockTitle}
				onChange={(value) => setAttributes({ blockTitle: value })}
				placeholder={__('Enter block title...', 'ai-style-finder')}
			/>
			<p>{__('AI-powered product search will appear here on the frontend.', 'ai-style-finder')}</p>
		</div>
	);
}
