<?php
/**
Plugin Name: Formatting correcter
Plugin Tag: tag
Description: <p>The plugin detects any formatting issues in your posts such as "double space" or any other issues that you may configure and proposes to correct them accordingly. </p>
Version: 1.1.2
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

		add_action( "wp_ajax_stopAnalysisFormatting",  array($this,"stopAnalysisFormatting")) ; 
		add_action( "wp_ajax_forceAnalysisFormatting",  array($this,"forceAnalysisFormatting")) ; 

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
		SLFramework_Debug::log(get_class(), "Update the plugin." , 4) ; 
	}
	
	/**====================================================================================================================================================
	* Function called to return a number of notification of this plugin
	* This number will be displayed in the admin menu
	*
	* @return int the number of notifications available
	*/
	 
	public function _notify() {
		global $wpdb ; 
		if ($this->get_param('show_nb')) {
			$res = $wpdb->get_results("SELECT numerror FROM ".$this->table_name) ;
			$numerror = 0 ; 
			foreach ( $res as $r ) {
				if ($this->get_param('show_nb_error')) {
					$numerror += $r->numerror;
				} else {
					if ($r->numerror>0) {
						$numerror += 1 ;
					}
				}
			}
			return $numerror ; 

		}
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
			case 'space_after_before_html': return false			; break ; 
			case 'remove_incorrect_quote' 		: return true			; break ; 
			case 'remove_div' 		: return false			; break ; 
			case 'french_punctuations' 		: return false			; break ; 
			case 'french_add_blank_after_double_quote' 		: return false			; break ; 
			case 'regex_error' 		: return ""			; break ; 
			case 'regex_correct' 		: return ""				; break ; 
			case 'avoid_multiple_revisions' : return true			; break ; 
			case 'show_nb' : return true			; break ; 
			case 'show_nb_error' : return true			; break ; 
			case 'change_ellipses' : return false			; break ; 
			case 'remove_nbsp' : return false			; break ; 
			case 'space_after_comma' : return false			; break ; 
			case 'strange_accentuation' : return false			; break ; 
			
			
			
			case 'shorten_normal'    : return true			; break ; 
			
			case 'advanced_CBE_PCT' : return false			; break ; 
			case 'epc_guidelines': return false			; break ; 
			case 'epc_epc': return false			; break ; 
			case 'pct_pct': return false			; break ; 

			case 'advanced_legifrance' : return false			; break ; 
			
			case 'list_post_id_to_check': return array()			; break ; 
			case 'nb_post_to_check'  : return 0 ; break ; 
			
			case 'max_page_to_check'  : return 200 ; break ; 
			
			
			
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
		
		$all_type = explode(",",$this->get_param('type_page')) ; 
		$pt = "" ; 
		foreach ($all_type as $at) {
			if ($pt != "") {
				$pt .= " OR " ; 
			}
			$pt .= "post_type = '".$at."'" ; 
		}
		$ids_posts = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE (".$pt.") AND (post_status='publish' OR post_status='inherit') ORDER BY RAND();" ) ;

		$post_temp = array() ; 
		foreach ($ids_posts as $i) {
			if (!in_array($i,$exclude_ids)) {
				$post_temp[] = $i->ID ; 
			}
		}
		
		// Si aucun post ne comvient on sort
		if (empty($post_temp)) {
			return ; 
		}
		if (!isset($post_temp[0])) {
			return ; 
		}
		
		$id = $post_temp[0] ; 
		
		$post_temp = get_post($id) ; 
		
		if ($post_temp==null) {
			return ; 
		}

		// Detect formatting issues in the content / description
		$text = $post_temp->post_content ; 
		$array_regexp = $this->get_regexp() ;
		$result1 = $this->split_regexp($text, $array_regexp) ;
		
		// Detect formatting issues in the except / caption
		$text = $post_temp->post_excerpt ; 
		$array_regexp = $this->get_regexp() ;
		$result2 = $this->split_regexp($text, $array_regexp) ;

		// Detect formatting issues in the title 
		$text = $post_temp->post_title ; 
		$array_regexp = $this->get_regexp() ;
		$result3 = $this->split_regexp($text, $array_regexp) ;
		
		$res = $wpdb->query("INSERT INTO ".$this->table_name." (id_post,numerror, date_check) VALUES ('".$id."', '".(floor((count($result1)-1)/2)+floor((count($result2)-1)/2)+floor((count($result3)-1)/2))."', NOW())") ;
				
		// we re-authorize a new request 
		$this->set_param('last_request', time()) ; 
		 
		return $id."-".(floor((count($result1)-1)/2)+floor((count($result2)-1)/2)+floor((count($result3)-1)/2)) ; 
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
		
		if (($this->get_param('advanced_CBE_PCT'))||($this->get_param('advanced_legifrance'))) {
			$array_text = $this->issueAdvanced($text) ; 
		}
				
		foreach ($array_regexp as $regexp) {
		
			$new_array_text = array() ; 
			foreach ($array_text as $a_t ) {
			 	
				if ($a_t['status']=='NORMAL') {
					$text = $a_t['text'] ; 
					
					$next_separator = true ; 
		
					$out = preg_split('/('.$regexp['found'].')/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_OFFSET_CAPTURE) ;
					$out2 = preg_split('/('.$regexp['found'].')/u', $text, -1, PREG_SPLIT_OFFSET_CAPTURE) ;
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
	* Split text with a plurality of regexp
	*
	* @return void
	*/
	
	function issueAdvanced ($text) {
		
		// Detect all url
		// 1 - All a tag
		// 2 - stuff in a tag
		// 3 - the URL
		// 4 - stuff in a tag
		// 5 - The name of the link
		$out = preg_split('/(<a ([^>]*)href=[\'"]([^>\'"]*)[\'"]([^>]*)>([^<]*)<\/a>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_OFFSET_CAPTURE) ;
		
		$new_array_text = array() ; 
		
		$j = 0 ; 
		for ($i=0 ; $i<=(count($out)/6)-1 ; $i++) {
			// le premier lement $i est le text normal avant le lien
			if (isset($new_array_text[$j])) {
				$new_array_text[$j]['text'] .= $out[$i*6][0] ; 
			} else {
				$new_array_text[$j] = array('text'=>$out[$i*6][0], 'status'=>"NORMAL"); 
			}
			
			// on regarde si le lien match avec quelquechose
			
			// LEGIFRANCE - SUPPRESSION DE JSESSION
			if (($this->get_param('advanced_legifrance'))&&(preg_match("/http:\/\/www\.legifrance\.gouv\.fr\/(.*)\.do;jsessionid=(.*?)\?(.*)$/i",$out[$i*6+3][0], $match))) {
				$new_array_text[$j]['text'] .= "<a " ; 
				$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
				$new_array_text[$j]['text'] .= "href=\"http://www.legifrance.gouv.fr/" ; 
				$new_array_text[$j]['text'] .= $match[1].".do" ;
				$j++ ; 
				$new_array_text[$j] = array('text'=>";jsessionid=".$match[2], 'pos'=>$out[$i*6+3][1]+strlen("http://www.legifrance.gouv.fr/".$match[1].".do"), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Remove the jsession for legifrance links?",$this->pluginID))); 
				$j++ ; 
				$new_array_text[$j] = array('text'=>"?".$match[3], 'status'=>"NORMAL"); 
				$new_array_text[$j]['text'] .=  "\"" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
				$new_array_text[$j]['text'] .= ">" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
				$new_array_text[$j]['text'] .= "</a>" ; 
			// GUIDELINES - SUPPRESSION HTM A LA FIN 
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_guidelines'))&&(preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/guidelines\/f\/(.*)\.htm(#?.*)$/",$out[$i*6+3][0], $match))) {
				$new_array_text[$j]['text'] .= "<a " ; 
				$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
				$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/guidelines/f/" ; 
				$new_array_text[$j]['text'] .= $match[1] ;
				$j++ ; 
				$new_array_text[$j] = array('text'=>".htm", 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/guidelines/f/"), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Remove the htm extension?",$this->pluginID))); 
				$j++ ; 
				$new_array_text[$j] = array('text'=>$match[2], 'status'=>"NORMAL"); 
				$new_array_text[$j]['text'] .=  "\"" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
				$new_array_text[$j]['text'] .= ">" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
				$new_array_text[$j]['text'] .= "</a>" ; 
				
			// GUIDELINES - VERIFICATION DU FORMAT
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_guidelines'))&& (preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/guidelines\/f\/(.*)$/",$out[$i*6+3][0],$match))){
				// vérifie si la structure des guidelines est respectée
				$directive_code = explode("_", $match[1]) ; 
				if (count($directive_code)>=1) {
					$resultat = "Directives ".strtoupper($directive_code[0]) ; 
				}
				if (count($directive_code)>=2) {
					$resultat .= "-".strtoupper($directive_code[1]) ; 
				}
				if (count($directive_code)>=3) {
					$resultat .= " ".strtoupper($directive_code[2]) ; 
				}
				$k=3 ; 
				while ($k<count($directive_code)) {
					$resultat .= ".".strtoupper($directive_code[$k]) ; 
					$k++ ; 
				}
				if ($out[$i*6+5][0]==$resultat) {
					// Le texte est correct donc on ne fait rien
					$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
				} else {
					// Le texte est incorrect donc on l'indique'
					$new_array_text[$j]['text'] .= "<a " ; 
					$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
					$new_array_text[$j]['text'] .= "href=\"" ; 
					$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
					$new_array_text[$j]['text'] .= "\"" ;
					$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
					$new_array_text[$j]['text'] .= ">" ; 
					$j++ ; 
					$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$resultat, 'message'=> addslashes(__("Correct this EPC Guidelines name?",$this->pluginID))); 
					$j++ ; 
					$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
				}
				
			// EPC 2013 - SUPPRESSION HTM A LA FIN 
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_epc'))&&(preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/epc\/2013\/f\/(.*)\.html(#?.*)$/",$out[$i*6+3][0], $match))) {
				$new_array_text[$j]['text'] .= "<a " ; 
				$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
				$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/2013/f/" ; 
				$new_array_text[$j]['text'] .= $match[1] ;
				$j++ ; 
				$new_array_text[$j] = array('text'=>".htm", 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/2013/f/"), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Remove the htm extension?",$this->pluginID))); 
				$j++ ; 
				$new_array_text[$j] = array('text'=>$match[2], 'status'=>"NORMAL"); 
				$new_array_text[$j]['text'] .=  "\"" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
				$new_array_text[$j]['text'] .= ">" ; 		
				$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
				$new_array_text[$j]['text'] .= "</a>" ; 

			// EPC 2010 - SUPPRESSION HTM A LA FIN 
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_epc'))&&(preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/epc\/2010\/f\/(.*)\.html(#?.*)$/",$out[$i*6+3][0], $match))) {
				$new_array_text[$j]['text'] .= "<a " ; 
				$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
				$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/2010/f/" ; 
				$new_array_text[$j]['text'] .= $match[1] ;
				$j++ ; 
				$new_array_text[$j] = array('text'=>".htm", 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/2010/f/"), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Remove the htm extension?",$this->pluginID))); 
				$j++ ; 
				$new_array_text[$j] = array('text'=>$match[2], 'status'=>"NORMAL"); 
				$new_array_text[$j]['text'] .=  "\"" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
				$new_array_text[$j]['text'] .= ">" ;
				$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
				$new_array_text[$j]['text'] .= "</a>" ;  			
			
			// EPC 1973 - SUPPRESSION HTM A LA FIN 
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_epc'))&&(preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/epc\/1973\/f\/(.*)\.html(#?.*)$/",$out[$i*6+3][0], $match))) {
				$new_array_text[$j]['text'] .= "<a " ; 
				$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
				$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/1973/f/" ; 
				$new_array_text[$j]['text'] .= $match[1] ;
				$j++ ; 
				$new_array_text[$j] = array('text'=>".htm", 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/1973/f/"), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Remove the htm extension?",$this->pluginID))); 
				$j++ ; 
				$new_array_text[$j] = array('text'=>$match[2], 'status'=>"NORMAL"); 
				$new_array_text[$j]['text'] .=  "\"" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
				$new_array_text[$j]['text'] .= ">" ; 	
				$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
				$new_array_text[$j]['text'] .= "</a>" ; 
	
			// EPC 2010->2013 - CHANGEMENT CATEGORIE
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_epc'))&&(preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/epc\/2010\/f\/(.*)$/",$out[$i*6+3][0], $match))) {
				$new_array_text[$j]['text'] .= "<a " ; 
				$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
				$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/" ; 
				$j++ ; 
				$new_array_text[$j] = array('text'=>"2010", 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/"), 'status'=>"DELIMITER", 'new_text'=>"2013", 'message'=> addslashes(__("Change to 2013 EPC?",$this->pluginID))); 
				$j++ ; 
				$new_array_text[$j] = array('text'=>"/f/", 'status'=>"NORMAL"); 
				$new_array_text[$j]['text'] .= $match[1] ;
				$new_array_text[$j]['text'] .= "\"" ; 
				$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
				$new_array_text[$j]['text'] .= ">" ; 	
				$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
				$new_array_text[$j]['text'] .= "</a>" ; 
				
			// EPC 2013 - VERIF FORMAT URL
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_epc'))&&(preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/epc\/2013\/f\/(.*)$/",$out[$i*6+3][0], $match))) {
				$val = explode("#",$match[1]) ;
				$num = $val[0] ; 
				$ancre = "" ;
				if (isset($val[1])) {
					$ancre = $val[1] ;
				}
				if ((strlen($num)>=2)&&(substr($num,0,2)=="ar")&&(is_numeric(substr($num,2,1)))) {
					$debut_lien="A".substr($num,2) ; 
					$fin_lien=" CBE" ; 	
					$ok = true ; 
				} elseif ((strlen($num)>=1)&&(substr($num,0,1)=="r")&&(is_numeric(substr($num,1,1)))) {
					$debut_lien="R".substr($num,1) ;
					$fin_lien=" CBE" ; 	
					$ok = true ;					
				} else {
					$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
					$ok = false ; 
				}
				if ($ok) {
					
					$s_ancre = explode("_",$ancre) ; 
					
					// Si on a une ancre et qu'elle correspond bien, on determine le nom
					if (($ancre!="")&&($s_ancre[0]==$debut_lien)){
						// gère le bis
						if ("a"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."bis" ; 
						}
						// gère le ter
						if ("b"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."ter" ; 
						}
						// gère le quater
						if ("c"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quater" ; 
						}
						// gère le quinquies
						if ("d"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quinquies" ; 
						}
						// gère le sexies
						if ("e"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."sexies" ; 
						}
						if (count($s_ancre)>=2) {
							if (is_numeric($s_ancre[1])) {
								$debut_lien .= "(".$s_ancre[1].")" ; 
							} else {
								$debut_lien .= " ".$s_ancre[1].")" ; 
							}
						}
						$k=2 ; 
						while ($k<count($s_ancre)) {
							$debut_lien .=  " ".($s_ancre[$k]).")" ; 
							$k++ ; 
						}
						$resultat = $debut_lien.$fin_lien ; 
						if ($out[$i*6+5][0]==$resultat) {
							// Le texte est correct donc on ne fait rien
							$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
						} else {
							// Le texte est incorrect donc on l'indique'
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
							$new_array_text[$j]['text'] .= "\"" ;
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$resultat, 'message'=> addslashes(__("Correct this EPC name?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
						}
					// Si on a une ancre mais qu'elle ne correspond pas, on supprime l'ancre
					} elseif (($ancre!="")&&($s_ancre[0]!=$debut_lien)) {
						$new_array_text[$j]['text'] .= "<a " ; 
						$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
						$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/2013/f/".$num ; 
						$j++ ; 
						$new_array_text[$j] = array('text'=>"#".$ancre, 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/2013/f/".$num), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Delete this anchor as it does not match with the URL?",$this->pluginID))); 
						$j++ ; 
						$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
						$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
						$new_array_text[$j]['text'] .= ">" ; 
						$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
						$new_array_text[$j]['text'] .= "</a>" ; 
					// Aucune ancre ... donc il va falloir deviner ...
					} else {
						// Si debut et fin identique
						$debut_ancre = $debut_lien ; 
						// gère le bis
						if ("a"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."bis" ; 
						}
						// gère le ter
						if ("b"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."ter" ; 
						}
						// gère le quater
						if ("c"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quater" ; 
						}
						// gère le quinquies
						if ("d"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quinquies" ; 
						}
						// gère le sexies
						if ("e"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."sexies" ; 
						}
						
						if (($debut_lien==substr(trim($out[$i*6+5][0]),0,strlen($debut_lien)))&&($fin_lien==substr(trim($out[$i*6+5][0]),-strlen($fin_lien)))) {
							$res = str_replace(")", " ", str_replace($fin_lien,"",trim($out[$i*6+5][0]))) ; 
							$res = str_replace("(", " ", $res) ; 
							$res2 = explode(" ",trim($res)) ; 
							$anchor = "#".$debut_ancre ; 
							for ($k=1;$k<count($res2) ; $k++) {
								if ($res2[$k]!=""){
									$anchor .= "_".$res2[$k] ;
								}
							}
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/2013/f/".$num ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"", 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/2013/f/".$num), 'status'=>"DELIMITER", 'new_text'=>$anchor, 'message'=> addslashes(__("Add this anchor?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
							$new_array_text[$j]['text'] .= "</a>" ; 
						//Si on n'a aucune corresopondance, on remplace le nom
						} else {
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
							$new_array_text[$j]['text'] .= "\"" ;
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$debut_lien.$fin_lien, 'message'=> addslashes(__("Correct this EPC name?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
						}
					}
				}
			// EPC 1973 - VERIF FORMAT URL
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('epc_epc'))&&(preg_match("/www\.epo\.org\/law-practice\/legal-texts\/html\/epc\/1973\/f\/(.*)$/",$out[$i*6+3][0], $match))) {
				$val = explode("#",$match[1]) ;
				$num = $val[0] ; 
				$ancre = "" ;
				if (isset($val[1])) {
					$ancre = $val[1] ;
				}
				if ((strlen($num)>=2)&&(substr($num,0,2)=="ar")&&(is_numeric(substr($num,2,1)))) {
					$debut_lien="A".substr($num,2) ; 
					$fin_lien=" CBE73" ; 	
					$ok = true ; 
				} elseif ((strlen($num)>=1)&&(substr($num,0,1)=="r")&&(is_numeric(substr($num,1,1)))) {
					$debut_lien="R".substr($num,1) ;
					$fin_lien=" CBE73" ; 	
					$ok = true ;					
				} else {
					$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
					$ok = false ; 
				}
				if ($ok) {
					
					$s_ancre = explode("_",$ancre) ; 
					
					// Si on a une ancre et qu'elle correspond bien, on determine le nom
					if (($ancre!="")&&($s_ancre[0]==$debut_lien)){
						// gère le bis
						if ("a"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."bis" ; 
						}
						// gère le ter
						if ("b"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."ter" ; 
						}
						// gère le quater
						if ("c"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quater" ; 
						}
						// gère le quinquies
						if ("d"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quinquies" ; 
						}
						// gère le sexies
						if ("e"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."sexies" ; 
						}
						if (count($s_ancre)>=2) {
							if (is_numeric($s_ancre[1])) {
								$debut_lien .= "(".$s_ancre[1].")" ; 
							} else {
								$debut_lien .= " ".$s_ancre[1].")" ; 
							}
						}
						$k=2 ; 
						while ($k<count($s_ancre)) {
							$debut_lien .=  " ".($s_ancre[$k]).")" ; 
							$k++ ; 
						}
						$resultat = $debut_lien.$fin_lien ; 
						if ($out[$i*6+5][0]==$resultat) {
							// Le texte est correct donc on ne fait rien
							$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
						} else {
							// Le texte est incorrect donc on l'indique'
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
							$new_array_text[$j]['text'] .= "\"" ;
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$resultat, 'message'=> addslashes(__("Correct this EPC name?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
						}
					// Si on a une ancre mais qu'elle ne correspond pas, on supprime l'ancre
					} elseif (($ancre!="")&&($s_ancre[0]!=$debut_lien)) {
						$new_array_text[$j]['text'] .= "<a " ; 
						$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
						$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/1973/f/".$num ; 
						$j++ ; 
						$new_array_text[$j] = array('text'=>"#".$ancre, 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/1973/f/".$num), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Delete this anchor as it does not match with the URL?",$this->pluginID))); 
						$j++ ; 
						$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
						$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
						$new_array_text[$j]['text'] .= ">" ; 
						$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
						$new_array_text[$j]['text'] .= "</a>" ; 
					// Aucune ancre ... donc il va falloir deviner ...
					} else {
						// Si debut et fin identique
						$debut_ancre = $debut_lien ; 
						// gère le bis
						if ("a"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."bis" ; 
						}
						// gère le ter
						if ("b"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."ter" ; 
						}
						// gère le quater
						if ("c"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quater" ; 
						}
						// gère le quinquies
						if ("d"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."quinquies" ; 
						}
						// gère le sexies
						if ("e"==substr($debut_lien,-1)) {
							$debut_lien = substr($debut_lien,0,strlen($debut_lien)-1)."sexies" ; 
						}
						
						if (($debut_lien==substr(trim($out[$i*6+5][0]),0,strlen($debut_lien)))&&($fin_lien==substr(trim($out[$i*6+5][0]),-strlen($fin_lien)))) {
							$res = str_replace(")", " ", str_replace($fin_lien,"",trim($out[$i*6+5][0]))) ; 
							$res = str_replace("(", " ", $res) ; 
							$res2 = explode(" ",trim($res)) ; 
							$anchor = "#".$debut_ancre ; 
							for ($k=1;$k<count($res2) ; $k++) {
								if ($res2[$k]!=""){
									$anchor .= "_".$res2[$k] ;
								}
							}
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"http://www.epo.org/law-practice/legal-texts/html/epc/1973/f/".$num ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"", 'pos'=>$out[$i*6+3][1]+strlen("http://www.epo.org/law-practice/legal-texts/html/epc/1973/f/".$num), 'status'=>"DELIMITER", 'new_text'=>$anchor, 'message'=> addslashes(__("Add this anchor?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
							$new_array_text[$j]['text'] .= "</a>" ; 
						//Si on n'a aucune corresopondance, on remplace le nom
						} else {
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
							$new_array_text[$j]['text'] .= "\"" ;
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$debut_lien.$fin_lien, 'message'=> addslashes(__("Correct this EPC name?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
						}
					}
				}
			// PCT - VERIF FORMAT URL - ARTICLE
			} elseif (($this->get_param('pct_pct'))&&(preg_match("/www\.wipo\.int\/pct\/fr\/texts\/articles\/(.*)$/",$out[$i*6+3][0], $match))) {
				$val = explode("#",$match[1]) ;
				$num = str_replace(".htm","",$val[0]) ; 
				$ancre = "" ;
				if (isset($val[1])) {
					$ancre = $val[1] ;
				}
				$debut_lien="A".substr($num,1) ; 
				$fin_lien=" PCT" ; 
				$debut_ancre = "_".substr($num,1) ; 
				
				$s_ancre = explode("_",$ancre) ; 
				
				// Si on a une ancre et qu'elle correspond bien, on determine le nom
			
				if ((strlen($num)>=1)&&(is_numeric(substr($num,1,1)))) {

					if (($ancre!="")&&($s_ancre[1]==substr($num,1))){
						$k=2 ; 
						$nexthide = false ;
						while ($k<count($s_ancre)) {
							if ($s_ancre[$k]!="") {
								if (!$nexthide) {
									$debut_lien .=  ".".($s_ancre[$k]) ; 
								}
								$nexthide = false ;
							} else {
								// car certains lien sont de type _49__1_a et doivent être lu 49.a
								$nexthide = true ;
							}
						
							$k++ ; 
						}
						$resultat = $debut_lien.$fin_lien ; 
						if ($out[$i*6+5][0]==$resultat) {
							// Le texte est correct donc on ne fait rien
							$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
						} else {
							// Le texte est incorrect donc on l'indique'
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
							$new_array_text[$j]['text'] .= "\"" ;
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$resultat, 'message'=> addslashes(__("Correct this PCT name?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
						}
					// Si on a une ancre mais qu'elle ne correspond pas, on supprime l'ancre
					} elseif (($ancre!="")&&(isset($s_ancre[1]))&&($s_ancre[1]!=substr($num,1))) {
						$new_array_text[$j]['text'] .= "<a " ; 
						$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
						$new_array_text[$j]['text'] .= "href=\"http://www.wipo.int/pct/fr/texts/articles/".$num.".htm" ; 
						$j++ ; 
						$new_array_text[$j] = array('text'=>"#".$ancre, 'pos'=>$out[$i*6+3][1]+strlen("http://www.wipo.int/pct/fr/texts/articles/".$num.".htm"), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Delete this anchor as it does not match with the URL?",$this->pluginID))); 
						$j++ ; 
						$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
						$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
						$new_array_text[$j]['text'] .= ">" ; 
						$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
						$new_array_text[$j]['text'] .= "</a>" ; 
					// Aucune ancre ... donc il va falloir deviner ...
					} else {
					//Si on a un corresopondance, on devine l'ancre
						if (($debut_lien==substr(trim($out[$i*6+5][0]),0,strlen($debut_lien)))&&($fin_lien==substr(trim($out[$i*6+5][0]),-strlen($fin_lien)))) {
							$res = str_replace(".", " ", str_replace($fin_lien,"",trim($out[$i*6+5][0]))) ; 
							$res = str_replace("(", " ", $res) ; 
							$res = str_replace(")", " ", $res) ; 
							$res2 = explode(" ",trim($res)) ; 
							$anchor = "#".$debut_ancre ; 
							for ($k=1;$k<count($res2) ; $k++) {
								if ($res2[$k]!=""){
									$anchor .= "_".$res2[$k] ;
								}
							}
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+3][0] ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"", 'pos'=>$out[$i*6+3][1]+strlen($out[$i*6+3][0]), 'status'=>"DELIMITER", 'new_text'=>$anchor, 'message'=> addslashes(__("Add this anchor?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
							$new_array_text[$j]['text'] .= "</a>" ; 
						//Si on n'a aucune corresopondance, on remplace le nom
						} else {
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
							$new_array_text[$j]['text'] .= "\"" ;
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$debut_lien.$fin_lien, 'message'=> addslashes(__("Correct this PCT article name?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
						}
					}
				}
			// PCT - VERIF FORMAT URL - RULE
			} elseif (($this->get_param('advanced_CBE_PCT'))&&($this->get_param('pct_pct'))&&(preg_match("/www\.wipo\.int\/pct\/fr\/texts\/rules\/(.*)$/",$out[$i*6+3][0], $match))) {
				$val = explode("#",$match[1]) ;
				$num = str_replace(".htm","",$val[0]) ; 
				$ancre = "" ;
				if (isset($val[1])) {
					$ancre = $val[1] ;
				}
				$debut_lien="R".substr($num,1) ; 
				$fin_lien=" PCT" ; 
				$debut_ancre = "_".substr($num,1) ; 
				
				$s_ancre = explode("_",$ancre) ; 
				
				if ($num=="rtax") {
					if ($out[$i*6+5][0]=="Barème de taxes") {
						// Le texte est correct donc on ne fait rien
						$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
					} else {
						// Le texte est incorrect donc on l'indique'
						$new_array_text[$j]['text'] .= "<a " ; 
						$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
						$new_array_text[$j]['text'] .= "href=\"" ; 
						$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
						$new_array_text[$j]['text'] .= "\"" ;
						$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
						$new_array_text[$j]['text'] .= ">" ; 
						$j++ ; 
						$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>"Barème de taxes", 'message'=> addslashes(__("Correct this PCT name?",$this->pluginID))); 
						$j++ ; 
						$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
					}
				} else {
				
					if ((strlen($num)>=1)&&(is_numeric(substr($num,1,1)))) {
					
						// Si on a une ancre et qu'elle correspond bien, on determine le nom
						if (($ancre!="")&&($s_ancre[1]==substr($num,1))){
							$k=2 ; 
							$nexthide = false ; 
							while ($k<count($s_ancre)) {
								if ($s_ancre[$k]!="") {
									if (!$nexthide) {
										$debut_lien .=  ".".($s_ancre[$k]) ; 
									}
									$nexthide = false ;
								} else {
									// car certains lien sont de type _49__1_a et doivent être lu 49.a
									$nexthide = true ;
								}
							
								$k++ ; 
							}
							$resultat = $debut_lien.$fin_lien ; 
							if ($out[$i*6+5][0]==$resultat) {
								// Le texte est correct donc on ne fait rien
								$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
							} else {
								// Le texte est incorrect donc on l'indique'
								$new_array_text[$j]['text'] .= "<a " ; 
								$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
								$new_array_text[$j]['text'] .= "href=\"" ; 
								$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
								$new_array_text[$j]['text'] .= "\"" ;
								$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
								$new_array_text[$j]['text'] .= ">" ; 
								$j++ ; 
								$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$resultat, 'message'=> addslashes(__("Correct this PCT name?",$this->pluginID))); 
								$j++ ; 
								$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
							}
						// Si on a une ancre mais qu'elle ne correspond pas, on supprime l'ancre
						} elseif (($ancre!="")&&(isset($s_ancre[1]))&&($s_ancre[1]!=substr($num,1))) {
							$new_array_text[$j]['text'] .= "<a " ; 
							$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
							$new_array_text[$j]['text'] .= "href=\"http://www.wipo.int/pct/fr/texts/rules/".$num.".htm" ; 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"#".$ancre, 'pos'=>$out[$i*6+3][1]+strlen("http://www.wipo.int/pct/fr/texts/rules/".$num.".htm"), 'status'=>"DELIMITER", 'new_text'=>"", 'message'=> addslashes(__("Delete this anchor as it does not match with the URL?",$this->pluginID))); 
							$j++ ; 
							$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
							$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
							$new_array_text[$j]['text'] .= ">" ; 
							$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
							$new_array_text[$j]['text'] .= "</a>" ; 
						// Aucune ancre ... donc il va falloir deviner ...
						} else {
						//Si on a un corresopondance, on devine l'ancre
							if (($debut_lien==substr(trim($out[$i*6+5][0]),0,strlen($debut_lien)))&&($fin_lien==substr(trim($out[$i*6+5][0]),-strlen($fin_lien)))) {
								$res = str_replace(".", " ", str_replace($fin_lien,"",trim($out[$i*6+5][0]))) ; 
								$res = str_replace("(", " ", $res) ; 
								$res = str_replace(")", " ", $res) ; 
								$res2 = explode(" ",trim($res)) ; 
								$anchor = "#".$debut_ancre ; 
								for ($k=1;$k<count($res2) ; $k++) {
									if ($res2[$k]!=""){
										$anchor .= "_".$res2[$k] ;
									}
								}
								$new_array_text[$j]['text'] .= "<a " ; 
								$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
								$new_array_text[$j]['text'] .= "href=\"" ; 
								$new_array_text[$j]['text'] .= $out[$i*6+3][0] ; 
								$j++ ; 
								$new_array_text[$j] = array('text'=>"", 'pos'=>$out[$i*6+3][1]+strlen($out[$i*6+3][0]), 'status'=>"DELIMITER", 'new_text'=>$anchor, 'message'=> addslashes(__("Add this anchor?",$this->pluginID))); 
								$j++ ; 
								$new_array_text[$j] = array('text'=>"\"", 'status'=>"NORMAL"); 
								$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
								$new_array_text[$j]['text'] .= ">" ; 
								$new_array_text[$j]['text'] .= $out[$i*6+5][0] ; 
								$new_array_text[$j]['text'] .= "</a>" ; 
							//Si on n'a aucune corresopondance, on remplace le nom
							} else {
								$new_array_text[$j]['text'] .= "<a " ; 
								$new_array_text[$j]['text'] .= $out[$i*6+2][0] ; 
								$new_array_text[$j]['text'] .= "href=\"" ; 
								$new_array_text[$j]['text'] .= $out[$i*6+3][0] ;
								$new_array_text[$j]['text'] .= "\"" ;
								$new_array_text[$j]['text'] .= $out[$i*6+4][0] ; 
								$new_array_text[$j]['text'] .= ">" ; 
								$j++ ; 
								$new_array_text[$j] = array('text'=>$out[$i*6+5][0], 'pos'=>$out[$i*6+5][1], 'status'=>"DELIMITER", 'new_text'=>$debut_lien.$fin_lien, 'message'=> addslashes(__("Correct this PCT rules name?",$this->pluginID))); 
								$j++ ; 
								$new_array_text[$j] = array('text'=>"</a>", 'status'=>"NORMAL"); 
							}
						}
					}
				}				
			} else {
				// Si rien ne match, cela veut dire qu'il faut considerer que c'est un text normal
				$new_array_text[$j]['text'] .= $out[$i*6+1][0] ; 
			}
		}
		
		// on ajoute le dernier element qui est normal
		if (isset($new_array_text[$j])) {
			$new_array_text[$j]['text'] .= $out[count($out)-1][0] ; 
		} else {
			$new_array_text[$j] = array('text'=>$out[count($out)-1][0], 'status'=>"NORMAL"); 
		}
		
		return $new_array_text ; 
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
				
		SLFramework_Debug::log(get_class(), "Print the configuration page." , 4) ; 
		
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
		<div class="plugin-titleSL">
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		
		<div class="plugin-contentSL">		
				
			<?php echo $this->signature ; ?>

			<?php
			//===============================================================================================
			// After this comment, you may modify whatever you want
			?>
			<?php
			
			// We check rights
			$this->check_folder_rights( array(array(WP_CONTENT_DIR."/sedlex/test/", "rwx")) ) ;
			
			$tabs = new SLFramework_Tabs() ; 
			
			ob_start() ; 
				
				echo "<div id='viewFormattingIssue_edit'></div>"  ; 
				
				echo "<div id='table_formatting'>"  ; 
				$this->displayTable() ;
				echo "</div>" ; 
				
				echo "<p>" ; 
				echo "<img id='wait_analysis' src='".WP_PLUGIN_URL."/".str_replace(basename(__FILE__),"",plugin_basename( __FILE__))."core/img/ajax-loader.gif' style='display: none;'>" ; 
				echo "<input type='button' id='forceAnalysis' class='button-primary validButton' onClick='forceAnalysis()'  value='". __('Force analysis',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#forceAnalysis').removeAttr('disabled');</script>" ; 
				echo " <input type='button' id='stopAnalysis' class='button validButton' onClick='stopAnalysis()'  value='". __('Stop analysis',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#stopAnalysis').attr('disabled', 'disabled');</script>" ; 
				echo "</p>" ; 
				
			$tabs->add_tab(__('Formatting Issues',  $this->pluginID), ob_get_clean()) ; 	

			ob_start() ; 
				$params = new SLFramework_Parameters($this, "tab-parameters") ; 
				$params->add_title(__('Typical formating issues',  $this->pluginID)) ; 
				$params->add_param('remove_double_space', __('Double-space:',  $this->pluginID)) ; 
				$params->add_comment(__("This option detects double space in your text",  $this->pluginID)) ; 
				$params->add_param('remove_incorrect_quote', __('Incorrect quotes:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__('Replace %s with %s and %s with %s.',  $this->pluginID), "<code>&#171;, &#187;, &#8220;, &#8221;, &#8222;</code>", "<code>&quot;</code>", "<code>&#96;,&#180;, &#8216;, &#8217;, &#8218;</code>", "<code>&#39;</code>")) ; 
				$params->add_param('french_punctuations', __('French punctuation marks:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__('For instance there is unbreakable space before the following double punctuation marks %s.',  $this->pluginID), "<code>!?:;%</code>")) ; 
				$params->add_param('french_add_blank_after_double_quote', __('Add space after double quote:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__('This is to ease the transformation of %s into %s or %s (if applicable).',  $this->pluginID), "<code>\"</code>", "<code>&#171;</code>", "<code>&#187;</code>")) ; 
				$params->add_param('remove_div', __('HTML code in your text:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__('This option remove the %s tag in the text.',  $this->pluginID), "<code>&lt;div&gt;,&lt;dl&gt;,&lt;dd&gt;,&lt;dt&gt;</code>")) ; 
				$params->add_param('space_after_before_html', __('Move space when inside HTML tag:',  $this->pluginID)) ; 
				$params->add_comment(__('This option move space just after opening tag out and move space just before closing tag out.',  $this->pluginID)) ; 
				$params->add_param('change_ellipses', __('Transform three successive points into ellipses:',  $this->pluginID)) ; 
				$params->add_param('remove_nbsp', __('Incorrect non-breaking space according French rules:',  $this->pluginID)) ; 
				$params->add_comment(__("This option removes non breaking space that are not before punctuation marks.",  $this->pluginID)) ; 
				$params->add_param('space_after_comma', __('Add a space after a comma and remove it before:',  $this->pluginID)) ; 
				$params->add_param('strange_accentuation', __('Correct the diacritics accentuated characters:',  $this->pluginID)) ; 
				$params->add_comment(__("In UTF8, there is two ways for coding accentuated characters: the diatrics way is not very well supported by browsers for now and the accentuated characters may appears as two characters (a non-accentuated one and an accent character).",  $this->pluginID)) ; 

				$params->add_title_macroblock(__('Custom issues %s',  $this->pluginID), __('Add a new custom regexp for a custom issue',  $this->pluginID)) ; 
				$params->add_param('regex_error', __('Custom regexp to detect a formatting issue:',  $this->pluginID)) ; 
				$params->add_comment(__("This regexp is used to detect formating issue in your posts",  $this->pluginID)) ; 
				$params->add_param('regex_correct', __('Custom regexp to correct it:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__("You could use %s or %s to use the content in parenthesis.",  $this->pluginID), "##1##", "##2##")) ; 
				
				$params->add_title(__('Advanced parameter',  $this->pluginID)) ; 
				$params->add_param('between_two_requests', __('Minimum interval bewteen two checks (in minutes):',  $this->pluginID)) ; 
				$params->add_param('shorten_normal', __('Shorten article when displayed in the backend:',  $this->pluginID)) ; 
				$params->add_param('type_page', __('Types of page/post to be checked:',  $this->pluginID)) ; 
				$params->add_comment(sprintf(__("You can type for instance %s or %s.",  $this->pluginID), "<code>page,post</code>", "<code>page,post,attachment</code>")) ; 
				$params->add_param('show_nb', __('Display the number of issues in the left column:',  $this->pluginID),"","",array('show_nb_error')) ; 
				$params->add_param('show_nb_error', __('If this is checked, display the total number of issues, if not, display only the number of posts with at least one issue:',  $this->pluginID)) ; 
				$params->add_param('max_page_to_check', __('When forced, how many posts is to be checked?',  $this->pluginID)) ; 
				$params->add_param('advanced_CBE_PCT', __('Advanced PCT-EPC link formatting link (normally, do not activate this option)',  $this->pluginID),"","",array('epc_guidelines','epc_epc','pct_pct')) ; 
				$params->add_param('epc_guidelines', __('Detect issues on EPC guidelines links',  $this->pluginID));
				$params->add_param('epc_epc', __('Detect issues on EPC articles/rules links',  $this->pluginID));
				$params->add_param('pct_pct', __('Detect issues on PCT articles/rules links',  $this->pluginID));
				$params->add_param('advanced_legifrance', __('Advanced Legifrance link formatting link (normally, do not activate this option)',  $this->pluginID),"","",array()) ; 
				
				//$params->add_param('avoid_multiple_revisions', __('Avoid creating a revision for each single modifications you validate:',  $this->pluginID)) ; 

				$params->flush() ; 
				
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			// HOW To
			ob_start() ;
				echo "<p>".__("This plugin is designed to detect usual typographic issues and to propose corrections for these issues.", $this->pluginID)."</p>" ; 
			$howto1 = new SLFramework_Box (__("Purpose of that plugin", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__("The plugin proposes a number of option to identify the formatting issues.", $this->pluginID)."</p>" ; 
				echo "<p>".__("Just have a look in the configuration tab: it is quite self-explanatory....", $this->pluginID)."</p>" ; 
				echo "<p>".__("You also may add you own regular expressions to identify custom issues", $this->pluginID)."</p>" ; 
			$howto2 = new SLFramework_Box (__("How to configure the plugin?", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__("There is two different ways to look for formatting issue:", $this->pluginID)."</p>" ; 
				echo "<ul style='list-style-type: disc;padding-left:40px;'>" ; 
					echo "<li><p>".__("an automatic process (namely background process):", $this->pluginID)."</p></li>" ; 
						echo "<ul style='list-style-type: circle;padding-left:40px;'>" ; 
							echo "<li><p>".__("Every time a user visits a page of the frontside of your website, an unverified post/page is verified;", $this->pluginID)."</p></li>" ; 
							echo "<li><p>".__("Note that if you have very few visits, a complete review of your articles may be quite long.", $this->pluginID)."</p></li>" ; 
						echo "</ul>" ;
					echo "<li><p>".__("a forced process:", $this->pluginID)."</p></li>" ; 
						echo "<ul style='list-style-type: circle;padding-left:40px;'>" ; 
							echo "<li><p>".__("The button that triggers this forced process may be found in the Formatting issues tab;", $this->pluginID)."</p></li>" ; 
							echo "<li><p>".__("You have to stay on that page for processing all posts/pages: if you go on another page (or if you reload the page), the process will be stopped.", $this->pluginID)."</p></li>" ; 
						echo "</ul>" ;				
				echo "</ul>" ; 
			$howto3 = new SLFramework_Box (__("How to backup the site?", $this->pluginID), ob_get_clean()) ; 
			ob_start() ; 
				 echo $howto1->flush() ; 
				 echo $howto2->flush() ; 
				 echo $howto3->flush() ; 
			$tabs->add_tab(__('How To',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_how.png") ; 	

			$frmk = new coreSLframework() ;  
			if (((is_multisite())&&($blog_id == 1))||(!is_multisite())||($frmk->get_param('global_allow_translation_by_blogs'))) {
				ob_start() ; 
					$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
					$trans = new SLFramework_Translation($this->pluginID, $plugin) ; 
					$trans->enable_translation() ; 
				$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	
			}

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new SLFramework_Feedback($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				// A list of plugin slug to be excluded
				$exlude = array('wp-pirate-search') ; 
				// Replace sedLex by your own author name
				$trans = new SLFramework_OtherPlugins("sedLex", $exlude) ; 
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
	
	function display_split_regexp($result, $id, $place="post_content") {
		$num = 1 ; 
		$solu = "" ;
		foreach ($result as $r) {
			if ($r['status']=="NORMAL") {
				$text = $r['text'] ; 
				if ($this->get_param('shorten_normal')){
					if (strlen($text)>650) {
						$text = mb_substr($text,0,300,'UTF-8')." ... ".mb_substr($text,-300,300,'UTF-8') ; 
					} else {
						// nothing
					}
				}
				$textold = $text ; 
				$text = htmlentities($text, ENT_COMPAT, 'UTF-8') ; 
				if ($text=="") {
					// A cause d'un bug de htmlentities
					$text = utf8_encode(htmlentities($textold, ENT_COMPAT, 'ISO-8859-1')) ; 
				}
				$text = str_replace("\r",'', $text) ; 
				$text = str_replace("\n",'<br/>', $text) ;
				$solu .= $text ; 
			} else {
				$text = htmlentities($r['text'], ENT_COMPAT, 'UTF-8') ; 
				if ($text=="") {
					// A cause d'un bug de htmlentities
					$text = utf8_encode(htmlentities($r['text'], ENT_COMPAT, 'ISO-8859-1')) ; 
				}
				$text = str_replace(' ','&nbsp;', $text) ; 
				$text = str_replace("\r",'', $text) ; 
				$text = str_replace("\n",'<br/>', $text) ;
				
				$new_text = htmlentities($r['new_text'], ENT_COMPAT, 'UTF-8') ; 
 				if ($text=="") {
					// A cause d'un bug de htmlentities
					$text = utf8_encode(htmlentities($r['text'], ENT_COMPAT, 'ISO-8859-1')) ; 
				}
				$new_text = str_replace(' ','&nbsp;', $new_text) ; 
				$new_text = str_replace("\r",'', $new_text) ; 
				$new_text = str_replace("\n",'<br/>', $new_text) ;

				$solu .=  '<span style="background-color:#FFBBBB;color:#770000;min-width:20px;padding-left:4px;padding-right:4px;text-decoration:line-through;" onclick="replaceTextInPost(\''.str_replace("'", " ",$r['message']).'\', '.$id.', '.$num.', '.$r['pos'].', \''.$place.'\');">'.$text.'</span>' ; 
				$solu .=  '<span style="background-color:#BBFFBB;color:#007700;min-width:20px;padding-left:4px;padding-right:4px;text-decoration:underline;" onclick="replaceTextInPost(\''.str_replace("'", " ",$r['message']).'\', '.$id.', '.$num.', '.$r['pos'].', \''.$place.'\');">'.$new_text.'</span>' ; 
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
		
		if ($this->get_param('space_after_comma')) {
			$regexp_norm[] = array('found'=>",([^\p{Zs}<&0-9\r\n])", 'replace'=>', ###1###', 'message'=>__("Add a space after this comma?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"([^\",;.]) ,", 'replace'=>'###1###,', 'message'=>__("Remove space before this comma?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('french_add_blank_after_double_quote')) {
			$regexp_norm[] = array('found'=>"([^ ;(])\"([^ \p{L})&\]>])(?=[^>\]]*(<|\[|$))", 'replace'=>'###1###" ###2###', 'message'=>__("Add a space after this double quote?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('change_ellipses')) {
			$regexp_norm[] = array('found'=>"(\p{Zs}| |&nbsp;)*[.]{3,}( |&nbsp;)*", 'replace'=>'&hellip; ', 'message'=>__("Transform this ellipse?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('remove_double_space')) {
			$regexp_norm[] = array('found'=>"(\p{Zs}| |&nbsp;){2,}",'replace'=>" ", 'message'=>__("Remove this double space?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('remove_nbsp')) {
			$regexp_norm[] = array('found'=>"&nbsp;([^!?:;%])",'replace'=>" ###1###", 'message'=>__("Remove this incorrect non-breaking space?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('remove_incorrect_quote')) {
			$regexp_norm[] = array('found'=>"(«[\s]|[\s]»|&#171;[\s]|[\s]&#187;)", 'replace'=>'"', 'message'=>__("Replace this double non-standard quote?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"(«|»|“|”|„|&#171;|&#187;|&#8220;|&#8221;|&#8222;)", 'replace'=>'"', 'message'=>__("Replace this double non-standard quote?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"(`|´|‘|’|‚|&#96;|&#180;|&#8216;|&#8217;|&#8218;)", 'replace'=>"'", 'message'=>__("Replace this single non-standard quote?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('space_after_before_html')) {
			$regexp_norm[] = array('found'=>"(<\w[^>]*>)( |&nbsp;)", 'replace'=>' ###1###', 'message'=>__("Move the space before the opening HTML tag?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"(\p{Zs}| |&nbsp;)(<\/\w[^>]*>)", 'replace'=>'###2### ', 'message'=>__("Move the space after the closing HTML tag?", $this->pluginID))  ; 
		}

		if ($this->get_param('remove_div')) {
			$regexp_norm[] = array('found'=>"<\/?div[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"<\/?dl[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"<\/?dt[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"<\/?dd[^>]*>", 'replace'=>'', 'message'=>__("Remove the HTML tag?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('french_punctuations')) {
			$regexp_norm[] = array('found'=>"([^&!?:;,.%]) ([!?:;%])(?!\/\/)(?=[^>\]]*(<|\[|$))", 'replace'=>'###1###&nbsp;###2###', 'message'=>__("Replace the breakable space by a non-breakable one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"([^&]{6}[^&!?:;,.%]{2})([!?:;%])(?!\/\/)(?=[^>\]]*(<|\[|$))", 'replace'=>'###1###&nbsp;###2###', 'message'=>__("Add a non-breakable space between the punction mark and the last word?", $this->pluginID))  ; 
		}
		
		if ($this->get_param('strange_accentuation')) {
			// a
			$regexp_norm[] = array('found'=>"\x61\xCC\x81", 'replace'=>'á', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x61\xCC\x80", 'replace'=>'à', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x61\xCC\x82", 'replace'=>'â', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x61\xCC\x83", 'replace'=>'ã', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x61\xCC\x88", 'replace'=>'ä', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// A
			$regexp_norm[] = array('found'=>"\x41\xCC\x81", 'replace'=>'À', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x41\xCC\x80", 'replace'=>'Á', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x41\xCC\x82", 'replace'=>'Â', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x41\xCC\x83", 'replace'=>'Ã', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x41\xCC\x88", 'replace'=>'Ä', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// c
			$regexp_norm[] = array('found'=>"\x63\xCC\xA7", 'replace'=>'ç', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 			
			// C
			$regexp_norm[] = array('found'=>"\x43\xCC\xA7", 'replace'=>'Ç', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 			
			// e
			$regexp_norm[] = array('found'=>"\x65\xCC\x81", 'replace'=>'é', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x65\xCC\x80", 'replace'=>'è', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x65\xCC\x82", 'replace'=>'ê', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x65\xCC\x88", 'replace'=>'ë', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// E
			$regexp_norm[] = array('found'=>"\x45\xCC\x81", 'replace'=>'É', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x45\xCC\x80", 'replace'=>'È', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x45\xCC\x82", 'replace'=>'Ê', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x45\xCC\x88", 'replace'=>'Ë', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// i
			$regexp_norm[] = array('found'=>"\x69\xCC\x81", 'replace'=>'í', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x69\xCC\x80", 'replace'=>'ì', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x69\xCC\x82", 'replace'=>'î', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x69\xCC\x88", 'replace'=>'ï', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// I
			$regexp_norm[] = array('found'=>"\x49\xCC\x81", 'replace'=>'Í', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x49\xCC\x80", 'replace'=>'Ì', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x49\xCC\x82", 'replace'=>'Î', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x49\xCC\x88", 'replace'=>'Ï', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// n
			$regexp_norm[] = array('found'=>"\x6E\xCC\x83", 'replace'=>'ñ', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// N
			$regexp_norm[] = array('found'=>"\x4E\xCC\x83", 'replace'=>'Ñ', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// o
			$regexp_norm[] = array('found'=>"\x6F\xCC\x81", 'replace'=>'ó', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x6F\xCC\x80", 'replace'=>'ò', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x6F\xCC\x82", 'replace'=>'ô', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x6F\xCC\x83", 'replace'=>'õ', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x6F\xCC\x88", 'replace'=>'ö', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// o
			$regexp_norm[] = array('found'=>"\x4F\xCC\x81", 'replace'=>'Ó', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x4F\xCC\x80", 'replace'=>'Ò', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x4F\xCC\x82", 'replace'=>'Ô', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x4F\xCC\x83", 'replace'=>'Õ', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x4F\xCC\x88", 'replace'=>'Ö', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// s
			$regexp_norm[] = array('found'=>"\x73\xCC\x8c", 'replace'=>'š', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// S
			$regexp_norm[] = array('found'=>"\x53\xCC\x8c", 'replace'=>'Š', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// u
			$regexp_norm[] = array('found'=>"\x75\xCC\x81", 'replace'=>'ú', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x75\xCC\x80", 'replace'=>'ù', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x75\xCC\x82", 'replace'=>'û', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x75\xCC\x88", 'replace'=>'ü', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// U
			$regexp_norm[] = array('found'=>"\x55\xCC\x81", 'replace'=>'Ú', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x55\xCC\x80", 'replace'=>'Ù', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x55\xCC\x82", 'replace'=>'Û', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x55\xCC\x88", 'replace'=>'Ü', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// y
			$regexp_norm[] = array('found'=>"\x79\xCC\x81", 'replace'=>'ý', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x79\xCC\x88", 'replace'=>'ÿ', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// Y
			$regexp_norm[] = array('found'=>"\x59\xCC\x81", 'replace'=>'Ý', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			$regexp_norm[] = array('found'=>"\x59\xCC\x88", 'replace'=>'Ÿ', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// z
			$regexp_norm[] = array('found'=>"\x7A\xCC\x8c", 'replace'=>'ž', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
			// Z
			$regexp_norm[] = array('found'=>"\x5A\xCC\x8c", 'replace'=>'Ž', 'message'=>__("Replace the diacritic accentuated characters with a correct one?", $this->pluginID))  ; 
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
		
		$post_temp[0] = get_post($id) ; 
		if ($post_temp[0]==null) {
			die() ; 
		}
		
		// Detect formatting issues in the excerpt / caption
		$text = $post_temp[0]->post_excerpt ; 
		$array_regexp = $this->get_regexp() ;
		$result2 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result2, $id, 'post_excerpt')."</code>" ;
			echo "</div>" ; 
		$box2 = new SLFramework_Box(__("Formating issue for the excerpt / caption", $this->pluginID), ob_get_clean()) ; 

		// Detect formatting issues in the title 
		$text = $post_temp[0]->post_title ; 
		$array_regexp = $this->get_regexp() ;
		$result3 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result3, $id, 'post_title')."</code>" ;
			echo "</div>" ; 
		$box3 = new SLFramework_Box(__("Formating issue for the title", $this->pluginID), ob_get_clean()) ; 
		
		// SHOW FORMATING ISSUES AND PROPOSE SOLUTION 
		$text = $post_temp[0]->post_content ; 
		$array_regexp = $this->get_regexp() ; 		
		$result1 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result1, $id, 'post_content')."</code>" ;
			echo "</div>" ; 
		$box1 = new SLFramework_Box(__("Formating issue for the content / description", $this->pluginID), ob_get_clean()) ; 
		
		ob_start() ; 
			echo "<div>" ; 
			echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
			echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
			echo "</div>" ; 
			echo "<p>&nbsp;</p>" ;
		$fin = ob_get_clean() ; 
		
		$content = 	"<div id='proposed_modifications'>" ; 
		$content .= "<div id='wait_proposed_modifications' style='display: none;'>" ; 
		$content .= "<img src='".WP_PLUGIN_URL."/".str_replace(basename(__FILE__),"",plugin_basename( __FILE__))."core/img/ajax-loader.gif'>" ; 
		$content .= "</div>" ; 
		$content .= "<div id='text_with_proposed_modifications'>" ; 
		$content .=  "<p>".__("Click on formatting issue to validate the replacement or use the button at the end of the document to validate all the replacements in one click.", $this->pluginID)."</p>" ;
		$content .= $box3->flush().$box2->flush().$box1->flush().$fin ; 
		$content .= "</div>" ; 
		$content .= "</div>" ; 

		$popup = new SLFramework_Popup (sprintf(__("View the formatting issue for %s", $this->pluginID),"<i>'".get_the_title($id)."'</i>"), $content, "", "window.location.href=window.location.href;") ; 
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
		$zone = $_POST['zone'] ; 
		if (($zone!="post_excerpt")&&($zone!="post_title")) {
			$zone = "post_content" ; 
		} 

		$pos = $_POST['pos'] ; 
		if ((!is_numeric($pos))&&($pos!="ALL")) {
			echo "Go away: POS is not an integer" ; 
			die() ; 
		} 
		
		$post_temp[0] = get_post($id) ; 
		
		if ($post_temp[0]==null) {
			die() ; 
		}

		
		$toBeUpdated = false ; 
		
		// CONTENT
		if (($zone=="post_content")||($num=="ALL")) {
			$text = $post_temp[0]->post_content ; 
			
			$array_regexp = $this->get_regexp() ; 		
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
				$toBeUpdated = true ; 
			}
		}
		
		// TITLE
		
		if (($zone=="post_title")||($num=="ALL")) {
			$text = $post_temp[0]->post_title ; 
			
			$array_regexp = $this->get_regexp() ; 		
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
				$post_temp[0]->post_title = $new_string ; 
				$toBeUpdated = true ; 
			}
		}
		
		// EXCERPT
		
		if (($zone=="post_excerpt")||($num=="ALL")) {
			$text = $post_temp[0]->post_excerpt ; 
			
			$array_regexp = $this->get_regexp() ; 		
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
				$post_temp[0]->post_excerpt = $new_string ; 
				$toBeUpdated = true ; 
			}
		}
		
		if ($toBeUpdated) {
			if ($this->get_param("avoid_multiple_revisions")) {
				//remove_action('pre_post_update', 'wp_save_post_revision');
			}
		
			remove_action('save_post', array($this, 'whenPostIsSaved'));
			wp_update_post( $post_temp[0] );
			add_action('save_post', array($this, 'whenPostIsSaved'));
		
			if ($this->get_param("avoid_multiple_revisions")) {
				//add_action('pre_post_update', 'wp_save_post_revision');
			}
		}				
		
		
		// Detect formatting issues in the excerpt / caption
		$text = $post_temp[0]->post_excerpt ; 
		$array_regexp = $this->get_regexp() ;
		$result2 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result2, $id, 'post_excerpt')."</code>" ;
			echo "</div>" ; 
		$box2 = new SLFramework_Box(__("Formating issue for the excerpt / caption", $this->pluginID), ob_get_clean()) ; 

		// Detect formatting issues in the title 
		$text = $post_temp[0]->post_title ; 
		$array_regexp = $this->get_regexp() ;
		$result3 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result3, $id, 'post_title')."</code>" ;
			echo "</div>" ; 
		$box3 = new SLFramework_Box(__("Formating issue for the title", $this->pluginID), ob_get_clean()) ; 
		
		// SHOW FORMATING ISSUES AND PROPOSE SOLUTION 
		$text = $post_temp[0]->post_content ; 
		$array_regexp = $this->get_regexp() ; 		
		$result1 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result1, $id, 'post_content')."</code>" ;
			echo "</div>" ; 
		$box1 = new SLFramework_Box(__("Formating issue for the content / description", $this->pluginID), ob_get_clean()) ; 
		
		ob_start() ; 
			echo "<div>" ; 
			echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
			echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
			echo "</div>" ; 
			echo "<p>&nbsp;</p>" ;
		$fin = ob_get_clean() ; 
		
		$content =  "<p>".__("Click on formatting issue to validate the replacement or use the button at the end of the document to validate all the replacements in one click.", $this->pluginID)."</p>" ;
		$content .= $box3->flush().$box2->flush().$box1->flush().$fin ; 
		
		echo $content ; 
		
		// We update the database so that the number of error is correct
		$wpdb->query("UPDATE ".$this->table_name." SET numerror='".(floor((count($result1)-1)/2)+floor((count($result2)-1)/2)+floor((count($result3)-1)/2))."', date_check=NOW() WHERE id_post='".$id."'") ; 		
		
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
		
		$post_temp[0] = get_post($id) ; 
		
		if ($post_temp[0]==null) {
			die() ; 
		}


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
		
		$post_temp[0] = get_post($id) ; 
		
		if ($post_temp[0]==null) {
			die() ; 
		}

		// Detect formatting issues in the excerpt / caption
		$text = $post_temp[0]->post_excerpt ; 
		$array_regexp = $this->get_regexp() ;
		$result2 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result2, $id, 'post_excerpt')."</code>" ;
			echo "</div>" ; 
		$box2 = new SLFramework_Box(__("Formating issue for the excerpt / caption", $this->pluginID), ob_get_clean()) ; 

		// Detect formatting issues in the title 
		$text = $post_temp[0]->post_title ; 
		$array_regexp = $this->get_regexp() ;
		$result3 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result3, $id, 'post_title')."</code>" ;
			echo "</div>" ; 
		$box3 = new SLFramework_Box(__("Formating issue for the title", $this->pluginID), ob_get_clean()) ; 
		
		// SHOW FORMATING ISSUES AND PROPOSE SOLUTION 
		$text = $post_temp[0]->post_content ; 
		$array_regexp = $this->get_regexp() ; 		
		$result1 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result1, $id, 'post_content')."</code>" ;
			echo "</div>" ; 
		$box1 = new SLFramework_Box(__("Formating issue for the content / description", $this->pluginID), ob_get_clean()) ; 
		
		ob_start() ; 
			echo "<div>" ; 
			echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
			echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
			echo "</div>" ; 
			echo "<p>&nbsp;</p>" ;
		$fin = ob_get_clean() ; 
		
		$content =  "<p>".__("Click on formatting issue to validate the replacement or use the button at the end of the document to validate all the replacements in one click.", $this->pluginID)."</p>" ;
		$content .= $box3->flush().$box2->flush().$box1->flush().$fin ; 
		
		echo $content ; 
		
		
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
		$text = stripslashes($_POST['text']) ; 
		
		$post_temp[0] = get_post($id) ; 
		
		if ($post_temp[0]==null) {
			die() ; 
		}

		
		$array_regexp = $this->get_regexp() ; 
		
		// Save
		$post_temp[0]->post_content = $text ; 
		remove_action('save_post', array($this, 'whenPostIsSaved'));
		wp_update_post( $post_temp[0] );
		add_action('save_post', array($this, 'whenPostIsSaved'));
		
		// Detect formatting issues in the excerpt / caption
		$text = $post_temp[0]->post_excerpt ; 
		$array_regexp = $this->get_regexp() ;
		$result2 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result2, $id, 'post_excerpt')."</code>" ;
			echo "</div>" ; 
		$box2 = new SLFramework_Box(__("Formating issue for the excerpt / caption", $this->pluginID), ob_get_clean()) ; 

		// Detect formatting issues in the title 
		$text = $post_temp[0]->post_title ; 
		$array_regexp = $this->get_regexp() ;
		$result3 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result3, $id, 'post_title')."</code>" ;
			echo "</div>" ; 
		$box3 = new SLFramework_Box(__("Formating issue for the title", $this->pluginID), ob_get_clean()) ; 
		
		// SHOW FORMATING ISSUES AND PROPOSE SOLUTION 
		$text = $post_temp[0]->post_content ; 
		$array_regexp = $this->get_regexp() ; 		
		$result1 = $this->split_regexp($text, $array_regexp) ;
		ob_start() ; 
			echo "<div id='proposed_modifications'>" ; 
			echo "<code>".$this->display_split_regexp($result1, $id, 'post_content')."</code>" ;
			echo "</div>" ; 
		$box1 = new SLFramework_Box(__("Formating issue for the content / description", $this->pluginID), ob_get_clean()) ; 
		
		ob_start() ; 
			echo "<div>" ; 
			echo "<input name='validAllIssue' class='button-primary validButton' value='".str_replace("'", "", __("Accept all propositions", $this->pluginID))."' type='button' onclick='validAllIssue(\"".str_replace("\\","",str_replace("'","",str_replace("\"","",__("Sure to modify all issues with the proposed modifications?", $this->pluginID))))."\", \"".$id."\")'>" ; 
			echo "&nbsp;<input name='Edit mode' class='button validButton' value='".str_replace("'", "", __("Edit the text", $this->pluginID))."' type='button' onclick='showEditor(".$id.")'>" ; 
			echo "</div>" ; 
			echo "<p>&nbsp;</p>" ;
		$fin = ob_get_clean() ; 
		
		$content =  "<p>".__("Click on formatting issue to validate the replacement or use the button at the end of the document to validate all the replacements in one click.", $this->pluginID)."</p>" ;
		$content .= $box3->flush().$box2->flush().$box1->flush().$fin ; 
		
		echo $content ; 
				
		// We update the database so that the number of error is correct
		$wpdb->query("UPDATE ".$this->table_name." SET numerror='".(floor((count($result1)-1)/2)+floor((count($result2)-1)/2)+floor((count($result3)-1)/2))."' WHERE id_post='".$id."'") ; 		
		
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
		
		// We recheck immediately
		$post_temp = get_post($id) ; 
		if ($post_temp!==null) {
			// On ne fait rien si c'est une révision
			if (!wp_is_post_revision($post_temp)) {
				
				// Detect formatting issues in the content / description
				$text = $post_temp->post_content ; 
				$array_regexp = $this->get_regexp() ;
				$result1 = $this->split_regexp($text, $array_regexp) ;
		
				// Detect formatting issues in the except / caption
				$text = $post_temp->post_excerpt ; 
				$array_regexp = $this->get_regexp() ;
				$result2 = $this->split_regexp($text, $array_regexp) ;

				// Detect formatting issues in the title 
				$text = $post_temp->post_title ; 
				$array_regexp = $this->get_regexp() ;
				$result3 = $this->split_regexp($text, $array_regexp) ;
				
				$res = $wpdb->query("INSERT INTO ".$this->table_name." (id_post,numerror, date_check) VALUES ('".$id."', '".(floor((count($result1)-1)/2)+floor((count($result2)-1)/2)+floor((count($result3)-1)/2))."', NOW())") ;
			}
		}
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
		//	$initArray['mode'] =  'textareas' ; 
		//	$initArray['theme'] =  'advanced' ; 
			$initArray['entity_encoding'] =  'named' ; 
			$initArray['entities'] =  '160,nbsp' ; 
		}

		return $initArray;
	}
	
	
	
	/** ====================================================================================================================================================
	* Ajax Callback to reset formatting issue
	* @return void
	*/
	function forceAnalysisFormatting() {
		global $post, $wpdb ; 
		
		// Initialize the list
		$at = $this->get_param('list_post_id_to_check') ; 
		if (empty($at)) {
			
			$all_type = explode(",",$this->get_param('type_page')) ; 
			$pt = "" ; 
			foreach ($all_type as $atxxx) {
				if ($pt != "") {
					$pt .= " OR " ; 
				}
				$pt .= "post_type = '".$atxxx."'" ; 
			}
			$ids_posts = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE (".$pt.") AND (post_status='publish' OR post_status='inherit') ORDER BY RAND() LIMIT 0,".intval($this->get_param('max_page_to_check')).";" ) ;

			$post_temp = array() ; 
			foreach ($ids_posts as $i) {
				$post_temp[] = $i->ID ; 
			}
			
			$this->set_param('list_post_id_to_check', $post_temp) ; 
			$this->set_param('nb_post_to_check', count($post_temp)) ; 
		}
		
		// Get the first post of the list
		$post_temp = $this->get_param('list_post_id_to_check') ; 
		$pid = array_pop($post_temp) ; 
		$this->set_param('list_post_id_to_check', $post_temp) ; 
		
		$post_temp[0] = get_post($pid) ; 
		
		if ($post_temp[0]==null) {
			die() ; 
		}

		// Detect formatting issues in the content / description
		$text = $post_temp[0]->post_content ; 
		$array_regexp = $this->get_regexp() ;
		$result1 = $this->split_regexp($text, $array_regexp) ;
		
		// Detect formatting issues in the except / caption
		$text = $post_temp[0]->post_excerpt ; 
		$array_regexp = $this->get_regexp() ;
		$result2 = $this->split_regexp($text, $array_regexp) ;

		// Detect formatting issues in the title 
		$text = $post_temp[0]->post_title ; 
		$array_regexp = $this->get_regexp() ;
		$result3 = $this->split_regexp($text, $array_regexp) ;

		$res = $wpdb->get_results("SELECT id_post FROM ".$this->table_name." WHERE id_post='".$pid."'") ;
		if (count($res)==0){
			$wpdb->query("INSERT INTO ".$this->table_name." (id_post,numerror, date_check) VALUES ('".$pid."', '".(floor((count($result1)-1)/2)+floor((count($result2)-1)/2)+floor((count($result3)-1)/2))."', NOW())") ;
		} else {
			$wpdb->query("UPDATE ".$this->table_name." SET numerror='".(floor((count($result1)-1)/2)+floor((count($result2)-1)/2)+floor((count($result3)-1)/2))."', date_check=NOW() WHERE id_post='".$pid."'") ; 		
		}
		
		$this->displayTable() ; 	
		
		$at = $this->get_param('list_post_id_to_check') ; 
		if (empty($at)) {
			$this->set_param('nb_post_to_check', 0) ; 
		} else {
			$pc = floor(100*($this->get_param('nb_post_to_check')-count($this->get_param('list_post_id_to_check')))/$this->get_param('nb_post_to_check')) ; 
			
			$pb = new SLFramework_Progressbar(500, 20, $pc, "PROGRESS - ".($this->get_param('nb_post_to_check')-count($this->get_param('list_post_id_to_check')))." / ".$this->get_param('nb_post_to_check')) ; 
			echo "<br>" ; 
			$pb->flush() ;	
		}
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to reset formatting issue
	* @return void
	*/
	function stopAnalysisFormatting() {
		global $post, $wpdb ; 
		
		$this->set_param('list_post_id_to_check', array()) ; 
		$this->set_param('nb_post_to_check', 0) ; 

		echo "OK" ; 
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Display the table
	*
	* @return void
	*/
	
	function displayTable() {
		global $wpdb, $post ; 
		$maxnb = 20 ; 
		$table = new SLFramework_Table(0, $maxnb, true, true) ; 
		
		$paged=1 ; 
		
		$result = array() ; 
		
		// on construit le filtre pour la requete
		$filter = explode(" ", $table->current_filter()) ; 
		
		$res = $wpdb->get_results("SELECT id_post, date_check, numerror FROM ".$this->table_name) ;
		
		if (count($res)==0) {
			echo "<p style='font-weight:bold;color:#8F0000;'>".__('No entry is available to be displayed... please wait until the background process find an article with issue(s) or force the analysis of all articles with the below button.', $this->pluginID)."</p>"  ; 
		} else {
			$all_type = explode(",",$this->get_param('type_page')) ; 
			$pt = "" ; 
			foreach ($all_type as $at) {
				if ($pt != "") {
					$pt .= " OR " ; 
				}
				$pt .= "post_type = '".$at."'" ; 
			}
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE (".$pt.") AND (post_status='publish' OR post_status='inherit')" ) ;
			
			echo "<p>".sprintf(__('%s articles/posts have been tested on a total of %s possible articles/posts.', $this->pluginID),"<b>".count($res)."</b>", "<b>".$total."</b>")."</p>"  ; 
			echo "<p>".__('To trigger a verification, you may either wait until the baground process verify all articles/posts, or you may force a verification with the button below.', $this->pluginID)."</p>"  ; 
		}
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
			$result = SLFramework_Utils::multicolumn_sort($result, $table->current_ordercolumn(), true) ;  
		} else { 
			$result = SLFramework_Utils::multicolumn_sort($result, $table->current_ordercolumn(), false) ;  
		}
		
		// We limit the result to the requested zone
		$result = array_slice($result,($table->current_page()-1)*$maxnb,$maxnb);
		
		// lignes du tableau
		// boucle sur les differents elements
		$ligne = 0 ; 
		if ($count!=0) {
			foreach ($result as $r) {
				$ligne++ ; 
				$post_temp2 = get_post($r[0]) ; 
				if ($post_temp2->post_type=="attachment") {
					$cel1 = new adminCell("<p><b><a href='".wp_get_attachment_url($r[0])."'>".$r[1]."</a></b><img src='".plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)).'img/attach.png'."'/> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($r[0]))."\">".__('Edit', $this->pluginID)."</a>)</span></p>") ;
				} else {
					$cel1 = new adminCell("<p><b><a href='".get_permalink($r[0])."'>".$r[1]."</a></b> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($r[0]))."\">".__('Edit', $this->pluginID)."</a>)</span></p>") ;
				} 	
				$cel1->add_action(__("View formatting issues", $this->pluginID), "viewFormattingIssue") ; 
				$cel1->add_action(__("Reset", $this->pluginID), "resetFormattingIssue") ; 
				$cel1->add_action(__("Accept all modifications", $this->pluginID), "acceptAllModificationProposed") ; 
				$cel2 = new adminCell("<p>".$r[2]."</p>") ; 	
			
				$cel3 = new adminCell($r[3]) ; 
			
			
				$table->add_line(array($cel1, $cel2, $cel3), $r[0]) ; 
			}
		} else {
			$cel1 = new adminCell("<p>".__("Nothing to display for now ...", $this->pluginID)."</p>") ; 	
			$cel2 = new adminCell("<p></p>") ; 	
			$cel3 = new adminCell("<p></p>") ; 
			$table->add_line(array($cel1, $cel2, $cel3), "1") ; 
		}
		
		echo $table->flush() ;
	}




			
}

$formatting_correcter = formatting_correcter::getInstance();

?>