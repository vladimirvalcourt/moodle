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
 * Background task for async course generation
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task to generate course in background
 */
class generate_course_task extends \core\task\adhoc_task {
    
    /**
     * Execute the task
     */
    public function execute() {
        global $DB;
        
        $data = $this->get_custom_data();
        $user_id = $data->user_id;
        $params = $data->params;
        $provider = $data->provider ?? null;
        
        mtrace("Starting course generation for user {$user_id}");
        
        try {
            // Create generator
            $generator = new \local_aicourse\generator\course_generator($user_id, $params, $provider);
            
            // Generate course
            $draft_id = $generator->generate();
            
            mtrace("Course generation complete. Draft ID: {$draft_id}");
            
            // TODO: Send notification to user
            
        } catch (\Exception $e) {
            mtrace("Course generation failed: " . $e->getMessage());
            throw $e;
        }
    }
}
