<?php defined("IN_FORMA") or die('Direct access is forbidden.');

/* ======================================================================== \
|   FORMA - The E-Learning Suite                                            |
|                                                                           |
|   Copyright (c) 2013 (Forma)                                              |
|   http://www.formalms.org                                                 |
|   License  http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt           |
|                                                                           |
|   from docebo 4.0.5 CE 2008-2012 (c) docebo                               |
|   License http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt            |
\ ======================================================================== */

require_once(dirname(__FILE__) . '/lib.connector.php');
require_once($GLOBALS['where_lms'] . '/lib/lib.course.php');
require_once(_base_ . '/lib/lib.eventmanager.php');

/**
 * class for define docebo course subscription connection to data source.
 * @package admin-core
 * @subpackage io-operation
 * @version    1.0
 * @author        Fabio Pirovano <fabio (@) docebo (.) com>
 * @access public
 **/
class DoceboConnector_DoceboCourseUser extends DoceboConnector
{

    public $last_error = "";

    public $acl_man = false;

    public $sub_man = false;

    // name, type
    public $all_cols = [
        ['idCourse', 'text', '%lms_courseuser.idCourse'],
        ['code', 'text', '%lms_course.code'],
        ['medium_time', 'text', '%lms_course.mediumTime'],
        ['level', 'int', '%lms_courseuser.level'],
        ['date_subscription', 'date', '%lms_courseuser.date_inscr'],
        ['last_finish', 'text', '%lms_courseuser.date_complete'],
        ['status', 'text', '%lms_courseuser.status'],
        ['userid', 'text', '%adm_user.userid'],
    ];


    public $mandatory_cols = ['code', 'userid', 'level'];

    public $default_cols = ['code' => '', 'userid' => '', 'level' => '3', 'date_subscription' => false];

    public $name = "";
    public $description = "";

    public $readwrite = 1; // read = 1, write = 2, readwrite = 3
    public $sendnotify = 1; // send notify = 1, don't send notify = 2
    public $on_delete = 1;  // unactivate = 1, delete = 2

    public $arr_pair_inserted = [];

    public $course_cache = false;
    public $userid_cache = false;

    public $first_row_header = '1';


    /**
     * constructor
     * @param array params
     **/
    function DoceboConnector_DoceboCourseUser($params)
    {

        require_once($GLOBALS['where_lms'] . '/lib/lib.subscribe.php');


        $this->acl_man = new DoceboACLManager();
        $this->sub_man = new CourseSubscribe_Management();

        if ($params === NULL)
            return;
        else
            $this->set_config($params);    // connection


    }

    /**
     * set configuration
     * @param array $params
     **/
    function set_config($params)
    {

        if (isset($params['name'])) $this->name = $params['name'];
        if (isset($params['description'])) $this->description = $params['description'];
        if (isset($params['readwrite'])) $this->readwrite = $params['readwrite'];
        if (isset($params['sendnotify'])) $this->sendnotify = $params['sendnotify'];
        if (isset($params['on_delete'])) $this->on_delete = $params['on_delete'];
    }

    /**
     * get configuration
     * @return array
     **/
    function get_config()
    {

        return array('name' => $this->name,
            'description' => $this->description,
            'readwrite' => $this->readwrite,
            'sendnotify' => $this->sendnotify,
            'on_delete' => $this->on_delete,
            'first_row_header' => $this->first_row_header);
    }

    /**
     * get configuration UI
     * @return  DoceboConnectorUI
     **/
    function get_configUI()
    {

        return new DoceboConnectorUI_DoceboCourseUserUI($this);
    }

    /**
     * execute the connection to source
     **/
    function connect()
    {

        $this->lang = DoceboLanguage::createInstance('rg_report');

        $this->_readed_end = false;
        $this->today = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $this->position = 1;


        // get custom field of course and put in property "vett_custom"
        $this->get_custom_field_course();

        $query = 'SELECT COUNT(*) FROM %lms_courseuser';

        list($tot_row) = sql_fetch_row(sql_query($query));

        $this->tot_row = $tot_row;

        $query = " SELECT %adm_user.idst, ";

        $numberCols = count($this->all_cols);
        foreach ($this->all_cols as $index => $column) {
            if ($index < $numberCols - 1) {
                $query .= sprintf(' %s as %s,', $column[2], $column[0]);
            } else {
                $query .= sprintf(' %s as %s', $column[2], $column[0]);
            }
        }

        $query .= " FROM %lms_courseuser INNER JOIN %lms_course ON %lms_courseuser.idCourse = %lms_course.idCourse INNER JOIN %adm_user ON %lms_courseuser.idUser = %adm_user.idst";

        $result = sql_query($query);

        $data = array();

        $counter = 0;

        // get custom field of course
        $courseCustomFields = $this->get_custom_field_all_info();
        $userCustomFields = $this->get_custom_field_all_info_user();

        if ($this->first_row_header) {
            foreach ($this->all_cols as $column) {
                $data[$counter][] = $column[0];
            }

            // ADD CUSTOM FIELD IN ROW HEADER
            foreach ($courseCustomFields as $key => $value) {
                $data[$counter][] = $this->lang->def('_COURSE') . ": " . $value[0];

            }

            // MANAGE CUSTOM FIELD USER
            foreach ($userCustomFields as $key => $value) {
                $data[$counter][] = $this->lang->def('_USER') . ": " . $value[0];

            }


            $counter++;
        }

        foreach ($result as $row) {
            foreach ($this->all_cols as $column) {

                $data[$counter][] = $this->getParsedTextfield($row[$column[0]]);
            }


            $idCourse = $row['idCourse'];
            $idUser = $row['idst'];
            // MANAGE CUSTOM FIELD COURSE
            foreach ($courseCustomFields as $value) {
                $data[$counter][] = $this->get_value_custom_field_by_type($value[1], $value[2], $idCourse);   //$value[1]."-".$value[2]."  ** ".$idCourse;

            }

            // MANAGE CUSTOM FIELD COURSE
            foreach ($userCustomFields as $value) {
                $data[$counter][] = $this->get_value_custom_field_user_by_type($value[1], $value[2], $idUser); //$value[1]."-".$value[2]."  ** ".$idUser;
            }
            $counter++;
        }
        $counter--;
        $this->all_data = $data;

        return true;

    }

    /**
     * execute the close of the connection
     **/
    function close()
    {
    }

    function get_type_name()
    {
        return "docebo-courseuser";
    }

    function get_type_description()
    {
        return "connector to docebo user course subscription";
    }

    function get_name()
    {
        return $this->name;
    }

    function get_description()
    {
        return $this->description;
    }

    function is_readonly()
    {
        return (bool)($this->readwrite & 1);
    }

    function is_writeonly()
    {
        return (bool)($this->readwrite & 2);
    }

    function is_raw_producer()
    {
        return false;
    }

    function get_tot_cols()
    {
        return count($this->all_cols);
    }


    /*  get custom field of entity course */
    function get_custom_field_course()
    {

        $search_query = "SELECT translation, code, type_field 
                        FROM %adm_customfield cf , %adm_customfield_lang cfl
                        where area_code like 'COURSE' and cfl.id_field=cf.id_field and lang_code like '" . getLanguage() . "'";

        $re_course = sql_query($search_query);
        if (!$re_course) return false;
        $vett_out = array();
        while (list($translation, $code, $type_field) = sql_fetch_row($re_course)) {
            $vett_out[] = array($translation, $type_field);
        }

        return $vett_out;

    }

    /* get custom field of entity user */
    function get_custom_field_user()
    {

        $search_query = "SELECT translation, lang_code, type_field 
                        FROM  %adm_field
                        where   lang_code like '" . getLanguage() . "'";


        $re_course = sql_query($search_query);
        if (!$re_course) return false;
        $vett_out = array();
        while (list($translation, $code, $type_field) = sql_fetch_row($re_course)) {
            $vett_out[] = array($translation, $type_field);
        }

        return $vett_out;

    }


    /* get custom field of entity course alla info */
    function get_custom_field_all_info()
    {

        $search_query = "SELECT translation, code, type_field , cfl.id_field
                        FROM %adm_customfield cf , %adm_customfield_lang cfl
                        where area_code like 'COURSE' and cfl.id_field=cf.id_field and lang_code like '" . getLanguage() . "'";

        $re_course = sql_query($search_query);
        if (!$re_course) return false;
        $vett_out = array();
        while (list($translation, $code, $type_field, $id_field) = sql_fetch_row($re_course)) {
            $vett_out[] = array($translation, $id_field, $type_field);
        }

        return $vett_out;

    }


    /* get custom field of entity user alla info */
    function get_custom_field_all_info_user()
    {

        $search_query = "SELECT translation, type_field , idField
                        FROM %adm_field
                        where lang_code like '" . getLanguage() . "'";


        $re_course = sql_query($search_query);
        if (!$re_course) return false;
        $vett_out = array();
        while (list($translation, $type_field, $id_field) = sql_fetch_row($re_course)) {
            $vett_out[] = array($translation, $id_field, $type_field);
        }

        return $vett_out;

    }


    /* get value custom field of entity user by type_field */
    function get_value_custom_field_user_by_type($id_field, $type_field, $idUser)
    {

        $value_custom = $this->get_value_textfield_user($id_field, $idUser);
        if ($type_field === "dropdown") {
            $value_custom = $this->get_value_dropdown_user($id_field, $idUser, $value_custom);
        }


        return $value_custom;

    }


    /* get value custum field entry by type of entity course */
    function get_value_custom_field_by_type($id_field, $type_field, $idCourse)
    {
        if ($type_field === "textfield") {
            $value_custom = $this->get_value_textfield($id_field, $idCourse);
        }

        if ($type_field === "dropdown") {
            $value = $this->get_value_textfield($id_field, $idCourse);
            $value_custom = $this->get_value_dropdown($id_field, $idCourse, $value);
        }


        return $value_custom;

    }

    /* get value of custom fiel for type textfield */
    function get_value_textfield($id_field, $id_course)
    {

        $sql = "SELECT obj_entry FROM %adm_customfield_entry WHERE id_field=" . $id_field . " AND id_obj=" . $id_course;
        $re_course = sql_query($sql);
        if (!$re_course) return false;
        list($obj_entry) = sql_fetch_row($re_course);


        return $obj_entry;

    }

    /* get value of custom script for type dropdown */
    function get_value_dropdown($id_field, $id_course, $value)
    {
        $sql = "select translation from %adm_customfield_son_lang, %adm_customfield_son 
             where %adm_customfield_son.id_field=" . $id_field . "
             and %adm_customfield_son.id_field_son=%adm_customfield_son_lang.id_field_son
             and lang_code like '" . getLanguage() . "' 
             and %adm_customfield_son.id_field_son=" . $value;

        $re_course = sql_query($sql);
        if (!$re_course) return false;
        list($obj_entry) = sql_fetch_row($re_course);


        return $obj_entry;

    }


    function get_value_dropdown_user($id_field, $idUser, $value)
    {


        $sql = "select translation from %adm_field_son where idField=" . $id_field . " and idSon=" . $value;

        $re_user = sql_query($sql);
        if (!$re_user) return false;
        list($obj_entry) = sql_fetch_row($re_user);


        return $obj_entry;


    }


    /* get value of custom fiel for type textfield */
    function get_value_textfield_user($id_field, $id_user)
    {

        $sql = "SELECT user_entry FROM %adm_field_userentry, core_field 
         WHERE idField=" . $id_field . " 
         AND  %adm_field_userentry.id_common = %adm_field.id_common
         AND id_user=" . $id_user;


        $re_course = sql_query($sql);
        if (!$re_course) return false;
        list($obj_entry) = sql_fetch_row($re_course);


        return $obj_entry;

    }

    function getParsedTextfield($value)
    {
        $test = preg_match("/^\//", $value);

        if ($test === 1) {
            $value = substr($value, 1);
        }
        return $value;
    }


    function get_cols_descripor()
    {

        $lang = DoceboLanguage::createInstance('subscribe', 'lms');

        // get custom field of course and put in property "vett_custom"
        $courseCustomFields = $this->get_custom_field_course();

        //get custom field user
        $userCustomFields = $this->get_custom_field_user();

        // merge custom and value default - course
        $this->all_cols = array_merge($this->all_cols, $courseCustomFields);

        // merge custom and value default - users
        $this->all_cols = array_merge($this->all_cols, $userCustomFields);

        $col_descriptor = array();
        foreach ($this->all_cols as $col) {
            $isMandatory = in_array($col[0], $this->mandatory_cols);

            $col_descriptor[] = array(
                DOCEBOIMPORT_COLNAME => $lang->def('_' . strtoupper($col[0])),
                DOCEBOIMPORT_COLID => $col[0],
                DOCEBOIMPORT_COLMANDATORY => $isMandatory,
                DOCEBOIMPORT_DATATYPE => $col[1],
                DOCEBOIMPORT_DEFAULT => ($in = array_search($col[0], $this->default_cols) === FALSE
                    ? ''
                    : $this->default_cols[$in])
            );
        }
        return $col_descriptor;
    }

    function get_first_row()
    {
        if ($this->first_row) return $this->first_row;
        $this->first_row = $this->all_data[0];
        return $this->first_row;
    }


    function get_next_row()
    {
        $row = array();
        if ($this->first_row_header) {
            if ($this->tot_row >= $this->position) {
                $row = $this->all_data[$this->position];

                $this->position++;

                return $row;
            } else {
                $this->_readed_end = true;
                return false;
            }
        } else {
            if ($this->tot_row > $this->position) {
                $row = $this->all_data[$this->position];

                $this->position++;

                return $row;
            } else {
                $this->_readed_end = true;
                return false;
            }
        }
    }


    function is_eof()
    {
        return $this->_readed_end;
    }


    function get_row_index()
    {
        return $this->position;

    }

    function get_tot_mandatory_cols()
    {

        return count($this->mandatory_cols);
    }

    function get_row_bypk($pk)
    {

        // if none cache course code info
        if ($this->course_cache === false) {

            $this->course_cache = array();
            $search_query = "
			SELECT idCourse, code, name
			FROM %lms_course";
            $re_course = sql_query($search_query);
            if (!$re_course) return false;
            while (list($id_course, $code, $name) = sql_fetch_row($re_course)) {

                $this->course_cache[$code]['id'] = $id_course;
                $this->course_cache[$code]['course_name'] = $name;
            }
        }
        // if userid not cached search for it in the database and populate cache
        if (!isset($this->userid_cache[$pk['userid']])) {
            if ($this->userid_cache === false) $this->userid_cache = array();

            $user = $this->acl_man->getUser(false, addslashes($pk['userid']));
            if ($user === false) return false;

            $this->userid_cache[$pk['userid']] = $user[ACL_INFO_IDST];
        }
        return array(
            'id_course' => (isset($this->course_cache[$pk['code']]) ? $this->course_cache[$pk['code']]['id'] : 0),
            'course_name' => (isset($this->course_cache[$pk['code']]) ? $this->course_cache[$pk['code']]['course_name'] : ''),
            'idst_user' => (isset($this->userid_cache[$pk['userid']]) ? $this->userid_cache[$pk['userid']] : 0)
        );
    }

    function add_row($row, $pk)
    {

        $arr_id = $this->get_row_bypk($pk);

        if (!$arr_id || ($arr_id['idst_user'] == '')) {
            $this->last_error = 'not found the requested user ' . sql_error();
            return false;
        }
        if ($arr_id['id_course'] == '') {
            $this->last_error = 'not found the requested course ' . sql_error();
            return false;
        }
        if (!$row['level'])
            $row['level'] = 3;
        $re_ins = $this->sub_man->subscribeUserWithConnection($arr_id['idst_user'], $arr_id['id_course'], $row['level'], $this->get_name(), $row['date_subscription'] . ' 00:00:00');

        if ($re_ins === 'jump') return true;
        /*if($re_ins) {

             $query = "UPDATE learning_courseuser"
                        ." SET date_complete = '".$row['last_finish']."',"
                        ." status = '"._CUS_END."' "
                        ." WHERE idUser = '".$arr_id['idst_user']."'"
                        ." AND idCourse = '".$arr_id['id_course']."'";
                $result = sql_query($query);
            if($this->cache_inserted) {
                $this->arr_pair_inserted[] = $arr_id['id_course'].' '.$arr_id['idst_user'];
            }
            if($this->sendnotify == 1) {

                $array_subst = array(	'[url]' => Get::site_url(),
                                '[course]' => $arr_id['course_name'] );
                $msg_composer = new EventMessageComposer();

                $msg_composer->setSubjectLangText('email', '_MOD_USER_SUBSCRIPTION_SUBJECT', false);
                $msg_composer->setBodyLangText('email', '_MOD_USER_SUBSCRIPTION_TEXT', $array_subst);

                $msg_composer->setBodyLangText('sms', '_MOD_USER_SUBSCRIPTION_TEXT_SMS', $array_subst);

                // send message to the user subscribed
                createNewAlert(	'UserCourseLevelChanged', 'subscribe', 'modify', '1', 'User subscribed',
                            array($arr_id['idst_user']), $msg_composer  );
            }
        } else {

            $this->last_error = 'error on user course subscription : '.sql_error();
        }*/
        return $re_ins;
    }

    function _delete_by_id($id_course, $idst_user, $course_name)
    {

        if ($this->on_delete == 1)
            $res &= $this->sub_man->suspendUserWithConnection($idst_user, $id_course, $this->get_name());
        else
            $re_ins = $this->sub_man->unsubscribeUserWithConnection($idst_user, $id_course, $this->get_name());
        if ($re_ins === 'jump') return true;
        if ($re_ins) {
            if ($this->sendnotify == 1) {

                $array_subst = array('[url]' => Get::site_url(),
                    '[course]' => $course_name);

                // message to user that is waiting
                $msg_composer = new EventMessageComposer();

                $msg_composer->setSubjectLangText('email', '_DEL_USER_SUBSCRIPTION_SUBJECT', false);
                $msg_composer->setBodyLangText('email', '_DEL_USER_SUBSCRIPTION_TEXT', $array_subst);

                $msg_composer->setBodyLangText('sms', '_DEL_USER_SUBSCRIPTION_TEXT_SMS', $array_subst);

                // send message to the user subscribed
                createNewAlert('UserCourseRemoved', 'subscribe', 'remove', '1', 'User removed form a course',
                    array($idst_user), $msg_composer);
            }
        }
        return $re_ins;
    }

    function delete_bypk($pk)
    {
        $arr_id = $this->get_row_bypk($pk);

        if ($this->on_delete == 1)
            $res &= $this->sub_man->suspendUserWithConnection($arr_id['idst_user'], $arr_id['id_course'], $this->get_name());
        else
            $re_ins = $this->sub_man->unsubscribeUserWithConnection($arr_id['idst_user'], $arr_id['id_course'], $this->get_name());


        if ($re_ins === 'jump') return true;
        if ($re_ins) {
            if ($this->sendnotify == 1) {

                $array_subst = array('[url]' => Get::site_url(),
                    '[course]' => $arr_id['course_name']);

                // message to user that is waiting
                $msg_composer = new EventMessageComposer();

                $msg_composer->setSubjectLangText('email', '_DEL_USER_SUBSCRIPTION_SUBJECT', false);
                $msg_composer->setBodyLangText('email', '_DEL_USER_SUBSCRIPTION_TEXT', $array_subst);

                $msg_composer->setBodyLangText('sms', '_DEL_USER_SUBSCRIPTION_TEXT_SMS', $array_subst);

                // send message to the user subscribed
                createNewAlert('UserCourseRemoved', 'subscribe', 'remove', '1', 'User removed form a course',
                    array($arr_id['idst_user']), $msg_composer);
            }
        }
        return $re_ins;
    }

    function delete_all_filtered($arr_pk)
    {

        $re = true;
        foreach ($arr_pk as $k => $pk) {

            $re &= $this->delete_bypk($pk);
        }
        return $re;
    }


    function delete_all_notinserted()
    {

        //cache course name
        $search_course = "
		SELECT idCourse, name
		FROM " . $GLOBALS['prefix_lms'] . "_course
		WHERE 1";
        $re_course = sql_query($search_course);
        while (list($id_course, $name) = sql_fetch_row($re_course)) {
            $course_name[$id_course] = $name;
        }

        $search_query = "
		SELECT idCourse, idUser
		FROM " . $GLOBALS['prefix_lms'] . "_courseuser 
		WHERE 1";
        if (!empty($this->arr_pair_inserted)) {

            $search_query .= " AND CONCAT(idCourse, '_', idUser) NOT IN (" . implode($this->arr_pair_inserted, ',') . ") ";
        }
        $re_courseuser = sql_query($search_query);
        if (!$re_courseuser) return 0;
        $counter = 0;
        while (list($id_course, $id_user) = sql_fetch_row($re_courseuser)) {

            if ($this->_delete_by_id($id_course, $id_user, $course_name[$id_course])) $counter++;
        }
        return $counter;
    }

    function get_error()
    {
        return $this->last_error;
    }

}

class DoceboConnectorUI_DoceboCourseUserUI extends DoceboConnectorUI
{

    public $connector = NULL;
    public $post_params = NULL;
    public $sh_next = TRUE;
    public $sh_prev = FALSE;
    public $sh_finish = FALSE;
    public $step_next = '';
    public $step_prev = '';

    function DoceboConnectorUI_DoceboCourseUserUI(&$connector)
    {

        $this->connector = $connector;
    }

    function _get_base_name()
    {
        return 'docebocourseuseruiconfig';
    }

    function get_old_name()
    {
        return $this->post_params['old_name'];
    }

    function parse_input($get, $post)
    {

        if (!isset($post[$this->_get_base_name()])) {

            // first call - first step, initialize variables
            $this->post_params = $this->connector->get_config();
            $this->post_params['step'] = '0';
            $this->post_params['old_name'] = $this->post_params['name'];
            if ($this->post_params['name'] == '')
                $this->post_params['name'] = $this->lang->def('_CONN_NAME_EXAMPLE');

        } else {
            // get previous values
            $this->post_params = Util::unserialize(urldecode($post[$this->_get_base_name()]['memory']));
            $arr_new_params = $post[$this->_get_base_name()];
            // overwrite with the new posted values
            foreach ($arr_new_params as $key => $val) {
                if ($key != 'memory' && $key != 'reset') {
                    $this->post_params[$key] = stripslashes($val);
                }
            }
        }
        $this->_load_step_info();
    }

    function _set_step_info($next, $prev, $sh_next, $sh_prev, $sh_finish)
    {
        $this->step_next = $next;
        $this->step_prev = $prev;
        $this->sh_next = $sh_next;
        $this->sh_prev = $sh_prev;
        $this->sh_finish = $sh_finish;
    }

    function _load_step_info()
    {

        $this->_set_step_info('1', '0', FALSE, FALSE, TRUE);
    }

    function go_next()
    {
        $this->post_params['step'] = $this->step_next;
        $this->_load_step_info();
    }

    function go_prev()
    {
        $this->post_params['step'] = $this->step_prev;
        $this->_load_step_info();
    }

    function go_finish()
    {
        $this->filterParams($this->post_params);
        $this->connector->set_config($this->post_params);
    }

    function show_next()
    {
        return $this->sh_next;
    }

    function show_prev()
    {
        return $this->sh_prev;
    }

    function show_finish()
    {
        return $this->sh_finish;
    }

    function get_htmlheader()
    {
        return '';
    }

    function get_html()
    {
        $out = '';
        switch ($this->post_params['step']) {
            case '0':
                $out .= $this->_step0();
                break;
        }
        // save parameters
        $out .= $this->form->getHidden($this->_get_base_name() . '_memory',
            $this->_get_base_name() . '[memory]',
            urlencode(Util::serialize($this->post_params)));

        return $out;
    }

    function _step0()
    {

        // ---- name -----
        $out = $this->form->getTextfield($this->lang->def('_NAME'),
            $this->_get_base_name() . '_name',
            $this->_get_base_name() . '[name]',
            255,
            $this->post_params['name']);
        // ---- description -----
        $out .= $this->form->getSimpleTextarea($this->lang->def('_DESCRIPTION'),
            $this->_get_base_name() . '_description',
            $this->_get_base_name() . '[description]',
            $this->post_params['description']);
        // ---- access type read/write -----
        $out .= $this->form->getRadioSet($this->lang->def('_ACCESSTYPE'),
            $this->_get_base_name() . '_readwrite',
            $this->_get_base_name() . '[readwrite]',
            array($this->lang->def('_READ') => '1',
                $this->lang->def('_WRITE') => '2',
                $this->lang->def('_READWRITE') => '3'),
            $this->post_params['readwrite']);
        // ---- on delete -> delete or unactivate -----
        $out .= $this->form->getRadioSet($this->lang->def('_CANCELED_COURSEUSER'),
            $this->_get_base_name() . '_on_delete',
            $this->_get_base_name() . '[on_delete]',
            array($this->lang->def('_DEACTIVATE') => '1',
                $this->lang->def('_DEL') => '2'),
            $this->post_params['on_delete']);
        // ---- access type read/write -----
        $out .= $this->form->getRadioSet($this->lang->def('_SENDNOTIFY'),
            $this->_get_base_name() . '_sendnotify',
            $this->_get_base_name() . '[sendnotify]',
            array($this->lang->def('_SEND') => '1',
                $this->lang->def('_DONTSEND') => '2'),
            $this->post_params['sendnotify']);

        $out .= $this->form->getRadioSet($this->lang->def('_FIRST_ROW_HEADER'),
            $this->_get_base_name() . '_first_row_header',
            $this->_get_base_name() . '[first_row_header]',
            array($this->lang->def('_YES') => '1',
                $this->lang->def('_NO') => '0'),
            $this->post_params['first_row_header']);

        return $out;
    }
}

function docebocourseuser_factory()
{
    return new DoceboConnector_DoceboCourseUser(array());
}

?>
