<?php
/*
Plugin Name: UpDownUpDown
Plugin URI: http://davekonopka.com/updownupdown
Description: Up/down voting for posts and comments
Version: 1.0.1
Author: Dave Konopka
Author URI: http://davekonopka.com
License: GPL2

  Copyright 2011 Dave Konopka (email : dave.konopka@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
    This Wordpress plugin is released under a GNU General Public License, version 2. 
    A complete version of this license can be found here: 
    http://www.gnu.org/licenses/gpl-2.0.html

  This plugin was developed as a project of Wharton Research Data Services.
*/

if (!class_exists("UpDownPostCommentVotes")) {
  class UpDownPostCommentVotes {
    
    function __construct() {
      add_action( 'init', array( &$this, 'init_plugin' ));
      add_action( 'wp_ajax_register_vote', array( &$this, 'ajax_register_vote' ));
      add_action( 'wp_head', array( &$this, 'add_ajax_url' ));
    }
    
    public function setup_plugin() {
      global $wpdb;

      if ( !empty($wpdb->charset) )
        $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

      $sql[] = "CREATE TABLE {$wpdb->base_prefix}up_down_post_vote_totals (
              id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
              post_id bigint(20) NOT NULL,
              vote_count_up bigint(20) NOT NULL DEFAULT '0',
              vote_count_down bigint(20) NOT NULL DEFAULT '0',
              KEY post_id (post_id),
              CONSTRAINT UNIQUE (post_id)
              ) {$charset_collate};";

      $sql[] = "CREATE TABLE {$wpdb->base_prefix}up_down_post_votes (
              id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
              post_id bigint(20) unsigned NOT NULL,
              voter_id bigint(20) unsigned NOT NULL,
              vote_value int(11) NOT NULL DEFAULT '0',
              KEY post_id (post_id),
              KEY voter_id (voter_id),
              KEY post_voter (post_id, voter_id),
              CONSTRAINT UNIQUE (post_id, voter_id)
              ) {$charset_collate};";

      $sql[] = "CREATE TABLE {$wpdb->base_prefix}up_down_comment_vote_totals (
              id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
              comment_id bigint(20) unsigned  NOT NULL,
              post_id bigint(20) unsigned NOT NULL,
              vote_count_up bigint(20) NOT NULL DEFAULT '0',
              vote_count_down bigint(20) NOT NULL DEFAULT '0',
              KEY post_id (post_id),
              KEY comment_id (comment_id),
              CONSTRAINT UNIQUE (comment_id)
              ) {$charset_collate};";

      $sql[] = "CREATE TABLE {$wpdb->base_prefix}up_down_comment_votes (
              id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
              comment_id bigint(20) unsigned NOT NULL,
              post_id bigint(20) unsigned NOT NULL,
              voter_id bigint(20) unsigned NOT NULL,
              vote_value int(11) NOT NULL DEFAULT '0',
              KEY comment_id (comment_id),
              KEY post_id (post_id),
              KEY voter_id (voter_id),
              KEY post_voter (post_id, voter_id),
              KEY comment_voter (comment_id, voter_id),
              CONSTRAINT UNIQUE (comment_id, voter_id)
              ) {$charset_collate};";

      require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

      dbDelta($sql);

    }

    public function init_plugin() {
      $this->load_vote_client_resources();
    }
    
    public function load_vote_client_resources() {
      if ( !is_admin() ) { 
        wp_register_style( 'updownupdown', plugins_url( '/style/updownupdown.css', __FILE__));
        wp_enqueue_style( 'updownupdown' );
        
         wp_register_script( 'updownupdown',
             plugins_url( '/js/updownupdown.js', __FILE__),
             array( 'jquery' ),
             '1.0' );
         wp_enqueue_script('updownupdown');
      }      
    }

    public function add_ajax_url() { ?>
    	<script type="text/javascript">var UpDownUpDown = { ajaxurl: "<?php echo admin_url('admin-ajax.php'); ?>" };</script>
    <?php
    }

    public function get_post_votes_total( $post_id ) {
      global $wpdb;

      if ( !$post_id )
        return false;

      $result_query = $wpdb->get_results($wpdb->prepare("
                              SELECT vote_count_up, vote_count_down 
                              FROM {$wpdb->base_prefix}up_down_post_vote_totals 
                              WHERE post_id = %d", $post_id));

      return ( count($result_query) == 1 ? array( "up" => $result_query[0]->vote_count_up, "down" => $result_query[0]->vote_count_down ) : array( "up" => 0, "down" => 0 ));
    }

    public function get_comment_votes_total( $comment_id ) {
      global $wpdb;

      if ( !$comment_id )
        return false;

      $result_query = $wpdb->get_results($wpdb->prepare("
                              SELECT vote_count_up, vote_count_down 
                              FROM {$wpdb->base_prefix}up_down_comment_vote_totals 
                              WHERE comment_id = %d", $comment_id));

      return ( count($result_query) == 1 ? array( "up" => $result_query[0]->vote_count_up, "down" => $result_query[0]->vote_count_down ) : array( "up" => 0, "down" => 0 ));
    }

    public function get_post_user_vote( $user_id, $post_id ) {
      global $wpdb;
      return $wpdb->get_var($wpdb->prepare("
                          SELECT vote_value 
                          FROM {$wpdb->base_prefix}up_down_post_votes
                          WHERE voter_id =  %d
                            AND post_id = %d", $user_id, $post_id));
    }

    public function get_comment_user_vote( $user_id, $comment_id ) {
      global $wpdb;
      return $wpdb->get_var($wpdb->prepare("
                          SELECT vote_value 
                          FROM {$wpdb->base_prefix}up_down_comment_votes
                          WHERE voter_id =  %d
                            AND comment_id = %d", $user_id, $comment_id));
    }

    public function render_vote_badge( $vote_up_count = 0, $vote_down_count = 0, $votable = true, $existing_vote = 0 ) { 
      
      $img_up_status = '';
      $img_down_status = '';
      $vote_label = '';
      $up_classnames = '';
      $down_classnames = '';

      $votable = is_user_logged_in() && $votable;
      
      if ( $existing_vote > 0 ) $img_up_status = '-on';
      elseif ( $existing_vote < 0 ) $img_down_status = '-on';

      $up_img_src = plugins_url( '/images/arrow-up'.$img_up_status.'.png', __FILE__);
      $down_img_src = plugins_url( '/images/arrow-down'.$img_down_status.'.png', __FILE__);

      if ( $vote_down_count > 0 ) {
        $vote_down_count *= -1;
        $down_classnames = ' updown-active';
      }
      
      if ( $vote_up_count > 0 ) {
        $vote_up_count = "+" . $vote_up_count;
        $up_classnames = ' updown-active';
      }

      if ( $vote_up_count == 0 && $vote_down_count == 0 && $votable ) {
        $vote_up_count = '';
        $vote_down_count = '';
      }
      
      $vote_label = 'vote';
      if (!$votable)
        $vote_label .= 's';
      ?>
        <?php if ($votable): ?>
        <div><img class="updown-button updown-up-button" vote-direction="1" src="<?=$up_img_src?>"></div>
        <?php endif; ?>
        
        <div class="updown-up-count<?=$up_classnames?>"><?=$vote_up_count?></div>
        <div class="updown-down-count<?=$down_classnames?>"><?=$vote_down_count?></div>

        <?php if ($votable): ?>
        <div><img class="updown-button updown-down-button" vote-direction="-1" src="<?=$down_img_src?>"></div>
        <?php endif; ?>

        <div class="updown-label"><?=$vote_label?></div>

    <?php
    }

    //********************************************************************
    //Ajax handlers

    public function ajax_register_vote() {
      global $wpdb;
      
      $result = array( 'status' => '-1', 
                        'message' => '', 
                        'vote_totals' => null, 
                        'post_id' => null, 
                        'comment_id' => null, 
                        'direction' => null );

      if (!is_user_logged_in()) {
        $result['message'] = 'You must be logged in to vote.';
        die(json_encode($result));
      }
      
      $user_id = get_current_user_id();

      //Validate expected params
      if ( ($_POST['post_id'] == null && $_POST['comment_id'] == null)
            || $_POST['direction'] == null
            || $user_id < 1 )
        die(json_encode($result));

      $post_id = $_POST['post_id'];
      $comment_id = $_POST['comment_id'];

      if ( $post_id != null ) {
        $element_name = 'post';
        $element_id = $post_id;
      } elseif ( $comment_id != null ) {
        $element_name = 'comment';
        $element_id = $comment_id;
      }
      else die(json_encode($result));

      $vote_value = $_POST['direction'];
      $down_vote = 0;
      $up_vote = 0;

      if ( $vote_value > 0 ) {
        $vote_value = 1;
        $up_vote_delta = 1;
      } elseif ( $vote_value < 0 ) { 
        $vote_value = -1;
        $down_vote_delta = 1;
      }

      if ( $element_name == 'post' ) $existing_vote = $this->get_post_user_vote( $user_id, $post_id );
      elseif  ( $element_name == 'comment' ) $existing_vote = $this->get_comment_user_vote( $user_id, $comment_id );
      
      if ( $existing_vote < 0 ) $down_vote_delta -= 1;
      elseif ( $existing_vote > 0 ) $up_vote_delta -= 1;

      //Update user vote
      if ($existing_vote != null) {
        $wpdb->query($wpdb->prepare("
          UPDATE {$wpdb->base_prefix}up_down_".$element_name."_votes
          SET vote_value = %d
          WHERE voter_id = %d
            AND ".$element_name."_id = %d", $vote_value, $user_id, $element_id));
      } else {
        $wpdb->query($wpdb->prepare("
          INSERT INTO {$wpdb->base_prefix}up_down_".$element_name."_votes
          ( vote_value, ".$element_name."_id, voter_id )
          VALUES
          ( %d, %d, %d )", $vote_value, $element_id, $user_id ));
        $existing_vote = 0;
      }

      //Update total
      if ($wpdb->query($wpdb->prepare("
          UPDATE {$wpdb->base_prefix}up_down_".$element_name."_vote_totals
          SET vote_count_up = (vote_count_up + %d),
            vote_count_down = (vote_count_down + %d)
          WHERE ".$element_name."_id = %d", $up_vote_delta, $down_vote_delta, $element_id)) == 0)
        $wpdb->query($wpdb->prepare("
          INSERT INTO {$wpdb->base_prefix}up_down_".$element_name."_vote_totals
          ( vote_count_up, vote_count_down, ".$element_name."_id )
          VALUES
          ( %d, %d, %d )", $up_vote_delta, $down_vote_delta, $element_id));

      //Return success
      $result["status"] = 1;
      $result["message"] = "Your vote has been registered for this ".$element_name.".";
      $result["post_id"] = $post_id;
      $result["comment_id"] = $comment_id;
      $result["direction"] = $vote_value;
      $result["vote_totals"] = $wpdb->get_row($wpdb->prepare("
                                SELECT vote_count_up as up, vote_count_down as down
                                FROM {$wpdb->base_prefix}up_down_".$element_name."_vote_totals
                                WHERE ".$element_name."_id = %d", $element_id));

      die(json_encode($result));
    }

  } //class:UpDownPostCommentVotes
  
  //Create instance of plugin
  $up_down_plugin = new UpDownPostCommentVotes();

  //Handle plugin activation
  register_activation_hook( __FILE__, array( &$up_down_plugin, 'setup_plugin' ));


  //********************************************************************
  //Custom template tags

  function up_down_post_votes( $post_id, $allow_votes = true ) {
    global $up_down_plugin;

    if ( !$post_id )
      return false;
      
    $vote_counts = $up_down_plugin->get_post_votes_total( $post_id );
    $existing_vote = $up_down_plugin->get_post_user_vote( get_current_user_id(), $post_id );

    ?>
    <div class="updown-vote-box updown-post" id="updown-post-<?php echo $post_id; ?>" post-id="<?php echo $post_id; ?>">
      <?php $up_down_plugin->render_vote_badge( $vote_counts["up"], $vote_counts["down"], $allow_votes, $existing_vote ); ?>
    </div>
    <?php
  }

  function up_down_comment_votes( $comment_id, $allow_votes = true ) {
    global $up_down_plugin;

    if ( !$comment_id )
      return false;
      
    $vote_counts = $up_down_plugin->get_comment_votes_total( $comment_id );
    $existing_vote = $up_down_plugin->get_comment_user_vote( get_current_user_id(), $comment_id );
    
    ?>
    <div class="updown-vote-box updown-comments" id="updown-comment-<?php echo $comment_id; ?>" comment-id="<?php echo $comment_id; ?>">
      <?php $up_down_plugin->render_vote_badge( $vote_counts["up"], $vote_counts["down"], $allow_votes, $existing_vote ); ?>
    </div>
    <?php
  }

} 

?>
