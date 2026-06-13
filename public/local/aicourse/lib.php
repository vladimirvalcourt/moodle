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
 * Core functions for AI Course Generator plugin
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get AI provider instance based on configuration
 *
 * @param string|null $provider Provider name (openai, claude)
 * @return \local_aicourse\api\ai_provider
 * @throws moodle_exception
 */
function local_aicourse_get_provider(?string $provider = null): \local_aicourse\api\ai_provider {
    if (!$provider) {
        $provider = get_config('local_aicourse', 'default_provider') ?: 'openai';
    }
    
    switch ($provider) {
        case 'openai':
            $api_key = get_config('local_aicourse', 'openai_api_key');
            $model = get_config('local_aicourse', 'default_model') ?: 'gpt-4-turbo';
            return new \local_aicourse\api\openai_client($api_key, $model);
            
        case 'claude':
            // TODO: Implement Claude client
            throw new moodle_exception('claude_not_implemented');
            
        default:
            throw new moodle_exception('invalid_ai_provider');
    }
}

/**
 * Create a new course draft
 *
 * @param int $user_id User creating the draft
 * @param array $params Course parameters
 * @return int Draft ID
 */
function local_aicourse_create_draft(int $user_id, array $params): int {
    global $DB;
    
    $draft = new stdClass();
    $draft->user_id = $user_id;
    $draft->course_title = $params['topic'] ?? '';
    $draft->topic_description = $params['description'] ?? '';
    $draft->target_audience = $params['audience'] ?? 'beginner';
    $draft->estimated_duration = $params['duration'] ?? 480;
    $draft->learning_objectives = isset($params['objectives']) ? implode("\n", $params['objectives']) : '';
    $draft->status = 'draft';
    $draft->timecreated = time();
    $draft->timemodified = time();
    
    return $DB->insert_record('local_aicourse_drafts', $draft);
}

/**
 * Update draft with generated content
 *
 * @param int $draft_id Draft ID
 * @param array $outline Generated outline
 * @param array $content Generated content
 * @param string $provider AI provider used
 * @param array $metadata Generation metadata
 */
function local_aicourse_update_draft_content(
    int $draft_id,
    array $outline,
    array $content,
    string $provider,
    array $metadata
): void {
    global $DB;
    
    $draft = new stdClass();
    $draft->id = $draft_id;
    $draft->generated_outline = json_encode($outline);
    $draft->generated_content = json_encode($content);
    $draft->ai_provider = $provider;
    $draft->model_version = $metadata['model'] ?? '';
    $draft->generation_metadata = json_encode($metadata);
    $draft->status = 'review';
    $draft->timemodified = time();
    
    $DB->update_record('local_aicourse_drafts', $draft);
}

/**
 * Get draft by ID
 *
 * @param int $draft_id Draft ID
 * @param int $user_id User ID (for permission check)
 * @return stdClass|false Draft object or false
 */
function local_aicourse_get_draft(int $draft_id, int $user_id) {
    global $DB;
    
    $draft = $DB->get_record('local_aicourse_drafts', ['id' => $draft_id]);
    
    if (!$draft) {
        return false;
    }
    
    // Check ownership or permission
    if ($draft->user_id != $user_id) {
        $context = context_system::instance();
        if (!has_capability('local/aicourse:review', $context)) {
            return false;
        }
    }
    
    // Decode JSON fields
    if ($draft->generated_outline) {
        $draft->generated_outline = json_decode($draft->generated_outline, true);
    }
    if ($draft->generated_content) {
        $draft->generated_content = json_decode($draft->generated_content, true);
    }
    if ($draft->generation_metadata) {
        $draft->generation_metadata = json_decode($draft->generation_metadata, true);
    }
    
    return $draft;
}

/**
 * Get all drafts for a user
 *
 * @param int $user_id User ID
 * @param string $status Filter by status (optional)
 * @return array Array of draft objects
 */
function local_aicourse_get_user_drafts(int $user_id, ?string $status = null): array {
    global $DB;
    
    $params = ['user_id' => $user_id];
    $sql = "SELECT * FROM {local_aicourse_drafts} WHERE user_id = :user_id";
    
    if ($status) {
        $sql .= " AND status = :status";
        $params['status'] = $status;
    }
    
    $sql .= " ORDER BY timecreated DESC";
    
    $drafts = $DB->get_records_sql($sql, $params);
    
    // Decode JSON fields
    foreach ($drafts as $draft) {
        if ($draft->generated_outline) {
            $draft->generated_outline = json_decode($draft->generated_outline, true);
        }
        if ($draft->generation_metadata) {
            $draft->generation_metadata = json_decode($draft->generation_metadata, true);
        }
    }
    
    return $drafts;
}

/**
 * Publish draft as actual Moodle course
 *
 * @param int $draft_id Draft ID
 * @param int $user_id User ID
 * @return int|false Course ID or false on failure
 */
function local_aicourse_publish_draft(int $draft_id, int $user_id) {
    global $DB, $CFG;
    
    require_once($CFG->dirroot . '/course/lib.php');
    
    $draft = local_aicourse_get_draft($draft_id, $user_id);
    if (!$draft) {
        return false;
    }
    
    // Create new course
    $course = new stdClass();
    $course->fullname = $draft->course_title;
    $course->shortname = substr(preg_replace('/[^a-zA-Z0-9]/', '', $draft->course_title), 0, 50);
    $course->summary = $draft->topic_description;
    $course->format = 'topics';
    $course->numsections = count($draft->generated_outline['modules'] ?? []);
    $course->startdate = time();
    
    $course_id = create_course($course);
    
    if (!$course_id) {
        return false;
    }
    
    // Update draft status
    $DB->set_field('local_aicourse_drafts', 'status', 'published', ['id' => $draft_id]);
    $DB->set_field('local_aicourse_drafts', 'published_course_id', $course_id, ['id' => $draft_id]);
    
    // Create quiz activity with AI-generated questions
    try {
        $quiz_integrator = new \local_aicourse\services\quiz_integrator($course_id, $draft_id);
        $quiz_id = $quiz_integrator->create_quiz();
        
        if ($quiz_id) {
            // Log successful quiz creation
            error_log("Quiz created for course {$course_id}: quiz_id={$quiz_id}");
        }
    } catch (\Exception $e) {
        // Log error but don't fail the publish operation
        error_log("Failed to create quiz for course {$course_id}: " . $e->getMessage());
    }
    
    return $course_id;
}

/**
 * Delete a draft
 *
 * @param int $draft_id Draft ID
 * @param int $user_id User ID
 * @return bool Success
 */
function local_aicourse_delete_draft(int $draft_id, int $user_id): bool {
    global $DB;
    
    $draft = $DB->get_record('local_aicourse_drafts', ['id' => $draft_id]);
    
    if (!$draft) {
        return false;
    }
    
    // Check ownership
    if ($draft->user_id != $user_id) {
        $context = context_system::instance();
        if (!has_capability('local/aicourse:manage', $context)) {
            return false;
        }
    }
    
    return $DB->delete_records('local_aicourse_drafts', ['id' => $draft_id]);
}

/**
 * Log generation history
 *
 * @param int $draft_id Draft ID
 * @param string $prompt Prompt sent to AI
 * @param string $response Response from AI
 * @param float|null $quality_score Quality score
 * @param int $revision Revision number
 */
function local_aicourse_log_generation(
    int $draft_id,
    string $prompt,
    string $response,
    ?float $quality_score = null,
    int $revision = 1
): void {
    global $DB;
    
    $history = new stdClass();
    $history->draft_id = $draft_id;
    $history->prompt_input = $prompt;
    $history->prompt_output = $response;
    $history->quality_score = $quality_score;
    $history->revision_number = $revision;
    $history->timecreated = time();
    
    $DB->insert_record('local_aicourse_history', $history);
}

/**
 * Get generation statistics for a user
 *
 * @param int $user_id User ID
 * @return array Statistics
 */
function local_aicourse_get_user_stats(int $user_id): array {
    global $DB;
    
    $today_start = strtotime('today midnight');
    
    // Total drafts
    $total_drafts = $DB->count_records('local_aicourse_drafts', ['user_id' => $user_id]);
    
    // Today's generations
    $today_generations = $DB->count_records_select(
        'local_aicourse_drafts',
        'user_id = :user_id AND timecreated >= :today',
        ['user_id' => $user_id, 'today' => $today_start]
    );
    
    // Published courses
    $published = $DB->count_records('local_aicourse_drafts', [
        'user_id' => $user_id,
        'status' => 'published'
    ]);
    
    return [
        'total_drafts' => $total_drafts,
        'today_generations' => $today_generations,
        'published_courses' => $published,
    ];
}

/**
 * Get courses enrolled by a user (AI-generated courses)
 *
 * @param int $user_id User ID
 * @return array Array of course objects
 */
function local_aicourse_get_user_enrolled_courses(int $user_id): array {
    global $DB;
    
    // Get all published AI courses
    $drafts = $DB->get_records('local_aicourse_drafts', [
        'status' => 'published'
    ], 'timecreated DESC');
    
    $enrolled_courses = [];
    
    foreach ($drafts as $draft) {
        if (!$draft->published_course_id) {
            continue;
        }
        
        // Check if user is enrolled in this course
        $context = context_course::instance($draft->published_course_id);
        if (is_enrolled($context, $user_id)) {
            $course = $DB->get_record('course', ['id' => $draft->published_course_id]);
            if ($course) {
                $enrolled_courses[] = $course;
            }
        }
    }
    
    return $enrolled_courses;
}
