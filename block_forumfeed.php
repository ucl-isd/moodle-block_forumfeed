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
        // call get posts function.
        $data = '';
        foreach($posts as $post) {
            $data .= $this->dummy_post($post);
        }
        return $this->dummy_post($post);;
    }

    public function dummy_post($data): string {
        global $OUTPUT;
        $template = new stdClass();
        $template->course = "Course name";
        $template->forum = "Forum name";
        $template->title = "Post title";
        $template->url = "forum post url";
        $template->date = "24/03/73";

        $template->username = "Brian emo";
        $template->img = "foo";
        $template->role = "teacher";


        return $OUTPUT->render_from_template('block_forumfeed/post', $template);
    }
}
