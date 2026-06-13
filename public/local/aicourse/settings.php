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
 * Admin settings for AI Course Generator plugin
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create main settings page
    $settings = new admin_settingpage('local_aicourse', get_string('pluginname', 'local_aicourse'));
    
    // API Configuration Section
    $settings->add(new admin_setting_heading(
        'local_aicourse/api_config',
        get_string('api_configuration', 'local_aicourse', 'API Configuration'),
        ''
    ));
    
    // OpenAI API Key
    $settings->add(new admin_setting_configtext(
        'local_aicourse/openai_api_key',
        get_string('openai_api_key', 'local_aicourse'),
        get_string('openai_api_key_desc', 'local_aicourse'),
        '',
        PARAM_TEXT,
        50
    ));
    
    // Claude API Key (optional)
    $settings->add(new admin_setting_configtext(
        'local_aicourse/claude_api_key',
        get_string('claude_api_key', 'local_aicourse'),
        get_string('claude_api_key_desc', 'local_aicourse'),
        '',
        PARAM_TEXT,
        50
    ));
    
    // Default Provider Selection
    $providers = [
        'openai' => get_string('provider_openai', 'local_aicourse'),
        'claude' => get_string('provider_claude', 'local_aicourse'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_aicourse/default_provider',
        get_string('default_provider', 'local_aicourse'),
        '',
        'openai',
        $providers
    ));
    
    // Generation Limits Section
    $settings->add(new admin_setting_heading(
        'local_aicourse/limits',
        get_string('generation_limits', 'local_aicourse', 'Generation Limits'),
        ''
    ));
    
    // Daily generation limit per user
    $settings->add(new admin_setting_configtext(
        'local_aicourse/daily_generation_limit',
        get_string('daily_generation_limit', 'local_aicourse'),
        get_string('daily_generation_limit_desc', 'local_aicourse', 'Maximum number of course generations per user per day'),
        10,
        PARAM_INT
    ));
    
    // Max tokens per request
    $settings->add(new admin_setting_configtext(
        'local_aicourse/max_tokens_per_request',
        get_string('max_tokens_per_request', 'local_aicourse'),
        get_string('max_tokens_per_request_desc', 'local_aicourse', 'Maximum tokens allowed per API request'),
        4000,
        PARAM_INT
    ));
    
    // Quality Control Section
    $settings->add(new admin_setting_heading(
        'local_aicourse/quality',
        get_string('quality_control', 'local_aicourse', 'Quality Control'),
        ''
    ));
    
    // Enable quality checks toggle
    $settings->add(new admin_setting_configcheckbox(
        'local_aicourse/enable_quality_checks',
        get_string('enable_quality_checks', 'local_aicourse'),
        get_string('enable_quality_checks_desc', 'local_aicourse', 'Automatically validate AI-generated content'),
        1
    ));
    
    // Quality threshold
    $settings->add(new admin_setting_configtext(
        'local_aicourse/quality_threshold',
        get_string('quality_threshold', 'local_aicourse'),
        get_string('quality_threshold_desc', 'local_aicourse', 'Minimum quality score (0-100) to auto-approve content'),
        75,
        PARAM_INT
    ));
    
    // Advanced Settings Section
    $settings->add(new admin_setting_heading(
        'local_aicourse/advanced',
        get_string('advanced_settings', 'local_aicourse', 'Advanced Settings'),
        ''
    ));
    
    // Default model selection
    $models = [
        'gpt-4-turbo' => 'GPT-4 Turbo (Recommended)',
        'gpt-4' => 'GPT-4',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Faster, cheaper)',
    ];
    $settings->add(new admin_setting_configselect(
        'local_aicourse/default_model',
        get_string('default_model', 'local_aicourse', 'Default OpenAI Model'),
        get_string('default_model_desc', 'local_aicourse', 'Model used for course generation'),
        'gpt-4-turbo',
        $models
    ));
    
    // Temperature setting
    $settings->add(new admin_setting_configtext(
        'local_aicourse/default_temperature',
        get_string('default_temperature', 'local_aicourse', 'Default Temperature'),
        get_string('default_temperature_desc', 'local_aicourse', 'Creativity level (0.0-1.0). Higher = more creative, lower = more focused'),
        '0.7',
        PARAM_FLOAT,
        null,
        null,
        function($value) {
            $float = floatval($value);
            if ($float < 0 || $float > 1) {
                return get_string('invalid_temperature', 'local_aicourse');
            }
            return null;
        }
    ));
    
    // Enable caching
    $settings->add(new admin_setting_configcheckbox(
        'local_aicourse/enable_caching',
        get_string('enable_caching', 'local_aicourse', 'Enable Response Caching'),
        get_string('enable_caching_desc', 'local_aicourse', 'Cache similar course generations to reduce API costs'),
        1
    ));
    
    // Supabase Integration Section
    $settings->add(new admin_setting_heading(
        'local_aicourse/supabase_config',
        get_string('supabase_configuration', 'local_aicourse', 'Supabase Backend Configuration'),
        ''
    ));
    
    // Supabase Project URL
    $settings->add(new admin_setting_configtext(
        'local_aicourse/supabase_url',
        get_string('supabase_url', 'local_aicourse', 'Supabase Project URL'),
        get_string('supabase_url_desc', 'local_aicourse', 'Your Supabase project URL (e.g., https://xxxxx.supabase.co)'),
        '',
        PARAM_URL,
        60
    ));
    
    // Supabase Anon Key
    $settings->add(new admin_setting_configtext(
        'local_aicourse/supabase_anon_key',
        get_string('supabase_anon_key', 'local_aicourse', 'Supabase Anon Key'),
        get_string('supabase_anon_key_desc', 'local_aicourse', 'Public anon key from Supabase dashboard'),
        '',
        PARAM_TEXT,
        80
    ));
    
    // Supabase Service Role Key (stored securely)
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aicourse/supabase_service_key',
        get_string('supabase_service_key', 'local_aicourse', 'Supabase Service Role Key'),
        get_string('supabase_service_key_desc', 'local_aicourse', 'Secret service role key (keep secure! Used for server-side operations)'),
        ''
    ));
    
    // Enable Supabase integration toggle
    $settings->add(new admin_setting_configcheckbox(
        'local_aicourse/enable_supabase',
        get_string('enable_supabase', 'local_aicourse', 'Enable Supabase Integration'),
        get_string('enable_supabase_desc', 'local_aicourse', 'Use Supabase as backend for AI features, analytics, and progress tracking'),
        0
    ));
    
    // Test Connection Button
    $settings->add(new admin_setting_description(
        'local_aicourse/test_connection_info',
        get_string('test_connection', 'local_aicourse', 'Test Connection'),
        '<button type="button" id="test-supabase-connection" class="btn btn-secondary">Test Supabase Connection</button>
         <span id="connection-status"></span>
         <script>
         require(["jquery"], function($) {
             $(document).ready(function() {
                 $("#test-supabase-connection").on("click", function() {
                     var url = $("input[name=\"s_local_aicourse[supabase_url]\"]").val();
                     var key = $("input[name=\"s_local_aicourse[supabase_anon_key]\"]").val();
                     
                     if (!url || !key) {
                         $("#connection-status").html("<span style=\"color:red;\">Please fill in URL and Anon Key first</span>");
                         return;
                     }
                     
                     $("#connection-status").html("<span style=\"color:blue;\">Testing...</span>");
                     
                     $.ajax({
                         url: M.cfg.wwwroot + "/local/aicourse/ajax/test_supabase.php",
                         method: "POST",
                         data: { url: url, key: key },
                         success: function(response) {
                             if (response.success) {
                                 $("#connection-status").html("<span style=\"color:green;\">✓ Connection successful!</span>");
                             } else {
                                 $("#connection-status").html("<span style=\"color:red;\">✗ " + response.error + "</span>");
                             }
                         },
                         error: function() {
                             $("#connection-status").html("<span style=\"color:red;\">✗ Request failed</span>");
                         }
                     });
                 });
             });
         });
         </script>'
    ));
    
    // Add the settings page to admin tree
    $ADMIN->add('localplugins', $settings);
}
