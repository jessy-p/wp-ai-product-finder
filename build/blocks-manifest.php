<?php
// This file is generated. Do not modify it manually.
return array(
	'ai-product-finder' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'ai-product-finder/search',
		'version' => '1.0.0',
		'title' => 'AI Product Finder',
		'category' => 'widgets',
		'icon' => 'smiley',
		'description' => 'Block that displays AI-powered Search Results',
		'example' => array(
			
		),
		'attributes' => array(
			'blockTitle' => array(
				'type' => 'string',
				'default' => 'AI Product Finder'
			),
			'resultCount' => array(
				'type' => 'number',
				'default' => 3
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'ai-product-finder',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js'
	)
);
