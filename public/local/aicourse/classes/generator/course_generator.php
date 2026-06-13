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
 * Course Generator Service - Orchestrates AI course generation
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\generator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aicourse/lib.php');

/**
 * Main course generation orchestrator
 */
class course_generator {
    
    /** @var \local_aicourse\api\ai_provider AI provider instance */
    private $provider;
    
    /** @var int User ID generating the course */
    private int $user_id;
    
    /** @var array Generation parameters */
    private array $params;
    
    /** @var int Current draft ID */
    private ?int $draft_id = null;
    
    /**
     * Constructor
     *
     * @param int $user_id User ID
     * @param array $params Generation parameters
     * @param string|null $provider Provider name
     */
    public function __construct(int $user_id, array $params, ?string $provider = null) {
        $this->user_id = $user_id;
        $this->params = $params;
        $this->provider = local_aicourse_get_provider($provider);
    }
    
    /**
     * Generate complete course (outline + content)
     *
     * @return int Draft ID
     * @throws \moodle_exception On failure
     */
    public function generate(): int {
        // Step 1: Create draft
        $this->draft_id = local_aicourse_create_draft($this->user_id, $this->params);
        
        try {
            // Step 2: Generate outline
            $outline = $this->generate_outline();
            
            // Step 3: Generate detailed content for each module
            $content = $this->generate_content($outline);
            
            // Step 4: Generate assessments
            $assessments = $this->generate_assessments($content);
            
            // Step 5: Validate quality
            $quality_score = $this->validate_quality($content);
            
            // Step 6: Update draft with all content
            $metadata = [
                'model' => $this->provider->get_model(),
                'token_usage' => $this->provider->get_token_usage(),
                'quality_score' => $quality_score,
                'generated_at' => time()
            ];
            
            local_aicourse_update_draft_content(
                $this->draft_id,
                $outline,
                array_merge($content, ['assessments' => $assessments]),
                $this->provider->get_provider_name(),
                $metadata
            );
            
            // Step 7: Log generation
            local_aicourse_log_generation(
                $this->draft_id,
                json_encode($this->params),
                json_encode($outline),
                $quality_score
            );
            
            return $this->draft_id;
            
        } catch (\Exception $e) {
            // Mark draft as failed
            if ($this->draft_id) {
                global $DB;
                $DB->set_field('local_aicourse_drafts', 'status', 'failed', ['id' => $this->draft_id]);
            }
            
            throw new \moodle_exception('generation_failed', '', '', $e->getMessage());
        }
    }
    
    /**
     * Generate only the course outline (faster, for preview)
     *
     * @return array Course outline
     */
    public function generate_outline_only(): array {
        $outline = $this->generate_outline();
        
        // Save just the outline
        if ($this->draft_id) {
            global $DB;
            $DB->set_field('local_aicourse_drafts', 'generated_outline', json_encode($outline), ['id' => $this->draft_id]);
        }
        
        return $outline;
    }
    
    /**
     * Generate course outline using AI
     *
     * @return array Structured outline
     */
    private function generate_outline(): array {
        return $this->provider->generate_course_outline([
            'topic' => $this->params['topic'],
            'audience' => $this->params['audience'] ?? 'beginner',
            'duration' => $this->params['duration'] ?? 480,
            'objectives' => $this->params['objectives'] ?? []
        ]);
    }
    
    /**
     * Generate detailed content for all modules
     *
     * @param array $outline Course outline
     * @return array Generated content
     */
    private function generate_content(array $outline): array {
        $content = [
            'course_description' => $outline['description'] ?? '',
            'modules' => []
        ];
        
        foreach ($outline['modules'] as $module_index => $module) {
            $module_content = [
                'title' => $module['title'],
                'description' => $module['description'] ?? '',
                'lessons' => []
            ];
            
            foreach ($module['lessons'] as $lesson_index => $lesson) {
                // Generate lesson content
                $lesson_text = $this->provider->generate_lesson_content(
                    $lesson['title'],
                    [
                        'module' => $module['title'],
                        'prerequisites' => $lesson_index > 0 ? $module['lessons'][$lesson_index - 1]['title'] : 'None'
                    ]
                );
                
                $module_content['lessons'][] = [
                    'title' => $lesson['title'],
                    'content' => $lesson_text,
                    'key_points' => $lesson['key_points'] ?? [],
                    'estimated_time' => $lesson['estimated_time'] ?? 15
                ];
            }
            
            $content['modules'][] = $module_content;
        }
        
        return $content;
    }
    
    /**
     * Generate assessments for the course
     *
     * @param array $content Generated content
     * @return array Assessments
     */
    private function generate_assessments(array $content): array {
        $assessments = [];
        
        foreach ($content['modules'] as $module_index => $module) {
            // Combine all lesson content for this module
            $module_text = implode("\n\n", array_column($module['lessons'], 'content'));
            
            // Generate quiz questions
            $questions = $this->provider->generate_quiz_questions(
                $module_text,
                5, // 5 questions per module
                'multiple_choice'
            );
            
            $assessments[] = [
                'module_index' => $module_index,
                'module_title' => $module['title'],
                'questions' => $questions
            ];
        }
        
        return $assessments;
    }
    
    /**
     * Validate content quality
     *
     * @param array $content Generated content
     * @return float Quality score (0-100)
     */
    private function validate_quality(array $content): float {
        $enable_checks = get_config('local_aicourse', 'enable_quality_checks');
        
        if (!$enable_checks) {
            return 100; // Skip validation
        }
        
        // TODO: Implement full quality validation pipeline
        // For now, return a default passing score
        return 85.0;
    }
}
