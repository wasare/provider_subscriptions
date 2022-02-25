<?php

namespace Drupal\provider_subscriptions\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Stripe\Subscription;

/**
 * Defines the Stripe subscription entity.
 *
 * @ingroup provider_subscriptions
 *
 * @ContentEntityType(
 *   id = "stripe_subscription",
 *   label = @Translation("Stripe subscription"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" =
 *   "Drupal\provider_subscriptions\StripeSubscriptionEntityListBuilder",
 *     "views_data" =
 *   "Drupal\provider_subscriptions\Entity\StripeSubscriptionEntityViewsData",
 *
 *     "form" = {
 *       "default" =
 *   "Drupal\provider_subscriptions\Form\StripeSubscriptionEntityForm",
 *       "add" =
 *   "Drupal\provider_subscriptions\Form\StripeSubscriptionEntityForm",
 *       "edit" =
 *   "Drupal\provider_subscriptions\Form\StripeSubscriptionEntityForm",
 *       "delete" =
 *   "Drupal\provider_subscriptions\Form\StripeSubscriptionEntityDeleteForm",
 *     },
 *     "access" =
 *   "Drupal\provider_subscriptions\StripeSubscriptionEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" =
 *   "Drupal\provider_subscriptions\StripeSubscriptionEntityHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "stripe_subscription",
 *   admin_permission = "administer stripe subscription entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" =
 *   "/stripe-subscription/{stripe_subscription}",
 *     "add-form" =
 *   "/stripe-subscription/add",
 *     "edit-form" =
 *   "/stripe-subscription/{stripe_subscription}/edit",
 *     "delete-form" =
 *   "/stripe-subscription/{stripe_subscription}/delete",
 *     "collection" =
 *   "/admin/content/stripe-subscriptions",
 *   },
 *   field_ui_base_route = "stripe_subscription.settings"
 * )
 */
class StripeSubscriptionEntity extends ContentEntityBase implements StripeSubscriptionEntityInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\provider_subscriptions\Entity\StripeSubscriptionEntity
   *   Current object.
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\provider_subscriptions\Entity\StripeSubscriptionEntity
   *   Current object.
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\provider_subscriptions\Entity\StripeSubscriptionEntity
   *   Current object.
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\provider_subscriptions\Entity\StripePlanEntity|null
   *   Stripe Plan object or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getPlan() {
    $plans = $this->entityTypeManager()
      ->getStorage('stripe_plan')
      ->loadByProperties([
        'plan_id' => $this->getPlanId(),
      ]);

    // Use price id as plan id.
    if (!$plans && $this->getPriceId()) {
      $plans = $this->entityTypeManager()->getStorage('stripe_plan')->loadByProperties([
        'plan_id' => $this->getPriceId(),
      ]);
    }

    // Use price id as price id.
    if (!$plans && $this->getPriceId()) {
      $plans = $this->entityTypeManager()->getStorage('stripe_plan')->loadByProperties([
        'plan_price_id' => $this->getPriceId(),
      ]);
    }

    // Use plan id as name.
    if (!$plans) {
      $plans = $this->entityTypeManager()->getStorage('stripe_plan')->loadByProperties([
        'name' => $this->getPlanId(),
      ]);
    }

    if ($plans) {
      return reset($plans);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->get('plan_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriceId() {
    return $this->get('plan_price_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The owner of this subscription.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setRequired(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Stripe subscription entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ]);

    $fields['subscription_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subscription ID'))
      ->setDescription(t('The Stripe ID for this subscription.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setRequired(TRUE);

    $fields['plan_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plan ID'))
      ->setDescription(t('The Stripe ID for this plan.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setRequired(TRUE);

    $fields['plan_price_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plan Price ID'))
      ->setDescription(t('The Stripe Price ID for this plan.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setRequired(TRUE);

    $fields['customer_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Customer ID'))
      ->setDescription(t("The Stripe ID for this subscription's customer."))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setRequired(TRUE);

    // Possible values are trialing, active, past_due, canceled, or unpaid.
    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The Stripe status for this subscription.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setRequired(TRUE);

    $fields['current_period_end'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Current period end'))
      ->setDescription(t('The end of the current pay period for this subscription.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 4,
      ])
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['cancel_at_period_end'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Cancel at period end'))
      ->setDescription(t('Whether this subscription will be cancelled at the end of the current pay period.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 4,
      ])
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage);

    $this->updateUserRoles();
  }

  /**
   * Update user roles.
   */
  public function updateUserRoles() {

    $current_plan = $this->getPlan();
    $status = $this->status->value;

    if ($current_plan && $this->getOwner()) {
      $local_plans = \Drupal::entityTypeManager()
        ->getStorage('stripe_plan')
        ->loadMultiple();
      $remove_roles = [];
      foreach ($local_plans as $local_plan) {
        $roles = $local_plan->roles->getIterator();
        // Remove all plan roles.
        foreach ($roles as $role) {
          $rid = $role->value;
          if ($this->getOwner()->hasRole($rid)) {
            $this->getOwner()->removeRole($rid);
            \Drupal::logger('provider_subscriptions')->info('Removing role @rid from user @user for subscription #@sub because its status is @status', [
              '@rid' => $rid,
              '@user' => $this->getOwner()->label(),
              '@sub' => $this->id(),
              '@status' => $status,
            ]);
          }
        }
      }
      // Add roles.
      $roles = $current_plan->roles->getIterator();
      if (in_array($status, ['active', 'trialing'])) {
        foreach ($roles as $role) {
          $rid = $role->value;
          $this->getOwner()->addRole($rid);
          \Drupal::logger('provider_subscriptions')->info('Adding role @rid to user @user for subscription #@sub because its status is @status', [
            '@rid' => $rid,
            '@user' => $this->getOwner()->label(),
            '@sub' => $this->id(),
            '@status' => $status,
          ]);
        }
      }

      $this->getOwner()->save();
    }
    else {
      \Drupal::logger('provider_subscriptions')->info('Could not find local Stripe plan matching remote plan id @plan_id.', [
        '@plan_id' => $this->getPlanId()
      ]);
    }

  }

  /**
   * Update local subscription from upstream subscription.
   *
   * @param \Stripe\Subscription $remote_subscription
   *   The remote Strip subscription.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function updateFromUpstream(Subscription $remote_subscription = NULL) {
    if (!$remote_subscription) {
      $remote_subscription = Subscription::retrieve($this->subscription_id);
    }

    $this->set('name', $remote_subscription->name);
    $this->set('subscription_id', $remote_subscription->id);
    $this->set('plan_id', $remote_subscription->plan->product);
    $this->set('plan_price_id', $remote_subscription->plan->id);
    $this->set('customer_id', $remote_subscription->customer);
    $this->set('status', $remote_subscription->status);
    $this->set('current_period_end', $remote_subscription->current_period_end);

    return $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    /** @var \Drupal\provider_subscriptions\StripeSubscriptionService $stripe_api */
    $stripe_api = \Drupal::service('provider_subscriptions.stripe_api');
    /** @var StripeSubscriptionEntity $entity */
    foreach ($entities as $entity) {
      $remote_id = $entity->get('subscription_id')->value;
      try {
        $stripe_api->cancelRemoteSubscription($remote_id);
      }
      catch (\Exception $e) {
      }
      // Remove roles related with a deleted subscription.
      $entity->removeRoles();
    }
  }

  /**
   * Remove roles related with a deleted subscription.
   */
  private function removeRoles() {
    $plan = $this->getPlan();

    if ($plan && $this->getOwner()) {
      $roles = $plan->roles->getIterator();

      foreach ($roles as $role) {
        $rid = $role->value;
        $this->getOwner()->removeRole($rid);
        \Drupal::logger('provider_subscriptions')->info('Removing role @rid from @user.', [
          '@rid' => $rid,
          '@user' => $this->getOwner()->label(),
        ]);
      }
      $this->getOwner()->save();
    }
  }

}
