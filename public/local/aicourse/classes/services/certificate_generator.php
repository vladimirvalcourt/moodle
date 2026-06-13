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
 * Certificate generation service
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
 * Generate completion certificates for AI courses
 */
class certificate_generator {
    
    /** @var int User ID */
    private $user_id;
    
    /** @var int Course ID */
    private $course_id;
    
    /** @var \moodle_database Database instance */
    private $db;
    
    /**
     * Constructor
     *
     * @param int $user_id
     * @param int $course_id
     */
    public function __construct(int $user_id, int $course_id) {
        global $DB;
        
        $this->user_id = $user_id;
        $this->course_id = $course_id;
        $this->db = $DB;
    }
    
    /**
     * Check if user is eligible for certificate
     *
     * @return bool
     */
    public function is_eligible(): bool {
        $progress_tracker = new progress_tracker($this->user_id, $this->course_id);
        return $progress_tracker->is_course_complete();
    }
    
    /**
     * Generate certificate HTML
     *
     * @return string Certificate HTML
     */
    public function generate_certificate(): string {
        if (!$this->is_eligible()) {
            throw new \moodle_exception('not_eligible_for_certificate');
        }
        
        // Get user info
        $user = $this->db->get_record('user', ['id' => $this->user_id]);
        $course = $this->db->get_record('course', ['id' => $this->course_id]);
        
        // Get draft for course details
        $draft = $this->db->get_record('local_aicourse_drafts', ['published_course_id' => $this->course_id]);
        $content = $draft ? json_decode($draft->generated_content, true) : null;
        
        // Calculate completion date
        $progress_tracker = new progress_tracker($this->user_id, $this->course_id);
        $completed_lessons = $progress_tracker->get_completed_lessons();
        $last_completion = !empty($completed_lessons) 
            ? max(array_column($completed_lessons, 'timecompleted'))
            : time();
        
        // Generate unique certificate ID
        $cert_id = strtoupper(bin2hex(random_bytes(8)));
        
        $html = $this->render_certificate_template([
            'student_name' => fullname($user),
            'course_name' => format_string($course->fullname),
            'course_description' => $content['course_description'] ?? '',
            'completion_date' => userdate($last_completion, get_string('strftimedatefullshort')),
            'certificate_id' => $cert_id,
            'issue_date' => userdate(time(), get_string('strftimedatefullshort')),
            'total_modules' => $content ? count($content['modules']) : 0,
            'total_lessons' => $this->count_total_lessons($content),
        ]);
        
        // Log certificate issuance
        $this->log_certificate_issuance($cert_id);
        
        return $html;
    }
    
    /**
     * Render certificate template
     *
     * @param array $data Certificate data
     * @return string HTML
     */
    private function render_certificate_template(array $data): string {
        ob_start();
        ?>
        <div class="certificate-container" style="
            max-width: 800px;
            margin: 40px auto;
            padding: 60px;
            border: 15px solid #2c5aa0;
            background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%);
            text-align: center;
            font-family: 'Georgia', serif;
            position: relative;
        ">
            <!-- Decorative corners -->
            <div style="position: absolute; top: 20px; left: 20px; font-size: 40px; color: #2c5aa0;">❖</div>
            <div style="position: absolute; top: 20px; right: 20px; font-size: 40px; color: #2c5aa0;">❖</div>
            <div style="position: absolute; bottom: 20px; left: 20px; font-size: 40px; color: #2c5aa0;">❖</div>
            <div style="position: absolute; bottom: 20px; right: 20px; font-size: 40px; color: #2c5aa0;">❖</div>
            
            <!-- Header -->
            <div style="margin-bottom: 30px;">
                <h1 style="
                    font-size: 48px;
                    color: #2c5aa0;
                    margin: 0;
                    text-transform: uppercase;
                    letter-spacing: 3px;
                ">Certificate of Completion</h1>
            </div>
            
            <!-- Subtitle -->
            <p style="font-size: 18px; color: #666; margin-bottom: 40px;">
                This is to certify that
            </p>
            
            <!-- Student Name -->
            <h2 style="
                font-size: 36px;
                color: #1a1a1a;
                margin: 20px 0;
                border-bottom: 3px solid #2c5aa0;
                display: inline-block;
                padding-bottom: 10px;
            "><?php echo s($data['student_name']); ?></h2>
            
            <!-- Achievement Text -->
            <p style="font-size: 18px; color: #333; margin: 30px 0; line-height: 1.6;">
                has successfully completed the course<br>
                <strong style="font-size: 24px; color: #2c5aa0;"><?php echo s($data['course_name']); ?></strong>
            </p>
            
            <?php if ($data['course_description']): ?>
            <p style="font-size: 14px; color: #666; max-width: 600px; margin: 20px auto;">
                <?php echo s($data['course_description']); ?>
            </p>
            <?php endif; ?>
            
            <!-- Course Stats -->
            <div style="
                margin: 30px 0;
                padding: 20px;
                background: rgba(44, 90, 160, 0.1);
                border-radius: 8px;
                display: inline-block;
            ">
                <p style="margin: 5px 0; font-size: 16px; color: #2c5aa0;">
                    <strong><?php echo $data['total_modules']; ?></strong> Modules • 
                    <strong><?php echo $data['total_lessons']; ?></strong> Lessons
                </p>
            </div>
            
            <!-- Date -->
            <p style="font-size: 16px; color: #666; margin: 30px 0;">
                Completed on: <strong><?php echo $data['completion_date']; ?></strong>
            </p>
            
            <!-- Certificate ID -->
            <div style="
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                font-size: 12px;
                color: #999;
            ">
                <p>Certificate ID: <code style="background: #f5f5f5; padding: 2px 8px; border-radius: 3px;">
                    <?php echo $data['certificate_id']; ?>
                </code></p>
                <p>Issued: <?php echo $data['issue_date']; ?></p>
            </div>
            
            <!-- Seal -->
            <div style="
                position: absolute;
                bottom: 80px;
                right: 80px;
                width: 100px;
                height: 100px;
                border: 4px solid #d4af37;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                color: #d4af37;
                transform: rotate(-15deg);
            ">★</div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Count total lessons from content
     *
     * @param array|null $content
     * @return int
     */
    private function count_total_lessons(?array $content): int {
        if (!$content || empty($content['modules'])) {
            return 0;
        }
        
        $total = 0;
        foreach ($content['modules'] as $module) {
            $total += count($module['lessons']);
        }
        
        return $total;
    }
    
    /**
     * Log certificate issuance
     *
     * @param string $certificate_id
     * @return void
     */
    private function log_certificate_issuance(string $certificate_id): void {
        $record = new \stdClass();
        $record->user_id = $this->user_id;
        $record->course_id = $this->course_id;
        $record->certificate_id = $certificate_id;
        $record->issued_date = time();
        
        $this->db->insert_record('local_aicourse_certificates', $record);
    }
}
