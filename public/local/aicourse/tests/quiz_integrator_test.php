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
 * Unit tests for quiz_integrator service
 *
 * @package    local_aicourse
 * @category   test
 * @covers     \local_aicourse\services\quiz_integrator
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\tests;

use advanced_testcase;
use local_aicourse\services\quiz_integrator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Unit tests for quiz_integrator service
 *
 * @package    local_aicourse
 * @category   test
 * @covers     \local_aicourse\services\quiz_integrator
 */
final class quiz_integrator_test extends advanced_testcase {

    /** @var \stdClass Test course */
    private $course;

    /** @var \stdClass Test draft */
    private $draft;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        
        // Create test course
        $this->course = $this->getDataGenerator()->create_course();
        
        // Create test draft
        $this->draft = $this->create_test_draft($this->course->id);
    }

    /**
     * Test successful quiz creation with multiple choice questions
     */
    public function test_create_quiz_success_multichoice(): void {
        $this->setAdminUser();
        
        // Create 5 multiple choice assessments
        $this->create_test_assessments($this->draft->id, 5, 'multiple_choice');
        
        // Execute
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        // Assert quiz was created
        $this->assertGreaterThan(0, $quiz_id);
        
        // Verify quiz exists in database
        $quiz = $this->getDb()->get_record('quiz', ['id' => $quiz_id]);
        $this->assertNotNull($quiz);
        $this->assertEquals($this->course->id, $quiz->course);
        
        // Verify questions were imported
        $this->assert_quiz_has_questions($quiz_id, 5);
    }

    /**
     * Test successful quiz creation with true/false questions
     */
    public function test_create_quiz_success_truefalse(): void {
        $this->setAdminUser();
        
        // Create 3 true/false assessments
        $this->create_test_assessments($this->draft->id, 3, 'true_false');
        
        // Execute
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        // Assert
        $this->assertGreaterThan(0, $quiz_id);
        $this->assert_quiz_has_questions($quiz_id, 3);
    }

    /**
     * Test successful quiz creation with short answer questions
     */
    public function test_create_quiz_success_shortanswer(): void {
        $this->setAdminUser();
        
        // Create 4 short answer assessments
        $this->create_test_assessments($this->draft->id, 4, 'short_answer');
        
        // Execute
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        // Assert
        $this->assertGreaterThan(0, $quiz_id);
        $this->assert_quiz_has_questions($quiz_id, 4);
    }

    /**
     * Test mixed question types in single quiz
     */
    public function test_create_quiz_mixed_types(): void {
        $this->setAdminUser();
        
        // Create mixed assessments
        $this->create_test_assessment($this->draft->id, 'multiple_choice');
        $this->create_test_assessment($this->draft->id, 'true_false');
        $this->create_test_assessment($this->draft->id, 'short_answer');
        
        // Execute
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        // Assert
        $this->assertGreaterThan(0, $quiz_id);
        $this->assert_quiz_has_questions($quiz_id, 3);
    }

    /**
     * Test permission denied for non-privileged user
     */
    public function test_create_quiz_no_permission(): void {
        // Create regular user without quiz capabilities
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        
        $this->create_test_assessments($this->draft->id, 1, 'multiple_choice');
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('nopermissions');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test invalid course ID validation
     */
    public function test_create_quiz_invalid_course_id(): void {
        $this->setAdminUser();
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_course_id');
        
        $integrator = new quiz_integrator(0, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test negative course ID validation
     */
    public function test_create_quiz_negative_course_id(): void {
        $this->setAdminUser();
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_course_id');
        
        $integrator = new quiz_integrator(-1, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test invalid draft ID validation
     */
    public function test_create_quiz_invalid_draft_id(): void {
        $this->setAdminUser();
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_draft_id');
        
        $integrator = new quiz_integrator($this->course->id, 0);
        $integrator->create_quiz();
    }

    /**
     * Test non-existent course
     */
    public function test_create_quiz_nonexistent_course(): void {
        $this->setAdminUser();
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalidcourse');
        
        $integrator = new quiz_integrator(999999, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test draft not belonging to course
     */
    public function test_create_quiz_draft_wrong_course(): void {
        $this->setAdminUser();
        
        // Create draft in different course
        $other_course = $this->getDataGenerator()->create_course();
        $other_draft = $this->create_test_draft($other_course->id);
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_draft');
        
        $integrator = new quiz_integrator($this->course->id, $other_draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test no assessments found
     */
    public function test_create_quiz_no_assessments(): void {
        $this->setAdminUser();
        
        // Don't create any assessments
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('no_assessments_found');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test inactive assessments are excluded
     */
    public function test_create_quiz_excludes_inactive_assessments(): void {
        $this->setAdminUser();
        
        // Create active assessment
        $this->create_test_assessment($this->draft->id, 'multiple_choice', true);
        
        // Create inactive assessment
        $this->create_test_assessment($this->draft->id, 'multiple_choice', false);
        
        // Execute
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        // Should only import active assessment
        $this->assert_quiz_has_questions($quiz_id, 1);
    }

    /**
     * Test invalid assessment data - missing question text
     */
    public function test_create_quiz_missing_question_text(): void {
        $this->setAdminUser();
        
        // Create assessment with empty question text
        $assessment = new \stdClass();
        $assessment->draft_id = $this->draft->id;
        $assessment->question_text = '';
        $assessment->question_type = 'multiple_choice';
        $assessment->correct_answer = 'A';
        $assessment->options = json_encode([
            ['text' => 'Option A', 'is_correct' => true],
            ['text' => 'Option B', 'is_correct' => false]
        ]);
        $assessment->explanation = 'Test explanation';
        $assessment->is_active = 1;
        $assessment->timecreated = time();
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_assessment_data');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test invalid assessment data - missing question type
     */
    public function test_create_quiz_missing_question_type(): void {
        $this->setAdminUser();
        
        // Create assessment with empty question type
        $assessment = new \stdClass();
        $assessment->draft_id = $this->draft->id;
        $assessment->question_text = 'Test question?';
        $assessment->question_type = '';
        $assessment->correct_answer = 'A';
        $assessment->options = json_encode([]);
        $assessment->explanation = '';
        $assessment->is_active = 1;
        $assessment->timecreated = time();
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_assessment_data');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test unsupported question type
     */
    public function test_create_quiz_unsupported_question_type(): void {
        $this->setAdminUser();
        
        // Create assessment with unsupported type
        $assessment = new \stdClass();
        $assessment->draft_id = $this->draft->id;
        $assessment->question_text = 'Test question?';
        $assessment->question_type = 'essay'; // Not supported
        $assessment->correct_answer = 'Answer';
        $assessment->options = json_encode([]);
        $assessment->explanation = '';
        $assessment->is_active = 1;
        $assessment->timecreated = time();
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('unsupported_question_type');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test invalid multiple choice options - empty array
     */
    public function test_create_quiz_mcq_empty_options(): void {
        $this->setAdminUser();
        
        // Create MCQ with no options
        $assessment = new \stdClass();
        $assessment->draft_id = $this->draft->id;
        $assessment->question_text = 'Test question?';
        $assessment->question_type = 'multiple_choice';
        $assessment->correct_answer = 'A';
        $assessment->options = json_encode([]); // Empty
        $assessment->explanation = '';
        $assessment->is_active = 1;
        $assessment->timecreated = time();
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_assessment_data');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test invalid multiple choice options - no correct answer
     */
    public function test_create_quiz_mcq_no_correct_answer(): void {
        $this->setAdminUser();
        
        // Create MCQ with all incorrect answers
        $assessment = new \stdClass();
        $assessment->draft_id = $this->draft->id;
        $assessment->question_text = 'Test question?';
        $assessment->question_type = 'multiple_choice';
        $assessment->correct_answer = 'A';
        $assessment->options = json_encode([
            ['text' => 'Option A', 'is_correct' => false],
            ['text' => 'Option B', 'is_correct' => false],
            ['text' => 'Option C', 'is_correct' => false]
        ]);
        $assessment->explanation = '';
        $assessment->is_active = 1;
        $assessment->timecreated = time();
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_assessment_data');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test invalid multiple choice options - missing fields
     */
    public function test_create_quiz_mcq_missing_option_fields(): void {
        $this->setAdminUser();
        
        // Create MCQ with incomplete option structure
        $assessment = new \stdClass();
        $assessment->draft_id = $this->draft->id;
        $assessment->question_text = 'Test question?';
        $assessment->question_type = 'multiple_choice';
        $assessment->correct_answer = 'A';
        $assessment->options = json_encode([
            ['text' => 'Option A'], // Missing is_correct
            ['is_correct' => false] // Missing text
        ]);
        $assessment->explanation = '';
        $assessment->is_active = 1;
        $assessment->timecreated = time();
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_assessment_data');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
    }

    /**
     * Test transaction rollback on failure
     */
    public function test_create_quiz_transaction_rollback(): void {
        $this->setAdminUser();
        
        // Create valid assessment
        $this->create_test_assessment($this->draft->id, 'multiple_choice');
        
        // Create invalid assessment that will fail
        $assessment = new \stdClass();
        $assessment->draft_id = $this->draft->id;
        $assessment->question_text = ''; // Invalid - empty
        $assessment->question_type = 'multiple_choice';
        $assessment->correct_answer = 'A';
        $assessment->options = json_encode([
            ['text' => 'Option A', 'is_correct' => true]
        ]);
        $assessment->explanation = '';
        $assessment->is_active = 1;
        $assessment->timecreated = time();
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
        
        // Should throw exception and rollback
        $this->expectException(\moodle_exception::class);
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $integrator->create_quiz();
        
        // Verify no quiz was created (transaction rolled back)
        $quizzes = $this->getDb()->get_records('quiz', ['course' => $this->course->id]);
        $this->assertEmpty($quizzes);
    }

    /**
     * Test high-volume import (100+ questions)
     */
    public function test_create_quiz_high_volume(): void {
        $this->setAdminUser();
        
        // Create 100 assessments
        $this->create_test_assessments($this->draft->id, 100, 'multiple_choice');
        
        // Execute
        $start_time = microtime(true);
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        $end_time = microtime(true);
        
        // Assert
        $this->assertGreaterThan(0, $quiz_id);
        $this->assert_quiz_has_questions($quiz_id, 100);
        
        // Performance check: should complete in reasonable time (< 30 seconds)
        $duration = $end_time - $start_time;
        $this->assertLessThan(30, $duration, "Import took too long: {$duration} seconds");
    }

    /**
     * Test question category creation
     */
    public function test_create_quiz_category_created(): void {
        $this->setAdminUser();
        
        $this->create_test_assessments($this->draft->id, 1, 'multiple_choice');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        // Verify category was created
        $context = \context_course::instance($this->course->id);
        $categories = $this->getDb()->get_records(
            'question_categories',
            ['contextid' => $context->id]
        );
        
        $this->assertNotEmpty($categories);
    }

    /**
     * Test quiz slots are sequential
     */
    public function test_create_quiz_sequential_slots(): void {
        $this->setAdminUser();
        
        $this->create_test_assessments($this->draft->id, 5, 'multiple_choice');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        // Get quiz slots
        $slots = $this->getDb()->get_records(
            'quiz_slots',
            ['quizid' => $quiz_id],
            'slot ASC'
        );
        
        // Verify sequential numbering
        $expected_slot = 1;
        foreach ($slots as $slot) {
            $this->assertEquals($expected_slot, $slot->slot);
            $expected_slot++;
        }
    }

    /**
     * Test quiz configuration defaults
     */
    public function test_create_quiz_configuration(): void {
        $this->setAdminUser();
        
        $this->create_test_assessments($this->draft->id, 1, 'multiple_choice');
        
        $integrator = new quiz_integrator($this->course->id, $this->draft->id);
        $quiz_id = $integrator->create_quiz();
        
        $quiz = $this->getDb()->get_record('quiz', ['id' => $quiz_id]);
        
        // Verify sensible defaults
        $this->assertEquals(0, $quiz->attempts); // Unlimited attempts
        $this->assertEquals(1, $quiz->shuffleanswers);
        $this->assertEquals(QUIZ_GRADEHIGHEST, $quiz->grademethod);
        $this->assertEquals(100, $quiz->grade);
    }

    /**
     * Helper: Create test draft
     *
     * @param int $course_id
     * @return \stdClass
     */
    private function create_test_draft(int $course_id): \stdClass {
        $draft = new \stdClass();
        $draft->course_id = $course_id;
        $draft->course_title = 'Test Course';
        $draft->status = 'approved';
        $draft->timecreated = time();
        $draft->timemodified = time();
        
        $draft->id = $this->getDb()->insert_record('local_aicourse_drafts', $draft);
        return $draft;
    }

    /**
     * Helper: Create test assessments
     *
     * @param int $draft_id
     * @param int $count
     * @param string $type
     * @param bool $is_active
     * @return void
     */
    private function create_test_assessments(
        int $draft_id,
        int $count,
        string $type = 'multiple_choice',
        bool $is_active = true
    ): void {
        for ($i = 0; $i < $count; $i++) {
            $this->create_test_assessment($draft_id, $type, $is_active);
        }
    }

    /**
     * Helper: Create single test assessment
     *
     * @param int $draft_id
     * @param string $type
     * @param bool $is_active
     * @return void
     */
    private function create_test_assessment(
        int $draft_id,
        string $type = 'multiple_choice',
        bool $is_active = true
    ): void {
        $assessment = new \stdClass();
        $assessment->draft_id = $draft_id;
        $assessment->question_text = "Test question {$type} #" . rand(1, 1000);
        $assessment->question_type = $type;
        $assessment->explanation = 'Test explanation';
        $assessment->is_active = $is_active ? 1 : 0;
        $assessment->timecreated = time();
        
        switch ($type) {
            case 'multiple_choice':
                $assessment->correct_answer = 'A';
                $assessment->options = json_encode([
                    ['text' => 'Option A', 'is_correct' => true],
                    ['text' => 'Option B', 'is_correct' => false],
                    ['text' => 'Option C', 'is_correct' => false],
                    ['text' => 'Option D', 'is_correct' => false]
                ]);
                break;
            
            case 'true_false':
                $assessment->correct_answer = rand(0, 1) ? 'true' : 'false';
                $assessment->options = json_encode([]);
                break;
            
            case 'short_answer':
                $assessment->correct_answer = 'Correct Answer';
                $assessment->options = json_encode([]);
                break;
        }
        
        $this->getDb()->insert_record('local_aicourse_assessments', $assessment);
    }

    /**
     * Helper: Assert quiz has expected number of questions
     *
     * @param int $quiz_id
     * @param int $expected_count
     * @return void
     */
    private function assert_quiz_has_questions(int $quiz_id, int $expected_count): void {
        $slots = $this->getDb()->get_records('quiz_slots', ['quizid' => $quiz_id]);
        $this->assertCount($expected_count, $slots);
    }

    /**
     * Helper: Get database instance
     *
     * @return \moodle_database
     */
    private function getDb(): \moodle_database {
        global $DB;
        return $DB;
    }
}
