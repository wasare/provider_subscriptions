<?php

namespace Drupal\provider_subscriptions\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

use Drupal\provider_subscriptions\Event\StripeCreateSubscribeSessionEvent;
use Drupal\provider_subscriptions\StripeSubscriptionService;

use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as BillingSession;
use Stripe\Plan;
use Stripe\Price;

/**
 * Class UserSubscriptionsController.
 *
 * @package Drupal\provider_subscriptions\Controller
 */
class UserSubscriptionsController extends ControllerBase {

  /**
   * Drupal\provider_subscriptions\StripeSubscriptionService definition.
   *
   * @var \Drupal\provider_subscriptions\StripeSubscriptionService
   */
  protected $stripeSubscription;

  /**
   * Logger definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Request definition.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * UserSubscriptionsController constructor.
   *
   * @param \Drupal\provider_subscriptions\StripeSubscriptionService $provider_subscriptions
   *   StripeSubscriptionService object.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   LoggerChannelInterface object.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   AccountProxyInterface object.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   RequestStack object.
   */
  public function __construct(
    StripeSubscriptionService $provider_subscriptions,
    LoggerChannelInterface $logger,
    AccountProxyInterface $current_user,
    RequestStack $requestStack) {
    $this->stripeSubscription = $provider_subscriptions;
    $this->logger = $logger;
    $this->currentUser = $current_user;
    $this->currentRequest = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('provider_subscriptions.stripe_api'),
      $container->get('logger.channel.provider_subscriptions'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * SubscribeForm.
   *
   * @return array
   *   Return SubscribeForm.
   */
  public function subscribeForm() {
    $form = $this->formBuilder()->getForm('Drupal\provider_subscriptions\Form\StripeSubscribeForm');

    return $form;
  }

  /**
   * Cancel subscription.
   */
  public function cancelSubscription() {

    $remote_id = \Drupal::request()->get('remote_id');
    $user_id = $this->currentUser()->id();

    try {
      $this->stripeApi->cancelRemoteSubscription($remote_id);
      $local_subscription = $this->stripeSubscription->loadLocalSubscription(['subscription_id' => $remote_id]);
      // Setup the status of a local subscription in 'cancel'.
      // As the Stripes changes the status after some time wee will not to
      // synchronize local subscription with a remote subscription.
      $local_subscription->set('status', 'canceled');
      // Roles related with a subscription will be removed after calling save()
      // method, because saving initiates a call of the 'updateUserRoles()'
      // method of a local subscription entity.
      $local_subscription->save();
    }
    catch (\Exception $e) {
    }

    // Do not do the redirect to the 'Subscription Plans' because it will be
    // done with the 'Rules' module.
    // The redirect may depend of a plan that user have unsubscribed.
    return [];

  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   TRUE if the user is allowed to cancel the subscription.
   */
  public function accessCancelSubscription(AccountInterface $account) {
    $remote_id = \Drupal::request()->get('remote_id');

    return AccessResult::allowedIf($account->hasPermission('administer stripe subscriptions') ||
      ($account->hasPermission('manage own stripe subscriptions') && $this->stripeSubscription->userHasStripeSubscription($account, $remote_id)));
  }

  /**
   * Reactivate subscription.
   */
  public function reactivateSubscription() {
    // $remote_id = $this->request->get('remote_id');
    $remote_id = \Drupal::request()->get('remote_id');

    $this->stripeApi->reactivateRemoteSubscription($remote_id);
    $this->stripeApi->syncRemoteSubscriptionToLocal($remote_id);

    return $this->redirect("provider_subscriptions.user.subscriptions.viewall", [
      'user' => $this->currentUser()->id(),
    ]);
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   TRUE if the user is allowed to reactivate a subscription.
   */
  public function accessReactivateSubscription(AccountInterface $account) {
    $remote_id = \Drupal::request()->get('remote_id');

    return AccessResult::allowedIf($account->hasPermission('administer stripe subscriptions') ||
      ($account->hasPermission('manage own stripe subscriptions') && $this->stripeSubscription->userHasStripeSubscription($account, $remote_id)));
  }

  /**
   * Redirect.
   *
   * @return string
   *   Return Hello string.
   */
  public function redirectToSubscriptions() {
    return $this->redirect('provider_subscriptions.manage_subscriptions',
      ['user' => $this->currentUser()->id()],
      ['query' => $this->currentRequest->query->all()]
    );
  }

  /**
   * SubscribeTitle().
   *
   * @return string
   *   Title.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see \Drupal\provider_subscriptions\Plugin\Menu\SubscribeMenuLink::getTitle()
   */
  public function subscribeTitle() {
    if ($this->stripeSubscription->userHasStripeSubscription($this->currentUser())) {
      return 'Upgrade';
    }
    return 'Subscribe';
  }

  /**
   * Implements subscribe()
   *
   * @return array
   *   Return
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function subscribe(): array {
    $remote_plans = $this->stripeSubscription->loadRemotePlanMultiple(
      [
        'limit' => 100,
        'active' => TRUE,
      ]
    );
    $user = User::load($this->currentUser->id());
    $build = [
      '#theme' => 'stripe_subscribe_plans',
      '#plans' => [],
    ];
    foreach ($remote_plans as $plan) {
      $product = $this->stripeSubscription->loadRemoteProductById($plan->product);
      if ($product->active == 'true') {
        $element = [
          '#theme' => 'stripe_subscribe',
          '#plan' => [
            // This is the price_id, which does not match the local plan->id (product).
            'price_id' => $plan->id,
            'name' => $plan->name,
          ],
          '#remote_plan' => $plan,
          '#plan_entity' => $this->stripeSubscription->loadLocalPlan(['plan_price_id' => $plan->id]),
          // @tode Check $subscription->cancel_at_period_end.
          '#current_user_subscribes_to_any_plan' => $this->stripeSubscription->userHasStripeSubscription($user),
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
    }

    return $build;
  }

  /**
   * Implements createSubscribeSession.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object.
   *
   * @throws \Exception
   */
  public function createSubscribeSession(Request $request): Response {
    // Simply instantiating the service will configure Stripe with the correct API key.
    /** @var \Drupal\provider_stripe\StripeApiService $stripe_api */
    $stripe_api = \Drupal::service('provider_stripe.stripe_api');

    $price = Price::retrieve($request->get('price_id'));
    $trial_period_days = 1;
    $product = '';
    if (count($price) > 0) {
      $product = $price->product;
      $plans = \Drupal::entityTypeManager()->getStorage('stripe_plan')
        ->loadByProperties(
          [
            'plan_id' => $product
          ]
        );

      if (count($plans) > 0) {
        $plan = reset($plans);
        $trial_period_days = $plan->field_trial_period_days->value;
      }
    }

    if ($request->get('return_url')) {
      $success_url = Url::fromUri(
        'internal:/' . $request->get('return_url'),
        [
          'absolute' => TRUE,
          'query' => [
            'checkout' => 'success',
            'price_id' => $request->get('price_id'),
          ],
        ])->toString();
      $cancel_url = Url::fromUri(
        'internal:/' . $request->get('return_url'),
        [
          'absolute' => TRUE,
          'query' => [
            'checkout' => 'failure',
            'price_id' => $request->get('price_id'),
          ],
        ])->toString();
    }
    else {
      $success_url = Url::fromRoute(
        '<front>', [], [
          'absolute' => TRUE,
          'query' => ['checkout' => 'success']
        ])->toString();
      $cancel_url = Url::fromRoute(
        '<front>', [], [
          'absolute' => TRUE,
          'query' => ['checkout' => 'failure']
        ])->toString();
    }

    $params = [
      'payment_method_types' => ['card'],
      'line_items' => [
        [
          'quantity' => 1,
          // This must correspond to an existing price id in the Stripe backend.
          'price' => $request->get('price_id'),
        ],
      ],
      'mode' => 'subscription',
      'subscription_data' => [
        // 'trial_from_plan' => TRUE,
        'trial_period_days' => $trial_period_days,
      ],
      'metadata' => [
        'module' => 'provider_subscriptions',
        'uid' => $this->currentUser()->id(),
      ],
      'allow_promotion_codes' => TRUE,
      'success_url' => $success_url,
      'cancel_url' => $cancel_url,
    ];
    if ($customer_id = $this->stripeSubscription->getLocalUserCustomerId($this->currentUser()->id())) {
      $params['customer'] = $customer_id;
    }
    else {
      $params['customer_email'] = $this->currentUser()->getEmail();
    }

    $event = new StripeCreateSubscribeSessionEvent($this->currentUser(), $params);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(StripeCreateSubscribeSessionEvent::EVENT_NAME, $event);

    try {
      $session = Session::create($params);
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
    }

    $response_content = [
      'session_id' => $session->id,
      'public_key' => \Drupal::service('provider_stripe.stripe_api')->getPubKey(),
    ];

    return new Response(json_encode($response_content), Response::HTTP_ACCEPTED);
  }

  /**
   * Implements manageSubscriptionsAccess().
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Return
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function manageSubscriptionsAccess($user) {
    return AccessResult::allowedIf(
      $this->stripeSubscription->userHasStripeSubscription(User::load($user))
    );
  }

  /**
   * Implements manageSubscriptions().
   *
   * @param string|int $user
   *   User ID.
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   *   TrustedRedirectResponse object or array.
   */
  public function manageSubscriptions($user) {
    try {
      $customer_id = $this->stripeSubscription->getLocalUserCustomerId($user);
      if ($this->currentRequest->query->has('return_url')) {
        $return_url_string = Url::fromUri('internal:' . $this->currentRequest->query->get('return_url'), ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();
      }
      else {
        $return_url = Url::fromRoute('user.page', [], ['absolute' => TRUE]);
        // This was not fun.
        // @see https://www.drupal.org/node/2630808
        // @see https://drupal.stackexchange.com/questions/225956/cache-controller-with-json-response
        // @see https://www.lullabot.com/articles/early-rendering-a-lesson-in-debugging-drupal-8
        $return_url_string = $return_url->toString(TRUE)->getGeneratedUrl();
      }
      $session = BillingSession::create([
        'customer' => $customer_id,
        'return_url' => $return_url_string,
      ]);
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return [
        '#markup' => 'Something went wrong! ' . $exception->getMessage(),
      ];
    }

    return new TrustedRedirectResponse($session->url);
  }

  /**
   * Implements userIsSubscribedToPlan().
   *
   * @param \Drupal\user\Entity\User $user
   *   User object.
   * @param \Stripe\Plan $plan
   *   Plan object.
   *
   * @return bool
   *   TRUE or FALSE.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function userIsSubscribedToPlan(User $user, Plan $plan): bool {
    if ($this->stripeSubscription->userHasStripeSubscription($user)) {
      $subscription = $this->stripeSubscription->loadLocalSubscription([
        'user_id' => $this->currentUser->id(),
      ]);
      return $subscription->plan_price_id->value === $plan->id || $subscription->plan_price_id->value === $plan->product;
    }

    return FALSE;
  }

}
