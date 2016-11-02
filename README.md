phpBitsTheater
==============

Highly customizable PHP mini-framework extending the MVC site model. Terms used borrow heavily from theater.

Minimum PHP version required is 5.4 (due to using the (new ClassName())->blah() feature of 5.4 in some places).

This mini-framework extends the Model-View-Controller site model:

<h2>Actor = Controller</h2>
    Business logic for your Views. Much of the behind the scenes work is handled here. Each public method an Actor
    defines is an Action that may be called from the URL. Actions are auto-mapped to the URL so merely defining
    a "public function bar()" inside Actor "foo" results in a URL of "http://mydomain.com/foo/bar/" executing
    said function.

<h2>Models</h2>
    Models are logical representations of data stored somewhere (usually in a local database).
    Models and controllers are not 1 to 1, a controller (Actor) may not have any model associated with it and 
    models can, and frequently do, have more than one model open at a time.
    
<h2>Views</h2>
    Standard HTML web pages. Most Actor actions will try to render a view with the same name as the Action itself.
    Views are stored inside the view subfolder as "app/view/Actor/action.php" and will automatically be rendered
    unless the Action returns a URL to redirect to instead.
    
<h2>Scenes</h2>
    An extension upon the MVC framework in that a Scene lives between an Actor and its View. A scene will define
    all the data that is to be shared between the two. A generic Scene class is all that is required, but for 
    complex work, you may wish to descend from it by giving it the same name as the Actor and cause that one to
    be auto-loaded instead. You can provide Actor-specific features this way.  
      An Actor references its Scene by using "$this->scene->some_var" and the View will reference the same 
    variable as either "$recite->some_var" or "$v->some_var".  Any number of variables can be created in this 
    manner as well as complex concepts such as properties that trigger functions during set and get.
    Views can easily reference configuration settings with $v->_config['category/setting'] or even the global
    Director with $v->_director.
    
<h2>Director</h2>
    The Director is the global manager that handles all of the delegation and routing required to load the various
    Actors, Views, Scenes, Resources, and call the Actions requested by the URL.
    
<h2>Resources</h2>
    Resources are images, strings, rights definitions, templates, menu definitions, and the like. They are designed
    to be loaded in a similar manner to how Android handles its resources so that supporting multiple languages
    can be done easily by separate teams and still operate even if only a partial translation is available.
    
<h2>Shout outs</h2>
    Thanks, Pasha Paterson, for the wonderful new password reset via email feature!
    Thanks goes out to Dave Payne for his contributions to the Auth Accounts API feature!
	
