<?php

namespace Drupal\commerce_promotions_management\Form;

use CommerceGuys\Intl\Calculator;
use Drupal\commerce\EntityHelper;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\commerce_promotion\PromotionStorage;
use Drupal\Component\Utility\Random;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DiscountControlForm.
 */
class UnusedCouponsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_promotions_management.unused_coupons',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unused_coupons_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_promotions_management.unused_coupons');
    //$form = parent::buildForm($form, $form_state);

    $form['generate'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['generate-form'],
      ],
    ];

    $form['generate']['coupon_discount_percent'] = [
      '#type' => 'number',
      '#title' => $this->t('New Coupon Discount %'),
      '#min' => 1,
      '#max' => 100,
      '#step' => 1,
      '#default_value' => 1,
    ];

    $form['generate']['coupon_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Coupon'),
      '#name' => 'generate_coupon',
    ];

    $form['coupons_table'] = $this->getCouponsTable();

    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //$config = $this->config('commerce_promotions_management.unused_coupons');
    //$values = $form_state->getValues();
    $trig_el = $form_state->getTriggeringElement();

    $discount_percentage = (string) $values['coupon_discount_percent'];

    if ($trig_el['#name'] == 'generate_coupon') {

      $order_types = \Drupal::entityTypeManager()
        ->getStorage('commerce_order_type')
        ->loadMultiple();

      $order_types = array_keys($order_types);
      // @Todo get all store ids.
      $store_ids = [1];

      $promotion = $this->createPromotion($store_ids, $order_types);
      $this->setDiscount($promotion, $discount_percentage);

      // @Todo Coupon code should be unique. Need to do $coupon->validate() or
      // $promotion->validate() before saving.
      $coupon_code = $this->getCouponCode();
      $new_coupon = $this->createCoupon($promotion, $coupon_code);
      $promotion->addCoupon($new_coupon);
      $promotion->setEnabled(TRUE);
      $promotion->save();
    }
    elseif ($trig_el['#name'] == 'expire_coupon') {
      $coupon_id = $trig_el['#coupon_id'];
      $coupon = Coupon::load($coupon_id);
      $promotion = $coupon->getPromotion();
      $promotion->setEnabled(FALSE);
      $promotion->save();
    }

  }

  /**
   * Sets the discount for the given promotion.
   *
   * @param \Drupal\commerce_promotion\Entity\Promotion $promotion
   *   The first time promotion object.
   * @param string $discount_percentage
   *   The discount percentage.
   *
   * @return \Drupal\commerce_promotion\Entity\Promotion
   *   The first time promotion object.
   */
  public function setDiscount(Promotion $promotion, string $discount_percentage): Promotion {
    if ($discount_percentage !== 0) {
      $discount_amount = Calculator::divide($discount_percentage, 100, 2);
    }
    else {
      $discount_amount = 0;
    }

    $offer['target_plugin_id'] = 'order_percentage_off';
    $offer['target_plugin_configuration'] = ['percentage' => $discount_amount];

    $promotion->set('offer', $offer);

    return $promotion;
  }

  /**
   * Creates first time promotion.
   *
   * @param array $store_ids
   *   The store ids for this promotion to apply to.
   * @param array $order_types
   *   Order types this promotion to be applied.
   *
   * @return \Drupal\commerce_promotion\Entity\Promotion|static
   *   The newly created promotion object.
   */
  public function createPromotion(array $store_ids, array $order_types): Promotion {

    $promotion = Promotion::create([
      'name' => 'site_wide_promotion',
      'order_types' => $order_types,
      'stores' => $store_ids,
      'status' => TRUE,
      'custom_promotion_type' => 'site_wide_promotion',
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '',
        ],
      ],
    ]);
    $promotion->save();

    return $promotion;
  }

  /**
   * Returns the Coupon code in format : FTD0001 etc.
   *
   * @return string
   *   The coupon code.
   */
  public function getCouponCode(): string {
    $random = new Random();
    return $random->name(8, TRUE);
  }

  /**
   * Creates the coupon.
   *
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $promotion
   *   The promotion object to attach the coupon to.
   * @param string $coupon_code
   *   The coupon code for this coupon.
   *
   * @return Coupon|static
   *   Returns the Coupon object created.
   */
  public function createCoupon(PromotionInterface $promotion, string $coupon_code): Coupon {
    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => $coupon_code,
    ]);

    $coupon->save();
    return $coupon;
  }

  /**
   * Returns the coupons table.
   *
   * @return array
   *   Render array for coupons table.
   */
  public function getCouponsTable(): array {

    $form['promotions'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#empty' => $this->t('There are no coupons yet.'),
    ];

    $usages = $this->getCoupons();
    foreach ($usages as $coupon_id => $usage) {
      $row = $this->buildRow($usage);

      $row['coupon'] = ['#markup' => $row['coupon']];
      $row['status'] = ['#markup' => $row['status']];
      $row['applied_by'] = ['#markup' => $row['applied_by']];
      $row['product'] = ['#markup' => $row['product']];
      $row['operation'] = $row['operation'];

      $form['promotions'][] = $row;
    }

    return $form;
  }

  /**
   * Returns the header for table.
   *
   * @return array
   *   Header array.
   */
  public function buildHeader(): array {
    $header['coupon'] = $this->t('Coupon');
    $header['status'] = $this->t('Status');
    $header['applied_by'] = $this->t('Applied by');
    $header['product'] = $this->t('Product');
    $header['operation'] = $this->t('Operations');

    return $header;
  }

  /**
   * Builds a coupon usage row.
   *
   * @param array $usage
   *   Coupon usage array with details.
   *
   * @return array
   *   Render array for the row.
   */
  public function buildRow(array $usage): array {
    $coupon = Coupon::load($usage['coupon_id']);

    if (empty($coupon)) {
      return [];
    }

    $row['coupon'] = $coupon->label();

    $promotion = $coupon->getPromotion();
    if (!empty($promotion) && ($promotion->isEnabled())) {
      $status = $this->t('Unused and Active.');
      $op = [
        '#type' => 'submit',
        '#value' => $this->t('Expire Coupon'),
        '#name' => 'expire_coupon',
        '#coupon_id' => $coupon->id(),
      ];
    }
    else {
      $status = $this->t('Unused and Expired.');
      $op['#markup'] = '--';
    }

    $row['status'] = $status;
    $row['applied_by'] = '--';
    $row['product'] = '--';
    $row['operation'] = $op;

    return $row;
  }

  /**
   * Returns the coupons usage details.
   *
   * @return array
   *   Coupon usage details array.
   */
  public function getCoupons(): array {
    /* @var PromotionStorage $promotion_storage */
    $promotion_storage = \Drupal::entityTypeManager()
      ->getStorage('commerce_promotion');
    $promotions = $promotion_storage->loadByProperties([
      'custom_promotion_type' => 'site_wide_promotion',
    ]);

    if (empty($promotions)) {
      return [];
    }

    $promotion_ids = EntityHelper::extractIds($promotions);

    $query = \Drupal::database()->select('commerce_promotion_coupon', 'cpc');
    $query->addField('cpc', 'id', 'coupon_id');
    $query->leftJoin('commerce_promotion_usage', 'cpu', 'cpc.id=cpu.coupon_id');
    $query->condition('cpc.promotion_id', $promotion_ids, 'IN');
    $query->condition('cpu.order_id', NULL, 'IS');
    $query->orderBy('cpc.id');
    $usages = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(10)
      ->execute()
      ->fetchAllAssoc('coupon_id', \PDO::FETCH_ASSOC);

    return $usages;
  }

}
