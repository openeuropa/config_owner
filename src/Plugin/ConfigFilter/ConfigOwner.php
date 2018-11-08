<?php

namespace Drupal\config_owner\Plugin\ConfigFilter;

use Drupal\config_filter\Plugin\ConfigFilterBase;
use Drupal\config_owner\OwnedConfigManagerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the filter for Config Owner.
 *
 * We are filtering the sync storage in view of two things:
 *
 * - Prevent changes to the staging (file) storage to override owned config in the
 * active storage.
 * - Prevent the export to the staging (file) storage of changes made to the
 * owned config.
 *
 * @ConfigFilter(
 *   id = "config_owner",
 *   label = "Config Owner",
 *   weight = 10,
 * )
 */
class ConfigOwner extends ConfigFilterBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  /**
   * @var \Drupal\config_owner\OwnedConfigManagerInterface
   */
  protected $ownedConfigManager;

  /**
   * ConfigOwner constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param array $plugin_definition
   * @param \Drupal\config_owner\OwnedConfigManagerInterface $ownedConfigManager
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, OwnedConfigManagerInterface $ownedConfigManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ownedConfigManager = $ownedConfigManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.owned_config')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Reading one configuration from the staging (file) storage.
   */
  public function filterRead($name, $data) {
    $owned_configs = $this->getOwnedConfig();
    if (!isset($owned_configs[$name])) {
      return $data;
    }

    // For owned config, we want to make sure that whatever is read from the
    // staging storage doesn't really matter. What counts is the original owned
    // config. This will also prevent Drupal from knowing if the staging config
    // has changes compared to the original owned config.
    $owned_config = $owned_configs[$name];
    foreach ($owned_config as $key => $value) {
      $data[$key] = $value;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * Reading the entire set of configuration from the staging (file) storage.
   */
  public function filterReadMultiple(array $names, array $data) {
    $owned_config = $this->getOwnedConfig();
    foreach (array_keys($owned_config) as $name) {
      if (isset($data[$name])) {
        $data[$name] = $this->filterRead($name, $data[$name]);
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * Writing one configuration to the staging (file) storage.
   *
   * We ensure that whatever gets exported to the staging storage, is in line
   * with the owned config values.
   */
  public function filterWrite($name, array $data) {
    $owned_config = $this->getOwnedConfig();
    if (!isset($owned_config[$name])) {
      return $data;
    }

    $owned = $owned_config[$name];
    foreach ($owned as $key => $value) {
      $data[$key] = $value;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterDelete($name, $delete) {
    $owned_config = $this->getOwnedConfig();
    if (!isset($owned_config[$name])) {
      return $delete;
    }

    // No config that is owned can be deleted.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function filterRename($name, $new_name, $rename) {
    $owned_config = $this->getOwnedConfig();
    if (!isset($owned_config[$name])) {
      return $rename;
    }

    // No config that is owned can be renamed.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function filterDeleteAll($prefix, $delete) {
    $owned_config = $this->getOwnedConfig();
    foreach (array_keys($owned_config) as $name) {
      if ($prefix !== '' && strpos($name, $prefix) === 0) {
        // If the prefix would delete any of the owned configs, we must not
        // allow this operation.
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Returns all the owned config values.
   *
   * @return array
   */
  protected function getOwnedConfig() {
    return $this->ownedConfigManager->getOwnedConfigValues();
  }

}