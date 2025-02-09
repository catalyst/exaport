<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
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
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

require_once(__DIR__.'/inc.php');

$itemid = optional_param('item_id', -1, PARAM_INT);
$access = optional_param('access', '', PARAM_TEXT);
// sometimes for artifacts with multiple images
$imageindex = optional_param('imindex', '', PARAM_INT);

$ispdf = optional_param('ispdf', 0, PARAM_INT);
$isforpdf = false;
$pdfuserid = 0;
if ($ispdf) {
    $vhash = optional_param('vhash', 0, PARAM_RAW);
    $vid = optional_param('vid', 0, PARAM_INT);
    $pdfuserid = optional_param('uid', 0, PARAM_INT);
    $view = $DB->get_record('block_exaportview', ['id' => $vid]);
    if ($view && $view->hash == $vhash && $pdfuserid > 0) {
        $isforpdf = true;
    }
}

$item = null;

// Thumbnails for BackEnd (editing the view part).
if ($access == '') {
    // echo $access;
    if ($sharable = block_exaport_can_user_access_shared_item($USER->id, $itemid)) {
        // Get thumbnails if item was shared for current user.
        $item = $DB->get_record('block_exaportitem', array('id' => $itemid));
    } else {
        // Get only for self (owner).
        $item = $DB->get_record('block_exaportitem', array('id' => $itemid, 'userid' => $USER->id));
    }
} else {
    // Checking access to item by access to view.
    if (!$view = block_exaport_get_view_from_access($access, $isforpdf, $pdfuserid)) {
        die("view not found");
    }
    $viewid = $view->id;
    $viewownerid = $view->userid;
    $item = $DB->get_record('block_exaportitem', array('id' => $itemid));
    $sharable = block_exaport_can_user_access_shared_item($viewownerid, $itemid);
    if ($viewownerid != $item->userid && !$sharable) {
        throw new moodle_exception('item not found');
    }
}
if (empty($item)) {
    throw new moodle_exception('item not found');
}

// Custom Icon file.
if ($iconfile = block_exaport_get_single_file($item, 'item_iconfile')) {
    send_stored_file($iconfile);
    exit;
}

switch ($item->type) {
    case "file":
        // Thumbnail of file.
        $file = block_exaport_get_item_files($item);
        // Serve file.
        if ($file && ($imageindex || $imageindex === 0  )) {
            $filevalues = array_values($file);
            $singleFile = $filevalues[$imageindex];
            if ($singleFile && $singleFile->is_valid_image()) {
                send_stored_file($singleFile, 1);
                exit;
            }
            $file = $singleFile;
        } else if ($file) {
            if (is_array($file)) {
                if (count($file) > 1) {
                    $mixedimage = block_exaport_mix_images($file);
                    //$file->is_valid_image()
                    //send_stored_file($file, 1);  // !!!!!!!!!!!!!!! may be make composite of images?
                    echo 'mixed image';
                    exit;
                } else {
                    $singleFile = reset($file);
                    if ($singleFile->is_valid_image()) {
                        send_stored_file($singleFile, 1);
                        exit;
                    }
                    $file = $singleFile;
                }
            } else {
                if ($file->is_valid_image()) {
                    send_stored_file($file, 1);
                    exit;
                }
            }
        }

        $output = block_exaport_get_renderer();
        // Needed for pix_url.
        $PAGE->set_context(context_system::instance());
        $icon = $output->image_url(file_file_icon($file, 90));

        header('Location: '.$icon);
        break;

    case "link":
        $url = $item->url;
        if (strpos($url, 'http') === false) {
            $url = 'http://'.$url;
        }

        $str = file_get_contents($url);

        if ($str && preg_match('/<img\s.*src=[\'"]([^\'"]+)[\'"]/im', $str, $matches)) {
            $firstimg = $matches[1];
            if (strpos($firstimg, 'http') === false) {
                if ($firstimg[0] == '/') {
                    /* google.com + /imgage.png
                       google.com/sub + /imgage.png. */
                    $firstimg = preg_replace('!([^:/])/.*$!m', '$1', $url).$firstimg;
                } else {
                    /* google.com + imgage.png. */
                    $firstimg = $url."/".$firstimg;
                }
            }

            $headers = get_headers($firstimg, 1);
            $type = $headers["Content-Type"];

            $imgstr = @file_get_contents($firstimg);
            if (strlen($imgstr) < 50) {
                header('Location: pix/link_tile.png');
                break;
            }
            header("Content-type: ".$type);

            echo $imgstr;

            exit;
        }
        header('Location: pix/link_tile.png');
        break;

    case "note":
        header('Location: pix/note_tile.png');
        break;
    default:
        die('wrong type');
}
