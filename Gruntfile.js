module.exports = function(grunt) {

    // Project configuration.
    grunt.initConfig({
        eslint: {
	    options: {
		fix: true
	    },
            location: {
                src: ['js/location.js', 'js/zones.js' ]
            }
        },
        
	wp_readme_to_markdown: {
            target: {
                options: {
                    screenshot_url: '/assets/{screenshot}.png'
                },
                files: {
                    'readme.md': 'readme.txt'
                }
            }
        },

        sass: {
            dev: {
                options: {
                    style: 'expanded'
                },
                files: {
                    'css/location.css': 'sass/main.scss',
                    'css/location-admin.css': 'sass/admin.scss'
                }
            },
            dist: {
                options: {
                    style: 'compressed'
                },
                files: {
                    'css/location.min.css': 'sass/main.scss',
                    'css/location-admin.min.css': 'sass/admin.scss'
                }
            }
        },
        downloadfile: {
	    options: {
	       overwriteEverytime: true
            },
	    files: {
              'data/airports.csv': 'https://ourairports.com/data/airports.csv',
	      'data/airlines.csv': 'https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat',
            } 
       }
    });

    // Load plugins.
    grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-eslint');
    grunt.loadNpmTasks('grunt-downloadfile');

    // Default task(s).
    grunt.registerTask('default', ['wp_readme_to_markdown', 'eslint', 'sass' ]);
};
