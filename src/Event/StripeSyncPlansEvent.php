<?php

namespace Drupal\provider_subscriptions\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class StripeSyncPlansEvent.
 *
 * @package Drupal\provider_subscriptions\Event
 */
class StripeSyncPlansEvent extends Event {

  public const EVENT_NAME = 'provider_subscriptions.sync_plans';

  /**
   * The event data.
   *
   * @var array
   */
  protected $data;

  /**
   * Constructs the object.
   *
   * @param array $params
   *   Custom values.
   */
  public function __construct(array $params) {
    $this->data = $params;
  }

  /**
   * Get the event data.
   *
   * @return array
   *   The plan synced plans data.
   */
  public function getData() {
    return $this->data;
  }

}
