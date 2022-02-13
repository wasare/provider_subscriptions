<?php

namespace Drupal\provider_subscriptions\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;

/**
 * Defines SubscribeMenuLink.
 */
class SubscribeMenuLink extends MenuLinkDefault {

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\provider_subscriptions\Controller\UserSubscriptionsController::subscribeTitle()
   */
  public function getTitle() {
    $stripe_subscription = \Drupal::service('provider_subscriptions.stripe_api');
    $current_user = \Drupal::service('current_user');
    if ($stripe_subscription->userHasStripeSubscription($current_user)) {
      return 'Upgrade';
    }
    return 'Subscribe';
  }

}
