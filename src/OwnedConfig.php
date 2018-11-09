<?php

namespace Drupal\config_owner;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * OwnedConfig plugin instance class.
 */
class OwnedConfig extends PluginBase {

  /**
   * The three types of owned config.
   */
  const OWNED_CONFIG_INSTALL = 'install';
  const OWNED_CONFIG_OPTIONAL = 'optional';
  const OWNED_CONFIG_OWNED = 'owned';

  /**
   * The directory where owned config resides (config state).
   */
  const CONFIG_OWNED_DIRECTORY = 'config/owned';

  /**
   * Returns the owned config values of a given type (directory);
   *
   * @param string $type
   *
   * @return array
   */
  public function getOwnedConfigValuesByType(string $type) {
    $definition = $this->getPluginDefinition();
    if (!isset($definition[$type])) {
      return [];
    }

    $storage = $this->getStorage($type);
    $prepared_definition = $this->prepareConfigDefinition($definition[$type], $storage);
    $configs = $this->getOwnedConfigValues($prepared_definition, $storage);

    return $configs;
  }

  /**
   * Given an array of config definitions specified in the plugin, return the
   * owned config values that must not be changed.
   *
   * @param array $config_definitions
   * @param \Drupal\Core\Config\FileStorage $storage
   *
   * @return array
   */
  protected function getOwnedConfigValues(array $config_definitions, FileStorage $storage) {
    $configs = [];
    foreach ($config_definitions as $name => $info) {
      $original_config = $storage->read($name);
      if (!$info || !isset($info['keys']) || !$info['keys']) {
        // In case no keys are specified, the entire config data is considered.
        $configs[$name] = $original_config;
        continue;
      }

      // Otherwise, we only consider the specified keys.
      $config = [];
      foreach ($info['keys'] as $key) {
        if (!isset($original_config[$key])) {
          continue;
        }

        $config[$key] = $original_config[$key];
      }
      $configs[$name] = $config;
    }

    return $configs;
  }

  /**
   * Gets the config file storage for the module this plugin belongs to.
   *
   * @param string $location
   *
   * @return \Drupal\Core\Config\FileStorage
   */
  protected function getStorage($location = self::OWNED_CONFIG_INSTALL) {
    $directory_map = [
      self::OWNED_CONFIG_INSTALL => InstallStorage::CONFIG_INSTALL_DIRECTORY,
      self::OWNED_CONFIG_OPTIONAL => InstallStorage::CONFIG_OPTIONAL_DIRECTORY,
      self::OWNED_CONFIG_OWNED => self::CONFIG_OWNED_DIRECTORY,
    ];

    $directory = $directory_map[$location];
    $path = drupal_get_path('module', $this->getPluginDefinition()['provider']);
    return new FileStorage($path . '/' . $directory, StorageInterface::DEFAULT_COLLECTION);
  }

  /**
   * Prepares the config definition for config extracting.
   *
   * Plugins may define configs using wildcards so we need to match those.
   *
   * @param $definition
   * @param \Drupal\Core\Config\FileStorage $storage
   *
   * @return array
   */
  protected function prepareConfigDefinition($definition, FileStorage $storage) {
    $prepared = [];
    $available_names = $storage->listAll();
    foreach ($definition as $name => $info) {
      if ($storage->exists($name)) {
        // In this case the full config name was defined.
        $prepared[$name] = $info;
        continue;
      }

      foreach ($available_names as $available_name) {
        if (fnmatch($name, $available_name)) {
          // For all the matches, we use the same info.
          $prepared[$available_name] = $info;
        }
      }
    }

    return $prepared;
  }
}