<?php

namespace Drupal\provider_subscriptions\Form; 

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class StripeSubscribeForm.
 *
 * @package Drupal\provider_subscriptions\Form
 */
class StripeSubscribeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stripe_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Form items are defined in provider_subscriptions_roles_subscribe_form() so that
    // they may be dynamically added to one or more forms.
    $form['uid'] = [
      '#type' => 'hidden',
      '#default_value' => \Drupal::currentUser()->id(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submission is handled via provider_subscriptions_submit().
  }

}
