module.exports = function(grunt) {
    require("matchdep").filterDev("grunt-*").forEach(grunt.loadNpmTasks);

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        sass: {
          dist: {
            options: {
              style: 'compressed'
            },
            files: {
              'assets/dist/frontend.min.css': 'assets/css/frontend.scss'
            },
          }
        },

        postcss: {
          options: {
            map: true,
            processors: [
              require('autoprefixer')({browsers: 'last 2 versions'}), 
            ]
          },
          dist: {
            src: 'assets/dist/frontend.min.css'
          }
        },

        uglify: {
          build: {
            options: {
              sourceMap: true,
              sourceMapIncludeSources: true
            },
            files: {
              'assets/dist/frontend.min.js': ['assets/js/frontend.js']
            }
          }
        },
    
        watch: {
          css: {
            files: 'assets/css/**/*.scss',
            tasks: ['sass']
          },
          js: {
            files: 'assets/js/**/*.js',
            tasks: ['uglify']
          }
        }
      });

    grunt.task.registerTask('default', [
      'sass',
      'postcss',
      'uglify'
    ]);
};