<?php

declare(strict_types = 1);

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
   *   The configuration to be replaced.
   * @param array $owned
   *   The owned configuration values.
   *
   * @return array
   *   The resulting configuration values.
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
   * Flattens the config using a dot(.) notation.
   *
   * @param array $config
   *   The configuration values to flatten.
   *
   * @return array
   *   The flattened array.
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
   * Removes the third party settings from a config.
   *
   * @param array $config
   *   The configuration to remove from.
   * @param array $ignored_keys
   *   Specific third party settings keys not to remove.
   *
   * @return array
   *   The resulting array.
   */
  public static function removeThirdPartySettings(array $config, array $ignored_keys = []) {
    // First we need to determine the locations of all the third party settings
    // in the config (their parents).
    $third_party_settings_parents = static::determineThirdPartyLocations($config);

    // Next, we need to identify the third party settings we want to remove
    // straight from the parents level (i.e those which do not have children
    // that are ignored - owned).
    if ($third_party_settings_parents && $ignored_keys) {
      $third_party_settings_parents = static::unsetThirdPartySettingsLocationsWithIgnoredChildren($third_party_settings_parents, $ignored_keys);
    }

    // After determining the final list of parents that can be fully removed,
    // we need to remove them completely.
    $flat = static::flattenConfig($config);
    foreach (array_keys($flat) as $key) {
      foreach ($third_party_settings_parents as $parent) {
        if (strpos($key, $parent) === 0) {
          $parts = explode('.', $parent);
          NestedArray::unsetValue($config, $parts);
          // Remove it also from the flattened array so we don't have it in the
          // next step below.
          unset($flat[$key]);
        }
      }
    }

    // Lastly, we loop through the flatten keys and remove the third party
    // settings that are not ignored (i.e owned).
    foreach (array_keys($flat) as $key) {
      if (strpos($key, 'third_party_settings.') === FALSE) {
        continue;
      }

      if (in_array($key, $ignored_keys)) {
        continue;
      }

      $parents = explode('.', $key);
      NestedArray::unsetValue($config, $parents);
    }

    return $config;
  }

  /**
   * Given the configuration array, determines where the TPS are.
   *
   * Flattens the array and returns a list of locations where third party
   * settings can be found, using dot(.) notation to indicate nesting.
   *
   * @param array $config
   *   The config array.
   *
   * @return array
   *   The list of third party settings locations.
   */
  protected static function determineThirdPartyLocations(array $config): array {
    $flat = static::flattenConfig($config);

    // First we need to determine the locations of all the third party settings
    // in the config (their parents).
    $third_party_settings_parents = [];
    foreach (array_keys($flat) as $key) {
      if (strpos($key, 'third_party_settings.') === FALSE) {
        continue;
      }

      $parts = explode('.', $key);
      $key = array_search('third_party_settings', $parts);
      $parents = array_slice($parts, 0, $key + 1, TRUE);
      $third_party_settings_parents[] = implode('.', $parents);
    }

    return array_unique($third_party_settings_parents);
  }

  /**
   * Removes the third party settings locations which have ignored children.
   *
   * The purpose is to ensure we don't remove those completely from the config.
   *
   * @param array $parents
   *   The list of flattened keys (locations) to the third party settings.
   * @param array $ignored_keys
   *   The keys which should be ignored whose parents should be removed.
   *
   * @return array
   *   The cleaned locations.
   */
  protected static function unsetThirdPartySettingsLocationsWithIgnoredChildren(array $parents, array $ignored_keys) {
    foreach ($parents as $parent) {
      $found = FALSE;
      foreach ($ignored_keys as $key) {
        if (strpos($key, $parent) === 0) {
          $found = TRUE;
        }
      }

      if ($found) {
        // If this is a parent of one of the ignored keys, we remove it from
        // the array so that it won't get unset later.
        $parent_key = array_search($parent, $parents);
        unset($parents[$parent_key]);
      }
    }

    return $parents;
  }

}
