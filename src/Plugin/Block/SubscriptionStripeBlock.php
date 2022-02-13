<?php

namespace Drupal\provider_subscriptions\Plugin\Block;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Block\BlockBase;

use Stripe\Subscription;


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

    $provider =  \Drupal::service('provider_subscriptions.stripe_api');
    $has_subscription = $provider->userHasStripeSubscription($user);

    $build['subscription'] = array(
      '#description' => '',
      '#title' => $this->t('Your Subscription'),
    );
  
    $options = ['absolute' => TRUE];
    if ($has_subscription) {
      // Local subscription
      $all_subscriptions = $provider->loadLocalSubscriptionMultiple([
        'user_id' => $user_id,
      ]);
      $subscription = end($all_subscriptions); // most recent 

      $status = $subscription->status->value;
      $remote_id = $subscription->subscription_id->value;

      // Remote subscription
      $subscriptions = $provider->loadRemoteSubscriptionsByUser($user, 'active');
      if (count($subscriptions->data) > 0) {
        $remote_subscription = end($subscriptions->data); // most recent
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
      $end_period = $subscription->current_period_end->value;

      $build['subscription']['plan'] = array(
        '#type' => 'item',
        '#markup' => "<strong>" . $this->t('Plan') . "</strong>: " . $plan_name,
      );

      $build['subscription']['status'] = array(
        '#type' => 'item',
        '#markup' => "<strong>" . $this->t('Status') . "</strong>: " . $this->t($status),
      );

      $build['subscription']['end_period'] = array(
        '#type' => 'item',
        '#markup' => "<strong>" . $this->t('End period') . "</strong>: " . 
        \Drupal::service('date.formatter')->format($end_period, 'date_text'),
      );

      $edit_url = Url::fromRoute('provider_subscriptions.stripe.subscriptions', [], $options);
      $edit_text = $this->t('Manage your subscription. Upgrade, Downgrade, Reactivate or Cancel.');
    }
    else {
      $edit_url = Url::fromRoute('provider_subscriptions.stripe.subscribe', [], $options);
      $edit_text = $this->t('Subscribe a plan to start publish Zap Pages');
    }

    // @todo when canceled link the subscription page

    $link = Link::fromTextAndUrl($edit_text, $edit_url)->toString();

    $build['account']['edit_link'] = [
      '#type' => 'item',
      '#markup' => $link,
    ];

    return $build;

  }
}