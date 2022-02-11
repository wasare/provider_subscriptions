<?php

namespace Drupal\provider_subscriptions\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a form for deleting Stripe subscription entities.
 *
 * @ingroup provider_subscriptions
 */
class StripeSubscriptionEntityDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete subscription %myentity?', ['%myentity' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

}
