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
 * AJAX endpoint to test Supabase connection
 *
 * @package    local_aicourse
 * @copyright  2026 Cours+ Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_login();

require_capability('moodle/site:config', context_system::instance());

$url = required_param('url', PARAM_URL);
$key = required_param('key', PARAM_TEXT);

header('Content-Type: application/json');

try {
    // Test basic connectivity
    $test_url = rtrim($url, '/') . '/rest/v1/';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo json_encode([
            'success' => false,
            'error' => 'Connection failed: ' . $error
        ]);
        exit;
    }
    
    if ($http_code === 200 || $http_code === 401) {
        // 200 = success, 401 = valid endpoint but auth issue (expected with anon key on some endpoints)
        echo json_encode([
            'success' => true,
            'message' => 'Supabase endpoint is reachable'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "HTTP {$http_code}: Invalid response from Supabase"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
