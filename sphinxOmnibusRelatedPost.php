<?php
if (!defined('ABSPATH')) exit;


class sphinxOmnibusRelatedContent extends sphinxOmnibusSearch
{
    public $error;
    public $warning;
    private static $instance;

    const OPTIONS_GROUP = 'sphinxOmnibus';
    const I18N_DOMAIN = 'sphinxOmnibus';

    protected $version = '0.0.1';
    protected $options;

    protected $pluginDir;
    protected $pluginName;
    protected $pluginFile;
    protected $templateDir;

    public function __construct()
    {
        $this->pluginDir = realpath(__DIR__ . '/../');
        $this->pluginName = basename($this->pluginDir);
        $this->pluginFile = $this->pluginDir . '/' . $this->pluginName . '.php';

        $this->options = $this->getSavedOptions();
        $this->init();
    }

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

    protected function init()
    {
        add_action('save_post', array(&$this, 'updatePostRelatedSearchWords'), 22);
    }


    public function updatePostRelatedSearchWords($post_id)
    {
        if (intval($post_id) > 0) {
            $post = get_post($post_id);
            if ($post instanceof WP_Post) {
                if (!in_array($post->post_type, array('nav_menu_item', 'nav_menu_item', 'attachment', 'action', 'author', 'order', 'theme'))) {
                    $keywords = $this->getKeywordsFromPost($post);
                    $keywordsSquashed = $this->flattenWords($keywords);
                    if ($keywordsSquashed != "") {
                        update_post_meta($post_id, "sphinxOmnibusRelatedSearchWords", $keywordsSquashed);
                        return $keywordsSquashed;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param $postId
     * @return array[WP_Post]
     */

    public function getRelatedPostsFromTaxa($taxonomy, $term_id)
    {

        $txargs = array(
            'meta_query' => array(
                array(
                    'key' => 'txindex_taxonomy',
                    'value' => $taxonomy,
                    'compare' => '=',
                ),
                array(
                    'key' => 'txindex_term_id',
                    'value' => $term_id,
                    'compare' => '=',
                )
            ),
            'posts_per_page' => 1
        );
        $txIDquery = new WP_Query($txargs);

        if ($txIDquery->have_posts()) {
            while ($txIDquery->have_posts()) {
                return $this->getRelatedPosts(get_the_id());
            }
            wp_reset_postdata();
        } else {
            wp_reset_postdata();
            return false;
        }
    }//getRelatedPostsFromTaxa


    public function getRelatedPosts($postId, $limit = null)
    {
        if (intval($postId) == 0) {
            return false;
        }
        if ($limit == null) {
            $limit = $postsperpage = get_option('posts_per_page');
        }

        $keywordString = get_post_meta($postId, "sphinxOmnibusRelatedSearchWords", true);

        if ($keywordString == "") {
            // No Search words found. Attempt to create them.
            $keywordString = $this->updatePostRelatedSearchWords($postId);
        }

        $ids = $this->searchPostIds($keywordString, $postId);


        if (!$ids) {
            return array();
        } else {
            // Remove the ID of the current post
            if (is_single()) {
                $excludeID = get_the_ID();
            } elseif (is_tax() || is_category()) {
                $txSet = rg_get_taxa_and_archiveID();
                $txArgs = array(
                    'posts_per_page' => 1,
                    'offset' => 0,
                    'meta_key' => 'txindex_term_id',
                    'meta_value' => $txSet['id'],
                    'post_type' => 'txindex',
                    'post_status' => 'publish',
                    'suppress_filters' => false

                );
                $post_to_exclude_array = get_posts($txArgs);
                if (count($post_to_exclude_array) > 0) {
                    $excludeID = $post_to_exclude_array[0]->ID;
                }
            }
            if ($excludeID > 0) {
                $ids = array_diff($ids, array($excludeID));
            }

        }

        return get_posts(array('post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => $limit));
    }

    protected function searchPostIds($keywords, $postId)
    {
        if (is_array($keywords)) {
            $q = $this->flattenWords($keywords);
        } else {
            $q = $keywords;
        }

        if (!$this->search instanceof sphinxOmnibusAPI) {
            $this->search = new sphinxOmnibusAPI;
        }

        $this->search->init();

        if (!$this->search->sphinxStatus()) {
            return false;
        }

        $result = $this->search->query(stripslashes($keywords));

        if ($result && isset($result['matches'])) {

            $ids = array();

            foreach ($result['matches'] as $id => $d) {
                if ($id == $postId) {
                    continue;
                }

                if ($d['weight'] >= $this->getOption('min_weight')) {
                    $ids[] = $id;
                }
            }

            if ($ids) {
                return $ids;
            }
        }

        return array();
    }

    protected function flattenWords(array $words)
    {
        $out = array_map(
            function ($el) {
                return htmlspecialchars($el, ENT_QUOTES, 'utf-8');
            },
            $words
        );

        return implode(" | ", $out);
    }

    protected function getKeywordsFromPost(WP_Post $post, $limit = 20)
    {
        $str = $post->post_title;

        $categories = $this->getPostTermNames($post->ID, 'category');

        foreach ($categories as $category) {
            $str .= ' ' . $category;
        }

        $tags = $this->getPostTermNames($post->ID, 'tag');

        foreach ($tags as $tag) {
            $str .= ' ' . $tag;
        }

        // Additional Taxonomies
        $fullTaxaList = get_object_taxonomies($post);

        foreach ($fullTaxaList as $oneTaxa) {
            if (!in_array($oneTaxa, array('tag', 'category', 'post_format'))) {
                // Custom taxa detected
                $taxaWords = $this->getPostTermNames($post->ID, $oneTaxa);
                foreach ($taxaWords as $theWord) {
                    $str .= ' ' . $theWord;
                }
            }
        }


        // Get first part of the_content
        $contentFull = $post->post_excerpt;
        if ($contentFull == "") {
            $contentFull = apply_filters('the_content', $post->post_content);
        }
        preg_match('/^(?>\S+\s*){1,100}/', $contentFull, $contentPart);
        if (is_array($contentPart)) {
            $str .= rtrim($contentPart[0]);
        }

        $wordList = explode(' ', $str);

        foreach ($wordList as $k => $word) {
            $word = mb_strtolower($word);
            $word = preg_replace('/[^a-zа-я0-9\s]/ui', '', $word);
            if (mb_strlen($word) <= 2) {
                unset($wordList[$k]);
                continue;
            }

            $wordList[$k] = $word;
        }

        $a = array_count_values($wordList);

        foreach ($this->getStopWords() as $word) {
            unset($a[$word]);
        }

        arsort($a, SORT_NUMERIC);

        $outWords = array_slice($a, 0, min(count($a), $limit));

        return array_keys($outWords);
    }

    private function getPostTermNames($postId, $taxonomy)
    {
        $tags = wp_get_post_terms($postId, $taxonomy);

        $names = array();

        foreach ($tags as $tag) {
            $names[] = $tag->name;
        }

        return $names;
    }


    protected function getStopWords()
    {
        $words = $this->getOption('stop_words');
        if (!$words) {
            return array();
        }

        $words = explode(',', $words);
        $words = array_map(
            function ($word) {
                return trim($word);
            },
            $words
        );

        return $words;
    }


    protected function getSavedOptions()
    {
        if ($options = get_option(self::OPTIONS_GROUP)) {
            return $options;
        }

        return $this->getDefaults();
    }

    protected function getOption($option, $default = null)
    {
        if (false !== strpos($option, ':')) {
            $keys = explode(':', $option);

            $data = $this->options;

            foreach ($keys as $key) {

                if (!isset($data[$key])) {
                    return $default;
                }

                $data = $data[$key];
            }

            return $data;
        }

        return isset ($this->options[$option]) ? $this->options[$option] : $default;
    }

    protected function overrideOptions($options)
    {
        $db_options = $this->options;
        if (!is_array($db_options) || !is_array($options)) {
            return false;
        }
        $this->options = array_merge($db_options, $options);

        return true;
    }

    protected function getDefaults()
    {
        return array(
            'enabled' => false,
            'version' => $this->version,
            'link_attr' => '',
            'weights' => array(
                'title' => 40,
                'text' => 5,
                'categories' => 30,
                'tags' => 20,
            ),
            'result_limit' => 5,
            'title' => __('Related Posts', self::OPTIONS_GROUP),
            'min_weight' => 50,
            'prefix' => 'sphinxOmnibus-',
            'stop_words' => 'a, an, the, and, of, i, to, is, in, with, for, as, that, on, at, this, my, was, our, it, you, we, 1, 2, 3, 4, 5, 6, 7, 8, 9, 0, 10, about, after, all, almost, along, also, amp, another, any, are, area, around, available, back, be, because, been, being, best, better, big, bit, both, but, by, c, came, can, capable, control, could, course, d, dan, day, decided, did, didn, different, div, do, doesn, don, down, drive, e, each, easily, easy, edition, end, enough, even, every, example, few, find, first, found, from, get, go, going, good, got, gt, had, hard, has, have, he, her, here, how, if, into, isn, just, know, last, left, li, like, little, ll, long, look, lot, lt, m, made, make, many, mb, me, menu, might, mm, more, most, much, name, nbsp, need, new, no, not, now, number, off, old, one, only, or, original, other, out, over, part, place, point, pretty, probably, problem, put, quite, quot, r, re, really, results, right, s, same, saw, see, set, several, she, sherree, should, since, size, small, so, some, something, special, still, stuff, such, sure, system, t, take, than, their, them, then, there, these, they, thing, things, think, those, though, through, time, today, together, too, took, two, up, us, use, used, using, ve, very, want, way, well, went, were, what, when, where, which, while, white, who, will, would, your, а, в, Я, это, алтухов, быть, вот, вы, да, еще, и, как, мы, не, нет, о, они, от, с, сказать, только, у, этот, большой, в, все, говорить, для, же, из, который, на, него, них, один, оно, ото, свой, та, тот, что, я, бы, весь, всей, год, до, знать, к, мочь, наш, нее, но, она, оный, по, себя, такой, ты, это ',
        );
    }


}