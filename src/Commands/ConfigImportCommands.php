<?php

namespace Drupal\config_owner\Commands;

use Drupal\config_owner\MemoryConfigStorage;
use Drupal\config_owner\OwnedConfigManagerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\config\ConfigCommands;
use Drush\Drupal\Commands\config\ConfigImportCommands as CoreConfigImportCommands;

/**
 * Commands for importing the owned configs.
 */
class ConfigImportCommands extends DrushCommands {

  /**
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * @var \Drupal\config_owner\OwnedConfigManagerInterface
   */
  protected $ownedConfigManager;

  /**
   * @var \Drush\Drupal\Commands\config\ConfigImportCommands
   */
  protected $configImportCommands;

  /**
   * ConfigImportCommands constructor.
   *
   * @param \Drush\Drupal\Commands\config\ConfigImportCommands $configImportCommands
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   * @param \Drupal\Core\Config\StorageInterface $activeStorage
   * @param \Drupal\config_owner\OwnedConfigManagerInterface $ownedConfigManager
   */
  public function __construct(CoreConfigImportCommands $configImportCommands, ConfigManagerInterface $configManager, StorageInterface $activeStorage, OwnedConfigManagerInterface $ownedConfigManager) {
    $this->configManager = $configManager;
    $this->activeStorage = $activeStorage;
    $this->ownedConfigManager = $ownedConfigManager;
    $this->configImportCommands = $configImportCommands;
  }

  /**
   * Imports all the owned configs into the active storage.
   *
   * @command config-owner:import
   */
  public function import() {
    $sync_storage = new MemoryConfigStorage();
    foreach ($this->activeStorage->listAll() as $name) {
      $sync_storage->write($name, $this->activeStorage->read($name));
    }
    $configs = $this->ownedConfigManager->getOwnedConfigValues();
    foreach ($configs as $name => $config) {
      $active = $this->activeStorage->read($name);
      foreach ($config as $key => $value) {
        // We only update the values that are actually owned at key level.
        $active[$key] = $value;
      }
      $sync_storage->write($name, $active);
    }

    $storage_comparer = new StorageComparer($sync_storage, $this->activeStorage, $this->configManager);

    if (!$storage_comparer->createChangelist()->hasChanges()) {
      $this->logger()->notice(('There are no changes to import.'));
      return;
    }

    $change_list = [];
    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      $change_list[$collection] = $storage_comparer->getChangelist(null, $collection);
    }
    $table = ConfigCommands::configChangesTable($change_list, $this->output());
    $table->render();

    if ($this->io()->confirm(dt('Import the listed configuration changes?'))) {
      return drush_op([$this->configImportCommands, 'doImport'], $storage_comparer);
    }
  }

}