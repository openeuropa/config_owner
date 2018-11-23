<?php

namespace Drupal\config_owner;

/**
 * Helper class that deals with replacing configuration with owned values.
 */
class OwnedConfigHelper {

  /**
   * Replaces a configuration array with owned values.
   *
   * @param array $config
   * @param array $owned
   *
   * @return array
   */
  public static function replaceConfig(array $config, array $owned) {
    foreach ($owned as $key => $value) {
      $config[$key] = $value;
    }

    return $config;
  }

}