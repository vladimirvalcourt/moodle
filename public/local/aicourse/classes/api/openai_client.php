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
 * OpenAI API Client Implementation
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aicourse/classes/api/ai_provider.php');

/**
 * OpenAI provider for course generation
 */
class openai_client implements ai_provider {
    
    /** @var string OpenAI API key */
    private string $api_key;
    
    /** @var string Model to use */
    private string $model = 'gpt-4-turbo';
    
    /** @var array Token usage tracking */
    private array $token_usage = [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0
    ];
    
    /** @var string OpenAI API endpoint */
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Constructor
     *
     * @param string|null $api_key OpenAI API key (uses config if null)
     * @param string $model Model to use
     */
    public function __construct(?string $api_key = null, string $model = 'gpt-4-turbo') {
        $this->api_key = $api_key ?? get_config('local_aicourse', 'openai_api_key');
        $this->model = $model;
        
        if (empty($this->api_key)) {
            throw new \moodle_exception('openai_api_key_not_configured');
        }
    }
    
    /**
     * Generate a course outline from topic description
     *
     * @param array $params Course parameters
     * @return array Structured course outline
     */
    public function generate_course_outline(array $params): array {
        $prompt = $this->build_outline_prompt($params);
        
        $response = $this->make_api_call([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->get_system_prompt('course_designer')
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 4000
        ]);
        
        // Parse and validate response
        $content = json_decode($response['choices'][0]['message']['content'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('invalid_json_response_from_ai');
        }
        
        // Track token usage
        $this->update_token_usage($response['usage']);
        
        return $this->validate_outline_structure($content);
    }
    
    /**
     * Generate detailed lesson content
     *
     * @param string $topic Lesson topic
     * @param array $context Additional context
     * @return string Generated lesson content
     */
    public function generate_lesson_content(string $topic, array $context): string {
        $prompt = $this->build_lesson_prompt($topic, $context);
        
        $response = $this->make_api_call([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->get_system_prompt('content_writer')
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.8,
            'max_tokens' => 3000
        ]);
        
        $this->update_token_usage($response['usage']);
        
        return trim($response['choices'][0]['message']['content']);
    }
    
    /**
     * Generate quiz questions from lesson content
     *
     * @param string $content Lesson content
     * @param int $count Number of questions
     * @param string $question_type Question type
     * @return array Array of questions
     */
    public function generate_quiz_questions(string $content, int $count, string $question_type = 'multiple_choice'): array {
        $prompt = $this->build_quiz_prompt($content, $count, $question_type);
        
        $response = $this->make_api_call([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->get_system_prompt('assessment_creator')
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.6,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 2500
        ]);
        
        $this->update_token_usage($response['usage']);
        
        $questions = json_decode($response['choices'][0]['message']['content'], true);
        
        return $questions['questions'] ?? [];
    }
    
    /**
     * Summarize content
     *
     * @param string $text Content to summarize
     * @param int $max_points Maximum bullet points
     * @return string Summary
     */
    public function summarize_content(string $text, int $max_points = 5): string {
        $prompt = "Summarize the following content into {$max_points} key bullet points:\n\n{$text}";
        
        $response = $this->make_api_call([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at creating concise, clear summaries.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.5,
            'max_tokens' => 500
        ]);
        
        $this->update_token_usage($response['usage']);
        
        return trim($response['choices'][0]['message']['content']);
    }
    
    /**
     * Translate content
     *
     * @param string $text Content to translate
     * @param string $target_lang Target language
     * @return string Translated content
     */
    public function translate_content(string $text, string $target_lang): string {
        $prompt = "Translate the following content to {$target_lang}. Maintain the original formatting and tone:\n\n{$text}";
        
        $response = $this->make_api_call([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional translator with expertise in educational content.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 3000
        ]);
        
        $this->update_token_usage($response['usage']);
        
        return trim($response['choices'][0]['message']['content']);
    }
    
    /**
     * Get token usage
     *
     * @return array Token usage data
     */
    public function get_token_usage(): array {
        return $this->token_usage;
    }
    
    /**
     * Get provider name
     *
     * @return string Provider identifier
     */
    public function get_provider_name(): string {
        return 'openai';
    }
    
    /**
     * Get model name
     *
     * @return string Model identifier
     */
    public function get_model(): string {
        return $this->model;
    }
    
    /**
     * Make API call to OpenAI
     *
     * @param array $payload Request payload
     * @return array API response
     * @throws \moodle_exception On API error
     */
    private function make_api_call(array $payload): array {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ];
        
        $ch = curl_init(self::API_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120, // 2 minute timeout for long generations
            CURLOPT_CONNECTTIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \moodle_exception('curl_error', '', '', $error);
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';
            throw new \moodle_exception('openai_api_error', '', '', $error_message);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Build prompt for course outline generation
     *
     * @param array $params Course parameters
     * @return string Formatted prompt
     */
    private function build_outline_prompt(array $params): string {
        $audience = $params['audience'] ?? 'beginner';
        $duration = $params['duration'] ?? 480;
        $objectives = isset($params['objectives']) ? implode(', ', $params['objectives']) : 'Comprehensive understanding of the topic';
        
        return <<<PROMPT
Create a comprehensive online course outline for: "{$params['topic']}"

TARGET AUDIENCE: {$audience}
ESTIMATED DURATION: {$duration} minutes
LEARNING OBJECTIVES: {$objectives}

Requirements:
1. Create 4-8 modules with logical progression
2. Each module should have 3-6 lessons
3. Include learning outcomes for each module
4. Suggest appropriate activities and assessments
5. Estimate time per module and lesson
6. Ensure content is engaging and practical
7. Match difficulty to audience level

Return ONLY valid JSON with this exact structure:
{
  "course_title": "Engaging course title",
  "description": "Compelling course description (2-3 sentences)",
  "modules": [
    {
      "title": "Module title",
      "description": "Brief module description",
      "learning_outcomes": ["Outcome 1", "Outcome 2"],
      "estimated_time": 60,
      "lessons": [
        {
          "title": "Lesson title",
          "content_type": "reading|video|interactive|quiz",
          "estimated_time": 15,
          "key_points": ["Point 1", "Point 2", "Point 3"]
        }
      ]
    }
  ]
}
PROMPT;
    }
    
    /**
     * Build prompt for lesson content
     *
     * @param string $topic Lesson topic
     * @param array $context Context information
     * @return string Formatted prompt
     */
    private function build_lesson_prompt(string $topic, array $context): string {
        $module_info = $context['module'] ?? '';
        $prerequisites = $context['prerequisites'] ?? 'None';
        
        return <<<PROMPT
Write detailed educational content for the lesson: "{$topic}"

MODULE: {$module_info}
PREREQUISITES: {$prerequisites}

Guidelines:
- Write in clear, engaging language appropriate for the target audience
- Include practical examples and real-world applications
- Break content into digestible sections with headings
- Include key takeaways at the end
- Aim for approximately 800-1200 words
- Use markdown formatting for structure

Content structure:
1. Introduction (hook the learner)
2. Main concepts (detailed explanation)
3. Examples and applications
4. Common mistakes/misconceptions
5. Summary and key takeaways

Write the complete lesson content now:
PROMPT;
    }
    
    /**
     * Build prompt for quiz generation
     *
     * @param string $content Lesson content
     * @param int $count Number of questions
     * @param string $type Question type
     * @return string Formatted prompt
     */
    private function build_quiz_prompt(string $content, int $count, string $type): string {
        return <<<PROMPT
Generate {$count} {$type} questions based on the following lesson content.

CONTENT:
{$content}

Requirements:
- Questions should test understanding, not just recall
- Vary difficulty levels (mix easy, medium, hard)
- Provide clear explanations for correct answers
- For multiple choice: include 4 options (1 correct, 3 plausible distractors)
- Align with Bloom's taxonomy where possible

Return ONLY valid JSON with this structure:
{
  "questions": [
    {
      "question": "Question text?",
      "type": "{$type}",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct_answer": "Correct option",
      "explanation": "Why this is correct",
      "difficulty": 2,
      "bloom_level": "Understand"
    }
  ]
}
PROMPT;
    }
    
    /**
     * Get system prompt based on role
     *
     * @param string $role Role type
     * @return string System prompt
     */
    private function get_system_prompt(string $role): string {
        $prompts = [
            'course_designer' => 'You are an expert instructional designer with 20+ years of experience creating engaging online courses. You specialize in structured curriculum design, learning objectives, and pedagogical best practices.',
            'content_writer' => 'You are an experienced educational content writer who creates clear, engaging, and accurate learning materials. You excel at explaining complex topics in accessible ways.',
            'assessment_creator' => 'You are an assessment specialist who creates fair, valid, and reliable test questions. You understand Bloom\'s taxonomy and how to write questions that truly measure learning.'
        ];
        
        return $prompts[$role] ?? $prompts['content_writer'];
    }
    
    /**
     * Update token usage tracking
     *
     * @param array $usage Usage data from API
     */
    private function update_token_usage(array $usage): void {
        $this->token_usage['prompt_tokens'] += $usage['prompt_tokens'] ?? 0;
        $this->token_usage['completion_tokens'] += $usage['completion_tokens'] ?? 0;
        $this->token_usage['total_tokens'] += $usage['total_tokens'] ?? 0;
    }
    
    /**
     * Validate outline structure
     *
     * @param array $outline Raw outline from AI
     * @return array Validated and normalized outline
     */
    private function validate_outline_structure(array $outline): array {
        // Ensure required fields exist
        $required = ['course_title', 'description', 'modules'];
        foreach ($required as $field) {
            if (!isset($outline[$field])) {
                throw new \moodle_exception('missing_required_field_in_outline', '', '', $field);
            }
        }
        
        // Validate modules structure
        if (!is_array($outline['modules']) || empty($outline['modules'])) {
            throw new \moodle_exception('invalid_modules_structure');
        }
        
        // Normalize data
        $outline['generated_at'] = time();
        $outline['provider'] = $this->get_provider_name();
        $outline['model'] = $this->get_model();
        
        return $outline;
    }
}
