/**
 * Grunt file.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 *
 * @todo consider switching to composer.json as the "source of truth" for the
 *       'replace' task.  Either way, add a task to keep {package,composer}.json
 *       in sync where they share common properties.
 * @todo look into grunt-wp-deploy, for deploying to .org SVN.
 */

module.exports = function( grunt ) {
	'use strict';

	var pkg = grunt.file.readJSON( 'package.json' );

	// Project configuration.
	var config = {
		pkg: pkg,

		// minify JS files.
		uglify: {
			build: {
				files: [{
					expand: true,
					src: [
						'assets/js/**/*.js',

						'!assets/js/**/*.min.js'
					],
					dest: '.',
					ext: '.min.js',
				}],
			},
		},

		// SASS pre-process CSS files.
		sass: {
			options: {
				sourcemap: 'none',
				style: 'compact',
			},
			build: {
				files: [{
					expand: true,
					src: [
						'assets/css/**/*.scss',

						'!assets/css/**/_*.scss'
					],
					dest: '.',
					ext: '.css',
				}],
			},
		},

		// create RTL CSS files.
		rtlcss: {
			options: {
				// borrowed from Core's Gruntfile.js, with a few mods.
				opts: {
					clean: false,
					processUrls: {
						atrule: true,
						decl: false,
					},
					stringMap: [{
						name: 'import-rtl-stylesheet',
						priority: 10,
						exclusive: true,
						search: ['.css'],
						replace: ['-rtl.css'],
						options: {
							scope: 'url',
							ignoreCase: false,
						},
					}],
				},
				plugins: [{
					name: 'swap-dashicons-left-right-arrows',
					priority: 10,
					directives: {
						control: {},
						value: []
					},
					processors: [{
						expr: /content/im,
						action: function( prop, value ) {
							if ( value === "'\\f141'" ) { // dashicons-arrow-left.
								value = "'\\f139'";
							} else if ( value === "'\\f340'" ) { // dashicons-arrow-left-alt.
								value = "'\\f344'";
							} else if ( value === "'\\f341'" ) { // dashicons-arrow-left-alt2.
								value = "'\\f345'";
							} else if ( value === "'\\f139'" ) { // dashicons-arrow-right.
								value = "'\\f141'";
							} else if ( value === "'\\f344'" ) { // dashicons-arrow-right-alt.
								value = "'\\f340'";
							} else if ( value === "'\\f345'" ) { // dashicons-arrow-right-alt2.
								value = "'\\f341'";
							}

							return {
								prop: prop,
								value: value
							};
						},
					}],
				}],
			},
			build: {
				files: [{
					expand: true,
					src: [
						'assets/css/**/*.css',

						'!assets/css/**/*-rtl.css', '!assets/css/**/*.min.css'
					],
					dest: '.',
					ext: '-rtl.css',
				}],
			},
		},

		// minify CSS files.
		cssmin: {
			build: {
				files: [{
					expand: true,
					src: [
						'assets/css/**/*.css',

						'!assets/css/**/*.min.css'
					],
					dest: '.',
					ext: '.min.css',
				}],
			},
		},

		// various string replacements.
		replace: {
			readme: {
				src: 'readme.txt',
				overwrite: true,
				replacements: [
					{
						from: /^=== (.*) ===/m,
						to: '=== <%= pkg.plugin_name %> ==='
					},
					{
						from: /^(Contributors:) (.*)/m,
						to: '$1 <%= pkg.contribs %>',
					},
					{
						from: /^(Tags:) (.*)/m,
						to: '$1 <%= pkg.tags %>',
					},
					{
						from: /^(Requires at least:) (.*)/m,
						to: '$1 <%= pkg.requires_at_least %>',
					},
					{
						from: /^(Requires PHP:) (.*)/m,
						to: '$1 <%= pkg.requires_php %>',
					},
					{
						from: /^(Tested up to:) (.*)/m,
						to: '$1 <%= pkg.tested_up_to %>',
					},
					{
						from: /^(Stable tag:) (.*)/m,
						to: '$1 <%= pkg.version %>',
					},
					{
						from: /^(License:) (.*)/m,
						to: '$1 <%= pkg.license %>',
					},
					{
						from: /^(License URI:) (.*)/m,
						to: '$1 <%= pkg.license_uri %>',
					},
					{
						from: /^(Donate [lL]ink:) (.*)/m,
						to: '$1 <%= pkg.donate_link %>',
					},
					{
						// for this regex to work the readme.txt file MUST have
						// unix line endings (Windows won't work).
						// note the look ahead. Also, the repeat on the
						// newline char class MUST be {2,2}, using just {2} always
						// fails.
						from: /.*(?=[\n\r]{2,2}== Description ==)/m,
						to: '<%= pkg.description %>',
					},
				],
			},
			plugin_php: {
				src: 'plugin.php',
				overwrite: true,
				replacements: [
					{
						from: /^( \* Plugin Name:) (.*)/m,
						to: '$1 <%= pkg.plugin_name %>',
					},
					{
						from: /^( \* Version:) (.*)/mg,
						to: '$1 <%= pkg.version %>',
					},
					{
						from: /^( \* Description:) (.*)/m,
						to: '$1 <%= pkg.description %>',
					},
					{
						from: /^( \* Plugin URI:) (.*)/m,
						to: '$1 <%= pkg.plugin_uri %>/',
					},
					{
						from: /^( \* GitHub Plugin URI:) (.*)/m,
						to: '$1 https://github.com/<%= pkg.github_user %>/<%= pkg.name %>/',
					},
					{
						from: /^( \* License URI:) (.*)/m,
						to: '$1 <%= pkg.license_uri %>',
					},
					{
						from: /^(.*const VERSION =) '(.*)'/m,
						to: "$1 '<%= pkg.version %>'",
					},
				],
			},
			namespace: {
				src: [
					'plugin.php', 'uninstall.php',
					'includes/**/*.php', 'admin/**/*.php'
				],
				overwrite: true,
				replacements: [{
					from: /^namespace (.*);$/m,
					to: 'namespace <%= pkg.namespace %>;',
				}],
			},
		},

		// Create README.md for GitHub.
		wp_readme_to_markdown: {
			options: {
				screenshot_url: '.org-repo-assets/{screenshot}.png?raw=true',
			},
			dest: {
				files: {
					'README.md': 'readme.txt'
				},
			},
		},

		// lint JS.
		jshint: {
			options: {
				jshintrc: '.jshintrc'
			},
			release: {
				src: [
					'assets/**/*.js',

					'!assets/**/*.min.js'
				]
			},
			build: {
				src: [
					'Gruntfile.js',
				],
			},
		},

		// copy files from one place to another.
		copy: {
			release: {
				expand: true,
				src: [
					'plugin.php', 'readme.txt', 'assets/**',
					'includes/**', 'admin/**','utils/**',
					'vendor/composer/**', 'vendor/autoload.php',

					'!assets/css/**/*.scss',
					'!vendor/composer/installed.sv'
				],
				dest: '<%= pkg.name %>',
			},
			composer_require: {
				expand: true,
				src: get_dependencies( grunt.file.readJSON( 'composer.json' ), 'composer' ),
				dest: '<%= pkg.name %>',
			},
			npm_dependencies: {
				expand: true,
				src: get_dependencies( pkg, 'npm' ),
				dest: '<%= pkg.name %>',
			},
		},

		// package into a zip.
		zip: {
			release: {
				expand: true,
				cwd: '.',
				src: '<%= pkg.name %>/**',
				dest: '<%= pkg.name %>.<%= pkg.version %>.zip',
			},
		},

		// cleanup.
		clean: {
			build: [
				'<%= pkg.name %>', '<%= pkg.name %>.zip',
				'assets/**/*.min.*', 'assets/css/**/*-rtl.css',
			],
			release: [
				'<%= pkg.name %>',
			],
		},

		// Run shell commands.
		shell: {
			options: {
				execOptions: {
					// prepend 'vendor/bin' to the PATH environment variable,
					// to ensure we get our copy of the commands, in any of them
					// are also installed globally.
					env: { 'PATH': ( 'win32' === process.platform ? '.\\vendor\\bin\\' : './vendor/bin/' ).concat( ';', process.env.PATH ) },
				}, 
			},
			phpcs: {
				command: 'phpcs',
			},
			phpcbf: { 
				command: 'phpcbf',
			},
			phpunit: {
				command: 'phpunit' + ( grunt.option( 'group' ) ? ' --group ' + grunt.option( 'group' ) : '' ),
			},
			phpunit_ms: {
				command: 'phpunit -c tests/phpunit/multisite.xml' + ( grunt.option( 'group' ) ? ' --group ' + grunt.option( 'group' ) : '' ),
			},
		},
	};

	grunt.initConfig( config );

	grunt.loadNpmTasks( 'grunt-composer' );
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-sass' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-shell' );
	grunt.loadNpmTasks( 'grunt-text-replace' );
	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.loadNpmTasks( 'grunt-zip' );

	// finally, register our tasks.
	grunt.registerTask( 'default', [ 'build' ] );
	grunt.registerTask( 'build', [ 'clean', 'autoload', 'uglify', /*'sass',*/ 'rtlcss', 'cssmin' ] );

	grunt.registerTask( 'precommit', [ 'phpunit', 'phpunit_ms', 'phpcs', 'jshint:release' ] );
	// build and package everything up into a ZIP suitable for installing on a WP site.
	grunt.registerTask(
		'release',
		[
			'build', 'precommit',
			'readme', 'replace:plugin_php',
			// make sure that autoloads for dev dependencies aren't included.'
			'stash_composer_installed', 'autoload-release',
			'copy', 'zip:release', 'clean:release',
			// rebuild autoloads with dev dependencies.'
			'restore_composer_installed', 'autoload',
		]
	);

	grunt.registerTask( 'phpcs', [ 'shell:phpcs' ] );
	grunt.registerTask( 'phpcbf', [ 'shell:phpcbf' ] );
	grunt.registerTask( 'phpunit', [ 'shell:phpunit' ] );
	grunt.registerTask( 'phpunit_ms', [ 'shell:phpunit_ms' ] );

	// this task is normally only run early in the project, when I haven't
	// yet decided on what namespace I want to use :-)
	grunt.registerTask( 'namespace', [ 'replace:namespace' ] );
	// run this task whenevr a new class class is added
	// (or the name of an existing one is changed
	grunt.registerTask( 'autoload', [ 'composer:dump-autoload' ] );
	grunt.registerTask( 'autoload-release', [ 'composer:dump-autoload:classmap-authoritative' ] );
	// regenerate the readme(s).
	grunt.registerTask( 'readme', ['replace:readme', 'wp_readme_to_markdown'] );

	// see the "release" task for where this task is used.
	// note that this is NOT general purpose and won't work IF our plugin has required
	// dependencies that use composer's autoloader.
	grunt.registerTask(
		'stash_composer_installed',
		'Stash composer\'s installed.json so that the output of composer dump:autoload will only contain our classes.',
		function() {
			if ( grunt.file.exists( 'vendor/composer/installed.json' ) ) {
				grunt.file.copy( 'vendor/composer/installed.json', 'vendor/composer/installed.sv' );
				grunt.file.delete( 'vendor/composer/installed.json' );
				grunt.log.writeln( 'installed.json stashed' );
			}
		}
	);
	// see the "release" task for where this task is used.
	grunt.registerTask(
		'restore_composer_installed',
		'Restore composer\'s installed.json, after stash_composer_installed was run.',
		function() {
			if ( grunt.file.exists( 'vendor/composer/installed.sv' ) ) {
				grunt.file.copy( 'vendor/composer/installed.sv', 'vendor/composer/installed.json' );
				grunt.file.delete( 'vendor/composer/installed.sv' );
				grunt.log.writeln( 'installed.json restored' );
			}
		}
	);
};

/**
 * Extract dependencies from {package,composer}.json, e.g. for use in a 'src:' property of a task.
 *
 * @since 0.2.0
 *
 * @param {object} json The parsed json file.
 * @param {string} which Whether thejson is for npm or composer.
 * @returns array
 *
 * @link https://stackoverflow.com/a/34629499/7751811
 */
function get_dependencies( json, which ) {
	'use strict';

	var prop, dir;

	switch ( which ) {
		case 'npm':
			prop = 'dependencies';
			dir  = 'node_modules';

			break;
		case 'composer':
			prop = 'require';
			dir  = 'vendor';

			break;
	}

	if ( ! json.hasOwnProperty( prop ) ) {
		return [];
	}

	return Object.keys( json[ prop ] ).map(
		function( val ) {
			return dir + '/' + val + '/**';
		}
	);
}
