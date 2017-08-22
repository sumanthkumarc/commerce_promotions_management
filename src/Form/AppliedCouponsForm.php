<?php

namespace Drupal\commerce_promotions_management\Form;

use Drupal\commerce\EntityHelper;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\PromotionStorage;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Link;

/**
 * Class AppliedCouponsForm.
 */
class AppliedCouponsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_promotions_management.applied_coupons',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'applied_coupons_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_promotions_management.applied_coupons');
    //$form = parent::buildForm($form, $form_state);
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
    //$config = $this->config('commerce_promotions_management.applied_coupons');
    $trig_el = $form_state->getTriggeringElement();

    if ($trig_el['#name'] == 'expire_coupon') {
      $coupon_id = $trig_el['#coupon_id'];
      $coupon = Coupon::load($coupon_id);
      $promotion = $coupon->getPromotion();
      $promotion->setEnabled(FALSE);
      $promotion->save();
    }
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
      $status = $this->t('Applied and Active.');
      $op = [
        '#type' => 'submit',
        '#value' => $this->t('Expire Coupon'),
        '#name' => 'expire_coupon',
        '#coupon_id' => $coupon->id(),
      ];
    }
    else {
      $status = $this->t('Applied and Expired.');
      $op['#markup'] = '--';
    }

    if (!empty($usage['order_id'])) {
      $order = Order::load($usage['order_id']);
      $items = $order->getItems();
      $products = [];
      foreach ($items as $item) {
        $product = $item->getPurchasedEntity();
        /* @var OrderItem $item */
        $label = $product->label();
        $route_params = ['commerce_product' => $product->id()];
        $route = 'entity.commerce_product.canonical';
        $products[] = Link::createFromRoute($label, $route, $route_params)
          ->toString();
      }

      $label = $order->getCustomer()->getDisplayName();
      $route_params = ['user' => $order->getCustomer()->id()];
      $route = 'entity.user.canonical';
      $user = Link::createFromRoute($label, $route, $route_params)
        ->toString();

      $products = implode(',', $products);
    }
    else {
      $products = $user = $this->t('--');
    }

    $row['status'] = $status;
    $row['applied_by'] = $user;
    $row['product'] = $products;
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

    $query = \Drupal::database()->select('commerce_promotion_usage', 'cpu');
    $query->fields('cpu', ['coupon_id', 'promotion_id', 'order_id']);
    $query->condition('cpu.promotion_id', $promotion_ids, 'IN');
    $query->orderBy('cpu.coupon_id');
    $usages = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(10)
      ->execute()
      ->fetchAllAssoc('coupon_id', \PDO::FETCH_ASSOC);

    return $usages;
  }

}
