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
            'site-index' => false,
            'course-view' => false,
            'mod' => false,
            'my' => true,
        ];
    }

    public function dummy_posts() {
        global $CFG, $DB, $USER, $OUTPUT;
        require_once $CFG->dirroot . '/mod/forum/lib.php';
        $template = new stdClass();

        if ($courses = enrol_get_users_courses($USER->id, true, 'id, fullname, shortname')) {
            // convert courses ids into a string separated by commas.
            $courseids = array_map(function($item) {
                return $item->id;
            }, $courses);

            $coursesstring = implode(', ', $courseids);
            $seven_days_ago = time() - (7 * DAYSECS);

            // Most popular discussion this week.  Find the discussion with the
            // highest number of replies this week.
            list($incourses, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $sql = "SELECT fd.id AS discussionid, fd.forum AS forumid,
                           COUNT(p.id) AS poststhisweek
                      FROM {forum} f
                           JOIN {course} c ON f.course = c.id
                           JOIN {forum_discussions} fd ON f.id = fd.forum
                           JOIN {forum_posts} p ON fd.id = p.discussion
                     WHERE f.course {$incourses} AND p.modified > $seven_days_ago
                  GROUP BY fd.id, fd.forum
                  ORDER BY COUNT(p.id) DESC
                     LIMIT 1";
            $record = $DB->get_record_sql($sql, $params);
            $poststhisweek = $record->poststhisweek;

            // Fetch the data for the most replied to discussion this week.
            $sql = "SELECT p.*, c.id AS 'courseid', c.fullname AS 'coursename',
                           f.name AS 'forum', fd.name AS 'discussions'
                      FROM {forum} f
                           JOIN {course} c ON f.course = c.id
                           JOIN {forum_discussions} fd ON f.id = fd.forum
                           JOIN {forum_posts} p ON fd.id = p.discussion
                     WHERE fd.id = {$record->discussionid} AND parent = 0";
            $record = $DB->get_record_sql($sql);
            $record->poststhisweek = $poststhisweek;
            $template->post[] = $this->dummy_post($record);

            // Most recent posts.
            $sql = "select p.*, c.id as 'courseid', c.fullname as 'coursename', f.name as 'forum', fd.name as 'discussions'
                    from {forum} f
                    join {course} c on f.course = c.id
                    join {forum_discussions} fd on f.id = fd.forum
                    join {forum_posts} p on fd.id = p.discussion
                        where f.course in (" . $coursesstring . ") and
                    p.modified > " . $seven_days_ago . " and p.userid != " . $USER->id . "
                    order by p.modified desc
                    limit 3";
            $posts = $DB->get_records_sql($sql);

            // call get posts function.
            foreach($posts as $post) {
                $template->post[] = $this->dummy_post($post);
            }
        }

        return $OUTPUT->render_from_template('block_forumfeed/posts', $template);
    }

    public function dummy_post($data)
    {
        global $DB, $PAGE;

        $url              = new moodle_url(
          '/mod/forum/discuss.php',
          ['d' => $data->discussion],
          'p' . $data->id
        );
        $template         = new stdClass();
        $template->course = $data->coursename;
        $template->forum  = $data->forum;
        $template->title  = $data->subject;
        $template->url    = $url->out(false);
        $template->date   = date('g:ia · jS F', $data->modified);

        // For most popular discussion.
        if (property_exists($data, 'poststhisweek')) {
            $template->poststhisweek = $data->poststhisweek;
        }

        $user         = $DB->get_record('user', ['id' => $data->userid]);
        $user_picture = new user_picture($user);
        $user_picture->size
                      = 100; // Size can be adjusted to '100' for a small icon, or 'f2' for full size, etc.
        $image_url    = $user_picture->get_url($PAGE);
        $roles        = get_user_roles_in_course(
          $user->id,
          $data->courseid
        );
        $roles = explode(',', $roles);

        $rolesarray = [];
        foreach($roles as $role) {
            if (preg_match('/<a[^>]*>(.*?)<\/a>/', $role, $matches)) {
                $rolename = $matches[1];
                if ($rolename != 'Student') {
                    $rolesarray[] = $rolename;
                }
            }
        }

        $rolename = implode(', ', $rolesarray);

        $template->username = $user->firstname . ' ' . $user->lastname;
        $template->img      = $image_url;
        $template->role     = $rolename;

        return $template;
        // $OUTPUT->render_from_template('block_forumfeed/post', $template);
    }
}
