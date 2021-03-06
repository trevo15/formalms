<?php

function GetCategory($the_course)
{
    $ret_val = str_replace("/", " - ", substr($the_course['nameCategory'], 1));
    if ($ret_val == "") {
        $ret_val = Lang::t('_NO_CATEGORY', 'standard');
    } else {
        //$ret_val = substr($the_course['nameCategory'], 6);
        $ret_val = explode("/", $the_course['nameCategory']);
        $ret_val = array_pop($ret_val);
    }
    return $ret_val;
}


function dataEndExists($the_course)
{
    $date = Format::date($the_course['date_end'], 'date');
    return ($date != "00-00-0000");
}

function GetCourseYear($the_course)
{
    $date = Format::date($the_course['date_end'], 'date');
     
    if(strpos($date,"/")){
        $separator = '/';
    } else if (strpos($date,"-")){
        $separator = '-';
    } else return null;
   
    $date_split = explode($separator, $date); // format DD/MM/YYYY
    return $date_split[2];
}

function GetCourseMonth($the_course)
{
    $lang = DoceboLangManager::getInstance()->getLanguageBrowsercode(Lang::get());
    setlocale(LC_ALL, $lang . "_" . strtoupper($lang)); // TBD: setting to platform locale
    $date = Format::date($the_course['date_end'], 'date');
    $month_name = ucfirst(strftime("%B", strtotime($date)));
    return substr($month_name, 0, 3);
}

function GetCourseDay($the_course)
{
    $date = Format::date($the_course['date_end'], 'date');
    $date_split = explode('-', $date);
    return $date_split[0];
}

function GetCourseImage($the_course, $path_image)
{

    if ($the_course['img_course']) {
        return $path_image . $the_course['img_course'];
    } else {
        return Get::tmpl_path() . 'images/course/course_nologo.png';
    }
}

function TruncateText($the_text, $size)
{
    if (strlen($the_text) > $size)
        return substr($the_text, 0, $size) . '...';
    return $the_text;
}

function getStringPresence($presence)
{
    $strPresence = Lang::t('_NO', 'standard');
    if ($presence == 1) $strPresence = Lang::t('_YES', 'standard');

    return $strPresence;
}


function typeOfCourse($t)
{
    switch ($t) {
        case "elearning":
            return Lang::t('_ELEARNING', 'catalogue');
        case "classroom":
            return Lang::t('_CLASSROOM_COURSE', 'cart');
        case "all":
            return Lang::t('_ALL_COURSES', 'standard');
    }
    return '';
}

function userCanUnsubscribe($course)
{
    $now = new DateTime();

    $courseUnsubscribeDateLimit = (null !== $course['course_unsubscribe_date_limit'] ? DateTime::createFromFormat('Y-m-d H:i:s', $course['course_unsubscribe_date_limit']) : '');
    $dateUnsubscribeDateLimit = (null !== $course['date_unsubscribe_date_limit'] ? DateTime::createFromFormat('Y-m-d H:i:s', $course['date_unsubscribe_date_limit']) : '');

    if ((['auto_unsubscribe'] == 2 || $course['auto_unsubscribe'] == 1) && $now < $courseUnsubscribeDateLimit || $now < $dateUnsubscribeDateLimit) {

        return true;
    }

    return false;
}


?>


<script language="javascript">
    function confirmDialog(title, id_course, id_date) {
        $('<div></div>').appendTo('body')
            .html("<div><h6><?php echo Lang::t('_SELF_UNSUBSCRIBE', 'course') ?></h6></div>")
            .dialog({
                modal: true,
                title: title,
                autoOpen: true,
                width: '200',
                height: '150',
                resizable: false,
                buttons: {
                    <?php echo Lang::t('_CONFIRM', 'standard') ?>:

                        function() {
                            var posting = $.get(
                                'ajax.server.php', {
                                    r: 'elearning/self_unsubscribe',
                                    id_course: id_course,
                                    id_date: id_date
                                }
                            );
                            posting.done(function(responseText) {
                                var ft = $("#course_search_filter_text").val();
                                var ctype = $("#course_search_filter_type").selectpicker().val();
                                var category = $('#course_search_filter_cat').selectpicker().val();
                                var cyear = $("#course_search_filter_year").selectpicker().val();
                                var json_status = $('.js-label-menu-filter.selected').attr('data-value');
                                $("#div_course").html("<br><p align='center'><img src='<?php echo Layout::path() ?>images/standard/loadbar.gif'></p>");
                                var posting = $.get('ajax.server.php?r=elearning/all&rnd=<?php echo time(); ?>&filter_text=' + ft + '&filter_type=' + ctype + '&filter_cat=' + category + '&filter_status=' + json_status + '&filter_year=' + cyear, {});
                                posting.done(function(responseText) {
                                    $("#div_course").html(responseText);
                                });
                            });
                            posting.fail(function() {
                                alert('unsubscribe failed')
                            })
                            $(this).dialog("close");
                        }

                        ,
                    <?php echo Lang::t('_UNDO', 'standard') ?>:

                        function() {
                            $(this).dialog("close");
                        }
                },
                open: function(event, ui) {
                    $(".ui-dialog-titlebar-close", ui.dialog | ui).hide();
                },
                close: function(event, ui) {
                    $(this).remove();
                }
            });
    }


    function openAllDatesPopup($id) {
        $('*[data-overlay="course-' + $id + '"]').fadeIn();
    }

    function closeAllDatesPopup($id) {
        $('*[data-overlay="course-' + $id + '"]').fadeOut();
    }
</script>


<link rel="shortcut icon" href="../favicon.ico">


<div id='container1_<?php echo $course_state; ?>'>
    <h1 class="page-header col-xs-12"><strong><?php echo typeOfCourse($filter_type); ?></strong></h1>
    <div class="clearfix row" id='mia_area_<?php echo $stato_corso; ?>'>
        <?php if (empty($courselist)) : ?>
            <div class="col-xs-12">
                <p><?php echo Lang::t('_NO_CONTENT', 'standard'); ?></p>
            </div>
        <?php endif; ?>



        <?php
        foreach ($courselist

            as $course) {
            $tooltipClass = '';
            $tooltipElement = '';
            if (strlen($course['name']) >= 50) {
                $tooltipClass = 'has-forma-tooltip';
                $tooltipElement = '
              <div class="forma-tooltip">' . $course['name'] . '</div>
            ';
            }


        ?>
            <div class="col-xs-12 col-md-4 col-lg-3 mycourses-list">
                <div class="course-box" <?php if ($tooltipClass !== '') echo 'style="overflow:visible";' ?>>
                    <!-- NEW BLOCK -->
                    <div class="course-box__item <?php echo $tooltipClass; ?>">
                        <div class="course-box__title icon--filter-<?php echo $course['user_status']; ?>">
                            <?php echo TruncateText($course['name'], 45);
                            echo $indexCourse; ?>
                        </div>
                        <?php
                        if ($tooltipElement !== '') {
                            echo $tooltipElement;
                        }
                        ?>
                    </div>
                    <div class="course-box__item course-box__item--no-padding">
                        <?php if ($course['use_logo_in_courselist']) { ?>
                            <div class="course-box__img" style="background-image: url(<?php echo GetCourseImage($course, $path_course) ?>)">
                            <?php } else { ?>
                                <div class="course-box__img">
                                <?php } ?>
                                <div class="course-box__img-title">
                                    <?php echo GetCategory($course) ?>
                                </div>
                                </div>
                            </div>
                            <div class="course-box__item">
                                <div class="course-box__owner course-box__owner--<?php echo $course['level']; ?>">
                                    <?php echo $this->levels[$course['level']]; ?>
                                </div>

                                <?php if (userCanUnsubscribe($course) || $course["course_demo"]) : ?>

                                    <div class="course-box__options dropdown pull-right">
                                        <div class="dropdown-toggle" id="courseBoxOptions" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                                            <i class="glyphicon glyphicon-option-horizontal"></i>
                                        </div>

                                        <ul class="dropdown-menu" aria-labelledby="courseBoxOptions">

                                            <?php if (userCanUnsubscribe($course)) : ?>
                                                <li>
                                                    <a href='javascript:confirmDialog(<?php echo "\"" . $course['name'] . "\"," . $course['idCourse'] . "," . key($display_info[$course['idCourse']]) ?>)'><?php echo Lang::t('_SELF_UNSUBSCRIBE', 'course') ?></a>
                                                </li>
                                            <?php endif; ?>

                                            <?php if ($course["course_demo"]) : ?>
                                                <li>
                                                    <a href="index.php?r=catalog/downloadDemoMaterial&amp;course_id=<?php echo $course['idCourse'] ?>"><?php echo Lang::t('_COURSE_DEMO', 'course') ?></a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="course-box__desc">
                                    <?php echo TruncateText($course['box_description'], 120); ?>
                                </div>
                            </div>

                            <!-- DATA CLOSING ONLY IF SET -->
                            <?php
                            $dataClosing = (int) GetCourseYear($course);
                            if ($dataClosing > 0) {
                            ?>
                                <div class="course-box__item course-box__item--half">
                                    <div class="course-box__date-text">
                                        <span><?php echo Lang::t('_CLOSING_DATA', 'course') ?></span>:
                                        <?php echo GetCourseDay($course) ?>&nbsp;<?php echo GetCourseMonth($course) ?>
                                        &nbsp;<?php echo $dataClosing; ?>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                            <?php

                            if ($course['course_type'] == 'classroom') {
                                // if exists end course, show it 
                            ?>
                                <div class="course-box__item course-box__item--half">

                                    <?php

                                    $vett_course = array();
                                    $day_lessons = array();
                                    $next_lesson = array();
                                    $vett_course = $display_info[$course['idCourse']];

                                    foreach ($vett_course as $date) {

                                        foreach ($date->date_info_day as $key => $value) {

                                            $day = array(
                                                "name" => $date->name,
                                                "code" => $date->code,
                                                "startDate" => $value['date_begin'],
                                                "endDate" => $value['date_end'],
                                                "location" => $value['location'],
                                                "teacher" => $value['teacher'],
                                                "presence" => $value['presence']
                                            );

                                            if ($value['nextMeet'] == 0) {
                                                $day_lessons[] = $day;
                                            } else {
                                                $next_lesson[] = $day;
                                            }
                                        }
                                    }


                                    ?>
                                    <?php if ($day_lessons && !empty($day_lessons) || $next_lesson && !empty($next_lesson)) : ?>
                                        <p class="course-box__show-dates js-course-box-open-dates-modal">
                                            <i class="glyphicon glyphicon-play"></i>
                                            &nbsp; <?php echo Lang::t('_MEETING_LESSON', 'standard') ?></p>
                                        <div class="course-box__modal">
                                            <div class="course-box__modal__header">
                                                <p class="course-box__modal__title"><?php echo $course['name']; ?></p>
                                                <button type="button" class="close-button js-course-box-close-dates-modal">
                                                    <span class="close-button__icon"></span>
                                                    <span class="close-button__label"><?php echo Lang::t('_CLOSE', 'standard') ?></span>
                                                </button>
                                            </div>


                                            <div class="course-box__modal__content">

                                                <?php if (count($next_lesson) > 0) : ?>

                                                    <!-- NEXT MEETING -->
                                                    <div class="course-box__modal__entry">

                                                        <p class="course-box__modal__title"><?php echo Lang::t('_NEXTMEETING', 'standard') ?></p>

                                                        <table class="course-box__modal__lesson">
                                                            <tr>
                                                                <td><?php echo Lang::t('_NAME', 'standard') ?>:</td>
                                                                <td><?php echo $next_lesson[0]['name']; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo Lang::t('_CODE', 'standard') ?>:</td>
                                                                <td><?php echo $next_lesson[0]['code']; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo Lang::t('_START', 'standard') ?>:</td>
                                                                <td><?php echo $next_lesson[0]['startDate']; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo Lang::t('_END', 'standard') ?>:</td>
                                                                <td><?php echo $next_lesson[0]['endDate']; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo Lang::t('_CLASSROOM', 'standard') ?>:</td>
                                                                <td><?php echo $next_lesson[0]['location']; ?></td>
                                                            </tr>

                                                        </table>


                                                    </div>
                                                <?php endif; ?>

                                                <!-- TEACHER -->
                                                <?php if (count($day_lessons[0]['teacher']) > 0) : ?>
                                                    <div class="course-box__modal__entry">
                                                        <p class="course-box__modal__title"><?php echo Lang::t('_COURSE_TEACHERS', 'course') ?></p>
                                                        <table class="course-box__modal__lesson" border=0>
                                                            <?php
                                                            for (
                                                                $j = 0;
                                                                $j < count($day_lessons[0]['teacher']);
                                                                $j++
                                                            ) {
                                                                echo "<tr width='100%' >
                                                    <td>" . Lang::t('_LEVEL_6', 'levels') . ":</td>
                                                    <td>
                                                        <a href='mailto:" . $day_lessons[$j]['teacher'][$j]['email'] . "'><img src='" . Get::tmpl_path() . "images/emoticons/email.gif'></a> &nbsp;
                                                        " . $day_lessons[$j]['teacher'][$j]['firstname'] . " 
                                                        " . $day_lessons[$j]['teacher'][$j]['lastname'] . "</td>
                                         
                                                    </tr>";
                                                            }

                                                            ?>
                                                        </table>

                                                    </div>
                                                <?php endif; ?>


                                                <!-- LESSONS PREV -->
                                                <?php if (count($day_lessons) > 1) : ?>
                                                    <div class="course-box__modal__entry">
                                                        <p class="course-box__modal__title"><?php echo Lang::t('_MEETING_LESSON', 'standard') ?></p>
                                                        <?php for (
                                                            $i = 0;
                                                            $i < count($day_lessons);
                                                            $i++
                                                        ) : ?>
                                                            <table class="course-box__modal__lesson">
                                                                <tr>
                                                                    <td><?php echo Lang::t('_NAME', 'standard') ?>:</td>
                                                                    <td><?php echo $day_lessons[$i]['name']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><?php echo Lang::t('_CODE', 'standard') ?>:</td>
                                                                    <td><?php echo $day_lessons[$i]['code']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><?php echo Lang::t('_START', 'standard') ?>:</td>
                                                                    <td><?php echo $day_lessons[$i]['startDate']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><?php echo Lang::t('_END', 'standard') ?>:</td>
                                                                    <td><?php echo $day_lessons[$i]['endDate']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><?php echo Lang::t('_CLASSROOM', 'standard') ?>:
                                                                    </td>
                                                                    <td><?php echo $day_lessons[$i]['location']; ?></td>
                                                                </tr>

                                                                <?php if ($day_lessons[$i]['presence'] > 0) : ?>
                                                                    <tr>
                                                                        <td><?php echo Lang::t('_IS_PRESENCE', 'standard') ?>
                                                                            :
                                                                        </td>
                                                                        <td><?php echo getStringPresence($day_lessons[$i]['presence']); ?></td>
                                                                    </tr>
                                                                <?php endif; ?>

                                                            </table>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>


                                            <div class="close-button js-course-box-close-dates-modal course-box__modal__footer">
                                                <button type="button" class="forma-button"><?php echo Lang::t('_CLOSE', 'standard') ?></button>
                                            </div>


                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php } ?>
                            <div class="course-box__item course-box__item<?php if (dataEndExists($course)) {
                                                                                echo '--half';
                                                                            } ?>">
                                <?php if ($course['can_enter']['can']) { ?>
                                    <a class="forma-button forma-button--orange-hover forma-button--full" title="<?php echo Util::purge($course['name']); ?>" href="index.php?modname=course&amp;op=aula&amp;idCourse=<?php echo $course['idCourse']; ?>" <?php echo ($course['direct_play'] == 1 && $course['level'] <= 3 && $course['first_lo_type'] == 'scormorg' ? ' rel="lightbox"' : ''); ?>>
                                        <span class="forma-button__label"> <?php echo Lang::t('_USER_STATUS_ENTER', 'catalogue'); ?></span>
                                    </a>
                                <?php } else { ?>
                                    <a class="forma-button forma-button--disabled" href="javascript:void(0);">
                                        <span class="forma-button__label">
                                            <?php echo Lang::t('_DISABLED', 'course') ?>
                                        </span>
                                    </a>
                                <?php } ?>
                            </div>

                            <div class="course-box__extraInfo">
                                <?php if ($course['course_type'] === 'classroom') {
                                    $classroom_man = new DateManager();

                                    $dates = $classroom_man->getCourseDate($course['idCourse'], false);
                                    reset($dates);
                                    $first_key = key($dates);
                                    if (count($dates) > 0) {

                                        $days = $classroom_man->getDateDayDateDetails($dates[$first_key]['id_date']);

                                ?>
                                        <div class="course-box__next">
                                            <?php echo Lang::t('_NEXT_LESSON', 'course');
                                            $nextLessonDateSting = '';
                                            $currentDate = new DateTime();
                                            foreach ($days as $day) {
                                                try {
                                                    $date = new DateTime($day['date_begin']);

                                                    if ($date > $currentDate) {

                                                        $nextLessonDateSting = '<div>' . Format::date($day['date_begin'], 'date') . '</div>';
                                                        break;
                                                    }
                                                } catch (\Exception $exception) {
                                                    continue;
                                                }
                                            }
                                            if (empty($nextLessonDateSting)) {
                                                $nextLessonDateSting = '<div> ' . Lang::t('_NEXT_LESSON_DATE_NOT_FOUND', 'course') . ' </div>';
                                            }

                                            echo $nextLessonDateSting;
                                            ?>
                                        </div>

                                        <div class="course-box__allDates">
                                            <a href="javascript:;" onclick="openAllDatesPopup(<?php echo $course['idCourse']; ?>)"><?php echo Lang::t('_SHOW_ALL_DATES', 'course'); ?></a>

                                            <div class="show-all-dates-popup" data-overlay="course-<?php echo $course['idCourse']; ?>">
                                                <div id="pop_up_container" class="yui-module yui-overlay yui-panel">
                                                    <a class="container-close" href="javascript:;" onclick="closeAllDatesPopup(<?php echo $course['idCourse']; ?>)"></a>
                                                    <div class="hd" id="pop_up_container_h"><?php echo $course['name']; ?></div>
                                                    <div class="bd">
                                                        <div class="edition_container">
                                                            <table class="edition_table">
                                                                <thead>
                                                                    <tr>
                                                                        <th><?php echo Lang::t('_Data Inizio', 'course'); ?></th>
                                                                        <th><?php echo Lang::t('_Data Fine', 'course'); ?></th>
                                                                        <th><?php echo Lang::t('_Classroom', 'course'); ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    $currentDate = new DateTime();
                                                                    foreach ($days as $day) {
                                                                        try {
                                                                            $date = new DateTime($day['date_begin']);
                                                                            if ($date > $currentDate) {

                                                                                echo '<tr>';
                                                                                echo '<td>' . Format::date($day['date_begin'], 'date') . '</td>';
                                                                                echo '<td>' . Format::date($day['date_end'], 'date') . '</td>';
                                                                                echo '<td>' . $day['classroom'] . '</td>';
                                                                                echo '</tr>';
                                                                            }
                                                                        } catch (\Exception $exception) {
                                                                            continue;
                                                                        }
                                                                    }
                                                                    ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                    </div>
                </div>
            <?php } // end foreach 
            ?>
            </div>
    </div>
</div>