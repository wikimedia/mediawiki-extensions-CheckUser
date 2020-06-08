/*!
 * Grunt file
 *
 * @package CheckUser
 */

/* eslint-env node */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true
			},
			all: [
				'*.{js,json}',
				'modules/**/*.{js,json}'
			]
		},
		stylelint: {
			all: [
				'modules/**/*.{css,less}'
			]
		},
		banana: conf.MessagesDirs
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana', 'stylelint' ] );
	grunt.registerTask( 'default', 'test' );
};
