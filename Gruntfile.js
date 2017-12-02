module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
                checktextdomain: {
                        options:{
                                text_domain: 'simple-location',
                                keywords: [
                                        '__:1,2d',
                                        '_e:1,2d',
                                        '_x:1,2c,3d',
                                        'esc_html__:1,2d',
                                        'esc_html_e:1,2d',
                                        'esc_html_x:1,2c,3d',
                                        'esc_attr__:1,2d',
                                        'esc_attr_e:1,2d',
                                        'esc_attr_x:1,2c,3d',
                                        '_ex:1,2c,3d',
                                        '_n:1,2,4d',
                                        '_nx:1,2,4c,5d',
                                        '_n_noop:1,2,3d',
                                        '_nx_noop:1,2,3c,4d'
                                ]
                        },
                        files: {
                                src:  [
                                        '**/*.php',         // Include all files
                                        'includes/*.php', // Include includes
                                        '!sass/**',       // Exclude sass/
                                        '!node_modules/**', // Exclude node_modules/
                                        '!tests/**',        // Exclude tests/
                                        '!vendor/**',       // Exclude vendor/
                                        '!build/**'           // Exclude build/
                                ],
                                expand: true
                        }
                },



    wp_readme_to_markdown: {
      target: {
        options: {
	  screenshot_url: '/assets/{screenshot}.png'
	},
        files: {
          'README.md': 'readme.txt'
        }
      }
    },

    sass: {
      dev: {
        options: {
          style: 'expanded'
        },
        files: {
          'css/location.css': 'sass/main.scss'
        }
      },
      dist: {
        options: {
          style: 'compressed'
        },
        files: {
          'css/location.min.css': 'sass/main.scss'
        }
      }
    },

    copy: {
      main: {
        options: {
          mode: true
        },
        src: [
          '**',
          '!node_modules/**',
          '!build/**',
          '!.git/**',
          '!Gruntfile.js',
          '!package.json',
          '!.gitignore',
	  '!vendor/**',
          '!sass/.sass-cache/**',
        ],
        dest: 'build/trunk/'
      },
            assets: {
               options: {
                   mode: true
               },
               src: [
                 'assets/*'
               ],
               dest: 'build/'
            }

    },

    makepot: {
      target: {
        options: {
	  mainFile: 'simple-location.php',
          domainPath: '/languages',
          potFilename: 'simple-location.pot',
          type: 'wp-plugin',
	  exclude: [
	  	'build/.*'
		],
          updateTimestamp: true
        }
      }
    }
  });

  // Load plugins.
  grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
  grunt.loadNpmTasks('grunt-wp-i18n');
  grunt.loadNpmTasks('grunt-contrib-sass');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-checktextdomain');

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'makepot', 'sass', 'checktextdomain']);
};
