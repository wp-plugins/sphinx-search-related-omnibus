<?php
if (!defined('ABSPATH')) exit;

// This class is used to integrate the search with wordpress itself
class sphinxOmnibusSearch
{
    private static $instance;
    public $search;

    function __construct()
    {
        // General Init and Attach Filters
        add_filter('query_vars', array(&$this, 'addAttributeFilterVariablesToPublicQuery'));
        $this->search = sphinxOmnibusAPI::get_instance();

        //prepare post results
        add_filter('posts_where', array(&$this, 'posts_request'));
        add_filter('posts_selection', array($this, 'unbind_filters'));
        add_filter('posts_results', array(&$this, 'search_post_results'), 16, 1);
        add_filter('found_posts', array(&$this, 'found_posts'));
        //sphinxOmnibus_Plugin
    }

    function dumpMe($query)
    {
        var_dump($query);
    }

    function search_post_results($posts)
    {
        if (is_search() && count($this->search->search_results_id_array) > 0) {
            $posts = $this->sort_post_results($posts);
        }
        return $posts;
    } // post_results

    function sort_post_results($posts)
    {
        $matchPostIDArray = $this->search->search_results_id_array;
        usort($posts, function ($a, $b) use ($matchPostIDArray) {
            $apos = array_search($a->ID, $matchPostIDArray);
            $bpos = array_search($b->ID, $matchPostIDArray);
            return ($apos < $bpos) ? -1 : 1;
        });

        return $posts;
    }

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

    function unbind_filters()
    {
        remove_filter('posts_where', array(&$this, 'posts_request'));
        remove_filter('posts_orderby', array(&$this, 'posts_orderby'));

    }

    function found_posts($fountPostCount)
    {
        if (is_search()) {
            return $this->search->post_count;
        }
        return $fountPostCount;
    }


    function posts_request($sqlQuery)
    {
        global $wpdb;

        // This function overrides how the search results page functions and populates data
        if (is_search()) {

            $this->search = sphinxOmnibusAPI::get_instance();
            $this->search->init();

            if (!$this->search->sphinxStatus()) {
                return $sqlQuery;
            }
            $possibleFilters = $this->search->getAvailableAttributeFilters();

            // Look for post filters in query string
            foreach ($possibleFilters as $possibleFilter) {
                $testVal = get_query_var($possibleFilter);

                if ($testVal != "") {
                    // Add the filter
                    $this->search->setAttributeFilter($possibleFilter, $testVal);
                }
            }


            // Set & Run the Query
            $searchReturn = $this->search->query(stripslashes(get_search_query()));

            if ($searchReturn) {
                // If there are results, alter the where query to pull the results instead of the LIKE type queries

                $this->search->parse_results();
                //var_dump($searchReturn['matches']);

                // Extract the postID's
                $matchPostIDArray = null;
                foreach ($searchReturn['matches'] as $matchPostID => $matchData) {
                    $matchPostIDArray[] = ($matchPostID - 1) / 2;
                }
                $matchPostIDStringSet = implode(",", $matchPostIDArray);

                // Remove the old search SQL
                $sqlQuery = preg_replace("/(\([^\(]*post_title LIKE [^\)]*\))/", "", $sqlQuery);
                $sqlQuery = preg_replace("/(\([^\(]*post_content LIKE [^\)]*\))/", "", $sqlQuery);


                // Remove any remaining () that may have been left by the previous step
                $safetyValve = 0;
                while ($this->doesSQLneedCleansing($sqlQuery) && $safetyValve < 150) {
                    $sqlQuery = $this->cleanseTheSQL($sqlQuery);
                    $safetyValve++;
                }


                // Remove any double AND that might be left over

                // Add the new where
                $sqlQuery .= " AND ($wpdb->posts.ID IN ($matchPostIDStringSet)) ";
                add_filter('posts_orderby', array(&$this, 'posts_orderby'));
                return $sqlQuery;
            } else {
                $sqlQuery = " AND (0 = 1) ";

                //returning empty string we disabled to run default query
                //instead of that we add our owen search results
                return $sqlQuery;
            }

        } else {
            return $sqlQuery;
        }
    }// end post_request

    function doesSQLneedCleansing($sqlQuery)
    {
        $cleanMe = false;
        $searches[] = "/(\\( *AND *\\))/";
        $searches[] = "/(\\( *OR *\\))/";
        $searches[] = "/(AND *AND)/";
        $searches[] = "()";
        $searches[] = "( )";

        foreach ($searches as $matchMe) {
            if (preg_match($matchMe, $sqlQuery)) {
                $cleanMe = true;
            }
        }
        return $cleanMe;
    }// doesSQLneedCleansing

    function cleanseTheSQL($sqlQuery)
    {
        $sqlQuery = preg_replace("/(\\( *AND *\\))/", "", $sqlQuery);
        $sqlQuery = preg_replace("/(\\( *OR *\\))/", "", $sqlQuery);
        $sqlQuery = preg_replace("/(AND *AND)/", "AND", $sqlQuery);
        $sqlQuery = str_replace("()", "", $sqlQuery);
        $sqlQuery = str_replace("( )", "", $sqlQuery);

        return $sqlQuery;

    }// doesSQLneedCleansing

    function posts_orderby($sqlQuery)
    {
        if (preg_match("/FIELD\\( ?[^\\.]*\\.ID ?,[^\\)]*\\)/", $sqlQuery) == 0 && count($this->search->search_results_id_array) > 0) {
            global $wpdb;
            if ($sqlQuery != "") {
                $joiner = ", ";
            } else {
                $joiner = " ";
            }

            $sqlQuery = "FIELD($wpdb->posts.ID," . implode(",", $this->search->search_results_id_array) . ") " . $joiner . $sqlQuery;
        }

        return $sqlQuery;
    }

    function addAttributeFilterVariablesToPublicQuery($vars)
    {
        // This function is used in a filter to add to the public vars / query string vars / post vars
        $this->search = sphinxOmnibusAPI::get_instance();
        $varset = $this->search->getAvailableAttributeFilters();
        $vars = array_merge($vars, $varset);
        return $vars;
    }

}



