<?php
/*
Plugin Name: JS AntiSpam
Plugin URI: http://bueltge.de/wp-js-antispam-plugin/418
Description: Simple antispam plugin without questions on Javascript-solution. Without JS-Solutions give it an textbox. The answers can you write in the <a href="admin.php?page=fb_antispam.php">options</a>.
Author: Frank Bueltge
Author URI: http://bueltge.de
Version: 0.8
*/

/*
ACKNOWLEDGEMENTS
-----------------------------------------------------------------------------------------------
The JS-Idea is made by Jakub Vrána - http://php.vrana.cz/ochrana-formularu-proti-spamu.php
Code-Ideas from the plugin Raven's Antispam - http://kahi.cz/blog/ravens-antispam-for-wordpress
*/

if(function_exists('load_plugin_textdomain'))
	load_plugin_textdomain('js_antispam', 'wp-content/plugins');

define('BROWSER_QUESTION', false); 

// plugin itself
$fbjsas = new fbjsas_check(); 

class fbjsas_check {

	// constructor
	function fbjsas_check() {
		add_action('comment_form', array(&$this, 'comment_form'));
		add_action('comment_post', array(&$this, 'comment_post'));

		$this->answer = $this->answer_arr();
	}

	function answer_arr() {

		$words = preg_split("/\r\n/", get_option('fbjsas_nojsanswer'));
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
		$answer_len = strlen($select_answer);
		$answer_splitpoint = rand(1, $answer_len-1);
		$answer[0] = substr($select_answer, 0, $answer_splitpoint);
		$answer[1] = substr($select_answer, $answer_splitpoint);

		// add hidden input by script & visible input inside <noscript>
		$fbjsas_advice = stripslashes(get_option('fbjsas_advice'));
		$fbjsas_advice = str_replace('%word%', $answer[0].'<span style="display:none;">+</span>'.$answer[1], $fbjsas_advice);

		// for camino & seamonkey browsers (problems reported) display
		$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
		if (
			(false !== strpos($ua, 'seamonkey'))
			OR (false !== strpos($ua, 'camino'))
			OR BROWSER_QUESTION
			) {

		?>

		<p><label for="nojsanswer"><?php echo $fbjsas_advice; ?></label> <input type="text" name="nojsanswer" id="nojsanswer" /><input type="hidden" name="select_answer0" id="select_answer0" value="<?php echo $answer[0]; ?>" /><input type="hidden" name="select_answer1" id="select_answer1" value="<?php echo $answer[1]; ?>" /></p>
		
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

		$fbjsas_empty = stripslashes(get_option('fbjsas_empty'));
		$fbjsas_empty = fbjsas_layout($fbjsas_empty);
		$fbjsas_wrong = stripslashes(get_option('fbjsas_wrong'));
		$fbjsas_wrong = fbjsas_layout($fbjsas_wrong);

		$errors['empty'] = $fbjsas_empty;
		$errors['wrong'] = $fbjsas_wrong;

		if ((get_option('fbjsas_trackback') == '1') && ($comment_type == 'trackback' || $comment_type == 'pingback' || $comment_type === '')) {
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

		$entry_id 			= (int) $_POST['comment_post_ID'];
		
		if (get_option('fbjsas_delete') == '1') {
			// Kill comment-spam
			$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID = {$post_ID} AND comment_type = '' ");
		} else {
			$wpdb->query("UPDATE $wpdb->comments SET comment_approved = 'spam' WHERE comment_ID = {$post_ID} AND comment_type = '' ");
		}

		if (get_option('fbjsas_delete_tb') == '1') {
			// Kill trackback-spam
			$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID = {$post_ID} AND comment_type = 'trackback' ");
		} else {
			$wpdb->query("UPDATE $wpdb->comments SET comment_approved = 'spam' WHERE comment_ID = {$post_ID} AND comment_type = 'trackback' ");
		}

		if (get_option('fbjsas_delete_pb') == '1') {
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
}

// replace in layout
function fbjsas_layout($string='') {

	$string = str_replace('%word%', NOJSANSWER, $string);
	return $string;
}

function fbjsas_page() {

	if($_POST['fbjsas_nojsanswer']){
		fbjsas_update();
	}
?>

	<div class="wrap" id="config">		
		<h2><?php _e('JS AntiSpam', 'js_antispam'); ?></h2>
		<fieldset class="options">
		<legend><?php _e('Options', 'js_antispam'); ?></legend>
		<form method="post" id="fbjsas_options" action="">
			<p><strong><?php _e('Reply:', 'js_antispam'); ?> </strong><small><?php _e('Possible answers. Separate multiple answers through a carriage return. Complex answers will make things more difficult not only for spam robots but visitors as well. The standard reply when no answer is given is "Spam Protection".', 'js_antispam'); ?></small><br /><textarea name="fbjsas_nojsanswer" cols="60" rows="4" id="fbjsas_nojsanswer" style="width: 99%;" ><?php form_option('fbjsas_nojsanswer'); ?></textarea></p>
			<p><strong><?php _e('Warning:', 'js_antispam'); ?> </strong><small><?php _e('Assign the password hint %word% to see it displayed when prompted.', 'js_antispam'); ?></small><br /><textarea class="code" rows="1" cols="60" name="fbjsas_advice" id="fbjsas_advice" style="width: 99%;" /><?php echo stripslashes(get_option('fbjsas_advice')); ?></textarea></p>
			<p><strong><?php _e('Empty:', 'js_antispam'); ?> </strong><small><?php _e('The warning you wish to give when the field is <strong>not</strong> filled out. xHTML allowed', 'js_antispam'); ?></small><br /><textarea class="code" rows="3" cols="60" name="fbjsas_empty" id="fbjsas_empty" style="width: 99%;" /><?php echo stripslashes(get_option('fbjsas_empty')); ?></textarea></p>
			<p><strong><?php _e('Wrong:', 'js_antispam'); ?> </strong><small><?php _e('The warning you wish to give when the field is <strong>not</strong> filled out correctly. xHTML allowed.', 'js_antispam'); ?></small><br /><textarea class="code" rows="3" cols="60" name="fbjsas_wrong" id="fbjsas_wrong" style="width: 99%;" /><?php echo stripslashes(get_option('fbjsas_wrong')); ?></textarea></p>
			<p><strong><?php _e('Immediately delete comments:', 'js_antispam'); ?> </strong><br /><input name="fbjsas_delete" id="fbjsas_delete" value='1' <?php if(get_option('fbjsas_delete') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <small><?php _e('Should comments recognized as spam be immediately deleted or saved in the database as spam?', 'js_antispam'); ?> <br /><strong><?php _e('<srong>Careful! (This also applies to the fields Trackback and Pinbback below).</strong> Use this option when you do not wish to have information in your database about "spam" content. Content marked as spam in the database can be viewed with a plugin such as <a href=\'http://bueltge.de/wp-spamviewer-zum-loeschen-und-retten-von-spam/255\' title=\'to the plugin\'>SpamViewer</a> and recovered if applicable. Furthermore, using this option will cause comments re-posted after the spam key was entered incorrectly once to be be treated as double posts, which is not allowed: at least one character must be changed.', 'js_antispam'); ?></small></p>
			<p><strong><?php _e('Delete trackback immediately:', 'js_antispam'); ?> </strong><br /><input name="fbjsas_delete_tb" id="fbjsas_delete_tb" value='1' <?php if(get_option('fbjsas_delete_tb') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <small><?php _e('Should trackbacks recognized as spam be immediately deleted or saved in the database as spam?', 'js_antispam'); ?></small></p>
			<p><strong><?php _e('Delete pingback immediately:', 'js_antispam'); ?> </strong><br /><input name="fbjsas_delete_pb" id="fbjsas_delete_pb" value='1' <?php if(get_option('fbjsas_delete_pb') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <small><?php _e('Should pingbacks recognized as spam be immediately deleted or saved in the database as spam', 'js_antispam'); ?></small></p>
			<p><strong><?php _e('Trackback/Pingback:', 'js_antispam'); ?> </strong><br /><input name="fbjsas_trackback" id="fbjsas_trackback" value='1' <?php if(get_option('fbjsas_trackback') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <small><?php _e('Allow trackbacks and pingbacks to be approved without filtering. <br /> Activate this option when the option "Delete immediately" is active, otherwise tracbacks ans pingbacks will be immediately deleted and not saved in the database.', 'js_antispam'); ?></small></p>
			<p class="submit"><input class="submit" type="submit" name="Submit" tabindex="10" value="<?php _e('Update Options'); ?> &raquo;" /></p>
			<input type="hidden" name="page_options" value="'dofollow_timeout'" />
		</form>
		</fieldset>
		<hr />
		<p><small><?php _e('Further information: Visit the <a href=\'http://bueltge.de/wp-js-antispam-plugin/418\'>plugin homepage</a> for further information or to grab the latest version of this plugin.', 'js_antispam'); ?><br />&copy; Copyright 2007 <a href="http://bueltge.de">Frank B&uuml;ltge</a> | <?php _e('You want to thank me? Visit my <a href=\'http://bueltge.de/wunschliste\'>wishlist</a>.', 'js_antispam'); ?></small></p>
	</div>

<?php
}

// update options
function fbjsas_update() {
	if (!empty($_POST)) {
		update_option('fbjsas_nojsanswer', $_POST['fbjsas_nojsanswer']);
		update_option('fbjsas_advice', $_POST['fbjsas_advice']);
		update_option('fbjsas_empty', $_POST['fbjsas_empty']);
		update_option('fbjsas_wrong', $_POST['fbjsas_wrong']);
		update_option('fbjsas_delete', $_POST['fbjsas_delete']);
		update_option('fbjsas_delete_tb', $_POST['fbjsas_delete_tb']);
		update_option('fbjsas_delete_pb', $_POST['fbjsas_delete_pb']);
		update_option('fbjsas_trackback', $_POST['fbjsas_trackback']);

		echo '<div class="updated"><p>' . __('The options have been saved!', 'js_antispam') . '</p></div>';
	}
}

// install options
function fbjsas_install(){
	add_option('fbjsas_nojsanswer', 'Mensch');
	add_option('fbjsas_advice', 'Kein JavaScript, dann gebe bitte das Wort &#8222;<strong>%word%</strong>&#8220; ein');
	add_option('fbjsas_empty', 'Du hast die Antispam-Frage nicht beantwortet. Dein Kommentar <strong>wurde nicht gespeichert</strong>. Benutze den "Zur&uuml;ck"-Button und beantworte die Frage.');
	add_option('fbjsas_wrong', 'Du hast die Antispam-Frage nicht richtig beantwortet. Dein Kommentar <strong>wurde nicht gespeichert</strong>. Benutze den "Zur&uuml;ck"-Button und beantworte die Frage richtig (unterscheide Gro&szlig;- und Kleinschreibung).');
	add_option('fbjsas_trackback', '1');
}

// add in wp-adminmenu
function add_fbjsas_page() {

	if(function_exists('add_options_page')) {
		add_options_page('JS AntiSpam', 'JS AntiSpam', 8, basename(__FILE__), 'fbjsas_page');
	}
}

// wp_hook add_action
if(function_exists('add_action')) {
	if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
		add_action('init', 'fbjsas_install');
	}
	add_action('admin_menu', 'add_fbjsas_page');
}

?>