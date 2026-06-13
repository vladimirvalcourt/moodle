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
 * AI Provider Interface - Abstract contract for AI service providers
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aicourse\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for AI course generation providers
 */
interface ai_provider {
    
    /**
     * Generate a course outline from topic description
     *
     * @param array $params Course parameters (topic, audience, duration, objectives)
     * @return array Structured course outline
     * @throws \moodle_exception If generation fails
     */
    public function generate_course_outline(array $params): array;
    
    /**
     * Generate detailed lesson content
     *
     * @param string $topic Lesson topic
     * @param array $context Additional context (module info, prerequisites, etc.)
     * @return string Generated lesson content
     */
    public function generate_lesson_content(string $topic, array $context): string;
    
    /**
     * Generate quiz questions from lesson content
     *
     * @param string $content Lesson content to base questions on
     * @param int $count Number of questions to generate
     * @param string $question_type Type of questions (multiple_choice, true_false, etc.)
     * @return array Array of question objects
     */
    public function generate_quiz_questions(string $content, int $count, string $question_type = 'multiple_choice'): array;
    
    /**
     * Summarize long content into key points
     *
     * @param string $text Content to summarize
     * @param int $max_points Maximum number of bullet points
     * @return string Summarized content
     */
    public function summarize_content(string $text, int $max_points = 5): string;
    
    /**
     * Translate content to another language
     *
     * @param string $text Content to translate
     * @param string $target_lang Target language code (e.g., 'es', 'fr', 'de')
     * @return string Translated content
     */
    public function translate_content(string $text, string $target_lang): string;
    
    /**
     * Get token usage statistics for billing/tracking
     *
     * @return array Token usage data (prompt_tokens, completion_tokens, total_tokens)
     */
    public function get_token_usage(): array;
    
    /**
     * Get the provider name
     *
     * @return string Provider identifier (e.g., 'openai', 'claude')
     */
    public function get_provider_name(): string;
    
    /**
     * Get the model being used
     *
     * @return string Model identifier (e.g., 'gpt-4-turbo', 'claude-3-opus')
     */
    public function get_model(): string;
}
