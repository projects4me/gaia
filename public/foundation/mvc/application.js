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
    headers: {
        "Authorization": "Bearer 5b0d6ba8cc54dce2eedb9a900c02b4c756343afb"
    },
    pathForType: function(modelName) {
        return Ember.String.capitalize(modelName);
    }
});
var hammad2;
Foundation.ApplicationSerializer = DS.RESTSerializer.extend({
    primaryKey: 'id',
    modelNameFromPayloadKey :function(modelName){
        hammad2 = this;
        return modelName;
    }
});

Foundation.ApplicationRoute = Em.Route.extend({
  beforeModel: function(){
    var lang = 'en_US';
    var route = this;
    if(lang){
      return new Ember.RSVP.Promise(function(resolve) {
        // Fetch language file
        Em.$.getJSON('app/locale/'+lang+".json").then(function(data){
          Em.I18n.translations = data;
          route.transitionTo('signin');
          resolve();
        });
      });
    }else{
      this.transitionTo('lang');
    }
  }
});
var hammad;



Foundation.ProjectsRoute = Em.Route.extend({
    model: function() {
        hammad = this.store.findAll('Projects','1');
        console.log(hammad);
        return hammad;
    }
});

Foundation.oAuth = {
    client_id : Foundation.CLIENT_ID,
    client_secret : Foundation.CLIENT_SECRET,
    auth_token : '',
    refresh_token : '',
    expiry : '',
    authorize:function(username,password){
        
    }
};

