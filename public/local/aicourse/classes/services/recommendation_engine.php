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
 * Course recommendation engine - Suggests courses based on user profile and history
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
 * Recommend personalized courses to learners
 */
class recommendation_engine {
    
    /** @var int User ID */
    private $user_id;
    
    /** @var \moodle_database Database instance */
    private $db;
    
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
     * Get personalized course recommendations
     *
     * @param int $limit Maximum number of recommendations
     * @return array Recommended courses with scores
     */
    public function get_recommendations(int $limit = 5): array {
        // Get all published AI courses
        $published_courses = $this->get_published_courses();
        
        if (empty($published_courses)) {
            return [];
        }
        
        // Calculate recommendation score for each course
        $scored_courses = [];
        foreach ($published_courses as $course) {
            $score = $this->calculate_relevance_score($course);
            
            if ($score > 0) {
                $scored_courses[] = [
                    'course' => $course,
                    'score' => $score,
                    'reason' => $this->generate_recommendation_reason($course, $score)
                ];
            }
        }
        
        // Sort by score (descending)
        usort($scored_courses, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return top N recommendations
        return array_slice($scored_courses, 0, $limit);
    }
    
    /**
     * Get all published AI-generated courses
     *
     * @return array Published courses
     */
    private function get_published_courses(): array {
        $drafts = $this->db->get_records('local_aicourse_drafts', [
            'status' => 'published'
        ], 'timecreated DESC');
        
        $courses = [];
        foreach ($drafts as $draft) {
            if ($draft->published_course_id) {
                $course = $this->db->get_record('course', ['id' => $draft->published_course_id]);
                if ($course) {
                    $course->draft_data = $draft;
                    $courses[] = $course;
                }
            }
        }
        
        return $courses;
    }
    
    /**
     * Calculate relevance score for a course
     *
     * @param \stdClass $course
     * @return float Score 0-100
     */
    private function calculate_relevance_score(\stdClass $course): float {
        $score = 0;
        
        // Factor 1: Topic similarity to completed courses (40%)
        $topic_score = $this->calculate_topic_similarity($course);
        $score += $topic_score * 0.4;
        
        // Factor 2: Difficulty progression (30%)
        $difficulty_score = $this->calculate_difficulty_fit($course);
        $score += $difficulty_score * 0.3;
        
        // Factor 3: Popularity/enrollment count (20%)
        $popularity_score = $this->calculate_popularity($course);
        $score += $popularity_score * 0.2;
        
        // Factor 4: Recency (10%)
        $recency_score = $this->calculate_recency($course);
        $score += $recency_score * 0.1;
        
        return min(100, max(0, $score));
    }
    
    /**
     * Calculate topic similarity score
     *
     * @param \stdClass $course
     * @return float Score 0-100
     */
    private function calculate_topic_similarity(\stdClass $course): float {
        // Get user's completed courses
        $completed = $this->get_user_completed_courses();
        
        if (empty($completed)) {
            return 50; // Neutral score for new users
        }
        
        // Extract keywords from current course
        $current_keywords = $this->extract_keywords($course->fullname . ' ' . $course->summary);
        
        // Compare with completed courses
        $max_similarity = 0;
        foreach ($completed as $completed_course) {
            $completed_keywords = $this->extract_keywords(
                $completed_course->fullname . ' ' . $completed_course->summary
            );
            
            $similarity = $this->calculate_keyword_overlap($current_keywords, $completed_keywords);
            $max_similarity = max($max_similarity, $similarity);
        }
        
        return $max_similarity * 100;
    }
    
    /**
     * Calculate difficulty fit score
     *
     * @param \stdClass $course
     * @return float Score 0-100
     */
    private function calculate_difficulty_fit(\stdClass $course): float {
        // Get user's proficiency from learning paths
        $paths = $this->db->get_records('local_aicourse_paths', ['user_id' => $this->user_id]);
        
        if (empty($paths)) {
            return 70; // Assume intermediate for new users
        }
        
        // Calculate average proficiency
        $proficiency_map = [
            'novice' => 25,
            'developing' => 50,
            'proficient' => 75,
            'expert' => 100
        ];
        
        $total_proficiency = 0;
        $count = 0;
        foreach ($paths as $path) {
            if (isset($proficiency_map[$path->proficiency_level])) {
                $total_proficiency += $proficiency_map[$path->proficiency_level];
                $count++;
            }
        }
        
        $avg_proficiency = $count > 0 ? $total_proficiency / $count : 50;
        
        // Get course target audience
        $draft = $course->draft_data ?? null;
        if (!$draft || !$draft->target_audience) {
            return 50; // Unknown difficulty
        }
        
        $audience_map = [
            'beginner' => 25,
            'intermediate' => 50,
            'advanced' => 75,
            'mixed' => 50
        ];
        
        $course_difficulty = $audience_map[$draft->target_audience] ?? 50;
        
        // Score based on how well difficulty matches user level
        // Allow some stretch (slightly harder courses are good)
        $difference = abs($avg_proficiency - $course_difficulty);
        
        if ($difference <= 10) {
            return 100; // Perfect match
        } elseif ($difference <= 25) {
            return 80; // Good match
        } elseif ($difference <= 40) {
            return 60; // Acceptable stretch
        } else {
            return 30; // Too different
        }
    }
    
    /**
     * Calculate popularity score
     *
     * @param \stdClass $course
     * @return float Score 0-100
     */
    private function calculate_popularity(\stdClass $course): float {
        // Count enrollments
        $context = \context_course::instance($course->id);
        $enrollment_count = count_enrolled_users($context);
        
        // Normalize (assume 100 enrollments is very popular)
        return min(100, ($enrollment_count / 100) * 100);
    }
    
    /**
     * Calculate recency score
     *
     * @param \stdClass $course
     * @return float Score 0-100
     */
    private function calculate_recency(\stdClass $course): float {
        $age_days = (time() - $course->timecreated) / 86400;
        
        // Newer courses get higher scores
        if ($age_days <= 7) {
            return 100; // Less than a week
        } elseif ($age_days <= 30) {
            return 80; // Less than a month
        } elseif ($age_days <= 90) {
            return 60; // Less than 3 months
        } elseif ($age_days <= 180) {
            return 40; // Less than 6 months
        } else {
            return 20; // Older than 6 months
        }
    }
    
    /**
     * Get user's completed courses
     *
     * @return array Completed course objects
     */
    private function get_user_completed_courses(): array {
        $certificates = $this->db->get_records('local_aicourse_certificates', [
            'user_id' => $this->user_id
        ]);
        
        $courses = [];
        foreach ($certificates as $cert) {
            $course = $this->db->get_record('course', ['id' => $cert->course_id]);
            if ($course) {
                $courses[] = $course;
            }
        }
        
        return $courses;
    }
    
    /**
     * Extract keywords from text
     *
     * @param string $text
     * @return array Keywords
     */
    private function extract_keywords(string $text): array {
        // Simple keyword extraction (can be enhanced with NLP)
        $text = strtolower($text);
        
        // Remove common words
        $stop_words = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        
        // Extract words
        preg_match_all('/\b[a-z]{4,}\b/', $text, $matches);
        $words = $matches[0] ?? [];
        
        // Filter stop words
        $keywords = array_filter($words, function($word) use ($stop_words) {
            return !in_array($word, $stop_words);
        });
        
        return array_unique(array_values($keywords));
    }
    
    /**
     * Calculate keyword overlap between two sets
     *
     * @param array $keywords1
     * @param array $keywords2
     * @return float Overlap ratio 0-1
     */
    private function calculate_keyword_overlap(array $keywords1, array $keywords2): float {
        if (empty($keywords1) || empty($keywords2)) {
            return 0;
        }
        
        $intersection = array_intersect($keywords1, $keywords2);
        $union = array_unique(array_merge($keywords1, $keywords2));
        
        return count($intersection) / count($union);
    }
    
    /**
     * Generate human-readable recommendation reason
     *
     * @param \stdClass $course
     * @param float $score
     * @return string Reason text
     */
    private function generate_recommendation_reason(\stdClass $course, float $score): string {
        if ($score >= 80) {
            return get_string('recommend_high', 'local_aicourse');
        } elseif ($score >= 60) {
            return get_string('recommend_medium', 'local_aicourse');
        } else {
            return get_string('recommend_low', 'local_aicourse');
        }
    }
    
    /**
     * Get trending courses (most enrolled recently)
     *
     * @param int $limit
     * @return array Trending courses
     */
    public function get_trending_courses(int $limit = 5): array {
        $sql = "SELECT c.*, COUNT(e.id) as enrollment_count
                FROM {course} c
                JOIN {local_aicourse_drafts} d ON d.published_course_id = c.id
                LEFT JOIN {enrol} e ON e.courseid = c.id
                WHERE d.status = 'published'
                GROUP BY c.id
                ORDER BY enrollment_count DESC
                LIMIT :limit";
        
        return $this->db->get_records_sql($sql, ['limit' => $limit]);
    }
    
    /**
     * Get similar courses to a given course
     *
     * @param int $course_id Reference course
     * @param int $limit
     * @return array Similar courses
     */
    public function get_similar_courses(int $course_id, int $limit = 3): array {
        $reference = $this->db->get_record('course', ['id' => $course_id]);
        if (!$reference) {
            return [];
        }
        
        $published = $this->get_published_courses();
        $similar = [];
        
        $ref_keywords = $this->extract_keywords($reference->fullname . ' ' . $reference->summary);
        
        foreach ($published as $course) {
            if ($course->id == $course_id) {
                continue; // Skip self
            }
            
            $course_keywords = $this->extract_keywords($course->fullname . ' ' . $course->summary);
            $similarity = $this->calculate_keyword_overlap($ref_keywords, $course_keywords);
            
            if ($similarity > 0.3) { // At least 30% similar
                $similar[] = [
                    'course' => $course,
                    'similarity' => $similarity
                ];
            }
        }
        
        // Sort by similarity
        usort($similar, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($similar, 0, $limit);
    }
}
