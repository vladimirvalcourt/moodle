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
 * Rate Limiter Service - Controls API usage
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Rate limiting for AI generations
 */
class rate_limiter {
    
    /**
     * Check if user can generate a course
     *
     * @param int $user_id User ID
     * @return bool True if allowed
     */
    public function can_generate(int $user_id): bool {
        $limit = intval(get_config('local_aicourse', 'daily_generation_limit') ?: 10);
        $usage = $this->get_today_usage($user_id);
        
        return $usage < $limit;
    }
    
    /**
     * Get remaining generations for today
     *
     * @param int $user_id User ID
     * @return int Remaining count
     */
    public function get_remaining(int $user_id): int {
        $limit = intval(get_config('local_aicourse', 'daily_generation_limit') ?: 10);
        $usage = $this->get_today_usage($user_id);
        
        return max(0, $limit - $usage);
    }
    
    /**
     * Get today's usage count
     *
     * @param int $user_id User ID
     * @return int Usage count
     */
    private function get_today_usage(int $user_id): int {
        global $DB;
        
        $today_start = strtotime('today midnight');
        
        return $DB->count_records_select(
            'local_aicourse_drafts',
            'user_id = :user_id AND timecreated >= :today',
            ['user_id' => $user_id, 'today' => $today_start]
        );
    }
}
