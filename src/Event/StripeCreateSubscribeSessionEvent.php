<?php

namespace Drupal\provider_subscriptions\Event;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class StripeCreateSubscribeSessionEvent.
 *
 * @package Drupal\provider_subscriptions\Event
 */
class StripeCreateSubscribeSessionEvent extends Event {

  public const EVENT_NAME = 'stripe_create_subscribe_session';

  /**
   * The session parameters.
   *
   * @var array
   */
  public $params;

  /**
   * The user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account of the user logged in.
   * @param array $params
   *   Custom values.
   */
  public function __construct(AccountInterface $account, array &$params) {
    $this->account = $account;
    $this->params = &$params;
  }

}
