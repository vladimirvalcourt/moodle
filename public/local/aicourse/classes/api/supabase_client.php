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
 * Supabase API client for local_aicourse plugin.
 *
 * Provides integration with Supabase backend for AI features,
 * analytics, and real-time capabilities.
 *
 * @package    local_aicourse
 * @copyright  2026 Cours+ Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Supabase client class.
 *
 * Handles all communication with Supabase backend.
 */
class supabase_client {
    
    /** @var string Supabase project URL */
    private $supabase_url;
    
    /** @var string Supabase anon key (for client-side operations) */
    private $anon_key;
    
    /** @var string Supabase service role key (for server-side operations) */
    private $service_key;
    
    /** @var array Default headers for API requests */
    private $headers;
    
    /**
     * Constructor.
     *
     * @throws \moodle_exception If Supabase configuration is missing
     */
    public function __construct() {
        // Load configuration from plugin settings
        $this->supabase_url = get_config('local_aicourse', 'supabase_url');
        $this->anon_key = get_config('local_aicourse', 'supabase_anon_key');
        $this->service_key = get_config('local_aicourse', 'supabase_service_key');
        
        // Validate configuration
        if (empty($this->supabase_url) || empty($this->anon_key)) {
            throw new \moodle_exception(
                'supabase_not_configured',
                'local_aicourse',
                '',
                'Supabase credentials not configured. Please set supabase_url and supabase_anon_key in plugin settings.'
            );
        }
        
        // Set up default headers
        $this->headers = [
            'apikey: ' . $this->anon_key,
            'Authorization: Bearer ' . $this->anon_key,
            'Content-Type: application/json',
        ];
    }
    
    /**
     * Record AI generation request.
     *
     * @param int $user_id Moodle user ID
     * @param string $course_title Course title
     * @param string $topic Topic description
     * @param string $difficulty Difficulty level
     * @param string $prompt AI prompt used
     * @return string Supabase record ID
     */
    public function record_generation_request(int $user_id, string $course_title, string $topic, string $difficulty, string $prompt): string {
        $data = [
            'user_id' => (string)$user_id,
            'course_title' => $course_title,
            'topic' => $topic,
            'difficulty' => $difficulty,
            'prompt' => $prompt,
            'status' => 'pending',
        ];
        
        $response = $this->insert('ai_generations', $data);
        
        if (!$response) {
            throw new \moodle_exception('supabase_insert_failed', 'local_aicourse');
        }
        
        return $response['id'];
    }
    
    /**
     * Update generation status.
     *
     * @param string $generation_id Supabase generation ID
     * @param string $status New status
     * @param array|null $response AI response data
     * @param string|null $error Error message if failed
     * @return bool Success
     */
    public function update_generation_status(string $generation_id, string $status, ?array $response = null, ?string $error = null): bool {
        $data = ['status' => $status];
        
        if ($status === 'completed') {
            $data['completed_at'] = date('c');
            if ($response) {
                $data['response'] = $response;
            }
        } elseif ($status === 'failed' && $error) {
            $data['error_message'] = $error;
        }
        
        return $this->update('ai_generations', $generation_id, $data);
    }
    
    /**
     * Store generated assessments.
     *
     * @param string $generation_id Supabase generation ID
     * @param array $assessments Array of assessment data
     * @return bool Success
     */
    public function store_generated_assessments(string $generation_id, array $assessments): bool {
        foreach ($assessments as $assessment) {
            $data = [
                'generation_id' => $generation_id,
                'question_text' => $assessment['question_text'],
                'question_type' => $assessment['question_type'],
                'options' => $assessment['options'] ?? null,
                'correct_answer' => $assessment['correct_answer'] ?? null,
                'explanation' => $assessment['explanation'] ?? null,
                'points' => $assessment['points'] ?? 1,
                'tags' => $assessment['tags'] ?? [],
            ];
            
            $success = $this->insert('generated_assessments', $data);
            if (!$success) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get user's generation history.
     *
     * @param int $user_id Moodle user ID
     * @param int $limit Maximum records to return
     * @return array Generation records
     */
    public function get_user_generations(int $user_id, int $limit = 20): array {
        $url = "{$this->supabase_url}/rest/v1/ai_generations?" . http_build_query([
            'select' => '*',
            'order' => 'created_at.desc',
            'limit' => $limit,
            'user_id' => 'eq.' . $user_id,
        ]);
        
        $response = $this->make_request('GET', $url);
        return $response ?: [];
    }
    
    /**
     * Track user progress.
     *
     * @param int $user_id Moodle user ID
     * @param int $course_id Moodle course ID
     * @param float $completion_percentage Completion percentage
     * @param int $time_spent_minutes Time spent in minutes
     * @return bool Success
     */
    public function track_progress(int $user_id, int $course_id, float $completion_percentage, int $time_spent_minutes = 0): bool {
        // Check if progress record exists
        $existing = $this->get_progress_record($user_id, $course_id);
        
        $data = [
            'user_id' => (string)$user_id,
            'course_id' => $course_id,
            'completion_percentage' => $completion_percentage,
            'time_spent_minutes' => $time_spent_minutes,
            'last_accessed' => date('c'),
        ];
        
        if ($existing) {
            return $this->update('user_progress', $existing['id'], $data);
        } else {
            $result = $this->insert('user_progress', $data);
            return !empty($result);
        }
    }
    
    /**
     * Log learning analytics event.
     *
     * @param int $user_id Moodle user ID
     * @param string $event_type Event type
     * @param array $event_data Event data
     * @return bool Success
     */
    public function log_event(int $user_id, string $event_type, array $event_data): bool {
        $data = [
            'user_id' => (string)$user_id,
            'event_type' => $event_type,
            'event_data' => $event_data,
        ];
        
        $result = $this->insert('learning_analytics', $data);
        return !empty($result);
    }
    
    /**
     * Get course recommendations for user.
     *
     * @param int $user_id Moodle user ID
     * @return array Recommended courses
     */
    public function get_recommendations(int $user_id): array {
        $url = "{$this->supabase_url}/rest/v1/course_recommendations?" . http_build_query([
            'select' => '*',
            'user_id' => 'eq.' . $user_id,
            'is_active' => 'eq.true',
            'order' => 'confidence_score.desc',
        ]);
        
        $response = $this->make_request('GET', $url);
        return $response ?: [];
    }
    
    /**
     * Insert record into Supabase table.
     *
     * @param string $table Table name
     * @param array $data Record data
     * @return array|false Inserted record or false on failure
     */
    private function insert(string $table, array $data) {
        $url = "{$this->supabase_url}/rest/v1/{$table}";
        
        $response = $this->make_request('POST', $url, $data);
        return $response;
    }
    
    /**
     * Update record in Supabase table.
     *
     * @param string $table Table name
     * @param string $id Record ID
     * @param array $data Updated data
     * @return bool Success
     */
    private function update(string $table, string $id, array $data): bool {
        $url = "{$this->supabase_url}/rest/v1/{$table}?id=eq.{$id}";
        
        $response = $this->make_request('PATCH', $url, $data);
        return $response !== false;
    }
    
    /**
     * Get user progress record.
     *
     * @param int $user_id Moodle user ID
     * @param int $course_id Moodle course ID
     * @return array|null Progress record or null
     */
    private function get_progress_record(int $user_id, int $course_id): ?array {
        $url = "{$this->supabase_url}/rest/v1/user_progress?" . http_build_query([
            'select' => '*',
            'user_id' => 'eq.' . $user_id,
            'course_id' => 'eq.' . $course_id,
        ]);
        
        $response = $this->make_request('GET', $url);
        return !empty($response) ? $response[0] : null;
    }
    
    /**
     * Make HTTP request to Supabase API.
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array|null $data Request body data
     * @return mixed Response data or false on failure
     */
    private function make_request(string $method, string $url, ?array $data = null) {
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_TIMEOUT => 30,
        ];
        
        if ($method === 'POST' || $method === 'PATCH') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            debugging("Supabase API error: {$error}", DEBUG_DEVELOPER);
            return false;
        }
        
        if ($http_code >= 400) {
            debugging("Supabase API returned HTTP {$http_code}: {$response}", DEBUG_DEVELOPER);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Test Supabase connection.
     *
     * @return bool Connection successful
     */
    public function test_connection(): bool {
        $url = "{$this->supabase_url}/rest/v1/ai_generations?limit=1";
        $response = $this->make_request('GET', $url);
        return $response !== false;
    }
}
