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
 * View and download course completion certificate
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/aicourse/lib.php');

// Get parameters
$course_id = required_param('id', PARAM_INT);

// Require login
require_login();

// Get course
$course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);

// Check enrollment
if (!is_enrolled(context_course::instance($course_id), $USER->id)) {
    throw new moodle_exception('notenrolled');
}

// Set up page
$PAGE->set_url('/local/aicourse/certificate.php', ['id' => $course_id]);
$PAGE->set_context(context_course::instance($course_id));
$PAGE->set_title(get_string('certificate', 'local_aicourse'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('mycourses'), new moodle_url('/my/'));
$PAGE->navbar->add(format_string($course->shortname), new moodle_url('/local/aicourse/view.php', ['id' => $course_id]));
$PAGE->navbar->add(get_string('certificate', 'local_aicourse'));

// Generate certificate
try {
    $cert_generator = new \local_aicourse\services\certificate_generator($USER->id, $course_id);
    
    if (!$cert_generator->is_eligible()) {
        throw new moodle_exception('not_eligible_for_certificate');
    }
    
    $certificate_html = $cert_generator->generate_certificate();
    $eligible = true;
} catch (Exception $e) {
    $eligible = false;
    $error_message = $e->getMessage();
}

// Output page
echo $OUTPUT->header();
?>

<div class="certificate-page">
    <?php if ($eligible): ?>
    <div class="text-center mb-4">
        <h1><i class="fa fa-certificate text-success"></i> <?php echo get_string('congratulations', 'local_aicourse'); ?></h1>
        <p class="lead"><?php echo get_string('certificate_earned', 'local_aicourse'); ?></p>
        
        <div class="btn-group mt-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fa fa-print"></i> <?php echo get_string('printcertificate', 'local_aicourse'); ?>
            </button>
            <a href="<?php echo new moodle_url('/local/aicourse/my_courses.php'); ?>" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> <?php echo get_string('backtomycourses', 'local_aicourse'); ?>
            </a>
        </div>
    </div>
    
    <!-- Certificate Display -->
    <div class="certificate-wrapper">
        <?php echo $certificate_html; ?>
    </div>
    
    <div class="text-center mt-4">
        <div class="alert alert-info">
            <strong><?php echo get_string('tip', 'local_aicourse'); ?>:</strong> 
            <?php echo get_string('print_tip', 'local_aicourse'); ?>
        </div>
    </div>
    
    <?php else: ?>
    <div class="alert alert-warning">
        <h4><i class="fa fa-exclamation-triangle"></i> <?php echo get_string('noteligible', 'local_aicourse'); ?></h4>
        <p><?php echo get_string('noteligible_desc', 'local_aicourse'); ?></p>
        <a href="<?php echo new moodle_url('/local/aicourse/view.php', ['id' => $course_id]); ?>" class="btn btn-primary mt-2">
            <?php echo get_string('continuecourse', 'local_aicourse'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .certificate-container, .certificate-container * {
        visibility: visible;
    }
    .certificate-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}
</style>

<?php
echo $OUTPUT->footer();
