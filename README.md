phpBitsTheater
==============

Highly customizable PHP framework extending the [MVC site model](https://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller). Terms used borrow heavily from theater jargon. The framework was designed from the ground-up to employ a more modern [Semantic URL](https://en.wikipedia.org/wiki/Semantic_URL) scheme.

Minimum PHP version required is 5.4 (due to using the (new ClassName())->blah() feature of 5.4 in some places).

This framework extends the Model-View-Controller site model:

<h2>Actor = Controller</h2>
Business logic for your Views. Much of the behind-the-scenes work is handled here. Each public method an Actor
defines is an Action that may be called from the URL. Actions are auto-mapped to the URL so merely defining
a "public function bar()" inside Actor "foo" results in a URL of "http://mydomain.com/foo/bar/" executing
said function.

<h2>Models</h2>
Models are logical representations of data stored somewhere (usually in a local database).
Models and controllers are not 1 to 1, a controller (Actor) may not have any model associated with it and 
models can, and frequently do, have more than one model open at a time. Each model may have its own database
connection resulting in the website's ability to connect to several different databases at the same time.

<h2>Views</h2>
Standard HTML web pages. Most Actor actions will try to render a view with the same name as the Action itself.
Views are stored inside the view subfolder as `app/view/Actor/action.php` and will automatically be rendered
unless the Action returns a URL to redirect to instead. REST services will usually render all of their output
as a particular format, like JSON with the `app/view/results_as_json.php` view.

<h2>Scenes</h2>
An extension upon the MVC framework in that a Scene lives between an Actor and its View. A scene will define
all the data that is to be shared between the two. A generic Scene class is all that is required, but for 
complex work, you may wish to descend from it by giving it the same name as the Actor and cause that one to
be auto-loaded instead. You can provide Actor-specific features this way. The main import of the Scene class is to
automatically convert GET/POST parameters passed to the website into properties of its object.

An Actor references its Scene by using `$this->scene->some_var` and the View will reference the same 
variable as either `$recite->some_var` or `$v->some_var`.  Any number of variables can be created in this 
manner as well as complex concepts such as properties that trigger functions during set and get.
Views can easily reference configuration settings with `$v->getConfigSetting('category/setting')` or
the global Director with `$v->getDirector()`.

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

## Development Setup (Eclipse) ##

1. Clone the phpBitsTheater repository in your development environment.

    ```
    git clone git+ssh://git@github.com/baracudda/phpBitsTheater ./phpBitsTheater
    ```

2. In Eclipse, create a new project for phpBitsTheater. 
3. Clone your repository in your development environment.
4. In Eclipse, create a new project for your web service.
5. Right-click the project root in Eclipse, and select **Include Path** →
   **Configure Include Path…**
6. On the **Projects** tab, select the project for phpBitsTheater that was
   created in step 2.
7. Commit the changes to link the two projects.

## Installation ##

1. Set up the database where the web service's data will be hosted.
2. Push the bare BitsTheater library to your target server instance.
3. Push your server code to the same location on the target server,
   overwriting any of the base files.
4. In a web browser, navigate to the root of the instance.
5. Follow instructions in the browser to install the service.
