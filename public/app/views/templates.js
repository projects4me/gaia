Ember.TEMPLATES["app"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "fragmentReason": {
        "name": "missing-wrapper",
        "problems": [
          "multiple-nodes"
        ]
      },
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 184,
          "column": 6
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("header");
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("nav");
      dom.setAttribute(el2,"class","navbar");
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","container-fluid");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","navbar-header");
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("button");
      dom.setAttribute(el5,"type","button");
      dom.setAttribute(el5,"class","navbar-toggle collapsed");
      dom.setAttribute(el5,"data-toggle","collapse");
      dom.setAttribute(el5,"data-target","#navbar");
      dom.setAttribute(el5,"aria-expanded","false");
      dom.setAttribute(el5,"aria-controls","navbar");
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("span");
      dom.setAttribute(el6,"class","sr-only");
      var el7 = dom.createTextNode("Toggle navigation");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-bars");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("a");
      dom.setAttribute(el5,"class","navbar-brand");
      dom.setAttribute(el5,"href","#");
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("img");
      dom.setAttribute(el6,"alt","Projects4Me");
      dom.setAttribute(el6,"src","img/app/logo-256.png");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n            ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("                    \n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","collapse navbar-collapse");
      dom.setAttribute(el4,"id","navbar");
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("ul");
      dom.setAttribute(el5,"class","nav navbar-nav");
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","active");
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-dashboard");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Dashboard");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","sr-only");
      var el9 = dom.createTextNode("(current)");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-briefcase");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Projects");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Project");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#/projects");
      var el10 = dom.createTextNode("List Projects");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Projects");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Projects");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-tasks");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Tasks");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Task");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Tasks");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Tasks");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Tasks");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-comments");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Chats");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-puzzle-piece");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Puzzle");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Puzzle");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Puzzles");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Puzzles");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Puzzles");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-bar-chart");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Reports");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Report");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Reports");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-clock-o");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Time");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Time Activities");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Time Activities");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Time Activities");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-calendar");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Calendar");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("ul");
      dom.setAttribute(el5,"class","nav navbar-nav navbar-right hidden-xs");
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("img");
      dom.setAttribute(el8,"src","img/newpf.jpg");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Action");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Another action");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Something else here");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Separated link");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n            ");
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
      var el1 = dom.createTextNode("\n\n	\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("div");
      dom.setAttribute(el1,"class","wrapper");
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","sidebar-wrapper");
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","sidebar");
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","sidebar-actions");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","sidebar-action");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-search");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","sidebar-action");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("span");
      dom.setAttribute(el6,"class","badge");
      var el7 = dom.createTextNode("4");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-bell");
      dom.setAttribute(el6,"onclick","toggleSidebar()");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","sidebar-action");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("span");
      dom.setAttribute(el6,"class","badge");
      var el7 = dom.createTextNode("14");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-envelope");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","sidebar-clock");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("span");
      dom.setAttribute(el5,"id","clock");
      var el6 = dom.createElement("div");
      dom.setAttribute(el6,"id","clock-hour");
      var el7 = dom.createTextNode("00");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("div");
      dom.setAttribute(el6,"id","clock-minute");
      var el7 = dom.createTextNode("00");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("div");
      dom.setAttribute(el6,"id","clock-period");
      var el7 = dom.createTextNode("pm");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","sidebar-chat");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("i");
      dom.setAttribute(el5,"class","fa fa-ellipsis-h");
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("\n                                                ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("span");
      dom.setAttribute(el7,"class","badge");
      var el8 = dom.createTextNode("1");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("i");
      dom.setAttribute(el7,"class","fa fa-user");
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                                        ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("\n                                                ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("span");
      dom.setAttribute(el7,"class","badge");
      var el8 = dom.createTextNode("14");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("i");
      dom.setAttribute(el7,"class","fa fa-user");
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                                        ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("				\n                ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","sidebar-panel");
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","container-fluid mCustomScrollbar");
      dom.setAttribute(el3,"data-mcs-theme","dark");
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("h1");
      var el5 = dom.createElement("span");
      var el6 = dom.createTextNode("Lorem Ipsum");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("h4");
      var el5 = dom.createTextNode("\"Neque porro quisquam est qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit...\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("h5");
      var el5 = dom.createTextNode("\"There is no one who loves pain itself, who seeks after it and wants to have it, simply because it is pain...\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","lc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","what");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("What is Lorem Ipsum?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createElement("strong");
      var el8 = dom.createTextNode("Lorem Ipsum");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode(" is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","rc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","why");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("Why do we use it?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using 'Content here, content here', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for 'lorem ipsum' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"style","clear:both;");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","lc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","where");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where does it come from?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("The standard chunk of Lorem Ipsum used since the 1500s is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from \"de Finibus Bonorum et Malorum\" by Cicero are also reproduced in their exact original form, accompanied by English versions from the 1914 translation by H. Rackham.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","rc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","getsome");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where can I get some?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","lc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","what");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("What is Lorem Ipsum?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createElement("strong");
      var el8 = dom.createTextNode("Lorem Ipsum");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode(" is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","rc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","why");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("Why do we use it?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using 'Content here, content here', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for 'lorem ipsum' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"style","clear:both;");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","lc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","where");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where does it come from?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("The standard chunk of Lorem Ipsum used since the 1500s is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from \"de Finibus Bonorum et Malorum\" by Cicero are also reproduced in their exact original form, accompanied by English versions from the 1914 translation by H. Rackham.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","rc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","getsome");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where can I get some?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","contents-wrapper");
      var el3 = dom.createTextNode("\n            ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","contents container-fluid mCustomScrollbar");
      dom.setAttribute(el3,"data-mcs-theme","dark");
      var el4 = dom.createTextNode("\n                ");
      dom.appendChild(el3, el4);
      var el4 = dom.createComment("");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("div");
      dom.setAttribute(el1,"class","footer-wrapper");
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("footer");
      dom.setAttribute(el2,"class","footer");
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("span");
      dom.setAttribute(el3,"class","copyright");
      var el4 = dom.createElement("p");
      var el5 = dom.createTextNode("Copyright Â© Projects4Me Inc. - 2016. All rights reserved.");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("span");
      dom.setAttribute(el3,"class","footer-links");
      var el4 = dom.createElement("i");
      dom.setAttribute(el4,"class","fa fa-bug");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("a");
      dom.setAttribute(el4,"href","#");
      var el5 = dom.createTextNode("Report Issue");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("i");
      dom.setAttribute(el4,"class","fa fa-life-buoy");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("a");
      dom.setAttribute(el4,"href","#");
      var el5 = dom.createTextNode("Support");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(1);
      morphs[0] = dom.createMorphAt(dom.childAt(fragment, [2, 5, 1]),1,1);
      return morphs;
    },
    statements: [
      ["content","outlet",["loc",[null,[175,16],[175,26]]]]
    ],
    locals: [],
    templates: []
  };
}()));

Ember.TEMPLATES["app/create"] = Ember.Handlebars.template((function() {
  var child0 = (function() {
    return {
      meta: {
        "fragmentReason": false,
        "revision": "Ember@2.3.0",
        "loc": {
          "source": null,
          "start": {
            "line": 8,
            "column": 2
          },
          "end": {
            "line": 12,
            "column": 2
          }
        }
      },
      isEmpty: false,
      arity: 2,
      cachedFragment: null,
      hasRendered: false,
      buildFragment: function buildFragment(dom) {
        var el0 = dom.createDocumentFragment();
        var el1 = dom.createTextNode("    ");
        dom.appendChild(el0, el1);
        var el1 = dom.createElement("li");
        var el2 = dom.createTextNode("Hello sexy ");
        dom.appendChild(el1, el2);
        var el2 = dom.createComment("");
        dom.appendChild(el1, el2);
        var el2 = dom.createTextNode("!");
        dom.appendChild(el1, el2);
        dom.appendChild(el0, el1);
        var el1 = dom.createTextNode("\n\n    ");
        dom.appendChild(el0, el1);
        var el1 = dom.createComment("");
        dom.appendChild(el0, el1);
        var el1 = dom.createTextNode("\n");
        dom.appendChild(el0, el1);
        return el0;
      },
      buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
        var morphs = new Array(2);
        morphs[0] = dom.createMorphAt(dom.childAt(fragment, [1]),1,1);
        morphs[1] = dom.createMorphAt(fragment,3,3,contextualElement);
        return morphs;
      },
      statements: [
        ["content","definition.fieldName",["loc",[null,[9,19],[9,43]]]],
        ["inline","field-text",[],["definition",["subexpr","@mut",[["get","definition",["loc",[null,[11,28],[11,38]]]]],[],[]]],["loc",[null,[11,4],[11,40]]]]
      ],
      locals: ["field","definition"],
      templates: []
    };
  }());
  return {
    meta: {
      "fragmentReason": {
        "name": "missing-wrapper",
        "problems": [
          "multiple-nodes",
          "wrong-type"
        ]
      },
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 13,
          "column": 5
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("h1");
      var el2 = dom.createTextNode("Creating ");
      dom.appendChild(el1, el2);
      var el2 = dom.createComment("");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n\nGood Luck!\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("br");
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n\nField Labels:  \n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("ul");
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      var el2 = dom.createComment("");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(2);
      morphs[0] = dom.createMorphAt(dom.childAt(fragment, [0]),1,1);
      morphs[1] = dom.createMorphAt(dom.childAt(fragment, [4]),1,1);
      return morphs;
    },
    statements: [
      ["content","module",["loc",[null,[1,13],[1,23]]]],
      ["block","each-in",[["get","metaData",["loc",[null,[8,13],[8,21]]]]],[],0,null,["loc",[null,[8,2],[12,14]]]]
    ],
    locals: [],
    templates: [child0]
  };
}()));

Ember.TEMPLATES["app/detail"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "fragmentReason": false,
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 1,
          "column": 16
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("h1");
      var el2 = dom.createTextNode("Project");
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

Ember.TEMPLATES["app/module"] = Ember.Handlebars.template((function() {
  var child0 = (function() {
    return {
      meta: {
        "fragmentReason": false,
        "revision": "Ember@2.3.0",
        "loc": {
          "source": null,
          "start": {
            "line": 5,
            "column": 0
          },
          "end": {
            "line": 7,
            "column": 0
          }
        }
      },
      isEmpty: false,
      arity: 1,
      cachedFragment: null,
      hasRendered: false,
      buildFragment: function buildFragment(dom) {
        var el0 = dom.createDocumentFragment();
        var el1 = dom.createTextNode("      ");
        dom.appendChild(el0, el1);
        var el1 = dom.createElement("li");
        var el2 = dom.createComment("");
        dom.appendChild(el1, el2);
        var el2 = dom.createTextNode(" - ");
        dom.appendChild(el1, el2);
        var el2 = dom.createComment("");
        dom.appendChild(el1, el2);
        dom.appendChild(el0, el1);
        var el1 = dom.createTextNode("\n");
        dom.appendChild(el0, el1);
        return el0;
      },
      buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
        var element0 = dom.childAt(fragment, [1]);
        var morphs = new Array(2);
        morphs[0] = dom.createMorphAt(element0,0,0);
        morphs[1] = dom.createMorphAt(element0,2,2);
        return morphs;
      },
      statements: [
        ["content","project.name",["loc",[null,[6,10],[6,26]]]],
        ["content","project.notes",["loc",[null,[6,29],[6,46]]]]
      ],
      locals: ["project"],
      templates: []
    };
  }());
  return {
    meta: {
      "fragmentReason": {
        "name": "missing-wrapper",
        "problems": [
          "multiple-nodes",
          "wrong-type"
        ]
      },
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 8,
          "column": 5
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("h1");
      var el2 = dom.createTextNode("Projects");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("p");
      var el2 = dom.createTextNode("This is the project detail page.");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createComment("");
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode(" - ");
      dom.appendChild(el0, el1);
      var el1 = dom.createComment("");
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("ul");
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      var el2 = dom.createComment("");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(3);
      morphs[0] = dom.createMorphAt(fragment,4,4,contextualElement);
      morphs[1] = dom.createMorphAt(fragment,6,6,contextualElement);
      morphs[2] = dom.createMorphAt(dom.childAt(fragment, [8]),1,1);
      return morphs;
    },
    statements: [
      ["content","model.id",["loc",[null,[3,0],[3,12]]]],
      ["content","model.name",["loc",[null,[3,15],[3,29]]]],
      ["block","each",[["get","model",["loc",[null,[5,8],[5,13]]]]],[],0,null,["loc",[null,[5,0],[7,9]]]]
    ],
    locals: [],
    templates: [child0]
  };
}()));

Ember.TEMPLATES["app/projects"] = Ember.Handlebars.template((function() {
  var child0 = (function() {
    return {
      meta: {
        "fragmentReason": false,
        "revision": "Ember@2.3.0",
        "loc": {
          "source": null,
          "start": {
            "line": 5,
            "column": 0
          },
          "end": {
            "line": 7,
            "column": 0
          }
        }
      },
      isEmpty: false,
      arity: 1,
      cachedFragment: null,
      hasRendered: false,
      buildFragment: function buildFragment(dom) {
        var el0 = dom.createDocumentFragment();
        var el1 = dom.createTextNode("      ");
        dom.appendChild(el0, el1);
        var el1 = dom.createElement("li");
        var el2 = dom.createComment("");
        dom.appendChild(el1, el2);
        var el2 = dom.createTextNode(" - ");
        dom.appendChild(el1, el2);
        var el2 = dom.createComment("");
        dom.appendChild(el1, el2);
        dom.appendChild(el0, el1);
        var el1 = dom.createTextNode("\n");
        dom.appendChild(el0, el1);
        return el0;
      },
      buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
        var element0 = dom.childAt(fragment, [1]);
        var morphs = new Array(2);
        morphs[0] = dom.createMorphAt(element0,0,0);
        morphs[1] = dom.createMorphAt(element0,2,2);
        return morphs;
      },
      statements: [
        ["content","project.name",["loc",[null,[6,10],[6,26]]]],
        ["content","project.notes",["loc",[null,[6,29],[6,46]]]]
      ],
      locals: ["project"],
      templates: []
    };
  }());
  return {
    meta: {
      "fragmentReason": {
        "name": "missing-wrapper",
        "problems": [
          "multiple-nodes",
          "wrong-type"
        ]
      },
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 8,
          "column": 5
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("h1");
      var el2 = dom.createTextNode("Projects");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("p");
      var el2 = dom.createTextNode("This is the project detail page.");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createComment("");
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode(" - ");
      dom.appendChild(el0, el1);
      var el1 = dom.createComment("");
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("ul");
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      var el2 = dom.createComment("");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(3);
      morphs[0] = dom.createMorphAt(fragment,4,4,contextualElement);
      morphs[1] = dom.createMorphAt(fragment,6,6,contextualElement);
      morphs[2] = dom.createMorphAt(dom.childAt(fragment, [8]),1,1);
      return morphs;
    },
    statements: [
      ["content","model.id",["loc",[null,[3,0],[3,12]]]],
      ["content","model.name",["loc",[null,[3,15],[3,29]]]],
      ["block","each",[["get","model",["loc",[null,[5,8],[5,13]]]]],[],0,null,["loc",[null,[5,0],[7,9]]]]
    ],
    locals: [],
    templates: [child0]
  };
}()));

Ember.TEMPLATES["application"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "fragmentReason": {
        "name": "missing-wrapper",
        "problems": [
          "wrong-type"
        ]
      },
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 1,
          "column": 10
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createComment("");
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(1);
      morphs[0] = dom.createMorphAt(fragment,0,0,contextualElement);
      dom.insertBoundary(fragment, 0);
      dom.insertBoundary(fragment, null);
      return morphs;
    },
    statements: [
      ["content","outlet",["loc",[null,[1,0],[1,10]]]]
    ],
    locals: [],
    templates: []
  };
}()));

Ember.TEMPLATES["components/field-text"] = Ember.Handlebars.template((function() {
  var child0 = (function() {
    return {
      meta: {
        "fragmentReason": false,
        "revision": "Ember@2.3.0",
        "loc": {
          "source": null,
          "start": {
            "line": 2,
            "column": 39
          },
          "end": {
            "line": 2,
            "column": 97
          }
        }
      },
      isEmpty: false,
      arity: 0,
      cachedFragment: null,
      hasRendered: false,
      buildFragment: function buildFragment(dom) {
        var el0 = dom.createDocumentFragment();
        var el1 = dom.createElement("span");
        dom.setAttribute(el1,"class","required");
        var el2 = dom.createTextNode("*");
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
  }());
  return {
    meta: {
      "fragmentReason": false,
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 5,
          "column": 6
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("div");
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("label");
      var el3 = dom.createComment("");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createComment("");
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("span");
      dom.setAttribute(el2,"class","error");
      var el3 = dom.createComment("");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("span");
      var el3 = dom.createTextNode("value");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var element0 = dom.childAt(fragment, [0]);
      var morphs = new Array(3);
      morphs[0] = dom.createMorphAt(dom.childAt(element0, [1]),0,0);
      morphs[1] = dom.createMorphAt(element0,2,2);
      morphs[2] = dom.createMorphAt(dom.childAt(element0, [4]),0,0);
      return morphs;
    },
    statements: [
      ["content","definition.label",["loc",[null,[2,11],[2,31]]]],
      ["block","if",[["get","definition.required",["loc",[null,[2,45],[2,64]]]]],[],0,null,["loc",[null,[2,39],[2,104]]]],
      ["content","definition.errorMsg",["loc",[null,[3,24],[3,47]]]]
    ],
    locals: [],
    templates: [child0]
  };
}()));

Ember.TEMPLATES["index/index"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "fragmentReason": false,
      "revision": "Ember@2.3.0",
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
    isEmpty: false,
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

Ember.TEMPLATES["layouts/blank"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "fragmentReason": {
        "name": "missing-wrapper",
        "problems": [
          "wrong-type"
        ]
      },
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 1,
          "column": 9
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createComment("");
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(1);
      morphs[0] = dom.createMorphAt(fragment,0,0,contextualElement);
      dom.insertBoundary(fragment, 0);
      dom.insertBoundary(fragment, null);
      return morphs;
    },
    statements: [
      ["content","yield",["loc",[null,[1,0],[1,9]]]]
    ],
    locals: [],
    templates: []
  };
}()));

Ember.TEMPLATES["layouts/default"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "fragmentReason": {
        "name": "missing-wrapper",
        "problems": [
          "multiple-nodes"
        ]
      },
      "revision": "Ember@2.3.0",
      "loc": {
        "source": null,
        "start": {
          "line": 1,
          "column": 0
        },
        "end": {
          "line": 184,
          "column": 6
        }
      }
    },
    isEmpty: false,
    arity: 0,
    cachedFragment: null,
    hasRendered: false,
    buildFragment: function buildFragment(dom) {
      var el0 = dom.createDocumentFragment();
      var el1 = dom.createElement("header");
      var el2 = dom.createTextNode("\n    ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("nav");
      dom.setAttribute(el2,"class","navbar");
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","container-fluid");
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","navbar-header");
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("button");
      dom.setAttribute(el5,"type","button");
      dom.setAttribute(el5,"class","navbar-toggle collapsed");
      dom.setAttribute(el5,"data-toggle","collapse");
      dom.setAttribute(el5,"data-target","#navbar");
      dom.setAttribute(el5,"aria-expanded","false");
      dom.setAttribute(el5,"aria-controls","navbar");
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("span");
      dom.setAttribute(el6,"class","sr-only");
      var el7 = dom.createTextNode("Toggle navigation");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-bars");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("a");
      dom.setAttribute(el5,"class","navbar-brand");
      dom.setAttribute(el5,"href","#");
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("img");
      dom.setAttribute(el6,"alt","Projects4Me");
      dom.setAttribute(el6,"src","img/app/logo-256.png");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n            ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("                    \n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","collapse navbar-collapse");
      dom.setAttribute(el4,"id","navbar");
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("ul");
      dom.setAttribute(el5,"class","nav navbar-nav");
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","active");
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-dashboard");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Dashboard");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","sr-only");
      var el9 = dom.createTextNode("(current)");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-briefcase");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Projects");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Project");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#/projects");
      var el10 = dom.createTextNode("List Projects");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Projects");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Projects");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-tasks");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Tasks");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Task");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Tasks");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Tasks");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Tasks");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-comments");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Chats");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-puzzle-piece");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Puzzle");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Puzzle");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Puzzles");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Puzzles");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Puzzles");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-bar-chart");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Reports");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Create Report");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Reports");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-clock-o");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Time");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("List Time Activities");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Import Time Activities");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Export Time Activities");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      var el8 = dom.createElement("i");
      dom.setAttribute(el8,"class","fa fa-calendar");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("p");
      var el9 = dom.createTextNode("Calendar");
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("ul");
      dom.setAttribute(el5,"class","nav navbar-nav navbar-right hidden-xs");
      var el6 = dom.createTextNode("\n                    ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("li");
      dom.setAttribute(el6,"class","dropdown");
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("a");
      dom.setAttribute(el7,"href","#");
      dom.setAttribute(el7,"class","dropdown-toggle");
      dom.setAttribute(el7,"data-toggle","dropdown");
      dom.setAttribute(el7,"role","button");
      dom.setAttribute(el7,"aria-haspopup","true");
      dom.setAttribute(el7,"aria-expanded","false");
      var el8 = dom.createElement("img");
      dom.setAttribute(el8,"src","img/newpf.jpg");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("span");
      dom.setAttribute(el8,"class","caret");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                        ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("ul");
      dom.setAttribute(el7,"class","dropdown-menu");
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Action");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Another action");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Something else here");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      dom.setAttribute(el8,"role","separator");
      dom.setAttribute(el8,"class","divider");
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                            ");
      dom.appendChild(el7, el8);
      var el8 = dom.createElement("li");
      var el9 = dom.createElement("a");
      dom.setAttribute(el9,"href","#");
      var el10 = dom.createTextNode("Separated link");
      dom.appendChild(el9, el10);
      dom.appendChild(el8, el9);
      dom.appendChild(el7, el8);
      var el8 = dom.createTextNode("\n                        ");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                    ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n            ");
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
      var el1 = dom.createTextNode("\n\n	\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("div");
      dom.setAttribute(el1,"class","wrapper");
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","sidebar-wrapper");
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","sidebar");
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","sidebar-actions");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","sidebar-action");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-search");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","sidebar-action");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("span");
      dom.setAttribute(el6,"class","badge");
      var el7 = dom.createTextNode("4");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-bell");
      dom.setAttribute(el6,"onclick","toggleSidebar()");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","sidebar-action");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("span");
      dom.setAttribute(el6,"class","badge");
      var el7 = dom.createTextNode("14");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("i");
      dom.setAttribute(el6,"class","fa fa-envelope");
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","sidebar-clock");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("span");
      dom.setAttribute(el5,"id","clock");
      var el6 = dom.createElement("div");
      dom.setAttribute(el6,"id","clock-hour");
      var el7 = dom.createTextNode("00");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("div");
      dom.setAttribute(el6,"id","clock-minute");
      var el7 = dom.createTextNode("00");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("div");
      dom.setAttribute(el6,"id","clock-period");
      var el7 = dom.createTextNode("pm");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","sidebar-chat");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("i");
      dom.setAttribute(el5,"class","fa fa-ellipsis-h");
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("\n                                                ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("span");
      dom.setAttribute(el7,"class","badge");
      var el8 = dom.createTextNode("1");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("i");
      dom.setAttribute(el7,"class","fa fa-user");
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                                        ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("\n                                                ");
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("span");
      dom.setAttribute(el7,"class","badge");
      var el8 = dom.createTextNode("14");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createElement("i");
      dom.setAttribute(el7,"class","fa fa-user");
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode("\n                                        ");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("				\n                ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","sidebar-panel");
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","container-fluid mCustomScrollbar");
      dom.setAttribute(el3,"data-mcs-theme","dark");
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("h1");
      var el5 = dom.createElement("span");
      var el6 = dom.createTextNode("Lorem Ipsum");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("h4");
      var el5 = dom.createTextNode("\"Neque porro quisquam est qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit...\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("h5");
      var el5 = dom.createTextNode("\"There is no one who loves pain itself, who seeks after it and wants to have it, simply because it is pain...\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","lc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","what");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("What is Lorem Ipsum?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createElement("strong");
      var el8 = dom.createTextNode("Lorem Ipsum");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode(" is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","rc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","why");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("Why do we use it?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using 'Content here, content here', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for 'lorem ipsum' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"style","clear:both;");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","lc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","where");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where does it come from?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("The standard chunk of Lorem Ipsum used since the 1500s is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from \"de Finibus Bonorum et Malorum\" by Cicero are also reproduced in their exact original form, accompanied by English versions from the 1914 translation by H. Rackham.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","rc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","getsome");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where can I get some?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","lc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","what");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("What is Lorem Ipsum?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createElement("strong");
      var el8 = dom.createTextNode("Lorem Ipsum");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      var el7 = dom.createTextNode(" is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("div");
      dom.setAttribute(el5,"class","rc");
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("h2");
      dom.setAttribute(el6,"class","why");
      var el7 = dom.createElement("span");
      var el8 = dom.createTextNode("Why do we use it?");
      dom.appendChild(el7, el8);
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                        ");
      dom.appendChild(el5, el6);
      var el6 = dom.createElement("p");
      var el7 = dom.createTextNode("It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using 'Content here, content here', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for 'lorem ipsum' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      var el6 = dom.createTextNode("\n                                ");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"style","clear:both;");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","lc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","where");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where does it come from?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("The standard chunk of Lorem Ipsum used since the 1500s is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from \"de Finibus Bonorum et Malorum\" by Cicero are also reproduced in their exact original form, accompanied by English versions from the 1914 translation by H. Rackham.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                        ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","rc");
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("h2");
      dom.setAttribute(el5,"class","getsome");
      var el6 = dom.createElement("span");
      var el7 = dom.createTextNode("Where can I get some?");
      dom.appendChild(el6, el7);
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                                ");
      dom.appendChild(el4, el5);
      var el5 = dom.createElement("p");
      var el6 = dom.createTextNode("There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc.");
      dom.appendChild(el5, el6);
      dom.appendChild(el4, el5);
      var el5 = dom.createTextNode("\n                        ");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n                ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("div");
      dom.setAttribute(el2,"class","contents-wrapper");
      var el3 = dom.createTextNode("\n            ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("div");
      dom.setAttribute(el3,"class","contents container-fluid mCustomScrollbar");
      dom.setAttribute(el3,"data-mcs-theme","dark");
      var el4 = dom.createTextNode("\n                ");
      dom.appendChild(el3, el4);
      var el4 = dom.createComment("");
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      var el1 = dom.createTextNode("\n");
      dom.appendChild(el0, el1);
      var el1 = dom.createElement("div");
      dom.setAttribute(el1,"class","footer-wrapper");
      var el2 = dom.createTextNode("\n        ");
      dom.appendChild(el1, el2);
      var el2 = dom.createElement("footer");
      dom.setAttribute(el2,"class","footer");
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("span");
      dom.setAttribute(el3,"class","copyright");
      var el4 = dom.createElement("p");
      var el5 = dom.createTextNode("Copyright Â© Projects4Me Inc. - 2016. All rights reserved.");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n                ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("span");
      dom.setAttribute(el3,"class","footer-links");
      var el4 = dom.createElement("i");
      dom.setAttribute(el4,"class","fa fa-bug");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("a");
      dom.setAttribute(el4,"href","#");
      var el5 = dom.createTextNode("Report Issue");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("i");
      dom.setAttribute(el4,"class","fa fa-life-buoy");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("a");
      dom.setAttribute(el4,"href","#");
      var el5 = dom.createTextNode("Support");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      dom.appendChild(el1, el2);
      var el2 = dom.createTextNode("\n");
      dom.appendChild(el1, el2);
      dom.appendChild(el0, el1);
      return el0;
    },
    buildRenderNodes: function buildRenderNodes(dom, fragment, contextualElement) {
      var morphs = new Array(1);
      morphs[0] = dom.createMorphAt(dom.childAt(fragment, [2, 5, 1]),1,1);
      return morphs;
    },
    statements: [
      ["content","yield",["loc",[null,[175,16],[175,25]]]]
    ],
    locals: [],
    templates: []
  };
}()));

Ember.TEMPLATES["signin/index"] = Ember.Handlebars.template((function() {
  return {
    meta: {
      "fragmentReason": {
        "name": "triple-curlies"
      },
      "revision": "Ember@2.3.0",
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
    isEmpty: false,
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
      var el4 = dom.createTextNode("\"view.signin.username\"");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n         ");
      dom.appendChild(el2, el3);
      var el3 = dom.createComment("");
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("label");
      dom.setAttribute(el3,"for","inputPassword");
      dom.setAttribute(el3,"class","sr-only");
      var el4 = dom.createTextNode("\"view.signin.password\"");
      dom.appendChild(el3, el4);
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createComment("");
      dom.appendChild(el2, el3);
      var el3 = dom.createTextNode("\n        ");
      dom.appendChild(el2, el3);
      var el3 = dom.createElement("button");
      dom.setAttribute(el3,"class","btn btn btn-primary btn-block");
      var el4 = dom.createElement("i");
      dom.setAttribute(el4,"class","fa fa-sign-in");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("span");
      dom.setAttribute(el4,"class","btn-txt");
      var el5 = dom.createTextNode("\"view.signin.sign-in\"");
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
      var el5 = dom.createTextNode(" \"view.signin.remember-me\"\n            ");
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
      var el5 = dom.createTextNode("\"view.signin.sign-up\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-6 text-right");
      var el5 = dom.createTextNode("\"view.signin.forgot-password\"");
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
      var el7 = dom.createTextNode("\"view.signin.sign-up-with-google\"");
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
      var el5 = dom.createTextNode("\"view.signin.terms\" \"static/termsandconditions\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-3 text-left");
      var el5 = dom.createTextNode("\"view.signin.privacy\" \"static/privacypolicy\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-3 text-left");
      var el5 = dom.createTextNode("\"view.signin.security\" \"static/securitypolicy\"");
      dom.appendChild(el4, el5);
      dom.appendChild(el3, el4);
      var el4 = dom.createTextNode("\n            ");
      dom.appendChild(el3, el4);
      var el4 = dom.createElement("div");
      dom.setAttribute(el4,"class","col-xs-3 text-left");
      var el5 = dom.createTextNode("\"view.signin.contact\" \"static/contactus\"");
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
      var element0 = dom.childAt(fragment, [0, 3]);
      var element1 = dom.childAt(element0, [9]);
      var morphs = new Array(3);
      morphs[0] = dom.createMorphAt(element0,3,3);
      morphs[1] = dom.createMorphAt(element0,7,7);
      morphs[2] = dom.createElementMorph(element1);
      return morphs;
    },
    statements: [
      ["inline","input",[],["type","text","value",["subexpr","@mut",[["get","username",["loc",[null,[9,35],[9,43]]]]],[],[]],"required",true,"autofocus",true,"placeholder","view.signin.username","class","form-control"],["loc",[null,[9,9],[9,130]]]],
      ["inline","input",[],["type","password","value",["subexpr","@mut",[["get","password",["loc",[null,[11,38],[11,46]]]]],[],[]],"required",true,"placeholder","view.signin.password","class","form-control"],["loc",[null,[11,8],[11,118]]]],
      ["element","action",["signin"],[],["loc",[null,[12,54],[12,73]]]]
    ],
    locals: [],
    templates: []
  };
}()));