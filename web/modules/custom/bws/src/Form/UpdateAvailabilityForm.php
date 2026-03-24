<?php

namespace Drupal\bws\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update reservation form for a BEE node.
 */
class UpdateAvailabilityForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * Constructs a new UpdateAvailabilityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bws_update_availability_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

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

    $form['availability'] = [
      '#type' => 'details',
      '#title' => $this->t('Update availability'),
    ];

    $form['availability']['state'] = [
      '#type' => 'select',
      '#title' => $this->t('State'),
      '#options' => [
        'available' => $this->t('Available'),
        'unavailable' => $this->t('Unavailable'),
      ],
      '#prefix' => '<div class="form-row">',
    ];

    $form['availability']['start_date'] = [
      '#type' => ($bws_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => $this->t('Start'),
      '#date_increment' => 60,
      '#default_value' => ($bws_settings['bookable_type'] == 'daily') ? $today->format('Y-m-d') : new DrupalDateTime($today->format('Y-m-d H:00')),
      '#required' => TRUE,
    ];

    $form['availability']['end_date'] = [
      '#type' => ($bws_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => $this->t('End'),
      '#default_value' => ($bws_settings['bookable_type'] == 'daily') ? $tomorrow->format('Y-m-d') : new DrupalDateTime($one_hour_later->format('Y-m-d H:00')),
      '#date_increment' => 60,
      '#required' => TRUE,
      '#suffix' => '</div>',
    ];

    $form['availability']['repeat'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This event repeats'),
      '#prefix' => '<div class="form-row">',
    ];

    $form['availability']['repeat_frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Repeat frequency'),
      '#options' => [
        'daily' => $this->t('Daily'),
        'weekly' => $this->t('Weekly'),
        'monthly' => $this->t('Monthly'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="repeat"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['availability']['repeat_until'] = [
      '#type' => 'date',
      '#title' => $this->t('Repeat until'),
      '#states' => [
        'visible' => [
          ':input[name="repeat"]' => ['checked' => TRUE],
        ],
      ],
      '#suffix' => '</div>',
    ];

    $form['availability']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Availability'),
    ];

    $form['#attached']['library'][] = 'bws/bws_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

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

    // The end date must be greater or equal than start date.
    if ($end_date < $start_date) {
      $form_state->setErrorByName('end_date', $this->t('End date must be on or after the start date.'));
    }

    if ($values['repeat']) {
      if (empty($values['repeat_until'])) {
        $form_state->setErrorByName('repeat_until', $this->t('Repeat until is required if "This event repeats" is checked.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $node = $this->nodeStorage->load($values['node']);
    $node_type = $this->nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    if ($bws_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);

      if ($values['repeat']) {
        $repeat_interval = $start_date->diff($end_date);

        $interval = new \DateInterval('P1D');
        if ($values['repeat_frequency'] == 'weekly') {
          $interval = new \DateInterval('P1W');
        }
        elseif ($values['repeat_frequency'] == 'monthly') {
          $interval = new \DateInterval('P1M');
        }

        $repeat_until_date = new \DateTime($values['repeat_until']);

        $repeat_period = new \DatePeriod($start_date, $interval, $repeat_until_date);

        foreach ($repeat_period as $date) {
          $temp_end_date = clone($date);
          $temp_end_date->add($repeat_interval);

          $this->createDailyEvent($node, $date, $temp_end_date, $bws_settings['type_id'], $values['state']);
        }
      }
      else {
        $this->createDailyEvent($node, $start_date, $end_date, $bws_settings['type_id'], $values['state']);
      }
    }
  }

  /**
   * Create daily event.
   *
   * @param \Drupal\node\Entity\Node $node
   *   A node object.
   * @param \DateTime $start_date
   *   A DateTime object.
   * @param \DateTime $end_date
   *   A DateTime object.
   * @param int $type_id
   *   An integer.
   * @param string $new_state
   *   A string.
   */
  private function createDailyEvent(Node $node, \DateTime $start_date, \DateTime $end_date, $type_id, $new_state) {
    $temp_end_date = clone($end_date);
    $temp_end_date->sub(new \DateInterval('PT1M'));

    $booked_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bws_daily_booked'], [$type_id], 'availability_daily');

    $available_units = bat_event_get_matching_units(
      $start_date,
      $temp_end_date,
      ['bws_daily_available', 'bws_daily_not_available'],
      [$type_id],
      'availability_daily'
    );

    $units_ids = [];
    foreach ($node->get('field_availability_daily') as $unit) {
      if ($unit->entity) {
        $units_ids[] = $unit->entity->id();
      }
    }

    if ($available_units) {
      if ($new_state == 'available') {
        $state = bat_event_load_state_by_machine_name('bws_daily_available');
      }
      else {
        $state = bat_event_load_state_by_machine_name('bws_daily_not_available');
      }

      foreach ($available_units as $unit) {
        $event = bat_event_create(['type' => 'availability_daily']);
        $event_dates = [
          'value' => $start_date->format('Y-m-d\TH:i:00'),
          'end_value' => $end_date->format('Y-m-d\TH:i:00'),
        ];
        $event->set('event_dates', $event_dates);
        $event->set('event_state_reference', $state->id());
        $event->set('event_bat_unit_reference', $unit);
        $event->save();
      }
    }

    foreach ($booked_units as $unit) {
      $bat_unit = bat_unit_load($unit);
      $this->messenger()->addWarning($this->t('Could not create event from @start to @end for unit @label with ID: @unit', [
        '@unit' => $unit,
        '@label' => $bat_unit->label(),
        '@start' => $start_date->format('Y-m-d'),
        '@end' => $end_date->format('Y-m-d'),
      ]));
    }
  }
}