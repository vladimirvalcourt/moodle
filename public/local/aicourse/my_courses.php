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
 * Learner dashboard - My AI Courses
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/aicourse/lib.php');

// Require login
require_login();

// Set up page
$PAGE->set_url('/local/aicourse/my_courses.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('myaicourses', 'local_aicourse'));
$PAGE->set_heading(get_string('myaicourses', 'local_aicourse'));
$PAGE->navbar->add(get_string('mycourses'), new moodle_url('/my/'));
$PAGE->navbar->add(get_string('myaicourses', 'local_aicourse'));

// Get user's enrolled AI courses
$enrolled_courses = local_aicourse_get_user_enrolled_courses($USER->id);

// Get course progress for each
$courses_with_progress = [];
foreach ($enrolled_courses as $course) {
    $progress_tracker = new \local_aicourse\services\progress_tracker($USER->id, $course->id);
    $stats = $progress_tracker->get_progress_stats();
    $next_lesson = $progress_tracker->get_next_lesson();
    
    // Generate learning path for this course
    try {
        $path_generator = new \local_aicourse\services\learning_path_generator($USER->id, $course->id);
        $learning_path = $path_generator->generate_path();
    } catch (Exception $e) {
        $learning_path = null;
    }
    
    $courses_with_progress[] = [
        'course' => $course,
        'progress' => $stats,
        'next_lesson' => $next_lesson,
        'learning_path' => $learning_path
    ];
}

// Get personalized recommendations
$recommendation_engine = new \local_aicourse\services\recommendation_engine($USER->id);
$recommendations = $recommendation_engine->get_recommendations(3);
$trending = $recommendation_engine->get_trending_courses(3);

// Output page
echo $OUTPUT->header();
?>

<div class="aicourse-dashboard">
    <div class="row mb-4">
        <div class="col-12">
            <h2><?php echo get_string('welcomeback', 'local_aicourse', fullname($USER)); ?></h2>
            <p class="text-muted"><?php echo get_string('dashboard_desc', 'local_aicourse'); ?></p>
        </div>
    </div>
    
    <?php if (empty($courses_with_progress)): ?>
    <!-- Empty State -->
    <div class="alert alert-info">
        <h4><i class="fa fa-book"></i> <?php echo get_string('nocoursesyet', 'local_aicourse'); ?></h4>
        <p><?php echo get_string('nocoursesyet_desc', 'local_aicourse'); ?></p>
        <a href="<?php echo new moodle_url('/course/index.php'); ?>" class="btn btn-primary mt-2">
            <?php echo get_string('browseallcourses', 'local_aicourse'); ?>
        </a>
    </div>
    <?php else: ?>
    <!-- Course Grid -->
    <div class="row">
        <?php foreach ($courses_with_progress as $item): 
            $course = $item['course'];
            $progress = $item['progress'];
            $next = $item['next_lesson'];
        ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <a href="<?php echo new moodle_url('/local/aicourse/view.php', ['id' => $course->id]); ?>">
                            <?php echo format_string($course->fullname); ?>
                        </a>
                    </h5>
                    
                    <p class="card-text text-muted small">
                        <?php echo s($course->summary ?? get_string('aicourse', 'local_aicourse')); ?>
                    </p>
                    
                    <!-- Progress Bar -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small><?php echo get_string('progress', 'local_aicourse'); ?></small>
                            <small class="font-weight-bold"><?php echo round($progress['percentage']); ?>%</small>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $progress['percentage']; ?>%"
                                 aria-valuenow="<?php echo $progress['percentage']; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        <small class="text-muted">
                            <?php echo $progress['completed_lessons']; ?> / <?php echo $progress['total_lessons']; ?> 
                            <?php echo get_string('lessons', 'local_aicourse'); ?>
                        </small>
                    </div>
                    
                    <!-- Next Lesson CTA -->
                    <?php if ($next && !$progress['is_complete']): ?>
                    <div class="alert alert-light border">
                        <small class="text-muted d-block mb-1">
                            <?php echo get_string('continuewith', 'local_aicourse'); ?>
                        </small>
                        <strong><?php echo s($next['title']); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <?php if ($progress['is_complete']): ?>
                        <span class="badge badge-success">
                            <i class="fa fa-check"></i> <?php echo get_string('completed', 'local_aicourse'); ?>
                        </span>
                        <a href="<?php echo new moodle_url('/local/aicourse/certificate.php', ['id' => $course->id]); ?>" 
                           class="btn btn-sm btn-success">
                            <i class="fa fa-certificate"></i> <?php echo get_string('viewcertificate', 'local_aicourse'); ?>
                        </a>
                        <?php else: ?>
                        <span class="badge badge-info">
                            <?php echo get_string('inprogress', 'local_aicourse'); ?>
                        </span>
                        <a href="<?php echo new moodle_url('/local/aicourse/view.php', [
                            'id' => $course->id,
                            'module' => $next ? $next['module_index'] : 0,
                            'lesson' => $next ? $next['lesson_index'] : 0
                        ]); ?>" class="btn btn-sm btn-primary">
                            <?php echo $next ? get_string('continuelearning', 'local_aicourse') : get_string('startlearning', 'local_aicourse'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Recommendations Section -->
    <?php if (!empty($recommendations)): ?>
    <div class="row mt-5">
        <div class="col-12">
            <h3><i class="fa fa-star text-warning"></i> <?php echo get_string('recommendedforyou', 'local_aicourse'); ?></h3>
        </div>
        <?php foreach ($recommendations as $rec): 
            $course = $rec['course'];
        ?>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <a href="<?php echo new moodle_url('/course/view.php', ['id' => $course->id]); ?>">
                            <?php echo format_string($course->fullname); ?>
                        </a>
                    </h5>
                    <p class="card-text small text-muted">
                        <?php echo s($course->summary); ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-success">
                            <i class="fa fa-thumbs-up"></i> <?php echo round($rec['score']); ?>% match
                        </small>
                        <a href="<?php echo new moodle_url('/course/view.php', ['id' => $course->id]); ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <?php echo get_string('viewcourse', 'local_aicourse'); ?>
                        </a>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <?php echo $rec['reason']; ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Trending Courses -->
    <?php if (!empty($trending)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <h3><i class="fa fa-fire text-danger"></i> <?php echo get_string('trendingcourses', 'local_aicourse'); ?></h3>
        </div>
        <?php foreach ($trending as $course): ?>
        <div class="col-md-4 mb-3">
            <div class="card border-warning">
                <div class="card-body">
                    <h5 class="card-title">
                        <a href="<?php echo new moodle_url('/course/view.php', ['id' => $course->id]); ?>">
                            <?php echo format_string($course->fullname); ?>
                        </a>
                    </h5>
                    <p class="card-text small text-muted">
                        <?php echo s($course->summary); ?>
                    </p>
                    <small class="text-muted">
                        <i class="fa fa-users"></i> <?php echo $course->enrollment_count ?? 0; ?> enrolled
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
