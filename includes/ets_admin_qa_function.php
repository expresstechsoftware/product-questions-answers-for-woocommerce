<?php
// admin controller
class ETS_WOO_PRODUCT_ADMIN_QUESTION_ANSWER 
{ 	
	public function __construct() {
		
		//Add new Theam option in the admin Painel.
		add_action('admin_menu', array($this, 'ets_product_q_a'));

		//Add CSS file.
		add_action( 'admin_enqueue_scripts',array($this, 'ets_woo_qa_admin_style'));  

		// add new Tab. 
		add_filter('woocommerce_product_data_tabs', array($this, 'ets_admin_answer_tabs'));
		
		// Add the script file in the drop & drag Question And Answer Listing.
		add_action( 'admin_enqueue_scripts', array($this, 'ets_product_panels_scripts_ui' ));
		
		// Create the admin Url in Script Variable.	
		add_action( 'admin_enqueue_scripts', array($this, 'ets_admin_woo_qa_scripts' ));
		
		// Tab content.
		add_action( 'woocommerce_product_data_panels', array($this, 'ets_product_panels'));

		// Save Product data in the admin Tab.
		add_action( 'woocommerce_process_product_meta', array($this, 'ets_woo_product_admin_qa') , 10, 1 );
		
		// Save question order in DB.
		add_action('wp_ajax_ets_qa_save_order', array($this, 'saveQaOrder'));
		
		// Add new Question And Answer.
		add_action('wp_ajax_ets_add_new_qusetion_answer', array($this, 'addNewQuestionAnswer'));

		// Delete the Question And Answer.
		add_action('wp_ajax_etsdelete_qusetion_answer', array($this, 'delete_qusetion_answer'));
	}

	/**
	 * Add new Theam option in the admin Panel
	 */ 
	public function ets_product_q_a(){
		add_menu_page(__('Products Q & A','ets_q_n_a'), __('Products Q & A','ets_q_n_a'), 'manage_options', 'theme-options', array($this, 'ets_load_more_question_answer'), 'dashicons-info ',59);
	}
	 
	/**
	 * Create Sub menu option
	 */
	public function ets_load_more_question_answer(){
		$loadButton = get_option( 'ets_load_more_button' ); 

		if(empty($loadButton)){  	
			update_option( 'ets_load_more_button','true' );
			update_option( 'ets_product_q_qa_list_length', '10' );
			update_option( 'ets_load_more_button_name', __("Load More",'ets_q_n_a') );
			update_option( 'ets_product_qa_paging_type', "normal" );
			$loadButton = get_option( 'ets_load_more_button' );
		}

		$lengthOfList = get_option( 'ets_product_q_qa_list_length');
		$buttonName = get_option( 'ets_load_more_button_name');
		$pagingType = get_option( 'ets_product_qa_paging_type');

		if (isset($_POST['ets_load_more'])) {
			$loadButton   =  isset($_POST['ets_load_more_active']) ? $_POST['ets_load_more_active'] : 'false' ; 
			$lengthOfList = $_POST['ets_length_of_list']; 
			$buttonName   = $_POST['ets_load_more_button_name'];  
			$pagingType   = $_POST['paging_type'];
			 
			if($loadButton == 'true'){ 
				if(!empty($lengthOfList)){
					update_option( 'ets_load_more_button', $loadButton );
					update_option( 'ets_product_q_qa_list_length', $lengthOfList );
					update_option( 'ets_product_qa_paging_type', $pagingType );
					if(!empty($buttonName)){
						update_option( 'ets_load_more_button_name', $buttonName );
					} else {
						$buttonName = __("Load More",'ets_q_n_a');
						update_option( 'ets_load_more_button_name', $buttonName );
					} 
				} else {
					$lengthOfList = 10;
					update_option( 'ets_product_q_qa_list_length', $lengthOfList );
				}
			} else {
				$buttonName = __("Load More",'ets_q_n_a');		
				update_option( 'ets_load_more_button', $loadButton );
				update_option( 'ets_product_q_qa_list_length', $lengthOfList );
				update_option( 'ets_load_more_button_name', $buttonName );
				update_option( 'ets_product_qa_paging_type', $pagingType );
			}  	 
		} 
		?><div class="wrap"><div id="icon-options-general" class="icon32"><br></div>
		<h2><?php echo __("Product Q & A Setting",'ets_q_n_a'); ?></h2></div>
		<form method="post" name="load_more_form" action="#"> 
			 	
			<table>
				<tr>
					<td><h4><?php echo __('Load More :','ets_q_n_a'); ?></h4></td>
					<td> 
						<input type="checkbox" <?php if(($loadButton == 'true')) { echo $loadButton; ?> checked <?php }?> name="ets_load_more_active" value="true" >
					</td> 	
				</tr> 
				<tr>
					<td><h4><?php echo __('Page Size:','ets_q_n_a'); ?></h4></td>
					<td><input type="number" name="ets_length_of_list" value="<?php echo isset($lengthOfList) ? $lengthOfList : '';?>"  min="1"  ></td>
				</tr> 
				<tr>
					<td><h4><?php echo __('Paging Button Name: ','ets_q_n_a'); ?></h4></td>
					<td><input type="text" name="ets_load_more_button_name" value="<?php echo isset($buttonName) ? $buttonName : '';?>" width="50px" height="90px"></td>
				</tr>
				<tr>
					<td><h4><?php echo __('Paging Type:','ets_q_n_a'); ?> </h4></td>
					<td><select name="paging_type">
					    	<option value="normal" <?php if($pagingType == "normal") { ?> selected <?php }?>><?php echo __('Normal','ets_q_n_a');?></option>
					    	<option value="accordion"  <?php if($pagingType == "accordion") { ?> selected <?php }?>><?php echo __('Accordion','ets_q_n_a');?></option>
						</select>
					</td>
				</tr>
				<tr><td></td>
					<td><button type="submit" name="ets_load_more"><?php echo __('Submit',"ets_q_n_a"); ?></button>
				</tr>
			</table>
		</form>   

		<?php 
	} 

 	/**
	 * Tab
	 */
 	public function ets_admin_answer_tabs( $tabs ){
 
		$tabs['admin_answer'] = array(
			'label'    		=> 'Q & A',
			'target'   		=> 'ets_product_data', 
			'priority' 		=> 100,  
			'id'			=> 'ets_question',
			'content'		=> ''
		);   
		return $tabs; 
	}
	 
	/**
	 * Include Drag and Drop Script jquery-ui
	 */
  	public function ets_product_panels_scripts_ui(){    
		wp_register_script(
			'jquery-ui-sortable', 
			array( 'jquery' )
		);
		wp_enqueue_script('jquery-ui-sortable'); 
	}  
	/*
	 * Tab content
	 */
	public function ets_product_panels(){ 
		?>
		<div id="ets_product_data" class="panel woocommerce_options_panel hidden"> 
			<div id="ets_product_detail"> <ul id="sortable">
				<?php
				global $post; 
				$productId = $post->ID; 
				$etsGetQuestion = get_post_meta( $productId,'ets_question_answer', true ); 
				if(!empty($etsGetQuestion)){   
					foreach ($etsGetQuestion as $key => $value) {  
						//var_dump($etsGetQuestion);
							// to create hidden input field
						?> <li id="ets-qa-item-<?php echo $key;?>" class="ets-qa-item" style="position: relative;"> 
						<?php 
					 	woocommerce_wp_hidden_input( 
							array(  
								'class'		  => "ets_user_name[$key]", 
								'id'		  => "ets_user_name[$key]",
								'name'		  => "ets_user_name[$key]",
								'value'       => $value['user_name'],
							) 

						);
						woocommerce_wp_hidden_input( 
							array(  
								'id'    	  => "ets_user_id[$key]",
								'class'		  => "ets_user_id[$key]",
								'name'		  => "ets_user_id[$key]",
								'value'       => $value['user_id'],  

							) 
						);
						if(!empty($value['user_email'])){
						woocommerce_wp_hidden_input( 
							array(  
								'id'    	  => "ets_user_email[$key]",
								'class'		  => "ets_user_email[$key]",
								'name'		  => "ets_user_email[$key]",
							    'value'       => $value['user_email'],  

							) 
						);
						}
						
						woocommerce_wp_hidden_input( 
							array(  
																
								'class'		  => "ets_product_title[$key]",	
								'id'    	  => "ets_product_title[$key]",
								'name'		  => "ets_product_title[$key]",							
								'value'       => $value['product_title'],  

							) 
						);
					
					 	woocommerce_wp_hidden_input( 
							array(  
								'name'		  => "ets_date[$key]",
								'id'    	  => "ets_date[$key]",
								'class'		  => "ets_date[$key]",
								'name'		  => "ets_date[$key]",
								'value'       => $value['date'],   
								'type'		  => 'hidden'
							) 
						);
					 	woocommerce_wp_text_input( 
							array(
								'id'    	  => "ets_question[$key]",  
								'name'		  => "ets_question[$key]",
								'value'       => $value['question'],
								'label'       => __('Question :','ets_q_n_a')  
							) 
						);
						woocommerce_wp_textarea_input( 
							array( 
								'id'		  => "ets_answer[$key]",
								'name'		  => "ets_answer[$key]",
								'value'       =>  $value['answer'], 
								'label'       => __('Answer :','ets_q_n_a') 
							) 
						);	
						
						
						if(empty($value['answer'])){
								$value['empty_text'] = 'empty_text';
								woocommerce_wp_hidden_input( 
								array(  
									'id'    	  => "ets_emp_text_answer[$key]",
									'class'		  => "ets_emp_text_answer[$key]",
									'name'		  => "ets_emp_text_answer[$key]",
									'value'       => $value['empty_text'],   
									'type'		  => 'hidden'
								) 
							);					
						}
						?>	
					<div class="image-preview">

						<div class="ets-qa-drop">
							<img class="ets-scroll-move" src="<?php echo ETS_WOO_QA_PATH . "asset/images/Cursor-Move.png"; ?>" style="max-width: 15px;">
						</div> 
						<div class="ets-qa-delete">
							<img src="<?php echo ETS_WOO_QA_PATH . "asset/images/delet.png"; ?>" style="max-width: 20px;" data-questionkey="<?php echo $key; ?>" id="ets-delete-qa"  class="ets-del-qa">
							
						</div>
					</div>
					<div class="border"></div>
					</li> 
					<?php 
					}  	
				} else{
					?> 
					<li id="ets-qa-item-new-q"> 
						<?php  // input
							woocommerce_wp_text_input( 
								array(  
									'name'		  => 'ets_first_question',
									'value'       => '',
									'label'       => __('Question :','ets_q_n_a'),
									'desc_tip'    => true, 
									'id'		  => 'ets_question_data'	 
								) 
							);
							woocommerce_wp_textarea_input( 
								array( 
									'name'		  => 'ets_first_answer',
									'value'       =>  '',
									'label'       => __('Answer :','ets_q_n_a'),
									'desc_tip'    => true, 	
									'id'		  => 'ets_answer_data' 
								) 
							); 
							echo '<div class="border"></div>';
						?>
					</li> 
					<?php 
				}   ?>
		 		<li class="ets-new-qa-field"></li>  
		 		</ul> 
		 		<input type="hidden" name="ets-new-question-Answer-count" id="ets-new-question-Answer-count" value=""> 
				<a href="#" type="submit" name="ets-add-new-qa" class="ets-add-new-qa "> <?php echo __('+Add New',"ets_q_n_a"); ?></a>   
				
			</div>
		</div> 	
		<?php
	}

	/**
	 * Save Product data in the admin Tab
	 */ 
	public function ets_woo_product_admin_qa( $productId ){   
		// to get data
		$userId = $_POST['ets_user_id']; 
		$userName = $_POST['ets_user_name'];
		$question = $_POST['ets_first_question'];
		$answer = $_POST['ets_first_answer'];
		$questions = $_POST['ets_question'];
		$answers = $_POST['ets_answer']; 
		$date = $_POST['ets_date'];  
		$newQuestion = $_POST['ets_new_question'];
		$newAnswer = $_POST['ets_new_answer'];		
		$newDate = date("d-M-Y");
		$user = wp_get_current_user();  
		$newUesrName = $user->user_login;
		$newUserId = $user->ID; 
		$userEmail = $_POST['ets_user_email'];		
		$productTitle = $_POST['ets_product_title'];
		$emp_text_answer = $_POST['ets_emp_text_answer'];
	
		//Insert the first New Question
		if(!empty($question)){

			$productFirstQa[]= array(
				"product_title" => $productTitle,
				"question" 	=> $question,
				"answer"	=> $answer,
				"date"		=> $newDate,
				"user_name"	=> $newUesrName,
				"user_id"	=> $newUserId 
			); 
			 update_post_meta( $productId, 'ets_question_answer',  $productFirstQa );
		}  

		$productQas = get_post_meta( $productId, 'ets_question_answer', true );
		
		//On Click Add new Field New Question
		if(!empty($newQuestion)){ 
			foreach ( $newQuestion as $qkey => $q) { 
				$productNewQas[$qkey] = array(   
					"product_title" => $productTitle[$qkey],
					"question" 	=> $newQuestion[$qkey],
					"answer"	=> $newAnswer[$qkey], 
					"date"		=> $newDate ,
					"user_name"	=> $newUesrName,
					"user_id"	=> $newUserId 
				);
				if(empty($productNewQas[$qkey]['question'])) {
					unset($productNewQas[$qkey]);
				}  
			}
			// update meta with data 
			if(!empty($productQas)){
				$productNewQasList = array_merge( $productQas, $productNewQas);

				 update_post_meta( $productId, 'ets_question_answer', $productNewQasList );
			} else if(!empty($productFirstQa)){
				$productNewQasList = array_merge( $productFirstQa, $productNewQas);  
				 update_post_meta( $productId, 'ets_question_answer', $productNewQasList );
			} else {
				 update_post_meta( $productId, 'ets_question_answer', $productNewQas );
			}	 
		} else { 
			
			//Edit the Question And Answer	  
			foreach ( $questions as $qkey => $q) {  

				$productQas[$qkey] = array(
					"product_title" => $productTitle[$qkey],
					"user_id"	=> $userId[$qkey],
					"user_email" => $userEmail[$qkey],
					"user_name"	=> $userName[$qkey],
					"question" 	=> $q,
					"answer"	=> $answers[$qkey], 
					"date"		=> $date[$qkey]
				
				);

				if(empty($productQas[$qkey]['question'])) {
					unset($productQas[$qkey]);
				}  
			} 	
			// update meta for answer at user question.	   
		 	     update_post_meta( $productId, 'ets_question_answer',  $productQas );  
		}

		/********user mail from admin*********/ 

		$etsGetQuestion = get_post_meta( $productId,'ets_question_answer', true ); 

	    foreach ($emp_text_answer as $key => $value ) {
	       	if( $value == 'empty_text' ) {
	       		// get value for sending mail.
		 		$productTitle = $etsGetQuestion[$key]['product_title'];
		 		$to = $etsGetQuestion[$key]['user_email']; 
		 		$answers = $etsGetQuestion[$key]['answer'];
		 		$url = get_permalink( $productId);         // to get product url
		  		$site_url = get_site_url();                // to get site url
				$site_name = get_bloginfo('name');         // to set site name
		 		$subject = __("New Question: ",'ets_q_n_a') . get_bloginfo('name');
				$message = "<a href='$site_url'>" . $site_name . "</a> added a answer on the <a href='$url'> " . $productTitle ."</a>:  <br><div style='background-color: #FFF8DC;border-left: 2px solid #ffeb8e;padding: 10px;margin-top:10px;'>". $answers ."</div>";
				if(!empty($answers))
				{	
			    	wp_mail($to, $subject, $message);  // sending mail from site admin to user.
				}	
			}		   		
	    }
	} 

	/**
	 * Change Order Q&A 
	 */
	public function saveQaOrder() { 
		$productId = $_POST['product_id'];  
 		$changedOrderQaList = $_POST['ets-qa-item']; 
		$productQas = get_post_meta( $productId, 'ets_question_answer', true );
		$newOrderQaList = array(); 
		foreach($changedOrderQaList as $index) {
		    $newOrderQaList[$index] = $productQas[$index];
		} 
		update_post_meta( $productId, 'ets_question_answer',  $newOrderQaList );		 
	}

	/**
	 * Secipt File include.
	 */
	public function ets_admin_woo_qa_scripts() {
		global $pagenow;

		if ( $pagenow == 'post.php' ) {
			global $post;
			//var_dump($post['id']);
			//die();
		    wp_register_script(
				'ets_woo_qa_admin_script',
				ETS_WOO_QA_PATH . 'asset/js/ets_woo_qa_admin_script.js',
				array('jquery')
			); 
	       // var_dump($post);
			wp_enqueue_script( 'ets_woo_qa_admin_script' );
			
			 	$script_params = array(
					'admin_ajax' => admin_url('admin-ajax.php'),
					'currentProdcutId' => $post->ID 
				);  
		  	wp_localize_script( 'ets_woo_qa_admin_script', 'etsWooQaParams', $script_params ); 
		}
	}

	/**
	 * Include custome style sheet
	 */
	public function ets_woo_qa_admin_style() {
		wp_register_style(
		    'ets_woo_qa_style_css',
		    ETS_WOO_QA_PATH. 'asset/css/ets_woo_qa_style.css'
		); 
		wp_enqueue_style( 'ets_woo_qa_style_css');
		 
	}

	/**
	 * Delete Q&A pare.
	 */
	public function delete_qusetion_answer(){
		$questionIndex = $_POST['questionIndex'];
		$productId = $_POST['prdId']; 
		$productQas = get_post_meta( $productId, 'ets_question_answer', true );
		unset($productQas[$questionIndex]);
		update_post_meta( $productId, 'ets_question_answer',  $productQas ); 
	}

	/**
	 * Add new Q&A field on click Add new Link
	 */
	public function addNewQuestionAnswer(){
		$count = $_POST['count']; 
		if(empty($count)){
			$count = 0;
		}
		ob_start();
		woocommerce_wp_text_input( 
			array(  
				'name'		  => "ets_new_question[$count]",
				'value'       => '',
				'label'       => __('Question :','ets_q_n_a'),
				'desc_tip'    => true,  	 
			) 
		);
		woocommerce_wp_textarea_input( 
			array( 
				'name'		  => "ets_new_answer[$count]",
				'value'       =>  '', 
				'label'       => __('Answer :','ets_q_n_a'),
				'desc_tip'    => true,
			) 
		); 
		$count = $count + 1;
		echo '<div class="border"></div>';
		$htmlData = ob_get_clean();  
		$response = array( 
			'htmlData'		=> $htmlData,
			'count'			=> $count,
		);
		echo json_encode($response);
		die;  
	}
	 
} 	
$etsWooProductAdminQuestionAnswer = new ETS_WOO_PRODUCT_ADMIN_QUESTION_ANSWER(); 