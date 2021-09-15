INTRODUCTION
------------

The CiviCRM Drush module provides tools to call CiviCRM functionality.


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.

REQUIREMENTS
------------

Need CiviCRM module


USAGE
-----

```
civicrm:api (cvapi)           CLI access to CiviCRM APIs.
civicrm:db-validate           Valid CiviCRM Database.
civicrm:disable-debug         Disable CiviCRM Debugging.
civicrm:enable-debug          Enable CiviCRM Debugging.
civicrm:ext-disable (ced)     Disable a CiviCRM extension.
civicrm:ext-install (cei)     Install a CiviCRM extension.
civicrm:ext-list (cel)        List of CiviCRM extensions enabled.
civicrm:ext-uninstall (ceui)  Uninstall a CiviCRM extension.
civicrm:member-records        Run the CiviMember UpdateMembershipRecord.
civicrm:process-mail-queue    Process pending CiviMail mailing jobs.
civicrm:rest (cvr)            Rest interface for accessing CiviCRM APIs.
civicrm:restore               Restore CiviCRM codebase and database back
                              from the specified backup dir.
civicrm:route-rebuild         Adds a route rebuild option for CiviCRM.
civicrm:sql-cli (cvsqlc)      Open a SQL command-line interface using
                              CiviCRM's credentials.
civicrm:sql-conf              Print CiviCRM database connection details.
civicrm:sql-connect           A string for connecting to the CiviCRM DB.
civicrm:sql-dump              Exports the CiviCRM DB as SQL using mysqldump.
civicrm:sql-query             Execute a query against the CiviCRM database.
civicrm:update-cfg (cvupcfg)  Update config_backend to correct config settings.
civicrm:upgrade (cvup)        Replace CiviCRM codebase with new specified
                              tarfile and upgrade database.
civicrm:upgrade-db (cvupdb)   Execute the civicrm/upgrade?reset=1 process
                              from the command line.
```

CONFIGURATION
-------------

No separate configuration required.
