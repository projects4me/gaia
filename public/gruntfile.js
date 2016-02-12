module.exports = function(grunt) {
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-ember-templates');
    grunt.loadNpmTasks('grunt-contrib-compass');
    grunt.initConfig({
        emberTemplates: {
           'default': {
              options: {
                 templateCompilerPath: 'libs/4-ember-template-compiler.js',
                 handlebarsPath: 'libs/3-handlebars.js',
                 templateNamespace: 'Handlebars',
                 templateFileExtensions: /\.hbs/,
                 templateBasePath: /app\/views\//
              },
              files: {
                "app/views/templates.js": "app/views/**/*.hbs"
              }
            }
        },
        compass: {
          dist: {
            options: {
              sassDir: 'themes/default',
              cssDir: 'css',
              environment: 'production',
            },
          },
        },
        watch: {
            emberTemplates: {
              files: 'app/views/**/*.hbs',
              tasks: ['emberTemplates']
            },
            saas: {
                files: ['themes/default/*.scss'],
                tasks: ['compass']
            }
        }
    });
    grunt.registerTask('default',['emberTemplates','watch']);
};