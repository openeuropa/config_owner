<?php

namespace Drupal\config_owner;

use Drupal\Component\Utility\NestedArray;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

/**
 * Helper class that deals with replacing configuration with owned values.
 */
class OwnedConfigHelper {

  /**
   * Replaces a configuration array with owned values.
   *
   * Owned configuration replaces the existing configuration at key level.
   * However, third party settings are by default not owned so they should not
   * be replaced unless they are specifically mentioned.
   *
   * @param array $config
   * @param array $owned
   *
   * @return array
   */
  public static function replaceConfig(array $config, array $owned) {
    foreach ($config as $key => $value) {
      if (!is_array($value) && isset($owned[$key])) {
        $config[$key] = $owned[$key];
        continue;
      }

      if (!is_array($value)) {
        continue;
      }

      $level = isset($owned[$key]) ? $owned[$key] : NULL;
      if ($level === NULL) {
        // It means we do not have this key owned.
        continue;
      }

      if ($level === [] && $key !== 'third_party_settings') {
        // If we have an empty array, it means we do own it but without any
        // value. Except when dealing with third party settings.
        $config[$key] = $level;
        continue;
      }

      $config[$key] = static::replaceConfig($value, $level);
    }

    return $config;
  }

  /**
   * Flattens the config using a dot(.) noation.
   *
   * @param array $config
   *
   * @return array
   */
  public static function flattenConfig(array $config): array {
    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($config));
    $flat = [];
    foreach ($iterator as $leaf) {
      $keys = [];
      foreach (range(0, $iterator->getDepth()) as $depth) {
        $keys[] = $iterator->getSubIterator($depth)->key();
      }
      $flat[implode('.', $keys)] = $leaf;
    }

    return $flat;
  }

  /**
   * @param array $config
   *
   * @return array
   */
  public static function removeThirdPartySettings(array $config) {
    $flat = static::flattenConfig($config);
    foreach (array_keys($flat) as $key) {
      if (strpos($key, 'third_party_settings.') === FALSE) {
        continue;
      }

      $parents = explode('.', $key);
      $third_party_key = array_search('third_party_settings', $parents);
      $parents = array_slice($parents, 0, $third_party_key + 1, TRUE);
      NestedArray::unsetValue($config, $parents);
    }

    return $config;
  }

}