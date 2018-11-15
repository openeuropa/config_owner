<?php

declare(strict_types = 1);

namespace Drupal\Tests\config_owner\Kernel;

use Drupal\Core\Config\StorageInterface;

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

    $this->copyConfig($active_storage, $sync_storage);

    // Make some changes in the active storage to some owned config.
    $this->performDefaultConfigChanges();

    // Because we changed the active storage, the diff will be shown in the
    // comparer.
    $changes = $this->configImporter()->getStorageComparer()->getChangelist('update');
    sort($changes);
    $this->assertEquals([
      'config_owner_test.settings',
      'config_owner_test.test_config.one',
      'system.mail',
      'system.site',
    ], $changes);

    /** @var \Drupal\Core\Config\StorageInterface[] $active_storages */
    $active_storages = [];
    /** @var \Drupal\Core\Config\StorageInterface[] $active_storages */
    $sync_storages = [];

    $active_storages[StorageInterface::DEFAULT_COLLECTION] = $active_storage;
    $sync_storages[StorageInterface::DEFAULT_COLLECTION] = $sync_storage;
    $active_storages['language.fr'] = $active_storage->createCollection('language.fr');
    $sync_storages['language.fr'] = $sync_storage->createCollection('language.fr');

    // We need to create the folder for the collection config sync folder.
    mkdir($this->siteDirectory . '/files/config/' . CONFIG_SYNC_DIRECTORY . '/language/fr', 0775, TRUE);
    foreach ([StorageInterface::DEFAULT_COLLECTION, 'language.fr'] as $collection) {
      // Export the configuration so that the two storages are in sync.
      $this->copyConfig($active_storages[$collection], $sync_storages[$collection]);
    }

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

    // Ensure the config ownership does not affect translations.
    $config = $sync_storage->read('system.maintenance');
    $this->assertEquals('@site is currently under maintenance. We should be back shortly. Thank you for your patience.', $config['message']);

    // Ensure that export to the sync storage is enforced only for the default
    // collection and that changes to the other collections (such as language)
    // do get exported correctly.
    $config = $sync_storages[StorageInterface::DEFAULT_COLLECTION]->read('system.maintenance');
    $this->assertEquals('@site is currently under maintenance. We should be back shortly. Thank you for your patience.', $config['message']);
    $config = $sync_storages['language.fr']->read('system.maintenance');
    $this->assertEquals('The French maintenance message', $config['message']);
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
    // Owned.
    $config['main_color'] = 'yellow';
    // Not owned key.
    $config['allowed_colors'] = ['blue', 'orange'];
    $sync_storage->write('config_owner_test.settings', $config);

    $config = $sync_storage->read('config_owner_test.test_config.one');
    // Owned.
    $config['name'] = 'The new name';
    $sync_storage->write('config_owner_test.test_config.one', $config);

    $config = $sync_storage->read('system.site');
    // Not owned.
    $config['name'] = 'The new site name';
    $sync_storage->write('system.site', $config);

    // Owned translated config.
    $config = $sync_storage->read('system.maintenance');
    $config['message'] = 'Dummy maintenance message.';
    $sync_storage->write('system.maintenance', $config);

    // We need to create the folder for the collection config sync folder.
    mkdir($this->siteDirectory . '/files/config/' . CONFIG_SYNC_DIRECTORY . '/language/fr', 0775, TRUE);
    $sync_storage_french = $sync_storage->createCollection('language.fr');
    $config = $sync_storage_french->read('system.maintenance');
    $config['message'] = 'Maintenance message in FR.';
    $sync_storage_french->write('system.maintenance', $config);

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

    // Translated "owned" config changes should be imported, but not changes to
    // the original one.
    $this->assertEquals('@site is currently under maintenance. We should be back shortly. Thank you for your patience.', $this->config('system.maintenance')->get('message'));
    $this->container->get('language_manager')->getLanguageConfigOverride('fr', 'system.maintenance');
    $this->assertEquals('Maintenance message in FR.', $this->container->get('language_manager')->getLanguageConfigOverride('fr', 'system.maintenance')->get('message'));
  }

}
