<?php

namespace Drupal\provider_subscriptions\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\provider_stripe\Event\StripeApiWebhookEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\provider_subscriptions\StripeSubscriptionService;

/**
 * Class WebHookSubscriber.
 *
 * @package Drupal\provider_subscriptions
 */
class WebHookSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\provider_subscriptions\StripeSubscriptionService*/
  protected $stripeRegApi;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface*/
  protected $logger;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * WebHookSubscriber constructor.
   *
   * @param \Drupal\provider_subscriptions\StripeSubscriptionService $provider_subscriptions_stripe_api
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(StripeSubscriptionService $provider_subscriptions_stripe_api, LoggerChannelInterface $logger, MessengerInterface $messenger) {
    $this->stripeRegApi = $provider_subscriptions_stripe_api;
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['provider_stripe.webhook'][] = ['onIncomingWebhook'];
    return $events;
  }

  /**
   * Process an incoming webhook.
   *
   * @param \Drupal\provider_stripe\Event\StripeApiWebhookEvent $event
   *   Logs an incoming webhook of the setting is on.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   * @throws \Throwable
   */
  public function onIncomingWebhook(StripeApiWebhookEvent $event) {
    $type = $event->type;
    $data = $event->data;
    $stripe_event = $event->event;
    $this->logEvent($event, $stripe_event);

    // $this->logger->error('Event @type', ['@type' => $type]);
    // $this->logger->error('remote_subscription @sub', ['@sub' => $data->object]);

    // React to subscription life cycle events.
    // @see https://stripe.com/docs/subscriptions/lifecycle
    switch ($type) {
      // Occurs whenever a customer with no subscription is signed up for a plan.
      case 'customer.subscription.created':
        // $remote_subscription = $data->object;
        // $this->createOrUpdateLocalSubscription($remote_subscription, 'created');
        // break;
      // Occurs whenever a subscription changes. Examples would include
      // switching from one plan to another, or switching status from trial
      // to active.
      case 'customer.subscription.updated':
        // $remote_subscription = $data->object;
        // $this->createOrUpdateLocalSubscription($remote_subscription, 'updated');
        // break;

      // Occurs whenever a customer ends their subscription.
      case 'customer.subscription.deleted':
        $remote_subscription = $data->object;
        // $this->createOrUpdateLocalSubscription($remote_subscription, 'deleted');
        $this->stripeRegApi->syncRemoteSubscriptionToLocal($remote_subscription->id);
        break;

      // Occurs three days before the trial period of a subscription is scheduled to end.
      case 'customer.subscription.trial_will_end':
        break;

      // Occurs whenever a invoice failed.
      case 'invoice.payment_failed':
        // Remove roles after 2 attempt.
        // @todo attempt as ui param
        if ($data->object->attempt_count >= 2) {
          $remote_subscription = $data->object;
          $this->createOrUpdateLocalSubscription($remote_subscription, 'payment_failed');
        }
        break;
    }

  }

  /**
   * @param $remote_subscription
   *
   * @throws \Throwable
   */
  protected function createLocalSubscription($remote_subscription): void {
    try {
      $local_subscription = $this->stripeRegApi->createLocalSubscription($remote_subscription);
      $this->messenger->addMessage(t('You have successfully subscribed to the @plan_name plan.',
        ['@plan_name' => $remote_subscription->plan->product]), 'status');
      $this->logger->debug('Created local subscription #@subscription_id with remote ID @remote_id', ['@subscription_id' => $local_subscription->id(), '@remote_id' => $remote_subscription->id]);
    } catch (\Throwable $e) {
      $this->logger->error('Failed to create local subscription for remote subscription @remote_id: @exception', ['@exception' => $e->getMessage() . $e->getTraceAsString(), '@remote_id' => $remote_subscription->id]);
      throw $e;
    }
  }

  // /**
  //  * @param $remote_subscription
  //  *
  //  * @throws \Throwable
  //  */
  // protected function deleteLocalSubscription($remote_subscription): void {
  //   try {
  //     $this->stripeRegApi->syncRemoteSubscriptionToLocal($remote_subscription->id);
  //     $local_subscription = $this->stripeRegApi->loadLocalSubscription(['subscription_id' => $remote_subscription->id]);
  //     // $local_subscription = $this->stripeRegApi->loadLocalSubscription(['subscription_id' => $remote_id]);
  //     // Setup the status of a local subscription in 'cancel'.
  //     // As the Stripes changes the status after some time wee will not to
  //     // synchronize local subscription with a remote subscription.
  //     $local_subscription->set('status', 'canceled');
  //     // Roles related with a subscription will be removed after calling save()
  //     // method, because saving initiates a call of the 'updateUserRoles()'
  //     // method of a local subscription entity.
  //     $local_subscription->save();
  //     //$local_subscription->delete();
  //   } catch (\Throwable $e) {
  //     $this->logger->error('Failed to cancel local subscription @remote_id: @exception', ['@exception' => $e->getMessage() . $e->getTraceAsString(), '@remote_id' => $remote_subscription->id]);
  //     throw $e;
  //   }
  // }

  /**
   * @param \Drupal\provider_stripe\Event\StripeApiWebhookEvent $event
   * @param \Stripe\Event $stripe_event
   */
  protected function logEvent(StripeApiWebhookEvent $event, \Stripe\Event $stripe_event): void {
    if (\Drupal::config('provider_stripe.settings')->get('log_webhooks')) {
      $this->logger->info("Event Subscriber reacting to @type event:\n @event",
        ['@type' => $event->type, '@event' => json_encode($stripe_event, JSON_PRETTY_PRINT)]);
    }
  }

  /**
   * @param $remote_subscription
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   * @throws \Throwable
   */
  protected function createOrUpdateLocalSubscription($remote_subscription, $reason = 'created'): void {
    try {
      $local_subscription = $this->stripeRegApi->loadLocalSubscription(['subscription_id' => $remote_subscription->id]);
      if (!$local_subscription && in_array($reason, array('created'))) {
        $this->createLocalSubscription($remote_subscription);
      }
      else {
        if ($reason == 'payment_failed' || $reason == 'deleted') {
            // Setup the status of a local subscription in 'cancel'.
            // As the Stripes changes the status after some time wee will not to
            // synchronize local subscription with a remote subscription.
            $local_subscription->set('status', 'canceled');
            // Roles related with a subscription will be removed after calling save()
            // method, because saving initiates a call of the 'updateUserRoles()'
            // method of a local subscription entity.
            $local_subscription->save();
        }
        else {
          // others
          // We don't want to delete canceled subscription.
          // Roles related with a canceled subscription will be removed after
          // syncRemoteSubscriptionToLocal() by calling updateUserRoles()
          // method of "provider_subscriptions" entity (local subscription).
          /** @var \Drupal\provider_subscriptions\Entity\StripeSubscriptionEntity $local_subscription */
          $this->stripeRegApi->syncRemoteSubscriptionToLocal($remote_subscription->id);
        }
      }
      // $local_subscription = $this->stripeRegApi
      //                             ->loadLocalSubscription(['subscription_id' => $remote_subscription->id]);
      // $local_subscription->updateUserRoles();
    }
    catch (\Throwable $e) {
      $this->logger->error("Failed to create or update local subscription: @exception", ['@exception' => $e->getMessage()]);
    }

  }

}
