<?php

namespace Drupal\Tests\config_owner\Kernel;

/**
 * Kernel test for the Config Owner.
 */
class ConfigOwnerPluginTest extends ConfigOwnerTestBase {

  /**
   * Tests the config owner filter in the write operations.
   *
   * Changes in the active storage to the owned config values should never end
   * up in the sync (staging) storage.
   */
  public function testConfigOwnerWrite() {
    $active_storage = $this->container->get('config.storage');
    $sync_storage = $this->container->get('config.storage.sync');

    // Export the configuration so that the two storages are in sync.
    $this->copyConfig($active_storage, $sync_storage);

    // Make some changes in the active storage to some owned config.
    $this->performDefaultConfigChanges();

    // Because we changed the active storage, the diff will be shown in the
    // comparer.
    $changes = $this->configImporter()->getStorageComparer()->getChangelist('update');
    sort($changes);
    $this->assertEquals(['config_owner_test.settings', 'config_owner_test.test_config.one', 'system.mail', 'system.site'], $changes);

    // Export again the configuration.
    $this->copyConfig($active_storage, $sync_storage);

    // The exported values should only be changed for the non-owned configs.
    $config = $sync_storage->read('config_owner_test.settings');

    // Owned, so no change.
    $this->assertEquals('green', $config['main_color']);
    // Non-owned so change.
    $this->assertEquals(['blue', 'orange'], $config['allowed_colors']);

    $config = $sync_storage->read('config_owner_test.test_config.one');
    // Owned so no change.
    $this->assertEquals('Test config one', $config['name']);

    $config = $sync_storage->read('system.mail');
    // Owned, so no change.
    $this->assertEquals(['default' => 'php_mail'], $config['interface']);

    $config = $sync_storage->read('system.site');
    // Not owned, so change.
    $this->assertEquals('The new site name', $config['name']);
  }

  /**
   * Test the config owner filter in the read operations.
   *
   * Manual changes in the sync (staging) storage to the owned config, should
   * never end up in the active storage. Instead, the original owned values
   * should be preserved.
   */
  public function testConfigOwnerRead() {
    $active_storage = $this->container->get('config.storage');
    $sync_storage = $this->container->get('config.storage.sync');

    // Export the configuration so that the two storages are in sync.
    $this->copyConfig($active_storage, $sync_storage);

    // Make changes to config in the sync (staging) storage.
    $config = $sync_storage->read('config_owner_test.settings');
    $config['main_color'] = 'yellow'; // Owned
    $config['allowed_colors'] = ['blue', 'orange']; // Not owned key
    $sync_storage->write('config_owner_test.settings', $config);

    $config = $sync_storage->read('config_owner_test.test_config.one');
    $config['name'] = 'The new name'; // Owned
    $sync_storage->write('config_owner_test.test_config.one', $config);

    $config = $sync_storage->read('system.site');
    $config['name'] = 'The new site name'; // Not owned
    $sync_storage->write('system.site', $config);

    $importer = $this->configImporter();
    $importer->import();

    // Owned, so no change.
    $this->assertEquals('green', $this->config('config_owner_test.settings')->get('main_color'));
    // Non-owned so change.
    $this->assertEquals(['blue', 'orange'], $this->config('config_owner_test.settings')->get('allowed_colors'));
    // Owned, so no change.
    $this->assertEquals('Test config one', $this->config('config_owner_test.test_config.one')->get('name'));
    // Non-owned so change.
    $this->assertEquals('The new site name', $this->config('system.site')->get('name'));

  }

}