FUPS: Forum user-post scraper
=============================

FUPS is an extensible PHP framework for scraping and outputting the posts of a specified user from a specified forum/board running supported forum software. Currently supported forum software is phpBB (well supported) and XenForo (minimally supported, possibly works on a single site only). FUPS can be run as either a web app or as a commandline script.

Installation-free use
---------------------

FUPS can be used pre-installed as a web app here: [http://creativeandcritical.net/fups/](http://creativeandcritical.net/fups/).

Installing and using
--------------------

### Dependencies ###

[PHP](http://php.net/) with the [Client URL Library (cURL)](http://php.net/manual/en/book.curl.php) installed. If running FUPS as a web app, then an additional dependency is a web server with PHP (with cURL) support. Tested on Apache and IIS but should work on other servers with minimal adjustment - the only Apache-specific file is output/.htaccess.

### Installing and configuring ###

Download this repository to your filesystem, optionally under your web root if you wish to run FUPS as a web app (anywhere under your web root is fine - FUPS doesn't have to run from the top level directory).

Copy `settings.default.php` to `settings.php` and edit that file as appropriate. The most minimal case is to edit only the `FUPS_DATADIR` define, and to then ensure that the specified directory exists and is writeable by the user under whom FUPS will run. If running FUPS as a web app, this will be the same user that your web server runs as.

If running FUPS as a web app, also make sure that the "output" sub-directory is writeable by the user your web server runs as.

### Using as a web app ###

Simply navigate to `index.php`. The rest should be self-explanatory. (In some of its backlinks, FUPS assumes that your web server is set up so that `index.php` is processed when the directory itself is accessed, but the core functionality will work fine even if this assumption, and those backlinks, fail).

### Using from the commandline ###

Create an options file. Change to the root FUPS directory and type:

    php fups.php -i path/to/your/optionsfile.txt -o path/to/your/outputfile.html

Optionally, a `-q` parameter can be added to suppress status update messages.

Depending on which forum software the forum you wish to scrape from runs, different options are available for your options file. Here are sample options files for both currently supported forum types - adjust these options as required. The meaning of each option is described further below.

### Sample options files ###

#### phpBB ####

    forum_type=phpbb
    base_url=http://example.com/phpBB
    extract_user_id=1234
    extract_user=John Smith
    login_user=Mary Jones
    login_password=abc123
    start_from_date=2014-10-17 19:46
    php_timezone=America/Los_Angeles
    debug=1

#### XenForo ####

    forum_type=xenforo
    base_url=http://example.com/forum
    extract_user_id=example-username.12345
    start_from_date=2013-05-31 07:30
    php_timezone=Australia/Hobart
    debug=0

### The options ###

First note that logging in to XenForo forums is not yet supported, hence the lack of *login_user* and *login_password* options for forums of that type (nor is *extract_user* supported for XenForo forums).

* *forum_type*: Required. One of phpbb or xenforo (case sensitive).

* *base_url*: Required. The URL that appears in your browser's address bar when you access the forum, with everything onwards from (and including) the filename/path of whichever script is being accessed stripped off - for phpBB forums this will be e.g. /index.php or /viewtopic.php, and for XenForo forums, this will be e.g. /threads or /forums. Ideally, you would remove any trailing forward slash from this, but scraping will almost certainly still work even if you don't.

* *extract_user_id*: Required. Set this to the user ID of the user whose posts are to be extracted. You can find a user's ID by hovering your cursor over a hyperlink to their name, and taking note of, in the URL in the browser's status bar: for phpBB forums, the number that appears after "&u="; for XenForo forums, everything that appears between "/members/" and the next "/" (i.e. this will be something like "my-member-name.12345").

* *extract_user*: Optional. Applies to phpBB forums only. Set this to the username corresponding to the *extract_user_id* - this saves FUPS from having to look this value up, which it often can only do when you are logged in, so this additionally might save you from having to provide values for *login_user* or *login_password*.

* *login_user*: Optional. Applies to phpBB forums only. Set this to the username of the user whom you wish to log in as (it's fine to set it to the same value as *extract_user*). If unset, FUPS will not log in. If supplied, then the timestamps associated with each post will be according to the timezone specified in this user's preferences, rather than the board default. Also, some boards require you to be logged in so that you can view posts.

* *login_password*: Optional. Applies to phpBB forums only. Set this to the password associated with the *login_user* user.

* *start_from_date*: Optional. Set this to the datetime of the earliest post to be extracted i.e. only posts of this datetime and later will be extracted. If not set then all posts will be extracted. This value is parsed with PHP's [strtotime()](http://www.php.net/strtotime) function, so check that link for details on what it should look like. An example of something that will work is: 2013-04-30 15:30.

* *php_timezone*: Required. Set this to the timezone in which the user's posts were made. It is a required setting (because PHP requires the timezone to be set), however it only affects the parsing of the *start_from_date* setting, so it is safe to leave it set to the default if you are not supplying a value for the *start_from_date* setting. Valid values are listed starting [here](http://php.net/manual/en/timezones.php).

* *debug*: Optional. If set to "true" or "1", additional debugging information will be output.

Limitations
-----------

* As already noted, FUPS currently doesn't support logging in to XenForo forums.

* The only XenForo skin supported is whichever skin is accessible by default for anonymous users on [civilwartalk.com](http://civilwartalk.com/) - I have no idea whether this is a default XenForo skin or a custom skin. If it is a custom skin, then civilwartalk.com is probably the only XenForo forum that FUPS is actually capable of scraping at present.

* Relative URLS within posts are currently not converted into absolute URLs. This means that sometimes, images that were uploaded to the forum do not appear in the FUPS output of the phpBB posts linking to those images, and that certain internal links in XenForo posts (e.g. those linked with an up arrow) are not functional in the HTML file that FUPS outputs.

The code
--------

### Overview ###

Super-quick start: the guts of the FUPS code (i.e. scraping and outputting the scraped posts) is in the `classes` subdirectory, especially in `classes/CFUPSBase.php`.

The "true" entry-point script into all of that though is `fups.php`, which is invoked either directly via commandline PHP, or by the web app as a background PHP process. What it does is described below.

#### Web app flow ####

1.  The user browses to `index.php` and clicks on the forum type.
2.  That click invokes `enter-options.php`, which shows options for the selected forum type, which the user then enters and submits. (This page also tests whether the user's browser is AJAX-capable by attempting to read the contents of `ajax-test.txt` via an AJAX call, and, if successful, it passes this information to the next step. Pedantic note: strictly speaking, FUPS doesn't use AJAX, but only because of the 'X' in 'AJAX' - the data FUPS transfers in this way is not entirely XML).
3.  This submission invokes `run.php`, which processes the user's options, and then (except for user input error, in which case `run.php` displays an error message and does nothing further):
  1. Generates a unique token.
  2. Creates the following files in the `FUPS_DATADIR` directory, named based on the token: a settings file containing the options ("settings" and "options" are used somewhat interchangeably to describe these user-supplied values and the file that stores them) entered by the user, an error file and a status file.
  3. Forks off `fups.php` to run in the background, invoking it via commandline PHP, and supplying it with the unique token via a `-t` parameter.
  4. Returns to the browser a status page, updating the contents of the status div via AJAX (through a call to `ajax-get-status.php`) if the browser was determined to be AJAX-capable in step #2, or otherwise via a full-page HTML meta refresh every 30 seconds. When the status file indicates to either `ajax-get-status.php` - or to `run.php` on full page refresh - that the `fups.php` process has completed, then the page updates to display a link to the scraped output (or it displays an error message if the `fups.php` process terminated due to error).

#### Manual commandline invocation flow ####

The user invokes `fups.php` as described in the section above, "Using from the commandline".

#### What the `fups.php` process does ####

1. Parses its commandline parameters (which differ between web app and manual commandline invocations).
2. Determines the name of the settings file based either on the `-t` (token) parameter (web app invocation) or the `-i` (input file) parameter (commandline invocation), and extracts from that file the value of the `forum_type` option.
3. Checks that this is a supported forum type, and, if so, instantiates an object of the corresponding class from the corresponding file in the `classes` subdirectory, passing on to that object's constructor the parameters with which it was supplied (token or input+output file and any "quiet" parameter), as well as whether it is running via a web app request or via a manual commandline invocation. During construction, the object reads the values from the settings file, validates them and stores them in a(n array) property of itself.
4. Calls the `run()` method of that object. This method performs the scraping, regularly outputting status messages to either the status file or to standard error. The status file is used in the case of web invocation. In that case, `ajax-get-status.php`, on the receiving end of an indefinite AJAX request from the user's browser initiated by internal Javascript in the page rendered by `run.php`, monitors the status file for changes, which it returns to the browser when it detects them. Alternatively, if AJAX capability was not detected, then `run.php` itself checks the status file each time it (`run.php`) refreshes via the 30 second HTML meta refresh. Output of status messages to standard error is used in the case of manual commandline invocation.
5. Unless a fatal error occurs, in the end, the `run()` method calls the `write_output()` method, which writes to an appropriately named file either in the `output` sub-directory (web app invocation) or as stipulated via the `-o` parameter to `fups.php` (manual commandline invocation), and then `fups.php` (and thus the PHP process running it) exits.

At regular points in the `run()` method, the object checks whether enough time has elapsed that it must "chain" itself - that is to say, exit after serialising its current state (again, to a unique file in the `FUPS_DATADIR` directory) and then reinvoking `fups.php` with the same parameters with which it was originally invoked, plus an additional parameter, `-c`, to indicate that it is being chained and thus that `fups.php` must unserialise it from its serialisation file rather than creating it anew. "Chaining" works around the problem of web hosts fixing the maximum execution time of PHP scripts, because the scraping process can often exceed this limit.

Note that other files may or will be created in the `FUPS_DATADIR` by objects of the `FUPSBase` class and its descendants: in particular, a cookie file and an admin error file, which in the case of certain errors stores the full HTML of a problematic scraped page.

A lot more could be said about how the `FUPSBase` class and its descendants do their work, and how the `FUPSBase` class caters for extensibility, but it is beyond the scope of this document. Best is to simply explore the code (starting from `FUPSBase->run()`), and drop me a line if you have any questions! In any case, the following checklist for extending FUPS to support a new forum type might help further.

### Extending FUPS (to support other forum software) ###

The steps to add support for a new type of forum software are:

1. Create a new file in the "classes" subdirectory, and name that file C[forum_software_name].php, where [forum_software_name] is the correctly-capitalised identifier for the forum software - e.g. "phpBB", "XenForo" or "vBulletin". FUPS will auto-detect this file, and, based on its filename, add the forum software as both a selection on the main  web app page, and as a valid *forum_type* option for manually generated options files (note that when specifying the *forum_type* option, [forum_software_name] should be converted to lowercase).

2. In that file, declare a class named [forum_software_name]FUPS which extends the FUPSBase class (in `classes/CFUPSBase.php`). This name (including correct capitalisation) is important because `fups.php` auto-instantiates it.

3. Implement in your new class all abstract methods of FUPSBase. At time of writing, these are: `get_post_url()`, `get_search_url()`, `get_topic_url()` and `get_user_page_url()`. Also implement the static methods `get_qanda()`, `get_forum_software_homepage()` and `get_msg_how_to_detect_forum()`. If your class is to support logging in, then also override `check_do_login()` and `supports_feature()`. Also, set the `$required_settings`, `$optional_settings`, and `$regexps` properties appropriately. Your biggest task will probably be working out appropriate regexes.

4. If necessary, you can implement overrides of any of the provided hooks. Hook methods at time of writing are: `find_author_posts_via_search_page__ts_raw_hook()`, `find_author_posts_via_search_page__match_hook()`, `find_author_posts_via_search_page__end_hook()`, `get_post_contents__end_hook()`, `hook_after__user_post_search()`, `hook_after__init_user_post_search()`, `hook_after__user_post_search()`, `hook_after__posts_retrieval()`, `hook_after__extract_per_thread_info()`, `hook_after__handle_missing_posts()`, and `hook_after__write_output()`. Finally, if necessary you can override the `validate_settings()` method.
