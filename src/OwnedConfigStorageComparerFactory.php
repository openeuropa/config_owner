<?php

namespace Drupal\config_owner;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;

/**
 * Generates a StorageComparer for the owned config.
 *
 * This service produces an instance of StorageComparer that compares the active
 * storage with a version of itself with the owned config applied.
 */
class OwnedConfigStorageComparerFactory {

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
   * ConfigImportCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   * @param \Drupal\Core\Config\StorageInterface $activeStorage
   * @param \Drupal\config_owner\OwnedConfigManagerInterface $ownedConfigManager
   */
  public function __construct(ConfigManagerInterface $configManager, StorageInterface $activeStorage, OwnedConfigManagerInterface $ownedConfigManager) {
    $this->configManager = $configManager;
    $this->activeStorage = $activeStorage;
    $this->ownedConfigManager = $ownedConfigManager;
  }

  /**
   * Creates the StorageComparer instance.
   *
   * @return \Drupal\Core\Config\StorageComparer
   */
  public function create() {
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

    return new StorageComparer($sync_storage, $this->activeStorage, $this->configManager);
  }

}