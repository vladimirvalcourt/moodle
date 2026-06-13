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
 * Language strings for AI Course Generation plugin
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin information.
$string['pluginname'] = 'AI Course Generator';
$string['plugindescription'] = 'Generate courses using artificial intelligence';

// Capabilities.
$string['aicourse:generate'] = 'Generate courses with AI';
$string['aicourse:review'] = 'Review AI-generated content';
$string['aicourse:manage'] = 'Manage AI course settings';

// UI Labels.
$string['create_with_ai'] = 'Create with AI';
$string['generate_course'] = 'Generate Course';
$string['course_topic'] = 'Course Topic';
$string['topic_description'] = 'Topic Description';
$string['target_audience'] = 'Target Audience';
$string['estimated_duration'] = 'Estimated Duration (minutes)';
$string['learning_objectives'] = 'Learning Objectives';
$string['generating_outline'] = 'Generating course outline...';
$string['generating_content'] = 'Creating lesson content...';
$string['review_generated_content'] = 'Review Generated Content';
$string['approve_and_publish'] = 'Approve & Publish';
$string['regenerate'] = 'Regenerate';
$string['edit_content'] = 'Edit Content';

// Form labels.
$string['audience_beginner'] = 'Beginner';
$string['audience_intermediate'] = 'Intermediate';
$string['audience_advanced'] = 'Advanced';
$string['audience_mixed'] = 'Mixed Levels';

// Messages.
$string['generation_started'] = 'Course generation started. This may take 1-2 minutes.';
$string['generation_complete'] = 'Course generation complete! Please review the content.';
$string['generation_failed'] = 'Course generation failed. Please try again.';
$string['api_error'] = 'AI service error: {$a}';
$string['rate_limit_reached'] = 'You have reached your daily generation limit.';
$string['content_saved'] = 'Content saved successfully.';

// Settings.
$string['openai_api_key'] = 'OpenAI API Key';
$string['openai_api_key_desc'] = 'Your OpenAI API key for course generation';
$string['claude_api_key'] = 'Anthropic Claude API Key';
$string['claude_api_key_desc'] = 'Your Anthropic API key (optional)';
$string['default_provider'] = 'Default AI Provider';
$string['provider_openai'] = 'OpenAI GPT-4';
$string['provider_claude'] = 'Anthropic Claude';
$string['daily_generation_limit'] = 'Daily Generation Limit per User';
$string['max_tokens_per_request'] = 'Maximum Tokens per Request';
$string['enable_quality_checks'] = 'Enable Quality Checks';
$string['quality_threshold'] = 'Quality Score Threshold';

// Privacy.
$string['privacy:metadata'] = 'This plugin stores AI generation history and course drafts.';
$string['privacy:drafts'] = 'Course drafts created by users';
$string['privacy:history'] = 'History of AI generations including prompts and responses';

// Learner Dashboard.
$string['myaicourses'] = 'My AI Courses';
$string['welcomeback'] = 'Welcome back, {$a}!';
$string['dashboard_desc'] = 'Continue your learning journey with AI-powered courses';
$string['nocoursesyet'] = 'No courses yet';
$string['nocoursesyet_desc'] = 'You haven\'t enrolled in any AI-generated courses yet. Browse available courses to get started!';
$string['browseallcourses'] = 'Browse All Courses';
$string['progress'] = 'Progress';
$string['lessons'] = 'lessons';
$string['continuewith'] = 'Continue with:';
$string['completed'] = 'Completed';
$string['viewcertificate'] = 'View Certificate';
$string['inprogress'] = 'In Progress';
$string['continuelearning'] = 'Continue Learning';
$string['startlearning'] = 'Start Learning';

// Course View.
$string['coursecontent'] = 'Course Content';
$string['yourprogress'] = 'Your Progress';
$string['lessonscompleted'] = 'lessons completed';
$string['estimatedtime'] = 'Estimated time: {$a} minutes';
$string['previouslesson'] = '← Previous Lesson';
$string['nextlesson'] = 'Next Lesson →';
$string['getcertificate'] = 'Get Certificate';
$string['selectlesson'] = 'Select a lesson to begin';
$string['selectlesson_desc'] = 'Choose a lesson from the sidebar to start learning';

// Certificates.
$string['certificate'] = 'Certificate of Completion';
$string['congratulations'] = 'Congratulations!';
$string['certificate_earned'] = 'You have earned your certificate of completion';
$string['printcertificate'] = 'Print Certificate';
$string['backtomycourses'] = 'Back to My Courses';
$string['tip'] = 'Tip';
$string['print_tip'] = 'Use Ctrl+P (or Cmd+P on Mac) to save as PDF or print your certificate';
$string['noteligible'] = 'Not Eligible for Certificate';
$string['noteligible_desc'] = 'Complete all lessons in this course to earn your certificate';
$string['continuecourse'] = 'Continue Course';

// Quiz Integration.
$string['quiztitle'] = '{$a} - Assessment';
$string['quizdescription'] = 'Test your knowledge with AI-generated questions based on the course content.';
$string['aigeneratedquestions'] = 'AI-Generated Questions';
$string['aigeneratedquestions_desc'] = 'Questions automatically generated by artificial intelligence';
$string['correct'] = 'Correct! Well done.';
$string['partiallycorrect'] = 'Partially correct.';
$string['incorrect'] = 'Incorrect. Please review the material.';
$string['true'] = 'True';
$string['false'] = 'False';

// Personalization.
$string['path_novice'] = 'You\'re just starting out! We\'ll guide you through the fundamentals step by step.';
$string['path_developing'] = 'You\'re making good progress! Let\'s strengthen your foundation before advancing.';
$string['path_proficient'] = 'Great job! You\'re ready to tackle more challenging material.';
$string['path_expert'] = 'Excellent! You can skip ahead to advanced topics and master this subject quickly.';
$string['recommendedforyou'] = 'Recommended for You';
$string['recommend_high'] = 'Highly recommended based on your learning history';
$string['recommend_medium'] = 'Good match for your skill level';
$string['recommend_low'] = 'Might interest you';
$string['trendingcourses'] = 'Trending Courses';
$string['similarcourses'] = 'Similar Courses';
$string['yourlearningpath'] = 'Your Personalized Learning Path';
$string['estimatedcompletion'] = 'Estimated completion time: {$a} minutes';
$string['nextstep'] = 'Your Next Step';
$string['viewcourse'] = 'View Course';
