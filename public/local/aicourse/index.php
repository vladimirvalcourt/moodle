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
 * Main entry page for AI Course Generator
 *
 * @package    local_aicourse
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/aicourse/lib.php');

// Require login
require_login();

// Check capability
$context = context_system::instance();
require_capability('local/aicourse:generate', $context);

// Set up page
$PAGE->set_url('/local/aicourse/index.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_aicourse'));
$PAGE->set_heading(get_string('pluginname', 'local_aicourse'));

// Handle form submission
$action = optional_param('action', '', PARAM_ALPHA);
$draft_id = optional_param('draft', 0, PARAM_INT);

if ($action === 'start_generation' && confirm_sesskey()) {
    // Validate rate limit
    $limiter = new \local_aicourse\services\rate_limiter();
    if (!$limiter->can_generate($USER->id)) {
        \core\notification::error(get_string('rate_limit_reached', 'local_aicourse'));
        redirect(new moodle_url('/local/aicourse/index.php'));
    }
    
    // Get parameters
    $params = [
        'topic' => required_param('topic', PARAM_TEXT),
        'audience' => required_param('audience', PARAM_ALPHA),
        'duration' => required_param('duration', PARAM_INT),
        'objectives' => optional_param_array('objectives', [], PARAM_TEXT),
    ];
    
    try {
        // Start generation in background task
        $task = new \local_aicourse\task\generate_course_task();
        $task->set_custom_data([
            'user_id' => $USER->id,
            'params' => $params,
            'provider' => null
        ]);
        \core\task\manager::queue_adhoc_task($task);
        
        \core\notification::success(get_string('generation_started', 'local_aicourse'));
        redirect(new moodle_url('/local/aicourse/index.php'));
        
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// Get user's drafts
$drafts = local_aicourse_get_user_drafts($USER->id);
$stats = local_aicourse_get_user_stats($USER->id);

// Output page
echo $OUTPUT->header();
?>

<div class="local-aicourse-container">
    <div class="row">
        <!-- Left Column: Create New Course -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h2><?php echo get_string('create_with_ai', 'local_aicourse'); ?></h2>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo new moodle_url('/local/aicourse/index.php'); ?>">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
                        <input type="hidden" name="action" value="start_generation" />
                        
                        <div class="form-group">
                            <label for="topic"><?php echo get_string('course_topic', 'local_aicourse'); ?></label>
                            <input type="text" class="form-control" id="topic" name="topic" required 
                                   placeholder="e.g., Introduction to Python Programming" />
                        </div>
                        
                        <div class="form-group">
                            <label for="audience"><?php echo get_string('target_audience', 'local_aicourse'); ?></label>
                            <select class="form-control" id="audience" name="audience" required>
                                <option value="beginner"><?php echo get_string('audience_beginner', 'local_aicourse'); ?></option>
                                <option value="intermediate"><?php echo get_string('audience_intermediate', 'local_aicourse'); ?></option>
                                <option value="advanced"><?php echo get_string('audience_advanced', 'local_aicourse'); ?></option>
                                <option value="mixed"><?php echo get_string('audience_mixed', 'local_aicourse'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration"><?php echo get_string('estimated_duration', 'local_aicourse'); ?></label>
                            <input type="number" class="form-control" id="duration" name="duration" 
                                   value="480" min="60" max="2400" step="60" />
                            <small class="form-text text-muted">In minutes (e.g., 480 = 8 hours)</small>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo get_string('learning_objectives', 'local_aicourse'); ?></label>
                            <div id="objectives-container">
                                <input type="text" class="form-control mb-2" name="objectives[]" 
                                       placeholder="Enter a learning objective" />
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addObjective()">
                                + Add Another Objective
                            </button>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?php echo get_string('generate_course', 'local_aicourse'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Stats & Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Your Statistics</h3>
                </div>
                <div class="card-body">
                    <p><strong>Total Drafts:</strong> <?php echo $stats['total_drafts']; ?></p>
                    <p><strong>Today's Generations:</strong> <?php echo $stats['today_generations']; ?></p>
                    <p><strong>Published Courses:</strong> <?php echo $stats['published_courses']; ?></p>
                    <p><strong>Remaining Today:</strong> 
                        <?php 
                        $limiter = new \local_aicourse\services\rate_limiter();
                        echo $limiter->get_remaining($USER->id); 
                        ?>
                    </p>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong>Tip:</strong> Course generation takes 1-2 minutes. You'll be notified when it's ready for review.
            </div>
        </div>
    </div>
    
    <!-- Drafts List -->
    <?php if (!empty($drafts)): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h2>Your Course Drafts</h2>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($drafts as $draft): ?>
                    <tr>
                        <td><?php echo s($draft->course_title); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $draft->status === 'published' ? 'success' : 
                                    ($draft->status === 'review' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($draft->status); ?>
                            </span>
                        </td>
                        <td><?php echo userdate($draft->timecreated); ?></td>
                        <td>
                            <?php if ($draft->status === 'review'): ?>
                            <a href="<?php echo new moodle_url('/local/aicourse/review.php', ['id' => $draft->id]); ?>" 
                               class="btn btn-sm btn-primary">Review</a>
                            <?php elseif ($draft->status === 'published'): ?>
                            <a href="<?php echo new moodle_url('/course/view.php', ['id' => $draft->published_course_id]); ?>" 
                               class="btn btn-sm btn-success">View Course</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function addObjective() {
    const container = document.getElementById('objectives-container');
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control mb-2';
    input.name = 'objectives[]';
    input.placeholder = 'Enter a learning objective';
    container.appendChild(input);
}
</script>

<?php
echo $OUTPUT->footer();
