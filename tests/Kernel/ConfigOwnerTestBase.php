<?php

declare(strict_types = 1);

namespace Drupal\Tests\config_owner\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Base class for config owner Kernel tests.
 */
class ConfigOwnerTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'config_filter',
    'config_owner',
    'config_owner_test',
    'language',
    'config_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installConfig(['config_owner_test']);

    // Create the French language.
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Makes default config changes used in tests.
   *
   * It makes changes to:
   *
   * - entire owned config objects
   * - partially owned config objects
   * - non-owned objects.
   */
  protected function performDefaultConfigChanges() {
    $this->config('config_owner_test.settings')
    // Owned key.
      ->set('main_color', 'yellow')
    // Not owned key.
      ->set('allowed_colors', ['blue', 'orange'])
      ->save();

    $this->config('config_owner_test.test_config.one')
      ->set('name', 'The new name')
      // The entire config is owned.
      ->save();

    $this->config('system.mail')
      ->set('interface', ['default' => 'dummy'])
      // The entire config is owned via the "owned" folder.
      ->save();

    $this->config('system.site')
      ->set('name', 'The new site name')
      // The entire config is not owned.
      ->save();

    // Translate a configuration that is owned.
    /** @var \Drupal\Core\Config\Config $config */
    $this->container->get('language_manager')
      ->getLanguageConfigOverride('fr', 'system.maintenance')
      ->set('message', 'The French maintenance message')
      ->save();
  }

}
