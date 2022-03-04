<?php

namespace Drupal\provider_subscriptions\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\provider_subscriptions\StripeSubscriptionService;
use Stripe\Plan;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Stripe Subscribe Block.
 *
 * @Block(
 *   id = "provider_subscription_stripe_subscribe_block",
 *   admin_label = @Translation("Provider Subscriptions Stripe Subscribe"),
 *   category = @Translation("Stripe Subscriptions"),
 * )
 */
class SubscribeStripeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\provider_subscriptions\StripeSubscriptionService definition.
   *
   * @var \Drupal\provider_subscriptions\StripeSubscriptionService
   */
  protected $stripeApi;

  /**
   * The current session's account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StripeSubscriptionService $stripe_api, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stripeApi = $stripe_api;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('provider_subscriptions.stripe_api'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $remote_plans = $this->stripeApi->loadRemotePlanMultiple();
    $user = User::load($this->currentUser->id());
    $build = [
      '#theme' => 'stripe_subscribe_plans_block',
      '#plans' => [],
    ];
    foreach ($remote_plans as $plan) {
      $element = [
        '#theme' => 'stripe_subscribe',
        '#plan' => [
          // This is the price_id, which does not match the local plan->id (product).
          'price_id' => $plan->id,
          'name' => $plan->name,
        ],
        '#remote_plan' => $plan,
        '#plan_entity' => $this->stripeApi->loadLocalPlan(['plan_price_id' => $plan->id]),
        // @tode Check $subscription->cancel_at_period_end.
        '#current_user_subscribes_to_any_plan' => $this->stripeApi->userHasStripeSubscription($user),
        // @tode Check $subscription->cancel_at_period_end.
        '#current_user_subscribes_to_this_plan' => $this->userIsSubscribedToPlan($user, $plan),
        '#attached' => [
          'library' => [
            'provider_subscriptions/checkout',
            'provider_subscriptions/stripe.stripejs',
            'core/drupal.dialog.ajax',
          ],
        ],
      ];

      $build['#plans'][$plan->id] = $element;
    }

    return $build;
  }

  /**
   * @param \Drupal\user\Entity\User $user
   * @param \Stripe\Plan $plan
   *
   * @return bool
   *   Return TRUE or FALSE.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function userIsSubscribedToPlan(User $user, Plan $plan): bool {
    if ($this->stripeApi->userHasStripeSubscription($user)) {
      $subscription = $this->stripeApi->loadLocalSubscription([
        'user_id' => $this->currentUser->id(),
      ]);
      return $subscription->plan_price_id->value === $plan->id || $subscription->plan_price_id->value === $plan->product;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'manage own stripe subscriptions');
  }

}
