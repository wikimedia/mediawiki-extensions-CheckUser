<?php

declare( strict_types=1 );

// Configuration for the composer package "friendsofphp/php-cs-fixer"

// Fix all the files in the project except for the 3rd-party ones in vendor/ and node_modules/ directories.
$finder = PhpCsFixer\Finder::create()
	->in( __DIR__ )
	->exclude( 'vendor' )
	->exclude( 'node_modules' );

return ( new PhpCsFixer\Config() )
	// Tells PHP CS Fixer to use tabs when it writes new indentation.
	// Without this it defaults to 4 spaces, which would fight with MediaWiki's tab-based style.
	->setIndent( "\t" )
	->setRules(
		// This is the rule we're enforcing:
		// if a function/method call is broken across multiple lines, every argument must be on its own line.
		[
			'method_argument_space' => [
				'on_multiline' => 'ensure_fully_multiline',
			],
		]
	)
	->setFinder( $finder );
