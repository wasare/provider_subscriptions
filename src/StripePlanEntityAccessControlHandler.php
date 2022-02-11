<?php

namespace Drupal\provider_subscriptions;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Stripe plan entity.
 *
 * @see \Drupal\provider_subscriptions\Entity\StripePlanEntity.
 */
class StripePlanEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\provider_subscriptions\Entity\StripePlanEntityInterface $entity */
    switch ($operation) {
      case 'view':
      case 'delete':
      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'administer stripe plans');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::forbidden();
  }

}
