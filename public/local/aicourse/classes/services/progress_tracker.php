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
 * Progress tracking service for AI-generated courses
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\services;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/dml/moodle_database.php');

/**
 * Track learner progress through AI-generated courses
 */
class progress_tracker {
    
    /** @var int User ID */
    private $user_id;
    
    /** @var int Course ID */
    private $course_id;
    
    /** @var \moodle_database Database instance */
    private $db;
    
    /**
     * Constructor
     *
     * @param int $user_id
     * @param int $course_id
     */
    public function __construct(int $user_id, int $course_id) {
        global $DB;
        
        $this->user_id = $user_id;
        $this->course_id = $course_id;
        $this->db = $DB;
    }
    
    /**
     * Mark a lesson as viewed/completed
     *
     * @param int $module_index Module index
     * @param int $lesson_index Lesson index within module
     * @return void
     */
    public function mark_lesson_viewed(int $module_index, int $lesson_index): void {
        $existing = $this->db->get_record('local_aicourse_progress', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'module_index' => $module_index,
            'lesson_index' => $lesson_index
        ]);
        
        if (!$existing) {
            $record = new \stdClass();
            $record->user_id = $this->user_id;
            $record->course_id = $this->course_id;
            $record->module_index = $module_index;
            $record->lesson_index = $lesson_index;
            $record->completed = 1;
            $record->timecompleted = time();
            $record->timespent = 0; // TODO: Track actual time spent
            
            $this->db->insert_record('local_aicourse_progress', $record);
        } else {
            // Update existing record
            $existing->timecompleted = time();
            $this->db->update_record('local_aicourse_progress', $existing);
        }
    }
    
    /**
     * Get progress statistics
     *
     * @return array Progress stats
     */
    public function get_progress_stats(): array {
        // Get total lessons from course draft
        $draft = $this->db->get_record('local_aicourse_drafts', ['published_course_id' => $this->course_id]);
        if (!$draft) {
            return [
                'total_lessons' => 0,
                'completed_lessons' => 0,
                'percentage' => 0,
                'is_complete' => false
            ];
        }
        
        $content = json_decode($draft->generated_content, true);
        $total_lessons = 0;
        
        foreach ($content['modules'] as $module) {
            $total_lessons += count($module['lessons']);
        }
        
        // Get completed lessons
        $completed = $this->db->count_records('local_aicourse_progress', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'completed' => 1
        ]);
        
        $percentage = $total_lessons > 0 ? ($completed / $total_lessons) * 100 : 0;
        
        return [
            'total_lessons' => $total_lessons,
            'completed_lessons' => $completed,
            'percentage' => min(100, $percentage),
            'is_complete' => $completed >= $total_lessons
        ];
    }
    
    /**
     * Check if course is complete
     *
     * @return bool
     */
    public function is_course_complete(): bool {
        $stats = $this->get_progress_stats();
        return $stats['is_complete'];
    }
    
    /**
     * Get list of completed lessons
     *
     * @return array Array of completed lesson records
     */
    public function get_completed_lessons(): array {
        return $this->db->get_records('local_aicourse_progress', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'completed' => 1
        ], 'module_index ASC, lesson_index ASC');
    }
    
    /**
     * Get next incomplete lesson
     *
     * @return array|null Next lesson to complete or null if all complete
     */
    public function get_next_lesson(): ?array {
        $draft = $this->db->get_record('local_aicourse_drafts', ['published_course_id' => $this->course_id]);
        if (!$draft) {
            return null;
        }
        
        $content = json_decode($draft->generated_content, true);
        $completed = $this->get_completed_lessons();
        
        // Build set of completed lessons
        $completed_set = [];
        foreach ($completed as $lesson) {
            $key = "{$lesson->module_index}:{$lesson->lesson_index}";
            $completed_set[$key] = true;
        }
        
        // Find first incomplete lesson
        foreach ($content['modules'] as $mod_idx => $module) {
            foreach ($module['lessons'] as $less_idx => $lesson) {
                $key = "{$mod_idx}:{$less_idx}";
                if (!isset($completed_set[$key])) {
                    return [
                        'module_index' => $mod_idx,
                        'lesson_index' => $less_idx,
                        'title' => $lesson['title'],
                        'module_title' => $module['title']
                    ];
                }
            }
        }
        
        return null; // All lessons complete
    }
    
    /**
     * Reset progress (for retaking course)
     *
     * @return void
     */
    public function reset_progress(): void {
        $this->db->delete_records('local_aicourse_progress', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id
        ]);
    }
    
    /**
     * Calculate average time per lesson
     *
     * @return float Average seconds per lesson
     */
    public function get_average_time_per_lesson(): float {
        $records = $this->db->get_records('local_aicourse_progress', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'completed' => 1
        ]);
        
        if (empty($records)) {
            return 0;
        }
        
        $total_time = 0;
        $count = 0;
        
        foreach ($records as $record) {
            if ($record->timespent > 0) {
                $total_time += $record->timespent;
                $count++;
            }
        }
        
        return $count > 0 ? $total_time / $count : 0;
    }
}
