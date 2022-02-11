<?php

namespace Drupal\provider_subscriptions\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Defines the Stripe plan entity.
 *
 * @ingroup provider_subscriptions
 *
 * @ContentEntityType(
 *   id = "stripe_plan",
 *   label = @Translation("Stripe plan"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\provider_subscriptions\StripePlanEntityListBuilder",
 *     "views_data" = "Drupal\provider_subscriptions\Entity\StripePlanEntityViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\provider_subscriptions\Form\StripePlanEntityForm",
 *       "add" = "Drupal\provider_subscriptions\Form\StripePlanEntityForm",
 *       "edit" = "Drupal\provider_subscriptions\Form\StripePlanEntityForm",
 *       "delete" = "Drupal\provider_subscriptions\Form\StripePlanEntityDeleteForm",
 *     },
 *     "access" = "Drupal\provider_subscriptions\StripePlanEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\provider_subscriptions\StripePlanEntityHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "stripe_plan",
 *   admin_permission = "administer stripe plan entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/stripe-subscription/stripe-plan/{stripe_plan}",
 *     "add-form" = "/admin/structure/stripe-subscription/stripe-plan/add",
 *     "edit-form" = "/admin/structure/stripe-subscription/stripe-plan/{stripe_plan}/edit",
 *     "delete-form" = "/admin/structure/stripe-subscription/stripe-plan/{stripe_plan}/delete",
 *     "collection" = "/admin/structure/stripe-subscription/stripe-plan",
 *   },
 *   field_ui_base_route = "stripe_plan.settings"
 * )
 */
class StripePlanEntity extends ContentEntityBase implements StripePlanEntityInterface {

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
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the New stripe plan entity entity.'))
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
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['plan_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Stripe Plan ID'))
      ->setDescription(t('The Stripe ID for this plan.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ]);

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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Stripe plan entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ]);

    $fields['livemode'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Live mode'))
      ->setDescription(t('If this plan is listed as live on Stripe.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
        'on_label' => new TranslatableMarkup('Live'),
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -4,
      ]);

    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('If this plan is active on Stripe.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
        'on_label' => new TranslatableMarkup('Active'),
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -4,
      ]);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Plan data'))
      ->setDescription(t('Array of raw plan data from Stripe.'));

    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = user_roles(TRUE);
    $role_options = [];
    foreach ($roles as $rid => $role) {
      $role_options[$rid] = $role->label();
    }
    // @todo Prevent administrator roles from being added here.
    $fields['roles'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Roles'))
      ->setDescription(t('Roles that will be granted to users actively subscribed to this plan. Warning: these roles will be removed from users who have cancelled or unpaid subscriptions for this plan!'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
        'allowed_values' => $role_options,
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
        'size' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -4,
        'size' => 10,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
