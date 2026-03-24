<?php

namespace Drupal\bws\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\bat_event_series\Entity\EventSeries;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use RRule\RRule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;

class AddReservationForm extends FormBase
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The order item storage.
   *
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The node type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodetypeStorage;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a new AddReservationForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce_cart\CartManagerInterface|null $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface|null $cart_provider
   *   The cart provider.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ?CartManagerInterface $cart_manager, ?CartProviderInterface $cart_provider)
  {

    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
    if ($entity_type_manager->hasHandler('commerce_order_item', 'storage')) {
      $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
    }

    $this->nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  public function isAllowedAccess()
  {
    $bundle = FALSE;
    $route = \Drupal::routeMatch();
    $node = $route->getParameter('node');

    $current_user = \Drupal::currentUser()->id();
    $uid = $node->getOwnerId();

    $estado = $node->get('field_estado')->getString();

    if ($node instanceof NodeInterface) {

      $units = $node->get('field_availability_daily')->getValue();

      if (empty($units)) {
        return AccessResult::forbidden();
      }

      if ($current_user == $uid) {
        return AccessResult::forbidden();
      }

      if ($estado != "3") {
        return AccessResult::forbidden();
      }

      // return AccessResult::allowedIf(!empty($units) );
    }
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('commerce_cart.cart_manager', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
      $container->get('commerce_cart.cart_provider', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'bws_add_reservation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL, EventSeries $bat_event_series = NULL)
  {

    if ($form_state->has('page_num') && $form_state->get('page_num') == 2) {
      return $this->aluguerParcialPage($form, $form_state);
    }

    if ($form_state->has('page_num') && $form_state->get('page_num') == 3) {
      return $this->resumeAluguerParcialPage($form, $form_state);
    }

    if ($form_state->has('page_num') && $form_state->get('page_num') == 4) {
      return $this->resumeAluguerTotalPage($form, $form_state);
    }

    $form_state->set('page_num', 1);

    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $today = new \DateTime();

    $tomorrow = clone($today);
    $tomorrow->modify('+1 day');

    $one_hour_later = clone($today);
    $one_hour_later->modify('+1 hour');

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $form['start_date'] = [
      '#type' => ($bws_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => $this->t('Start date'),
      '#default_value' => ($bws_settings['bookable_type'] == 'daily') ? $today->format('Y-m-d') : new DrupalDateTime($today->format('Y-m-d H:00')),
      '#date_increment' => 60,
      '#required' => true,
    ];

    $form['end_date'] = [
      '#type' => ($bws_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => $this->t('End date'),
      '#default_value' => ($bws_settings['bookable_type'] == 'daily') ? $tomorrow->format('Y-m-d') : new DrupalDateTime($one_hour_later->format('Y-m-d H:00')),
      '#date_increment' => 60,
      '#required' => true,
    ];

    $form['contact'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I want to be contacted'),
      '#description' => $this->t('Ask one of our managers to contact you as soon as possible to make a guided reservation.'),
      '#description_display' => "after",
      '#states' => [
        'invisible' => [
          [':input[name="aluguer_imovel"]' => ['checked' => TRUE],],
          'or',
          [':input[name="aluguer_parcial"]' => ['checked' => TRUE],],
        ],
      ],
    ];

    $form['aluguer_imovel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Property rental'),
      '#description' => $this->t('Renting the property in its entirety and not sharing it with other entities.'),
      '#states' => [
        'invisible' => [
          [':input[name="contact"]' => ['checked' => TRUE],],
          'or',
          [':input[name="aluguer_parcial"]' => ['checked' => TRUE],],
        ],
      ],
    ];

    if ($node->get('field_permite_ser_partilhado')->getString() == "sim" || $node->get('field_tem_camaras_de_frio')->getString() == "sim") {

      $form['aluguer_parcial'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Partial rental'),
        '#description' => $this->t('Rent a part of the property, which can be shared with other entities.'),
        '#states' => [
          'invisible' => [
            [':input[name="contact"]' => ['checked' => TRUE],],
            'or',
            [':input[name="aluguer_imovel"]' => ['checked' => TRUE],],
          ],
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['next_imovel'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Confirm'),
      '#submit' => ['::resumeAluguerTotalNextSubmit'],
      '#states' => [
        'visible' => [
          ':input[name="aluguer_imovel"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['actions']['contact'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Contact'),
      '#validate' => ['::contactValidate'],
      '#submit' => ['::contactSubmit'],
      '#states' => [
        'visible' => [
          ':input[name="contact"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if ($node->get('field_permite_ser_partilhado')->getString() == "sim" || $node->get('field_tem_camaras_de_frio')->getString() == "sim") {

      $form['actions']['next_parcial'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Next'),
        '#submit' => ['::aluguerParcialNextSubmit'],
        '#states' => [
          'visible' => [
            ':input[name="aluguer_parcial"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form['#attached']['library'][] = 'bws/bws_form';

    return $form;
  }

  // aluguer total do armazem

  public function resumeAluguerTotalNextSubmit(array &$form, FormStateInterface $form_state)
  {

    $form_state
      ->set('page_values', [
        'start_date' => $form_state->getValue('start_date'),
        'end_date' => $form_state->getValue('end_date'),
        'node' => $form_state->getValue('node'),
      ])
      ->set('page_num', 4)
      ->setRebuild(TRUE);
  }

  public function resumeAluguerTotalPage(array &$form, FormStateInterface $form_state)
  {

    $values = $form_state->getValues();
    $node = $values['node'];
    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $start_date_format = new \DateTime($start_date);
    $end_date_format = new \DateTime($end_date);

    $price = $this->calculatePrices($node, $start_date, $end_date, $form_state, "field_preco_full");

    $node_entity = $this->nodeStorage->load($node);

    $config = \Drupal::config('bws.settings');

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $node,
    ];

    $form['description_date'] = [
      '#type' => 'item',
      '#title' => $this->t('Dates'),
      '#markup' => $start_date_format->format('d-m-Y') . " até " . $end_date_format->format('d-m-Y'),
    ];

    $form['description_tipo_aluguer'] = [
      '#type' => 'item',
      '#title' => $this->t('Type of rental'),
      '#markup' => $this->t('Total'),
    ];

    $form['description_espaço_alugado'] = [
      '#type' => 'item',
      '#title' => $this->t('Rented space'),
      '#markup' => $this->t('Total property rental'),
    ];

    // $form['description_pagamentos'] = [
    //   '#type' => 'item',
    //   '#title' => $this->t('Payments'),
    //   '#markup' => $this->t('Amount to pay:')."<br>" . $price . " €",
    // ];

    $form['description_note'] = [
      '#type' => 'item',
      '#markup' => $config->get('message_form_add_reservation'),
    ];

    $form['price'] = [
      '#type' => 'hidden',
      '#value' => $price,
    ];


    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::subscribePageTwoBack'],
      '#validate' => ['::bwsMultistepFormNextValidate'],
      '#limit_validation_errors' => [],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getStorage()['page_values'];
    $values_values = $form_state->getValues();

    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $node = $this->nodeStorage->load($values['node']);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
    }

    if ($bws_settings['bookable_type'] == 'daily') {
      $booked_state = bat_event_load_state_by_machine_name('bws_daily_pending');

      $event = bat_event_create(['type' => 'availability_daily']);

      $event_dates = [
        'value' => $start_date->format('Y-m-d\TH:i:00'),
        'end_value' => $end_date->format('Y-m-d\TH:i:00'),
      ];
      $event->set('event_dates', $event_dates);
      $event->set('event_state_reference', $booked_state->id());
    }

    $available_units = $this->getAvailableUnits($values['node'], $values['start_date'], $values['end_date'], "area_total");
    $event->set('event_bat_unit_reference', reset($available_units));

    $event->set('field_armazem', $values['node']);
    $event->set('field_price', $values_values['price']);
    $event->set('field_type', 0);
    $event->set('field_user', \Drupal::currentUser()->id());

    $event->save();

    $this->messenger()->addMessage($this->t('Reservation created!'));

    $message = "Foi efetuada uma nova reserva.";
    $userStorage = \Drupal::entityTypeManager()->getStorage('user');
    $query = $userStorage->getQuery();
    $uids = $query
      ->condition('status', '1')
      ->condition('roles', 'gestor')
      ->accessCheck(FALSE)
      ->execute();

    $uids = (array) $uids;
    $uids = array_filter($uids, function ($id) {
      return is_int($id) || is_string($id);
    });

    $users = $userStorage->loadMultiple($uids);

    foreach ($users as $user) {
      $this->send_email($user->getEmail(), "Foi efetuada uma nova reserva", $message);
    }

    if (isset($values['event_series'])) {
      $form_state->setRedirect('entity.bat_event_series.canonical', ['bat_event_series' => $values['event_series']]);
    } else {
      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();
    $storage = $form_state->getStorage();
    $values_storage = isset($storage['page_values']) && is_array($storage['page_values'])
      ? $storage['page_values']
      : [];

    $node = $this->nodeStorage->load($values['node']);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');


    $start_date_raw = $values['start_date'] ?? ($values_storage['start_date'] ?? NULL);
    $end_date_raw  = $values['end_date'] ?? ($values_storage['end_date'] ?? NULL);

    if (!$start_date_raw || !$end_date_raw) {
      $form_state->setErrorByName('start_date', $this->t('You must select a start and end date.'));
      return;
    }
    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date_raw);
      $end_date = new \DateTime($end_date_raw);
    }

    $dates_valid = TRUE;

    //@TODO: validar erro e colocar validação de reserva

    if ($dates_valid) {
      /*if ($end_date <= $start_date) {
        $form_state->setErrorByName('end_date', $this->t('End date must be after the start date.'));
        return;
      }

      if($this->checkAvailableAreaTotal($values['node'], $start_date_raw, $end_date_raw ,"area_partilhada") == false) {
        $form_state->setError($form, $this->t('No availability for the selected dates.'));
        return;
      }

      if($this->checkAvailableAreaTotal($values['node'], $start_date_raw, $end_date_raw ,"camara_de_frio") == false) {
        $form_state->setError($form, $this->t('No availability for the selected dates.'));
        return;
      }
      

      $available_units = $this->getAvailableUnits($values['node'], $start_date_raw, $end_date_raw ,"area_total");
      if (empty($available_units)) {
        $form_state->setError($form, $this->t('No availability for the selected dates.'));
      }*/
    }
  }

  public function checkAvailableAreaTotal($nid, $start_date, $end_date, $type)
  {

    $node = $this->nodeStorage->load($nid);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bws_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        if ($unit->entity->type->getString() == $type) {
          $units_ids[] = $unit->entity->id();
        }
      }
    }

    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
      $end_date->sub(new \DateInterval('PT1M'));

      $available_units_ids = bat_event_get_matching_units($start_date, $end_date, ['bws_daily_available'], [$bws_settings['type_id']], 'availability_daily');
    }

    if (count($units_ids) == count(array_intersect($units_ids, $available_units_ids))) {
      return true;
    }

    return false;
  }

  // aluguer parcial do armazem

  public function aluguerParcialNextSubmit(array &$form, FormStateInterface $form_state)
  {
    $form_state
      ->set('page_values', [
        // Keep only first step values to minimize stored data.
        'start_date' => $form_state->getValue('start_date'),
        'end_date' => $form_state->getValue('end_date'),
        'node' => $form_state->getValue('node'),
      ])
      ->set('page_num', 2)
      ->setRebuild(TRUE);
  }

  public function aluguerParcialPage(array &$form, FormStateInterface $form_state)
  {

    $values = $form_state->getStorage()['page_values'];
    $node = $values['node'];
    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $start_date_format = new \DateTime($start_date);
    $end_date_format = new \DateTime($end_date);

    $node_entity = $this->nodeStorage->load($node);

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $node,
    ];

    $form['description_date'] = [
      '#type' => 'item',
      '#title' => $this->t('Dates'),
      '#markup' => $start_date_format->format('d-m-Y') . " até " . $end_date_format->format('d-m-Y'),
    ];

    $camaras_frio_avaiable = $this->getCamarasFrioUnits($node, $start_date, $end_date, "camara_de_frio");

    if ($camaras_frio_avaiable) {


      if ($node_entity->get('field_permite_ser_partilhado')->getString() == "sim") {

        $form['tipo_aluguer'] = [
          '#type' => 'radios',
          '#default_value' => null,
          '#multiple' => true,
          '#title' => t('Type of rental'),
          '#required' => TRUE,
          '#options' => array(
            "camara_frio" => t('Cold storage rooms'),
            "aluguer_espaço" => t('Renting space')
          ),
        ];
      } else {

        $form['tipo_aluguer'] = [
          '#type' => 'radios',
          '#default_value' => "camara_frio",
          '#multiple' => true,
          '#title' => t('Type of rental'),
          '#required' => TRUE,
          '#options' => array(
            "camara_frio" => t('Cold storage rooms')
          ),
        ];
      }

      $form['camara_de_frio'] = [
        '#type' => 'radios',
        '#default_value' => null,
        '#multiple' => true,
        '#title' => t('Cold storage rooms'),
        '#options' => $camaras_frio_avaiable,
        '#states' => [
          'visible' => [
            ':input[name="tipo_aluguer"]' => ['value' => 'camara_frio'],
          ],
        ],
      ];

      if ($node_entity->get('field_permite_ser_partilhado')->getString() == "sim") {
        $form['unidades_medida'] = [
          '#type' => 'select',
          '#title' => $this->t('Measurement units'),
          '#options' => [
            'all' => $this->t('Choose one'),
            'area_partilhada' => t('Square meters'),
            'euro_paletes' => t('Europallets'),
            'paletes_americanas' => t('American pallets'),
          ],
          '#states' => [
            'visible' => [
              ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],
            ],
          ]
        ];
      }
    } else {

      $form['tipo_aluguer'] = [
        '#type' => 'radios',
        '#default_value' => "aluguer_espaço",
        '#multiple' => true,
        '#title' => t('Type of rental'),
        '#required' => TRUE,
        '#options' => array(
          "aluguer_espaço" => t('Renting space')
        ),
      ];

      if ($node_entity->get('field_permite_ser_partilhado')->getString() == "sim") {
        $form['unidades_medida'] = [
          '#type' => 'select',
          '#title' => $this->t('Measurement units'),
          '#options' => [
            'all' => $this->t('Choose one'),
            'area_partilhada' => t('Square meters'),
            'euro_paletes' => t('Europallets'),
            'paletes_americanas' => t('American pallets'),
          ],
        ];
      }
    }

    $form['container'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'grid grid-cols-12 gap-4',
        ),
      ),
    );


    $form['container']['row_full'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'col-span-12',
        ),
      ),
    );

    $form['container']['row_left'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'col-span-12 xl:col-span-6',
        ),
      ),
    );

    $form['container']['row_right'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'col-span-12 xl:col-span-6',
        ),
      ),
    );

    $form['container']['row_left']['comprimento'] = [
      '#type' => 'number',
      '#title' => $this->t('Length (m)'),
      '#states' => [
        'visible' => [
          ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],
          ':input[name="unidades_medida"]' => ['value' => 'area_partilhada'],
        ],
      ],
    ];

    $form['container']['row_right']['largura'] = [
      '#type' => 'number',
      '#title' => $this->t('Width (m)'),
      '#states' => [
        'visible' => [
          ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],
          ':input[name="unidades_medida"]' => ['value' => 'area_partilhada'],
        ],
      ],
    ];

    $form['container']['row_full']['quantidade'] = [
      '#type' => 'number',
      '#title' => $this->t('Total pallets'),
      '#states' => [
        'visible' => [
          [':input[name="unidades_medida"]' => ['value' => 'euro_paletes'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
          'or',
          [':input[name="unidades_medida"]' => ['value' => 'paletes_americanas'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
        ],
      ],
    ];

    $form['container']['row_full']['paletes_altura'] = [
      '#type' => 'number',
      '#title' => $this->t('No. pallets supported at a height?'),
      '#states' => [
        'visible' => [
          [':input[name="unidades_medida"]' => ['value' => 'euro_paletes'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
          'or',
          [':input[name="unidades_medida"]' => ['value' => 'paletes_americanas'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
        ],
      ],
    ];

    $form['container']['row_left']['incluir_in'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include IN'),
      '#states' => [
        'visible' => [
          [':input[name="unidades_medida"]' => ['value' => 'euro_paletes'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
          'or',
          [':input[name="unidades_medida"]' => ['value' => 'paletes_americanas'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
        ],
      ],
    ];

    $form['container']['row_right']['incluir_out'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include OUT'),
      '#states' => [
        'visible' => [
          [':input[name="unidades_medida"]' => ['value' => 'euro_paletes'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
          'or',
          [':input[name="unidades_medida"]' => ['value' => 'paletes_americanas'], ':input[name="tipo_aluguer"]' => ['value' => 'aluguer_espaço'],],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#validate' => ['::bwsMultistepFormNextValidate'],
      '#submit' => ['::subscribePageTwoBack'],
      '#limit_validation_errors' => [],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Next'),
      '#validate' => ['::validateAluguerParcialPage'],
      '#submit' => ['::resumeAluguerParcialNextSubmit']
    ];

    return $form;
  }

  public function resumeAluguerParcialNextSubmit(array &$form, FormStateInterface $form_state)
  {

    $name = $form_state->get('page_values');
    $form_state
      ->set('page_values', [
        'tipo_aluguer' => $form_state->getValue('tipo_aluguer'),
        'camara_de_frio' => $form_state->getValue('camara_de_frio'),
        'unidades_medida' => $form_state->getValue('unidades_medida'),
        'comprimento' => $form_state->getValue('comprimento'),
        'largura' => $form_state->getValue('largura'),
        'quantidade' => $form_state->getValue('quantidade'),
        'paletes_altura' => $form_state->getValue('paletes_altura'),
        'incluir_in' => $form_state->getValue('incluir_in'),
        'incluir_out' => $form_state->getValue('incluir_out'),
        'start_date' => $name['start_date'],
        'end_date' => $name['end_date'],
        'node' => $name['node'],

      ])
      ->set('page_num', 3)
      ->setRebuild(TRUE);
  }

  public function resumeAluguerParcialPage(array &$form, FormStateInterface $form_state)
  {

    $values = $form_state->getValues();
    $values_storage = $form_state->getStorage()['page_values'];

    $node = $values['node'];
    $tipo_aluguer = $values['tipo_aluguer'];
    $camara_de_frio = $values['camara_de_frio'] ?? NULL;
    $unidades_medida = $values['unidades_medida'];
    $comprimento = $values['comprimento'];
    $quantidade = $values['quantidade'];
    $incluir_in = $values['incluir_in'];
    $largura = $values['largura'];
    $paletes_altura = $values['paletes_altura'];
    $incluir_out = $values['incluir_out'];
    $start_date = $values_storage['start_date'];
    $end_date = $values_storage['end_date'];

    $start_date_format = new \DateTime($start_date);
    $end_date_format = new \DateTime($end_date);

    $node_entity = $this->nodeStorage->load($node);

    if ($tipo_aluguer == null) {
      $tipo_aluguer = 'aluguer_espaço';
    }

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $node,
    ];

    $form['description_date'] = [
      '#type' => 'item',
      '#title' => $this->t('Dates'),
      '#markup' => $start_date_format->format('d-m-Y') . " até " . $end_date_format->format('d-m-Y'),
    ];

    $form['description_tipo_aluguer'] = [
      '#type' => 'item',
      '#title' => $this->t('Type of rental'),
      '#markup' => $this->t('Partial'),
    ];

    if ($tipo_aluguer == 'camara_frio') {
      $price = $this->calculatePrices($node, $start_date, $end_date, $form_state, "field_preco_temp");

      $entity = \Drupal::entityTypeManager()->getStorage('bat_unit')->load($camara_de_frio);

      $form['description_espaço_alugado'] = [
        '#type' => 'item',
        '#title' => $this->t('Rented space'),
        '#markup' => $entity->get("name")->getString(),
      ];
    } elseif ($tipo_aluguer == 'aluguer_espaço') {

      if ($unidades_medida == "area_partilhada") {
        $price_days = $this->calculatePrices($node, $start_date, $end_date, $form_state, "field_preco_partilhado");

        $area = floatval($comprimento) * floatval($largura);

        $price = $area * $price_days;

        $string = $comprimento . " m " . $this->t('in length') . " <br>" . $largura . " m " . $this->t('in width');
      } elseif ($unidades_medida == "euro_paletes") {

        $price_unit = $this->calculatePrices($node, $start_date, $end_date, $form_state, "field_euro_paletes");

        $price = floatval($quantidade) * $price_unit;

        $paragraph_id = $node_entity->get("field_euro_paletes")->getValue()[0]['target_id'];
        $p = \Drupal\paragraphs\Entity\Paragraph::load($paragraph_id);
        $price_in = floatval($p->get('field_preco_in')->getString());
        $price_out = floatval($p->get('field_preco_out')->getString());

        if ($incluir_in == 1) {
          $string_in = $this->t("Include IN");

          $price += $price_in * floatval($quantidade);
        }

        if ($incluir_out == 1) {
          $string_out = $this->t("Include OUT");

          $price += $price_out * floatval($quantidade);
        }

        if ($paletes_altura == null) {
          $paletes_altura = 0;
        }

        $string = $quantidade . " " . $this->t("europallets with") . " " . $paletes_altura . " " . $this->t("pallets at height") . "<br>" . $string_in . "<br>" . $string_out;
      } elseif ($unidades_medida == "paletes_americanas") {

        $price_unit = $this->calculatePrices($node, $start_date, $end_date, $form_state, "field_paletes_americanas");

        $price = floatval($quantidade) * $price_unit;

        $paragraph_id = $node_entity->get("field_paletes_americanas")->getValue()[0]['target_id'];
        $p = \Drupal\paragraphs\Entity\Paragraph::load($paragraph_id);
        $price_in = floatval($p->get('field_preco_in')->getString());
        $price_out = floatval($p->get('field_preco_out')->getString());

        if ($incluir_in == 1) {
          $string_in = $this->t("Include IN");

          $price += $price_in * floatval($quantidade);
        }

        if ($incluir_out == 1) {
          $string_out =  $this->t("Include OUT");

          $price += $price_out * floatval($quantidade);
        }

        if ($paletes_altura == null) {
          $paletes_altura = 0;
        }

        $string = $quantidade . " " . $this->t("american pallets with") . " " . $paletes_altura . " " . $this->t("pallets at height") . "<br>" . $string_in . "<br>" . $string_out;
      }

      $form['description_espaço_alugado'] = [
        '#type' => 'item',
        '#title' => $this->t('Rented space'),
        '#markup' => $string,
      ];
    }

    $config = \Drupal::config('bws.settings');

    $form['price'] = [
      '#type' => 'hidden',
      '#value' => $price,
    ];

    // $form['description_pagamentos'] = [
    //   '#type' => 'item',
    //   '#title' => $this->t('Payments'),
    //   '#markup' => $this->t('Amount to be paid:') . "<br>" . $price . " €",
    // ];

    $form['description_note'] = [
      '#type' => 'item',
      '#markup' => $config->get('message_form_add_reservation'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change reservation'),
      '#submit' => ['::subscribePageThreeBack'],
      '#validate' => ['::bwsMultistepFormNextValidate'],
      '#limit_validation_errors' => [],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#validate' => ['::bwsMultistepFormNextValidate'],
      '#submit' => ['::submitFormParcial'],
      '#value' => $this->t('Confirm'),
    ];

    return $form;
  }

  public function validateAluguerParcialPage(array &$form, FormStateInterface $form_state)
  {

    $values_storage = $form_state->getStorage()['page_values'];
    $values = $form_state->getValues();
    $node = $this->nodeStorage->load($values['node']);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $start_date = $values_storage['start_date'];
    $end_date = $values_storage['end_date'];

    $tipo_aluguer = $values['tipo_aluguer'];
    $camara_de_frio = $values['camara_de_frio'] ?? NULL;
    $unidades_medida = $values['unidades_medida'];

    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
    }

    $dates_valid = TRUE;

    if ($dates_valid) {
      if ($end_date <= $start_date) {
        $form_state->setErrorByName('end_date', $this->t('End date must be after the start date.'));
        return;
      } else {

        if (!empty($this->getAvailableUnits($values['node'], $values_storage['start_date'], $values_storage['end_date'], "area_total"))) {

          if ($tipo_aluguer == "camara_frio") {
            $available_units = $this->getAvailableUnitsCamaraFrio($values['node'], $values_storage['start_date'], $values_storage['end_date'], $camara_de_frio);
          } else {
            $available_units = $this->getAvailableSharedAreaUnit($values['node'], $values_storage['start_date'], $values_storage['end_date'], $form_state);
          }
        }
        /*if (empty($available_units)) {
          $form_state->setError($form, $this->t('There is not enough space in the warehouse for the quantity indicated.'));
        }*/
      }
    }
  }

  public function submitFormParcial(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getStorage()['page_values'];
    $values_values = $form_state->getValues();

    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $node = $this->nodeStorage->load($values['node']);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
    }

    if ($bws_settings['bookable_type'] == 'daily') {

      $booked_state = bat_event_load_state_by_machine_name('bws_daily_pending');

      $event = bat_event_create(['type' => "availability_daily"]);

      $event_dates = [
        'value' => $start_date->format('Y-m-d\TH:i:00'),
        'end_value' => $end_date->format('Y-m-d\TH:i:00'),
      ];
      $event->set('event_dates', $event_dates);
      $event->set('event_state_reference', $booked_state->id());

      if ($values['tipo_aluguer'] == "aluguer_espaço" || $values['tipo_aluguer'] == null) {
        $available_units = $this->getAvailableSharedAreaUnit($values['node'], $values['start_date'], $values['end_date'], $form_state);
      } else {
        $available_units = $this->getAvailableUnitsCamaraFrio($values['node'], $values['start_date'], $values['end_date'], $values['camara_de_frio']);
      }

      $event_bat_unit_reference = NULL;

      if (!empty($available_units) && is_array($available_units)) {
        $first = reset($available_units);
        if (is_int($first) || is_string($first)) {
          $event_bat_unit_reference = $first;
        }
      }

      if ($event_bat_unit_reference !== NULL) {
        $event->set('event_bat_unit_reference', $event_bat_unit_reference);
      }
    }

    $available_units = (array) $available_units;
    $event_bat_unit_reference = NULL;

    if (!empty($available_units)) {
      $first = reset($available_units);
      if (is_int($first) || is_string($first)) {
        $event_bat_unit_reference = $first;
      }
    }

    if ($event_bat_unit_reference !== NULL) {
      $event->set('event_bat_unit_reference', $event_bat_unit_reference);
    }
    $event->set('field_incluir_in', $values['incluir_in']);
    $event->set('field_incluir_out', $values['incluir_out']);
    $event->set('field_largura', $values['largura']);
    $event->set('field_comprimento', $values['comprimento']);
    $event->set('field_paletes_altura', $values['paletes_altura']);
    $event->set('field_quantidade', $values['quantidade']);
    $event->set('field_unidades_medida', $values['unidades_medida']);
    $event->set('field_armazem', $values['node']);
    $event->set('field_price', $values_values['price']);
    $event->set('field_type', 1);
    $event->set('field_user', \Drupal::currentUser()->id());


    if (isset($values['event_series'])) {
      $event->set('event_series', $values['event_series']);
    }

    $event->save();

    $this->messenger()->addMessage($this->t('Reservation created!'));

    $message = "Foi efetuada uma nova reserva.";
    $userStorage = \Drupal::entityTypeManager()->getStorage('user');
    $query = $userStorage->getQuery();
    $uids = $query
      ->condition('status', '1')
      ->condition('roles', 'gestor')
      ->accessCheck(FALSE)
      ->execute();

    $uids = (array) $uids;
    $uids = array_filter($uids, function ($id) {
      return is_int($id) || is_string($id);
    });

    $users = $userStorage->loadMultiple($uids);

    foreach ($users as $user) {
      $this->send_email($user->getEmail(), "Foi efetuada uma nova reserva", $message);
    }

    if (isset($values['event_series'])) {
      $form_state->setRedirect('entity.bat_event_series.canonical', ['bat_event_series' => $values['event_series']]);
    } else {
      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
    }
  }

  // general functions

  public function subscribePageTwoBack(array &$form, FormStateInterface $form_state)
  {
    $form_state
      // Restore values for the first step.
      ->setValues($form_state->get('page_values'))
      ->set('page_num', 1)
      // Since we have logic in our buildForm() method, we have to tell the form
      // builder to rebuild the form. Otherwise, even though we set 'page_num'
      // to 1, the AJAX-rendered form will still show page 2.
      ->setRebuild(TRUE);
  }

  public function subscribePageThreeBack(array &$form, FormStateInterface $form_state)
  {
    $form_state
      // Restore values for the first step.
      ->setValues($form_state->get('page_values'))
      ->set('page_num', 2)
      // Since we have logic in our buildForm() method, we have to tell the form
      // builder to rebuild the form. Otherwise, even though we set 'page_num'
      // to 1, the AJAX-rendered form will still show page 2.
      ->setRebuild(TRUE);
  }

  public function bwsMultistepFormNextValidate(array &$form, FormStateInterface $form_state) {}

  public function contactValidate(array &$form, FormStateInterface $form_state) {}

  public function contactSubmit(array &$form, FormStateInterface $form_state)
  {

    $values = $form_state->getValues();

    $start_date = $values['start_date'];
    $end_date = $values['end_date'];
    $nid = $values['node'];

    $start_date = new \DateTime($start_date);
    $end_date = new \DateTime($end_date);

    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $node = \Drupal\node\Entity\Node::load($nid);

    $address = $node->get('field_localidade')->getValue()[0];
    $title = $node->getTitle();
    $config = \Drupal::config('bws.settings');
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString();
    $owner_armazem = \Drupal\user\Entity\User::load($node->getOwnerId());


    $message = '<div>
      <p>Um utilizador deseja ser contactado relativo a uma reserva para as datas ' . $start_date->format('d-m-Y') . ' até ' . $end_date->format('d-m-Y') . '</p>
    </div>
    <div>

      <h2>Informação do utilizador que deseja ser contactado</h2>

        <div>
        <p><b>Nome</b></p>
        <p> 
          ' . $user->get('field_nome')->getString() . '
        </p>
      </div>

      <div>
        <p><b>Email</b></p>
        <p> 
          ' . $user->get('mail')->getString() . '
        </p>
      </div>

      <div>
        <p><b>Telefone</b></p>
        <p> 
          ' . $user->get('field_telefone')->getString() . '
        </p>
      </div>

      <div>
        <p><b>Telemovel</b></p>
        <p> 
          ' . $user->get('field_telemovel')->getString() . '
        </p>
      </div>
    </div>
    <div>
      <h1>Informação do armazem </h1>

      <div>
        <p><b>Nome</b></p>
        <p>
        ' . $title . '
        </p>
      </div>

      <div>
        <p><b>Localização</b></p>
        <p>
        ' . $address['address_line1'] . "<br>" . $address['postal_code'] . " " . $address['locality'] . '
        </p>
      </div>

      <div>
        <p><b>Link armazem</b></p>
        <p> <a href="' . $url . '">Ver armazem</a>
        </p>
      </div>

      <h2>Informação do proprietario do armazem</h2>

      <div>
        <p><b>Nome</b></p>
        <p> 
          ' . $owner_armazem->get('field_nome')->getString() . '
        </p>
      </div>

      <div>
        <p><b>Email</b></p>
        <p> 
          ' . $owner_armazem->get('mail')->getString() . '
        </p>
      </div>

      <div>
        <p><b>Telefone</b></p>
        <p> 
          ' . $owner_armazem->get('field_telefone')->getString() . '
        </p>
      </div>

      <div>
        <p><b>Telemovel</b></p>
        <p> 
          ' . $owner_armazem->get('field_telemovel')->getString() . '
        </p>
      </div>
    </div>';

    $userStorage = \Drupal::entityTypeManager()->getStorage('user');
    $query = $userStorage->getQuery();
    $uids = $query
      ->condition('status', '1')
      ->condition('roles', 'gestor')
      ->accessCheck(FALSE)
      ->execute();

    $uids = (array) $uids;
    $uids = array_filter($uids, function ($id) {
      return is_int($id) || is_string($id);
    });

    $users = $userStorage->loadMultiple($uids);

    foreach ($users as $user) {
      $this->send_email($user->getEmail(), "Um utilizador deseja ser contactado.", $message);
    }

    $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
  }

  protected function calculatePrices($nid, $start_date, $end_date, FormStateInterface $form_state, $field)
  {

    $node = $this->nodeStorage->load($nid);

    $prices = $node->get($field)->getValue();

    foreach ($prices as $price) {

      $p = \Drupal\paragraphs\Entity\Paragraph::load($price['target_id']);

      $field_preco_dia = $p->get('field_preco_dia')->getString();
      $field_preco_mes = $p->get('field_preco_mes')->getString();
      $field_preco_ano = $p->get('field_preco_ano')->getString();
    }

    $start_date = new \DateTime($start_date);
    $end_date = new \DateTime($end_date);

    $days = $end_date->diff($start_date)->days;

    if ($days > 0) {
      if ($days < 30) {
        $preco = floatval($field_preco_dia) * $days;
      } elseif ($days >= 30 && $days <= 366) {
        $preco = floatval($field_preco_mes) * $days;
      } else {
        $preco = floatval($field_preco_ano) * $days;
      }
    }

    return $preco;
  }

  protected function getAvailableUnits($nid, $start_date, $end_date, $type)
  {

    $node = $this->nodeStorage->load($nid);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bws_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        if ($unit->entity->type->getString() == $type) {
          $units_ids[] = $unit->entity->id();
        }
      }
    }

    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
      $end_date->sub(new \DateInterval('PT1M'));

      $available_units_ids = bat_event_get_matching_units($start_date, $end_date, ['bws_daily_available'], [$bws_settings['type_id']], 'availability_daily');
    }
    return array_intersect($units_ids, $available_units_ids);
  }

  protected function getAvailableSharedAreaUnit($nid, $start_date, $end_date, $form_state)
  {

    $node = $this->nodeStorage->load($nid);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $values_storage = $form_state->getStorage()['page_values'];
    $values = $form_state->getValues();

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bws_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        if ($unit->entity->type->getString() == 'area_partilhada') {

          $comprimento = $unit->entity->get('field_comprimento')->getString();
          $largura = $unit->entity->get('field_largura')->getString();
          $area_total = $unit->entity->get('field_area_total')->getString();

          $start_date = new \DateTime($start_date);
          $end_date = new \DateTime($end_date);
          $end_date->modify('+1 day');

          $interval = \DateInterval::createFromDateString('1 day');
          $period = new \DatePeriod($start_date, $interval, $end_date);

          foreach ($period as $dt) {

            $bat_event = \Drupal::entityTypeManager()->getStorage('bat_event');

            $ids = $bat_event->getQuery()
              ->condition('event_bat_unit_reference', $unit->entity->id())
              ->condition('event_dates.value', $dt->format("Y-m-d H:i:s"), '<=')
              ->condition('event_dates.end_value', $dt->format("Y-m-d H:i:s"), '>=')
              ->accessCheck(FALSE)
              ->execute();

            $ids = (array) $ids;
            $ids = array_filter($ids, function ($id) {
              return is_int($id) || is_string($id);
            });

            $events = $bat_event->loadMultiple($ids);

            $area_ocupada = 0;

            foreach ($events as $event) {

              $e_comprimento = $event->get('field_comprimento')->getString();
              $e_largura = $event->get('field_largura')->getString();
              $e_unidades_medida = $event->get('field_unidades_medida')->getString();
              $e_paletes_altura = $event->get('field_paletes_altura')->getString();
              $e_quantidade = $event->get('field_quantidade')->getString();


              if ($e_unidades_medida == 'area_partilhada') {

                $area_ocupada += floatval($e_comprimento) * floatval($e_largura);
              } elseif ($e_unidades_medida == 'euro_paletes') {

                $euro_palete_c = 1.2;
                $euro_palete_l = 0.8;

                $europaletes_base = ceil(floatval($e_quantidade) / floatval($e_paletes_altura));

                $area_ocupada += $europaletes_base * ($euro_palete_c * $euro_palete_l);
              } elseif ($e_unidades_medida == 'paletes_americanas') {
                $paletes_americanas_c = 1.2;
                $paletes_americanas_l = 1;

                $paletes_americanas_base = ceil(floatval($e_quantidade) / floatval($e_paletes_altura));

                $area_ocupada += $paletes_americanas_base * ($paletes_americanas_c * $paletes_americanas_l);
              }
            }

            if ($values['unidades_medida'] == 'area_partilhada') {

              $area_ocupada += floatval($values['comprimento']) * floatval($values['largura']);
            } elseif ($values['unidades_medida'] == 'euro_paletes') {

              $euro_palete_c = 1.2;
              $euro_palete_l = 0.8;

              $europaletes_base = ceil(floatval($values['quantidade']) / floatval($values['paletes_altura']));

              $area_ocupada += $europaletes_base * ($euro_palete_c * $euro_palete_l);
            } elseif ($values['unidades_medida'] == 'paletes_americanas') {
              $paletes_americanas_c = 1.2;
              $paletes_americanas_l = 1;

              $paletes_americanas_base = ceil(floatval($values['quantidade']) / floatval($values['paletes_altura']));

              $area_ocupada += $paletes_americanas_base * ($paletes_americanas_c * $paletes_americanas_l);
            }



            if ($area_ocupada > floatval($area_total)) {
              return $units_ids;
            }
          }

          $units_ids[] = $unit->entity->id();
        }
      }
    }
    return $units_ids;
  }

  public function getCamarasFrioUnits($nid, $start_date, $end_date, $type)
  {

    $available_units = $this->getAvailableUnits($nid, $start_date, $end_date, $type);

    $options = [];

    foreach ($available_units as $unit) {
      $entity = \Drupal::entityTypeManager()->getStorage('bat_unit')->load($unit);
      $options[$unit] = $entity->get("name")->getString();
    }

    return $options;
  }

  protected function getAvailableUnitsCamaraFrio($nid, $start_date, $end_date, $unit_id)
  {

    $node = $this->nodeStorage->load($nid);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bws_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        if ($unit->entity->id() == $unit_id) {
          $units_ids[] = $unit->entity->id();
        }
      }
    }

    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
      $end_date->sub(new \DateInterval('PT1M'));

      $available_units_ids = bat_event_get_matching_units($start_date, $end_date, ['bws_daily_available'], [$bws_settings['type_id']], 'availability_daily');
    }


    return array_intersect($units_ids, $available_units_ids);
  }

  function send_email($user_email, $subject, $message)
  {

    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'bws';
    $key = 'general_mail';
    $to = $user_email;

    $params['headers'] = [];
    $params['subject'] = $subject;
    $params['body'] = $message;
    $params['plain'] = "";
    $params['plaintext'] = "";
    $params['attachments'] = [];

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    if ($result['result'] !== true) {
      \Drupal::messenger()->addError(t('There was a problem sending your message and it was not sent.'), 'error');
    }
  }
}
