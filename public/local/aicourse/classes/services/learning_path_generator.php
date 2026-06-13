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
 * Learning path generator - Creates personalized learning paths based on user performance
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
 * Generate adaptive learning paths for students
 */
class learning_path_generator {
    
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
     * Generate personalized learning path
     *
     * @return array Learning path with recommended module order
     */
    public function generate_path(): array {
        // Get course content
        $draft = $this->db->get_record('local_aicourse_drafts', ['published_course_id' => $this->course_id]);
        if (!$draft) {
            throw new \moodle_exception('course_not_found');
        }
        
        $content = json_decode($draft->generated_content, true);
        $modules = $content['modules'] ?? [];
        
        // Get user's current progress
        $progress_tracker = new progress_tracker($this->user_id, $this->course_id);
        $completed = $progress_tracker->get_completed_lessons();
        $stats = $progress_tracker->get_progress_stats();
        
        // Get quiz performance if available
        $quiz_scores = $this->get_quiz_performance();
        
        // Determine proficiency level
        $proficiency = $this->assess_proficiency($stats, $quiz_scores);
        
        // Generate personalized module order
        $recommended_order = $this->calculate_optimal_order($modules, $proficiency, $completed);
        
        // Save learning path
        $this->save_learning_path($recommended_order, $proficiency);
        
        return [
            'recommended_order' => $recommended_order,
            'proficiency_level' => $proficiency,
            'personalized_message' => $this->generate_personalized_message($proficiency),
            'estimated_time' => $this->estimate_completion_time($recommended_order, $modules),
            'next_recommended' => $this->get_next_recommendation($recommended_order, $completed)
        ];
    }
    
    /**
     * Assess user's proficiency level based on performance
     *
     * @param array $stats Progress statistics
     * @param array $quiz_scores Quiz performance data
     * @return string Proficiency level
     */
    private function assess_proficiency(array $stats, array $quiz_scores): string {
        if (empty($quiz_scores)) {
            // No quiz data yet, assume novice
            return 'novice';
        }
        
        $average_score = array_sum($quiz_scores) / count($quiz_scores);
        
        if ($average_score >= 90) {
            return 'expert';
        } elseif ($average_score >= 75) {
            return 'proficient';
        } elseif ($average_score >= 60) {
            return 'developing';
        } else {
            return 'novice';
        }
    }
    
    /**
     * Calculate optimal module order based on proficiency
     *
     * @param array $modules All course modules
     * @param string $proficiency User's proficiency level
     * @param array $completed Completed lessons
     * @return array Recommended module indices in order
     */
    private function calculate_optimal_order(array $modules, string $proficiency, array $completed): array {
        $module_count = count($modules);
        $order = range(0, $module_count - 1); // Default: sequential
        
        switch ($proficiency) {
            case 'expert':
                // Experts can skip basics, focus on advanced topics
                // Assume later modules are more advanced
                $order = array_reverse($order);
                break;
                
            case 'proficient':
                // Proficient learners follow standard order but can move faster
                // Keep default sequential order
                break;
                
            case 'developing':
                // Developing learners need more practice on fundamentals
                // Prioritize early modules, repeat if needed
                $fundamentals = array_slice($order, 0, ceil($module_count / 2));
                $advanced = array_slice($order, ceil($module_count / 2));
                $order = array_merge($fundamentals, $advanced);
                break;
                
            case 'novice':
            default:
                // Novices need structured, sequential learning with repetition
                // Add review modules
                $review_modules = array_slice($order, 0, floor($module_count / 2));
                $order = array_merge($order, $review_modules);
                break;
        }
        
        // Remove already completed modules from recommendations
        $completed_modules = $this->get_completed_modules($completed);
        $order = array_filter($order, function($idx) use ($completed_modules) {
            return !in_array($idx, $completed_modules);
        });
        
        return array_values($order); // Re-index
    }
    
    /**
     * Get quiz performance data
     *
     * @return array Quiz scores by module
     */
    private function get_quiz_performance(): array {
        // Query Moodle gradebook for quiz scores
        // This is a simplified implementation
        $sql = "SELECT g.finalgrade, q.name
                FROM {grade_items} gi
                JOIN {grade_grades} g ON g.itemid = gi.id
                JOIN {quiz} q ON q.id = gi.instance
                WHERE gi.courseid = :courseid
                AND gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND g.userid = :userid
                AND g.finalgrade IS NOT NULL";
        
        $grades = $this->db->get_records_sql($sql, [
            'courseid' => $this->course_id,
            'userid' => $this->user_id
        ]);
        
        $scores = [];
        foreach ($grades as $grade) {
            $scores[] = $grade->finalgrade;
        }
        
        return $scores;
    }
    
    /**
     * Get list of completed module indices
     *
     * @param array $completed Completed lesson records
     * @return array Module indices
     */
    private function get_completed_modules(array $completed): array {
        $modules = [];
        foreach ($completed as $lesson) {
            if (!in_array($lesson->module_index, $modules)) {
                $modules[] = $lesson->module_index;
            }
        }
        return $modules;
    }
    
    /**
     * Generate personalized message based on proficiency
     *
     * @param string $proficiency
     * @return string Motivational message
     */
    private function generate_personalized_message(string $proficiency): string {
        $messages = [
            'novice' => get_string('path_novice', 'local_aicourse'),
            'developing' => get_string('path_developing', 'local_aicourse'),
            'proficient' => get_string('path_proficient', 'local_aicourse'),
            'expert' => get_string('path_expert', 'local_aicourse')
        ];
        
        return $messages[$proficiency] ?? $messages['novice'];
    }
    
    /**
     * Estimate completion time for remaining modules
     *
     * @param array $recommended_order Module indices
     * @param array $modules All modules
     * @return int Estimated minutes
     */
    private function estimate_completion_time(array $recommended_order, array $modules): int {
        $total_minutes = 0;
        
        foreach ($recommended_order as $idx) {
            if (isset($modules[$idx])) {
                // Sum estimated time for all lessons in module
                foreach ($modules[$idx]['lessons'] as $lesson) {
                    $total_minutes += $lesson['estimated_time'] ?? 10;
                }
            }
        }
        
        return $total_minutes;
    }
    
    /**
     * Get next recommended action
     *
     * @param array $recommended_order
     * @param array $completed
     * @return array|null Next recommendation
     */
    private function get_next_recommendation(array $recommended_order, array $completed): ?array {
        if (empty($recommended_order)) {
            return null;
        }
        
        // Get first uncompleted module from recommended order
        $completed_modules = $this->get_completed_modules($completed);
        
        foreach ($recommended_order as $module_idx) {
            if (!in_array($module_idx, $completed_modules)) {
                return [
                    'module_index' => $module_idx,
                    'action' => 'start_module',
                    'priority' => 'high'
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Save learning path to database
     *
     * @param array $recommended_order
     * @param string $proficiency
     * @return void
     */
    private function save_learning_path(array $recommended_order, string $proficiency): void {
        $existing = $this->db->get_record('local_aicourse_paths', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id
        ]);
        
        $record = new \stdClass();
        $record->user_id = $this->user_id;
        $record->course_id = $this->course_id;
        $record->recommended_modules = json_encode($recommended_order);
        $record->proficiency_level = $proficiency;
        $record->current_position = 0;
        $record->last_accessed = time();
        $record->timemodified = time();
        
        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $this->db->update_record('local_aicourse_paths', $record);
        } else {
            $record->timecreated = time();
            $this->db->insert_record('local_aicourse_paths', $record);
        }
    }
    
    /**
     * Get existing learning path
     *
     * @return array|null Learning path data
     */
    public function get_existing_path(): ?array {
        $path = $this->db->get_record('local_aicourse_paths', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id
        ]);
        
        if (!$path) {
            return null;
        }
        
        return [
            'recommended_modules' => json_decode($path->recommended_modules, true),
            'proficiency_level' => $path->proficiency_level,
            'current_position' => $path->current_position,
            'last_accessed' => $path->last_accessed
        ];
    }
    
    /**
     * Update learning path position
     *
     * @param int $position Current position in recommended order
     * @return void
     */
    public function update_position(int $position): void {
        $path = $this->db->get_record('local_aicourse_paths', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id
        ]);
        
        if ($path) {
            $path->current_position = $position;
            $path->last_accessed = time();
            $path->timemodified = time();
            $this->db->update_record('local_aicourse_paths', $path);
        }
    }
}
