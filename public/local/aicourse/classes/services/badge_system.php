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
 * Badge and achievement system - Gamification engine
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
 * Manage badges, achievements, and gamification
 */
class badge_system {
    
    /** @var int User ID */
    private $user_id;
    
    /** @var \moodle_database Database instance */
    private $db;
    
    // Badge definitions
    const BADGES = [
        'first_course' => [
            'name' => 'First Steps',
            'description' => 'Complete your first course',
            'icon' => '🎯',
            'criteria' => 'complete_first_course',
            'points' => 100
        ],
        'speed_learner' => [
            'name' => 'Speed Learner',
            'description' => 'Complete a course in under 24 hours',
            'icon' => '⚡',
            'criteria' => 'fast_completion',
            'points' => 150
        ],
        'perfect_score' => [
            'name' => 'Perfectionist',
            'description' => 'Get 100% on a quiz',
            'icon' => '💯',
            'criteria' => 'perfect_quiz',
            'points' => 200
        ],
        'course_master' => [
            'name' => 'Course Master',
            'description' => 'Complete 5 courses',
            'icon' => '🏆',
            'criteria' => 'complete_5_courses',
            'points' => 500
        ],
        'streak_7' => [
            'name' => 'Week Warrior',
            'description' => '7-day learning streak',
            'icon' => '🔥',
            'criteria' => 'streak_7_days',
            'points' => 300
        ],
        'streak_30' => [
            'name' => 'Monthly Champion',
            'description' => '30-day learning streak',
            'icon' => '👑',
            'criteria' => 'streak_30_days',
            'points' => 1000
        ],
        'early_adopter' => [
            'name' => 'Early Adopter',
            'description' => 'Enroll in 10+ courses',
            'icon' => '🌟',
            'criteria' => 'enroll_10_courses',
            'points' => 250
        ],
        'quiz_champion' => [
            'name' => 'Quiz Champion',
            'description' => 'Complete 20 quizzes with 80%+ average',
            'icon' => '🎓',
            'criteria' => 'quiz_excellence',
            'points' => 750
        ],
        'knowledge_seeker' => [
            'name' => 'Knowledge Seeker',
            'description' => 'Complete courses in 3 different topics',
            'icon' => '📚',
            'criteria' => 'diverse_learning',
            'points' => 400
        ],
        'night_owl' => [
            'name' => 'Night Owl',
            'description' => 'Complete a lesson after midnight',
            'icon' => '🦉',
            'criteria' => 'late_night_learning',
            'points' => 50
        ]
    ];
    
    /**
     * Constructor
     *
     * @param int $user_id
     */
    public function __construct(int $user_id) {
        global $DB;
        
        $this->user_id = $user_id;
        $this->db = $DB;
    }
    
    /**
     * Check and award badges based on user activity
     *
     * @param string $event Event type that triggered check
     * @param array $data Additional event data
     * @return array Newly awarded badges
     */
    public function check_and_award(string $event, array $data = []): array {
        $awarded_badges = [];
        
        switch ($event) {
            case 'course_completed':
                $awarded_badges = array_merge(
                    $awarded_badges,
                    $this->check_course_completion_badges($data)
                );
                break;
                
            case 'quiz_completed':
                $awarded_badges = array_merge(
                    $awarded_badges,
                    $this->check_quiz_badges($data)
                );
                break;
                
            case 'lesson_viewed':
                $awarded_badges = array_merge(
                    $awarded_badges,
                    $this->check_learning_habit_badges($data)
                );
                break;
                
            case 'daily_check':
                $awarded_badges = array_merge(
                    $awarded_badges,
                    $this->check_streak_badges()
                );
                break;
                
            case 'enrollment':
                $awarded_badges = array_merge(
                    $awarded_badges,
                    $this->check_enrollment_badges()
                );
                break;
        }
        
        return $awarded_badges;
    }
    
    /**
     * Check course completion badges
     *
     * @param array $data Course completion data
     * @return array Awarded badges
     */
    private function check_course_completion_badges(array $data): array {
        $awarded = [];
        $course_id = $data['course_id'] ?? null;
        
        if (!$course_id) {
            return $awarded;
        }
        
        // First course completed
        if (!$this->has_badge('first_course')) {
            $completed_count = $this->get_completed_course_count();
            if ($completed_count == 1) {
                $awarded[] = $this->award_badge('first_course');
            }
        }
        
        // 5 courses completed
        if (!$this->has_badge('course_master')) {
            $completed_count = $this->get_completed_course_count();
            if ($completed_count >= 5) {
                $awarded[] = $this->award_badge('course_master');
            }
        }
        
        // Speed learner (completed in < 24 hours)
        if (!$this->has_badge('speed_learner') && isset($data['completion_time'])) {
            $hours = $data['completion_time'] / 3600;
            if ($hours < 24) {
                $awarded[] = $this->award_badge('speed_learner');
            }
        }
        
        // Diverse learning (3+ different topics)
        if (!$this->has_badge('knowledge_seeker')) {
            $topic_count = $this->get_unique_topic_count();
            if ($topic_count >= 3) {
                $awarded[] = $this->award_badge('knowledge_seeker');
            }
        }
        
        return $awarded;
    }
    
    /**
     * Check quiz-related badges
     *
     * @param array $data Quiz completion data
     * @return array Awarded badges
     */
    private function check_quiz_badges(array $data): array {
        $awarded = [];
        $score = $data['score'] ?? 0;
        
        // Perfect score
        if (!$this->has_badge('perfect_score') && $score == 100) {
            $awarded[] = $this->award_badge('perfect_score');
        }
        
        // Quiz champion (20 quizzes with 80%+ average)
        if (!$this->has_badge('quiz_champion')) {
            $quiz_stats = $this->get_quiz_statistics();
            if ($quiz_stats['count'] >= 20 && $quiz_stats['average'] >= 80) {
                $awarded[] = $this->award_badge('quiz_champion');
            }
        }
        
        return $awarded;
    }
    
    /**
     * Check learning habit badges
     *
     * @param array $data Lesson view data
     * @return array Awarded badges
     */
    private function check_learning_habit_badges(array $data): array {
        $awarded = [];
        
        // Night owl (lesson after midnight)
        if (!$this->has_badge('night_owl')) {
            $hour = date('H');
            if ($hour >= 0 && $hour < 5) {
                $awarded[] = $this->award_badge('night_owl');
            }
        }
        
        return $awarded;
    }
    
    /**
     * Check streak badges
     *
     * @return array Awarded badges
     */
    private function check_streak_badges(): array {
        $awarded = [];
        $streak = $this->calculate_current_streak();
        
        // 7-day streak
        if (!$this->has_badge('streak_7') && $streak >= 7) {
            $awarded[] = $this->award_badge('streak_7');
        }
        
        // 30-day streak
        if (!$this->has_badge('streak_30') && $streak >= 30) {
            $awarded[] = $this->award_badge('streak_30');
        }
        
        return $awarded;
    }
    
    /**
     * Check enrollment badges
     *
     * @return array Awarded badges
     */
    private function check_enrollment_badges(): array {
        $awarded = [];
        
        // Early adopter (10+ enrollments)
        if (!$this->has_badge('early_adopter')) {
            $enrollment_count = $this->get_enrollment_count();
            if ($enrollment_count >= 10) {
                $awarded[] = $this->award_badge('early_adopter');
            }
        }
        
        return $awarded;
    }
    
    /**
     * Award a badge to user
     *
     * @param string $badge_key Badge identifier
     * @return array Badge data
     */
    private function award_badge(string $badge_key): array {
        if ($this->has_badge($badge_key)) {
            return [];
        }
        
        $badge_def = self::BADGES[$badge_key] ?? null;
        if (!$badge_def) {
            return [];
        }
        
        // Insert badge record
        $record = new \stdClass();
        $record->user_id = $this->user_id;
        $record->badge_key = $badge_key;
        $record->badge_name = $badge_def['name'];
        $record->badge_description = $badge_def['description'];
        $record->badge_icon = $badge_def['icon'];
        $record->points = $badge_def['points'];
        $record->awarded_date = time();
        
        $this->db->insert_record('local_aicourse_badges', $record);
        
        // Update user total points
        $this->update_user_points($badge_def['points']);
        
        return [
            'key' => $badge_key,
            'name' => $badge_def['name'],
            'description' => $badge_def['description'],
            'icon' => $badge_def['icon'],
            'points' => $badge_def['points']
        ];
    }
    
    /**
     * Check if user has a specific badge
     *
     * @param string $badge_key
     * @return bool
     */
    private function has_badge(string $badge_key): bool {
        return $this->db->record_exists('local_aicourse_badges', [
            'user_id' => $this->user_id,
            'badge_key' => $badge_key
        ]);
    }
    
    /**
     * Get all user badges
     *
     * @return array User's badges
     */
    public function get_user_badges(): array {
        $badges = $this->db->get_records('local_aicourse_badges', [
            'user_id' => $this->user_id
        ], 'awarded_date DESC');
        
        $result = [];
        foreach ($badges as $badge) {
            $result[] = [
                'key' => $badge->badge_key,
                'name' => $badge->badge_name,
                'description' => $badge->badge_description,
                'icon' => $badge->badge_icon,
                'points' => $badge->points,
                'awarded_date' => $badge->awarded_date
            ];
        }
        
        return $result;
    }
    
    /**
     * Get user's total points
     *
     * @return int Total points
     */
    public function get_user_points(): int {
        $user = $this->db->get_record('local_aicourse_users', ['user_id' => $this->user_id]);
        return $user ? (int)$user->total_points : 0;
    }
    
    /**
     * Update user's total points
     *
     * @param int $points Points to add
     * @return void
     */
    private function update_user_points(int $points): void {
        $user = $this->db->get_record('local_aicourse_users', ['user_id' => $this->user_id]);
        
        if ($user) {
            $user->total_points += $points;
            $user->timemodified = time();
            $this->db->update_record('local_aicourse_users', $user);
        } else {
            $record = new \stdClass();
            $record->user_id = $this->user_id;
            $record->total_points = $points;
            $record->timecreated = time();
            $record->timemodified = time();
            $this->db->insert_record('local_aicourse_users', $record);
        }
    }
    
    /**
     * Calculate current learning streak
     *
     * @return int Current streak in days
     */
    private function calculate_current_streak(): int {
        // Get unique days with activity in last 90 days
        $sql = "SELECT DISTINCT DATE(FROM_UNIXTIME(timecreated)) as activity_date
                FROM {local_aicourse_progress}
                WHERE user_id = :userid
                AND timecreated > :cutoff
                ORDER BY activity_date DESC";
        
        $activities = $this->db->get_records_sql($sql, [
            'userid' => $this->user_id,
            'cutoff' => time() - (90 * 86400)
        ]);
        
        if (empty($activities)) {
            return 0;
        }
        
        // Calculate consecutive days
        $streak = 1;
        $dates = array_column($activities, 'activity_date');
        
        for ($i = 1; $i < count($dates); $i++) {
            $prev_date = strtotime($dates[$i - 1]);
            $curr_date = strtotime($dates[$i]);
            $diff = ($prev_date - $curr_date) / 86400;
            
            if ($diff == 1) {
                $streak++;
            } else {
                break;
            }
        }
        
        return $streak;
    }
    
    /**
     * Get completed course count
     *
     * @return int
     */
    private function get_completed_course_count(): int {
        return $this->db->count_records('local_aicourse_certificates', [
            'user_id' => $this->user_id
        ]);
    }
    
    /**
     * Get unique topic count from completed courses
     *
     * @return int
     */
    private function get_unique_topic_count(): int {
        $sql = "SELECT COUNT(DISTINCT d.topic_description) as topic_count
                FROM {local_aicourse_certificates} c
                JOIN {local_aicourse_drafts} d ON d.published_course_id = c.course_id
                WHERE c.user_id = :userid";
        
        $result = $this->db->get_record_sql($sql, ['userid' => $this->user_id]);
        return $result ? (int)$result->topic_count : 0;
    }
    
    /**
     * Get quiz statistics
     *
     * @return array Quiz stats
     */
    private function get_quiz_statistics(): array {
        $sql = "SELECT COUNT(*) as count, AVG(g.finalgrade) as average
                FROM {grade_items} gi
                JOIN {grade_grades} g ON g.itemid = gi.id
                WHERE gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND g.userid = :userid
                AND g.finalgrade IS NOT NULL";
        
        $stats = $this->db->get_record_sql($sql, ['userid' => $this->user_id]);
        
        return [
            'count' => $stats ? (int)$stats->count : 0,
            'average' => $stats ? (float)$stats->average : 0
        ];
    }
    
    /**
     * Get enrollment count
     *
     * @return int
     */
    private function get_enrollment_count(): int {
        $sql = "SELECT COUNT(DISTINCT e.courseid) as count
                FROM {enrol} e
                JOIN {user_enrolments} ue ON ue.enrolid = e.id
                WHERE ue.userid = :userid";
        
        $result = $this->db->get_record_sql($sql, ['userid' => $this->user_id]);
        return $result ? (int)$result->count : 0;
    }
    
    /**
     * Get leaderboard (top users by points)
     *
     * @param int $limit
     * @return array Top users
     */
    public function get_leaderboard(int $limit = 10): array {
        $sql = "SELECT u.id, u.firstname, u.lastname, au.total_points
                FROM {local_aicourse_users} au
                JOIN {user} u ON u.id = au.user_id
                ORDER BY au.total_points DESC
                LIMIT :limit";
        
        $users = $this->db->get_records_sql($sql, ['limit' => $limit]);
        
        $leaderboard = [];
        $rank = 1;
        foreach ($users as $user) {
            $leaderboard[] = [
                'rank' => $rank++,
                'name' => fullname($user),
                'points' => (int)$user->total_points
            ];
        }
        
        return $leaderboard;
    }
    
    /**
     * Get available badges (not yet earned)
     *
     * @return array Available badges
     */
    public function get_available_badges(): array {
        $earned_keys = array_column($this->get_user_badges(), 'key');
        $available = [];
        
        foreach (self::BADGES as $key => $badge) {
            if (!in_array($key, $earned_keys)) {
                $available[] = [
                    'key' => $key,
                    'name' => $badge['name'],
                    'description' => $badge['description'],
                    'icon' => $badge['icon'],
                    'points' => $badge['points']
                ];
            }
        }
        
        return $available;
    }
}
