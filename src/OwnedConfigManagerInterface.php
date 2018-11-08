<?php

namespace Drupal\config_owner;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines an interface for owned_config managers.
 */
interface OwnedConfigManagerInterface extends PluginManagerInterface {

  /**
   * Returns all the owned config values that should not be alterable.
   *
   * @return array
   */
  public function getOwnedConfigValues(): array;
}
