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
	copy                 : {
		main: {
			files: [
				{expand: true, cwd: 'node_modules/simple-icons/icons/', src: ['{boeing,lufthansa,britishairways,easyjet,americanairlines,s7airlines,unitedairlines,pegasusairlines,ethiopianairlines,southwestairlines,lotpolishairlines,chinaeasternairlines,chinasouthernairlines,aerlingus,aeroflot,aeromexico,aircanada,airchina,airfrance,airasia,airbus,emirates,etihadairways,qatarairways,ryanair,sanfranciscomunicipalrailway,shanghaimetro,turkishairlines,wizzair,alitalia,ana,delta}.svg'], dest: 'svgs/'},
			],
		},
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
              'data/airports.csv': 'https://davidmegginson.github.io/ourairports-data/airports.csv',
	      'data/airlines.csv': 'https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat',
            } 
       }
    });

    // Load plugins.
    grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-eslint');
    grunt.loadNpmTasks('grunt-downloadfile');

    // Default task(s).
    grunt.registerTask('default', ['wp_readme_to_markdown', 'eslint', 'sass' ]);
};
