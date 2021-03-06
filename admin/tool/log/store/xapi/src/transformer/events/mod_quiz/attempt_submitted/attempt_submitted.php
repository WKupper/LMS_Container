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

namespace src\transformer\events\mod_quiz\attempt_submitted;

defined('MOODLE_INTERNAL') || die();

use src\transformer\utils as utils;

function get_status_verb($res, $lang) {
    return (
        ($res['success'] == 1) ? 
        [
            'id' => 'http://adlnet.gov/expapi/verbs/passed',
            'display' => [
                $lang => 'passed'
            ]
        ] :
        [
            'id' => 'http://adlnet.gov/expapi/verbs/failed',
            'display' => [
                $lang => 'failed'
            ]
        ]
    );
}

function attempt_submitted(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->relateduserid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $attempt = $repo->read_record_by_id('quiz_attempts', $event->objectid);
    $coursemodule = $repo->read_record_by_id('course_modules', $event->contextinstanceid);
    $quiz = $repo->read_record_by_id('quiz', $attempt->quiz);

    $gradeitem = $repo->read_record('grade_items', [
        'itemmodule' => 'quiz',
        'iteminstance' => $quiz->id,
    ]);

    // attemptgrade holds the grade for the quiz across all attempts, 
    // not necessarily the grade for this quiz attempt
    $attemptgrade = $repo->read_record('grade_grades', [
        'itemid' => $gradeitem->id,
        'userid' => $event->relateduserid
    ]);


    $lang = utils\get_course_lang($course);
    $result = utils\get_attempt_result($config, $attempt, $quiz, $gradeitem, $attemptgrade);

    return [
        [
            'actor' => utils\get_user($config, $user),
            'verb' => get_status_verb($result, $lang),
            'object' => utils\get_activity\course_quiz($config, $course, $event->contextinstanceid),
            'timestamp' => utils\get_event_timestamp($event),
            'result' => $result,
            'context' => utils\get_activity\netc_context($config, $event, $course)
        ],
        [
            'actor' => utils\get_user($config, $user),
            'verb' => [
                'id' => 'http://adlnet.gov/expapi/verbs/completed',
                'display' => [
                    $lang => 'completed'
                ],
            ],
            'object' => utils\get_activity\course_quiz($config, $course, $event->contextinstanceid),
            'timestamp' => utils\get_event_timestamp($event),
            'context' => utils\get_activity\netc_context($config, $event, $course)
        ],
        [
            'actor' => utils\get_user($config, $user),
            'verb' => [
                'id' => 'http://adlnet.gov/expapi/verbs/terminated',
                'display' => [
                    $lang => 'terminated'
                ]
            ],
            'object' => utils\get_activity\course_quiz($config, $course, $event->contextinstanceid),
            'timestamp' => utils\get_event_timestamp($event),
            'result' => [
                'completion' => $result['completion'],
                'duration' => $result['duration'],
                'success' => $result['success']
            ],
            'context' => utils\get_activity\netc_context($config, $event, $course)
        ],
    ];
}
