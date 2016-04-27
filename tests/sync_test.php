<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class enrol_databasegroup_testcase extends advanced_testcase {
    protected static $courses = array();
    protected static $users = array();
    protected static $roles = array();
    
    /** @var string Original error log */
    protected $oldlog;
    
    protected function init_enrol_database() {
        global $DB, $CFG;

        // Discard error logs from AdoDB.
        $this->oldlog = ini_get('error_log');
        ini_set('error_log', "$CFG->dataroot/testlog.log");

        $dbman = $DB->get_manager();

        set_config('dbencoding', 'utf-8', 'enrol_databasegroup');

        set_config('dbhost', $CFG->dbhost, 'enrol_databasegroup');
        set_config('dbuser', $CFG->dbuser, 'enrol_databasegroup');
        set_config('dbpass', $CFG->dbpass, 'enrol_databasegroup');
        set_config('dbname', $CFG->dbname, 'enrol_databasegroup');

        if (!empty($CFG->dboptions['dbport'])) {
            set_config('dbhost', $CFG->dbhost.':'.$CFG->dboptions['dbport'], 'enrol_databasegroup');
        }

        switch ($DB->get_dbfamily()) {

            case 'mysql':
                set_config('dbtype', 'mysqli', 'enrol_databasegroup');
                set_config('dbsetupsql', "SET NAMES 'UTF-8'", 'enrol_databasegroup');
                set_config('dbsybasequoting', '0', 'enrol_databasegroup');
                if (!empty($CFG->dboptions['dbsocket'])) {
                    $dbsocket = $CFG->dboptions['dbsocket'];
                    if ((strpos($dbsocket, '/') === false and strpos($dbsocket, '\\') === false)) {
                        $dbsocket = ini_get('mysqli.default_socket');
                    }
                    set_config('dbtype', 'mysqli://'.rawurlencode($CFG->dbuser).':'.rawurlencode($CFG->dbpass).'@'.rawurlencode($CFG->dbhost).'/'.rawurlencode($CFG->dbname).'?socket='.rawurlencode($dbsocket), 'enrol_databasegroup');
                }
                break;

            case 'oracle':
                set_config('dbtype', 'oci8po', 'enrol_databasegroup');
                set_config('dbsybasequoting', '1', 'enrol_databasegroup');
                break;

            case 'postgres':
                set_config('dbtype', 'postgres7', 'enrol_databasegroup');
                $setupsql = "SET NAMES 'UTF-8'";
                if (!empty($CFG->dboptions['dbschema'])) {
                    $setupsql .= "; SET search_path = '".$CFG->dboptions['dbschema']."'";
                }
                set_config('dbsetupsql', $setupsql, 'enrol_databasegroup');
                set_config('dbsybasequoting', '0', 'enrol_databasegroup');
                if (!empty($CFG->dboptions['dbsocket']) and ($CFG->dbhost === 'localhost' or $CFG->dbhost === '127.0.0.1')) {
                    if (strpos($CFG->dboptions['dbsocket'], '/') !== false) {
                        $socket = $CFG->dboptions['dbsocket'];
                        if (!empty($CFG->dboptions['dbport'])) {
                            $socket .= ':' . $CFG->dboptions['dbport'];
                        }
                        set_config('dbhost', $socket, 'enrol_databasegroup');
                    } else {
                      set_config('dbhost', '', 'enrol_databasegroup');
                    }
                }
                break;

            case 'mssql':
                if (get_class($DB) == 'mssql_native_moodle_database') {
                    set_config('dbtype', 'mssql_n', 'enrol_databasegroup');
                } else {
                    set_config('dbtype', 'mssqlnative', 'enrol_databasegroup');
                }
                set_config('dbsybasequoting', '1', 'enrol_databasegroup');
                break;

            default:
                throw new exception('Unknown database driver '.get_class($DB));
        }

        // NOTE: It is stongly discouraged to create new tables in advanced_testcase classes,
        //       but there is no other simple way to test ext database enrol sync, so let's
        //       disable transactions are try to cleanup after the tests.

        $table = new xmldb_table('enrol_dbgroup_test_enrols');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course_shortname', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('username', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('roleid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('otheruser', XMLDB_TYPE_CHAR, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);
        set_config('remoteenroltable', $CFG->prefix.'enrol_dbgroup_test_enrols', 'enrol_databasegroup');
        set_config('remotecoursefield', 'course_shortname', 'enrol_databasegroup');
        set_config('remoteuserfield', 'username', 'enrol_databasegroup');
        set_config('remoterolefield', 'roleid', 'enrol_databasegroup');
        set_config('remoteotheruserfield', 'otheruser', 'enrol_database');

        $table = new xmldb_table('enrol_dbgroup_test_courses');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);
        set_config('newcoursetable', $CFG->prefix.'enrol_dbgroup_test_courses', 'enrol_databasegroup');
        set_config('newcoursefullname', 'fullname', 'enrol_databasegroup');
        set_config('newcourseshortname', 'shortname', 'enrol_databasegroup');
        set_config('newcourseidnumber', 'idnumber', 'enrol_databasegroup');
        set_config('newcoursecategory', 'category', 'enrol_databasegroup');

        // Create some test users and courses.
        for ($i = 1; $i <= 4; $i++) {
            self::$courses[$i] = $this->getDataGenerator()->create_course(array('fullname' => 'Test course '.$i, 'shortname' => 'tc'.$i, 'idnumber' => 'courseid'.$i));
        }

        for ($i = 1; $i <= 10; $i++) {
            self::$users[$i] = $this->getDataGenerator()->create_user(array('username' => 'username'.$i, 'idnumber' => 'userid'.$i, 'email' => 'user'.$i.'@example.com'));
        }

        foreach (get_all_roles() as $role) {
            self::$roles[$role->shortname] = $role;
        }
    } 
    
    protected function reset_enrol_database() {
        global $DB;

        $DB->delete_records('enrol_dbgroup_test_enrols', array());
        $DB->delete_records('enrol_dbgroup_test_courses', array());

        $plugin = enrol_get_plugin('databasegroup');
        $instances = $DB->get_records('enrol', array('enrol' => 'databasegroup'));
        foreach($instances as $instance) {
            $plugin->delete_instance($instance);
        }
    }
    protected function assertIsEnrolled($userindex, $courseindex, $status=null, $rolename = null) {
        global $DB;
        $dbinstance = $DB->get_record('enrol', array('courseid' => self::$courses[$courseindex]->id, 'enrol' => 'databasegroup'), '*', MUST_EXIST);

        $conditions = array('enrolid' => $dbinstance->id, 'userid' => self::$users[$userindex]->id);
        if ($status !== null) {
            $conditions['status'] = $status;
        }
        $this->assertTrue($DB->record_exists('user_enrolments', $conditions));

        $this->assertHasRoleAssignment($userindex, $courseindex, $rolename);
    }
    
    
    public function test_adding() {
         $this->assertEquals(3, 1+2);
     }
     
     public function test_sync_user_enrolments() {
        global $DB;

        $this->init_enrol_database();

        $this->resetAfterTest(false);
        $this->preventResetByRollback();

        $plugin = enrol_get_plugin('databasegroup');
        
                // Test basic enrol sync for one user after login.

        $this->reset_enrol_database();
        
        /*
        $plugin->set_config('localcoursefield', 'shortname');
        $plugin->set_config('localuserfield', 'username');
        $plugin->set_config('localrolefield', 'shortname');

        $plugin->set_config('defaultrole', self::$roles['student']->id);

        $DB->insert_record('enrol_databasegroup_test_enrols', array('username' => 'userid1', 'course_shortname' => 'courseid1', 'roleid' => 'student'));
        $DB->insert_record('enrol_databasegroup_test_enrols', array('username' => 'userid1', 'course_shortname' => 'courseid2', 'roleid' => 'teacher'));
        $DB->insert_record('enrol_databasegroup_test_enrols', array('username' => 'userid2', 'course_shortname' => 'courseid1', 'roleid' => null));
        $DB->insert_record('enrol_databasegroup_test_enrols', array('username' => 'userid4', 'course_shortname' => 'courseid4', 'roleid' => 'editingteacher', 'otheruser' => '1'));
        $DB->insert_record('enrol_databasegroup_test_enrols', array('username' => 'xxxxxxx', 'course_shortname' => 'courseid1', 'roleid' => 'student')); // Bogus record to be ignored.
        $DB->insert_record('enrol_databasegroup_test_enrols', array('username' => 'userid1', 'course_shortname' => 'xxxxxxxxx', 'roleid' => 'student')); // Bogus record to be ignored.

        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(0, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));

        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
         * 
         */
        
     }
     
 }