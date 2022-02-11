<?php

namespace Drupal\Tests\provider_subscriptions\Controller;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the provider_subscriptions module.
 */
class UserSubscriptionsControllerTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "provider_subscriptions UserSubscriptionsController's controller functionality",
      'description' => 'Test Unit for module provider_subscriptions and controller UserSubscriptionsController.',
      'group' => 'Other',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests provider_subscriptions functionality.
   */
  public function testUserSubscriptionsController() {
    // Check that the basic functions of module provider_subscriptions.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
