<?php

namespace Drupal\civicrm_drush\Commands;

use Drush\Commands\sql\SqlCommands;
use Drupal\civicrm\Civicrm;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A Drush command file.
 */
class CivicrmDrushSqlCommands extends SqlCommands {

  /**
   * An array of options that can be passed to SqlBase.
   *
   * @var array
   */
  protected $civiDbOptions;

  /**
   * The CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * CivicrmDrushSqlCommands constructor.
   *
   * @param \Drupal\civicrm\Civicrm $civicrm
   *   The civicrm service.
   */
  public function __construct(Civicrm $civicrm) {
    $this->civicrm = $civicrm;
  }

  /**
   * Print CiviCRM database connection details.
   *
   * @command civicrm:sql-conf
   */
  public function drushCivicrmSqlconf() {
    $this->civicrmDsnInit();
    $options = array_merge([
      'format' => 'yaml',
      'all' => FALSE,
      'show-passwords' => FALSE,
    ], $this->civiDbOptions);

    return print_r($this->conf($options));
  }

  /**
   * A string for connecting to the CiviCRM DB.
   *
   * @command civicrm:sql-connect
   */
  public function drushCivicrmSqlconnect() {
    $this->civicrmDsnInit();

    return $this->connect($this->civiDbOptions);
  }

  /**
   * Exports the CiviCRM DB as SQL using mysqldump.
   *
   * @command civicrm:sql-dump
   * @optionset_sql
   * @optionset_table_selection
   * @option result-file Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value 'auto', a date-based filename will be created under ~/drush-backups directory.
   * @option create-db Omit DROP TABLE statements. Used by Postgres and Oracle only.
   * @option data-only Dump data without statements to create any of the schema.
   * @option ordered-dump Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.
   * @option gzip Compress the dump using the gzip program which must be in your $PATH.
   * @option extra Add custom arguments/options when connecting to database (used internally to list tables).
   * @option extra-dump Add custom arguments/options to the dumping of the database (e.g. mysqldump command).
   * @usage drush civicrm:sql-dump --result-file=../CiviCRM.sql
   *   Save SQL dump to the directory above Drupal root.
   * @hidden-options create-db
   */
  public function drushCivicrmSqldump(array $options = [
    'result-file' =>
    self::REQ,
    'create-db' => FALSE,
    'data-only' => FALSE,
    'ordered-dump' => FALSE,
    'gzip' => FALSE,
    'extra' => self::REQ,
    'extra-dump' => self::REQ,
    'format' => 'null',
  ]) {
    $this->civicrmDsnInit();
    $this->dump(array_merge($options, $this->civiDbOptions));
  }

  /**
   * Execute a query against the CiviCRM database.
   *
   * @command civicrm:sql-query
   * @usage drush civicrm:sql-query "SELECT * FROM civicrm_contact WHERE id=1"
   *   Browse user record.
   */
  public function drushCivicrmSqlquery(string $query) {
    $this->civicrmDsnInit();
    $this->query($query, $this->civiDbOptions);
  }

  /**
   * Open a SQL command-line interface using CiviCRM's credentials.
   *
   * @command civicrm:sql-cli
   * @aliases cvsqlc
   */
  public function drushCivicrmSqlcli(InputInterface $input) {
    $this->civicrmDsnInit();
    $this->cli($input, $this->civiDbOptions);
  }

  /**
   * Initialise CiviCRM.
   */
  private function civicrmInit() {
    $this->civicrm->initialize();
  }

  /**
   * Initialise CiviCRM.
   */
  private function civicrmDsnInit() {
    $this->civicrmInit();
    $this->civiDbOptions = [
      'db-url' => CIVICRM_DSN,
    ];
  }

}
