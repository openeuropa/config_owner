<?php

declare(strict_types = 1);

namespace Drupal\config_owner\EventSubscriber;

use Drupal\config_owner\OwnedConfigManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for the owned config plugins.
 */
class OwnedConfigEventSubscriber implements EventSubscriberInterface {

  /**
   * Owned config manager.
   *
   * @var \Drupal\config_owner\OwnedConfigManagerInterface
   */
  protected $ownedConfigManager;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * OwnedConfigEventSubscriber constructor.
   *
   * @param \Drupal\config_owner\OwnedConfigManagerInterface $ownedConfigManager
   *   Owned config manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend.
   */
  public function __construct(OwnedConfigManagerInterface $ownedConfigManager, CacheBackendInterface $cacheBackend) {
    $this->ownedConfigManager = $ownedConfigManager;
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onConfigCrud', 20];
    $events[ConfigEvents::DELETE][] = ['onConfigCrud', 20];
    $events[ConfigEvents::RENAME][] = ['onConfigCrud', 20];
    return $events;
  }

  /**
   * Listener to when there is a change on a given config.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The CRUD event.
   */
  public function onConfigCrud(ConfigCrudEvent $event) {
    if (!$this->ownedConfigManager->configIsOwned($event->getConfig()->getName())) {
      return;
    }

    $this->cacheBackend->invalidate('owned_config_values');
  }

}
