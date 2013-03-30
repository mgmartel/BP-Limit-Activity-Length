<?php
/*
  Plugin Name: BP Limit Activity Length
  Plugin URI: http://trenvo.com
  Description: Limit the maximum length of activities like Twitter
  Version: 0.1
  Author: Mike Martel
  Author URI: http://trenvo.com
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Version number
 *
 * @since 0.1
 */
define('BP_LAL_VERSION', '0.1');

/**
 * PATHs and URLs
 *
 * @since 0.1
 */
define('BP_LAL_DIR', plugin_dir_path(__FILE__));
define('BP_LAL_URL', plugin_dir_url(__FILE__));
define('BP_LAL_INC_URL', BP_LAL_URL . '_inc/');

if (!class_exists('BP_LimitActivityLength')) :

    class BP_LimitActivityLength    {

        private $limit;

        /**
         * Creates an instance of the BP_LimitActivityLength class
         *
         * @return BP_LimitActivityLength object
         * @since 0.1
         * @static
        */
        public static function &init() {
            static $instance = false;

            if (!$instance) {
                load_plugin_textdomain('bp-lal', false, basename(BP_LAL_DIR) . '/languages/');
                $instance = new BP_LimitActivityLength;
            }

            return $instance;
        }

        /**
         * Constructor
         *
         * @since 0.1
         */
        public function __construct() {
            $this->limit = bp_get_option('bp-activity-length-limit', 140);

            add_action( 'init', array ( &$this, '_maybe_load_scripts' ) );
            add_filter( "bp_get_activity_content_body", array ( &$this, 'limit_activity_body_length' ), 10, 1 );

            // Admin
            add_action( 'bp_register_admin_settings', array ( &$this, 'register_settings' ) );
        }

        public function _maybe_load_scripts() {
            global $bp;

            if ( // Load the scripts on Activity pages
                (defined('BP_ACTIVITY_SLUG') && bp_is_activity_component())
                ||
                // Load the scripts when Activity page is the Home page
                (defined('BP_ACTIVITY_SLUG') && 'page' == get_option('show_on_front') && is_front_page() && BP_ACTIVITY_SLUG == get_option('page_on_front'))
                ||
                // Load the script on Group home page
                (defined('BP_GROUPS_SLUG') && bp_is_groups_component() && 'home' == $bp->current_action)
                ) {
                add_action( "wp_enqueue_scripts", array (&$this, 'enqueue_scripts') );
                add_action( "wp_print_scripts", array ( &$this, 'print_style' ) );
            }
        }

        public function register_settings() {
            add_settings_field( 'bp-activity-length-limit', __( 'Activity Length', 'bp-lal' ), array ( &$this, 'display_limit_setting'), 'buddypress', 'bp_activity' );
			register_setting( 'buddypress', 'bp-activity-length-limit', array ( &$this, 'sanitize_limit' ) );
        }

        public function display_limit_setting() {
            ?>
                <input id="bp-activity-length-limit" name="bp-activity-length-limit" type="text" value="<?php echo $this->limit ?>" />
                <label for="bp-activity-length-limit"><?php _e( 'Allowed length for activity updates. Limit to get force shorter updates.', 'bp-lal' ); ?></label>
            <?php
        }

        public function sanitize_limit( $setting ) {
            if ( !is_numeric( $setting ) ) $setting = $this->limit;
            return $setting;
        }


        public function enqueue_scripts() {
            wp_enqueue_script( 'bplal', BP_LAL_INC_URL . 'bp-lal.js', array('jquery'), BP_LAL_VERSION, true );
            wp_localize_script('bplal', 'BPLal', array(
                'limit'     => $this->limit,
            ));
        }

        /**
         * Because it's just one declaration, let's put it in the header
         */
        public function print_style() {
            ?>
            <style>div#whats-new-limit{float:right;margin:12px 10px 0 0;line-height:28px;}</style>
            <?php
        }

        /**
         *
         * @param type $activity_body
         * @uses force_balance_tags()
         */
        public function limit_activity_body_length( $activity_body ) {
            $chars = strlen ( strip_tags ( $activity_body ) );
            $diff = $this->limit - $chars;

            if ( $diff < 0 ) {
                $activity_body = $this->truncateHtml( $activity_body, $this->limit, '', true );
            }

            return $activity_body;
        }

        /**
         * truncateHtml can truncate a string up to a number of characters while preserving whole words and HTML tags
         *
         * @param string $text String to truncate.
         * @param integer $length Length of returned string, including ellipsis.
         * @param string $ending Ending to be appended to the trimmed string.
         * @param boolean $exact If false, $text will not be cut mid-word
         * @param boolean $considerHtml If true, HTML tags would be handled correctly
         *
         * @return string Trimmed string.
         * http://alanwhipple.com/2011/05/25/php-truncate-string-preserving-html-tags-words/
         */
        private function truncateHtml($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true) {
            if ($considerHtml) {
                // if the plain text is shorter than the maximum length, return the whole text
                if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                    return $text;
                }
                // splits all html-tags to scanable lines
                preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
                $total_length = strlen($ending);
                $open_tags = array();
                $truncate = '';
                foreach ($lines as $line_matchings) {
                    // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                    if (!empty($line_matchings[1])) {
                        // if it's an "empty element" with or without xhtml-conform closing slash
                        if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                            // do nothing
                        // if tag is a closing tag
                        } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                            // delete tag from $open_tags list
                            $pos = array_search($tag_matchings[1], $open_tags);
                            if ($pos !== false) {
                            unset($open_tags[$pos]);
                            }
                        // if tag is an opening tag
                        } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                            // add tag to the beginning of $open_tags list
                            array_unshift($open_tags, strtolower($tag_matchings[1]));
                        }
                        // add html-tag to $truncate'd text
                        $truncate .= $line_matchings[1];
                    }
                    // calculate the length of the plain text part of the line; handle entities as one character
                    $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
                    if ($total_length+$content_length> $length) {
                        // the number of characters which are left
                        $left = $length - $total_length;
                        $entities_length = 0;
                        // search for html entities
                        if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                            // calculate the real length of all entities in the legal range
                            foreach ($entities[0] as $entity) {
                                if ($entity[1]+1-$entities_length <= $left) {
                                    $left--;
                                    $entities_length += strlen($entity[0]);
                                } else {
                                    // no more characters left
                                    break;
                                }
                            }
                        }
                        $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                        // maximum lenght is reached, so get off the loop
                        break;
                    } else {
                        $truncate .= $line_matchings[2];
                        $total_length += $content_length;
                    }
                    // if the maximum length is reached, get off the loop
                    if($total_length>= $length) {
                        break;
                    }
                }
            } else {
                if (strlen($text) <= $length) {
                    return $text;
                } else {
                    $truncate = substr($text, 0, $length - strlen($ending));
                }
            }
            // if the words shouldn't be cut in the middle...
            if (!$exact) {
                // ...search the last occurance of a space...
                $spacepos = strrpos($truncate, ' ');
                if (isset($spacepos)&&$spacepos) {
                    // ...and cut the text in this position
                    $truncate = substr($truncate, 0, $spacepos);
                }
            }
            // add the defined ending to the text
            $truncate .= $ending;
            if($considerHtml) {
                // close all unclosed html-tags
                foreach ($open_tags as $tag) {
                    $truncate .= '</' . $tag . '>';
                }
            }
            return $truncate;
        }

    }

    add_action('bp_include', array('BP_LimitActivityLength', 'init'));
endif;