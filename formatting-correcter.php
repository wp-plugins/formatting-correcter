<?php
/**
Plugin Name: Formatting correcter
Plugin Tag: tag
Description: <p>The plugin detects any formatting issues in your posts such as "double space" or any other issues that you may configure and proposes to correct them accordingly. </p>
Version: 1.0.2


Framework: SL_Framework
Author: sedLex
Author URI: http://www.sedlex.fr/
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Plugin URI: http://wordpress.org/plugins/formatting-correcter/
License: GPL3
*/

//Including the framework in order to make the plugin work

require_once('core.php') ; 

/** ====================================================================================================================================================
* This class has to be extended from the pluginSedLex class which is defined in the framework
*/
class formatting_correcter extends pluginSedLex {
	
	/** ====================================================================================================================================================
	* Plugin initialization
	* 
	* @return void
	*/
	static $instance = false;

	protected function _init() {
		global $wpdb ; 

		// Name of the plugin (Please modify)
		$this->pluginName = 'Formatting correcter' ; 
		
		// The structure of the SQL table if needed (for instance, 'id_post mediumint(9) NOT NULL, short_url TEXT DEFAULT '', UNIQUE KEY id_post (id_post)') 
		$this->tableSQL = "id mediumint(9) NOT NULL AUTO_INCREMENT, id_post mediumint(9) NOT NULL, numerror mediumint(9) NOT NULL, date_check DATETIME, UNIQUE KEY id (id)" ; 
		// The name of the SQL table (Do no modify except if you know what you do)
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 

		//Configuration of callbacks, shortcode, ... (Please modify)
		// For instance, see 
		//	- add_shortcode (http://codex.wordpress.org/Function_Reference/add_shortcode)
		//	- add_action 
		//		- http://codex.wordpress.org/Function_Reference/add_action
		//		- http://codex.wordpress.org/Plugin_API/Action_Reference
		//	- add_filter 
		//		- http://codex.wordpress.org/Function_Reference/add_filter
		//		- http://codex.wordpress.org/Plugin_API/Filter_Reference
		// Be aware that the second argument should be of the form of array($this,"the_function")
		// For instance add_action( "wp_ajax_foo",  array($this,"bar")) : this function will call the method 'bar' when the ajax action 'foo' is called
		
		add_action( "wp_ajax_replaceWithProposedModifications_FR",  array($this,"replaceWithProposedModifications_FR")) ; 
		add_action( "wp_ajax_resetFormattingIssue",  array($this,"resetFormattingIssue")) ; 
		add_action( "wp_ajax_viewFormattingIssue",  array($this,"viewFormattingIssue")) ; 
		add_action( "wp_ajax_showEditorModif",  array($this,"showEditor")) ; 
		add_action( "wp_ajax_saveEditorModif",  array($this,"saveEditor")) ; 
		add_action( "wp_ajax_cancelEditorModif",  array($this,"cancelEditor")) ; 

		add_action( 'wp_ajax_nopriv_checkIfFormattingCorrecterNeeded', array( $this, 'checkIfFormattingCorrecterNeeded'));
		add_action( 'wp_ajax_checkIfFormattingCorrecterNeeded', array( $this, 'checkIfFormattingCorrecterNeeded'));
		
		add_action( 'save_post', array( $this, 'whenPostIsSaved') );
		add_filter('tiny_mce_before_init', array( $this, 'change_mce_options'));
		
		// Important variables initialisation (Do not modify)
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		// activation and deactivation functions (Do not modify)
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array('formatting_correcter','uninstall_removedata'));
		
	}
	
	/** ====================================================================================================================================================
	* In order to uninstall the plugin, few things are to be done ... 
	* (do not modify this function)
	* 
	* @return void
	*/
	
	static public function uninstall_removedata () {
		global $wpdb ;
		// DELETE OPTIONS
		delete_option('formatting_correcter'.'_options') ;
		if (is_multisite()) {
			delete_site_option('formatting_correcter'.'_options') ;
		}
		
		// DELETE SQL
		if (function_exists('is_multisite') && is_multisite()){
			$old_blog = $wpdb->blogid;
			$old_prefix = $wpdb->prefix ; 
			// Get all blog ids
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM ".$wpdb->blogs));
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				$wpdb->query("DROP TABLE ".str_replace($old_prefix, $wpdb->prefix, $wpdb->prefix . "pluginSL_" . 'formatting_correcter')) ; 
			}
			switch_to_blog($old_blog);
		} else {
			$wpdb->query("DROP TABLE ".$wpdb->prefix . "pluginSL_" . 'formatting_correcter' ) ; 
		}
	}
	
	/**====================================================================================================================================================
	* Function called when the plugin is activated
	* For instance, you can do stuff regarding the update of the format of the database if needed
	* If you do not need this function, you may delete it.
	*
	* @return void
	*/
	
	public function _update() {
		SL_Debug::log(get_class(), "Update the plugin." , 4) ; 
	}
	
	/**====================================================================================================================================================
	* Function called to return a number of notification of this plugin
	* This number will be displayed in the admin menu
	*
	* @return int the number of notifications available
	*/
	 
	public function _notify() {
		return 0 ; 
	}
	
	
	/** ====================================================================================================================================================
	* Init javascript for the public side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('formatting_correcter_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _public_js_load() {	
		ob_start() ; 
		?>
			function checkIfFormattingCorrecterNeeded() {
				
				var arguments = {
					action: 'checkIfFormattingCorrecterNeeded'
				} 
				var ajaxurl2 = "<?php echo admin_url()."admin-ajax.php"?>" ; 
				jQuery.post(ajaxurl2, arguments, function(response) {
					// We do nothing as the process should be as silent as possible
				});    
			}
			
			// We launch the callback
			if (window.attachEvent) {window.attachEvent('onload', checkIfFormattingCorrecterNeeded);}
			else if (window.addEventListener) {window.addEventListener('load', checkIfFormattingCorrecterNeeded, false);}
			else {document.addEventListener('load', checkIfFormattingCorrecterNeeded, false);} 
						
		<?php 
		
		$java = ob_get_clean() ; 
		$this->add_inline_js($java) ; 
	}		
	/** ====================================================================================================================================================
	* Init css for the public side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _public_css_load() {	
		return ; 
	}
	
	/** ====================================================================================================================================================
	* Init javascript for the admin side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('formatting_correcter_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _admin_js_load() {	
		return ; 
	}
	
	/** ====================================================================================================================================================
	* Init css for the admin side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _admin_css_load() {	
		return ; 
	}

	/** ====================================================================================================================================================
	* Called when the content is displayed
	*
	* @param string $content the content which will be displayed
	* @param string $type the type of the article (e.g. post, page, custom_type1, etc.)
	* @param boolean $excerpt if the display is performed during the loop
	* @return string the new content
	*/
	
	function _modify_content($content, $type, $excerpt) {	
		return $content; 
	}
		
	/** ====================================================================================================================================================
	* Add a button in the TinyMCE Editor
	*
	* To add a new button, copy the commented lines a plurality of times (and uncomment them)
	* 
	* @return array of buttons
	*/
	
	function add_tinymce_buttons() {
		$buttons = array() ; 
		//$buttons[] = array(__('title', $this->pluginID), '[tag]', '[/tag]', plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)).'img/img_button.png') ; 
		return $buttons ; 
	}
	
	/**====================================================================================================================================================
	* Function to instantiate the class and make it a singleton
	* This function is not supposed to be modified or called (the only call is declared at the end of this file)
	*
	* @return void
	*/
	
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/** ====================================================================================================================================================
	* Define the default option values of the plugin
	* This function is called when the $this->get_param function do not find any value fo the given option
	* Please note that the default return value will define the type of input form: if the default return value is a: 
	* 	- string, the input form will be an input text
	*	- integer, the input form will be an input text accepting only integer
	*	- string beggining with a '*', the input form will be a textarea
	* 	- boolean, the input form will be a checkbox 
	* 
	* @param string $option the name of the option
	* @return variant of the option
	*/
	public function get_default_option($option) {
		switch ($option) {
			case 'type_page' 		: return "page,post" 		; break ; 
			case 'remove_double_space' 		: return true			; break ; 
			case 'remove_incorrect_quote' 		: return true			; break ; 
			case 'remove_div' 		: return false			; break ; 
			case 'french_punctuations' 		: return false			; break ; 
			case 'regex_error' 		: return ""			; break ; 
			case 'regex_correct' 		: return ""				; break ; 
			case 'avoid_multiple_revisions' : return true			; break ; 
			
			case 'last_request' : return 0 ; break ; 
			case 'between_two_requests' : return 2 ; break ; 
		}
		return null ;
	}

	/** ====================================================================================================================================================
	* The admin configuration page
	* This function will be called when you select the plugin in the admin backend 
	*
	* @return void
	*/
	
	public function check_text($tempo=true) {
		global $post, $wpdb ;
		// We check that the last request has not been emitted since a too short period of time
		$now = time() ; 
		if ($tempo) {
			$last = $this->get_param('last_request') ; 
			if ($now-$last<=60*$this->get_param('between_two_requests')) {
				return sprintf(__('Only %s seconds since the last computation: please wait!', $this->pluginID), ($now-$last)."" ) ; 
			}
		}
		$this->set_param('last_request',$now); 
		
		// Exclude post that has already been analyzed
		$exclude_ids = array() ; 
		$res = $wpdb->get_results("SELECT id_post FROM ".$this->table_name) ;
		foreach ( $res as $r ) {
			$exclude_ids[] = $r->id_post;
		}

		// We get a random post 
		$args = array(
			'numberposts'     => 1,
			'orderby'         => 'rand',
			'post__not_in' => $exclude_ids, 
			'post_type'       => explode(",",$this->get_param('type_page')),
			'post_status'     => 'publish' );

		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$post_temp = array() ; 
		while ( $myQuery->have_posts() ) {
			$myQuery->the_post();
			$post_temp[] = $post;
		}

		// Reset Post Data
		wp_reset_postdata();
		
		// Si aucun post ne comvient on sort
		if (empty($post_temp)) {
			return ; 
		}

		$text = $post_temp[0]->post_content ; 
		$id = $post_temp[0]->ID ; 
						
		$array_regexp = $this->get_regexp() ; 		
		
		
		// Detect formatting issues
		$result = $this->split_regexp($text, $array_regexp) ;
		
		
		$res = $wpdb->query("INSERT INTO ".$this->table_name." (id_post,numerror, date_check) VALUES ('".$id."', '".floor((count($result)-1)/2)."', NOW())") ;
				
		// we re-authorize a new request 
		$this->set_param('last_request', time()) ; 
		 
		return $id."-".floor((count($result)-1)/2) ; 
	}
	
	/** ====================================================================================================================================================
	* Callback for processing
	*
	* @return void
	*/
	
	function checkIfFormattingCorrecterNeeded() {
		echo $this->check_text(true) ; 
		die() ; 
	}
	
	
	/** ====================================================================================================================================================
	* Split text with a plurality of regexp
	*
	* @return void
	*/

	function split_regexp($text, $array_regexp) {

		$array_text = array(array('text'=>$text, 'status'=>"NORMAL")) ;  
				
		foreach ($array_regexp as $regexp) {
		
			$new_array_text = array() ; 
			foreach ($array_text as $a_t ) {
			 	
				if ($a_t['status']=='NORMAL') {
					$text = $a_t['text'] ; 
					
					$next_separator = true ; 
		
					$out = preg_split('/('.$regexp['found'].')/', $text, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_OFFSET_CAPTURE) ;
					$out2 = preg_split('/('.$regexp['found'].')/', $text, -1, PREG_SPLIT_OFFSET_CAPTURE) ;
					for ($i=0 ; $i<count($out) ; $i++) {
						$ao = $out[$i] ;
						// se trouve dans la liste out2 ?
						$pos_out2 = array_search($ao, $out2) ; 
						if ($pos_out2!==false) {
							// j'arme pour le prochain separateur
							$next_separator = true ; 
							$new_array_text[] = array('text'=>$ao[0], 'status'=>"NORMAL"); 
						} else {
							if ($next_separator) {
								$ns = $regexp['replace'] ; 
								$ao0 = $ao[0] ; 
								// Replace occurrence
								for ($j=1 ; $j<20 ; $j++) {
									if (isset($out[$i+$j])) {
										if (array_search($out[$i+$j], $out2)===false) {
											$ns = str_replace("###".$j."###", $out[$i+$j][0], $ns) ; 
										} else {
											break ; 
										}
									} else {
										break ; 
									}
								}
								$new_array_text[] = array('text'=>$ao[0], 'pos'=>$ao[1], 'status'=>"DELIMITER", 'new_text'=>$ns, 'message'=> addslashes($regexp['message'])); 
							} 
							$next_separator = false ; 
						}
					}
				} else {
					// On recopie s'il on ne doit rien changer
					$new_array_text[] = $a_t ; 
				}
			}
			$array_text = $new_array_text ; 

		}
		return $array_text ; 
	}
	
	/** ====================================================================================================================================================
	* The admin configuration page
	* This function will be called when you select the plugin in the admin backend 
	*
	* @return void
	*/
	
	public function configuration_page() {
		global $wpdb;
		global $blog_id ; 
		
		SL_Debug::log(get_class(), "Print the configuration page." , 4) ; 
		
		// Si on change les paramètres, on supprime les entrées
		if (isset($_POST['submitOptions'])) {
			echo "<div class='updated'><p>".__("All existing entries are deleted as you just update the params.",$this->pluginID)."</p><p>".__("Do not worry, the checking will restart as soon as possible.",$this->pluginID)."</p></div>" ; 
			$wpdb->query("DELETE FROM ".$this->table_name) ; 
		}
		// Si on change les paramètres, on supprime les entrées
		if (isset($_POST['resetOptions'])) {
			echo "<div class='updated'><p>".__("All existing entries are deleted as you just reset the params.",$this->pluginID)."</p><p>".__("Do not worry, the checking will restart as soon as possible.",$this->pluginID)."</p></div>" ; 
			$wpdb->query("DELETE FROM ".$this->table_name) ; 
		}
		
		?>
		<div class="wrap">
			<div id="icon-themes" class="icon32"><br></div>
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		<div style="padding:20px;">			
			<?php
			//===============================================================================================
			// After this comment, you may modify whatever you want
			?>
			<p><?php echo __("This is the configuration page of the plugin", $this->pluginID) ;?></p>
			<?php
			
			// We check rights
			$this->check_folder_rights( array(array(WP_CONTENT_DIR."/sedlex/test/", "rwx")) ) ;
			
			$tabs = new adminTabs() ; 
			
			ob_start() ; 
				
				echo "<div id='viewFormattingIssue_edit'></div>"  ; 
			
				$maxnb = 20 ; 
				$table = new adminTable(0, $maxnb, true, true) ; 
				
				$paged=1 ; 
				
				$result = array() ; 
				
				// on construit le filtre pour la requete
				$filter = explode(" ", $table->current_filter()) ; 
				
				$res = $wpdb->get_results("SELECT id_post, date_check, numerror FROM ".$this->table_name." ORDER BY numerror") ;
				foreach ( $res as $r ) {
					if ($r->numerror!=0) {
						$match = true ; 
						$title = get_the_title($r->id_post) ;  
						foreach ($filter as $fi) {
							if ($fi!="") {
								if (strpos(strtolower($title), strtolower($fi))===FALSE) {
									$match = false ; 
									break ; 
								}
							}
						}
						if ($match) {
							$result[] = array($r->id_post, $title, $r->numerror, $r->date_check) ; 
						}
					}
				}
				
				$count = count($result);
				$table->set_nb_all_Items($count) ; 

				$table->title(array(__('Title of your articles', $this->pluginID), __('Num of formatting issues', $this->pluginID), __('Date of verification', $this->pluginID))) ; 

				// We order the posts page according to the choice of the user
				if ($table->current_orderdir()=="ASC") {
					$result = Utils::multicolumn_sort($result, $table->current_ordercolumn(), true) ;  
				} else { 
					$result = Utils::multicolumn_sort($result, $table->current_ordercolumn(), false) ;  
				}
				
				// We limit the result to the requested zone
				$result = array_slice($result,($table->current_page()-1)*$maxnb,$maxnb);
				
				// lignes du tableau
				// boucle sur les differents elements
				$ligne = 0 ; 
				foreach ($result as $r) {
					$ligne++ ; 
					$cel1 = new adminCell("<p><b>".$r[1]."</b></p>") ; 	
					$cel1->add_action(__("View formatting issues", $this->pluginID), "viewFormattingIssue") ; 
					$cel1->add_action(__("Reset", $this->pluginID), "resetFormattingIssue") ; 
					$cel2 = new adminCell("<p>".$r[2]."</p>") ; 	
					
					$cel3 = new adminCell($r[3]) ; 
					
					
					$table->add_line(array($cel1, $cel2, $cel3), $r[0]) ; 
				}
				echo $table->flush() ;
				
			$tabs->add_tab(__('Formatting Issues',  $this->pluginID), ob_get_clean()) ; 	

			ob_start() ; 
				$params = new parametersSedLex($this, "tab-parameters") ; 
				$params->add_title(__('Typical formating issues',  $this->pluginID)) ; 
				$params->add_param('remove_double_space', __('Double-space:',  $this->pluginID)) ; 
				$params->add_comment(__("This option detects double space in your text",  $this->pluginID)) ; 
				$params->add_param('remove_incorrect_quote', __('Incorrect quotes:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__('Replace %s with %s and %s with %s.',  $this->pluginID), "<code>&#171;, &#187;, &#8220;, &#8221;, &#8222;</code>", "<code>&quot;</code>", "<code>&#96;,&#180;, &#8216;, &#8217;, &#8218;</code>", "<code>&#39;</code>")) ; 
				$params->add_param('french_punctuations', __('French punctuation marks:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__('For instance there is unbreakable space before the following double punctuation marks %s.',  $this->pluginID), "<code>!?:;%</code>")) ; 
				$params->add_param('remove_div', __('HTML code in your text:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__('This option remove the %s tag in the text.',  $this->pluginID), "<code>&lt;div&gt;,&lt;dl&gt;,&lt;dd&gt;,&lt;dt&gt;</code>")) ; 
				
				$params->add_title_macroblock(__('Custom issues %s',  $this->pluginID), __('Add a new custom regexp for a custom issue',  $this->pluginID)) ; 
				$params->add_param('regex_error', __('Custom regexp to detect a formatting issue:',  $this->pluginID)) ; 
				$params->add_comment(__("This regexp is used to detect formating issue in your posts",  $this->pluginID)) ; 
				$params->add_param('regex_correct', __('Custom regexp to correct it:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__("You could use %s or %s to use the content in parenthesis.",  $this->pluginID), "##1##", "##2##")) ; 
				
				$params->add_title(__('Advanced parameter',  $this->pluginID)) ; 
				$params->add_param('between_two_requests', __('Minimum interval bewteen two checks (in minutes):',  $this->pluginID)) ; 
				$params->add_param('type_page', __('Types of page/post to be checked:',  $this->pluginID)) ; 
				//$params->add_param('avoid_multiple_revisions', __('Avoid creating a revision for each single modifications you validate:',  $this->pluginID)) ; 

				$params->flush() ; 
				
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			$frmk = new coreSLframework() ;  
			if (((is_multisite())&&($blog_id == 1))||(!is_multisite())||($frmk->get_param('global_allow_translation_by_blogs'))) {
				ob_start() ; 
					$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
					$trans = new translationSL($this->pluginID, $plugin) ; 
					$trans->enable_translation() ; 
				$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	
			}

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new feedbackSL($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				// A list of plugin slug to be excluded
				$exlude = array('wp-pirate-search') ; 
				// Replace sedLex by your own author name
				$trans = new otherPlugins("sedLex", $exlude) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			
			// Before this comment, you may modify whatever you want
			//===============================================================================================
			?>
			<?php echo $this->signature ; ?>
		</div>
		<?php
	}
	
	/** ====================================================================================================================================================
	* Display the splitted text
	*
	* @return string
	*/
	
	function display_split_regexp($result, $id) {
		$num = 1 ; 
		$solu = "" ;
		foreach ($result as $r) {
			if ($r['status']=="NORMAL") {
				$text = htmlentities($r['text'], ENT_COMPAT, 'UTF-8') ; 
				$text = str_replace("\r",'', $text) ; 
				$text = str_replace("\n",'<br/>', $text) ;
				$solu .= $text ; 
			}
			if ($r['status']!="NORMAL") {
				$text = htmlentities($r['text'], ENT_COMPAT, 'UTF-8') ; 
				$text = str_replace(' ','&nbsp;', $text) ; 
				$text = str_replace("\r",'', $text) ; 
				$text = str_replace("\n",'<br/>', $text) ;
				
				$new_text = htmlentities($r['new_text'], ENT_COMPAT, 'UTF-8') ; 
 				$new_text = str_replace(' ','&nbsp;', $new_text) ; 
				$new_text = str_replace("\r",'', $new_text) ; 
				$new_text = str_replace("\n",'<br/>', $new_text) ;

				$solu .=  '<span style="background-color:#FFBBBB;color:#770000;min-width:20px;padding-left:4px;padding-right:4px;text-decoration:line-through;" onclick="replaceTextInPost(\''.str_replace("'", " ",$r['message']).'\', '.$id.', '.$num.', '.$r['pos'].');">'.$text.'</span>' ; 
				$solu .=  '<span style="background-color:#BBFFBB;color:#007700;min-width:20px;padding-left:4px;padding-right:4px;text-decoration:underline;" onclick="replaceTextInPost(\''.str_replace("'", " ",$r['message']).'\', '.$id.', '.$num.', '.$r['pos'].');">'.$new_text.'</span>' ; 
				$num ++ ; 
			}
		}
		return $solu ; 
	}

	/** ====================================================================================================================================================
	* Get Regexp
	* @return void
	*/
	function get_regexp() {
	
		$regexp_norm = array() ; 
		if ($this->get_param('remove_double_space')) {
			$regexp_norm[] = array('found'=>"^[ \t]+",'replace'=>"", 'message'=>__("Remove this leading space?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"( |&nbsp;){2,}",'replace'=>" ", 'message'=>__("Remove this double space?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('remove_incorrect_quote')) {
			$regexp_norm[] = array('found'=>"(«|»|“|”|„|&#171;|&#187;|&#8220;|&#8221;|&#8222;)", 'replace'=>'"', 'message'=>__("Replace this double non-standard quote?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"(`|´|‘|’|‚|&#96;|&#180;|&#8216;|&#8217;|&#8218;)", 'replace'=>"'", 'message'=>__("Replace this single non-standard quote?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('remove_div')) {
			$regexp_norm[] = array('found'=>"<\/?div[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"<\/?dl[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"<\/?dt[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"<\/?dd[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('french_punctuations')) {
			$regexp_norm[] = array('found'=>" ([!?:;%])(?!\/\/)(?=[^>\]]*(<|\[|$))", 'replace'=>'&nbsp;###1###', 'message'=>__("Replace the breakable space by a non-breakable one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"([^&]{6})([!?:;%])(?!\/\/)(?=[^>\]]*(<|\[|$))", 'replace'=>'###1###&nbsp;###2###', 'message'=>__("Add a non-breakable space between the punction mark and the last word?", $this->pluginID))  ; 
		}
		
		$regexp_found = $this->get_param_macro('regex_error') ; 
		$regexp_replace = $this->get_param_macro('regex_correct') ; 
		$regexp_cust = array() ; 
		for ($i=0 ; $i<count($regexp_found) ; $i++) {
			if (trim($regexp_found[$i]) != "") {
				$regexp_cust[] = array('found'=>$regexp_found[$i],'replace'=>$regexp_replace[$i], 'message'=>sprintf(__("Replace this formatting based on custom regexp %s ?", $this->pluginID), $i+1)) ; 
			}
		}
		
		$array_regexp = array_merge($regexp_norm, $regexp_cust) ;
		
		return $array_regexp ; 
	}
	

	
	/** ====================================================================================================================================================
	* Ajax Callback to view formatting issue (only the first time)
	* @return void
	*/
	function viewFormattingIssue() {
		global $post, $wpdb ; 
		$id = $_POST['id'] ; 
		if (!is_numeric($id)) {
			echo "Go away: ID is not an integer" ; 
			die() ; 
		} 
		
		// We get the post
		$args = array(
				'p'=>$id,
				'post_type' => 'any'
		) ; 
		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$post_temp = array() ; 
		while ( $myQuery->have_posts() ) {
			$myQuery->the_post();
			$post_temp[] = $post;
		}

		// Reset Post Data
		wp_reset_postdata();
		

		$text = $post_temp[0]->post_content ; 
								
		$array_regexp = $this->get_regexp() ; 		
		
		
		// SHOW FORMATING ISSUES AND PROPOSE SOLUTION 
		$result = $this->split_regexp($text, $array_regexp) ;
		
		ob_start() ; 
		
			echo "<p>".__("Click on formatting issue to validate the replacement or use the button at the end of the document to validate all the replacements in one click.", $this->pluginID)."</p>" ;

			echo "<div id='proposed_modifications'>" ; 
			echo "<div id='wait_proposed_modifications' style='display: none;'>" ; 
			echo "<img src='".WP_PLUGIN_URL."/".str_replace(basename(__FILE__),"",plugin_basename( __FILE__))."core/img/ajax-loader.gif'>" ; 
			echo "</div>" ; 
			echo "<div id='text_with_proposed_modifications'>" ; 
		
			echo "<code>".$this->display_split_regexp($result, $id)."</code>" ;

			echo "<p>&nbsp;</p>" ; 
			echo "<div>" ; 
			echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
			echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
			echo "</div>" ; 
			echo "<p>&nbsp;</p>" ; 
		
			echo "</div>" ; 
			
			echo "</div>" ; 
			
		$content = ob_get_clean() ; 
		$popup = new popupAdmin (sprintf(__("View the formatting issue for %s", $this->pluginID),"<i>'".get_the_title($id)."'</i>"), $content, "", "window.location.href=window.location.href;") ; 
		echo $popup->render() ; 
		
		die() ; 
	}

	/** ====================================================================================================================================================
	* Ajax Callback to replace issues
	* @return void
	*/
	function replaceWithProposedModifications_FR() {
		global $post, $wpdb ; 
		$id = $_POST['id'] ; 
		if (!is_numeric($id)) {
			echo "Go away: ID is not an integer" ; 
			die() ; 
		} 
		$num = $_POST['num'] ; 
		if ((!is_numeric($num))&&($num!="ALL")) {
			echo "Go away: NUM is not an integer" ; 
			die() ; 
		} 
		$pos = $_POST['pos'] ; 
		if ((!is_numeric($pos))&&($pos!="ALL")) {
			echo "Go away: POS is not an integer" ; 
			die() ; 
		} 
		
		// We get the post 
		$args = array(
				'p'=>$id,
				'post_type' => 'any'
		) ; 
		
		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$post_temp = array() ; 
		while ( $myQuery->have_posts() ) {
			$myQuery->the_post();
			$post_temp[] = $post;
		}

		// Reset Post Data
		wp_reset_postdata();

		$text = $post_temp[0]->post_content ; 
								
		$array_regexp = $this->get_regexp() ; 
		
		// SHOW FORMATING ISSUES AND PROPOSE SOLUTION 
		$result = $this->split_regexp($text, $array_regexp) ;
		
		$new_string = "" ; 
		
		$numVerif = 1 ; 
		foreach ($result as $r) {
			if ($r['status']=="NORMAL") {
				$new_string .= $r['text'] ; 
			}
			if ($r['status']!="NORMAL") {
				if (($numVerif == $num)||($num=="ALL")) {
					if (($r['pos']==$pos)||($pos=="ALL")) {
						$new_string .=  $r['new_text'] ; 
					} else {
						echo sprintf(__('ERROR: There is a problem as the %s delimiter should be at the %s character and is instead at the %s character.',$this->pluginID), $num, $pos, $r['pos']) ; 
						die() ; 
					}
				} else {
					$new_string .=  $r['text'] ; 
				}
				
				$numVerif ++ ; 
			}
		}
		if ($new_string != "") {
			$post_temp[0]->post_content = $new_string ; 
			
			if ($this->get_param("avoid_multiple_revisions")) {
			//	remove_action('pre_post_update', 'wp_save_post_revision');
			}
			
			remove_action('save_post', array($this, 'whenPostIsSaved'));
			wp_update_post( $post_temp[0] );
			add_action('save_post', array($this, 'whenPostIsSaved'));
			
			if ($this->get_param("avoid_multiple_revisions")) {
			//	add_action('pre_post_update', 'wp_save_post_revision');
			}
		}
		
		$result = $this->split_regexp($new_string, $array_regexp) ;
		
		// We update the database so that the number of error is correct
		$wpdb->query("UPDATE ".$this->table_name." SET numerror='".floor((count($result)-1)/2)."' WHERE id_post='".$id."'") ; 		

		echo "<code>".$this->display_split_regexp($result, $id)."</code>" ; 
		
		echo "<p>&nbsp;</p>" ; 
		echo "<div>" ; 
		echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
		echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
		echo "</div>" ; 
		echo "<p>&nbsp;</p>" ; 
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to replace issues
	* @return void
	*/
	function showEditor() {
		global $post, $wpdb ; 
		$id = $_POST['id'] ; 
		if (!is_numeric($id)) {
			echo "Go away: ID is not an integer" ; 
			die() ; 
		}
		
		// We get the post 
		$args = array(
				'p'=>$id,
				'post_type' => 'any'
		) ; 
		
		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$post_temp = array() ; 
		while ( $myQuery->have_posts() ) {
			$myQuery->the_post();
			$post_temp[] = $post;
		}

		// Reset Post Data
		wp_reset_postdata();

		$text = $post_temp[0]->post_content ; 
		
		echo "<textarea id='editor_textarea' style='width:90%;height: 150px;'>".htmlentities($text, ENT_COMPAT , 'UTF-8')."</textarea>" ; 
		
		echo "<p>&nbsp;</p>" ; 
		echo "<div>" ; 
		echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Save", $this->pluginID))."' type='button' onclick='saveEditor(".$id.")'>" ; 
		echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Cancel", $this->pluginID))."' type='button' onclick='cancelEditor(".$id.")'>" ; 
		echo "</div>" ;
		echo "<p>&nbsp;</p>" ;  
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to reset formatting issue
	* @return void
	*/
	function cancelEditor() {
		global $post, $wpdb ; 
		$id = $_POST['id'] ; 
		if (!is_numeric($id)) {
			echo "Go away: ID is not an integer" ; 
			die() ; 
		} 
		
		// We get the post 
		$args = array(
				'p'=>$id,
				'post_type' => 'any'
		) ; 
		
		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$post_temp = array() ; 
		while ( $myQuery->have_posts() ) {
			$myQuery->the_post();
			$post_temp[] = $post;
		}

		// Reset Post Data
		wp_reset_postdata();

		$text = $post_temp[0]->post_content ; 
								
		$array_regexp = $this->get_regexp() ; 
		
		$result = $this->split_regexp($text, $array_regexp) ;
		
		echo "<code>".$this->display_split_regexp($result, $id)."</code>" ; 
		
		echo "<p>&nbsp;</p>" ; 
		echo "<div>" ; 
		echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
		echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
		echo "</div>" ; 
		echo "<p>&nbsp;</p>" ; 
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to reset formatting issue
	* @return void
	*/
	
	function saveEditor() {
		
		global $post, $wpdb ; 
		$id = $_POST['id'] ; 
		if (!is_numeric($id)) {
			echo "Go away: ID is not an integer" ; 
			die() ; 
		} 
		//$text = html_entity_decode(stripslashes($_POST['text']), ENT_COMPAT, 'UTF-8') ; 
		$text = stripslashes($_POST['text']) ; 
		
		// We get the post 
		$args = array(
				'p'=>$id,
				'post_type' => 'any'
		) ; 
		
		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$post_temp = array() ; 
		while ( $myQuery->have_posts() ) {
			$myQuery->the_post();
			$post_temp[] = $post;
		}

		// Reset Post Data
		wp_reset_postdata();
		
		$array_regexp = $this->get_regexp() ; 
		
		// Save
		$post_temp[0]->post_content = $text ; 
		remove_action('save_post', array($this, 'whenPostIsSaved'));
		wp_update_post( $post_temp[0] );
		add_action('save_post', array($this, 'whenPostIsSaved'));
		
		//$result = $this->split_regexp($text, $array_regexp) ;
		//echo "<code>".$this->display_split_regexp($result, $id)."</code>" ; 
		//echo "</p>####</p>" ; 

		
		$result = $this->split_regexp($post_temp[0]->post_content, $array_regexp) ;
		
		// We update the database so that the number of error is correct
		$wpdb->query("UPDATE ".$this->table_name." SET numerror='".floor((count($result)-1)/2)."' WHERE id_post='".$id."'") ; 		

		echo "<code>".$this->display_split_regexp($result, $id)."</code>" ; 
		
		echo "<p>&nbsp;</p>" ; 
		echo "<div>" ; 
		echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
		echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
		echo "</div>" ; 
		echo "<p>&nbsp;</p>" ; 
		
		die() ; 
	}
	/** ====================================================================================================================================================
	* Ajax Callback to reset formatting issue
	* @return void
	*/
	function resetFormattingIssue() {
		global $post, $wpdb ; 
		$id = $_POST['id'] ; 
		if (!is_numeric($id)) {
			echo "Go away: ID is not an integer" ; 
			die() ; 
		} 
		$wpdb->query("DELETE FROM ".$this->table_name." WHERE id_post='".$id."'") ; 
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to save post
	* @return void
	*/
	function whenPostIsSaved($id) {
		global $wpdb ; 
		// We delete the entries
		$wpdb->query("DELETE FROM ".$this->table_name." WHERE id_post='".$id."'") ; 
	}
	
	/** ====================================================================================================================================================
	* Avoid removing the &nbsp caract that the plugin will add :)
	*
	* @return void
	*/
	function change_mce_options($initArray) {
		
		if ($this->get_param('french_punctuations')) {
		
			/*$initArray['verify_html'] = false;
			$initArray['cleanup_on_startup'] = false;
			$initArray['cleanup'] = false;
			$initArray['forced_root_block'] = false;
			$initArray['validate_children'] = false;
			$initArray['remove_redundant_brs'] = false;
			$initArray['remove_linebreaks'] = false;
			$initArray['force_p_newlines'] = false;
			$initArray['force_br_newlines'] = false;
			$initArray['fix_table_elements'] = false;*/

			//Normalement c'est ça : $initArray['entities'] = '160,nbsp,38,amp,60,lt,62,gt';	
			// On evite la suppression de &nbsp;
			//$initArray['entities'] = '38,amp,60,lt,62,gt';	
			$initArray['mode'] =  'textareas' ; 
			$initArray['theme'] =  'advanced' ; 
			$initArray['entity_encoding'] =  'named' ; 
			$initArray['entities'] =  '160,nbsp' ; 
		}

		return $initArray;
	}


			
}

$formatting_correcter = formatting_correcter::getInstance();

?>