<?php
class QMNQuizManager
{
	public function __construct()
	{
		$this->add_hooks();
	}
	public function add_hooks()
	{
		add_shortcode('mlw_quizmaster', array($this, 'display_shortcode'));
	}

	public function display_shortcode($atts)
	{
		extract(shortcode_atts(array(
			'quiz' => 0
		), $atts));

		global $wpdb;
		global $mlwQuizMasterNext;
		global $qmn_allowed_visit;
		$qmn_allowed_visit = true;
		$mlwQuizMasterNext->quizCreator->set_id($quiz);
		date_default_timezone_set(get_option('timezone_string'));
		$return_display = '';
		$qmn_quiz_options = $this->load_quiz_options($quiz);
		$qmn_quiz_questions = $this->load_questions($quiz, $qmn_quiz_options);
		$qmn_quiz_answers = $this->create_answer_array($qmn_quiz_questions);

		$qmn_array_for_variables = array(
			'quiz_id' => $qmn_quiz_options->quiz_id,
			'quiz_name' => $qmn_quiz_options->quiz_name,
			'quiz_system' => $qmn_quiz_options->system
		);

		$return_display = apply_filters('qmn_begin_shortcode', $return_display, $qmn_quiz_options, $qmn_array_for_variables);

		if ($qmn_allowed_visit && !isset($_POST["complete_quiz"]) && $qmn_quiz_options->quiz_name != '')
		{
			$return_display .= $this->display_quiz($qmn_quiz_options, $qmn_quiz_questions, $qmn_quiz_answers, $qmn_array_for_variables);
		}
		elseif (isset($_POST["complete_quiz"]) && $_POST["complete_quiz"] == "confirmation")
		{
			$return_display .= $this->display_results($qmn_quiz_options, $qmn_quiz_questions, $qmn_quiz_answers, $qmn_array_for_variables);
		}
		else
		{
			//return $return_display;
		}

		$return_display = apply_filters('qmn_end_shortcode', $return_display, $qmn_quiz_options, $qmn_array_for_variables);
		return $return_display;
	}

	public function load_quiz_options($quiz_id)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'mlw_quizzes WHERE quiz_id=%d AND deleted=0', $quiz_id));
	}

	public function load_questions($quiz_id, $quiz_options)
	{
		global $wpdb;
		$order_by_sql = "ORDER BY question_order ASC";
		$limit_sql = '';
		if ($quiz_options->randomness_order == 1 || $quiz_options->randomness_order == 2)
		{
			$order_by_sql = "ORDER BY rand()";
		}
		if ($quiz_options->question_from_total != 0)
		{
			$limit_sql = " LIMIT ".$quiz_options->question_from_total;
		}
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."mlw_questions WHERE quiz_id=%d AND deleted=0 ".$order_by_sql.$limit_sql, $quiz_id));
	}

	public function create_answer_array($questions)
	{
		//Load and prepare answer arrays
		$mlw_qmn_answer_arrays = array();
		foreach($questions as $mlw_question_info) {
			if (is_serialized($mlw_question_info->answer_array) && is_array(@unserialize($mlw_question_info->answer_array)))
			{
				$mlw_qmn_answer_array_each = @unserialize($mlw_question_info->answer_array);
				$mlw_qmn_answer_arrays[$mlw_question_info->question_id] = $mlw_qmn_answer_array_each;
			}
			else
			{
				$mlw_answer_array_correct = array(0, 0, 0, 0, 0, 0);
				$mlw_answer_array_correct[$mlw_question_info->correct_answer-1] = 1;
				$mlw_qmn_answer_arrays[$mlw_question_info->question_id] = array(
					array($mlw_question_info->answer_one, $mlw_question_info->answer_one_points, $mlw_answer_array_correct[0]),
					array($mlw_question_info->answer_two, $mlw_question_info->answer_two_points, $mlw_answer_array_correct[1]),
					array($mlw_question_info->answer_three, $mlw_question_info->answer_three_points, $mlw_answer_array_correct[2]),
					array($mlw_question_info->answer_four, $mlw_question_info->answer_four_points, $mlw_answer_array_correct[3]),
					array($mlw_question_info->answer_five, $mlw_question_info->answer_five_points, $mlw_answer_array_correct[4]),
					array($mlw_question_info->answer_six, $mlw_question_info->answer_six_points, $mlw_answer_array_correct[5]));
			}
		}
		return $mlw_qmn_answer_arrays;
	}

	public function display_quiz($qmn_quiz_options, $qmn_quiz_questions, $qmn_quiz_answers, $qmn_array_for_variables)
	{
		global $qmn_allowed_visit;
		$quiz_display = '';
		$quiz_display = apply_filters('qmn_begin_quiz', $quiz_display, $qmn_quiz_options, $qmn_array_for_variables);
		if (!$qmn_allowed_visit)
		{
			return $quiz_display;
		}
		wp_enqueue_script( 'json2' );
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-effects-core' );
		wp_enqueue_script( 'jquery-effects-slide' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-button' );
		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_style( 'qmn_jquery_redmond_theme', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/redmond/jquery-ui.css' );

		?>
		<script>
		var email_error = '<?php _e('Not a valid e-mail address!', 'quiz-master-next'); ?>';
		var number_error = '<?php _e('This field must be a number!', 'quiz-master-next'); ?>';
		var incorrect_error = '<?php _e('The entered text is not correct!', 'quiz-master-next'); ?>';
		var empty_error = '<?php _e('Please complete all required fields!', 'quiz-master-next'); ?>';
		</script>
		<?php
		wp_enqueue_script( 'qmn_quiz', plugins_url( 'js/qmn_quiz.js' , __FILE__ ) );
		wp_enqueue_script( 'math_jax', '//cdn.mathjax.org/mathjax/latest/MathJax.js?config=TeX-AMS-MML_HTMLorMML' );

		wp_enqueue_style( 'qmn_quiz_style', plugins_url( 'css/qmn_quiz.css' , __FILE__ ) );
		if ($qmn_quiz_options->theme_selected == "default")
		{
			echo "<style type='text/css'>".$qmn_quiz_options->quiz_stye."</style>";
		}
		else
		{
			echo "<link type='text/css' href='".get_option('mlw_qmn_theme_'.$qmn_quiz_options->theme_selected)."' rel='stylesheet' />";
		}

		global $qmn_total_questions;
		$qmn_total_questions = 0;
		global $mlw_qmn_section_count;
		$mlw_qmn_section_count = 1;

		$quiz_display .= "<div class='mlw_qmn_quiz'>";
		$quiz_display .= "<form name='quizForm' id='quizForm' action='' method='post' class='mlw_quiz_form' onsubmit='return mlw_validateForm()' novalidate >";
		$quiz_display .= "<span id='mlw_top_of_quiz'></span>";
		$quiz_display = apply_filters('qmn_begin_quiz_form', $quiz_display, $qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display .= $this->display_begin_section($qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display = apply_filters('qmn_begin_quiz_questions', $quiz_display, $qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display .= $this->display_questions($qmn_quiz_options, $qmn_quiz_questions, $qmn_quiz_answers);
		$quiz_display = apply_filters('qmn_before_comment_section', $quiz_display, $qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display .= $this->display_comment_section($qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display = apply_filters('qmn_after_comment_section', $quiz_display, $qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display .= $this->display_end_section($qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display .= "<input type='hidden' name='total_questions' id='total_questions' value='".$qmn_total_questions."'/>";
		$quiz_display .= "<input type='hidden' name='timer' id='timer' value='0'/>";
		$quiz_display .= "<input type='hidden' name='complete_quiz' value='confirmation' />";
		$quiz_display = apply_filters('qmn_end_quiz_form', $quiz_display, $qmn_quiz_options, $qmn_array_for_variables);
		$quiz_display .= "</form>";
		$quiz_display .= "</div>";

		$quiz_display = apply_filters('qmn_end_quiz', $quiz_display, $qmn_quiz_options, $qmn_array_for_variables);
		return $quiz_display;
	}

	public function display_begin_section($qmn_quiz_options, $qmn_array_for_variables)
	{
		global $mlw_qmn_section_count;
		$section_display = "<div class='quiz_section  quiz_begin slide$mlw_qmn_section_count'>";

		$message_before = htmlspecialchars_decode($qmn_quiz_options->message_before, ENT_QUOTES);
		$message_before = apply_filters( 'mlw_qmn_template_variable_quiz_page', $message_before, $qmn_array_for_variables);

		$section_display .= "<span class='mlw_qmn_message_before'>$message_before</span><br />";
		$section_display .= "<span name='mlw_error_message' id='mlw_error_message' class='qmn_error'></span><br />";

		if ($qmn_quiz_options->contact_info_location == 0)
		{
			$section_display .= mlwDisplayContactInfo($qmn_quiz_options);
		}
		$section_display .= "</div>";
		return $section_display;
	}

	public function display_questions($qmn_quiz_options, $qmn_quiz_questions, $qmn_quiz_answers)
	{
		$question_display = '';
		global $qmn_total_questions;
		global $mlw_qmn_section_count;
		foreach($qmn_quiz_questions as $mlw_question)
		{
			if (is_serialized($mlw_question->question_settings) && is_array(@unserialize($mlw_question->question_settings)))
			{
				$mlw_question_settings = @unserialize($mlw_question->question_settings);
			}
			else
			{
				$mlw_question_settings = array();
				$mlw_question_settings['required'] = 1;
			}
			if ( !isset($mlw_question_settings['required']))
			{
				$mlw_question_settings['required'] = 1;
			}
			$mlw_qmn_section_count = $mlw_qmn_section_count + 1;
			$question_display .= "<div class='quiz_section slide".$mlw_qmn_section_count."'>";
			if ($mlw_question->question_type == 0)
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				$mlw_qmn_answer_array = $qmn_quiz_answers[$mlw_question->question_id];
				if (is_array($mlw_qmn_answer_array))
				{
					if ($qmn_quiz_options->randomness_order == 2)
					{
						shuffle($mlw_qmn_answer_array);
					}
					$mlw_answer_total = 0;
					foreach($mlw_qmn_answer_array as $mlw_qmn_answer_each)
					{
						$mlw_answer_total++;
						if ($mlw_qmn_answer_each[0] != "")
						{
							$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_".$mlw_answer_total."' value='".esc_attr($mlw_qmn_answer_each[0])."' /> <label for='question".$mlw_question->question_id."_".$mlw_answer_total."'>".htmlspecialchars_decode($mlw_qmn_answer_each[0], ENT_QUOTES)."</label>";
							$question_display .= "<br />";
						}
					}
					$question_display .= "<input type='radio' style='display: none;' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_none' checked='checked' value='No Answer Provided' />";
				}
				else
				{
					if ($mlw_question->answer_one != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_one' value='1' /> <label for='question".$mlw_question->question_id."_one'>".htmlspecialchars_decode($mlw_question->answer_one, ENT_QUOTES)."</label>";
						$question_display .= "<br />";
					}
					if ($mlw_question->answer_two != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_two' value='2' /> <label for='question".$mlw_question->question_id."_two'>".htmlspecialchars_decode($mlw_question->answer_two, ENT_QUOTES)."</label>";
						$question_display .= "<br />";
					}
					if ($mlw_question->answer_three != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_three' value='3' /> <label for='question".$mlw_question->question_id."_three'>".htmlspecialchars_decode($mlw_question->answer_three, ENT_QUOTES)."</label>";
						$question_display .= "<br />";
					}
					if ($mlw_question->answer_four != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_four' value='4' /> <label for='question".$mlw_question->question_id."_four'>".htmlspecialchars_decode($mlw_question->answer_four, ENT_QUOTES)."</label>";
						$question_display .= "<br />";
					}
					if ($mlw_question->answer_five != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_five' value='5' /> <label for='question".$mlw_question->question_id."_five'>".htmlspecialchars_decode($mlw_question->answer_five, ENT_QUOTES)."</label>";
						$question_display .= "<br />";
					}
					if ($mlw_question->answer_six != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_six' value='6' /> <label for='question".$mlw_question->question_id."_six'>".htmlspecialchars_decode($mlw_question->answer_six, ENT_QUOTES)."</label>";
						$question_display .= "<br />";
					}
				}
			}
			elseif ($mlw_question->question_type == 4)
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				$mlw_qmn_answer_array = $qmn_quiz_answers[$mlw_question->question_id];
				if (is_array($mlw_qmn_answer_array))
				{
					if ($qmn_quiz_options->randomness_order == 2)
					{
						shuffle($mlw_qmn_answer_array);
					}
					$mlw_answer_total = 0;
					foreach($mlw_qmn_answer_array as $mlw_qmn_answer_each)
					{
						$mlw_answer_total++;
						if ($mlw_qmn_answer_each[0] != "")
						{
							$question_display .= "<input type='hidden' name='question".$mlw_question->question_id."' value='This value does not matter' />";
							$question_display .= "<input type='checkbox' name='question".$mlw_question->question_id."_".$mlw_answer_total."' id='question".$mlw_question->question_id."_".$mlw_answer_total."' value='".esc_attr($mlw_qmn_answer_each[0])."' /> <label for='question".$mlw_question->question_id."_".$mlw_answer_total."'>".htmlspecialchars_decode($mlw_qmn_answer_each[0], ENT_QUOTES)."</label>";
							$question_display .= "<br />";
						}
					}
				}
			}
			elseif ($mlw_question->question_type == 10)
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				$mlw_qmn_answer_array = $qmn_quiz_answers[$mlw_question->question_id];
				if (is_array($mlw_qmn_answer_array))
				{
					if ($qmn_quiz_options->randomness_order == 2)
					{
						shuffle($mlw_qmn_answer_array);
					}
					$mlw_answer_total = 0;
					foreach($mlw_qmn_answer_array as $mlw_qmn_answer_each)
					{
						$mlw_answer_total++;
						if ($mlw_qmn_answer_each[0] != "")
						{
							$question_display .= "<input type='hidden' name='question".$mlw_question->question_id."' value='This value does not matter' />";
							$question_display .= "<span class='mlw_horizontal_multiple'><input type='checkbox' name='question".$mlw_question->question_id."_".$mlw_answer_total."' id='question".$mlw_question->question_id."_".$mlw_answer_total."' value='".esc_attr($mlw_qmn_answer_each[0])."' /> <label for='question".$mlw_question->question_id."_".$mlw_answer_total."'>".htmlspecialchars_decode($mlw_qmn_answer_each[0], ENT_QUOTES)."&nbsp;</label></span>";
						}
					}
				}
			}
			elseif ($mlw_question->question_type == 1)
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				$mlw_qmn_answer_array = $qmn_quiz_answers[$mlw_question->question_id];
				if (is_array($mlw_qmn_answer_array))
				{
					if ($qmn_quiz_options->randomness_order == 2)
					{
						shuffle($mlw_qmn_answer_array);
					}
					$mlw_answer_total = 0;
					foreach($mlw_qmn_answer_array as $mlw_qmn_answer_each)
					{
						$mlw_answer_total++;
						if ($mlw_qmn_answer_each[0] != "")
						{
							$question_display .= "<input type='radio' id='question".$mlw_question->question_id."_".$mlw_answer_total."' name='question".$mlw_question->question_id."' value='".esc_attr($mlw_qmn_answer_each[0])."' /> <label for='question".$mlw_question->question_id."_".$mlw_answer_total."'>".htmlspecialchars_decode($mlw_qmn_answer_each[0], ENT_QUOTES)."</label> ";
						}
					}
					$question_display .= "<input type='radio' style='display: none;' name='question".$mlw_question->question_id."' id='question".$mlw_question->question_id."_none' checked='checked' value='No Answer Provided' />";
				}
				else
				{
					if ($mlw_question->answer_one != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' value='1' />".htmlspecialchars_decode($mlw_question->answer_one, ENT_QUOTES);
					}
					if ($mlw_question->answer_two != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' value='2' />".htmlspecialchars_decode($mlw_question->answer_two, ENT_QUOTES);
					}
					if ($mlw_question->answer_three != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' value='3' />".htmlspecialchars_decode($mlw_question->answer_three, ENT_QUOTES);
					}
					if ($mlw_question->answer_four != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' value='4' />".htmlspecialchars_decode($mlw_question->answer_four, ENT_QUOTES);
					}
					if ($mlw_question->answer_five != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' value='5' />".htmlspecialchars_decode($mlw_question->answer_five, ENT_QUOTES);
					}
					if ($mlw_question->answer_six != "")
					{
						$question_display .= "<input type='radio' name='question".$mlw_question->question_id."' value='6' />".htmlspecialchars_decode($mlw_question->answer_six, ENT_QUOTES);
					}
				}
				$question_display .= "<br />";
			}
			elseif ($mlw_question->question_type == 2)
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				$question_display .= "<select name='question".$mlw_question->question_id."'>";
				$mlw_qmn_answer_array = $qmn_quiz_answers[$mlw_question->question_id];
				if (is_array($mlw_qmn_answer_array))
				{
					if ($qmn_quiz_options->randomness_order == 2)
					{
						shuffle($mlw_qmn_answer_array);
					}
					$mlw_answer_total = 0;
					foreach($mlw_qmn_answer_array as $mlw_qmn_answer_each)
					{
						$mlw_answer_total++;
						if ($mlw_qmn_answer_each[0] != "")
						{
							$question_display .= "<option value='".esc_attr($mlw_qmn_answer_each[0])."'>".htmlspecialchars_decode($mlw_qmn_answer_each[0], ENT_QUOTES)."</option>";
						}
					}
				}
				else
				{
					if ($mlw_question->answer_one != "")
					{
						$question_display .= "<option value='1'>".htmlspecialchars_decode($mlw_question->answer_one, ENT_QUOTES)."</option>";
					}
					if ($mlw_question->answer_two != "")
					{
						$question_display .= "<option value='2'>".htmlspecialchars_decode($mlw_question->answer_two, ENT_QUOTES)."</option>";
					}
					if ($mlw_question->answer_three != "")
					{
						$question_display .= "<option value='3'>".htmlspecialchars_decode($mlw_question->answer_three, ENT_QUOTES)."</option>";
					}
					if ($mlw_question->answer_four != "")
					{
						$question_display .= "<option value='4'>".htmlspecialchars_decode($mlw_question->answer_four, ENT_QUOTES)."</option>";
					}
					if ($mlw_question->answer_five != "")
					{
						$question_display .= "<option value='5'>".htmlspecialchars_decode($mlw_question->answer_five, ENT_QUOTES)."</option>";
					}
					if ($mlw_question->answer_six != "")
					{
						$question_display .= "<option value='6'>".htmlspecialchars_decode($mlw_question->answer_six, ENT_QUOTES)."</option>";
					}
				}
				$question_display .= "</select>";
				$question_display .= "<br />";
			}
			elseif ($mlw_question->question_type == 5)
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				if ($mlw_question_settings['required'] == 0) {$mlw_requireClass = "mlwRequiredText";} else {$mlw_requireClass = "";}
				$question_display .= "<textarea class='mlw_answer_open_text $mlw_requireClass' cols='70' rows='5' name='question".$mlw_question->question_id."' /></textarea>";
				$question_display .= "<br />";
			}
			elseif ($mlw_question->question_type == 6)
			{
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES);
				$question_display .= "<br />";
			}
			elseif ($mlw_question->question_type == 7)
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				if ($mlw_question_settings['required'] == 0) {$mlw_requireClass = "mlwRequiredNumber";} else {$mlw_requireClass = "";}
				$question_display .= "<input type='number' class='mlw_answer_number $mlw_requireClass' name='question".$mlw_question->question_id."' />";
				$question_display .= "<br />";
			}
			elseif ($mlw_question->question_type == 8)
			{
				if ($mlw_question_settings['required'] == 0) {$mlw_requireClass = "mlwRequiredCheck";} else {$mlw_requireClass = "";}
				$question_display .= "<input type='checkbox' id='mlwAcceptance' class='$mlw_requireClass ' />";
				$question_display .= "<label for='mlwAcceptance'><span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span></label>";
				$question_display .= "<br />";
			}
			elseif ($mlw_question->question_type == 9)
			{
				if ($mlw_question_settings['required'] == 0) {$mlw_requireClass = "mlwRequiredCaptcha";} else {$mlw_requireClass = "";}
				$question_display .= "<div class='mlw_captchaWrap'>";
				$question_display .= "<canvas alt='' id='mlw_captcha' class='mlw_captcha' width='100' height='50'></canvas>";
				$question_display .= "</div>";
				$question_display .= "<span class='mlw_qmn_question'>";
		        $question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
		        $question_display .= "<input type='text' class='mlw_answer_open_text $mlw_requireClass' id='mlw_captcha_text' name='mlw_user_captcha'/>";
		        $question_display .= "<input type='hidden' name='mlw_code_captcha' id='mlw_code_captcha' value='none' />";
				$question_display .= "<br />";
				$question_display .= "<script>
				var mlw_code = '';
				var mlw_chars = '0123456789ABCDEFGHIJKL!@#$%^&*()MNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz';
				var mlw_code_length = 5;
				for (var i=0; i<mlw_code_length; i++) {
		            var rnum = Math.floor(Math.random() * mlw_chars.length);
		            mlw_code += mlw_chars.substring(rnum,rnum+1);
		        }
		        var mlw_captchaCTX = document.getElementById('mlw_captcha').getContext('2d');
		        mlw_captchaCTX.font = 'normal 24px Verdana';
		        mlw_captchaCTX.strokeStyle = '#000000';
		        mlw_captchaCTX.clearRect(0,0,100,50);
		        mlw_captchaCTX.strokeText(mlw_code,10,30,70);
		        mlw_captchaCTX.textBaseline = 'middle';
		        document.getElementById('mlw_code_captcha').value = mlw_code;
		        </script>
		        ";
			}
			else
			{
				$question_display .= "<span class='mlw_qmn_question'>";
				$qmn_total_questions = $qmn_total_questions + 1;
				if ($qmn_quiz_options->question_numbering == 1) { $question_display .= $qmn_total_questions.") "; }
				$question_display .= htmlspecialchars_decode($mlw_question->question_name, ENT_QUOTES)."</span><br />";
				if ($mlw_question_settings['required'] == 0) {$mlw_requireClass = "mlwRequiredText";} else {$mlw_requireClass = "";}
				$question_display .= "<input type='text' class='mlw_answer_open_text $mlw_requireClass' name='question".$mlw_question->question_id."' />";
				$question_display .= "<br />";
			}
			if ($mlw_question->comments == 0)
			{
				$question_display .= "<input type='text' class='mlw_qmn_question_comment' x-webkit-speech id='mlwComment".$mlw_question->question_id."' name='mlwComment".$mlw_question->question_id."' value='".esc_attr(htmlspecialchars_decode($qmn_quiz_options->comment_field_text, ENT_QUOTES))."' onclick='clear_field(this)'/>";
				$question_display .= "<br />";
			}
			if ($mlw_question->comments == 2)
			{
				$question_display .= "<textarea cols='70' rows='5' class='mlw_qmn_question_comment' id='mlwComment".$mlw_question->question_id."' name='mlwComment".$mlw_question->question_id."' onclick='clear_field(this)'>".htmlspecialchars_decode($qmn_quiz_options->comment_field_text, ENT_QUOTES)."</textarea>";
				$question_display .= "<br />";
			}
			if ($mlw_question->hints != "")
			{
				$question_display .= "<span title=\"".htmlspecialchars_decode($mlw_question->hints, ENT_QUOTES)."\" class='mlw_qmn_hint_link'>".__('Hint', 'quiz-master-next')."</span>";
				$question_display .= "<br /><br />";
			}
			$question_display .= "</div>";
			if ( $qmn_quiz_options->pagination == 0) { $question_display .= "<br />"; }
		}
		return $question_display;
	}

	public function display_comment_section($qmn_quiz_options, $qmn_array_for_variables)
	{
		global $mlw_qmn_section_count;
		$comment_display = '';
		if ($qmn_quiz_options->comment_section == 0)
		{
			$mlw_qmn_section_count = $mlw_qmn_section_count + 1;
			$comment_display .= "<div class='quiz_section slide".$mlw_qmn_section_count."'>";
			$message_comments = htmlspecialchars_decode($qmn_quiz_options->message_comment, ENT_QUOTES);
			$message_comments = apply_filters( 'mlw_qmn_template_variable_quiz_page', $message_comments, $qmn_array_for_variables);
			$comment_display .= "<label for='mlwQuizComments' class='mlw_qmn_comment_section_text'>$message_comments</label><br />";
			$comment_display .= "<textarea cols='60' rows='15' id='mlwQuizComments' name='mlwQuizComments' ></textarea>";
			$comment_display .= "</div>";
			if ( $qmn_quiz_options->pagination == 0) { $comment_display .= "<br /><br />"; }
		}
		return $comment_display;
	}

	public function display_end_section($qmn_quiz_options, $qmn_array_for_variables)
	{
		global $mlw_qmn_section_count;
		$section_display = '';
		$section_display .= "<br />";
		$mlw_qmn_section_count = $mlw_qmn_section_count + 1;
		$section_display .= "<div class='quiz_section slide$mlw_qmn_section_count quiz_end'>";
		if ($qmn_quiz_options->message_end_template != '')
		{
			$message_end = htmlspecialchars_decode($qmn_quiz_options->message_end_template, ENT_QUOTES);
			$message_end = apply_filters( 'mlw_qmn_template_variable_quiz_page', $message_end, $qmn_array_for_variables);
			$section_display .= "<span class='mlw_qmn_message_end'>$message_end</span>";
			$section_display .= "<br /><br />";
		}
		if ($qmn_quiz_options->contact_info_location == 1)
		{
			$section_display .= mlwDisplayContactInfo($qmn_quiz_options);
		}

		//Legacy Code
		ob_start();
	    do_action('mlw_qmn_end_quiz_section');
	    $section_display .= ob_get_contents();
    ob_end_clean();

		$section_display .= "<span style='display: none;'>If you are human, leave this field blank or you will be considered spam:</span>";
		$section_display .= "<input style='display: none;' type='text' name='email' id='email' />";
		$section_display .= "<input type='submit' value='".esc_attr(htmlspecialchars_decode($qmn_quiz_options->submit_button_text, ENT_QUOTES))."' />";
		$section_display .= "<span name='mlw_error_message_bottom' id='mlw_error_message_bottom' class='qmn_error'></span><br />";
		$section_display .= "</div>";

		return $section_display;
	}

	public function display_results($qmn_quiz_options, $qmn_quiz_questions, $qmn_quiz_answers, $qmn_array_for_variables)
	{
		global $qmn_allowed_visit;
		$result_display = '';
		$result_display = apply_filters('qmn_begin_results', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
		if (!$qmn_allowed_visit)
		{
			return $result_display;
		}
		wp_enqueue_script( 'math_jax', '//cdn.mathjax.org/mathjax/latest/MathJax.js?config=TeX-AMS-MML_HTMLorMML' );
		wp_enqueue_style( 'qmn_quiz_style', plugins_url( 'css/qmn_quiz.css' , __FILE__ ) );
		if ($qmn_quiz_options->theme_selected == "default")
		{
			echo "<style type='text/css'>".$qmn_quiz_options->quiz_stye."</style>";
		}
		else
		{
			echo "<link type='text/css' href='".get_option('mlw_qmn_theme_'.$qmn_quiz_options->theme_selected)."' rel='stylesheet' />";
		}

		$mlw_user_name = isset($_POST["mlwUserName"]) ? $_POST["mlwUserName"] : 'None';
		$mlw_user_comp = isset($_POST["mlwUserComp"]) ? $_POST["mlwUserComp"] : 'None';
		$mlw_user_email = isset($_POST["mlwUserEmail"]) ? $_POST["mlwUserEmail"] : 'None';
		$mlw_user_phone = isset($_POST["mlwUserPhone"]) ? $_POST["mlwUserPhone"] : 'None';
		$mlw_qmn_timer = isset($_POST["timer"]) ? $_POST["timer"] : 0;
		$qmn_array_for_variables['user_name'] = $mlw_user_name;
		$qmn_array_for_variables['user_business'] = $mlw_user_comp;
		$qmn_array_for_variables['user_email'] = $mlw_user_email;
		$qmn_array_for_variables['user_phone'] = $mlw_user_phone;
		$qmn_array_for_variables['user_id'] = get_current_user_id();
		$qmn_array_for_variables['timer'] = $mlw_qmn_timer;


		$result_display .= "<div id='top_of_results'></div>";
		$result_display .= "<script>
		window.location.hash='top_of_results';
		</script>";
		?>
		<script type="text/javascript">
			window.sessionStorage.setItem('mlw_time_quiz<?php echo $qmn_array_for_variables['quiz_id']; ?>', 'completed');
			window.sessionStorage.setItem('mlw_started_quiz<?php echo $qmn_array_for_variables['quiz_id']; ?>', "no");
		</script>
		<?php
		if (empty($_POST["email"]) && ((!isset($_POST["mlw_code_captcha"])) || isset($_POST["mlw_code_captcha"]) && $_POST["mlw_user_captcha"] == $_POST["mlw_code_captcha"]))
		{
			$qmn_array_for_variables = array_merge($qmn_array_for_variables,$this->check_answers($qmn_quiz_questions, $qmn_quiz_answers, $qmn_quiz_options, $qmn_array_for_variables));
			$result_display = apply_filters('qmn_after_check_answers', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
			$qmn_array_for_variables['comments'] = $this->check_comment_section($qmn_quiz_options, $qmn_array_for_variables);
			$result_display = apply_filters('qmn_after_check_comments', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
			$qmn_array_for_variables['certificate_link'] = $this->generate_certificate($qmn_quiz_options, $qmn_array_for_variables);
			$result_display = apply_filters('qmn_after_generate_certificate', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
			$result_display .= $this->display_results_text($qmn_quiz_options, $qmn_array_for_variables);
			$result_display = apply_filters('qmn_after_results_text', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
			$result_display .= $this->display_social($qmn_quiz_options, $qmn_array_for_variables);
			$result_display = apply_filters('qmn_after_social_media', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
			$this->send_user_email($qmn_quiz_options, $qmn_array_for_variables);
			$result_display = apply_filters('qmn_after_send_user_email', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
			$this->send_admin_email($qmn_quiz_options, $qmn_array_for_variables);
			$result_display = apply_filters('qmn_after_send_admin_email', $result_display, $qmn_quiz_options, $qmn_array_for_variables);

			//Save the results into database
			$mlw_quiz_results_array = array( intval($qmn_array_for_variables['timer']), $qmn_array_for_variables['question_answers_array'], htmlspecialchars(stripslashes($qmn_array_for_variables['comments']), ENT_QUOTES));
			$mlw_quiz_results = serialize($mlw_quiz_results_array);

			global $wpdb;
			$table_name = $wpdb->prefix . "mlw_results";
			$results_insert = $wpdb->insert(
				$table_name,
				array(
					'quiz_id' => $qmn_array_for_variables['quiz_id'],
					'quiz_name' => $qmn_array_for_variables['quiz_name'],
					'quiz_system' => $qmn_array_for_variables['quiz_system'],
					'point_score' => $qmn_array_for_variables['total_points'],
					'correct_score' => $qmn_array_for_variables['total_score'],
					'correct' => $qmn_array_for_variables['total_correct'],
					'total' => $qmn_array_for_variables['total_questions'],
					'name' => $qmn_array_for_variables['user_name'],
					'business' => $qmn_array_for_variables['user_business'],
					'email' => $qmn_array_for_variables['user_email'],
					'phone' => $qmn_array_for_variables['user_phone'],
					'user' => $qmn_array_for_variables['user_id'],
					'time_taken' => date("h:i:s A m/d/Y"),
					'time_taken_real' => date("Y-m-d H:i:s"),
					'quiz_results' => $mlw_quiz_results,
					'deleted' => 0
				),
				array(
					'%d',
					'%s',
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);
			$result_display = apply_filters('qmn_end_results', $result_display, $qmn_quiz_options, $qmn_array_for_variables);
			//Legacy Code
			do_action('mlw_qmn_load_results_page', $wpdb->insert_id, $qmn_quiz_options->quiz_settings);
		}
		else
		{
			$result_display .= "Thank you.";
		}
		return $result_display;
	}

	public function check_answers($qmn_quiz_questions, $qmn_quiz_answers, $qmn_quiz_options, $qmn_array_for_variables)
	{
		$mlw_points = 0;
		$mlw_correct = 0;
		$mlw_total_score = 0;
		$mlw_question_answers = "";
		isset($_POST["total_questions"]) ? $mlw_total_questions = intval($_POST["total_questions"]) : $mlw_total_questions = 0;

		$mlw_user_text = "";
		$mlw_correct_text = "";
		$qmn_correct = "incorrect";
		$qmn_answer_points = 0;
		$mlw_qmn_answer_array = array();
		foreach($qmn_quiz_questions as $mlw_question)
		{
			$mlw_user_text = "";
			$mlw_correct_text = "";
			$qmn_correct = "incorrect";
			$qmn_answer_points = 0;
			if ( isset($_POST["question".$mlw_question->question_id]) || isset($_POST["mlwComment".$mlw_question->question_id]) )
			{
				if ( $mlw_question->question_type == 0 || $mlw_question->question_type == 1 || $mlw_question->question_type == 2)
				{
					if (isset($_POST["question".$mlw_question->question_id]))
					{
						$mlw_user_answer = $_POST["question".$mlw_question->question_id];
					}
					else
					{
						$mlw_user_answer = " ";
					}
					$mlw_qmn_question_answers_array = $qmn_quiz_answers[$mlw_question->question_id];
					foreach($mlw_qmn_question_answers_array as $mlw_qmn_question_answers_each)
					{
						if (htmlspecialchars(stripslashes($mlw_user_answer), ENT_QUOTES) == esc_attr($mlw_qmn_question_answers_each[0]))
						{
							$mlw_points += $mlw_qmn_question_answers_each[1];
							$qmn_answer_points = += $mlw_qmn_question_answers_each[1];
							$mlw_user_text .= strval(htmlspecialchars_decode($mlw_qmn_question_answers_each[0], ENT_QUOTES));
							if ($mlw_qmn_question_answers_each[2] == 1)
							{
								$mlw_correct += 1;
								$qmn_correct = "correct";
							}
						}
						if ($mlw_qmn_question_answers_each[2] == 1)
						{
							$mlw_correct_text .= htmlspecialchars_decode($mlw_qmn_question_answers_each[0], ENT_QUOTES);
						}
					}
				}
				elseif ( $mlw_question->question_type == 3 ||  $mlw_question->question_type == 5 ||  $mlw_question->question_type == 7)
				{
					if (isset($_POST["question".$mlw_question->question_id]))
					{
						$mlw_user_answer = $_POST["question".$mlw_question->question_id];
					}
					else
					{
						$mlw_user_answer = " ";
					}
					$mlw_user_text .= strval(stripslashes(htmlspecialchars_decode($mlw_user_answer, ENT_QUOTES)));
					$mlw_qmn_question_answers_array = $qmn_quiz_answers[$mlw_question->question_id];
					foreach($mlw_qmn_question_answers_array as $mlw_qmn_question_answers_each)
					{
						$mlw_correct_text = strval(htmlspecialchars_decode($mlw_qmn_question_answers_each[0], ENT_QUOTES));
						if (strtoupper($mlw_user_text) == strtoupper($mlw_correct_text))
						{
							$mlw_correct += 1;
							$qmn_correct = "correct";
							$mlw_points += $mlw_qmn_question_answers_each[1];
							$qmn_answer_points = += $mlw_qmn_question_answers_each[1];
							break;
						}
					}
				}
				elseif ( $mlw_question->question_type == 4 ||  $mlw_question->question_type == 10)
				{
					$mlw_qmn_user_correct_answers = 0;
					$mlw_qmn_total_correct_answers = 0;
					$mlw_qmn_question_answers_array = $qmn_quiz_answers[$mlw_question->question_id];
					$mlw_qmn_total_answers = count($mlw_qmn_question_answers_array);
					foreach($mlw_qmn_question_answers_array as $mlw_qmn_question_answers_each)
					{
						for ($i = 1; $i <= $mlw_qmn_total_answers; $i++) {
						    if (isset($_POST["question".$mlw_question->question_id."_".$i]) && htmlspecialchars(stripslashes($_POST["question".$mlw_question->question_id."_".$i]), ENT_QUOTES) == esc_attr($mlw_qmn_question_answers_each[0]))
						    {
						    	$mlw_points += $mlw_qmn_question_answers_each[1];
									$qmn_answer_points = += $mlw_qmn_question_answers_each[1];
								$mlw_user_text .= strval(htmlspecialchars_decode($mlw_qmn_question_answers_each[0], ENT_QUOTES)).".";
								if ($mlw_qmn_question_answers_each[2] == 1)
								{
									$mlw_qmn_user_correct_answers += 1;
								}
								else
								{
									$mlw_qmn_user_correct_answers = -1;
								}
						    }
						}
						if ($mlw_qmn_question_answers_each[2] == 1)
						{
							$mlw_correct_text .= htmlspecialchars_decode($mlw_qmn_question_answers_each[0], ENT_QUOTES).".";
							$mlw_qmn_total_correct_answers++;
						}
					}
					if ($mlw_qmn_user_correct_answers == $mlw_qmn_total_correct_answers)
					{
						$mlw_correct += 1;
						$qmn_correct = "correct";
					}
				}
				if (isset($_POST["mlwComment".$mlw_question->question_id]))
				{
					$mlw_qm_question_comment = $_POST["mlwComment".$mlw_question->question_id];
				}
				else
				{
					$mlw_qm_question_comment = "";
				}

				$mlw_qmn_answer_array[] = apply_filters('qmn_answer_array', array($mlw_question->question_name, htmlspecialchars($mlw_user_text, ENT_QUOTES), htmlspecialchars($mlw_correct_text, ENT_QUOTES), htmlspecialchars(stripslashes($mlw_qm_question_comment), ENT_QUOTES), "correct" => $qmn_correct, "id" => $mlw_question->question_id, "points" => $qmn_answer_points), $qmn_quiz_options, $qmn_array_for_variables);
			}

		}

		//Calculate Total Percent Score And Average Points Only If Total Questions Doesn't Equal Zero To Avoid Division By Zero Error
		if ($mlw_total_questions != 0)
		{
			$mlw_total_score = round((($mlw_correct/$mlw_total_questions)*100), 2);
			$mlw_average_points = round(($mlw_points/$mlw_total_questions), 2);
		}
		else
		{
			$mlw_total_score = 0;
			$mlw_average_points = 0;
		}

		return array(
			'total_points' => $mlw_points,
			'total_score' => $mlw_total_score,
			'total_correct' => $mlw_correct,
			'total_questions' => $mlw_total_questions,
			'question_answers_display' => $mlw_question_answers,
			'question_answers_array' => $mlw_qmn_answer_array,
		);
	}

	public function check_comment_section($qmn_quiz_options, $qmn_array_for_variables)
	{
		$qmn_quiz_comments = "";
		if (isset($_POST["mlwQuizComments"]))
		{
			$qmn_quiz_comments = $_POST["mlwQuizComments"];
		}
		return apply_filters('qmn_returned_comments', $qmn_quiz_comments, $qmn_quiz_options, $qmn_array_for_variables);
	}

	public function generate_certificate($qmn_quiz_options, $qmn_array_for_variables)
	{
		$mlw_certificate_link = "";
		if (is_serialized($qmn_quiz_options->certificate_template) && is_array(@unserialize($qmn_quiz_options->certificate_template)))
		{
			$mlw_certificate_options = unserialize($qmn_quiz_options->certificate_template);
		}
		else
		{
			$mlw_certificate_options = array('Enter title here', 'Enter text here', '', '', 1);
		}
    if ($mlw_certificate_options[4] == 0)
    {
		$mlw_message_certificate = $mlw_certificate_options[1];
		$mlw_message_certificate = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message_certificate, $qmn_array_for_variables);
		$mlw_message_certificate = str_replace( "\n" , "<br>", $mlw_message_certificate);
		$mlw_plugindirpath = plugin_dir_path( __FILE__ );
		$plugindirpath=plugin_dir_path( __FILE__ );
		$mlw_qmn_certificate_file=<<<EOC
<?php
include("$plugindirpath/fpdf/WriteHTML.php");
\$pdf=new PDF_HTML();
\$pdf->AddPage('L');
EOC;
			$mlw_qmn_certificate_file.=$mlw_certificate_options[3] != '' ? '$pdf->Image("'.$mlw_certificate_options[3].'",0,0,$pdf->w, $pdf->h);' : '';
			$mlw_qmn_certificate_file.=<<<EOC
\$pdf->Ln(20);
\$pdf->SetFont('Arial','B',24);
\$pdf->MultiCell(280,20,'$mlw_certificate_options[0]',0,'C');
\$pdf->Ln(15);
\$pdf->SetFont('Arial','',16);
\$pdf->WriteHTML("<p align='center'>$mlw_message_certificate</p>");
EOC;
			$mlw_qmn_certificate_file.=$mlw_certificate_options[2] != '' ? '$pdf->Image("'.$mlw_certificate_options[2].'",110,130);' : '';
			$mlw_qmn_certificate_file.=<<<EOC
\$pdf->Output('mlw_qmn_certificate.pdf','D');
unlink(__FILE__);
EOC;
			$mlw_qmn_certificate_filename = str_replace(home_url()."/", '', plugin_dir_url( __FILE__ ))."certificates/mlw_qmn_quiz".date("YmdHis").$qmn_array_for_variables['timer'].".php";
			file_put_contents($mlw_qmn_certificate_filename, $mlw_qmn_certificate_file);
			$mlw_qmn_certificate_filename = plugin_dir_url( __FILE__ )."certificates/mlw_qmn_quiz".date("YmdHis").$qmn_array_for_variables['timer'].".php";
			$mlw_certificate_link = "<a href='".$mlw_qmn_certificate_filename."' class='qmn_certificate_link'>Download Certificate</a>";
	    }
			return $mlw_certificate_link;
	}

	public function display_results_text($qmn_quiz_options, $qmn_array_for_variables)
	{
		$results_text_display = '';
		if (is_serialized($qmn_quiz_options->message_after) && is_array(@unserialize($qmn_quiz_options->message_after)))
		{
			$mlw_message_after_array = @unserialize($qmn_quiz_options->message_after);
			//Cycle through landing pages
			foreach($mlw_message_after_array as $mlw_each)
			{
				//Check to see if default
				if ($mlw_each[0] == 0 && $mlw_each[1] == 0)
				{
					$mlw_message_after = htmlspecialchars_decode($mlw_each[2], ENT_QUOTES);
					$mlw_message_after = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message_after, $qmn_array_for_variables);
					$mlw_message_after = str_replace( "\n" , "<br>", $mlw_message_after);
					$results_text_display .= $mlw_message_after;
					break;
				}
				else
				{
					//Check to see if points fall in correct range
					if ($qmn_quiz_options->system == 1 && $qmn_array_for_variables['total_points'] >= $mlw_each[0] && $qmn_array_for_variables['total_points'] <= $mlw_each[1])
					{
						$mlw_message_after = htmlspecialchars_decode($mlw_each[2], ENT_QUOTES);
						$mlw_message_after = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message_after, $qmn_array_for_variables);
						$mlw_message_after = str_replace( "\n" , "<br>", $mlw_message_after);
						$results_text_display .= $mlw_message_after;
						break;
					}
					//Check to see if score fall in correct range
					if ($qmn_quiz_options->system == 0 && $qmn_array_for_variables['total_score'] >= $mlw_each[0] && $qmn_array_for_variables['total_score'] <= $mlw_each[1])
					{
						$mlw_message_after = htmlspecialchars_decode($mlw_each[2], ENT_QUOTES);
						$mlw_message_after = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message_after, $qmn_array_for_variables);
						$mlw_message_after = str_replace( "\n" , "<br>", $mlw_message_after);
						$results_text_display .= $mlw_message_after;
						break;
					}
				}
			}
		}
		else
		{
			//Prepare the after quiz message
			$mlw_message_after = htmlspecialchars_decode($qmn_quiz_options->message_after, ENT_QUOTES);
			$mlw_message_after = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message_after, $qmn_array_for_variables);
			$mlw_message_after = str_replace( "\n" , "<br>", $mlw_message_after);
			$results_text_display .= $mlw_message_after;
		}
		return do_shortcode( $results_text_display );
	}

	public function display_social($qmn_quiz_options, $qmn_array_for_variables)
	{
		$social_display = '';
		if ($qmn_quiz_options->social_media == 1)
		{
			wp_enqueue_script( 'qmn_quiz_social_share', plugins_url( 'js/qmn_social_share.js' , __FILE__ ) );

			//Load Social Media Text
			$qmn_social_media_text = "";
			if (is_serialized($qmn_quiz_options->social_media_text) && is_array(@unserialize($qmn_quiz_options->social_media_text)))
			{
				$qmn_social_media_text = @unserialize($qmn_quiz_options->social_media_text);
			}
			else
			{
				$qmn_social_media_text = array(
		        		'twitter' => $qmn_quiz_options->social_media_text,
		        		'facebook' => $qmn_quiz_options->social_media_text
		        	);
			}
			$qmn_social_media_text["twitter"] = apply_filters( 'mlw_qmn_template_variable_results_page', $qmn_social_media_text["twitter"], $qmn_array_for_variables);
			$qmn_social_media_text["facebook"] = apply_filters( 'mlw_qmn_template_variable_results_page', $qmn_social_media_text["facebook"], $qmn_array_for_variables);
			$social_display .= "<br />
			<a class=\"mlw_qmn_quiz_link\" onclick=\"mlw_qmn_share('facebook', '".esc_js($qmn_social_media_text["facebook"])."', '".esc_js($qmn_quiz_options->quiz_name)."');\">Facebook</a>
			<a class=\"mlw_qmn_quiz_link\" onclick=\"mlw_qmn_share('twitter', '".esc_js($qmn_social_media_text["twitter"])."', '".esc_js($qmn_quiz_options->quiz_name)."');\">Twitter</a>
			<br />";
		}
		return apply_filters('qmn_returned_social_buttons', $social_display, $qmn_quiz_options, $qmn_array_for_variables);
	}

	public function send_user_email($qmn_quiz_options, $qmn_array_for_variables)
	{
		add_filter( 'wp_mail_content_type', 'mlw_qmn_set_html_content_type' );
		$mlw_message = "";
		if ($qmn_quiz_options->send_user_email == "0")
		{
			if ($qmn_array_for_variables['user_email'] != "")
			{
				if (is_serialized($qmn_quiz_options->user_email_template) && is_array(@unserialize($qmn_quiz_options->user_email_template)))
				{
					$mlw_user_email_array = @unserialize($qmn_quiz_options->user_email_template);

					//Cycle through landing pages
					foreach($mlw_user_email_array as $mlw_each)
					{

						//Generate Email Subject
						if (!isset($mlw_each[3]))
						{
							$mlw_each[3] = "Quiz Results For %QUIZ_NAME";
						}
						$mlw_each[3] = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_each[3], $qmn_array_for_variables);

						//Check to see if default
						if ($mlw_each[0] == 0 && $mlw_each[1] == 0)
						{
							$mlw_message = htmlspecialchars_decode($mlw_each[2], ENT_QUOTES);
							$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
							$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
							$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
							$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
							$mlw_headers = 'From: '.$qmn_quiz_options->email_from_text.' <'.$qmn_quiz_options->admin_email.'>' . "\r\n";
							wp_mail($qmn_array_for_variables['user_email'], $mlw_each[3], $mlw_message, $mlw_headers);
							break;
						}
						else
						{
							if ($qmn_quiz_options->system == 1 && $qmn_array_for_variables['total_points'] >= $mlw_each[0] && $qmn_array_for_variables['total_points'] <= $mlw_each[1])
							{
								$mlw_message = htmlspecialchars_decode($mlw_each[2], ENT_QUOTES);
								$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
								$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
								$mlw_headers = 'From: '.$qmn_quiz_options->email_from_text.' <'.$qmn_quiz_options->admin_email.'>' . "\r\n";
								wp_mail($qmn_array_for_variables['user_email'], $mlw_each[3], $mlw_message, $mlw_headers);
								break;
							}

							//Check to see if score fall in correct range
							if ($qmn_quiz_options->system == 0 && $qmn_array_for_variables['total_score'] >= $mlw_each[0] && $qmn_array_for_variables['total_score'] <= $mlw_each[1])
							{
								$mlw_message = htmlspecialchars_decode($mlw_each[2], ENT_QUOTES);
								$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
								$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
								$mlw_headers = 'From: '.$qmn_quiz_options->email_from_text.' <'.$qmn_quiz_options->admin_email.'>' . "\r\n";
								wp_mail($qmn_array_for_variables['user_email'], $mlw_each[3], $mlw_message, $mlw_headers);
								break;
							}
						}
					}
				}
				else
				{
					$mlw_message = htmlspecialchars_decode($qmn_quiz_options->user_email_template, ENT_QUOTES);
					$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
					$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
					$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
					$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
					$mlw_headers = 'From: '.$qmn_quiz_options->email_from_text.' <'.$qmn_quiz_options->admin_email.'>' . "\r\n";
					wp_mail($qmn_array_for_variables['user_email'], "Quiz Results For ".$qmn_quiz_options->quiz_name, $mlw_message, $mlw_headers);
				}
			}
		}
		remove_filter( 'wp_mail_content_type', 'mlw_qmn_set_html_content_type' );
	}

	public function send_admin_email($qmn_quiz_options, $qmn_array_for_variables)
	{
		//Switch email type to HTML
		add_filter( 'wp_mail_content_type', 'mlw_qmn_set_html_content_type' );

		$mlw_message = "";
		if ($qmn_quiz_options->send_admin_email == "0")
		{
			if ($qmn_quiz_options->admin_email != "")
			{
				$mlw_message = "";
				$mlw_subject = "";
				if (is_serialized($qmn_quiz_options->admin_email_template) && is_array(@unserialize($qmn_quiz_options->admin_email_template)))
				{
					$mlw_admin_email_array = @unserialize($qmn_quiz_options->admin_email_template);

					//Cycle through landing pages
					foreach($mlw_admin_email_array as $mlw_each)
					{

						//Generate Email Subject
						if (!isset($mlw_each["subject"]))
						{
							$mlw_each["subject"] = "Quiz Results For %QUIZ_NAME";
						}
						$mlw_each["subject"] = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_each["subject"], $qmn_array_for_variables);

						//Check to see if default
						if ($mlw_each["begin_score"] == 0 && $mlw_each["end_score"] == 0)
						{
							$mlw_message = htmlspecialchars_decode($mlw_each["message"], ENT_QUOTES);
							$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
							$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
							$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
							$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
							$mlw_subject = $mlw_each["subject"];
							break;
						}
						else
						{
							//Check to see if points fall in correct range
							if ($qmn_quiz_options->system == 1 && $qmn_array_for_variables['total_points'] >= $mlw_each["begin_score"] && $qmn_array_for_variables['total_points'] <= $mlw_each["end_score"])
							{
								$mlw_message = htmlspecialchars_decode($mlw_each["message"], ENT_QUOTES);
								$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
								$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
								$mlw_subject = $mlw_each["subject"];
								break;
							}

							//Check to see if score fall in correct range
							if ($qmn_quiz_options->system == 0 && $qmn_array_for_variables['total_score'] >= $mlw_each["begin_score"] && $qmn_array_for_variables['total_score'] <= $mlw_each["end_score"])
							{
								$mlw_message = htmlspecialchars_decode($mlw_each["message"], ENT_QUOTES);
								$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
								$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
								$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
								$mlw_subject = $mlw_each["subject"];
								break;
							}
						}
					}
				}
				else
				{
					$mlw_message = htmlspecialchars_decode($qmn_quiz_options->admin_email_template, ENT_QUOTES);
					$mlw_message = apply_filters( 'mlw_qmn_template_variable_results_page', $mlw_message, $qmn_array_for_variables);
					$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
					$mlw_message = str_replace( "<br/>" , "<br>", $mlw_message);
					$mlw_message = str_replace( "<br />" , "<br>", $mlw_message);
					$mlw_subject = "Quiz Results For ".$qmn_quiz_options->quiz_name;
				}
			}
			if ( get_option('mlw_advert_shows') == 'true' ) {$mlw_message .= "<br>This email was generated by the Quiz Master Next script by Frank Corso";}
			$mlw_headers = 'From: '.$qmn_quiz_options->email_from_text.' <'.$qmn_quiz_options->admin_email.'>' . "\r\n";
			$mlw_qmn_admin_emails = explode(",", $qmn_quiz_options->admin_email);
			foreach($mlw_qmn_admin_emails as $admin_email)
			{
				wp_mail($admin_email, $mlw_subject, $mlw_message, $mlw_headers);
			}
		}

		//Remove HTML type for emails
		remove_filter( 'wp_mail_content_type', 'mlw_qmn_set_html_content_type' );
	}
}
$qmnQuizManager = new QMNQuizManager();

add_filter('qmn_begin_shortcode', 'qmn_require_login_check', 10, 3);
function qmn_require_login_check($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $qmn_allowed_visit;
	if ( $qmn_quiz_options->require_log_in == 1 && !is_user_logged_in() )
	{
		$qmn_allowed_visit = false;
		$mlw_message = htmlspecialchars_decode($qmn_quiz_options->require_log_in_text, ENT_QUOTES);
		$mlw_message = apply_filters( 'mlw_qmn_template_variable_quiz_page', $mlw_message, $qmn_array_for_variables);
		$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
		$display .= $mlw_message;
		$display .= wp_login_form( array('echo' => false) );
	}
	return $display;
}

add_filter('qmn_begin_shortcode', 'qmn_scheduled_timeframe_check', 10, 3);
function qmn_scheduled_timeframe_check($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $qmn_allowed_visit;
	if (is_serialized($qmn_quiz_options->scheduled_timeframe) && is_array(@unserialize($qmn_quiz_options->scheduled_timeframe)))
	{
		$qmn_scheduled_timeframe = @unserialize($qmn_quiz_options->scheduled_timeframe);
		if ($qmn_scheduled_timeframe["start"] != '' && $qmn_scheduled_timeframe["end"] != '')
		{
			$qmn_scheduled_start = strtotime($qmn_scheduled_timeframe["start"]);
			$qmn_scheduled_end = strtotime($qmn_scheduled_timeframe["end"]) + 86399; ///Added seconds to bring time to 11:59:59 PM of given day
			if (time() < $qmn_scheduled_start | time() > $qmn_scheduled_end)
			{
				$qmn_allowed_visit = false;
				$mlw_message = htmlspecialchars_decode($qmn_quiz_options->scheduled_timeframe_text, ENT_QUOTES);
				$mlw_message = apply_filters( 'mlw_qmn_template_variable_quiz_page', $mlw_message, $qmn_array_for_variables);
				$mlw_message = str_replace( "\n" , "<br>", $mlw_message);
				$display .= $mlw_message;
			}
		}
	}
	return $display;
}

add_filter('qmn_begin_shortcode', 'qmn_total_user_tries_check', 10, 3);
function qmn_total_user_tries_check($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $qmn_allowed_visit;
	if ( $qmn_quiz_options->total_user_tries != 0 && is_user_logged_in() )
	{
		global $wpdb;
		$current_user = wp_get_current_user();
		$mlw_qmn_user_try_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ".$wpdb->prefix."mlw_results WHERE email='%s' AND deleted='0' AND quiz_id=%d", $current_user->user_email, $qmn_array_for_variables['quiz_id'] ) );
		if ($mlw_qmn_user_try_count >= $qmn_quiz_options->total_user_tries)
		{
			$qmn_allowed_visit = false;
			$mlw_message = htmlspecialchars_decode($qmn_quiz_options->total_user_tries_text, ENT_QUOTES);
			$mlw_message = apply_filters( 'mlw_qmn_template_variable_quiz_page', $mlw_message, $qmn_array_for_variables);
			$display .= $mlw_message;
		}
	}
	return $display;
}

add_filter('qmn_begin_shortcode', 'qmn_quiz_name_check', 10, 3);
function qmn_quiz_name_check($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $qmn_allowed_visit;
	if ($qmn_quiz_options->quiz_name == "")
	{
		$qmn_allowed_visit = false;
		$display .= __("It appears that this quiz is not set up correctly.", 'quiz-master-next');
	}
	return $display;
}

add_filter('qmn_begin_quiz', 'qmn_total_tries_check', 10, 3);
function qmn_total_tries_check($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $qmn_allowed_visit;
	if ( $qmn_quiz_options->limit_total_entries != 0 )
	{
		global $wpdb;
		$mlw_qmn_entries_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(quiz_id) FROM ".$wpdb->prefix."mlw_results WHERE deleted='0' AND quiz_id=%d", $qmn_array_for_variables['quiz_id'] ) );
		if ($mlw_qmn_entries_count >= $qmn_quiz_options->limit_total_entries)
		{
			$mlw_message = htmlspecialchars_decode($qmn_quiz_options->limit_total_entries_text, ENT_QUOTES);
			$mlw_message = apply_filters( 'mlw_qmn_template_variable_quiz_page', $mlw_message, $qmn_array_for_variables);
			$display .= $mlw_message;
			$qmn_allowed_visit = false;
		}
	}
	return $display;
}

add_filter('qmn_begin_quiz', 'qmn_pagination_check', 10, 3);
function qmn_pagination_check($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	if ($qmn_quiz_options->pagination != 0)
	{
		global $wpdb;
		$limit_sql = '';
		if ($qmn_quiz_options->question_from_total != 0)
		{
			$limit_sql = " LIMIT ".$qmn_quiz_options->question_from_total;
		}
		$total_questions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(quiz_id) FROM ".$wpdb->prefix."mlw_questions WHERE deleted=0 AND quiz_id=%d".$limit_sql, $qmn_array_for_variables["quiz_id"]));
		$display .= "<style>.quiz_section { display: none; }</style>";
		$mlw_qmn_section_limit = 2 + $total_questions;
		if ($qmn_quiz_options->comment_section == 0)
		{
			$mlw_qmn_section_limit = $mlw_qmn_section_limit + 1;
		}

		//Gather text for pagination buttons
		$mlw_qmn_pagination_text = "";
		if (is_serialized($qmn_quiz_options->pagination_text) && is_array(@unserialize($qmn_quiz_options->pagination_text)))
		{
			$mlw_qmn_pagination_text = @unserialize($qmn_quiz_options->pagination_text);
		}
		else
		{
			$mlw_qmn_pagination_text = array(__('Previous', 'quiz-master-next'), $qmn_quiz_options->pagination_text);
		}
		?>
		<script type="text/javascript">
			var qmn_pagination = <?php echo $qmn_quiz_options->pagination; ?>;
			var qmn_section_limit = <?php echo $mlw_qmn_section_limit; ?>;
			var qmn_pagination_previous_text = '<?php echo $mlw_qmn_pagination_text[0]; ?>';
			var qmn_pagination_next_text = '<?php echo $mlw_qmn_pagination_text[1]; ?>';
		</script>
		<?php
		wp_enqueue_script( 'qmn_quiz_pagination', plugins_url( 'js/qmn_pagination.js' , __FILE__ ) );
	}
	return $display;
}

add_filter('qmn_begin_quiz', 'qmn_timer_check', 10, 3);
function qmn_timer_check($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $qmn_allowed_visit;
	if ($qmn_allowed_visit && $qmn_quiz_options->timer_limit != 0)
	{
		?>
		<div id="mlw_qmn_timer" class="mlw_qmn_timer"></div>
		<script type="text/javascript">
			var qmn_quiz_id = <?php echo $qmn_array_for_variables['quiz_id']; ?>;
			var qmn_timer_limit = <?php echo $qmn_quiz_options->timer_limit; ?>;
		</script>
		<?php
		wp_enqueue_script( 'qmn_quiz_timer', plugins_url( 'js/qmn_timer.js' , __FILE__ ) );
	}
	return $display;
}

add_filter('qmn_begin_quiz', 'qmn_update_views', 10, 3);
function qmn_update_views($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $wpdb;
	$mlw_views = $qmn_quiz_options->quiz_views;
	$mlw_views += 1;
	$results = $wpdb->update(
		$wpdb->prefix . "mlw_quizzes",
		array(
			'quiz_views' => $mlw_views
		),
		array( 'quiz_id' => $qmn_array_for_variables["quiz_id"] ),
		array(
			'%d'
		),
		array( '%d' )
	);
	return $display;
}

add_filter('qmn_begin_results', 'qmn_update_taken', 10, 3);
function qmn_update_taken($display, $qmn_quiz_options, $qmn_array_for_variables)
{
	global $wpdb;
	$mlw_taken = $qmn_quiz_options->quiz_taken;
	$mlw_taken += 1;
	$results = $wpdb->update(
		$wpdb->prefix . "mlw_quizzes",
		array(
			'quiz_taken' => $mlw_taken
		),
		array( 'quiz_id' => $qmn_array_for_variables["quiz_id"] ),
		array(
			'%d'
		),
		array( '%d' )
	);
	return $display;
}

/*
This function displays fields to ask for contact information
*/
function mlwDisplayContactInfo($mlw_quiz_options)
{
	$mlw_contact_display = "";
	//Check to see if user is logged in, then ask for contact if not
	if ( is_user_logged_in() )
	{
		//If this quiz does not let user edit contact information we hide this section
		if ($mlw_quiz_options->loggedin_user_contact == 1)
		{
			$mlw_contact_display .= "<div style='display:none;'>";
		}

		//Retrieve current user information and save into text fields for contact information
		$current_user = wp_get_current_user();
		if ($mlw_quiz_options->user_name != 2)
		{
			$mlw_contact_class = "class=\"\"";
			if ($mlw_quiz_options->user_name == 1)
			{
				$mlw_contact_class = "class=\"mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->name_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserName' value='".$current_user->display_name."' />";
			$mlw_contact_display .= "<br /><br />";

		}
		if ($mlw_quiz_options->user_comp != 2)
		{
			$mlw_contact_class = "class=\"\"";
			if ($mlw_quiz_options->user_comp == 1)
			{
				$mlw_contact_class = "class=\"mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->business_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserComp' value='' />";
			$mlw_contact_display .= "<br /><br />";
		}
		if ($mlw_quiz_options->user_email != 2)
		{
			$mlw_contact_class = "class=\"mlwEmail\"";
			if ($mlw_quiz_options->user_email == 1)
			{
				$mlw_contact_class = "class=\"mlwEmail mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->email_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserEmail' value='".$current_user->user_email."' />";
			$mlw_contact_display .= "<br /><br />";
		}
		if ($mlw_quiz_options->user_phone != 2)
		{
			$mlw_contact_class = "class=\"\"";
			if ($mlw_quiz_options->user_phone == 1)
			{
				$mlw_contact_class = "class=\"mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->phone_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserPhone' value='' />";
			$mlw_contact_display .= "<br /><br />";
		}

		//End of hidden section div
		if ($mlw_quiz_options->loggedin_user_contact == 1)
		{
			$mlw_contact_display .= "</div>";
		}
	}
	else
	{
		//See if the site wants to ask for any contact information, then ask for it
		if ($mlw_quiz_options->user_name != 2)
		{
			$mlw_contact_class = "class=\"\"";
			if ($mlw_quiz_options->user_name == 1)
			{
				$mlw_contact_class = "class=\"mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->name_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserName' value='' />";
			$mlw_contact_display .= "<br /><br />";
		}
		if ($mlw_quiz_options->user_comp != 2)
		{
			$mlw_contact_class = "class=\"\"";
			if ($mlw_quiz_options->user_comp == 1)
			{
				$mlw_contact_class = "class=\"mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->business_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserComp' value='' />";
			$mlw_contact_display .= "<br /><br />";
		}
		if ($mlw_quiz_options->user_email != 2)
		{
			$mlw_contact_class = "class=\"mlwEmail\"";
			if ($mlw_quiz_options->user_email == 1)
			{
				$mlw_contact_class = "class=\"mlwEmail mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->email_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserEmail' value='' />";
			$mlw_contact_display .= "<br /><br />";
		}
		if ($mlw_quiz_options->user_phone != 2)
		{
			$mlw_contact_class = "class=\"\"";
			if ($mlw_quiz_options->user_phone == 1)
			{
				$mlw_contact_class = "class=\"mlwRequiredText\"";
			}
			$mlw_contact_display .= "<span class='mlw_qmn_question'>".htmlspecialchars_decode($mlw_quiz_options->phone_field_text, ENT_QUOTES)."</span><br />";
			$mlw_contact_display .= "<input type='text' $mlw_contact_class x-webkit-speech name='mlwUserPhone' value='' />";
			$mlw_contact_display .= "<br /><br />";
		}
	}
	return $mlw_contact_display;
}

/*
This function helps set the email type to HTML
*/
function mlw_qmn_set_html_content_type() {

	return 'text/html';
}
?>
