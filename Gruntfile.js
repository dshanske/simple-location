module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    wp_readme_to_markdown: {
      target: {
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
          '!sass/.sass-cache/**',
        ],
        dest: 'build/trunk/'
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

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'makepot', 'sass']);
};
