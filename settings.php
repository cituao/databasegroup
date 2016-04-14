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
 * Database enrolment plugin settings and presets.
 *
 * @package    enrol_databasegroup
 * @copyright  2016 Jesus Marquez {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_databasegroup_settings', '', get_string('pluginname_desc', 'enrol_databasegroup')));

    $settings->add(new admin_setting_heading('enrol_databasegroup_exdbheader', get_string('settingsheaderdb', 'enrol_databasegroup'), ''));

    $options = array('', "access","ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('enrol_databasegroup/dbtype', get_string('dbtype', 'enrol_databasegroup'), get_string('dbtype_desc', 'enrol_databasegroup'), '', $options));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/dbhost', get_string('dbhost', 'enrol_databasegroup'), get_string('dbhost_desc', 'enrol_databasegroup'), 'localhost'));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/dbuser', get_string('dbuser', 'enrol_databasegroup'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('enrol_databasegroup/dbpass', get_string('dbpass', 'enrol_databasegroup'), '', ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/dbname', get_string('dbname', 'enrol_databasegroup'), get_string('dbname_desc', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/dbencoding', get_string('dbencoding', 'enrol_databasegroup'), '', 'utf-8'));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/dbsetupsql', get_string('dbsetupsql', 'enrol_databasegroup'), get_string('dbsetupsql_desc', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configcheckbox('enrol_databasegroup/dbsybasequoting', get_string('dbsybasequoting', 'enrol_databasegroup'), get_string('dbsybasequoting_desc', 'enrol_databasegroup'), 0));

    $settings->add(new admin_setting_configcheckbox('enrol_databasegroup/debugdb', get_string('debugdb', 'enrol_databasegroup'), get_string('debugdb_desc', 'enrol_databasegroup'), 0));



    $settings->add(new admin_setting_heading('enrol_databasegroup_localheader', get_string('settingsheaderlocal', 'enrol_databasegroup'), ''));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_databasegroup/localcoursefield', get_string('localcoursefield', 'enrol_databasegroup'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'email'=>'email', 'username'=>'username'); // only local users if username selected, no mnet users!
    $settings->add(new admin_setting_configselect('enrol_databasegroup/localuserfield', get_string('localuserfield', 'enrol_databasegroup'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_databasegroup/localrolefield', get_string('localrolefield', 'enrol_databasegroup'), '', 'shortname', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber');
    $settings->add(new admin_setting_configselect('enrol_databasegroup/localcategoryfield', get_string('localcategoryfield', 'enrol_databasegroup'), '', 'id', $options));


    $settings->add(new admin_setting_heading('enrol_databasegroup_remoteheader', get_string('settingsheaderremote', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/remoteenroltable', get_string('remoteenroltable', 'enrol_databasegroup'), get_string('remoteenroltable_desc', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/remotecoursefield', get_string('remotecoursefield', 'enrol_databasegroup'), get_string('remotecoursefield_desc', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/remoteuserfield', get_string('remoteuserfield', 'enrol_databasegroup'), get_string('remoteuserfield_desc', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/remoterolefield', get_string('remoterolefield', 'enrol_databasegroup'), get_string('remoterolefield_desc', 'enrol_databasegroup'), ''));

    $otheruserfieldlabel = get_string('remoteotheruserfield', 'enrol_databasegroup');
    $otheruserfielddesc  = get_string('remoteotheruserfield_desc', 'enrol_databasegroup');
    $settings->add(new admin_setting_configtext('enrol_databasegroup/remoteotheruserfield', $otheruserfieldlabel, $otheruserfielddesc, ''));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_databasegroup/defaultrole', get_string('defaultrole', 'enrol_databasegroup'), get_string('defaultrole_desc', 'enrol_databasegroup'), $student->id, $options));
    }

    $settings->add(new admin_setting_configcheckbox('enrol_databasegroup/ignorehiddencourses', get_string('ignorehiddencourses', 'enrol_databasegroup'), get_string('ignorehiddencourses_desc', 'enrol_databasegroup'), 0));

    $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                     ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
    $settings->add(new admin_setting_configselect('enrol_databasegroup/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));



    $settings->add(new admin_setting_heading('enrol_databasegroup_newcoursesheader', get_string('settingsheadernewcourses', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/newcoursetable', get_string('newcoursetable', 'enrol_databasegroup'), get_string('newcoursetable_desc', 'enrol_databasegroup'), ''));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/newcoursefullname', get_string('newcoursefullname', 'enrol_databasegroup'), '', 'fullname'));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/newcourseshortname', get_string('newcourseshortname', 'enrol_databasegroup'), '', 'shortname'));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/newcourseidnumber', get_string('newcourseidnumber', 'enrol_databasegroup'), '', 'idnumber'));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/newcoursecategory', get_string('newcoursecategory', 'enrol_databasegroup'), '', ''));

    require_once($CFG->dirroot.'/enrol/databasegroup/settingslib.php');

    $settings->add(new enrol_database_admin_setting_category('enrol_databasegroup/defaultcategory', get_string('defaultcategory', 'enrol_databasegroup'), get_string('defaultcategory_desc', 'enrol_databasegroup')));

    $settings->add(new admin_setting_configtext('enrol_databasegroup/templatecourse', get_string('templatecourse', 'enrol_databasegroup'), get_string('templatecourse_desc', 'enrol_databasegroup'), ''));
}
