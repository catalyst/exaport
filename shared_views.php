<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once __DIR__.'/inc.php';

use block_exaport\globals as g;

$courseid = required_param('courseid', PARAM_INT);
$sort = optional_param('sort', 'user', PARAM_TEXT);
$access = optional_param('access', 0, PARAM_TEXT);
$userid = optional_param('userid', 0, PARAM_INT); // for excomp to only show views of one user

require_login($courseid);

$context = context_system::instance();
require_capability('block/exaport:use', $context);

$url = '/blocks/exaport/shared_views.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

$parsedsort = block_exaport_parse_sort($sort, array('course', 'user', 'view', 'timemodified'));
if ($parsedsort[0] == 'timemodified') {
	$sql_sort = " ORDER BY v.timemodified DESC, v.name, u.lastname, u.firstname";
	$parsedsort[1] = 'desc';
	if (strcmp($CFG->dbtype, "sqlsrv") == 0) {
		$sql_sort = " ORDER BY v.timemodified DESC, cast(v.name AS varchar(max)), u.lastname, u.firstname";
	}
} elseif ($parsedsort[0] == 'view') {
	$sql_sort = " ORDER BY v.name, u.lastname, u.firstname";
	if (strcmp($CFG->dbtype, "sqlsrv") == 0) {
		$sql_sort = " ORDER BY cast(v.name AS varchar(max)), u.lastname, u.firstname";
	}
} else {
	$sql_sort = " ORDER BY u.lastname, u.firstname, v.name";
	if (strcmp($CFG->dbtype, "sqlsrv") == 0) {
		$sql_sort = " ORDER BY u.lastname, u.firstname, cast(v.name AS varchar(max))";
	}
}


block_exaport_print_header("shared_views");

$strheader = get_string("shared_views", "block_exaport");

echo "<div class='block_eportfolio_center'>\n";

// Views for user groups
$userviews = block_exaport_get_group_share_views($USER->id);

$view_columns = g::$DB->get_column_names_prefixed('block_exaportview', 'v');
$views = $DB->get_records_sql("
	SELECT
		{$view_columns}, u.firstname, u.lastname, u.picture
		, COUNT(DISTINCT vshar_total.userid) AS cnt_shared_users, COUNT(DISTINCT vgshar.groupid) AS cnt_shared_groups
	FROM {user} u
	JOIN {block_exaportview} v ON u.id=v.userid
	LEFT JOIN {block_exaportviewshar} vshar ON v.id=vshar.viewid AND vshar.userid=?
	LEFT JOIN {block_exaportviewgroupshar} vgshar ON v.id=vgshar.viewid
	LEFT JOIN {block_exaportviewshar} vshar_total ON v.id=vshar_total.viewid
	WHERE
		(
			vshar.userid IS NOT NULL -- shared to me
			OR ".(block_exaport_shareall_enabled() ? 'v.shareall=1' : '1!=1')." -- shared to all, if enabled
			OR v.id IN (".join(',', array_merge([0], array_keys($userviews))).") -- Add group shareing views
	  	)
		AND v.userid!=? -- don't show my own views
		AND u.deleted = 0
		".($userid ? " AND v.userid=".$userid : '')." -- user filter
	GROUP BY
		{$view_columns}, u.firstname, u.lastname, u.picture
	$sql_sort", [$USER->id, $USER->id]);

function exaport_search_views($views, $column, $value) {
	$viewsFound = array();
	foreach ($views as $view) {
		if ($view->{$column} == $value) {
			$view->found = true;
			$viewsFound[] = $view;
		}
	}

	return $viewsFound;
}

function exaport_print_views($views, $parsedsort) {
	global $CFG, $courseid, $COURSE, $OUTPUT, $DB;

	$courses = exaport_get_shareable_courses_with_users('shared_views');
	$sort = $parsedsort[0];

	$mainViewGroups = array(
		'thiscourse' => array(),
		'othercourses' => array(),
	);

	if (isset($courses[$COURSE->id])) {
		$userIdsInThisCourse = array_keys($courses[$COURSE->id]->users);

		foreach ($views as $view) {
			if (in_array($view->userid, $userIdsInThisCourse)) {
				$mainViewGroups['thiscourse'][] = $view;
			} else {
				$mainViewGroups['othercourses'][] = $view;
			}
		}
	} else {
		$mainViewGroups['othercourses'] = $views;
	}

	if ($courses) {
		echo '<span style="padding-right: 20px;">'.get_string('course').': <select id="block-exaport-courses" url="shared_views.php?courseid=%courseid%&sort='.$sort.'">';

		// print empty line, if course is not in list
		if (!isset($courses[$COURSE->id])) {
			echo '<option/>';
		}

		foreach ($courses as $c) {
			echo '<option value="'.$c->id.'"'.($c->id == $COURSE->id ? ' selected="selected"' : '').'>'.$c->fullname.'</option>';
		}
		echo '</select></span>';
	}

	// print
	if ($views) {
		echo get_string('sortby').': ';
		echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=user\"".
			($sort == 'user' ? ' style="font-weight: bold;"' : '').">".get_string('user')."</a> | ";
		echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=view\"".
			($sort == 'view' ? ' style="font-weight: bold;"' : '').">".get_string('view', 'block_exaport')."</a> | ";
		echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=timemodified\"".
			($sort == 'timemodified' ? ' style="font-weight: bold;"' : '').">".get_string('date', 'block_exaport')."</a> ";
		echo '</div>';
	}

	foreach ($mainViewGroups as $mainViewGroupId => $mainViewGroup) {
		if (empty($mainViewGroup) && ($mainViewGroupId != 'thiscourse')) {
			// don't print if no views
			continue;
		}

		// header
		echo '<h2>'.get_string($mainViewGroupId, 'block_exaport').'</h2>';

		if (empty($mainViewGroup)) {
			// print for this course only
			echo get_string("nothingshared", "block_exaport");
			continue;
		}

		if ($sort == 'user') {

			// group by user
			$viewsByUser = array();

			foreach ($mainViewGroup as $view) {
				if (!isset($viewsByUser[$view->userid])) {
					$viewsByUser[$view->userid] = array(
						'user' => $DB->get_record('user', array("id" => $view->userid)),
						'views' => array(),
					);
				}

				$viewsByUser[$view->userid]['views'][] = $view;
			}

			foreach ($viewsByUser as $item) {
				$curuser = $item['user'];

				$table = new html_table();
				$table->width = "100%";
				$table->size = array('50%', '25%', '25%');
				$table->head = array(
					'view' => block_exaport_get_string('view'),
					'timemodified' => block_exaport_get_string("date"),
					'sharedwith' => block_exaport_get_string("sharedwith"),
				);
				$table->data = array();

				foreach ($item['views'] as $view) {
					$linktoview = "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_view.php?courseid=$courseid&amp;access=id/{$view->userid}-{$view->id}\">".
						format_string($view->name)."</a>";
					$table->data[] = array(
						$linktoview,
						userdate($view->timemodified),
						block_exaport_get_shared_with_text($view),
					);
				}

				echo '<div class="view-group">';
				echo '<div class="header view-group-header" style="align: right">';
				echo '<span class="view-group-pic">'.$OUTPUT->user_picture($curuser, array('link' => false)).'</span>';
				echo '<span class="view-group-title">'.fullname($curuser).' ('.count($item['views']).') </span>';
				echo '</div>';

				echo '<div class="view-group-content">';
				echo html_writer::table($table);
				echo '</div>';

				echo '</div>';
			}
		} else {
			$table = new html_table();
			$table->width = "100%";
			$table->size = array('1%', '25%', '25%', '25%', '24%');
			$table->head = array(
				'userpic' => '',
				'user' => get_string('user'),
				'view' => block_exaport_get_string('view'),
				'timemodified' => block_exaport_get_string("date"),
				'sharedwith' => block_exaport_get_string("sharedwith"),
			);
			$table->data = array();

			foreach ($mainViewGroup as $view) {
				$curuser = $DB->get_record('user', array("id" => $view->userid));
				$table->data[] = array(
					$OUTPUT->user_picture($curuser, array("courseid" => $courseid)),
					fullname($curuser),
					"<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_view.php?courseid=$courseid&amp;access=id/{$view->userid}-{$view->id}\">".
					format_string($view->name)."</a>",
					userdate($view->timemodified),
					block_exaport_get_shared_with_text($view),
				);
			}

			$sorticon = $parsedsort[1].'.png';
			$table->head[$parsedsort[0]] .= " <img src=\"pix/$sorticon\" alt='".get_string("updownarrow", "block_exaport")."' />";

			echo html_writer::table($table);
		}
	}
}

echo '<div style="padding-bottom: 20px;">';

if (!$views) {
	echo get_string("nothingshared", "block_exaport");
} else {
	exaport_print_views($views, $parsedsort);
}

echo "</div>";
echo block_exaport_wrapperdivend();
echo block_exaport_print_footer();


function block_exaport_get_shared_with_text($view) {
	$shared = "";
	if ($view->shareall == 1) {
		$shared = block_exaport_get_string('sharedwith_shareall');
	} elseif ($view->cnt_shared_groups) {
		$shared = block_exaport_get_string('sharedwith_group');
	} elseif ($view->cnt_shared_users > 1) {
		$shared = block_exaport_get_string('sharedwith_user_cnt', $view->cnt_shared_users);
	} elseif ($view->cnt_shared_users) {
		$shared = block_exaport_get_string('sharedwith_onlyme');
	}

	return $shared;
}
