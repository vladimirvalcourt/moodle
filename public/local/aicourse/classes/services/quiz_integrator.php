<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Quiz integration service - Connects AI-generated assessments to Moodle quizzes
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\services;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/dml/moodle_database.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

/**
 * Integrate AI-generated questions into Moodle quiz system
 */
class quiz_integrator {
    
    /** @var int Course ID */
    private $course_id;
    
    /** @var int Draft ID */
    private $draft_id;
    
    /** @var \moodle_database Database instance */
    private $db;
    
    /** @var int Quiz module ID (created) */
    private $quiz_id;
    
    /**
     * Constructor
     *
     * @param int $course_id
     * @param int $draft_id
     */
    public function __construct(int $course_id, int $draft_id) {
        global $DB;
        
        $this->course_id = $course_id;
        $this->draft_id = $draft_id;
        $this->db = $DB;
        $this->quiz_id = null;
    }
    
    /**
     * Create quiz activity and import AI-generated questions
     *
     * @return int Quiz module ID
     * @throws \moodle_exception If validation fails or operation cannot complete
     */
    public function create_quiz(): int {
        // Validate inputs
        $this->validate_inputs();
        
        // Check permissions
        $this->check_permissions();
        
        // Get assessments from database
        $assessments = $this->db->get_records('local_aicourse_assessments', [
            'draft_id' => $this->draft_id,
            'is_active' => 1
        ], 'id ASC');
        
        if (empty($assessments)) {
            throw new \moodle_exception('no_assessments_found', 'local_aicourse');
        }
        
        // Wrap entire operation in transaction for atomicity
        $transaction = $this->db->start_delegated_transaction();
        
        try {
            // Create quiz activity
            $this->quiz_id = $this->create_quiz_activity();
            
            // Create question category
            $category_id = $this->create_question_category();
            
            // Import questions with progress tracking
            $imported_count = 0;
            $failed_count = 0;
            $total_count = count($assessments);
            
            foreach ($assessments as $assessment) {
                try {
                    $this->validate_assessment_data($assessment);
                    $this->import_question($assessment, $category_id);
                    $imported_count++;
                } catch (\Exception $e) {
                    $failed_count++;
                    // Log detailed error for debugging
                    $this->log_import_error($assessment->id, $e);
                    
                    // Fail fast if too many errors (>10% failure rate)
                    if ($total_count > 0 && ($failed_count / $total_count) > 0.1) {
                        throw new \moodle_exception(
                            'too_many_import_failures',
                            'local_aicourse',
                            '',
                            "Failed to import {$failed_count} of {$total_count} questions"
                        );
                    }
                }
            }
            
            // Log summary
            $this->log_import_summary($imported_count, $failed_count);
            
            // Commit transaction only if we have at least some successful imports
            if ($imported_count === 0) {
                throw new \moodle_exception('all_imports_failed', 'local_aicourse');
            }
            
            $transaction->allow_commit();
            
            return $this->quiz_id;
            
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    /**
     * Create quiz activity in course
     *
     * @return int Quiz instance ID
     */
    private function create_quiz_activity(): int {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        
        // Get draft info for quiz name
        $draft = $this->db->get_record('local_aicourse_drafts', ['id' => $this->draft_id]);
        
        // Prepare quiz data
        $quiz_data = new \stdClass();
        $quiz_data->course = $this->course_id;
        $quiz_data->name = get_string('quiztitle', 'local_aicourse', format_string($draft->course_title));
        $quiz_data->intro = get_string('quizdescription', 'local_aicourse');
        $quiz_data->introformat = FORMAT_HTML;
        $quiz_data->timeopen = 0;
        $quiz_data->timeclose = 0;
        $quiz_data->timelimit = 0;
        $quiz_data->overduehandling = 'autoabandon';
        $quiz_data->graceperiod = 60;
        $quiz_data->preferredbehaviour = 'deferredfeedback';
        $quiz_data->canredoquestions = 0;
        $quiz_data->attempts = 0; // Unlimited attempts
        $quiz_data->attemptonlast = 0;
        $quiz_data->grademethod = QUIZ_GRADEHIGHEST;
        $quiz_data->decimalpoints = 2;
        $quiz_data->questiondecimalpoints = -1;
        $quiz_data->reviewattempt = QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_CLOSED;
        $quiz_data->reviewcorrectness = QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_CLOSED;
        $quiz_data->reviewmarks = QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_CLOSED;
        $quiz_data->reviewspecificfeedback = QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_CLOSED;
        $quiz_data->reviewgeneralfeedback = QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_CLOSED;
        $quiz_data->reviewrightanswer = QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_CLOSED;
        $quiz_data->reviewoverallfeedback = QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_CLOSED;
        $quiz_data->questionsperpage = 1;
        $quiz_data->navmethod = QUIZ_NAVMETHOD_FREE;
        $quiz_data->shuffleanswers = 1;
        $quiz_data->sumgrades = 0; // Will be calculated
        $quiz_data->grade = 100;
        $quiz_data->timecreated = time();
        $quiz_data->timemodified = time();
        $quiz_data->password = '';
        $quiz_data->subnet = '';
        $quiz_data->browsersecurity = '';
        $quiz_data->delay1 = 0;
        $quiz_data->delay2 = 0;
        $quiz_data->showuserpicture = QUIZ_SHOWUSERIMAGE_NEVER;
        $quiz_data->showblocks = 0;
        
        // Add quiz module to course section 1
        $modinfo = add_moduleinfo(
            (object)['modulename' => 'quiz', 'instance' => 0],
            (object)['id' => $this->course_id],
            null
        );
        
        // Insert quiz record
        $quiz_instance = $this->db->insert_record('quiz', $quiz_data);
        
        // Update module instance
        $cm = $this->db->get_record('course_modules', [
            'course' => $this->course_id,
            'module' => $modinfo->module,
            'instance' => 0
        ]);
        
        if ($cm) {
            $cm->instance = $quiz_instance;
            $this->db->update_record('course_modules', $cm);
        }
        
        return $quiz_instance;
    }
    
    /**
     * Create question category for this quiz
     *
     * @return int Category ID
     */
    private function create_question_category(): int {
        $category = new \stdClass();
        $category->contextid = \context_course::instance($this->course_id)->id;
        $category->name = get_string('aigeneratedquestions', 'local_aicourse');
        $category->info = get_string('aigeneratedquestions_desc', 'local_aicourse');
        $category->infoformat = FORMAT_HTML;
        $category->sortorder = 999;
        $category->parent = 0;
        
        return $this->db->insert_record('question_categories', $category);
    }
    
    /**
     * Import a single assessment as a Moodle question
     *
     * @param \stdClass $assessment
     * @param int $category_id
     * @return void
     */
    private function import_question(\stdClass $assessment, int $category_id): void {
        switch ($assessment->question_type) {
            case 'multiple_choice':
                $this->import_multichoice_question($assessment, $category_id);
                break;
            case 'true_false':
                $this->import_truefalse_question($assessment, $category_id);
                break;
            case 'short_answer':
                $this->import_shortanswer_question($assessment, $category_id);
                break;
            default:
                throw new \moodle_exception('unsupported_question_type', '', '', $assessment->question_type);
        }
    }
    
    /**
     * Import multiple choice question
     *
     * @param \stdClass $assessment
     * @param int $category_id
     * @return void
     */
    private function import_multichoice_question(\stdClass $assessment, int $category_id): void {
        $options = json_decode($assessment->options, true);
        
        // Create question record
        $question = new \stdClass();
        $question->category = $category_id;
        $question->parent = 0;
        $question->name = substr(s($assessment->question_text), 0, 255);
        $question->questiontext = s($assessment->question_text);
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = s($assessment->explanation ?? '');
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0;
        $question->qtype = 'multichoice';
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $this->get_admin_user_id();
        $question->modifiedby = $this->get_admin_user_id();
        
        $question_id = $this->db->insert_record('question', $question);
        
        // Create multichoice options
        $mc_options = new \stdClass();
        $mc_options->questionid = $question_id;
        $mc_options->layout = 0; // Vertical layout
        $mc_options->single = 1; // Single answer only
        $mc_options->shuffleanswers = 1;
        $mc_options->correctfeedback = get_string('correct', 'local_aicourse');
        $mc_options->partiallycorrectfeedback = get_string('partiallycorrect', 'local_aicourse');
        $mc_options->incorrectfeedback = get_string('incorrect', 'local_aicourse');
        $mc_options->answernumbering = 'abc';
        $mc_options->shownumcorrect = 1;
        
        $this->db->insert_record('question_multichoice', $mc_options);
        
        // Create answers
        foreach ($options as $idx => $option) {
            $answer = new \stdClass();
            $answer->question = $question_id;
            $answer->answer = s($option['text']);
            $answer->answerformat = FORMAT_HTML;
            $answer->fraction = ($option['is_correct'] ? 1 : 0);
            $answer->feedback = '';
            $answer->feedbackformat = FORMAT_HTML;
            
            $this->db->insert_record('question_answers', $answer);
        }
        
        // Add question to quiz
        $this->add_question_to_quiz($question_id, 1);
    }
    
    /**
     * Import true/false question
     *
     * @param \stdClass $assessment
     * @param int $category_id
     * @return void
     */
    private function import_truefalse_question(\stdClass $assessment, int $category_id): void {
        // Create question record
        $question = new \stdClass();
        $question->category = $category_id;
        $question->parent = 0;
        $question->name = substr(s($assessment->question_text), 0, 255);
        $question->questiontext = s($assessment->question_text);
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = s($assessment->explanation ?? '');
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0;
        $question->qtype = 'truefalse';
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $this->get_admin_user_id();
        $question->modifiedby = $this->get_admin_user_id();
        
        $question_id = $this->db->insert_record('question', $question);
        
        // Create true/false options
        $tf_options = new \stdClass();
        $tf_options->questionid = $question_id;
        $tf_options->trueanswer = 1; // True is correct
        $tf_options->falseanswer = 2; // False is incorrect
        
        $this->db->insert_record('question_truefalse', $tf_options);
        
        // Create answers (True and False)
        $is_true_correct = (strtolower($assessment->correct_answer) === 'true');
        
        $true_answer = new \stdClass();
        $true_answer->question = $question_id;
        $true_answer->answer = get_string('true', 'local_aicourse');
        $true_answer->answerformat = FORMAT_HTML;
        $true_answer->fraction = ($is_true_correct ? 1 : 0);
        $true_answer->feedback = '';
        $true_answer->feedbackformat = FORMAT_HTML;
        $this->db->insert_record('question_answers', $true_answer);
        
        $false_answer = new \stdClass();
        $false_answer->question = $question_id;
        $false_answer->answer = get_string('false', 'local_aicourse');
        $false_answer->answerformat = FORMAT_HTML;
        $false_answer->fraction = ($is_true_correct ? 0 : 1);
        $false_answer->feedback = '';
        $false_answer->feedbackformat = FORMAT_HTML;
        $this->db->insert_record('question_answers', $false_answer);
        
        // Add question to quiz
        $this->add_question_to_quiz($question_id, 1);
    }
    
    /**
     * Import short answer question
     *
     * @param \stdClass $assessment
     * @param int $category_id
     * @return void
     */
    private function import_shortanswer_question(\stdClass $assessment, int $category_id): void {
        // Create question record
        $question = new \stdClass();
        $question->category = $category_id;
        $question->parent = 0;
        $question->name = substr(s($assessment->question_text), 0, 255);
        $question->questiontext = s($assessment->question_text);
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = s($assessment->explanation ?? '');
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0;
        $question->qtype = 'shortanswer';
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $this->get_admin_user_id();
        $question->modifiedby = $this->get_admin_user_id();
        
        $question_id = $this->db->insert_record('question', $question);
        
        // Create short answer options
        $sa_options = new \stdClass();
        $sa_options->questionid = $question_id;
        $sa_options->usecase = 0; // Case insensitive
        
        $this->db->insert_record('question_shortanswer', $sa_options);
        
        // Create answer
        $answer = new \stdClass();
        $answer->question = $question_id;
        $answer->answer = s($assessment->correct_answer);
        $answer->answerformat = FORMAT_PLAIN;
        $answer->fraction = 1;
        $answer->feedback = s($assessment->explanation ?? '');
        $answer->feedbackformat = FORMAT_HTML;
        
        $this->db->insert_record('question_answers', $answer);
        
        // Add question to quiz
        $this->add_question_to_quiz($question_id, 1);
    }
    
    /**
     * Add question to quiz with optimized slot calculation
     *
     * @param int $question_id
     * @param float $grade
     * @return void
     * @throws \moodle_exception If quiz not created or slot assignment fails
     */
    private function add_question_to_quiz(int $question_id, float $grade): void {
        if (!$this->quiz_id) {
            throw new \moodle_exception('quiz_not_created', 'local_aicourse');
        }
        
        // Validate grade
        if ($grade <= 0) {
            throw new \moodle_exception('invalid_grade', 'local_aicourse');
        }
        
        $quiz_question = new \stdClass();
        $quiz_question->quizid = $this->quiz_id;
        $quiz_question->questionid = $question_id;
        $quiz_question->maxmark = $grade;
        $quiz_question->slot = $this->get_next_slot();
        
        $this->db->insert_record('quiz_slots', $quiz_question);
    }
    
    /**
     * Get next available slot number
     *
     * @return int
     */
    private function get_next_slot(): int {
        $max_slot = $this->db->get_field(
            'quiz_slots',
            'MAX(slot)',
            ['quizid' => $this->quiz_id]
        );
        
        return ($max_slot ?: 0) + 1;
    }
    
    /**
     * Get admin user ID for question creation
     *
     * @return int User ID
     */
    private function get_admin_user_id(): int {
        global $USER;
        return $USER->id ?? 2; // Default to admin (ID 2) if not logged in
    }
    
    /**
     * Validate input parameters
     *
     * @return void
     * @throws \moodle_exception If validation fails
     */
    private function validate_inputs(): void {
        if ($this->course_id <= 0) {
            throw new \moodle_exception('invalid_course_id', 'local_aicourse');
        }
        
        if ($this->draft_id <= 0) {
            throw new \moodle_exception('invalid_draft_id', 'local_aicourse');
        }
        
        // Verify course exists
        $course = $this->db->get_record('course', ['id' => $this->course_id]);
        if (!$course) {
            throw new \moodle_exception('invalidcourse', 'error');
        }
        
        // Verify draft exists and belongs to course
        $draft = $this->db->get_record('local_aicourse_drafts', [
            'id' => $this->draft_id,
            'course_id' => $this->course_id
        ]);
        
        if (!$draft) {
            throw new \moodle_exception('invalid_draft', 'local_aicourse');
        }
    }
    
    /**
     * Check user permissions for quiz creation
     *
     * @return void
     * @throws \moodle_exception If user lacks required capability
     */
    private function check_permissions(): void {
        $context = \context_course::instance($this->course_id);
        
        if (!has_capability('mod/quiz:addinstance', $context)) {
            throw new \moodle_exception('nopermissions');
        }
        
        if (!has_capability('moodle/question:add', $context)) {
            throw new \moodle_exception('nopermissions');
        }
    }
    
    /**
     * Validate assessment data structure
     *
     * @param \stdClass $assessment
     * @return void
     * @throws \moodle_exception If data is invalid
     */
    private function validate_assessment_data(\stdClass $assessment): void {
        // Check required fields
        if (empty($assessment->question_text)) {
            throw new \moodle_exception(
                'invalid_assessment_data',
                'local_aicourse',
                '',
                'Question text is required'
            );
        }
        
        if (empty($assessment->question_type)) {
            throw new \moodle_exception(
                'invalid_assessment_data',
                'local_aicourse',
                '',
                'Question type is required'
            );
        }
        
        // Validate question type
        $valid_types = ['multiple_choice', 'true_false', 'short_answer'];
        if (!in_array($assessment->question_type, $valid_types)) {
            throw new \moodle_exception(
                'unsupported_question_type',
                'local_aicourse',
                '',
                $assessment->question_type
            );
        }
        
        // Validate options for multiple choice
        if ($assessment->question_type === 'multiple_choice') {
            $options = json_decode($assessment->options ?? '', true);
            
            if (!is_array($options) || empty($options)) {
                throw new \moodle_exception(
                    'invalid_assessment_data',
                    'local_aicourse',
                    '',
                    'Multiple choice questions require valid options array'
                );
            }
            
            // Validate each option has required fields
            foreach ($options as $idx => $option) {
                if (!isset($option['text']) || !isset($option['is_correct'])) {
                    throw new \moodle_exception(
                        'invalid_assessment_data',
                        'local_aicourse',
                        '',
                        "Option {$idx} missing required fields (text, is_correct)"
                    );
                }
            }
            
            // Ensure at least one correct answer
            $has_correct = array_reduce($options, function($carry, $option) {
                return $carry || $option['is_correct'];
            }, false);
            
            if (!$has_correct) {
                throw new \moodle_exception(
                    'invalid_assessment_data',
                    'local_aicourse',
                    '',
                    'Multiple choice questions must have at least one correct answer'
                );
            }
        }
    }
    
    /**
     * Log import error with context
     *
     * @param int $assessment_id
     * @param \Exception $exception
     * @return void
     */
    private function log_import_error(int $assessment_id, \Exception $exception): void {
        // Use Moodle's logging framework
        if (class_exists('\core\event\base')) {
            // Could trigger a custom event here
        }
        
        // Log to PHP error log with context
        error_log(sprintf(
            '[local_aicourse] Question import failed - Assessment ID: %d, Error: %s, Trace: %s',
            $assessment_id,
            $exception->getMessage(),
            $exception->getTraceAsString()
        ));
    }
    
    /**
     * Log import summary statistics
     *
     * @param int $imported_count
     * @param int $failed_count
     * @return void
     */
    private function log_import_summary(int $imported_count, int $failed_count): void {
        $total = $imported_count + $failed_count;
        $success_rate = $total > 0 ? round(($imported_count / $total) * 100, 2) : 0;
        
        // Log summary for monitoring
        error_log(sprintf(
            '[local_aicourse] Quiz import completed - Draft: %d, Total: %d, Imported: %d, Failed: %d, Success Rate: %.2f%%',
            $this->draft_id,
            $total,
            $imported_count,
            $failed_count,
            $success_rate
        ));
    }
}
