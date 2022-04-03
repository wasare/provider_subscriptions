<?php

namespace Drupal\provider_subscriptions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\provider_stripe\StripeApiService;
use Stripe\Customer;
use Stripe\Plan;
use Stripe\Product;
use Stripe\Price;
use Stripe\Subscription;
use Drupal\Core\Messenger\MessengerTrait;

use Drupal\provider_subscriptions\Event\StripeSyncPlansEvent;

/**
 * Class StripeSubscriptionService.
 *
 * @package Drupal\provider_subscriptions
 */
class StripeSubscriptionService {

  use MessengerTrait;

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Increase default plan loading limit to its maximum.
   */
  const PLAN_LOADING_MAX_LIMIT = 100;

  /**
   * Drupal\provider_stripe\StripeApiService definition.
   *
   * @var \Drupal\provider_stripe\StripeApiService
   */
  protected $stripeApi;

  /**
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   ConfigFactoryInterface object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManagerInterface object.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   LoggerChannelInterface object.
   * @param \Drupal\provider_stripe\StripeApiService $stripe_api
   *   StripeApiService object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger, StripeApiService $stripe_api) {
    $this->config = $config_factory->get('provider_subscriptions.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->stripeApi = $stripe_api;
  }

  /**
   * Check if a given user has a stripe subscription.
   *
   * @param \Drupal\user\UserInterface|\Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param string $remote_id
   *   Subscription ID on Stripe plataform.
   *
   * @return bool
   *   TRUE if the user has a subscription.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function userHasStripeSubscription($user, $remote_id = NULL): bool {
    if (is_null($remote_id)) {
      if (empty($user->stripe_customer_id->value)) {
        return FALSE;
      }
      $remote_id = $user->stripe_customer_id->value;
    }

    $subscription = $this->loadLocalSubscription([
      'subscription_id' => $remote_id,
      'user_id' => $user->id(),
    ]);

    return (bool) $subscription;
  }

  /**
   * Loads a user's remote subscription.
   *
   * @param \Drupal\user\UserInterface|\Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param string $status
   *   Status filter.
   *
   * @return bool|\Stripe\Collection
   *   A collection of subscriptions.
   *
   * @throws \Stripe\Error\Api
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemoteSubscriptionsByUser($user, $status = 'active') {
    return $this->loadRemoteSubscriptionMultiple(
          ['customer' => $user->stripe_customer_id->value, 'status' => $status]);
  }

  /**
   * Load multiple remote subscriptions.
   *
   * @param array $args
   *   Arguments by which to filter the subscriptions.
   *
   * @return bool|\Stripe\Collection
   *   A collection of subscriptions.
   *
   * @throws \Stripe\Error\Api
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemoteSubscriptionMultiple(array $args = []) {
    // @todo add try, catch.
    $subscriptions = Subscription::all($args);

    if (!count($subscriptions->data)) {
      return FALSE;
    }

    return $subscriptions;
  }

  /**
   * Load a local subscription.
   *
   * @param array $properties
   *   Local properties by which to filter the subscriptions.
   *
   * @return \Drupal\provider_subscriptions\Entity\StripeSubscriptionEntity|bool
   *   A Stripe subscription entity, or else FALSE.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalSubscription(array $properties = []) {
    $stripe_subscription_entities = $this->loadLocalSubscriptionMultiple($properties);
    if (!count($stripe_subscription_entities)) {
      return FALSE;
    }

    $first = reset($stripe_subscription_entities);

    return $first;
  }

  /**
   * Load multiple local subscriptions.
   *
   * @param array $properties
   *   Local properties by which to filter the subscriptions.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of Stripe subscription entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalSubscriptionMultiple(array $properties = []) {
    $stripe_subscription_entities = $this->entityTypeManager
      ->getStorage('stripe_subscription')
      ->loadByProperties($properties);

    return $stripe_subscription_entities;
  }

  /**
   * Load multiple local plans.
   *
   * @param array $properties
   *   An array of arguments by which to filter the local plans.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalPlanMultiple(array $properties = []): array {

    if (count($properties) > 0) {
      $stripe_plan_entities = $this->entityTypeManager
        ->getStorage('stripe_plan')
        ->loadByProperties($properties);
    }
    else {
      $stripe_plan_entities = $this->entityTypeManager
        ->getStorage('stripe_plan')
        ->loadMultiple();
    }

    return $stripe_plan_entities;
  }

  /**
   * Implements loadLocalPlan().
   *
   * @param array $properties
   *   An array of properties.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|mixed
   *   bool, EntityInterface or mixed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalPlan(array $properties = []) {
    $stripe_plan_entities = $this->entityTypeManager
      ->getStorage('stripe_plan')
      ->loadByProperties($properties);

    if (!count($stripe_plan_entities)) {
      return FALSE;
    }

    $first = reset($stripe_plan_entities);

    return $first;
  }

  /**
   * Load multiple remote plans.
   *
   * @param array $args
   *   An array of arguments by which to filter the remote plans.
   *
   * @return \Stripe\Plan[]
   *   Array of remote plans.
   *
   * @throws \Stripe\Error\Api
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemotePlanMultiple(array $args = []): array {
    $plans = Plan::all($args);

    // @todo handle no results case.
    // Re-key array.
    $keyed_plans = [];
    foreach ($plans->data as $plan) {
      $product = Product::retrieve($plan->product);
      $plan->name = $product->name;
      $keyed_plans[$plan->product] = $plan;
    }

    return $keyed_plans;
  }

  /**
   * Implements loadRemotePlanById().
   *
   * @param string $plan_id
   *   Plan ID on Stripe plataform.
   *
   * @return \Stripe\Plan
   *   Plan object.
   *
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemotePlanById(string $plan_id) {
    $plan = $this->loadRemotePlanMultiple(['id' => $plan_id]);

    return $plan->data;
  }

  /**
   * Implements loadRemoteProductById().
   *
   * @param string $product_id
   *   Product ID on Stripe plataform.
   *
   * @return \Stripe\Product
   *   Product object.
   *
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemoteProductById(string $product_id) {
    return Product::retrieve($product_id);
  }

  /**
   * Implements syncPlans().
   *
   * @param bool $delete
   *   If true, local plans without matching remote plans will be deleted from Drupal.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function syncPlans($delete = FALSE): void {
    // @todo Handle pagination here.
    $remote_plans = $this->loadRemotePlanMultiple(
      [
        'limit' => self::PLAN_LOADING_MAX_LIMIT,
      ]
    );

    $local_plans = $this->entityTypeManager->getStorage('stripe_plan')->loadMultiple();

    /** @var \Drupal\Core\Entity\EntityInterface[] $local_plans_keyed */
    $local_plans_keyed = [];
    foreach ($local_plans as $local_plan) {
      $local_plans_keyed[$local_plan->plan_id->value] = $local_plan;
    }

    // $plans_to_delete = array_diff(array_keys($local_plans_keyed), array_keys($remote_plans));
    $plans_to_create = array_diff(array_keys($remote_plans), array_keys($local_plans_keyed));
    $plans_to_update = array_intersect(array_keys($remote_plans), array_keys($local_plans_keyed));

    $this->logger->info('Synchronizing Stripe plans.');

    // Create new plans.
    foreach ($plans_to_create as $plan_id) {
      $remote_plan = $remote_plans[$plan_id];
      $product = $this->loadRemoteProductById($remote_plan->product);
      $prices = Price::all(['product' => $remote_plan->product]);
      $plan_data = [
        'plan' => $remote_plan->jsonSerialize(),
        'prices' => $prices->jsonSerialize()['data']
      ];
      $data = [
        'plan_id' => $remote_plan->product,
        'plan_price_id' => $remote_plan->id,
        'name' => $remote_plans[$plan_id]->name,
        'livemode' => $remote_plan->livemode == 'true',
        'active' => $product->active == 'true',
        'data' => serialize($plan_data),
      ];

      $this->entityTypeManager->getStorage('stripe_plan')->create($data)->save();
      $this->logger->info('Created @plan_id plan.', ['@plan_id' => $plan_id]);
    }
    // // Delete invalid plans.
    // if ($delete && $plans_to_delete) {
    // $entities_to_delete = [];
    // foreach ($plans_to_delete as $plan_id) {
    // $entities_to_delete[] = $local_plans_keyed[$plan_id];
    // }
    // $this->entityTypeManager->getStorage('stripe_plan')
    // ->delete($entities_to_delete);
    // $this->logger->info('Deleted plans @plan_ids.', ['@plan_ids' => $plans_to_delete]);
    // }

    // Update existing plans.
    foreach ($plans_to_update as $plan_id) {
      /** @var \Drupal\Core\Entity\EntityInterface $plan */
      $plan = $local_plans_keyed[$plan_id];
      /** @var \Stripe\Plan $remote_plan */
      $remote_plan = $remote_plans[$plan_id];
      $product = $this->loadRemoteProductById($remote_plan->product);
      $prices = Price::all(['product' => $remote_plan->product]);
      $plan_data = [
        'plan' => $remote_plan->jsonSerialize(),
        'prices' => $prices->jsonSerialize()['data']
      ];

      $plan->set('name', $remote_plan->name);
      $plan->set('plan_price_id', $remote_plan->id);
      $plan->set('livemode', $remote_plan->livemode == 'true');
      $plan->set('active', $product->active == 'true');
      $data = serialize($plan_data);
      $plan->set('data', $data);
      $plan->save();
      $this->logger->info('Updated @plan_id plan.', ['@plan_id' => $plan_id]);
    }

    $synced_plans = array_unique(array_merge($plans_to_create, $plans_to_update));
    $event = new StripeSyncPlansEvent($synced_plans);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(StripeSyncPlansEvent::EVENT_NAME, $event);

    $this->messenger()->addMessage(t('Stripe plans were synchronized. Visit %link to see synchronized plans. If any plans were newly created, you must assign roles to them.', ['%link' => Link::fromTextAndUrl('Stripe plan list', Url::fromUri('internal:/admin/structure/stripe-subscription/stripe-plan'))->toString()]), 'status');

  }

  /**
   * Implements syncRemoteSubscriptionToLocal().
   *
   * @param string $remote_id
   *   Subscription ID on stripe plataform.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function syncRemoteSubscriptionToLocal(string $remote_id): void {
    $remote_subscription = Subscription::retrieve($remote_id);
    $local_subscription = $this->loadLocalSubscription(['subscription_id' => $remote_id]);
    if (!$local_subscription) {
      $this->createLocalSubscription($remote_subscription);
      $local_subscription = $this->loadLocalSubscription(['subscription_id' => $remote_id]);
      if (!$local_subscription) {
        throw new \RuntimeException("Could not find matching local subscription for Remote ID $remote_id.");
      }
    }
    $local_subscription->updateFromUpstream($remote_subscription);
    $this->logger->info('Updated subscription entity #@subscription_id: @sub',
      [
        '@subscription_id' => $local_subscription->id(),
        '@sub' => $local_subscription->id()
      ]);
  }

  /**
   * Implements createLocalSubscription().
   *
   * @param \Stripe\Subscription $remote_subscription
   *   Stripe Subscription object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Stripe\Subscription
   *   EntityInterface or Subscription object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function createLocalSubscription(Subscription $remote_subscription) {
    if ($user_entity = $this->loadUserByStripeCustomerId($remote_subscription->customer)) {
      $uid = $user_entity->id();
    }
    else {
      $customer = Customer::retrieve($remote_subscription->customer);
      $user_entity = user_load_by_mail($customer->email);
      $uid = $user_entity->id();
      $this->setLocalUserCustomerId($uid, $remote_subscription->customer);
      if (!$user_entity) {
        throw new \RuntimeException("There is no local user with with Stripe customer id " . $remote_subscription->customer);
      }
    }

    $values = [
      'user_id' => $uid,
      'plan_id' => $remote_subscription->plan->product,
      'plan_price_id' => $remote_subscription->plan->id,
      'subscription_id' => $remote_subscription->id,
      'customer_id' => $remote_subscription->customer,
      'status' => $remote_subscription->status,
      'roles' => [],
      'current_period_end' => ['value' => DrupalDateTime::createFromTimestamp($remote_subscription->current_period_end)->format('U')],
    ];
    $subscription = $this->entityTypeManager->getStorage('stripe_subscription')->create($values);
    $subscription->save();
    $this->logger->info('Created subscription entity #@subscription_id : @sub',
      [
        '@subscription_id' => $subscription->id(),
        '@sub' => json_encode($subscription)
      ]);

    return $subscription;
  }

  /**
   * Implements reactivateRemoteSubscription().
   *
   * @param string $remote_id
   *   Subscription ID on stripe plataform.
   */
  public function reactivateRemoteSubscription($remote_id) {
    // @see https://stripe.com/docs/subscriptions/guide#reactivating-canceled-subscriptions
    $subscription = Subscription::retrieve($remote_id);
    Subscription::update($remote_id,
      [
        'cancel_at_period_end' => FALSE,
        'items' => [
            [
              'id' => $subscription->items->data[0]->id,
              'plan' => $subscription->plan->id
            ]
          ]
      ]);
    $this->messenger()->addMessage('Subscription re-activated.');
    $this->logger->info('Re-activated remote subscription @subscription_id id.',
      ['@subscription_id' => $remote_id]);
  }

  /**
   * Implements cancelRemoteSubscription().
   *
   * @param string $remote_id
   *   Subscription ID on stripe plataform.
   */
  public function cancelRemoteSubscription(string $remote_id): void {
    $subscription = Subscription::retrieve($remote_id);
    if ($subscription->status != 'canceled') {
      Subscription::update($remote_id, ['cancel_at_period_end' => TRUE]);
      $this->messenger()->addMessage('Subscription cancelled. It will not renew after the current pay period.');
      $this->logger->info('Cancelled remote subscription @subscription_id.',
        ['@subscription_id' => $remote_id]);
    }
    else {
      $this->logger->info('Remote subscription @subscription_id was already cancelled.',
        ['@subscription_id' => $remote_id]);
    }
  }

  /**
   * Sets the stripe_customer_id field value for a given user.
   *
   * @param string|int $uid
   *   User ID.
   * @param string $customer_id
   *   Customer ID on stripe plataform.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function setLocalUserCustomerId($uid, string $customer_id): void {
    /** @var \Stripe\Customer $user */
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $user->set('stripe_customer_id', $customer_id);
    $user->save();
  }

  /**
   * Implements getLocalUserCustomerId().
   *
   * @param string|int $uid
   *   User ID.
   *
   * @return string
   *   Customer ID on stripe plataform.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLocalUserCustomerId($uid) {
    /** @var \Stripe\Customer $user */
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    return $user->get('stripe_customer_id')->value;
  }

  /**
   * Implements loadUserByStripeCustomerId().
   *
   * @param string $customer_id
   *   Customer ID.
   *
   * @return bool|\Drupal\user\Entity\User
   *   Return bool or User object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadUserByStripeCustomerId(string $customer_id) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['stripe_customer_id' => $customer_id]);

    if (!count($users)) {
      return FALSE;
    }

    return reset($users);
  }

}
