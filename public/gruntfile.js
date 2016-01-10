module.exports = function(grunt) {
  grunt.initConfig({
    emberTemplates: {
       'default': {
          options: {
             templateCompilerPath: 'libs/3-ember-template-compiler.js',
             handlebarsPath: 'libs/2-handlebars.js',
             templateNamespace: 'Handlebars',
             templateFileExtensions: /\.hbs/,
             templateBasePath: /app\/views\//
          },
          files: {
            "app/views/templates.js": "app/views/**/*.hbs"
          }
        }
    },
    watch: {
        emberTemplates: {
          files: 'app/views/**/*.hbs',
          tasks: ['emberTemplates']
        },
    }
  });
  
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-ember-templates');

  grunt.registerTask('default',['emberTemplates','watch']);
};