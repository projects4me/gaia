App = Ember.Application.create({
    LOG_TRANSITIONS:true
});

App.Router.map(function() {
    this.resource('index', { path: '/' }, function() {});
    this.resource('login', { path: '/login' }, function() {});
});


