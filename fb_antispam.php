<?php
/**
 * @package JS AntiSpam
 * @author Frank B&uuml;ltge
 * @version 1.2.1
 */
 
/*
Plugin Name: JS AntiSpam
Plugin URI: http://bueltge.de/wp-js-antispam-plugin/418/
Description: Simple antispam plugin without questions on Javascript-solution. Without JS-Solutions give it an textbox.
Author: Frank B&uuml;ltge
Author URI: http://bueltge.de/
Version: 1.2.1
Last Change: 26.11.2008 14:01:39
*/

/*
ACKNOWLEDGEMENTS
-----------------------------------------------------------------------------------------------
The JS-Idea is made by Jakub Vrána - http://php.vrana.cz/ochrana-formularu-proti-spamu.php
Code-Ideas from the plugin Raven's Antispam - http://kahi.cz/blog/ravens-antispam-for-wordpress
*/

// Pre-2.6 compatibility
if ( !defined( 'WP_CONTENT_URL' ) )
	define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( !defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );

define('BROWSER_QUESTION', false); 


/**
 * Images/ Icons in base64-encoding
 * @use function wpag_get_resource_url() for display
 */
if ( isset($_GET['resource']) && !empty($_GET['resource']) ) {
	# base64 encoding performed by base64img.php from http://php.holtsmark.no
	$resources = array(
		'js-antispam.gif' =>
		'R0lGODlhCwALAKIEAPb29tTU1P///5SUlP///wAAAAAAAAAAAC'.
		'H5BAEAAAQALAAAAAALAAsAAAMiSEoz+0wAB4cImK4BcNBbBz4N'.
		'tDUjA3QTuXqtwnnZiaJKAgA7'.
		'',
		'wp.png' =>
		'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAFfKj/FAAAAB3RJTUUH1wYQEiwG0'.
		'0adjQAAAAlwSFlzAAALEgAACxIB0t1+/AAAAARnQU1BAACxjwv8YQUAAABOUExURZ'.
		'wMDN7n93ut1kKExjFjnHul1tbn75S93jFrnP///1qUxnOl1sbe71KMxjFrpWOUzjl'.
		'7tYy13q3G5+fv95y93muczu/39zl7vff3//f//9Se9dEAAAABdFJOUwBA5thmAAAA'.
		's0lEQVR42iWPUZLDIAxDRZFNTMCllJD0/hddktWPRp6x5QcQmyIA1qG1GuBUIArwj'.
		'SRITkiylXNxHjtweqfRFHJ86MIBrBuW0nIIo96+H/SSAb5Zm14KnZTm7cQVc1XSMT'.
		'jr7IdAVPm+G5GS6YZHaUv6M132RBF1PopTXiuPYplcmxzWk2C72CfZTNaU09GCM3T'.
		'Ww9porieUwZt9yP6tHm5K5L2Uun6xsuf/WoTXwo7yQPwBXo8H/8TEoKYAAAAASUVO'.
		'RK5CYII='.
		'');
	
	if ( array_key_exists($_GET['resource'], $resources) ) {

		$content = base64_decode($resources[ $_GET['resource'] ]);

		$lastMod = filemtime(__FILE__);
		$client = ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false );
		// Checking if the client is validating his cache and if it is current.
		if ( isset($client) && (strtotime($client) == $lastMod) ) {
			// Client's cache IS current, so we just respond '304 Not Modified'.
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 304);
			exit;
		} else {
			// Image not cached or cache outdated, we respond '200 OK' and output the image.
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 200);
			header('Content-Length: '.strlen($content));
			header('Content-Type: image/' . substr(strrchr($_GET['resource'], '.'), 1) );
			echo $content;
			exit;
		}
	}
}


if ( !class_exists('fbjsas_check') ) {
	class fbjsas_check {
	
		// constructor
		function fbjsas_check() {
			global $wp_version;
			
			// set default options
			$this->options_array = array('fbjsas_nojsanswer' => 'Mensch',
																	 'fbjsas_advice' => 'Spamschutz: Kein JavaScript, dann gebe bitte das Wort &lt;strong&gt;%word%&lt;/strong&gt; ein.',
																	 'fbjsas_empty' => 'Du hast die Antispam-Frage nicht beantwortet. Dein Kommentar &lt;strong&gt;wurde nicht gespeichert&lt;/strong&gt;. Benutze den Zur&uuml;ck-Button und beantworte die Frage.',
																	 'fbjsas_wrong' => 'Du hast die Antispam-Frage nicht richtig beantwortet. Dein Kommentar &lt;strong&gt;wurde nicht gespeichert&lt;/strong&gt;. Benutze den Zur&uuml;ck-Button und beantworte die Frage richtig (unterscheide Gro&szlig;- und Kleinschreibung).',
																	 'fbjsas_delete' => '1',
																	 'fbjsas_delete_tb' => '',
																	 'fbjsas_delete_pb' => '',
																	 'fbjsas_trackback' => '1'
																	);
			
			// add class WPlize for options in WP
			$GLOBALS['WPlize'] = new WPlize(
																		 'fbjsas_settings',
																		 $this->options_array
																		 );
			
			// wp_hook add_action
			if ( function_exists('add_action') && is_admin() ) {
				if ( function_exists('register_uninstall_hook') )
					register_uninstall_hook(__FILE__, array(&$this, 'fbjsas_uninstall') );
				
				add_action('admin_menu', array(&$this, 'add_fbjsas_page') );
				add_action('in_admin_footer', array(&$this, 'add_fbjsas_admin_footer') );
				
				if ( version_compare( $wp_version, '2.6.999', '>' ) && file_exists(ABSPATH . '/wp-admin/admin-ajax.php') && (basename($_SERVER['QUERY_STRING']) == 'page=fb_antispam.php') ) {
					wp_enqueue_script( 'js_antispam_plugin_win_page',  plugins_url( $path = 'js-antispam/js/page.php' ), array('jquery') );
				} elseif ( version_compare( $wp_version, '2.6.999', '<' ) && file_exists(ABSPATH . '/wp-admin/admin-ajax.php') && (basename($_SERVER['QUERY_STRING']) == 'page=fb_antispam.php') ) {
					wp_enqueue_script( 'js_antispam_plugin_win_page',  plugins_url( $path = 'js-antispam/js/page_s27.php' ), array('jquery') );
				}
			}
			
			if ( function_exists('add_action') ) {
				add_action( 'init', array(&$this,'textdomain') );
				
				add_action('comment_form', array(&$this, 'comment_form'));
				add_action('comment_post', array(&$this, 'comment_post'));
			}
			
			$this->answer = $this->answer_arr();

			/**
			 * Retrieve the url to the plugins directory.
			 *
			 * @package WordPress
			 * @since 2.6.0
			 *
			 * @param string $path Optional. Path relative to the plugins url.
			 * @return string Plugins url link with optional path appended.
			 */
			if ( !function_exists('plugins_url') ) {
				function plugins_url($path = '') {
					if ( function_exists( 'is_ssl' ) ) {
						$scheme = ( is_ssl() ? 'https' : 'http' );
					} else {
						$scheme = ( 'http' );
					}
					$url = WP_PLUGIN_URL;
					if ( 0 === strpos($url, 'http') ) {
						if ( is_ssl() )
							$url = str_replace( 'http://', "{$scheme}://", $url );
					}
				
					if ( !empty($path) && is_string($path) && strpos($path, '..') === false )
						$url .= '/' . ltrim($path, '/');
				
					return $url;
				}
			}
			
		}
		
		
		// active for multilanguage
		function textdomain() {
		
			if (function_exists('load_plugin_textdomain')) {
				if ( !defined('WP_PLUGIN_DIR') ) {
					load_plugin_textdomain('js_antispam', str_replace( ABSPATH, '', dirname(__FILE__) ) . '/languages');
				} else {
					load_plugin_textdomain('js_antispam', false, dirname( plugin_basename(__FILE__) ) . '/languages');
				}
			}
		}
		
		
		function answer_arr() {
	
			$words = preg_split("/\r\n/", $GLOBALS['WPlize']->get_option('fbjsas_nojsanswer') );
			foreach ( (array) $words as $key => $word ) {
				$word = trim($word);
			}
			
			$arr_count = count($words);
			$ran_nojsanswer = time() % $arr_count;
			$select_answer = $words[$ran_nojsanswer];
			return $select_answer;
		}
	
	
		// improves comment-form
		function comment_form($form) {
			
			$select_answer = $this->answer;
	
			if ($select_answer == '') {
				$select_answer = 'Spamschutz';
			}
			
			// split the answer
			$answer_len = strlen($select_answer); //length of string
			$answer_splitpoint = rand(1, $answer_len-1); // rand in 1-sting length
			$answer[0] = substr($select_answer, 0, $answer_splitpoint);
			$answer[1] = substr($select_answer, $answer_splitpoint);
	
			// add hidden input by script & visible input inside <noscript>
			$fbjsas_advice = $GLOBALS['WPlize']->get_option('fbjsas_advice');
			$fbjsas_advice = str_replace('%word%', $answer[0] . '<span style="display:none;">+</span>' . $answer[1], $fbjsas_advice);
	
			// for camino & seamonkey browsers (problems reported) display
			$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
			if (
				( false !== strpos( $ua, 'seamonkey') )
				OR ( false !== strpos( $ua, 'camino') )
				OR BROWSER_QUESTION
				) {
	
			?>
	
			<p>
				<label for="nojsanswer"><?php echo $fbjsas_advice; ?></label>&nbsp;
				<input type="text" name="nojsanswer" id="nojsanswer" />
				<input type="hidden" name="select_answer0" id="select_answer0" value="<?php echo $answer[0]; ?>" />
				<input type="hidden" name="select_answer1" id="select_answer1" value="<?php echo $answer[1]; ?>" />
			</p>
			
			<?php
			
			// for other browsers - add hidden input by script & visible input inside <noscript>
			} else {
	
			?>
			
			<!-- JS AntiSpam Plugin for WordPress by Frank Bueltge | bueltge.de -->
			<noscript><p><label for="nojsanswer"><?php echo $fbjsas_advice; ?></label> <input type="text" name="nojsanswer" id="nojsanswer" /><input type="hidden" name="select_answer0" id="select_answer0" value="<?php echo $answer[0]; ?>" /><input type="hidden" name="select_answer1" id="select_answer1" value="<?php echo $answer[1]; ?>" /></p></noscript>
			<script type="text/javascript">/* <![CDATA[ */ document.write('<p><input type="hidden" name="nojsanswer" value="<?php echo $answer[0]; ?>' + '<?php echo $answer[1]; ?>" \/><input type="hidden" name="select_answer0" value="<?php echo $answer[0]; ?>" \/><input type="hidden" name="select_answer1" value="<?php echo $answer[1]; ?>" \/><\/p>'); /* ]]> */</script>
			
			<?php
			}
		}
	
		// checks the answer
		function comment_post($post_ID) {
			global $comment_content, $comment_type;
	
			$answer = trim($_POST['nojsanswer']);
			$select_answer = trim($_POST['select_answer0']).trim($_POST['select_answer1']);
	
			$fbjsas_empty = $GLOBALS['WPlize']->get_option('fbjsas_empty');
			$fbjsas_empty = $this->fbjsas_layout($fbjsas_empty);
			$fbjsas_wrong = $GLOBALS['WPlize']->get_option('fbjsas_wrong');
			$fbjsas_wrong = $this->fbjsas_layout($fbjsas_wrong);
	
			$errors['empty'] = $fbjsas_empty;
			$errors['wrong'] = $fbjsas_wrong;
	
			if ( ($GLOBALS['WPlize']->get_option('fbjsas_trackback') == '1') && ($comment_type == 'trackback' || $comment_type == 'pingback' || $comment_type === '') ) {
				if( $answer == '' ) {
					$this->fbjsas_delete_comment($post_ID);
					wp_die($errors['empty'] .'<br /><br /><strong>' . __('Your comment:', 'js_antispam') . '</strong><br /><em>'. $comment_content .'</em>');
				} elseif ( $answer != $select_answer ) {
					$this->fbjsas_delete_comment($post_ID);
					wp_die($errors['wrong'] .'<br /><br /><strong>' . __('Your comment:', 'js_antispam') . '</strong><br /><em>'. $comment_content .'</em>');
				}
			}
			return $post_ID;
		}
	
		// Well, the comment was saved already, so delete it
		function fbjsas_delete_comment($post_ID) {
			global $wpdb, $comment_count_cache, $comments, $comment_type; 
	
			$entry_id = (int) $_POST['comment_post_ID'];
			
			if ($GLOBALS['WPlize']->get_option('fbjsas_delete') == '1') {
				// Kill comment-spam
				$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID = {$post_ID} AND comment_type = '' ");
			} else {
				$wpdb->query("UPDATE $wpdb->comments SET comment_approved = 'spam' WHERE comment_ID = {$post_ID} AND comment_type = '' ");
			}
	
			if ($GLOBALS['WPlize']->get_option('fbjsas_delete_tb') == '1') {
				// Kill trackback-spam
				$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID = {$post_ID} AND comment_type = 'trackback' ");
			} else {
				$wpdb->query("UPDATE $wpdb->comments SET comment_approved = 'spam' WHERE comment_ID = {$post_ID} AND comment_type = 'trackback' ");
			}
	
			if ($GLOBALS['WPlize']->get_option('fbjsas_delete_pb') == '1') {
				// Kill pingback-spam
				$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID = {$post_ID} AND comment_type = 'pingback' ");
			} else {
				$wpdb->query("UPDATE $wpdb->comments SET comment_approved = 'spam' WHERE comment_ID = {$post_ID} AND comment_type = 'pingback' ");
			}
	
			// Recount the comments
			$count = $wpdb->get_var("SELECT COUNT(*) from $wpdb->comments WHERE comment_post_id = {$entry_id} AND comment_approved = '1'");	
			$wpdb->query("UPDATE $wpdb->posts SET comment_count = $count WHERE ID = {$entry_id}");
			$comment_count_cache[$entry_id]--;
		}
		
		
		// add in wp-adminmenu
		function add_fbjsas_page() {
			global $wp_version;
			
			if ( function_exists('add_management_page') && current_user_can('manage_options') ) {
			
				$menutitle = '';
				if ( version_compare( $wp_version, '2.6.999', '>' ) ) {
					$menutitle = '<img src="' . $this->fbjsas_get_resource_url('js-antispam.gif') . '" alt="" />' . ' ';
				}
				$menutitle .= __('JS AntiSpam', 'js_antispam');
				
				if ( version_compare( $wp_version, '2.6.999', '>' ) && function_exists('add_contextual_help') ) {
					$hook = add_options_page( __('JS AntiSpam', 'js_antispam'), $menutitle, 8, basename(__FILE__), array(&$this, 'fbjsas_page') );
					add_contextual_help( $hook, __('<a href="http://wordpress.org/extend/plugins/js-antispam/">Documentation</a>', 'js_antispam') );
					//add_filter( 'contextual_help', array(&$this, 'contextual_help') );
				} else {
					add_options_page( __('JS AntiSpam', 'js_antispam'), $menutitle, 8, basename(__FILE__), array(&$this, 'fbjsas_page') );
				}
				
				if ( version_compare( $wp_version, '2.6.999', '>' ) ) {
					$plugin = plugin_basename(__FILE__);
					add_filter( 'plugin_action_links_' . $plugin, array(&$this, 'add_fbjsas_plugin_actions_new') );
				} else {
					add_filter( 'plugin_action_links', array(&$this, 'add_fbjsas_plugin_actions'), 10, 2 );
				}
			}
		}
		

		/**
		 * Adds an action link to the plugins page
		 */
		function add_fbjsas_plugin_actions($links, $file){
			static $this_plugin;
		
			if( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);
		
			if( $file == $this_plugin ) {
				$settings_link = '<a href="options-general.php?page=fb_antispam.php">' . __('Settings') . '</a>';
				$links         = array_merge( array($settings_link), $links); // before other links
			}
			return $links;
		}
		
		
		/**
		 * @version WP 2.7
		 * Add action link(s) to plugins page
		 */
		function add_fbjsas_plugin_actions_new( $links ) {
			
			$settings_link = '<a href="options-general.php?page=fb_antispam.php">' . __('Settings') . '</a>';
			array_unshift( $links, $settings_link );
			
			return $links;
		}
		
		
		/**
		 * Display Images/ Icons in base64-encoding
		 * @return $resourceID
		 */
		function fbjsas_get_resource_url($resourceID) {
		
			return trailingslashit( get_bloginfo('url') ) . '?resource=' . $resourceID;
		}
		
		
		/**
		 * credit in wp-footer
		 */
		function add_fbjsas_admin_footer() {
			
			if ( basename($_SERVER['REQUEST_URI']) == 'options-general.php?page=fb_antispam.php' ) {
				$plugin_data = get_plugin_data( __FILE__ );
				printf('%1$s plugin | ' . __('Version') . ' %2$s | ' . __('Author') . ' %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']);
			}
		}
		
		
		// replace in layout
		function fbjsas_layout( $string = '' ) {
		
			$string = str_replace('%word%', NOJSANSWER, $string);
			return $string;
		}
		
		
		function fbjsas_page() {
			global $wp_version;
		
			if ( isset($_POST['action']) && 'update' == $_POST['action'] ) {
				check_admin_referer('fbjsas_settings_form');
				if ( current_user_can('manage_options') ) {
				
					// init value
					$update_options = array();
					
					// set value
					foreach ($this->options_array as $key => $value) {
						$update_options[$key] = stripslashes_deep(trim($_POST[$key]));
					}
					
					// save value
					if ($update_options) {
						$GLOBALS['WPlize']->update_option($update_options);
					}
					
					echo '<div id="message" class="updated fade"><p>' . __('The options have been saved!', 'js_antispam') . '</p></div>';

				} else {
				
					echo '<div id="message" class="error"><p>' . __('Options not update - you don&lsquo;t have the privilidges to do this!', 'js_antispam') . '</p></div>';
				
				}
			}
		
			if ( isset($_POST['action']) && 'deactivate' == $_POST['action'] && $_POST['fbjsas_uninstall'] ) {
				check_admin_referer('fbjsas_uninstall_form');
				if ( current_user_can('manage_options') && isset($_POST['deinstall_yes']) ) {
					
					$GLOBALS['WPlize']->delete_option();
					
					// old <1.2
					delete_option('fbjsas_nojsanswer');
					delete_option('fbjsas_advice');
					delete_option('fbjsas_empty');
					delete_option('fbjsas_wrong');
					delete_option('fbjsas_delete');
					delete_option('fbjsas_delete_tb');
					delete_option('fbjsas_delete_pb');
					delete_option('fbjsas_trackback');
			
					echo '<div id="message" class="updated fade"><p>' . __('The options have been deleted!', 'js_antispam') . '</p></div>';
				} else {
					echo '<div id="message" class="error"><p>' . __('Entries was not delleted - check the checkbox or you don&lsquo;t have the privilidges to do this!', 'js_antispam') . '</p></div>';
				}
			}
			
			$fbjsas_nojsanswer = $GLOBALS['WPlize']->get_option('fbjsas_nojsanswer');
			$fbjsas_advice     = $GLOBALS['WPlize']->get_option('fbjsas_advice');
			$fbjsas_empty      = $GLOBALS['WPlize']->get_option('fbjsas_empty');
			$fbjsas_wrong      = $GLOBALS['WPlize']->get_option('fbjsas_wrong');
			$fbjsas_delete     = $GLOBALS['WPlize']->get_option('fbjsas_delete');
			$fbjsas_delete_tb  = $GLOBALS['WPlize']->get_option('fbjsas_delete_tb');
			$fbjsas_delete_pb  = $GLOBALS['WPlize']->get_option('fbjsas_delete_pb');
			$fbjsas_trackback  = $GLOBALS['WPlize']->get_option('fbjsas_trackback');
		?>
		
			<div class="wrap" id="config">		
				<h2><?php _e('JS AntiSpam', 'js_antispam'); ?></h2>
				<br class="clear" />
		
				<div id="poststuff" class="ui-sortable">
					<div id="fbjsas_settings_win_opt" class="postbox <?php echo $GLOBALS['WPlize']->get_option('fbjsas_settings_win_opt'); ?>" >
						<h3><?php _e('Settings', 'js_antispam'); ?></h3>
						<div class="inside">
							
							<form method="post" id="fbjsas_options" action="">
								<?php if (function_exists('wp_nonce_field') === true) wp_nonce_field('fbjsas_settings_form'); ?>
								
								<table summary="fbjsas_options" class="form-table">
									<tr valign="top">
										<th scope="row"><label for="fbjsas_nojsanswer"><?php _e('Reply', 'js_antispam'); ?></label></th>
										<td>
											<textarea name="fbjsas_nojsanswer" cols="60" rows="3" id="fbjsas_nojsanswer" style="width: 99%;" ><?php echo $fbjsas_nojsanswer; ?></textarea>
											<br />
											<?php _e('Possible answers. Separate multiple answers through a carriage return. Complex answers will make things more difficult not only for spam robots but visitors as well. The standard reply when no answer is given is "Spam Protection".', 'js_antispam'); ?>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="fbjsas_advice"><?php _e('Warning', 'js_antispam'); ?></label></th>
										<td>
											<textarea name="fbjsas_advice" cols="60" rows="3" id="fbjsas_advice" style="width: 99%;" /><?php echo $fbjsas_advice; ?></textarea>
											<br />
											<?php _e('Assign the password hint %word% to see it displayed when prompted.', 'js_antispam'); ?>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="fbjsas_empty"><?php _e('Empty', 'js_antispam'); ?></label></th>
										<td>
											<textarea rows="3" cols="60" name="fbjsas_empty" id="fbjsas_empty" style="width: 99%;" /><?php echo $fbjsas_empty; ?></textarea>
											<br />
											<?php _e('The warning you wish to give when the field is <strong>not</strong> filled out. xHTML allowed', 'js_antispam'); ?>
											</td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="fbjsas_wrong"><?php _e('Wrong', 'js_antispam'); ?></label></th>
										<td>
											<textarea rows="3" cols="60" name="fbjsas_wrong" id="fbjsas_wrong" style="width: 99%;" /><?php echo $fbjsas_wrong; ?></textarea>
											<br />
											<?php _e('The warning you wish to give when the field is <strong>not</strong> filled out correctly. xHTML allowed.', 'js_antispam'); ?>
											</td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="fbjsas_delete"><?php _e('Immediately delete comments', 'js_antispam'); ?></label></th>
										<td><input name="fbjsas_delete" id="fbjsas_delete" value='1' <?php if ($fbjsas_delete == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <?php _e('Should comments recognized as spam be immediately deleted or saved in the database as spam?', 'js_antispam'); ?> <br /><?php _e('<strong>Careful! (This also applies to the fields Trackback and Pingback below).</strong> Use this option when you do not wish to have information in your database about "spam" content. Content marked as spam in the database can be viewed with a plugin such as <a href=\'http://bueltge.de/wp-spamviewer-zum-loeschen-und-retten-von-spam/255\' title=\'to the plugin\'>SpamViewer</a> and recovered if applicable. Furthermore, using this option will cause comments re-posted after the spam key was entered incorrectly once to be be treated as double posts, which is not allowed: at least one character must be changed.', 'js_antispam'); ?></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="fbjsas_delete_tb"><?php _e('Delete trackback immediately', 'js_antispam'); ?></label></th>
										<td><input name="fbjsas_delete_tb" id="fbjsas_delete_tb" value='1' <?php if ($fbjsas_delete_tb == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <?php _e('Should trackbacks recognized as spam be immediately deleted or saved in the database as spam?', 'js_antispam'); ?></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="fbjsas_delete_pb"><?php _e('Delete pingback immediately', 'js_antispam'); ?></label></th>
										<td><input name="fbjsas_delete_pb" id="fbjsas_delete_pb" value='1' <?php if ($fbjsas_delete_pb == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <?php _e('Should pingbacks recognized as spam be immediately deleted or saved in the database as spam', 'js_antispam'); ?></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="fbjsas_trackback"><?php _e('Trackback/Pingback', 'js_antispam'); ?></label></th>
										<td><input name="fbjsas_trackback" id="fbjsas_trackback" value='1' <?php if ($fbjsas_trackback == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <?php _e('Allow trackbacks and pingbacks to be approved without filtering. <br /> Activate this option when the option "Delete immediately" is active, otherwise tracbacks ans pingbacks will be immediately deleted and not saved in the database.', 'js_antispam'); ?></td>
									</tr>
									</table>
									
									<p class="submit">
										<input type="submit" name="Submit" value="<?php _e('Save Changes', 'js_antispam'); ?> &raquo;" />
										<input type="hidden" name="action" value="update" />
									</p>
							</form>
							
						</div>
					</div>
				</div>
				
				<div id="poststuff" class="ui-sortable">
					<div id="fbjsas_settings_win_uninstall" class="postbox <?php echo $GLOBALS['WPlize']->get_option('fbjsas_settings_win_uninstall'); ?>" >
						<h3><?php _e('Uninstall options', 'js_antispam') ?></h3>
						<div class="inside">
							<p><?php _e('Click this button to delete settings of this plugin. Deactivating JS AntiSpam plugin remove any data that may have been created.', 'js_antispam'); ?></p>
							
							<form name="form2" method="post" action="<?php echo $location; ?>">
								<?php if (function_exists('wp_nonce_field') === true) wp_nonce_field('fbjsas_uninstall_form'); ?>
								<p id="submitbutton">
									<input type="hidden" name="action" value="deactivate" />
									<input class="button" type="submit" name="fbjsas_uninstall" value="<?php _e('Delete Options', 'js_antispam'); ?> &raquo;" />
									<input type="checkbox" name="deinstall_yes" />
								</p>
							</form>
							
						</div>
					</div>
				</div>
				
				<div id="poststuff" class="ui-sortable">
					<div id="fbjsas_settings_win_about" class="postbox <?php echo $GLOBALS['WPlize']->get_option('fbjsas_settings_win_about'); ?>" >
						<h3 id="about"><?php _e('Information on the plugin', 'sayfasayacprc') ?></h3>
						<div class="inside">
							<p><?php _e('Further information: Visit the <a href=\'http://bueltge.de/wp-js-antispam-plugin/418\'>plugin homepage</a> for further information or to grab the latest version of this plugin.', 'js_antispam'); ?><br />&copy; Copyright 2007 - <?php echo date('Y'); ?> <a href="http://bueltge.de">Frank B&uuml;ltge</a> | <?php _e('You want to thank me? Visit my <a href=\'http://bueltge.de/wunschliste\'>wishlist</a>.', 'js_antispam'); ?></p>
						</div>
					</div>
				</div>
		
			</div>
		<?php
		}		
		
	} // end class
}


if ( !class_exists('WPlize') ) {
	require_once('inc/WPlize.php');
}


/* Initialise outselves */
if ( class_exists('fbjsas_check') && class_exists('WPlize') && function_exists('is_admin') ) {
	$fbjsas_injector = new fbjsas_check();
}


if ( isset($fbjsas_injector) && function_exists( 'add_action' ) ) {
	add_action( 'fbjsas_check',  array(&$fbjsas_injector, 'init') );
}
?>