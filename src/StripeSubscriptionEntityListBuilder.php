<?php

namespace Drupal\provider_subscriptions;

use Drupal\Core\Link;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Stripe subscription entities.
 *
 * @ingroup provider_subscriptions
 */
class StripeSubscriptionEntityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Stripe subscription ID');
    $header['subscription'] = $this->t('Subscription');
    $header['plan_name'] = $this->t('Plan Name');
    $header['customer_id'] = $this->t('Customer ID');
    $header['plan_id'] = $this->t('Plan ID');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\provider_subscriptions\Entity\StripeSubscriptionEntity $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::fromTextAndUrl(
      $entity->get('subscription_id')->value,
      new Url(
        'entity.stripe_subscription.edit_form', [
          'stripe_subscription' => $entity->id(),
        ]
      )
    );
    $row['plan_name'] = $entity->getPlan()->get('name')->value;
    $row['customer_id'] = $entity->get('customer_id')->value;
    $row['plan_id'] = $entity->getPlanId();
    $row['status'] = $entity->status->value;
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritDoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    $user_id = $entity->get('user_id')->target_id;
    // Stripe subscription id (remote subscription id).
    $subscription_id = $entity->get('subscription_id')->value;

    $url = Url::fromRoute('provider_subscriptions.stripe.cancel', [
      'user' => $user_id,
      'remote_id' => $subscription_id,
    ]);

    // Cancel button.
    if ($entity->get('status')->value != 'canceled') {
      $operations['cancel'] = [
        'title' => $this->t('Cancel'),
        'weight' => 1,
        'url' => $url,
      ];
    }

    // In our business case we don't need the 'Reactivate' button.
    // Re-activate button.
    // elseif (REQUEST_TIME < $entity->get('current_period_end')->value) {
    // $operations['reactivate'] = [
    // 'title' => $this->t('Re-activate'),
    // 'weight' => 1,
    // 'url' => Url::fromRoute('provider_subscriptions.stripe.reactivate',
    // ['remote_id' => $subscription_id]),
    // ];
    // }.
    return $operations;
  }

}
