<?php
/*
##########################################################################
#                                                                        #
#           Version 4       /                        /   /               #
#          -----------__---/__---__------__----__---/---/-               #
#           | /| /  /___) /   ) (_ `   /   ) /___) /   /                 #
#          _|/_|/__(___ _(___/_(__)___/___/_(___ _/___/___               #
#                       Free Content / Management System                 #
#                                   /                                    #
#                                                                        #
#                                                                        #
#   Copyright 2005-2015 by webspell.org                                  #
#                                                                        #
#   visit webSPELL.org, webspell.info to get webSPELL for free           #
#   - Script runs under the GNU GENERAL PUBLIC LICENSE                   #
#   - It's NOT allowed to remove this copyright-tag                      #
#   -- http://www.fsf.org/licensing/licenses/gpl.html                    #
#                                                                        #
#   Code based on WebSPELL Clanpackage (Michael Gruber - webspell.at),   #
#   Far Development by Development Team - webspell.org                   #
#                                                                        #
#   visit webspell.org                                                   #
#                                                                        #
##########################################################################
 */

$_language->readModule('squads', false, true);

if (!isuseradmin($userID) || mb_substr(basename($_SERVER['REQUEST_URI']), 0, 15) != "admincenter.php") {
    die($_language->module['access_denied']);
}

if (isset($_GET['delete'])) {
    $CAPCLASS = new \webspell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'])) {
        $squadID = $_GET['squadID'];
        $ergebnis = safe_query("SELECT userID FROM " . PREFIX . "squads_members WHERE squadID='$squadID'");
        while ($ds = mysqli_fetch_array($ergebnis)) {
            $squads = mysqli_num_rows(
                safe_query(
                    "SELECT userID FROM " . PREFIX . "squads_members WHERE userID='$ds[userID]'"
                )
            );
            if ($squads < 2 && !issuperadmin($ds['userID'])) {
                safe_query("DELETE FROM " . PREFIX . "user_groups WHERE userID='$ds[userID]'");
            }
        }
        safe_query("DELETE FROM " . PREFIX . "squads_members WHERE squadID='$squadID' ");
        safe_query("DELETE FROM " . PREFIX . "squads WHERE squadID='$squadID' ");

        $ergebnis = safe_query("SELECT upID FROM " . PREFIX . "upcoming WHERE squad='$squadID'");
        while ($ds = mysqli_fetch_array($ergebnis)) {
            safe_query("DELETE FROM " . PREFIX . "upcoming_announce WHERE upID='$ds[upID]'");
        }
        safe_query("DELETE FROM " . PREFIX . "upcoming WHERE squad='$squadID' ");

        $ergebnis = safe_query("SELECT cwID FROM " . PREFIX . "clanwars WHERE squad='$squadID'");
        while ($ds = mysqli_fetch_array($ergebnis)) {
            safe_query("DELETE FROM " . PREFIX . "comments WHERE type='cw' AND parentID='$ds[cwID]'");
        }
        safe_query("DELETE FROM " . PREFIX . "clanwars WHERE squad='$squadID' ");
        $filepath = "../images/squadicons/";
        if (file_exists($filepath . $squadID . '.gif')) {
            unlink($filepath . $squadID . '.gif');
        }
        if (file_exists($filepath . $squadID . '.jpg')) {
            unlink($filepath . $squadID . '.jpg');
        }
        if (file_exists($filepath . $squadID . '.png')) {
            unlink($filepath . $squadID . '.png');
        }
        if (file_exists($filepath . $squadID . '_small.gif')) {
            unlink($filepath . $squadID . '_small.gif');
        }
        if (file_exists($filepath . $squadID . '_small.jpg')) {
            unlink($filepath . $squadID . '_small.jpg');
        }
        if (file_exists($filepath . $squadID . '_small.png')) {
            unlink($filepath . $squadID . '_small.png');
        }
    } else {
        echo $_language->module['transaction_invalid'];
    }
}

if (isset($_POST['sortieren'])) {
    $CAPCLASS = new \webspell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        $sort = $_POST['sort'];
        if (is_array($sort)) {
            foreach ($sort as $sortstring) {
                $sorter = explode("-", $sortstring);
                safe_query("UPDATE " . PREFIX . "squads SET sort='$sorter[1]' WHERE squadID='$sorter[0]' ");
            }
        }
    } else {
        echo $_language->module['transaction_invalid'];
    }
}

if (isset($_POST['save'])) {
    $CAPCLASS = new \webspell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (checkforempty(array('name'))) {
            $games = implode(";", $_POST['games']);
            safe_query(
                "INSERT INTO " . PREFIX . "squads ( gamesquad, games, name, info, sort ) VALUES ( '" .
                $_POST['gamesquad'] . "', '" . $games . "', '" . $_POST['name'] . "', '" . $_POST['message'] .
                "', '1' )"
            );

            $id = mysqli_insert_id($_database);
            $filepath = "../images/squadicons/";

            $errors = array();

            //TODO: should be loaded from root language folder
            $_language->readModule('formvalidation', true);

            $upload = new \webspell\HttpUpload('icon');
            if ($upload->hasFile()) {
                if ($upload->hasError() === false) {
                    $mime_types = array('image/jpeg', 'image/png', 'image/gif');

                    if ($upload->supportedMimeType($mime_types)) {
                        $imageInformation = getimagesize($upload->getTempFile());

                        if (is_array($imageInformation)) {
                            switch ($imageInformation[2]) {
                                case 1:
                                    $endung = '.gif';
                                    break;
                                case 3:
                                    $endung = '.png';
                                    break;
                                default:
                                    $endung = '.jpg';
                                    break;
                            }
                            $file = $id . $endung;

                            if ($upload->saveAs($filepath . $file, true)) {
                                @chmod($filepath . $file, $new_chmod);
                                safe_query(
                                    "UPDATE " . PREFIX . "squads SET icon='" . $file . "' WHERE squadID='" . $id . "'"
                                );
                            }
                        } else {
                            $errors[] = $_language->module['broken_image'];
                        }
                    } else {
                        $errors[] = $_language->module['unsupported_image_type'];
                    }
                } else {
                    $errors[] = $upload->translateError();
                }
            }

            $upload = new \webspell\HttpUpload('icon_small');
            if ($upload->hasFile()) {
                if ($upload->hasError() === false) {
                    $mime_types = array('image/jpeg', 'image/png', 'image/gif');

                    if ($upload->supportedMimeType($mime_types)) {
                        $imageInformation = getimagesize($upload->getTempFile());

                        if (is_array($imageInformation)) {
                            switch ($imageInformation[2]) {
                                case 1:
                                    $endung = '.gif';
                                    break;
                                case 3:
                                    $endung = '.png';
                                    break;
                                default:
                                    $endung = '.jpg';
                                    break;
                            }
                            $file = $id . '_small' . $endung;

                            if ($upload->saveAs($filepath . $file, true)) {
                                @chmod($filepath . $file, $new_chmod);
                                safe_query(
                                    "UPDATE " . PREFIX . "squads SET icon_small='" . $file .
                                    "' WHERE squadID='" . $id . "'"
                                );
                            }
                        } else {
                            $errors[] = $_language->module['broken_image'];
                        }
                    } else {
                        $errors[] = $_language->module['unsupported_image_type'];
                    }
                } else {
                    $errors[] = $upload->translateError();
                }
            }

            if (count($errors)) {
                $errors = array_unique($errors);
                echo generateErrorBoxFromArray($_language->module['errors_there'], $errors);
            }
        } else {
            echo $_language->module['information_incomplete'];
        }
    } else {
        echo $_language->module['transaction_invalid'];
    }
}

if (isset($_POST['saveedit'])) {
    $CAPCLASS = new \webspell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (checkforempty(array('name'))) {
            $games = implode(";", $_POST['games']);
            safe_query(
                "UPDATE " . PREFIX . "squads SET gamesquad='" . $_POST['gamesquad'] . "', games='" . $games .
                "', name='" . $_POST['name'] . "', info='" . $_POST['message'] . "' WHERE squadID='" .
                $_POST['squadID'] . "' "
            );
            $filepath = "../images/squadicons/";
            $id = $_POST['squadID'];

            $errors = array();

            //TODO: should be loaded from root language folder
            $_language->readModule('formvalidation', true);

            $upload = new \webspell\HttpUpload('icon');
            if ($upload->hasFile()) {
                if ($upload->hasError() === false) {
                    $mime_types = array('image/jpeg', 'image/png', 'image/gif');

                    if ($upload->supportedMimeType($mime_types)) {
                        $imageInformation = getimagesize($upload->getTempFile());

                        if (is_array($imageInformation)) {
                            switch ($imageInformation[2]) {
                                case 1:
                                    $endung = '.gif';
                                    break;
                                case 3:
                                    $endung = '.png';
                                    break;
                                default:
                                    $endung = '.jpg';
                                    break;
                            }
                            $file = $id . $endung;

                            if ($upload->saveAs($filepath . $file, true)) {
                                @chmod($filepath . $file, $new_chmod);
                                safe_query(
                                    "UPDATE " . PREFIX . "squads SET icon='" . $file . "' WHERE squadID='" . $id . "'"
                                );
                            }
                        } else {
                            $errors[] = $_language->module['broken_image'];
                        }
                    } else {
                        $errors[] = $_language->module['unsupported_image_type'];
                    }
                } else {
                    $errors[] = $upload->translateError();
                }
            }

            $upload = new \webspell\HttpUpload('icon_small');
            if ($upload->hasFile()) {
                if ($upload->hasError() === false) {
                    $mime_types = array('image/jpeg', 'image/png', 'image/gif');

                    if ($upload->supportedMimeType($mime_types)) {
                        $imageInformation = getimagesize($upload->getTempFile());

                        if (is_array($imageInformation)) {
                            switch ($imageInformation[2]) {
                                case 1:
                                    $endung = '.gif';
                                    break;
                                case 3:
                                    $endung = '.png';
                                    break;
                                default:
                                    $endung = '.jpg';
                                    break;
                            }
                            $file = $id . '_small' . $endung;

                            if ($upload->saveAs($filepath . $file, true)) {
                                @chmod($filepath . $file, $new_chmod);
                                safe_query(
                                    "UPDATE " . PREFIX . "squads SET icon_small='" . $file .
                                    "' WHERE squadID='" . $id . "'"
                                );
                            }
                        } else {
                            $errors[] = $_language->module['broken_image'];
                        }
                    } else {
                        $errors[] = $_language->module['unsupported_image_type'];
                    }
                } else {
                    $errors[] = $upload->translateError();
                }
            }

            if (count($errors)) {
                $errors = array_unique($errors);
                echo generateErrorBoxFromArray($_language->module['errors_there'], $errors);
            }
        } else {
            echo $_language->module['information_incomplete'];
        }
    } else {
        echo $_language->module['transaction_invalid'];
    }
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    $action = '';
}

if ($action == "add") {
    echo '<h1>&curren; <a href="admincenter.php?site=squads" class="white">' . $_language->module['squads'] .
    '</a> &raquo; ' . $_language->module['add_squad'] . '</h1>';

    $filepath = "../images/squadicons/";
    $sql = safe_query("SELECT * FROM " . PREFIX . "games ORDER BY name");
    $games = '<select name="games[]">';
    while ($db = mysqli_fetch_array($sql)) {
        $games .= '<option value="' . htmlspecialchars($db['name']) . '">' . htmlspecialchars($db['name']) .
        '</option>';
    }
    $games .= '</select>';
    $CAPCLASS = new \webspell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    $_language->readModule('bbcode', true, true);

    $addbbcode = $GLOBALS["_template"]->replaceTemplate("addbbcode", array());
    $addflags = $GLOBALS["_template"]->replaceTemplate("flags_admin", array());

    echo '<script>
		<!--
			function chkFormular() {
				if(!validbbcode(document.getElementById(\'message\').value, \'admin\')) {
					return false;
				}
			}
		-->
	</script>';

    echo '<form method="post" id="post" name="post" action="admincenter.php?site=squads" enctype="multipart/form-data"
onsubmit="return chkFormular();">
  <table width="100%" border="0" cellspacing="1" cellpadding="3">
      <tr>
        <td width="15%"><b>' . $_language->module['icon_upload'] . '</b></td>
        <td width="85%"><input name="icon" type="file" size="40" /></td>
      </tr>
        <tr>
        <td><b>' . $_language->module['icon_upload_small'] . '</b></td>
        <td>
        <input name="icon_small" type="file" size="40" />
         <small>(' . $_language->module['icon_upload_info'] . ')</small>
        </td>
      </tr>
      <tr>
        <td><b>' . $_language->module['squad_name'] . '</b></td>
        <td><input type="text" name="name" size="60" /></td>
      </tr>
      <tr>
        <td><b>' . $_language->module['squad_type'] . '</b></td>
        <td><input onclick="document.getElementById(\'games\').style.display = \'block\'" type="radio"
        name="gamesquad" value="1" checked="checked" /> ' . $_language->module['gaming_squad'] . ' &nbsp;
        <input onclick="document.getElementById(\'games\').style.display = \'none\'" type="radio"
        name="gamesquad" value="0" /> ' . $_language->module['non_gaming_squad'] . '</td>
      </tr>
    </table>
    <div id="games" style="display:block;">
    <table width="100%" border="0" cellspacing="1" cellpadding="3">
      <tr>
        <td width="15%"><b>' . $_language->module['game'] . '</b></td>
        <td width="85%">' . $games . '</td>
      </tr>
    </table>
    </div>
    <table width="100%" border="0" cellspacing="1" cellpadding="3">
      <tr>
        <td colspan="2"><b>' . $_language->module['squad_info'] . '</b><br />
		  <table width="99%" border="0" cellspacing="0" cellpadding="0">
		    <tr>
			   <td valign="top">' . $addbbcode . '</td>
				<td valign="top">' . $addflags . '</td>
		    </tr>
		  </table>
		  <br /><textarea id="message" rows="5" cols="" name="message" style="width: 100%;"></textarea>
		  </td>
      </tr>
      <tr>
        <td colspan="2"><input type="hidden" name="captcha_hash" value="' . $hash .
    '" /><input type="submit" name="save" value="' . $_language->module['add_squad'] . '" /></td>
      </tr>
    </table>
    </form>';
} elseif ($action == "edit") {
    echo '<h1>&curren; <a href="admincenter.php?site=squads" class="white">' . $_language->module['squads'] .
    '</a> &raquo; ' . $_language->module['edit_squad'] . '</h1>';

    $squadID = (int) $_GET['squadID'];
    $filepath = "../images/squadicons/";

    $ergebnis = safe_query("SELECT * FROM " . PREFIX . "squads WHERE squadID='$squadID'");
    $ds = mysqli_fetch_array($ergebnis);

    $games_array = explode(";", $ds['games']);
    $sql = safe_query("SELECT * FROM " . PREFIX . "games ORDER BY name");
    $games = '<select name="games[]">';
    while ($db = mysqli_fetch_array($sql)) {
        $selected = '';
        if ($db['name'] == $ds['games']) {
            $selected = ' selected="selected"';
        }
        $games .= '<option value="' . htmlspecialchars($db['name']) . '"' . $selected . '>' .
        htmlspecialchars($db['name']) . '</option>';
    }
    $games .= '</select>';

    if ($ds['gamesquad']) {
        $type = '<input onclick="document.getElementById(\'games\').style.display = \'block\'"
type="radio" name="gamesquad" value="1" checked="checked" /> ' . $_language->module['gaming_squad'] . ' &nbsp;
<input onclick="document.getElementById(\'games\').style.display = \'none\'" type="radio"
            name="gamesquad" value="0" /> ' . $_language->module['non_gaming_squad'];
        $display = 'block';
    } else {
        $type = '<input onclick="document.getElementById(\'games\').style.display = \'block\'" type="radio"
            name="gamesquad" value="1" /> ' . $_language->module['gaming_squad'] . ' &nbsp;
            <input onclick="document.getElementById(\'games\').style.display = \'none\'" type="radio"
            name="gamesquad" value="0" checked="checked" /> ' . $_language->module['non_gaming_squad'];
        $display = 'none';
    }

    if (!empty($ds['icon'])) {
        $pic = '<img src="' . $filepath . $ds['icon'] . '" alt="">';
    } else {
        $pic = $_language->module['no_icon'];
    }
    if (!empty($ds['icon_small'])) {
        $pic_small = '<img src="' . $filepath . $ds['icon_small'] . '" alt="">';
    } else {
        $pic_small = $_language->module['no_icon'];
    }

    $CAPCLASS = new \webspell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    $_language->readModule('bbcode', true, true);

    $addbbcode = $GLOBALS["_template"]->replaceTemplate("addbbcode", array());
    $addflags = $GLOBALS["_template"]->replaceTemplate("flags_admin", array());

    echo '<script>
		<!--
			function chkFormular() {
				if(!validbbcode(document.getElementById(\'message\').value, \'admin\')) {
					return false;
				}
			}
		-->
	</script>';

    echo '<form method="post" id="post" name="post" action="admincenter.php?site=squads" enctype="multipart/form-data"
    onsubmit="return chkFormular();">
  <table width="100%" border="0" cellspacing="1" cellpadding="3">
    <tr>
      <td width="15%"><b>' . $_language->module['current_icon'] . '</b></td>
      <td width="85%">' . $pic . '</td>
    </tr>
	 <tr>
      <td width="15%"><b>' . $_language->module['current_icon_small'] . '</b></td>
      <td width="85%">' . $pic_small . '</td>
    </tr>
    <tr>
      <td><b>' . $_language->module['icon_upload'] . '</b></td>
      <td><input name="icon" type="file" size="40" /></td>
    </tr>
	 <tr>
      <td><b>' . $_language->module['icon_upload_small'] . '</b></td>
      <td>
      <input name="icon_small" type="file" size="40" />
      <small>(' . $_language->module['icon_upload_info'] . ')</small>
      </td>
    </tr>
    <tr>
      <td><b>' . $_language->module['squad_name'] . '</b></td>
      <td><input type="text" name="name" value="' . getinput($ds['name']) . '" size="60" /></td>
    </tr>
    <tr>
      <td><b>' . $_language->module['squad_type'] . '</b></td>
      <td>' . $type . '</td>
    </tr>
  </table>
  <div id="games" style="display:' . $display . '">
  <table width="100%" border="0" cellspacing="1" cellpadding="3">
    <tr>
      <td width="15%"><b>' . $_language->module['game'] . '</b></td>
      <td width="85%">' . $games . '</td>
    </tr>
  </table>
  </div>
  <table width="100%" border="0" cellspacing="1" cellpadding="3">
    <tr>
      <td colspan="2"><b>' . $_language->module['squad_info'] . '</b>
      <table width="99%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td valign="top">' . $addbbcode . '</td>
          <td valign="top">' . $addflags . '</td>
        </tr>
      </table>
      <br /><textarea rows="5" cols="" name="message" style="width: 100%;">' . getinput($ds['info']) . '</textarea>
      </td>
    </tr>
    <tr>
      <td colspan="2"><input type="hidden" name="captcha_hash" value="' . $hash .
    '" /><input type="hidden" name="squadID" value="' . getforminput($squadID) .
    '" /><input type="submit" name="saveedit" value="' . $_language->module['edit_squad'] . '" /></td>
    </tr>
  </table>
  </form>';
} else {
    echo '<h1>&curren; ' . $_language->module[ 'squads' ] . '</h1>';

    echo '<a href="admincenter.php?site=squads&amp;action=add" class="input">' .
        $_language->module[ 'new_squad' ] . '</a><br><br>';

    echo '<form method="post" action="admincenter.php?site=squads">
  <table width="100%" border="0" cellspacing="1" cellpadding="3" bgcolor="#DDDDDD">
    <tr>
      <td width="20%" class="title"><b>' . $_language->module['squad_name'] . '</b></td>
      <td width="17%" class="title"><b>' . $_language->module['squad_type'] . '</b></td>
      <td width="35%" class="title"><b>' . $_language->module['squad_info'] . '</b></td>
      <td width="20%" class="title"><b>' . $_language->module['actions'] . '</b></td>
      <td width="8%" class="title"><b>' . $_language->module['sort'] . '</b></td>
    </tr>';

    $squads = safe_query("SELECT * FROM " . PREFIX . "squads ORDER BY sort");
    $anzsquads = mysqli_num_rows($squads);
    $CAPCLASS = new \webspell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    if ($anzsquads) {
        $i = 1;
        while ($db = mysqli_fetch_array($squads)) {
            if ($i % 2) {
                $td = 'td1';
            } else {
                $td = 'td2';
            }

            $games = explode(";", $db['games']);
            $games = implode(", ", $games);
            if ($games) {
                $games = "(" . $games . ")";
            }
            if ($db['gamesquad']) {
                $type = $_language->module['gaming_squad'] . '<br /><small>' . $games . '</small>';
            } else {
                $type = $_language->module['non_gaming_squad'];
            }

            echo '<tr>
        <td class="' . $td . '"><a href="../index.php?site=squads&amp;squadID=' . $db['squadID'] .
            '" target="_blank">' . getinput($db['name']) . '</a></td>
        <td class="' . $td . '" align="center">' . $type . '</td>
        <td class="' . $td . '">' . cleartext($db['info'], true, 'admin') . '</td>
        <td class="' . $td . '" align="center">
            <a href="admincenter.php?site=squads&amp;action=edit&amp;squadID=' . $db['squadID'] . '" class="input">' .
            $_language->module['edit'] . '</a>
        <input type="button" onclick="MM_confirm(\'' . $_language->module['really_delete'] .
            '\', \'admincenter.php?site=squads&amp;delete=true&amp;squadID=' . $db['squadID'] .
            '&amp;captcha_hash=' . $hash . '\')" value="' . $_language->module['delete'] . '" /></td>
        <td class="' . $td . '" align="center"><select name="sort[]">';

            for ($j = 1; $j <= $anzsquads; $j++) {
                if ($db['sort'] == $j) {
                    echo '<option value="' . $db['squadID'] . '-' . $j . '" selected="selected">' . $j . '</option>';
                } else {
                    echo '<option value="' . $db['squadID'] . '-' . $j . '">' . $j . '</option>';
                }
            }
            echo '</select>
        </td>
      </tr>';

            $i++;
        }
    }

    echo '<tr>
      <td class="td_head" colspan="5" align="right"><input type="hidden" name="captcha_hash" value="' . $hash .
    '"><input type="submit" name="sortieren" value="' . $_language->module['to_sort'] . '" /></td>
    </tr>
  </table>
  </form>';
}
