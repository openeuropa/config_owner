<?php

declare(strict_types = 1);

namespace Drupal\Tests\config_owner\Unit;

use Drupal\config_owner\OwnedConfigHelper;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ConfigOwnerHelper class.
 *
 * @group config_owner
 */
class OwnedConfigHelperTest extends UnitTestCase {

  /**
   * Tests that the third party settings are correctly removed from a config.
   */
  public function testRemoveThirdPartySettings(): void {
    $config = $this->getConfig();
    // No keys to keep.
    $clean = OwnedConfigHelper::removeThirdPartySettings($config);
    $this->assertEquals([
      'name' => 'The name',
      'something' => [
        'something_else' => [
          'name' => 'Some name',
        ],
        'another_thing' => [
          'name' => 'Another  name',
        ],
        'third_thing' => [
          'name' => 'Third name',
          'third_party_settings' => [],
        ],
      ],
    ], $clean);

    $clean = OwnedConfigHelper::removeThirdPartySettings($config, ['third_party_settings.module_name.config_one', 'something.another_thing.third_party_settings.module_name.config_three']);
    $this->assertEquals([
      'name' => 'The name',
      'third_party_settings' => [
        'module_name' => [
          'config_one' => 'value_one',
        ],
      ],
      'something' => [
        'something_else' => [
          'name' => 'Some name',
        ],
        'another_thing' => [
          'name' => 'Another  name',
          'third_party_settings' => [
            'module_name' => [
              'config_three' => 'value_three',
            ],
          ],
        ],
        'third_thing' => [
          'name' => 'Third name',
          'third_party_settings' => [],
        ],
      ],
    ], $clean);
  }

  /**
   * Returns the default config to test with.
   *
   * @return array
   *   The test configuration.
   */
  protected function getConfig(): array {
    return [
      'name' => 'The name',
      'third_party_settings' => [
        'module_name' => [
          'config_one' => 'value_one',
        ],
      ],
      'something' => [
        'something_else' => [
          'name' => 'Some name',
          'third_party_settings' => [
            'module_name' => [
              'config_two' => 'value_two',
            ],
          ],
        ],
        'another_thing' => [
          'name' => 'Another  name',
          'third_party_settings' => [
            'module_name' => [
              'config_three' => 'value_three',
            ],
          ],
        ],
        'third_thing' => [
          'name' => 'Third name',
          'third_party_settings' => [],
        ],
      ],
    ];
  }

}
