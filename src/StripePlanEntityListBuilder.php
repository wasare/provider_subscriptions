<?php

namespace Drupal\provider_subscriptions;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Stripe plan entities.
 *
 * @ingroup provider_subscriptions
 */
class StripePlanEntityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['plan_id'] = $this->t('Stripe plan ID');
    $header['name'] = $this->t('Name');
    $header['active'] = $this->t('Active');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\provider_subscriptions\Entity\StripePlanEntity */
    $row['plan_id'] = $entity->plan_id->value;
    $row['name'] = $entity->label();
    $row['active'] = $entity->active->value;
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as renderable array for table.html.twig.
   */
  public function render() {
    $build = parent::render();
    $build['table']['#footer'] = [
      'data' => [
        [
          'data' => $this->t(
            'Visit the %link page to synchronize with plans from Stripe.',
            [
              '%link' => Link::createFromRoute('Stripe Subscriptions configuration', 'provider_stripe.admin')->toString(),
            ]
          ),
          'colspan' => count($build['table']['#header']),
        ],
      ],
    ];
    return $build;
  }

}
