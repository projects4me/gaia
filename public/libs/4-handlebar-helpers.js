Ember.Handlebars.helper('link', function(display, link) {
	var url = Handlebars.Utils.escapeExpression(link);
	return new Handlebars.SafeString("<a href='/#/" + url + "'>" + Em.I18n.t(display) + "</a>");
});


Ember.Handlebars.registerHelper('debug', function(the_string){
    Ember.Logger.log(the_string);
    // or simply
    console.log(the_string);
  });
