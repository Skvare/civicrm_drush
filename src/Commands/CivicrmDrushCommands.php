<?php

namespace Drupal\civicrm_drush\Commands;

use Drupal\civicrm_drush\Util\ConsoleQueueRunner;
use Drupal\civicrm\Civicrm;
use Drupal\Core\Routing\RouteBuilderInterface;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\DrushCommands;
use Drush\Sql\SqlBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @file
 * A Drush command file.
 */
/**
 * List the all civicrm drush command.
 */
class CivicrmDrushCommands extends DrushCommands {

  /**
   * An array of options that can be passed to SqlBase.
   *
   * Create to reference the CiviCRM database.
   *
   * @var array
   */
  protected $civiDbOptions;

  /**
   * A SqlBase object pointing to the CiviCRM database.
   *
   * @var \Drush\Sql\SqlBase
   */
  private $dbObject;

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * The Route Builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  private $routeBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CivicrmDrushCommands constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module_handler service.
   * @param \Drupal\civicrm\Civicrm $civicrm
   *   The civicrm service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $routeBuilder
   *   The Routing service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, Civicrm $civicrm, RouteBuilderInterface $routeBuilder, EntityTypeManagerInterface $entity_type_manager) {
    $this->moduleHandler = $moduleHandler;
    $this->civicrm = $civicrm;
    $this->routeBuilder = $routeBuilder;
    $this->entityTypeManager = $entity_type_manager;
    $this->root = dirname(dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__))));
  }

  /**
   * Adds a cache clear option for CiviCRM.
   *
   * Warning: do not name drush_civicrm_cache_clear() otherwise it will
   * conflict with hook_drush_cache_clear() and be called systematically
   * when "drush cc" is called.
   *
   * @param array $types
   *   The Drush clear types to make available.
   * @param bool $includeBootstrappedTypes
   *   Whether to include types only available in a bootstrapped Drupal or not.
   *
   * @hook on-event cache-clear
   */
  public function drushCivicrmCacheclear(array &$types, $includeBootstrappedTypes) {
    if ($includeBootstrappedTypes && $this->moduleHandler->moduleExists('civicrm')) {
      $types['civicrm'] = [$this, 'civicrmClearCache'];
    }
  }

  /**
   * Adds a route rebuild option for CiviCRM.
   *
   * @command civicrm:route-rebuild
   */
  public function drushCivicrmRouteRebuild() {
    $this->routeBuilder->rebuild();

    $this->output()->writeln(dt('Route rebuild complete.'));
  }

  /**
   * Enable CiviCRM Debugging.
   *
   * @command civicrm:enable-debug
   */
  public function drushCivicrmEnableDebug() {
    $settings = [
      'debug_enabled' => 1,
      'backtrace' => 1,
    ];

    $this->civicrmEnableSettings($settings);
    $this->output()->writeln(dt('CiviCRM debug setting enabled.'));
  }

  /**
   * Disable CiviCRM Debugging.
   *
   * @command civicrm:disable-debug
   */
  public function drushCivicrmDisableDebug() {
    $settings = [
      'debug_enabled' => 0,
      'backtrace' => 0,
    ];

    $this->civicrmEnableSettings($settings);
    $this->output()->writeln(dt('CiviCRM debug setting disabled.'));
  }

  /**
   * Process pending CiviMail mailing jobs.
   *
   * @command civicrm:process-mail-queue
   * @usage civicrm:process-mail-queue -u admin
   *
   * @return string
   *   Output to show the message about process is completed.
   */
  public function drushCivicrmProcessMailQueue(array $options = [
    'uid' => 1,
    'in' => 'args',
    'out' => 'pretty',
    'name' => '',
  ]) {
    $this->civicrmInit();
    $user = NULL;
    if (!is_null($options['name']) && !$user = user_load_by_name($options['name'])) {
      throw new \Exception(dt('Unable to load user by name: !name', ['!name' => $options['name']]));
    }
    if (!empty($options['uid'])) {
      $user = $this->entityTypeManager->getStorage('user')->load($options['uid']);
      if (is_null($user)) {
        throw new \Exception(
          dt("Failed to find Drupal user (!error)", ['!error' => $options['uid']])
        );
      }
    }

    if (is_null($user)) {
      throw new \Exception(dt('uid and name options are empty.'));
    }

    user_login_finalize($user);
    \CRM_Core_BAO_UFMatch::synchronize($user, FALSE, 'Drupal', 'Individual');
    try {
      $result = civicrm_api3('Job', 'process_mailing', []);
    }
    catch (\CiviCRM_API3_Exception $exception) {
      throw new \Exception(
        dt($exception->getMessage() . ', Check User permissions, provide correct user id for --uid parameter')
      );
    }

    return $options['out'] == 'pretty' ? print_r($result, TRUE) : json_encode($result, JSON_PRETTY_PRINT);
  }

  /**
   * Run the CiviMember UpdateMembershipRecord cron (civicrm-member-records).
   *
   * @command civicrm:member-records
   *
   * @return string
   *   Output to show the message about process is completed.
   */
  public function drushCivicrmMemberRecords() {
    // @todo Write functionality.
    return dt('Not implemented yet');
  }

  /**
   * CLI access to CiviCRM APIs.
   *
   * It can return pretty-printor json formatted data.
   *
   * @param array $commands
   *   CiviCRM command to run specific api entity with action.
   * @param array $options
   *   Options for CiviCRM command.
   *
   * @command civicrm:api
   * @option in Input type: "args" (command-line), "json" (STDIN).
   * @option out Output type: "pretty" (STDOUT), "json" (STDOUT).
   * @usage drush civicrm:api contact.create first_name=John last_name=Doe
   * contact_type=Individual
   *   Create a new contact named John Doe.
   * @usage drush civicrm:api contact.create id=1 --out=json
   *   Find/display a contact in JSON format.
   * @aliases cvapi
   *
   * @return mixed
   *   Output to show the message about process is completed.
   */
  public function drushCivicrmApi(array $commands,
                                  array $options = [
                                    'uid' => 1,
                                    'in' => 'args',
                                    'out' => 'pretty',
                                  ]) {
    $default = ['version' => 3];
    $args = $commands;
    [$entity, $action] = explode('.', $args[0]);
    array_shift($args);
    $this->civicrmInit();
    $user = $this->entityTypeManager->getStorage('user')->load($options['uid']);
    if (is_null($user)) {
      throw new \Exception(
        dt("Failed to find Drupal user (!error)", ['!error' => $options['uid']])
      );
    }
    user_login_finalize($user);
    \CRM_Core_BAO_UFMatch::synchronize($user, FALSE, 'Drupal', 'Individual');
    $params = $default;
    if ($options['in'] == 'json') {
      $json = stream_get_contents(STDIN);
      if (empty($json)) {
        $params = $default;
      }
      else {
        $params = array_merge($default, json_decode($json, TRUE));
      }
    }
    else {
      foreach ($args as $arg) {
        $matches = explode('=', $arg);
        $params[$matches[0]] = $matches[1];
      }
    }
    $result = civicrm_api($entity, $action, $params);

    return $options['out'] == 'pretty' ? print_r($result, TRUE) : json_encode($result, JSON_PRETTY_PRINT);
  }

  /**
   * List of CiviCRM extensions enabled.
   *
   * @param array $options
   *   Output format.
   *
   * @command civicrm:ext-list
   * @aliases cel
   * @usage drush civicrm:ext-list
   *   List of CiviCRM extensions in table format.
   * @field-labels
   *   key: App name
   *   status: Status
   * @default-fields key,status
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Tabled output.
   *
   * @throws \Exception
   */
  public function drushCivicrmExtList(array $options = ['format' => 'table']) {
    $this->civicrmInit();
    try {
      $result = civicrm_api3('extension', 'get', [
        'options' => [
          'limit' => 0,
        ],
      ]);
      foreach ($result['values'] as $extension_data) {
        $rows[] = [
          'key' => $extension_data['key'],
          'status' => $extension_data['status'],
        ];
      }

      return new RowsOfFields($rows);
    }
    catch (\CiviCRM_API3_Exception $e) {
      // Handle error here.
      $errorMessage = $e->getMessage();
      throw new \Exception(
        dt("!error", ['!error' => $errorMessage])
      );
    }
  }

  /**
   * Install a CiviCRM extension.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command civicrm:ext-install
   * @usage drush civicrm:ext-install civimobile
   *   Install the civimobile extension.
   * @aliases cei
   */
  public function drushCivicrmExtInstall($name) {
    $this->civicrmExtensionAction($name, 'install', dt('installed'));
  }

  /**
   * Disable a CiviCRM extension.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command civicrm:ext-disable
   * @usage drush civicrm:ext-disable civimobile
   *   Disable the civimobile extension.
   * @aliases ced
   */
  public function drushCivicrmExtDisable($name) {
    $this->civicrmExtensionAction($name, 'disable', dt('disabled'));
  }

  /**
   * Uninstall a CiviCRM extension.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command civicrm:ext-uninstall
   * @usage drush civicrm:ext-uninstall civimobile
   *   Uninstall the civimobile extension.
   * @aliases ceui
   */
  public function drushCivicrmExtUninstall($name) {
    $this->civicrmExtensionAction($name, 'uninstall', dt('uninstalled'));
  }

  /**
   * Update config_backend to correct config settings.
   *
   * Especially when the CiviCRM site has been cloned / migrated.
   *
   * @param string $url
   *   The site url.
   *
   * @command civicrm:update-cfg
   * @usage drush civicrm:update-cfg http://example.com/civicrm
   *   Update config_backend to correct config settings for civicrm
   * installation on example.com site.
   * @aliases cvupcfg
   */
  public function drushCivicrmUpdateCfg(string $url) {
    $this->civicrmInit();
    $defaultValues = [];
    $states = ['old', 'new'];
    for ($i = 1; $i <= 3; $i++) {
      foreach ($states as $state) {
        $name = "{$state}Val_{$i}";
        $value = $url;
        if ($value) {
          $defaultValues[$name] = $value;
        }
      }
    }

    // @todo Refactor to not use BAO?
    $result = \CRM_Core_BAO_ConfigSetting::doSiteMove($defaultValues);

    if ($result) {
      $this->output()->writeln(dt('Config successfully updated.'));
    }
    else {
      $this->output()->writeln(dt('Config update failed.'));
    }

  }

  /**
   * Valid CiviCRM Database.
   *
   * @command civicrm:db-validate
   *
   * @throws \Exception
   */
  public function drushCivicrmUpgradeDbValidate() {
    if (!defined('CIVICRM_UPGRADE_ACTIVE')) {
      define('CIVICRM_UPGRADE_ACTIVE', 1);
    }
    $this->civicrmInit();
    $_POST['upgrade'] = 1;
    $_GET['q'] = 'civicrm/upgrade';
    require_once 'CRM/Core/Config.php';
    require_once 'CRM/Utils/System.php';
    require_once 'CRM/Core/BAO/Domain.php';
    $codeVer = \CRM_Utils_System::version();
    $dbVer = \CRM_Core_BAO_Domain::version();
    if (!$dbVer) {
      throw new \Exception(dt('Version information missing in civicrm database.'));
    }
    elseif (stripos($dbVer, 'upgrade')) {
      throw new \Exception(dt('Database check failed - the database looks to have been partially upgraded. You may want to reload the database with the backup and try the upgrade process again.'));
    }
    elseif (!$codeVer) {
      throw new \Exception(dt('Version information missing in civicrm codebase.'));
    }
    elseif (version_compare($codeVer, $dbVer) > 0) {
      throw new \Exception(dt("Starting with v!dbVer -> v!codeVer upgrade.",
        ['!dbVer' => $dbVer, '!codeVer' => $codeVer]));
    }
    elseif (version_compare($codeVer, $dbVer) < 0) {
      throw new \Exception(dt("Database is marked with an unexpected version '!dbVer' which is higher than that of codebase version '!codeVer'.",
        ['!dbVer' => $dbVer, '!codeVer' => $codeVer]));
    }

    $this->output()->writeln(dt('CiviCRM Database version in sync with code.'));
  }

  /**
   * Execute the civicrm/upgrade?reset=1 process from the command line.
   *
   * @param array $options
   *   Options for CiviCRM command.
   *
   * @command civicrm:upgrade-db
   * @aliases cvupdb
   * @option step Step: "0" (command-line), "0" (STDIN).
   * @option retry Retry: "0" (command-line), "0" (STDIN).
   * @option skip Skip: "0" (command-line), "0" (STDIN).
   * @option dry-run Dry Run: "0" (command-line), "0" (STDIN).
   * @option out Output type: "pretty" (STDOUT), "json" (STDOUT).
   */
  public function drushCivicrmUpgradeDb(array $options = [
    'step' => 0,
    'retry' => 0,
    'skip' => 0,
    'dry-run' => 0,
    'out' => 'pretty',
  ]) {
    $this->civicrmInit();
    $niceMsgVerbosity = $options['out'] === 'pretty' ?
      OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE;

    $isFirstTry = !$options['retry'] && !$options['skip'];

    $codeVer = \CRM_Utils_System::version();
    $dbVer = \CRM_Core_BAO_Domain::version();
    $postUpgradeMessageFile = \CRM_Utils_File::tempnam('civicrm-post-upgrade');
    $this->output()->writeln(sprintf("<info>Found CiviCRM database version <comment>%s</comment>.</info>", $dbVer), $niceMsgVerbosity);
    $this->output()->writeln(sprintf("<info>Found CiviCRM code version <comment>%s</comment>.</info>", $codeVer), $niceMsgVerbosity);

    if (version_compare($codeVer, $dbVer) == 0) {
      $result = [
        'latestVer' => $codeVer,
        'message' => "You are already upgraded to CiviCRM $codeVer",
      ];
      $result['text'] = $result['message'];
      $this->output()->writeln("Upgrade outputs: " . "\"" . $result['text'] . "\"");
      unlink($postUpgradeMessageFile);

      return 1;
    }

    if ($isFirstTry && FALSE !== stripos($dbVer, 'upgrade')) {
      $this->output()->writeln("<error>Cannot begin upgrade: The database indicates that an incomplete upgrade is pending. If you would like to resume, use --retry or --skip.</error>");

      return 1;
    }
    if (!$isFirstTry && !file_exists($postUpgradeMessageFile)) {
      $this->output()->writeln("<error>Cannot resume upgrade: The log file ($postUpgradeMessageFile) is missing. Consider a regular upgrade (without --retry or --skip).</error>");

      return 1;
    }

    $upgrade = new \CRM_Upgrade_Form();

    if ($error = $upgrade->checkUpgradeableVersion($dbVer, $codeVer)) {
      $this->output()->writeln("<error>{$error}</error>");

      return 1;
    }

    if ($isFirstTry) {
      $this->output()->writeln("<info>Checking pre-upgrade messages...</info>", $niceMsgVerbosity);
      $preUpgradeMessage = NULL;
      $upgrade->setPreUpgradeMessage($preUpgradeMessage, $dbVer, $codeVer);
      if ($preUpgradeMessage) {
        $this->output()->writeln(\CRM_Utils_String::htmlToText($preUpgradeMessage), $niceMsgVerbosity);
        if (!$this->io()->confirm('Continue?')) {
          $this->output()->writeln("<error>Abort</error>");

          return 1;
        }
      }
      else {
        $this->output()->writeln("(No messages)", $niceMsgVerbosity);
      }
    }

    // Why is dropTriggers() hard-coded? Can't we just enqueue this as part
    // of buildQueue()?
    if ($isFirstTry) {
      $this->output()->writeln("<info>Dropping SQL triggers...</info>", $niceMsgVerbosity);
      if (!$options['dry-run']) {
        \CRM_Core_DAO::dropTriggers();
      }
    }
    if ($isFirstTry) {
      $this->output()->writeln("<info>Preparing upgrade...</info>", $niceMsgVerbosity);
      file_put_contents($postUpgradeMessageFile, "");
      chmod($postUpgradeMessageFile, 0700);
      $queue = \CRM_Upgrade_Form::buildQueue($dbVer, $codeVer, $postUpgradeMessageFile);
      if (!($queue instanceof \CRM_Queue_Queue_Sql)) {
        // Sanity check -- only SQL queues are resuamble.
        $this->output()->writeln("<error>Error: \"drush civicrm:upgrade-db\" only supports SQL-based queues.</error>");

        return 1;
      }
    }
    else {
      $this->output()->writeln("<info>Resuming upgrade...</info>",
        $niceMsgVerbosity);
      $queue = \CRM_Queue_Service::singleton()->load([
        'name' => \CRM_Upgrade_Form::QUEUE_NAME,
        'type' => 'Sql',
      ]);

      if ($options['skip']) {
        $item = $queue->stealItem();
        $this->output()->writeln(sprintf("<error>Skip task: %s</error>",
          $item->data->title));
        $queue->deleteItem($item);
      }
    }

    $this->output()->writeln("<info>Executing upgrade...</info>", $niceMsgVerbosity);
    $runner = new ConsoleQueueRunner($this->io(), $queue, $options['dry-run'],
      $options['step']);
    $runner->runAll();

    $this->output()->writeln("<info>Finishing upgrade...</info>", $niceMsgVerbosity);
    if (!$options['dry-run']) {
      \CRM_Upgrade_Form::doFinish();
    }

    $this->output()->writeln("<info>Upgrade to <comment>$codeVer</comment> completed.</info>", $niceMsgVerbosity);

    $this->output()->writeln("<info>Checking post-upgrade messages...</info>", $niceMsgVerbosity);
    $message = file_get_contents($postUpgradeMessageFile);
    $result = [
      'latestVer' => $codeVer,
      'message' => $message,
      'text' => \CRM_Utils_String::htmlToText($message),
    ];

    $this->output()->writeln("Upgrade outputs: " . "\"" . $result['text'] . "\"");
    unlink($postUpgradeMessageFile);
  }

  /**
   * Replace CiviCRM codebase with new specified tarfile and upgrade database.
   *
   * By executing the CiviCRM upgrade process - civicrm/upgrade?reset=1.
   *
   * @todo Do we need to validate?
   *
   * @command civicrm:upgrade
   * @option tarfile Path of new CiviCRM tarfile, with which current CiviCRM
   * codebase is to be replaced.
   * @option backup-dir Specify a directory to backup current CiviCRM codebase
   * and database into, defaults to a backup directory above your Drupal root.
   * @usage drush civicrm:upgrade --tarfile=~/civicrm-4.1.2-drupal.tar.gz
   *   Replace old CiviCRM codebase with new v4.1.2 and run upgrade process.
   * @aliases cvup
   *
   * @throws \Exception
   */
  public function drushCivicrmUpgrade(array $options = [
    'tarfile' => NULL,
    'backup-dir' => NULL,
  ]) {
    // @todo Write functionality.
    throw new \Exception(dt('Not implemented yet.'));
  }

  /**
   * Restore CiviCRM codebase and database back from the specified backup dir.
   *
   * @todo Do we need to validate?
   *
   * @command civicrm:restore
   * @option restore-dir Path of directory having backed up CiviCRM codebase
   * and database.
   * @option backup-dir Specify a directory to backup current CiviCRM codebase
   * and database into, defaults to a backup directory above your Drupal root.
   * @usage drush civicrm:restore --restore-dir=../backup/modules/20100309200249
   *   Replace current civicrm codebase with the $restore-dir/civicrm codebase,
   * and reload the database with $restore-dir/civicrm.sql file.
   *
   * @throws \Exception
   */
  public function drushCivicrmRestore(array $options = [
    'restore-dir' => NULL,
    'backup-dir' => NULL,
  ]) {

    // @todo Can we place the validation in a drush_hook_COMMAND_validate?
    $restore_dir = $options['restore-dir'];
    $restore_dir = rtrim($restore_dir, '/');

    $drupal_root = $this->root;
    $civicrm_root_base = '';
    $this->civicrmDsnInit();
    $dbSpec = $this->dbObject->getDbSpec();
    $restore_backup_dir = isset($options['backup-dir']) ? $options['backup-dir'] : $drupal_root . '/backup';
    $restore_backup_dir = rtrim($restore_backup_dir, '/');

    $this->output->write([
      '',
      dt("Process involves:"),
      dt("1. Restoring '!restoreDir/civicrm' directory to '!toDir'.",
        ['!restoreDir' => $restore_dir, '!toDir' => $civicrm_root_base]
      ),
      dt("2. Dropping and creating '!db' database.",
        ['!db' => $dbSpec['database']]
      ),
      dt("3. Loading '!restoreDir/civicrm.sql' file into the database.",
        ['!restoreDir' => $restore_dir]
      ),
      '',
      dt("Note: Before restoring a backup will be taken in '!path' directory.",
        ['!path' => "$restore_backup_dir/modules/restore"]
      ),
      '',
    ], TRUE);

    throw new \Exception(dt('Not implemented yet.'));
  }

  /**
   * Rest interface for accessing CiviCRM APIs.
   *
   * It can return xml or json formatted data.
   *
   * @todo Do we need to validate?
   *
   * @command civicrm:rest
   * @option query Query part of url. Refer CiviCRM wiki doc for more details.
   * @usage drush civicrm:rest --query='civicrm/contact/search&json=1&key=7decb879f28ac4a0c6a92f0f7889a0c9&api_key=7decb879f28ac4a0c6a92f0f7889a0c9'
   *   Use contact search api to return data in json format.
   * @aliases cvr
   *
   * @throws \Exception
   */
  public function drushCivicrmRest(array $options = ['query' => NULL]) {
    // @todo Write functionality.
    throw new \Exception(dt('Not implemented yet.'));
  }

  /**
   * Initialise CiviCRM.
   */
  private function civicrmInit() {
    $this->civicrm->initialize();
  }

  /**
   * Clear civicrm caches using the API.
   *
   * @param array $options
   *   Options for CiviCRM command.
   *
   * @throws \Exception
   */
  public function civicrmClearCache(array $options = []) {
    $this->civicrmInit();

    if ($options['triggers']) {
      $params['triggers'] = 1;
    }

    if ($options['session']) {
      $params['session'] = 1;
    }

    // Need to set API version or drush cc civicrm fails.
    $params['version'] = 3;
    $result = civicrm_api('System', 'flush', $params);

    if ($result['is_error']) {
      throw new \Exception(dt('An error occurred: !message', ['!message' => $result['error_message']]));
    }
    $this->logger()->info(dt('The CiviCRM cache has been cleared.'));
  }

  /**
   * Enable settings for CiviCRM.
   *
   * @param array $settings
   *   An array containing the keys and values.
   *
   * @throws \Exception
   */
  private function civicrmEnableSettings(array $settings) {
    $this->civicrmInit();
    foreach ($settings as $key => $val) {
      $result = civicrm_api('Setting', 'create', ['version' => 3, $key => $val]);

      if ($result['is_error']) {
        throw new \Exception(dt('An error occurred: !message', ['!message' => $result['error_message']]));
      }
    }
  }

  /**
   * Execute an action on an extension.
   *
   * @param string $name
   *   The name of the extension.
   * @param string $action
   *   The action.
   * @param string $message
   *   The message for the action.
   *
   * @throws \Exception
   */
  private function civicrmExtensionAction($name, $action, $message) {
    $this->civicrmInit();

    try {
      $result = civicrm_api('extension', $action,
        ['key' => $name, 'version' => 3]);
      if ($result['values'] && $result['values'] == 1) {
        $this->output->writeln(dt("Extension !ename !message.",
          ['!ename' => $name, '!message' => $message]));
      }
      else {
        throw new \Exception(dt('Extension !ename could not be !message.',
          ['!ename' => $name, '!message' => $message]));
      }
    }
    catch (\CiviCRM_API3_Exception $e) {
      $errorMessage = $e->getMessage();
      throw \Exception(dt("!error", ['!error' => $errorMessage]));
    }
  }

  /**
   * Initialise CiviCRM.
   */
  private function civicrmDsnInit() {
    $this->civicrmInit();
    $this->civiDbOptions = [
      'db-url' => CIVICRM_DSN,
    ];
    $this->dbObject = SqlBase::create($this->civiDbOptions);
  }

}
