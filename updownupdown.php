<?php
/*
 * Plugin Name: UpDownUpDown
 * Plugin URI: http://davekonopka.com/updownupdown
 * Description: Up/down voting for posts and comments
 * Version: 1.1
 * Author: Dave Konopka, Martin Scharm
 * Author URI: http://davekonopka.com
 * License: GPL2
 * 
 *	Copyright 2011 Dave Konopka (email : dave.konopka@gmail.com)
 * 
 *		This program is free software; you can redistribute it and/or modify
 *		it under the terms of the GNU General Public License, version 2, as 
 *		published by the Free Software Foundation.
 * 
 *		This program is distributed in the hope that it will be useful,
 *		but WITHOUT ANY WARRANTY; without even the implied warranty of
 *		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 *		GNU General Public License for more details.
 * 
 *		You should have received a copy of the GNU General Public License
 *		along with this program; if not, write to the Free Software
 *		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA	02110-1301	USA
 *		
 *		This Wordpress plugin is released under a GNU General Public License, version 2. 
 *		A complete version of this license can be found here: 
 *		http://www.gnu.org/licenses/gpl-2.0.html
 * 
 *	This plugin was initially developed as a project of Wharton Research Data Services.
*/

if (!class_exists("UpDownPostCommentVotes"))
{
	global $updown_db_version;
	$updown_db_version = "1.1";
		
	class UpDownPostCommentVotes
	{
		function __construct()
		{
			add_action( 'init', array( &$this, 'init_plugin' ));
			add_action( 'wp_ajax_register_vote', array( &$this, 'ajax_register_vote' ));
			add_action( 'wp_ajax_nopriv_register_vote', array( &$this, 'ajax_register_vote' ));
			add_action( 'wp_head', array( &$this, 'add_ajax_url' ));
			add_action( 'admin_menu', 'updown_plugin_menu' );
		}
		
		public function setup_plugin() {
			global $wpdb;
			global $updown_db_version;
			require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

			if (get_option("updown_db_version")) {
				if (get_option("updown_db_version") != $updown_db_version)
					die ("downgrade not supported!!!"); // there is no prev version using this var
				return;
			}
			
			if ( !empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET ".$wpdb->charset;

			$sql[] = "CREATE TABLE ".$wpdb->base_prefix."up_down_post_vote_totals (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
						post_id bigint(20) NOT NULL,
						vote_count_up bigint(20) NOT NULL DEFAULT '0',
						vote_count_down bigint(20) NOT NULL DEFAULT '0',
						KEY post_id (post_id),
						CONSTRAINT UNIQUE (post_id)
			) ".$charset_collate.";";

			$sql[] = "CREATE TABLE ".$wpdb->base_prefix."up_down_post_vote (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
						post_id bigint(20) unsigned NOT NULL,
						voter_id varchar(32) NOT NULL DEFAULT '',
						vote_value int(11) NOT NULL DEFAULT '0',
						KEY post_id (post_id),
						KEY voter_id (voter_id),
						KEY post_voter (post_id, voter_id),
						CONSTRAINT UNIQUE (post_id, voter_id)
			) ".$charset_collate.";";

			$sql[] = "CREATE TABLE ".$wpdb->base_prefix."up_down_comment_vote_totals (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
						comment_id bigint(20) unsigned	NOT NULL,
						post_id bigint(20) unsigned NOT NULL,
						vote_count_up bigint(20) NOT NULL DEFAULT '0',
						vote_count_down bigint(20) NOT NULL DEFAULT '0',
						KEY post_id (post_id),
						KEY comment_id (comment_id),
						CONSTRAINT UNIQUE (comment_id)
			) ".$charset_collate.";";

			$sql[] = "CREATE TABLE ".$wpdb->base_prefix."up_down_comment_vote (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
						comment_id bigint(20) unsigned NOT NULL,
						post_id bigint(20) unsigned NOT NULL,
						voter_id varchar(32) NOT NULL DEFAULT '',
						vote_value int(11) NOT NULL DEFAULT '0',
						KEY comment_id (comment_id),
						KEY post_id (post_id),
						KEY voter_id (voter_id),
						KEY post_voter (post_id, voter_id),
						KEY comment_voter (comment_id, voter_id),
						CONSTRAINT UNIQUE (comment_id, voter_id)
			) ".$charset_collate.";";

			dbDelta ($sql);

			# If old style post vote logging table exists, port records over to new logging table
			if( $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->base_prefix."up_down_post_votes'") == $wpdb->base_prefix."up_down_post_votes" ) {
				
				// Port post vote logs
				$result_query = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->base_prefix."up_down_post_votes"));
				
				foreach ( $result_query as $value ) {
					$wpdb->insert ($wpdb->base_prefix."up_down_post_vote",
						array ('post_id' => $value->post_id,
										'voter_id' => $value->voter_id,
										'vote_value' => $value->vote_value));
				}
				
				//Drop old post log table
				$wpdb->query( 'DROP TABLE IF EXISTS '.$wpdb->base_prefix."up_down_post_votes");
			}

			# If old style comment vote logging table exists, port records over to new logging table
			if( $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->base_prefix."up_down_comment_votes'") == $wpdb->base_prefix."up_down_comment_votes" ) {
			
				// Port comment vote logs
				$result_query = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->base_prefix."up_down_comment_votes"));
				
				foreach ( $result_query as $value ) {
					$wpdb->insert ($wpdb->base_prefix."up_down_comment_vote",
						array ('comment_id' => $value->comment_id,
							'post_id' => $value->post_id,
							'voter_id' => $value->voter_id,
							'vote_value' => $value->vote_value));
				}

				//Drop old comment log table
				$wpdb->query( 'DROP TABLE IF EXISTS '.$wpdb->base_prefix."up_down_comment_votes");
			}

			update_option ("updown_db_version", $updown_db_version);
			
			if (!get_option ("updown_guest_allowed"))
				update_option ("updown_guest_allowed", "not allowed");
			if (!get_option ("updown_counter_type"))
				update_option ("updown_counter_type", "plusminus");
			if (!get_option ('updown_vote_text'))
				update_option ("updown_vote_text", "vote");
			if (!get_option ('updown_votes_text'))
				update_option ("updown_votes_text", "votes");
		}
		
		public function init_plugin()
		{
			$this->load_vote_client_resources();
		}
		
		public function load_vote_client_resources()
		{
			if ( !is_admin() )
			{
				// different styles available
				switch (get_option ("updown_css"))
				{
					case "simple":
						wp_register_style ( 'updownupdown', plugins_url ( '/style/updownupdown-simple.css', __FILE__));
						break;
					default:
				wp_register_style( 'updownupdown', plugins_url( '/style/updownupdown.css', __FILE__));
				}
				wp_enqueue_style( 'updownupdown' );
				
				 wp_register_script( 'updownupdown',
						 plugins_url( '/js/updownupdown.js', __FILE__),
						 array( 'jquery' ),
						 '1.0' );
				 wp_enqueue_script('updownupdown');
			}			
		}

		public function add_ajax_url()
		{ 
			echo '<script type="text/javascript">var UpDownUpDown = { ajaxurl: "'.admin_url ('admin-ajax.php').'" };</script>';
		}

		function guest_allowed()
		{
			return get_option ("updown_guest_allowed") == "allowed";
		}
		
		public function get_user_id()
		{
			if (is_user_logged_in())
				return get_current_user_id();
			
			// guests user-id = md5 hash of it's IP
			if ($this->guest_allowed())
				return md5 ($_SERVER['REMOTE_ADDR']);
			
			return 0;
		}
		
		public function get_post_votes_total ( $post_id )
		{
			global $wpdb;

			if ( !$post_id )
				return false;

			$result_query = $wpdb->get_results($wpdb->prepare("
															SELECT vote_count_up, vote_count_down 
															FROM ".$wpdb->base_prefix."up_down_post_vote_totals 
															WHERE post_id = %d", $post_id));

			return ( count($result_query) == 1 ? array( "up" => $result_query[0]->vote_count_up, "down" => $result_query[0]->vote_count_down ) : array( "up" => 0, "down" => 0 ));
		}

		public function get_comment_votes_total( $comment_id ) {
			global $wpdb;

			if ( !$comment_id )
				return false;

			$result_query = $wpdb->get_results($wpdb->prepare("
															SELECT vote_count_up, vote_count_down 
															FROM ".$wpdb->base_prefix."up_down_comment_vote_totals 
															WHERE comment_id = %d", $comment_id));

			return ( count($result_query) == 1 ? array( "up" => $result_query[0]->vote_count_up, "down" => $result_query[0]->vote_count_down ) : array( "up" => 0, "down" => 0 ));
		}

		public function get_post_user_vote( $user_id, $post_id ) {
			global $wpdb;
			return $wpdb->get_var($wpdb->prepare("
													SELECT vote_value 
													FROM ".$wpdb->base_prefix."up_down_post_vote
													WHERE voter_id =	%s
													AND post_id = %d", $user_id, $post_id));
		}

		public function get_comment_user_vote( $user_id, $comment_id ) {
			global $wpdb;
			return $wpdb->get_var($wpdb->prepare("
													SELECT vote_value 
													FROM ".$wpdb->base_prefix."up_down_comment_vote
													WHERE voter_id =	%s
													AND comment_id = %d", $user_id, $comment_id));
		}

		public function render_vote_badge( $vote_up_count = 0, $vote_down_count = 0, $votable = true, $existing_vote = 0 ) { 
			
			$img_up_status = '';
			$img_down_status = '';
			$vote_label = '';
			$up_classnames = '';
			$down_classnames = '';
			$down_classnames = '';

			$votable = (is_user_logged_in() || $this->guest_allowed()) && $votable;
			
			if ( $existing_vote > 0 )
				$img_up_status = '-on';
			elseif ( $existing_vote < 0 )
				$img_down_status = '-on';

			$up_img_src = plugins_url( '/images/arrow-up'.$img_up_status.'.png', __FILE__);
			$down_img_src = plugins_url( '/images/arrow-down'.$img_down_status.'.png', __FILE__);

			$vote_total_count = $vote_up_count - $vote_down_count;
			$vote_total_count_num = $vote_up_count + $vote_down_count;
			
			if ( $vote_down_count > 0 )
			{
				$vote_down_count *= -1;
				$down_classnames = ' updown-active';
			}
			
			if ( $vote_up_count > 0 ) {
				$vote_up_count = "+" . $vote_up_count;
				$up_classnames = ' updown-active';
			}

			if (!get_option ("updown_counter_sign") || get_option ("updown_counter_sign") == "yes")
				$updown_classnames .= ' updown-count-sign';
			else
				$updown_classnames .= ' updown-count-unsign';
			
			if ($vote_total_count > 0)
			{
				if (!get_option ("updown_counter_sign") || get_option ("updown_counter_sign") == "yes")
					$vote_total_count = "+" . $vote_total_count;
				$updown_classnames .= ' updown-pos-count';
				//				$updown_style = 'style="color:#5ebc40" ';
			}
			else if ($vote_total_count < 0)
			{
				if (get_option ("updown_counter_sign") == "no")
					$vote_total_count = substr ($vote_total_count, 1);
				$updown_classnames .= ' updown-neg-count';
				//				$updown_style = 'style="color:#bb5853" ';
			}
			
			if ( $vote_up_count == 0 && $vote_down_count == 0 && $votable )
			{
				$vote_up_count = '';
				$vote_down_count = '';
				$vote_total_count = '';
			}
			
			if ($votable)
				$vote_label = get_option ('updown_vote_text');
			else
				$vote_label .= get_option ('updown_votes_text');
				
			if ($votable)
				echo '<div><img class="updown-button updown-up-button" vote-direction="1" src="'.$up_img_src.'"></div>';

			if (get_option ("updown_counter_type") == "total")
			{
				echo '<div class="updown-total-count'.$updown_classnames.'" title="'.$vote_total_count_num.' vote'.($vote_total_count_num != 1 ? 's' : '').' so far">'.$vote_total_count.'</div>';
			}
			else
			{
				echo '<div class="updown-up-count'.$up_classnames.'">'.$vote_up_count.'</div>';
				echo '<div class="updown-down-count'.$down_classnames.'">'.$vote_down_count.'</div>';
			}

			if ($votable)
				echo '<div><img class="updown-button updown-down-button" vote-direction="-1" src="'.$down_img_src.'"></div>';

			echo '<div class="updown-label">'.$vote_label.'</div>';
			
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

			if (!is_user_logged_in() && !$this->guest_allowed())
			{
				$result['message'] = 'You must be logged in to vote.';
				die(json_encode($result));
			}
			
			$user_id = $this->get_user_id();

			//Validate expected params
			if ( ($_POST['post_id'] == null && $_POST['comment_id'] == null)
						|| $_POST['direction'] == null
				|| !$user_id )
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
			else
				die(json_encode($result));

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

			if ( $element_name == 'post' )
				$existing_vote = $this->get_post_user_vote( $user_id, $post_id );
			elseif	( $element_name == 'comment' )
				$existing_vote = $this->get_comment_user_vote( $user_id, $comment_id );
			
			if ( $existing_vote < 0 )
				$down_vote_delta -= 1;
			elseif ( $existing_vote > 0 )
				$up_vote_delta -= 1;

			//Update user vote
			if ($existing_vote != null) {
				$wpdb->query($wpdb->prepare("
					UPDATE ".$wpdb->base_prefix."up_down_".$element_name."_vote
					SET vote_value = %d
					WHERE voter_id = %s
						AND ".$element_name."_id = %d", $vote_value, $user_id, $element_id));
			} else {
				$wpdb->query($wpdb->prepare("
					INSERT INTO ".$wpdb->base_prefix."up_down_".$element_name."_vote
					( vote_value, ".$element_name."_id, voter_id )
					VALUES
					( %d, %d, %s )", $vote_value, $element_id, $user_id ));
				$existing_vote = 0;
			}

			//Update total
			if ($wpdb->query($wpdb->prepare("
					UPDATE ".$wpdb->base_prefix."up_down_".$element_name."_vote_totals
					SET vote_count_up = (vote_count_up + %d),
						vote_count_down = (vote_count_down + %d)
					WHERE ".$element_name."_id = %d", $up_vote_delta, $down_vote_delta, $element_id)) == 0)
				$wpdb->query($wpdb->prepare("
				INSERT INTO ".$wpdb->base_prefix."up_down_".$element_name."_vote_totals
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
				FROM ".$wpdb->base_prefix."up_down_".$element_name."_vote_totals
																WHERE ".$element_name."_id = %d", $element_id));

			die(json_encode($result));
		}
	} //class:UpDownPostCommentVotes
	
	//Create instance of plugin
	$up_down_plugin = new UpDownPostCommentVotes();

	//Handle plugin activation and update
	register_activation_hook( __FILE__, array( &$up_down_plugin, 'setup_plugin' ));
	if (function_exists ("register_update_hook"))
		register_update_hook ( __FILE__, array( &$up_down_plugin, 'setup_plugin' ));
	else
		add_action('init', array( &$up_down_plugin, 'setup_plugin' ), 1);

	//********************************************************************
	//Custom template tags

	function up_down_post_votes( $post_id, $allow_votes = true ) {
		global $up_down_plugin;

		if ( !$post_id )
			return false;
			
		$vote_counts = $up_down_plugin->get_post_votes_total( $post_id );
		$existing_vote = $up_down_plugin->get_post_user_vote( $up_down_plugin->get_user_id(), $post_id );

		echo '<div class="updown-vote-box updown-post" id="updown-post-'.$post_id.'" post-id="'.$post_id.'">';
		$up_down_plugin->render_vote_badge( $vote_counts["up"], $vote_counts["down"], $allow_votes, $existing_vote );
		echo '</div>';
	}

	function up_down_comment_votes( $comment_id, $allow_votes = true ) {
		global $up_down_plugin;

		if ( !$comment_id )
			return false;
			
		$vote_counts = $up_down_plugin->get_comment_votes_total( $comment_id );
		$existing_vote = $up_down_plugin->get_comment_user_vote( $up_down_plugin->get_user_id(), $comment_id );
		
		echo '<div class="updown-vote-box updown-comments" id="updown-comment-'.$comment_id.'" comment-id="'.$comment_id.'">';
		$up_down_plugin->render_vote_badge( $vote_counts["up"], $vote_counts["down"], $allow_votes, $existing_vote );
		echo '</div>';
	}

	
	//********************************************************************
	// Admin page
	
	function updown_plugin_menu()
	{
		add_options_page('UpDown Options', 'UpDownUpDown', 'manage_options', 'updown_plugin_menu_id', 'updown_options');
} 

	function updown_options()
	{
		global $up_down_plugin;
		if (!current_user_can('manage_options'))
		{
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		
		if (isset ($_POST['Submit']))
		{
			// guest allowed
			if (isset ($_POST['guest_allowed']) && $_POST['guest_allowed'] == "on")
			{
				update_option ("updown_guest_allowed", "allowed");
			}
			else
				update_option ("updown_guest_allowed", "not allowed");
			
			// style
			update_option ("updown_css", trim (strip_tags ($_POST['style'])));
			
			// counter type
			update_option ("updown_counter_type", trim (strip_tags ($_POST['counter'])));
			if ($_POST['counter-sign'] == "yes")
				update_option ("updown_counter_sign", "yes");
			else
				update_option ("updown_counter_sign", "no");
			
			// text
			update_option ("updown_vote_text", trim ($_POST['votetext']));
			update_option ("updown_votes_text", trim ($_POST['votestext']));
		}
		
		echo '<div class="wrap"><h2>UpDownUpDown Plugin Settings</h2><form name="form1" method="post" action=""><table width="100%" cellpadding="5" class="form-table"><tbody>';
		
		// permissions
		$allow_guests = get_option ("updown_guest_allowed") == "allowed" ? "checked " : "";
		echo '<tr valign="top"><th>Allow guests to vote:</th><td><input type="checkbox" name="guest_allowed" '.$allow_guests.'/> <span class="description">Allow guest visitors to vote without login? (Votes tracked by ip address)</span></td></tr>';
		
		//style
		$selected = "";
		echo '<tr valign="top"><th>Badge style:</th><td><select name="style">';
		if (get_option ("updown_css") == "default") $selected = "selected ";
		else $selected = "";
		echo '<option value="default"'.$selected.'>default</option>';
		if (get_option ("updown_css") == "simple") $selected = "selected ";
		else $selected = "";
		echo '<option value="simple"'.$selected.'>simple</option>';
		echo '</select> <span class="description">Choose basic badge style. You can also override CSS in your theme.</span></td></tr>';
		
		// counter type
		echo '<tr valign="top"><th>Counter type:</th><td>';
		if (!get_option ("updown_counter_type") || get_option ("updown_counter_type") == "plusminus")
			$selected = "checked ";
		else
			$selected = "";
		echo '<input type="radio" name="counter" value="plusminus" '.$selected.'/> Plus/Minus ';
		if (get_option ("updown_counter_type") == "total")
			$selected = "checked ";
		else
			$selected = "";
		echo '<input type="radio" name="counter" value="total" '.$selected.'/> Total ';
		echo ' <span class="description">Do you want to see the positive and negative counts, or only a total score?</td></tr>';
		
		// sign?
		echo '<tr valign="top"><th>Sign total counter:</th><td>';
		if (!get_option ("updown_counter_sign") || get_option ("updown_counter_sign") == "yes")
			$selected = "checked ";
		else
			$selected = "";
		echo '<input type="radio" name="counter-sign" value="yes" '.$selected.'/> sign ';
		if (get_option ("updown_counter_sign") == "no")
			$selected = "checked ";
		else
			$selected = "";
		echo '<input type="radio" name="counter-sign" value="no" '.$selected.'/> don\'t sign ';
		echo ' <span class="description">Should the total score contain a +/- in front of it?</span></td></tr>';
		
		//text
		echo '<tr valign="top"><th>Vote label if voteable:</th><td><input type="text" name="votetext" value="'.get_option('updown_vote_text').'"/> <span class="description">Text on the bottom of the buttons if the visitor is allowed to vote (HTML allowed)</span></td></tr>';
		echo '<tr valign="top"><th>Vote label if not voteable:</th><td><input type="text" name="votestext" value="'.get_option('updown_votes_text').'"/> <span class="description">Text on the bottom of the buttons if the visitor is <strong>not</strong> allowed to vote (HTML allowed)</span></td></tr>';
		
		echo '</tbody></table>';
		
		//save
		echo '<p class="submit"><input type="submit" name="Submit" class="button-primary" value="'.esc_attr('Save Changes').'" /></p>';
		echo '</form></div>';
	}
} 

?>
