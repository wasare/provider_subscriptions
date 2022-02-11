Provider Subscriptions Drupal module
-------------------------------------

Provider Subscriptions as forked from: https://www.drupal.org/project/stripe_registration

Assumptions:

* Each user will have one one subscription, active or otherwise.

Installation:

* Add API and Pub keys at `admin/config/services/provider/stripe`
* Synchronize Stripe plans at `admin/config/services/provider/stripe`
* Edit plans at `admin/structure/stripe-subscription/stripe-plan` and select the Drupal roles that should be associated with each plan.
