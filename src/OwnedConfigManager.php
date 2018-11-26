<?php

declare(strict_types = 1);

namespace Drupal\config_owner;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Provides the default owned_config manager.
 */
class OwnedConfigManager extends DefaultPluginManager implements OwnedConfigManagerInterface {

  /**
   * Provides default values for all owned_config plugins.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'install' => '',
  ];

  /**
   * The config dependency validator.
   *
   * @var \Drupal\config_owner\ConfigDependencyValidator
   */
  protected $configDependencyValidator;

  /**
   * Constructs a new OwnedConfigManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\config_owner\ConfigDependencyValidator $config_dependency_validator
   *   The config dependency validator.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, ConfigDependencyValidator $config_dependency_validator) {
    // Add more services as required.
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, 'owned_config', ['owned_config']);
    $this->configDependencyValidator = $config_dependency_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    // You can add validation of the plugin definition here.
    if (empty($definition['id'])) {
      throw new PluginException(sprintf('Example plugin property (%s) definition "is" is required.', $plugin_id));
    }

    $definition['class'] = OwnedConfig::class;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnedConfigValues() {
    $cache = $this->cacheBackend->get('owned_config_values');
    if ($cache && $cache->data) {
      return $cache->data;
    }

    $types = $this->getOwnedConfigTypes();
    $configs = [];

    // We need to organise the configs by type so that we can examine the
    // optional configs for dependencies.
    foreach ($this->getDefinitions() as $definition) {
      /** @var \Drupal\config_owner\OwnedConfig $plugin */
      $plugin = $this->createInstance($definition['id']);
      foreach ($types as $type) {
        $type_configs = $plugin->getOwnedConfigValuesByType($type);
        foreach ($type_configs as $name => $values) {
          if (isset($configs[$type][$name])) {
            throw new PluginException('The config %s is marked as owned more than once.', $name);
          }

          $configs[$type][$name] = $values;
        }
      }
    }

    if (isset($configs[OwnedConfig::OWNED_CONFIG_OPTIONAL])) {
      $configs[OwnedConfig::OWNED_CONFIG_OPTIONAL] = $this->validateOptionalConfigs($configs[OwnedConfig::OWNED_CONFIG_OPTIONAL]);
    }

    $configs = $this->flattenConfigs($configs);

    $this->cacheBackend->set('owned_config_values', $configs);

    return $configs;
  }

  /**
   * {@inheritdoc}
   */
  public function configIsOwned(string $name) {
    $config = $this->getOwnedConfigValues();
    return isset($config[$name]);
  }

  /**
   * Returns the types of the owned config:.
   *
   * - Install folder
   * - Optional folder
   * - Owned folder.
   */
  public static function getOwnedConfigTypes() {
    return [
      OwnedConfig::OWNED_CONFIG_INSTALL,
      OwnedConfig::OWNED_CONFIG_OPTIONAL,
      OwnedConfig::OWNED_CONFIG_OWNED,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('owned_config', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * Flattens the configs grouped by type to one level.
   *
   * @param array $configs
   *   The list of config data to flatten.
   *
   * @return array
   *   The flattened config data.
   */
  protected function flattenConfigs(array $configs) {
    $flat = [];
    foreach ($configs as $type => $type_configs) {
      foreach ($type_configs as $name => $config) {
        if (isset($flat[$name])) {
          throw new PluginException('The config %s is marked as owned more than once.', $name);
        }

        $flat[$name] = $config;
      }
    }

    return $flat;
  }

  /**
   * Validates the optional configs.
   *
   * Checks if they have unmet dependencies and removes them from the list
   * if they do.
   *
   * @param array $configs
   *   The list of config data to validate.
   *
   * @return array
   *   Validated configs.
   */
  protected function validateOptionalConfigs(array $configs) {
    foreach ($configs as $name => $values) {
      if (!$this->configDependencyValidator->validateDependencies($name, $values)) {
        unset($configs[$name]);
      }
    }

    return $configs;
  }

}
