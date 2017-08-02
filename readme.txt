=== Traktivity ===
Contributors: jeherve
Tags: Trakt.tv, TV, Activity, Track, tmdb, Movies, TV Shows, Trakt, Log
Stable tag: 2.2.0
Requires at least: 4.7
Tested up to: 4.8
License: GPLv2+

Are you a TV addict, and want to keep track of all the shows you've binge-watched and movies you saw? Traktivity is for you!

== Description ==

This plugin allows you to log your watched TV series on Trakt.tv.

Right now it only logs data. In the next version you will get ways to display that data on your site.

**Questions, problems?**

Take a look at the *Installation* and *FAQ* tabs here. If that doesn't help, [post in the support forums](http://wordpress.org/support/plugin/traktivity).

**Want to contribute with a patch?**

[Join me on GitHub!](https://github.com/jeherve/traktivity/)

**Credits**

The Traktivity plugin gets most its data from your Trakt.tv account, via [the Trakt.tv API.](http://docs.trakt.apiary.io/)
To get more information about each show or movie, the plugin also uses [the TMDb API](https://www.themoviedb.org/documentation/api).

**Traktivity is not endorsed or certified by TMDb or Trakt.tv.**

Do you like that header image? Me too! Credit goes to [Andrew Branch](https://unsplash.com/@branch_portraits).

== Installation ==

1. Install the plugin either via the WordPress.org plugin directory, or by uploading the files to your server.
2. Go to Traktivity > Settings in your dashboard.
3. Follow the instructions to set up the plugin.
4. Sit back and watch something on TV. 📺

== Frequently Asked Questions ==

== Screenshots ==

1. Dashboard: Discover the plugin and how to set it up.
2. Configuration: Link the plugin to your Trakt.tv account to get started.
3. Events: Everything you watch gets logged, right in your dashboard.
4. Sort: you can then search and sort everything.
5. Genres: Multiple details are logged for each event, like the movie or show's genre.
6. Movie: example of a movie and some of the details logged for that movie.

== Changelog ==

= 2.2.0 =
Release Date: August 2, 2017

* Start recording total time spent watching things.
* New dashboard component to display that information.
* Add option to recalculate runtime for all series from the dashboard.
* Avoid errors when no Featured Image is set for an event.
* Allow display of series's total runtime in a nice, translatable tally of years, days, hours, and minutes.

= 2.1.0 =
Release Date: August 1, 2017

* Start recording total runtime for each TV show, saved in term meta.

= 2.0.1 =
Release Date: July 31, 2017

* Avoid PHP warnings on network admin pages.
* Avoid encoded HTML in notices.

= 2.0.0 =
Release Date: July 31, 2017

* New Widget to list recent events recorded by our plugin.
* New Dashboard Interface.

= 1.1.3 =
Release Date: March 5, 2017

* Make sure Year is properly recorded for movies.

= 1.1.2 =
Release Date: March 5, 2017

* Fix link on Settings page.
* Only log the episode title as post title for each event.

= 1.1.1 =
Release Date: March 5, 2017

* Update readme with more instructions.

= 1.1.0 =
Release Date: March 5, 2017

* Add more information to our Settings page.
* Implement system to check API credentials from the Settings page.
* Add Image Credits to the bottom of our posts.
* Style the settings page, and add more helpful information to get started.
* Move the Settings page under the Traktivity menu.
* Add system to synchronize all past events logged on Trakt.tv.

= 1.0.0 =
Release Date: January 23, 2017

* Initial release
