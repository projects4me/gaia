### Project Code Name : Projects4Me
### Date Created : 19th March, 2015

Foundation is the base framework with the idea to create complex web application using gui.
Even in the most complex application the foundation of each component remains the same mostly what changes is the link of the one functionality with the other
Foundation is a framework built on top of Bootstrap, Emberjs and phalcon.

In any system there are the following basic components
* Data
* Business Logic
* View

MVC design pattern was created in order to solve this problem with the Models handling the data, Controllers handling the business logic and the Views controlling the UI
Although this is completely correct and this pattern allows creation of extremely extensible, robust and easily maintainable the problem is that most of the frameworks
only supports this design pattern in one programing language, in Web-Application being created in the this era have partial business logic and the complete view layer
in the browsers. At the moment there are many excellent frameworks in JavaScript, CSS, PHP and Database layers but what is missing is a framework that allows users to
control all the aspects of the application via an interface.

One application that kind of comes close to this concept is Apigility which allows the creation of API via an interface created in JavaScript, SugarCRM 7 is also an application
that comes close to this concept.

### The Goal
 Foundation should let you create a medium level application in one hours.
### Target Application
 [Projects 4 Me](http://www.projects4.me/)
### Type
 PJC - PHP, Javascript, CSS

#### Considerations
1. Speed - There should be no comprise in the speed on the application, this might seem a bit too ambitious however if you implement the system correctly it is possible
2. Scalability - A base framework that allows creation of complex is a failure if it does not allow extension easily, inspiration  CakePHP, Zend, SugarCRM and Maya

## Frameworks 

#### Chosen
1. Bootstrap - By distributing a page in columns bootstrap give the best structure to provide pragmatically generating dynamic view
2. Emberjs - With extensive control on the navigation and the pages and being served in the browsers a front end is required to control the views
3. Phalcon - Being the fastest PHP framework being a C extension and loosely coupled phalcon is the best framework for this application
4. Grunt - The build procedure will be written through grunt
5. Less - For extensive CSS styling 
6. Selenium - To be used for automated test cases
7. PHP Docs - For PHP documentation Generation
8. JS Docs - For JavaScript documentation generation

#### Under consideration
1. Doctrine - I would like to have support most of the major relational and document NoSQL database
2. Jenkins - To be used for build management


