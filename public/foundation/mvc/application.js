/* 
 * Projects4Me Community Edition is an open source project management software 
 * developed by PROJECTS4ME Inc. Copyright (C) 2015-2016 PROJECTS4ME Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 (GNU AGPL v3) as
 * published be the Free Software Foundation with the addition of the following 
 * permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF 
 * THE COVERED WORK IN WHICH THE COPYRIGHT IS OWNED BY PROJECTS4ME Inc., 
 * Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.
 * 
 * You should have received a copy of the GNU AGPL v3 along with this program; 
 * if not, see http://www.gnu.org/licenses or write to the Free Software 
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * You can contact PROJECTS4ME, Inc. at email address contact@projects4.me.
 * 
 * The interactive user interfaces in modified source and object code versions 
 * of this program must display Appropriate Legal Notices, as required under 
 * Section 5 of the GNU AGPL v3.
 * 
 * In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal 
 * Notices must retain the display of the "Powered by Projects4Me" logo. If the 
 * display of the logo is not reasonably feasible for technical reasons, the 
 * Appropriate Legal Notices must display the words "Powered by Projects4Me".
 */

Foundation = Ember.Application.create({
    LOG_TRANSITIONS:true,
    VERSION:'1',
    CLIENT_ID:'projects4me',
    CLIENT_SECRET:'06110fb83488715ca69057f4a7cedf93'
});

    
Foundation.ApplicationStore = DS.Store.extend({
    //adapter translating requested records into the appropriate calls
    adapter: 'DS.RESTAdapter',
//    serializer: Foundation.RESTSerializer
 });
 
Foundation.ApplicationAdapter = DS.RESTAdapter.extend({
    namespace:'api/v'+Foundation.VERSION,
    headers: Ember.computed(function() {
        return {
            "Authorization": "Bearer "+Foundation.oAuth.get('access_token')
        };
    }).volatile(),

    pathForType: function(modelName) {
        return Ember.String.capitalize(modelName);
    },
    normalizeErrorResponse: function (status, headers, payload) {
        if (status == 401)
        {
            Foundation.oAuth.clear();
            Foundation.oAuth.route.transitionTo('signin');
            return false;
        }
    },
});

Foundation.ApplicationSerializer = DS.RESTSerializer.extend({
    primaryKey: 'id',
    modelNameFromPayloadKey :function(modelName){
        return modelName;
    }
});


Foundation.ApplicationRoute = Em.Route.extend({
    auth :  null,
    setupController: function(controller, model) {
        var isAuthenticated = Foundation.oAuth.isAuthenticated();
        controller.set('isAuthenticated', isAuthenticated);
    },
    beforeModel: function(){
        var lang = 'en_US';
        var route = this;
        this.auth = Foundation.oAuth.initialize(this);        
        var isAuthenticated = Foundation.oAuth.isAuthenticated();
        if (!isAuthenticated)
            route.transitionTo('signin');
        if(lang){
            return new Ember.RSVP.Promise(function(resolve) {
                // Fetch language file
                Em.$.getJSON('app/locale/'+lang+".json").then(function(data){
                    //Em.I18n.translations = data;
                    resolve();
                });
            });
        }else{
      this.transitionTo('lang');
    }
    }
});
/*
Ember.View.reopen({
    layoutName:Ember.computed(function() {
        console.log('--------------------------');
        console.log(this.authenticate);
        console.log(this.get('controller'));
        console.log('--------------------------');
        return "";
    }).volatile(),
    didInsertElement : function(){
    this._super();
    $('.mCustomScrollbar').mCustomScrollbar();
    Ember.run.scheduleOnce('afterRender', this, this.afterRenderEvent);
  },
  afterRenderEvent : function(){
    // implement this hook in your own subclasses and run your jQuery logic there
  }
});*/
/*
Foundation.defaultView = Em.View.extend({
    layoutName:'layouts/default',
});

Foundation.ApplicationView = Em.View.extend({
//    layoutName:'layouts/default'
});
/**/



Foundation.SigninIndexRoute = Em.Route.extend({ 
    /**
     * if a rest returns unauthorize then use refresk the token
     * or take the user to the authentication page
     * @todo on error clear the console
     * @returns {undefined}
     */
   beforeModel:function(){
       if (Foundation.oAuth.isAuthenticated())
            this.transitionTo('projects');
   } 
});

Foundation.SigninIndexController = Ember.Controller.extend({
    authenticate:false,
});

Foundation.ProjectsRoute = Em.Route.extend({
    model: function() {
        return this.store.findAll('Projects','1');
    }
});
/*
Foundation.IndexView = Foundation.defaultView.extend();
*/
Foundation.ProjectsIndexController = Ember.Controller.extend({authenticate:true});
/**/
/**
 * @todo log errors
 * @todo cater history
 */
var hammad = null;
Foundation.oAuth = {
    client_id : Foundation.CLIENT_ID,
    client_secret : Foundation.CLIENT_SECRET,
    access_token : null,
    refresh_token : null,
    expires_in : null,
    route:null,
    initialize:function(route){
        this.route = route;
        return this;
    },
    authorize:function(username,password){
        Token = this.route.store.createRecord('Token',{
            username:username,
            password:password,
            grant_type:'password',
            client_id:Foundation.CLIENT_ID,
            client_secret:Foundation.CLIENT_SECRET
        });
        Token.save().then(function(Token){
            this.access_token = Token.get('access_token');
            this.refresh_token = Token.get('refresh_token');
            this.expires_in = Token.get('expires_in');
            Ember.$.cookie('access_token',this.access_token);
            Ember.$.cookie('refresh_token',this.refresh_token);
            Ember.$.cookie('expires_in',this.expires_in);
            Foundation.oAuth.route.transitionTo('projects');
        },function(){
            return false;
        });
    },
    isAuthenticated:function(){
        if (Ember.$.cookie('access_token') != undefined && Ember.$.cookie('access_token') != '')
            return true;
        else
            return false;
    },
    get:function(param){
        if (Ember.$.cookie(param) != undefined || Ember.$.cookie(param) != '')
        {
            return Ember.$.cookie(param);
        }
        else
        {
            return 'none'
        }
    },
    clear:function(){
        Ember.$.removeCookie('access_token');
        Ember.$.removeCookie('refresh_token');
        Ember.$.removeCookie('expires_in');
    }
};