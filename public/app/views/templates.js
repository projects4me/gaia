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
          "line": 16,
          "column": 7
        }
      }
    },
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("form");
      dom.setAttribute(el1,"class","form-signin");
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("label");
      dom.setAttribute(el2,"for","inputUsername");
      dom.setAttribute(el2,"class","sr-only");
      var el3 = dom.createTextNode("Username");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("input");
      dom.setAttribute(el2,"type","text");
      dom.setAttribute(el2,"id","inputUsername");
      dom.setAttribute(el2,"class","form-control");
      dom.setAttribute(el2,"placeholder","Username");
      dom.setAttribute(el2,"required","");
      dom.setAttribute(el2,"autofocus","");
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("label");
      dom.setAttribute(el2,"for","inputPassword");
      dom.setAttribute(el2,"class","sr-only");
      var el3 = dom.createTextNode("Password");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("input");
      dom.setAttribute(el2,"type","password");
      dom.setAttribute(el2,"id","inputPassword");
      dom.setAttribute(el2,"class","form-control");
      dom.setAttribute(el2,"placeholder","Password");
      dom.setAttribute(el2,"required","");
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("button");
      dom.setAttribute(el2,"class","btn btn btn-primary btn-block");
      dom.setAttribute(el2,"type","submit");
      var el3 = dom.createElement("i");
      dom.setAttribute(el3,"class","fa fa-sign-in");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("span");
      dom.setAttribute(el3,"class","btn-txt");
      var el4 = dom.createTextNode("Sign In");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","checkbox");
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("label");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("input");
      dom.setAttribute(el4,"type","checkbox");
      dom.setAttribute(el4,"value","remember-me");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode(" Remember me?\n        ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n    ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","row");
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","col-md-6 text-left");
      var el4 = dom.createComment("");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","col-md-6 text-right");
      var el4 = dom.createComment("");
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
      var element0 = dom.childAt(fragment, [0, 13]);
      var morphs = new Array(2);
      morphs[0] = dom.createMorphAt(dom.childAt(element0, [1]),0,0);
      morphs[1] = dom.createMorphAt(dom.childAt(element0, [3]),0,0);
      return morphs;
    },
    statements: [
      ["inline","link",["Sing Up","signup"],[],["loc",[null,[13,40],[13,67]]]],
      ["inline","link",["Forgot Password?","forgotpassword"],[],["loc",[null,[14,41],[14,85]]]]
    ],
    locals: [],
    templates: []
  };
}()));