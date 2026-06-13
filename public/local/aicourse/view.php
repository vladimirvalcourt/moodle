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
 * View AI-generated course content
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/aicourse/lib.php');

// Get parameters
$course_id = required_param('id', PARAM_INT);
$module_index = optional_param('module', 0, PARAM_INT);
$lesson_index = optional_param('lesson', 0, PARAM_INT);

// Require login
require_login();

// Get course
$course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);

// Check enrollment
if (!is_enrolled(context_course::instance($course_id), $USER->id)) {
    redirect(new moodle_url('/local/aicourse/enrol.php', ['id' => $course_id]));
}

// Get draft data
$draft = $DB->get_record('local_aicourse_drafts', ['published_course_id' => $course_id]);
if (!$draft) {
    throw new moodle_exception('course_not_found');
}

// Decode content
$content = json_decode($draft->generated_content, true);
$outline = json_decode($draft->generated_outline, true);

// Set up page
$PAGE->set_url('/local/aicourse/view.php', ['id' => $course_id]);
$PAGE->set_context(context_course::instance($course_id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('mycourses'), new moodle_url('/my/'));
$PAGE->navbar->add(format_string($course->shortname));

// Get current module and lesson
$current_module = $content['modules'][$module_index] ?? null;
$current_lesson = $current_module['lessons'][$lesson_index] ?? null;

// Track progress
if ($current_lesson) {
    $progress_tracker = new \local_aicourse\services\progress_tracker($USER->id, $course_id);
    $progress_tracker->mark_lesson_viewed($module_index, $lesson_index);
}

// Output page
echo $OUTPUT->header();
?>

<div class="aicourse-view-container">
    <div class="row">
        <!-- Sidebar: Module Navigation -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header">
                    <h5><?php echo get_string('coursecontent', 'local_aicourse'); ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($content['modules'] as $mod_idx => $module): ?>
                        <div class="list-group-item">
                            <strong><?php echo ($mod_idx + 1) . '. ' . s($module['title']); ?></strong>
                            <ul class="list-unstyled mt-2 ml-3">
                                <?php foreach ($module['lessons'] as $less_idx => $lesson): ?>
                                <li class="<?php 
                                    echo ($mod_idx == $module_index && $less_idx == $lesson_index) ? 'active' : ''; 
                                ?>">
                                    <a href="<?php echo new moodle_url('/local/aicourse/view.php', [
                                        'id' => $course_id,
                                        'module' => $mod_idx,
                                        'lesson' => $less_idx
                                    ]); ?>" class="text-decoration-none">
                                        <?php echo ($less_idx + 1) . '. ' . s($lesson['title']); ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Progress Card -->
            <?php
            $progress = new \local_aicourse\services\progress_tracker($USER->id, $course_id);
            $stats = $progress->get_progress_stats();
            ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo get_string('yourprogress', 'local_aicourse'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-2">
                        <div class="progress-bar" role="progressbar" 
                             style="width: <?php echo $stats['percentage']; ?>%"
                             aria-valuenow="<?php echo $stats['percentage']; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($stats['percentage']); ?>%
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php echo $stats['completed_lessons']; ?> / <?php echo $stats['total_lessons']; ?> 
                        <?php echo get_string('lessonscompleted', 'local_aicourse'); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="col-md-9">
            <?php if ($current_lesson): ?>
            <div class="card">
                <div class="card-header">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="<?php echo new moodle_url('/local/aicourse/view.php', ['id' => $course_id]); ?>">
                                    <?php echo s($content['modules'][$module_index]['title']); ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item active">
                                <?php echo s($current_lesson['title']); ?>
                            </li>
                        </ol>
                    </nav>
                </div>
                <div class="card-body">
                    <h1 class="h3 mb-4"><?php echo s($current_lesson['title']); ?></h1>
                    
                    <!-- Lesson Content -->
                    <div class="lesson-content">
                        <?php 
                        // Render markdown content (simple conversion)
                        echo format_text($current_lesson['content'], FORMAT_HTML);
                        ?>
                    </div>
                    
                    <!-- Key Points -->
                    <?php if (!empty($current_lesson['key_points'])): ?>
                    <div class="alert alert-info mt-4">
                        <h5><i class="fa fa-lightbulb-o"></i> Key Takeaways</h5>
                        <ul>
                            <?php foreach ($current_lesson['key_points'] as $point): ?>
                            <li><?php echo s($point); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Estimated Time -->
                    <div class="text-muted mt-3">
                        <small>
                            <i class="fa fa-clock-o"></i> 
                            <?php echo get_string('estimatedtime', 'local_aicourse', $current_lesson['estimated_time']); ?>
                        </small>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <?php
                        // Previous lesson link
                        if ($lesson_index > 0) {
                            echo html_writer::link(
                                new moodle_url('/local/aicourse/view.php', [
                                    'id' => $course_id,
                                    'module' => $module_index,
                                    'lesson' => $lesson_index - 1
                                ]),
                                get_string('previouslesson', 'local_aicourse'),
                                ['class' => 'btn btn-secondary']
                            );
                        } else if ($module_index > 0) {
                            // Go to last lesson of previous module
                            $prev_module_lessons = count($content['modules'][$module_index - 1]['lessons']);
                            echo html_writer::link(
                                new moodle_url('/local/aicourse/view.php', [
                                    'id' => $course_id,
                                    'module' => $module_index - 1,
                                    'lesson' => $prev_module_lessons - 1
                                ]),
                                get_string('previouslesson', 'local_aicourse'),
                                ['class' => 'btn btn-secondary']
                            );
                        } else {
                            echo '<div></div>'; // Spacer
                        }
                        
                        // Next lesson link
                        $has_next = false;
                        if ($lesson_index < count($current_module['lessons']) - 1) {
                            echo html_writer::link(
                                new moodle_url('/local/aicourse/view.php', [
                                    'id' => $course_id,
                                    'module' => $module_index,
                                    'lesson' => $lesson_index + 1
                                ]),
                                get_string('nextlesson', 'local_aicourse'),
                                ['class' => 'btn btn-primary']
                            );
                            $has_next = true;
                        } else if ($module_index < count($content['modules']) - 1) {
                            // Go to first lesson of next module
                            echo html_writer::link(
                                new moodle_url('/local/aicourse/view.php', [
                                    'id' => $course_id,
                                    'module' => $module_index + 1,
                                    'lesson' => 0
                                ]),
                                get_string('nextlesson', 'local_aicourse'),
                                ['class' => 'btn btn-primary']
                            );
                            $has_next = true;
                        }
                        
                        if (!$has_next) {
                            // Course complete - show certificate link
                            echo html_writer::link(
                                new moodle_url('/local/aicourse/certificate.php', ['id' => $course_id]),
                                get_string('getcertificate', 'local_aicourse'),
                                ['class' => 'btn btn-success']
                            );
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Course Overview (no specific lesson selected) -->
            <div class="card">
                <div class="card-body">
                    <h1 class="h3 mb-4"><?php echo s($course->fullname); ?></h1>
                    <p class="lead"><?php echo s($content['course_description'] ?? $course->summary); ?></p>
                    
                    <div class="alert alert-info">
                        <strong><?php echo get_string('selectlesson', 'local_aicourse'); ?></strong>
                        <?php echo get_string('selectlesson_desc', 'local_aicourse'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
