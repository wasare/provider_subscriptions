<?php

namespace Drupal\provider_subscriptions\Plugin\Block;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Subscription Stripe' Block.
 *
 * @Block(
 *   id = "subscription_stripe_block",
 *   admin_label = @Translation("Subscription Stripe Block"),
 *   category = @Translation("Subscription"),
 * )
 */
class SubscriptionStripeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $current_user = \Drupal::currentUser();
    $user_id = $current_user->id();
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);

    $provider = \Drupal::service('provider_subscriptions.stripe_api');
    $has_subscription = $provider->userHasStripeSubscription($user);

    $build['subscription'] = [
      '#description' => '',
      '#title' => $this->t('Your Subscription'),
    ];

    $options = ['absolute' => TRUE];
    if ($has_subscription) {
      // Local subscription.
      $all_subscriptions = $provider->loadLocalSubscriptionMultiple([
        'user_id' => $user_id,
      ]);
      // Most recent.
      $subscription = end($all_subscriptions);

      $status = $subscription->status->value;
      $remote_id = $subscription->subscription_id->value;

      // Remote subscription.
      $subscriptions = $provider->loadRemoteSubscriptionsByUser($user, 'active');
      if (!is_bool($subscriptions) && !is_null($subscriptions->data) && count($subscriptions->data) > 0) {
        // Most recent.
        $remote_subscription = end($subscriptions->data);
        if ($status != 'active' || $remote_id != $remote_subscription->id) {
          $provider->syncRemoteSubscriptionToLocal($remote_subscription->id);
          $subscription = $provider->loadLocalSubscription([
            'user_id' => $user_id,
            'status' => 'active',
            'subscription_id' => $remote_subscription->id,
          ]);
        }
        $status = $subscription->status->value;
        $remote_id = $subscription->subscription_id->value;
      }

      $plan = $subscription->getPlan();
      $plan_name = $plan->name->value;

      $build['subscription']['plan'] = [
        '#type' => 'item',
        '#markup' => "<strong>" . $this->t('Plan') . "</strong>: " . $plan_name,
      ];

      $build['subscription']['status'] = [
        '#type' => 'item',
        '#markup' => "<strong>" . $this->t('Status') . "</strong>: " . $this->t($status),
      ];

      $edit_url = Url::fromRoute('provider_subscriptions.stripe.subscriptions', [], $options);
      $edit_text = $this->t('Manage your subscription.');

      if ($status != 'canceled') {
        $end_period = \Drupal::service('date.formatter')
          ->format($subscription->current_period_end->value, 'date_text');

        $build['subscription']['end_period'] = [
          '#type' => 'item',
          '#markup' => "<strong>" . $this->t('End period') . "</strong>: " .
          $end_period,
        ];
      }
      else {
        $edit_url = Url::fromRoute('provider_subscriptions.stripe.subscribe', [], $options);
        $edit_text = $this->t('Subscribe a plan to start publish Zap Pages / QRCodes.');
      }
    }
    else {
      $edit_url = Url::fromRoute('provider_subscriptions.stripe.subscribe', [], $options);
      $edit_text = $this->t('Subscribe a plan to start publish Zap Pages / QRCodes.');
    }

    $link = Link::fromTextAndUrl($edit_text, $edit_url)->toString();

    $build['account']['edit_link'] = [
      '#type' => 'item',
      '#markup' => $link,
    ];

    return $build;

  }

}
