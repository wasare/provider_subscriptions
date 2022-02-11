<?php

namespace Drupal\provider_subscriptions\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Stripe subscription entities.
 */
class StripeSubscriptionEntityViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['stripe_subscription']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Stripe subscription'),
      'help' => $this->t('The Stripe subscription ID.'),
    ];

    $data['stripe_subscription']['stripe_plan'] = [
      'title' => $this->t('Stripe Plan'),
      'help' => $this->t('Stripe plan of a subscription.'),
      'relationship' => [
        'id' => 'standard',
        'base' => 'stripe_plan',
        'base field' => 'plan_id',
        'field' => 'plan_id',
        'label' => $this->t('Plans'),
      ],
    ];

    return $data;
  }

}
