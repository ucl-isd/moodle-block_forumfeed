<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block forumfeed is defined here.
 *
 * @package     block_forumfeed
 * @copyright   2024 Leon Stringer <leon.stringer@ucl.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_forumfeed extends block_base {

    /**
     * Initializes class member variables.
     */
    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_forumfeed');
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';


        $this->content->text = $this->dummy_posts();
        return $this->content;
    }

    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {

        // Load user defined title and make sure it's never empty.
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_forumfeed');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return [
            'admin' => false,
            'site-index' => true,
            'course-view' => true,
            'mod' => false,
            'my' => true,
        ];
    }

    public function dummy_posts() {
        global $CFG, $DB, $USER, $OUTPUT;
        require_once $CFG->dirroot . '/mod/forum/lib.php';
        $courses = enrol_get_users_courses($USER->id, true, 'id, fullname, shortname');
        // convert courses ids into a string separated by commas.
        $courseids = array_map(function($item) {
            return $item->id;
        }, $courses);

        $coursesstring = implode(', ', $courseids);
        $seven_days_ago = time() - (7 * 24 * 60 * 60);

        $sql = "select p.*, c.id as 'courseid', c.fullname as 'coursename', f.name as 'forum', fd.name as 'discussions'
                from {forum} f
                join {course} c on f.course = c.id
                join {forum_discussions} fd on f.id = fd.forum
                join {forum_posts} p on fd.id = p.discussion
                where f.course in (" . $coursesstring . ") and
                p.modified > " . $seven_days_ago . "
                order by p.modified desc
                limit 10";
        $posts = $DB->get_records_sql($sql);

        // call get posts function.
        $template = new stdClass();
        foreach($posts as $post) {
            $template->post[] = $this->dummy_post($post);
        }
        return $OUTPUT->render_from_template('block_forumfeed/posts', $template);
    }

    public function dummy_post($data) {
        global $DB, $PAGE;

        $url = new moodle_url('/mod/forum/discuss.php', ['d' => $data->discussion],'p'.$data->id);
        $template = new stdClass();
        $template->course = $data->coursename;
        $template->forum = $data->forum;
        $template->title = $data->subject;
        $template->url = $url->out(false);
        //"4:30pm on 24th Sept"
        $template->date = date('g:ia \o\n jS M', $data->modified);

        $user = $DB->get_record('user', ['id' => $data->userid]);
        $user_picture = new user_picture($user);
        $user_picture->size = 100; // Size can be adjusted to '100' for a small icon, or 'f2' for full size, etc.
        $image_url = $user_picture->get_url($PAGE);
        $roles = get_user_roles_in_course($user->id, $data->courseid);

        $template->username = $user->firstname . ' ' . $user->lastname;
        $template->img = $image_url;
        $template->role = $roles;

        return $template;
        // $OUTPUT->render_from_template('block_forumfeed/post', $template);
    }
}
