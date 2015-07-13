=== Sphinx Search & Related Omnibus ===
Contributors: kasigi,soreingold 
Tags: search, sphinx, related, index
Requires at least: 3.5
Tested up to: 4.2.2
Stable tag: trunk
License: GPLv2

Use sphinx search to search content and provide related posts.

== Description ==

Use sphinx indexes to search content and provide related posts functionality.
This is intended for developers and theme creators who require customized implementations,
multi-index support, custom weighting, and granular control of how results are returned.

The search mechanism is also able to search taxonomies along with \'post\' type content.

In addition to providing search facilities, this plugin provides related content results.

It does not provide wizards to create indexes, sphinx configuration files, or sphinx itself.

This plugin is idea for users who wish to custom tune their sphinx indexes.

It does NOT require that the Wordpress plugins folder be write-able or have any elevated permissions.

== Installation ==
= Requirements =

    * Sphinx Search 2.0.4 or higher
    * Advanced Custom Fields Plugin

= Install the plugin =

    1. Unzip the plugin into wp-content/plugins/
    2. Setup and configure Sphinx\'s indexes, indexer/cron.  There's an example of a sphinx configuration file in the plugin's lib folder.
    3. Activate plugin
    4. Configure address, port, and filters in the Wordpress Administration as needed
    5. Integrate the related post widgets as desired
    6. Add filterable fields to your search forms as desired

== Frequently Asked Questions ==

Q: Who should use this plugin?

A: Developers and integrators who want a customizable search implementation.

Q: How does Sphinx differ from the search included with Wordpress?

A: The basic wordpress search system runs a MySQL query against the posts table and looks for the search phrase in the post titles which is then returned in DATE order. Sphinx analyzes the content of the wordpress installation, unifies the spelling of words (stemming), provides boolean searches, and returns it in relevance order. This is far more accurate and will have better performance.

Q: How do I install Sphinx?

A: See http://sphinxsearch.com/docs/current.html#installation or consult your web host.  You may not be able to install this tool on many shared hosts.

Q: What is a Sphinx index?

A: A Sphinx index the data that searches are queried against. They are derived from MySQL queries. 

Q: How do I update my indexes?

A: Use a cron job on your server. The usual command run is \"indexer --rotate --all\" but may be modified for your specific installation.

Q: What is weighting?

A: Weighting alters the importance of various fields. This plugin weights the title very heavily.

Q: Is there a particular version of Sphinx this plugin is tested against?

A: 2.0.4 Ubuntu

Q: Does this mean I can only use this with Sphinx version 2.0.4 Ubuntu?

A: Not in the least.  It just means that this is the version we\'ve TESTED against. There\'s nothing inherently preventing this from working with other versions of Sphinx.


== Changelog ==
= 0.1 =
- Initial Revision