Ember.Handlebars.helper('link', function(display, link) {
	var url = Handlebars.Utils.escapeExpression(link);
	return new Handlebars.SafeString("<a href='/#/" + url + "'>" + display + "</a>");
});
