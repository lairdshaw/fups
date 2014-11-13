FUPS: Forum user-post scraper
=============================

FUPS is an extensible PHP framework for scraping and outputting the posts of a specified user from a specified forum/board running supported forum software. Currently supported forum software is phpBB (well supported) and XenForo (minimally supported, possibly works on a single site only). FUPS can be run as either a web app or as a commandline script.

A working installation
----------------------

FUPS can be used as a web app here: [http://creativeandcritical.net/fups/](http://creativeandcritical.net/fups/).

Dependencies
------------

[PHP](http://php.net/) with the [Client URL Library (cURL)](http://php.net/manual/en/book.curl.php) installed. If running FUPS as a web app, then an additional dependency is a web server with PHP (with cURL) support. Tested on Apache but should work on other servers with minimal adjustment - the only Apache-specific file is output/.htaccess.

Setting it up
-------------

Download this repository to your filesystem, optionally under your web root if you wish to run FUPS as a web app.

Copy settings.default.php to settings.php and edit that file as appropriate. The most minimal case is to edit only the FUPS_DATADIR define, and to then ensure that the specified directory exists and is writeable by the user under whom FUPS will run. If running FUPS as a web app, this will be the same user that your web server runs as.

If running FUPS as a web app, also make sure that the "output" sub-directory is writeable by your web server.

Using as a web app
------------------

Simply navigate to index.php. The rest should be self-explanatory. (FUPS assumes that your web server is set up so that "index.php" is processed when the directory itself is accessed).

Using from the commandline
--------------------------

Create an options file. Change to the root FUPS directory and type:

    php fups.php -i path/to/your/optionsfile.txt -o path/to/your/outputfile.html

Depending on which forum software the forum you wish to scrape from runs, different options are available for your options file. Here are sample options files for both currently supported forum types - adjust these options as required. The meaning of each option is described further below.

Sample phpBB options file
-------------------------

    forum_type=phpbb
    base_url=http://example.com/phpBB/
    extract_user_id=1234
    extract_user=username_of_above_user
    login_user=my_login_forum_username
    login_password=password_for_the_above_username
    start_from_date=2014-10-17 19:46
    php_timezone=Australia/Hobart
    debug=0

Sample XenForo options file
---------------------------

    forum_type=xenforo
    base_url=http://example.com/forum/
    extract_user_id=example-username.12345
    start_from_date=2014-10-17 19:46
    php_timezone=Australia/Hobart
    debug=0

The options
-----------

First note that logging in to XenForo forums is not yet supported, hence the lack of *login_user* and *login_password* settings for those forums.

* *forum_type*: Required. One of phpbb or xenforo (case sensitive).

* *base_url*: Required. Set this to the base URL of the forum. This is the URL that appears in your browser's address bar when you access the forum. Strip off everything onwards from (and including) the filename of whichever script is being accessed - for phpBB forums this will be e.g. /index.php or /viewtopic.php, and for XenForo forums, this will be e.g. /threads or /forums.

* *extract_user_id*: Required. Set this to the user ID of the user whose posts are to be extracted. You can find a user's ID by hovering your cursor over a hyperlink to their name and in the URL in the browser's status bar: for phpBB forums, taking note of the number that appears after "&u="; for XenForo forums, taking note of everything that appears between "/members/" and the next "/" (i.e. this will be something like "my-member-name.12345").

* *extract_user*: Optional. Applies to phpBB forums only. Set this to the username corresponding to the *extract_user_id* - this saves FUPS from having to look this value up, which it often can only do when you are logged in, so this additionally might save you from having to provide values for *login_user* or *login_password*.

* *login_user*: Optional. Applies to phpBB forums only. Set this to the username of the user whom you wish to log in as (it's fine to set it to the same value as extract_user). If unset, FUPS will not log in. If supplied, then the timestamps associated with each post will be according to the timezone specified in this user's preferences, rather than the board default. Also, some boards require you to be logged in so that you can view posts.

* *login_password*: Optional. Applies to phpBB forums only. Set this to the password associated with the *login_user* user.

* *start_from_date*: Optional. Set this to the datetime of the earliest post to be extracted i.e. only posts of this datetime and later will be extracted. If not set then all posts will be extracted. This value is parsed with PHP's [strtotime()](http://www.php.net/strtotime) function, so check that link for details on what it should look like. An example of something that will work is: 2013-04-30 15:30.

* *php_timezone*: Required. Set this to the timezone in which the user's posts were made. It is a required setting (because PHP requires the timezone to be set), however it only affects the parsing of the *start_from_date* setting (so it is safe to leave it set to the default if you are not supplying a value for the *start_from_date* setting). Valid values are listed starting [here](http://php.net/manual/en/timezones.php).

* *debug*: Optional. If set true, additional debugging information will be output.

Limitations
-----------

* As already noted, FUPS currently doesn't support logging in to XenForo forums.

* The only XenForo skin supported is whichever skin is accessible by default for anonymous users on [civilwartalk.com](http://civilwartalk.com/) - I have no idea whether this is a default XenForo skin or a custom skin. If it is a custom skin, then civilwartalk.com is probably the only XenForo forum that FUPS is actually capable of scraping at present.

* Relative URLS within posts are currently not converted into absolute URLs. This means that sometimes, images that were uploaded to the forum do not appear in the FUPS output of the phpBB posts linking to those images, and that certain internal links in XenForo posts (e.g. those linked with an up arrow) are not functional in the HTML file that FUPS outputs.

Extending FUPS
--------------

The steps to add support for a new type of forum software are:

1. Create a new file in the "classes" subdirectory, and name that file C[forum_software_name].php, where [forum_software_name] is the correctly-capitalised identifier for the forum software - e.g. "phpBB", "XenForo" or "vBulletin". FUPS will auto-detect this file, and, based on its filename, add the forum software as both a selection on the main  web app page, and as a valid *forum_type* option in manually generated options files (note that when specifying the *forum_type* option, [forum_software_name] should be converted to lowercase).

2. In that file, declare a class named [forum_software_name]FUPS which extends the FUPSBase class (in classes/CFUPSBase.php).

3. Implement in your new class all abstract methods of FUPSBase. At time of writing, these are: `get_post_url()`, `get_search_url()`, `get_topic_url()` and `get_user_page_url()`. Also implement the static methods `get_qanda()`, `get_forum_software_homepage()` and `get_msg_how_to_detect_forum()`. Also, set the `$required_settings`, `$optional_settings`, and `$regexps` properties appropriately. Your biggest task will probably be working out appropriate regexes.

4. If necessary, you can implement overrides of any of the provided hooks. Hook methods at time of writing are: `find_author_posts_via_search_page__ts_raw_hook()`, `find_author_posts_via_search_page__match_hook()`, `find_author_posts_via_search_page__end_hook()`, `get_post_contents__end_hook()`, `hook_after__user_post_search()`, `hook_after__user_post_scrape()`, `hook_after__topic_post_sort()`, `hook_after__posts_retrieval()`, `hook_after__extract_per_thread_info()`, `hook_after__handle_missing_posts()`, and `hook_after__write_output()`.
