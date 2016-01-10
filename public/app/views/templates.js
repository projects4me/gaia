Ember.TEMPLATES["application"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "revision": "Ember@1.13.11",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 3,
          "column": 6
        }
      }
    },
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("div");
      dom.setAttribute(el1,"class","container");
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createComment("");
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(1);
      morphs[0] = dom.createMorphAt(dom.childAt(fragment, [0]),1,1);
      return morphs;
    },
    statements: [
      ["content","outlet",["loc",[null,[2,4],[2,14]]]]
    ],
    locals: [],
    templates: []
  };
}()));

Ember.TEMPLATES["index/index"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "revision": "Ember@1.13.11",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 1,
          "column": 22
        }
      }
    },
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("h1");
      var el2 = dom.createTextNode("Projects 4 Me");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes() { return []; },
    statements: [

    ],
    locals: [],
    templates: []
  };
}()));

Ember.TEMPLATES["signin/index"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "revision": "Ember@1.13.11",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 37,
          "column": 6
        }
      }
    },
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("div");
      dom.setAttribute(el1,"class","view-signin");
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","logo-img text-center");
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("a");
      dom.setAttribute(el3,"href","//projects4.me");
      dom.setAttribute(el3,"title","Projects4Me");
      dom.setAttribute(el3,"id","logo");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("img");
      dom.setAttribute(el4,"src","/img/app/logo-256.png");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n        ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n    ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("form");
      dom.setAttribute(el2,"class","form-signin");
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("label");
      dom.setAttribute(el3,"for","inputUsername");
      dom.setAttribute(el3,"class","sr-only");
      var el4 = dom.createComment("");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("input");
      dom.setAttribute(el3,"type","text");
      dom.setAttribute(el3,"id","inputUsername");
      dom.setAttribute(el3,"class","form-control");
      dom.setAttribute(el3,"required","");
      dom.setAttribute(el3,"autofocus","");
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("label");
      dom.setAttribute(el3,"for","inputPassword");
      dom.setAttribute(el3,"class","sr-only");
      var el4 = dom.createComment("");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("input");
      dom.setAttribute(el3,"type","password");
      dom.setAttribute(el3,"id","inputPassword");
      dom.setAttribute(el3,"class","form-control");
      dom.setAttribute(el3,"required","");
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("button");
      dom.setAttribute(el3,"class","btn btn btn-primary btn-block");
      dom.setAttribute(el3,"type","submit");
      var el4 = dom.createElement("i");
      dom.setAttribute(el4,"class","fa fa-sign-in");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("span");
      dom.setAttribute(el4,"class","btn-txt");
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","checkbox");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("label");
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("input");
      dom.setAttribute(el5,"type","checkbox");
      dom.setAttribute(el5,"value","remember-me");
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode(" ");
      dom.appendChild(el4, el5);
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n            ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n        ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","row");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-6 text-left");
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-6 text-right");
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n        ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n    ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","additional-links");
      var el3 = dom.createTextNode("\n\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","row");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-12 text-center");
      var el5 = dom.createElement("button");
      dom.setAttribute(el5,"class","btn btn-danger btn-block");
      dom.setAttribute(el5,"type","submit");
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-google-plus");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("span");
      dom.setAttribute(el6,"class","btn-txt");
      var el7 = dom.createComment("");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n        ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n    \n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","row");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-3 text-left");
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-3 text-left");
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-3 text-left");
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-3 text-left");
      var el5 = dom.createComment("");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n        ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n    ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var element0 = dom.childAt(fragment, [0]);
      var element1 = dom.childAt(element0, [3]);
      var element2 = dom.childAt(element1, [3]);
      var element3 = dom.childAt(element1, [7]);
      var element4 = dom.childAt(element1, [13]);
      var element5 = dom.childAt(element0, [5]);
      var element6 = dom.childAt(element5, [3]);
      var morphs = new Array(13);
      morphs[0] = dom.createMorphAt(dom.childAt(element1, [1]),0,0);
      morphs[1] = dom.createAttrMorph(element2, 'placeholder');
      morphs[2] = dom.createMorphAt(dom.childAt(element1, [5]),0,0);
      morphs[3] = dom.createAttrMorph(element3, 'placeholder');
      morphs[4] = dom.createMorphAt(dom.childAt(element1, [9, 1]),0,0);
      morphs[5] = dom.createMorphAt(dom.childAt(element1, [11, 1]),3,3);
      morphs[6] = dom.createMorphAt(dom.childAt(element4, [1]),0,0);
      morphs[7] = dom.createMorphAt(dom.childAt(element4, [3]),0,0);
      morphs[8] = dom.createMorphAt(dom.childAt(element5, [1, 1, 0, 1]),0,0);
      morphs[9] = dom.createMorphAt(dom.childAt(element6, [1]),0,0);
      morphs[10] = dom.createMorphAt(dom.childAt(element6, [3]),0,0);
      morphs[11] = dom.createMorphAt(dom.childAt(element6, [5]),0,0);
      morphs[12] = dom.createMorphAt(dom.childAt(element6, [7]),0,0);
      return morphs;
    },
    statements: [
      ["inline","t",["view.signin.username"],[],["loc",[null,[8,51],[8,79]]]],
      ["attribute","placeholder",["concat",[["subexpr","t",["view.signin.username"],[],["loc",[null,[9,80],[9,108]]]]]]],
      ["inline","t",["view.signin.password"],[],["loc",[null,[10,51],[10,79]]]],
      ["attribute","placeholder",["concat",[["subexpr","t",["view.signin.password"],[],["loc",[null,[11,84],[11,112]]]]]]],
      ["inline","t",["view.signin.sign-in"],[],["loc",[null,[12,119],[12,146]]]],
      ["inline","t",["view.signin.remember-me"],[],["loc",[null,[15,60],[15,91]]]],
      ["inline","link",["view.signin.sign-up","signup"],[],["loc",[null,[19,44],[19,83]]]],
      ["inline","link",["view.signin.forgot-password","forgotpassword"],[],["loc",[null,[20,45],[20,100]]]],
      ["inline","t",["view.signin.sign-up-with-google"],[],["loc",[null,[27,157],[27,196]]]],
      ["inline","link",["view.signin.terms","static/termsandconditions"],[],["loc",[null,[31,44],[31,100]]]],
      ["inline","link",["view.signin.privacy","static/privacypolicy"],[],["loc",[null,[32,44],[32,97]]]],
      ["inline","link",["view.signin.security","static/securitypolicy"],[],["loc",[null,[33,44],[33,99]]]],
      ["inline","link",["view.signin.contact","static/contactus"],[],["loc",[null,[34,44],[34,93]]]]
    ],
    locals: [],
    templates: []
  };
}()));