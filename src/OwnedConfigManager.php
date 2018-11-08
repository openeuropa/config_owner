<?php

namespace Drupal\config_owner;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\InstallStorage;
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
   * Constructs a new OwnedConfigManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    // Add more services as required.
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, 'owned_config', ['owned_config']);
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
   *
   * @todo cache and invalidate
   */
  public function getOwnedConfigValues(): array {
    $configs = [];
    foreach ($this->getDefinitions() as $definition) {
      /** @var \Drupal\config_owner\OwnedConfig $plugin */
      $plugin = $this->createInstance($definition['id']);
      $types = [
        'install'
        // @todo add the other two types: optional, owned
      ];

      foreach ($types as $type) {
        $type_configs = $plugin->getOwnedConfigValuesByType($type);
        foreach ($type_configs as $name => $values) {
          if (isset($configs[$name])) {
            throw new PluginException('The config %s is marked as owned more than once.', $name);
          }

          $configs[$name] = $values;
        }
      }
    }

    return $configs;
  }
}
