<?php

declare(strict_types = 1);

namespace Drupal\Tests\config_owner\Kernel;

/**
 * Tests the owned config storage comparer factory.
 */
class OwnedConfigStorageComparerTest extends ConfigOwnerTestBase {

  /**
   * Tests the factory that creates the storage comparer for owned config.
   *
   * The goal is to ensure that the resulting comparer correctly identifies
   * the differences of the owned config between the active storage and what
   * is in the original owned config files.
   */
  public function testConfigStorageComparer() {

    // Makes some config changes.
    $this->performDefaultConfigChanges();

    /** @var \Drupal\Core\Config\StorageComparer $storage_comparer */
    $storage_comparer = $this->container->get('config_owner.storage_comparer_factory')->create();

    $changes = $storage_comparer->createChangelist()->getChangelist();

    sort($changes['update']);
    $this->assertEquals([
      'config_owner_test.settings',
      'config_owner_test.test_config.one',
      'system.mail',
    ], $changes['update']);

    // Assert that the not-owned keys does not differ.
    $active_config = $storage_comparer->getTargetStorage()->read('config_owner_test.settings');
    $sync_config = $storage_comparer->getSourceStorage()->read('config_owner_test.settings');
    // Not owned.
    $this->assertEquals($active_config['allowed_colors'], $sync_config['allowed_colors']);
    // Owned.
    $this->assertNotEquals($active_config['main_color'], $sync_config['main_color']);

    $active_config = $storage_comparer->getTargetStorage()->read('system.mail');
    $sync_config = $storage_comparer->getSourceStorage()->read('system.mail');
    // Owned.
    $this->assertNotEquals($active_config['interface'], $sync_config['interface']);

    // Assert that the sync config (owned) only contains the optional config
    // that was imported (had all dependencies met).
    $this->assertEmpty($storage_comparer->getSourceStorage()->read('config_owner_test.optional_two'));
    $this->assertEquals('Optional One', $storage_comparer->getSourceStorage()->read('config_owner_test.optional_one')['name']);
  }

}
