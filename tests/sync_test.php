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
 * External databasegroup enrolment sync tests, this also tests adodb drivers
 * that are matching our four supported Moodle database drivers.
 *
 * @package    enrol_databasegroup
 * @category   phpunit
 * @copyright  2011 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/group/lib.php');

class enrol_databasegroup_testcase extends advanced_testcase {
    protected static $courses = array();
    protected static $users = array();
    protected static $roles = array();
    protected static $groups = array();
    
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
        $table->add_field('courseid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('userid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('roleid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('groupid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('otheruser', XMLDB_TYPE_CHAR, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);
        set_config('remoteenroltable', $CFG->prefix.'enrol_dbgroup_test_enrols', 'enrol_databasegroup');
        set_config('remotecoursefield', 'courseid', 'enrol_databasegroup');
        set_config('remoteuserfield', 'userid', 'enrol_databasegroup');
        set_config('remoterolefield', 'roleid', 'enrol_databasegroup');
        set_config('remotegroupfield', 'groupid', 'enrol_databasegroup');
        set_config('remoteotheruserfield', 'otheruser', 'enrol_databasegroup');
        

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

        // Create some test users, courses and groups.
        for ($i = 1; $i <= 4; $i++) {
            self::$courses[$i] = $this->getDataGenerator()->create_course(array('fullname' => 'Test course '.$i, 'shortname' => 'tc'.$i, 'idnumber' => 'courseid'.$i));
        }

        for ($i = 1; $i <= 10; $i++) {
            self::$users[$i] = $this->getDataGenerator()->create_user(array('username' => 'username'.$i, 'idnumber' => 'userid'.$i, 'email' => 'user'.$i.'@example.com'));
        }
        
        for ($i = 1; $i <= 4; $i++) {
            self::$groups[$i] = $this->getDataGenerator()->create_group(array('courseid' => self::$courses[$i]->id));
        }

        //$this->getDataGenerator()->enrol_user(self::$users[1]->id, self::$courses[1]->id);
        //$this->getDataGenerator()->create_group_member(array('userid' => self::$users[1]->id, 'groupid' => self::$groups[1]->id));
        
        $component = "enrol_databasegroup";
        $itemid = 0;
        //$this->getDataGenerator()->create_group_member(array('userid' => self::$users[1]->id, 'groupid' => self::$groups[1]->id, 'component' => $component, 'itemid' => $itemid));
        
        //groups_add_member(self::$groups[1]->id, self::$users[1]->id, 'databasegroup', '123' );
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
    
    
    protected function assertHasRoleAssignment($userindex, $courseindex, $rolename = null) {
        global $DB;
        $dbinstance = $DB->get_record('enrol', array('courseid' => self::$courses[$courseindex]->id, 'enrol' => 'databasegroup'), '*', MUST_EXIST);

        $coursecontext = context_course::instance(self::$courses[$courseindex]->id);
        if ($rolename === false) {
            $this->assertFalse($DB->record_exists('role_assignments', array('component' => 'enrol_databasegroup', 'itemid' => $dbinstance->id, 'userid' => self::$users[$userindex]->id, 'contextid' => $coursecontext->id)));
        } else if ($rolename !== null) {
            $this->assertTrue($DB->record_exists('role_assignments', array('component' => 'enrol_databasegroup', 'itemid' => $dbinstance->id, 'userid' => self::$users[$userindex]->id, 'contextid' => $coursecontext->id, 'roleid' => self::$roles[$rolename]->id)));
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
    
    protected function assertIsNotEnrolled($userindex, $courseindex) {
        global $DB;
        if (!$dbinstance = $DB->get_record('enrol', array('courseid' => self::$courses[$courseindex]->id, 'enrol' => 'databasegroup'))) {
            return;
        }
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $dbinstance->id, 'userid' => self::$users[$userindex]->id)));
    }
    
   
    public function test_sync_user_enrolments() {
        global $DB;

        $this->init_enrol_database();

        $this->resetAfterTest(false);
        $this->preventResetByRollback();

        $plugin = enrol_get_plugin('databasegroup');
        
        // Test basic enrol sync for one user after login.

        $this->reset_enrol_database();
       
        $plugin->set_config('localcoursefield', 'idnumber');
        $plugin->set_config('localuserfield', 'idnumber');
        $plugin->set_config('localrolefield', 'shortname');
        $plugin->set_config('localgroupfield', 'idnumber');
        
        $plugin->set_config('defaultrole', self::$roles['student']->id);

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student', 'groupid' => 'group1'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid2', 'roleid' => 'teacher' , 'groupid' => 'group1'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid2', 'courseid' => 'courseid1', 'roleid' => null , 'groupid' => 'group1'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid4', 'courseid' => 'courseid4', 'roleid' => 'editingteacher' , 'groupid' => 'group1', 'otheruser' => '1'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'xxxxxxx', 'courseid' => 'courseid1', 'roleid' => 'student', 'groupid' => 'group1')); // Bogus record to be ignored.
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'xxxxxxxxx', 'roleid' => 'student', 'groupid' => 'group1')); // Bogus record to be ignored.

        
        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(0, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertEquals(0, $DB->count_records('groups', array('idnumber' => 'group1')));
        
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        $this->assertEquals(2, $DB->count_records('groups', array('idnumber' => 'group1')));
        //check groups
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));

        // Make sure there are no errors or changes on the next login.
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        //check groups
        $this->assertEquals(2, $DB->count_records('groups', array('idnumber' => 'group1')));
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        
        //user # 2
        $plugin->sync_user_enrolments(self::$users[2]);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(3, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        //check groups
        $this->assertEquals(2, $DB->count_records('groups', array('idnumber' => 'group1')));
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $this->assertEquals(true, groups_is_member($group->id, self::$users[2]->id));
        //user # 4
        $plugin->sync_user_enrolments(self::$users[4]);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        //check groups this user not enroll
        $this->assertEquals(2, $DB->count_records('groups', array('idnumber' => 'group1')));
        $group = groups_get_group_by_idnumber(self::$courses[4]->id, 'group1');
        $this->assertEquals(false, $group);
        
        // Enrolment removals.
        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher'); 
        //test groups
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
   
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND);
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_SUSPENDED, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        //test groups
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        //test groups
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        
        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(3, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_SUSPENDED, false);
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        //test groups
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        //test groups
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        
        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(3, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsNotEnrolled(1, 1);
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        //test groups
        $group = groups_get_group_by_idnumber(self::$courses[1]->id, 'group1');
        $this->assertEquals(false, groups_is_member($group->id, self::$users[1]->id));
        $group = groups_get_group_by_idnumber(self::$courses[2]->id, 'group1');
        $this->assertEquals(true, groups_is_member($group->id, self::$users[1]->id));
        
        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid4', 'courseid' => 'courseid4', 'roleid' => 'editingteacher'));
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
        $plugin->sync_user_enrolments(self::$users[4]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, false);
        //check groups this user not enroll
        $this->assertEquals(2, $DB->count_records('groups', array('idnumber' => 'group1')));
        $group = groups_get_group_by_idnumber(self::$courses[4]->id, 'group1');
        $this->assertEquals(false, $group);
        
        /*
        $this->reset_enrol_database();

        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(0, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
       
        $plugin->set_config('localcoursefield', 'id');
        $plugin->set_config('localuserfield', 'id');
        $plugin->set_config('localrolefield', 'id');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->id, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->id, 'courseid' => self::$courses[2]->id, 'roleid' => self::$roles['teacher']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[2]->id, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));

        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        
        $this->reset_enrol_database();
        $plugin->set_config('localcoursefield', 'shortname');
        $plugin->set_config('localuserfield', 'email');
        $plugin->set_config('localrolefield', 'id');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->email, 'courseid' => self::$courses[1]->shortname, 'roleid' => self::$roles['student']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->email, 'courseid' => self::$courses[2]->shortname, 'roleid' => self::$roles['teacher']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[2]->email, 'courseid' => self::$courses[1]->shortname, 'roleid' => self::$roles['student']->id));

        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
      
        $this->reset_enrol_database();
        $plugin->set_config('localcoursefield', 'id');
        $plugin->set_config('localuserfield', 'username');
        $plugin->set_config('localrolefield', 'id');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->username, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->username, 'courseid' => self::$courses[2]->id, 'roleid' => self::$roles['teacher']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[2]->username, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));

        $plugin->sync_user_enrolments(self::$users[1]);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
         * 
         */
        
     }
      
     
        
    /**
     * @depends test_sync_user_enrolments
     */
     /*
    public function test_sync_users() {
        global $DB;

        $this->resetAfterTest(false);
        $this->preventResetByRollback();
        $this->reset_enrol_database();

        $plugin = enrol_get_plugin('databasegroup');

        $trace = new null_progress_trace();

        // Test basic enrol sync for one user after login.

        $this->reset_enrol_database();
        $plugin->set_config('localcoursefield', 'idnumber');
        $plugin->set_config('localuserfield', 'idnumber');
        $plugin->set_config('localrolefield', 'shortname');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid2', 'roleid' => 'editingteacher'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid2', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid4', 'courseid' => 'courseid4', 'roleid' => 'editingteacher', 'otheruser' => '1'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'xxxxxxx', 'courseid' => 'courseid1', 'roleid' => 'student')); // Bogus record to be ignored.
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'xxxxxxxxx', 'roleid' => 'student')); // Bogus record to be ignored.
        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(0, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        
        
        $plugin->sync_enrolments($trace);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        
        
        $plugin->set_config('defaultrole', self::$roles['teacher']->id);
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid3', 'courseid' => 'courseid3'));
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');
        
        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');        
        

        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND);
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_SUSPENDED, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');
        
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');
        
        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_SUSPENDED, false);
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');
        
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');
        
        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
        $plugin->sync_enrolments($trace);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsNotEnrolled(1, 1);
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'student'));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'teacher'));
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(6, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'teacher');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');

        $DB->delete_records('enrol_dbgroup_test_enrols', array('userid' => 'userid1', 'courseid' => 'courseid1', 'roleid' => 'teacher'));
        $plugin->sync_enrolments($trace);
        $this->assertEquals(4, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'editingteacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsNotEnrolled(4, 4);
        $this->assertHasRoleAssignment(4, 4, 'editingteacher');
        $this->assertIsEnrolled(3, 3, ENROL_USER_ACTIVE, 'teacher');
        
         // Test all other mapping options.

        $this->reset_enrol_database();

        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(0, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));

        $plugin->set_config('localcoursefield', 'id');
        $plugin->set_config('localuserfield', 'id');
        $plugin->set_config('localrolefield', 'id');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->id, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->id, 'courseid' => self::$courses[2]->id, 'roleid' => self::$roles['teacher']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[2]->id, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));

        $plugin->sync_enrolments($trace);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(3, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');


        $this->reset_enrol_database();
        $plugin->set_config('localcoursefield', 'shortname');
        $plugin->set_config('localuserfield', 'email');
        $plugin->set_config('localrolefield', 'id');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->email, 'courseid' => self::$courses[1]->shortname, 'roleid' => self::$roles['student']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->email, 'courseid' => self::$courses[2]->shortname, 'roleid' => self::$roles['teacher']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[2]->email, 'courseid' => self::$courses[1]->shortname, 'roleid' => self::$roles['student']->id));

        $plugin->sync_enrolments($trace);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(3, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');


        $this->reset_enrol_database();
        $plugin->set_config('localcoursefield', 'id');
        $plugin->set_config('localuserfield', 'username');
        $plugin->set_config('localrolefield', 'id');

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->username, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->username, 'courseid' => self::$courses[2]->id, 'roleid' => self::$roles['teacher']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[2]->username, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));

        $plugin->sync_enrolments($trace);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(3, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');


        // Test sync of one course only.

        $this->reset_enrol_database();

        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->username, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[1]->username, 'courseid' => self::$courses[2]->id, 'roleid' => self::$roles['teacher']->id));
        $DB->insert_record('enrol_dbgroup_test_enrols', array('userid' => self::$users[2]->username, 'courseid' => self::$courses[1]->id, 'roleid' => self::$roles['student']->id));

        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(0, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));

        $plugin->sync_enrolments($trace, self::$courses[3]->id);
        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(1, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));

        $plugin->sync_enrolments($trace, self::$courses[1]->id);
        $this->assertEquals(2, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(2, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');

        $plugin->sync_enrolments($trace, self::$courses[2]->id);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(3, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 1, ENROL_USER_ACTIVE, 'student');
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');
        $this->assertIsEnrolled(2, 1, ENROL_USER_ACTIVE, 'student');


        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        $DB->delete_records('enrol_dbgroup_test_enrols', array());

        $plugin->sync_enrolments($trace, self::$courses[1]->id);
        $this->assertEquals(1, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(1, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        $this->assertIsEnrolled(1, 2, ENROL_USER_ACTIVE, 'teacher');

        $plugin->sync_enrolments($trace, self::$courses[2]->id);
        $this->assertEquals(0, $DB->count_records('user_enrolments', array()));
        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'databasegroup')));
        $this->assertEquals(0, $DB->count_records('role_assignments', array('component' => 'enrol_databasegroup')));
        
     
     }
     */
 }