<?php
// This file is generated. Do not modify it manually.
return array(
	'ai-style-finder' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'create-block/ai-style-finder',
		'version' => '0.1.0',
		'title' => 'AI Style Finder',
		'category' => 'widgets',
		'icon' => 'smiley',
		'description' => 'Block that displays AI-powered Search Results',
		'example' => array(
			
		),
		'attributes' => array(
			'productCount' => array(
				'type' => 'number',
				'default' => 6
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'ai-style-finder',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js'
	)
);
