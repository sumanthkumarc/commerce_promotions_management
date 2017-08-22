<?php

namespace Drupal\commerce_promotions_management\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class UnusedCouponsController.
 */
class UnusedCouponsController extends ControllerBase {

  /**
   * Provides the interface for managing the Site wide discounts.
   *
   * @return array
   *   Return content for managing discounts.
   */
  public function build() {
    $content = [];

    $content['form'] = $this->formBuilder()
      ->getForm('Drupal\commerce_promotions_management\Form\UnusedCouponsForm');

    return $content;
  }

}
