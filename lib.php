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
 * Cards Format - A topics based format that uses card layout to display the activities/section/topics.
 *
 * @package    course/format
 * @subpackage remuiformat
 * @copyright  2019 Wisdmlabs
 * @version    See the value of '$plugin->version' in version.php.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php'); // For format_base.

define ('REMUI_CARD_FORMAT', 0);
define ('REMUI_LIST_FORMAT', 1);

class format_remuiformat extends format_base {

    /**
     * Creates a new instance of class
     * Please use {@link course_get_format($courseorid)} to get an instance of the format class
     * @param string $format
     * @param int $courseid
     * @return format_remuiformat
     */
    protected function __construct($format, $courseid) {
        global $PAGE;
        if ($courseid === 0) {
            global $COURSE;
            $courseid = $COURSE->id;  // Save lots of global $COURSE as we will never be the site course.
        }
        $this->availablelayouts = array(
            'REMUI_CARD_FORMAT' => array(
                'format' => REMUI_CARD_FORMAT,
                'optionlabel' => 'remuicourseformat_card',
                'supports' => COURSE_DISPLAY_MULTIPAGE,
            ),
            'REMUI_LIST_FORMAT' => array(
                'format' => REMUI_LIST_FORMAT,
                'optionlabel' => 'remuicourseformat_list',
                'supports' => COURSE_DISPLAY_SINGLEPAGE,
            ),

        );
        // Include course format js module.
        $PAGE->requires->js_call_amd('format_remuiformat/format', 'init', array($this->availablelayouts));

        // Pass constants defined for the formats.
        parent::__construct($format, $courseid);
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     * The property (array)testedbrowsers can be used as a parameter for {@link ajaxenabled()}.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Returns the format's settings and gets them if they do not exist.
     * @return array The settings as an array.
     */
    public function get_settings() {
        if (empty($this->settings) == true) {
            $this->settings = $this->get_format_options();
        }
        return $this->settings;
    }

    /**
     * Indicates this format uses sections.
     * @return bool Returns true
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     * Use section name is specified by user. Otherwise use default ("Topic #")
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the topics course format.
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_remuiformat');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return get_string('sectionname', 'format_remuiformat').' '. $section->section;
        }
    }

    /**
     * The URL to use for the specified course (with section)
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Definitions of the additional options that this course format uses for the course.
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            /* Note: Because 'admin_setting_configcolourpicker' in 'settings.php' needs to use a prefixing '#'
            this needs to be stripped off here if it's there for the format's specific colour picker. */
            $courseconfig = get_config('moodlecourse');
            $contextid = context_course::instance($this->courseid);
            $data = new stdClass;
            $draftitemid = file_get_submitted_draft_itemid('remuicourseimage');

            try {
                $data = file_prepare_standard_filemanager(
                    $data,
                    'remuicourseimage',
                    array('accepted_types' => 'images', 'maxfiles' => 1),
                    $contextid,
                    'format_remuiformat',
                    'remuicourseimage_filearea'
                );
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'remuicourseformat' => array(
                    'default' => 1,
                    'type' => PARAM_INT
                ),
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT
                ),
                'remuicourseimage_filemanager' => array(
                    'default' => get_config('format_remuiformat', 'remuicourseimage_filemanager'),
                    'type' => PARAM_CLEANFILE
                ),
                'sectiontitlesummarymaxlength' => array(
                    'default' => get_config('format_remuiformat', 'defaultsectionsummarymaxlength'),
                    'type' => PARAM_INT
                ),
                'remuiteacherdisplay' => array(
                    'default' => 1,
                    'type' => PARAM_INT
                ),
                'remuidefaultsectionview' => array(
                    'default' => 1,
                    'type' => PARAM_INT
                )
            );
        }

        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptionsedit = array(
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'remuicourseformat' => array(
                    'label' => new lang_string('remuicourseformat', 'format_remuiformat'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            REMUI_CARD_FORMAT => new lang_string('remuicourseformat_card', 'format_remuiformat'),
                            REMUI_LIST_FORMAT => new lang_string('remuicourseformat_list', 'format_remuiformat'),
                        )
                    ),
                    'help' => 'remuicourseformat',
                    'help_component' => 'format_remuiformat',
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi'),
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ),
                'remuicourseimage_filemanager' => array(
                    'label' => new lang_string('remuicourseimage', 'format_remuiformat'),
                    'element_type' => 'filemanager',
                    'element_attributes' => array(
                        null,
                        array(
                        'maxfiles' => 1,
                        'accepted_types' => array('image'),
                        )
                    ),
                    'help' => 'remuicourseimage',
                    'help_component' => 'format_remuiformat',
                ),
                'sectiontitlesummarymaxlength' => array(
                    'label' => new lang_string('sectiontitlesummarymaxlength', 'format_remuiformat'),
                    'element_type' => 'text',
                    'element_attributes' => array('size' => 3),
                    'help' => 'sectiontitlesummarymaxlength',
                    'help_component' => 'format_remuiformat'
                ),
                'remuiteacherdisplay' => array(
                    'label' => new lang_string('remuiteacherdisplay', 'format_remuiformat'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            1 => new lang_string('yes'),
                            0 => new lang_string('no')
                        )
                    ),
                    'help' => 'remuiteacherdisplay',
                    'help_component' => 'format_remuiformat'
                ),
                'remuidefaultsectionview' => array(
                    'label' => new lang_string('remuidefaultsectionview', 'format_remuiformat'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            1 => new lang_string('expanded', 'format_remuiformat'),
                            0 => new lang_string('collapsed', 'format_remuiformat')
                        )
                    ),
                    'help' => 'remuidefaultsectionview',
                    'help_component' => 'format_remuiformat'
                )
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     * This function is called from {@link course_edit_form::definition_after_data()}.
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }
        return $elements;
    }

    /**
     * Whether this format allows to delete sections
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name(
        $section,
        $linkifneeded = true,
        $editable = null,
        $edithint = null,
        $editlabel = null
    ) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_remuiformat');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_remuiformat', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }
    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }
    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }
    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'topics' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_topics');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }

    public function update_course_format_options($data, $sectionid = null) {
        if (!empty($data)) {
            // Used optional_param() instead of using $_POST and $_GET.
            $data->remuicourseformat = optional_param('remuicourseformat', null, PARAM_INT);
            $contextid = context_course::instance($this->courseid);
            if (!empty($data->remuicourseimage_filemanager)) {
                file_postupdate_standard_filemanager(
                    $data,
                    'remuicourseimage',
                    array ('accepted_types' => 'images', 'maxfiles' => 1),
                    $contextid,
                    'format_remuiformat',
                    'remuicourseimage_filearea',
                    $data->remuicourseimage_filemanager
                );
            }
        }
        return $this->update_format_options($data);
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    public function edit_form_validation($data, $files, $errors) {
        if (isset($data)) {
            $rformat = $data['remuicourseformat'];
            if (isset($rformat)) {
                foreach ($this->availablelayouts as $key => $value) {
                    if ($rformat == $value['format']) {
                        if ($rformat == 0 && $data['coursedisplay'] != $value['supports']) {
                            $errors['coursedisplay'] = get_string('coursedisplay_error', 'format_remuiformat');
                        }
                    }
                }
            }
        }
        return $errors;
    }

}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_remuiformat_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'remuiformat'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}


function format_remuiformat_pluginfile ($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB;
    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }
    require_login();
    if ($filearea != 'remuicourseimage_filearea') {
        return false;
    }

    $itemid = (int)array_shift($args);
    $fs = get_file_storage();
    $filename = array_pop($args);

    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }
    $file = $fs->get_file($context->id, 'format_remuiformat', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, 0, $options);
}
