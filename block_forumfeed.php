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

use core_course\external\course_summary_exporter;

/**
 * Block forumfeed is defined here.
 *
 * @package     block_forumfeed
 * @copyright   2024 Leon Stringer <leon.stringer@ucl.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_forumfeed extends block_base {
    /**
     * @var int Epoch time seven days ago.
     */
    private int $sevendaysago;

    /**
     * Initializes class member variables.
     */
    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_forumfeed');

        $this->sevendaysago = time() - (7 * DAYSECS);
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return [
            'my' => true,
        ];
    }


    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {
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
        $this->content->items = [];
        $this->content->icons = [];
        $this->content->footer = '';
        $this->content->text = $this->forum_posts();
        return $this->content;
    }

    /**
     * Returns html templated forum posts as a string.
     *
     */
    public function forum_posts(): string {
        global $CFG, $DB, $USER, $OUTPUT;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $template = new stdClass();

        if ($courses = enrol_get_users_courses($USER->id, true, 'id')) {
            // Convert courses ids into a string separated by commas.
            $courseids = array_map(function($item) {
                return $item->id;
            }, $courses);

            list($incourses, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $now = \core\di::get(\core\clock::class)->time();

            // Get the discussions visible to this user.
            $sql = "SELECT fd.id AS discussionid, fd.groupid, c.id AS courseid,
                           f.id as forumid, cm.id AS cmid
                      FROM {forum} f
                      JOIN {course} c ON f.course = c.id
                      JOIN {forum_discussions} fd ON f.id = fd.forum
                      JOIN {course_modules} cm ON cm.instance = f.id
                      JOIN {modules} m ON cm.module = m.id
                           AND m.name = 'forum'
                     WHERE f.course $incourses
                           AND fd.timemodified > $this->sevendaysago
                           AND fd.timestart <= $now
                           AND (fd.timeend = 0 OR fd.timeend > $now)";

            // Filter out discussions where either the forum is not visible to
            // the current user or the discussion is not visible due to group
            // restrictions.
            $visiblediscussions = array_filter(
                    $DB->get_records_sql($sql, $params),
                    function ($item) {
                        global $USER;
                        $cm = get_fast_modinfo($item->courseid, $USER->id)->get_cm($item->cmid);
                        $context = context_module::instance($cm->id);

                        return $cm->uservisible && (($item->groupid == -1) ||
                                groups_is_member($item->groupid) ||
                                has_capability('moodle/site:accessallgroups',
                                        $context));
                    }
            );

            if (count($visiblediscussions) === 0) {
                return '';
            }

            // Convert visible discussions into array of discussion IDs.
            $visiblediscussions = array_map(function($item) {
                return $item->discussionid;
            }, $visiblediscussions);

            // Most popular post.
            if ($popular = $this->popular_post($visiblediscussions)) {
                $template->post[] = $this->forum_post($popular);
            }

            // Recent posts.
            $posts = $this->recent_posts($visiblediscussions);
            foreach ($posts as $post) {
                $template->post[] = $this->forum_post($post);
            }
        }

        return $OUTPUT->render_from_template('block_forumfeed/posts', $template);
    }

    /**
     * Return sql result for recent posts.
     *
     * @param array $visiblediscussions Array of discussion IDs visible to
     * this user, for example, [1, 2, 3].
     */
    public function popular_post(array $visiblediscussions): ?stdClass {
        global $DB;

        list($indiscussions, $params) = $DB->get_in_or_equal($visiblediscussions, SQL_PARAMS_NAMED);

        // Most popular discussion this week.  Find the discussion with the
        // highest number of replies this week.
        $sql = "SELECT fd.id AS discussionid, fd.forum AS forumid,
                       COUNT(p.id) AS poststhisweek
                  FROM {forum} f
                       JOIN {course} c ON f.course = c.id
                       JOIN {forum_discussions} fd ON f.id = fd.forum
                       JOIN {forum_posts} p ON fd.id = p.discussion
                 WHERE p.modified > {$this->sevendaysago}
                       AND fd.id {$indiscussions}
              GROUP BY fd.id, fd.forum
              ORDER BY COUNT(p.id) DESC
                 LIMIT 1";
        $record = $DB->get_record_sql($sql, $params);

        // If popular discussion, get data for template.
        if ($record) {
            $poststhisweek = $record->poststhisweek; // Count of replies this week.

            // Fetch the data for the most popular discussion this week.
            $sql = "SELECT p.*, c.id AS courseid, c.fullname AS coursename,
                            f.name AS forum, fd.name AS discussions
                        FROM {forum} f
                            JOIN {course} c ON f.course = c.id
                            JOIN {forum_discussions} fd ON f.id = fd.forum
                            JOIN {forum_posts} p ON fd.id = p.discussion
                        WHERE fd.id = {$record->discussionid} AND parent = 0";
            $record = $DB->get_record_sql($sql);
            $record->poststhisweek = $poststhisweek;
            return $record;
        }

        // No popular discussion.
        return null;
    }

    /**
     * Return sql results for recent posts.
     *
     * @param array $visiblediscussions Array of discussion IDs visible to
     * this user, for example, [1, 2, 3].
     */
    public function recent_posts(array $visiblediscussions): array {
        global $DB, $USER;

        list($indiscussions, $params) = $DB->get_in_or_equal($visiblediscussions, SQL_PARAMS_NAMED);

        // Most recent posts.
        $sql = "SELECT
                p.*,
                c.id AS courseid,
                c.fullname AS coursename,
                f.name AS forum,
                fd.name AS discussions
                FROM {forum} f
                    JOIN {course} c ON f.course = c.id
                    JOIN {forum_discussions} fd ON f.id = fd.forum
                    JOIN {forum_posts} p ON fd.id = p.discussion
                WHERE p.modified > {$this->sevendaysago}
                    AND p.userid != " . $USER->id . "
                    AND fd.id {$indiscussions}
                ORDER BY p.modified DESC
                LIMIT 6";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return template data for a single forum post.
     *
     * @param stdClass $data
     */
    public function forum_post($data): stdClass {
        global $DB;

        $template = new stdClass();
        // Course.
        $template->course = $data->coursename;
        $course = new stdClass();
        $course->id = $data->courseid;
        $template->courseimage = course_summary_exporter::get_course_image($course);

        // Forum.
        $template->forum = $data->forum;

        // Post.
        $template->title = str_replace("Re: ", "", $data->subject);
        $template->date = $this->human_readable_time($data->modified);

        // URL with # appended.
        $template->url = new moodle_url('/mod/forum/discuss.php',
            ['d' => $data->discussion],
            'p' . $data->id
        );

        // Post author.
        $user = core_user::get_user($data->userid);
        $userpicture = new user_picture($user);
        $userpicture->size = 100;
        $template->img = $userpicture->get_url($this->page)->out(false);
        $template->username = fullname($user);

        // Role tag.
        $template->role = $this->user_role($data->courseid, $user);

        // For popular discussion.
        if (property_exists($data, 'poststhisweek')) {
            $template->popular = $data->poststhisweek;
            $template->date = date('g:ia · jS F', $data->modified);
        }

        return $template;
    }

    /**
     * Return user role
     *
     * @param int $courseid
     * @param stdClass $user
     */
    public function user_role(int $courseid, $user): string {
        $roles = get_user_roles_in_course($user->id, $courseid);
        $roles = explode(',', $roles);
        foreach ($roles as $role) {
            if (preg_match('/<a[^>]*>(.*?)<\/a>/', $role, $matches)) {
                $rolename = $matches[1];
                if ($rolename != 'Student') {
                    // Return highest priorty role.
                    return $rolename;
                }
            }
        }
        // No role.
        return '';
    }

    /**
     * Return time ago.
     *
     * @param int $timestamp
     */
    public function human_readable_time($timestamp): string {
        $timeelapsed = time() - $timestamp;

        if ($timeelapsed < MINSECS) {
            return get_string('timejustnow', 'block_forumfeed');
        } else if ($timeelapsed < HOURSECS) {
            return get_string('timem', 'block_forumfeed', round($timeelapsed / MINSECS));
        } else if ($timeelapsed < DAYSECS) {
            return get_string('timeh', 'block_forumfeed', round($timeelapsed / HOURSECS));
        } else {
            return get_string('timed', 'block_forumfeed', round($timeelapsed / DAYSECS));
        }
    }
}
