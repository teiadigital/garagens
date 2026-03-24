<?php

namespace Drupal\bws\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use RRule\RRule;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Interact with Orders.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity manager.
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
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_manager;
    $this->configFactory = $config_factory;
    $this->nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = ['commerce_order.place.pre_transition' => 'finalizeCart'];
    return $events;
  }

  /**
   * Finalizes the cart when the order is placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function finalizeCart(WorkflowTransitionEvent $event) {

    $order = $event->getEntity();

    foreach ($order->getItems() as $order_item) {
      if ($order_item->bundle() == 'bws') {
        if ($booking = $order_item->get('field_booking')->entity) {

          $booking_event_reference = $booking->get("booking_event_reference")->target_id;

          if (!isset($booking_event_reference)) {

            $node = $order_item->get('field_node')->entity;

            $start_date = new \DateTime($booking->get('booking_start_date')->value);
            $end_date = new \DateTime($booking->get('booking_end_date')->value);

            $node_type = $this->nodetypeStorage->load($node->bundle());

            assert($node_type instanceof NodeType);
            $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

            if ($bws_settings['bookable_type'] == 'daily') {
              $booked_state = bat_event_load_state_by_machine_name('bws_daily_booked');
              $event_type = 'availability_daily';
            }

            $events = [];

            $repeat_frequency = $booking->get('booking_repeat_frequency')->value;
            $repeat_until = $booking->get('booking_repeat_until')->value;

            if ($repeat_frequency && $repeat_until) {
              $repeat_until_object = new \DateTime($repeat_until);

              $label = $this->getEventSeriesLabel($node, $bws_settings['bookable_type'], $start_date, $end_date, $repeat_frequency, $repeat_until_object);

              $rrule = new RRule([
                'FREQ' => strtoupper($repeat_frequency),
                'UNTIL' => $repeat_until . 'T235959Z',
              ]);

              $event_series = bat_event_series_create([
                'type' => $event_type,
                'label' => $label,
                'rrule' => $rrule->rfcString(),
              ]);
              $event_dates = [
                'value' => $start_date->format('Y-m-d\TH:i:00'),
                'end_value' => $end_date->format('Y-m-d\TH:i:00'),
              ];
              $event_series->set('event_dates', $event_dates);
              $event_series->set('event_state_reference', $booked_state->id());

              $available_units = $this->getAvailableUnits($node, $start_date, $end_date);

              $event_series->set('event_bat_unit_reference', reset($available_units));

              $event_series->save();

              $booking->set('booking_event_series_reference', $event_series->id());

              $query = $this->entityTypeManager->getStorage('bat_event')->getQuery()
                ->condition('event_series.target_id', $event_series->id());
              $events_created = $query->execute();

              foreach ($events_created as $event_id) {
                $event = bat_event_load($event_id);
                $events[] = $event;
              }
            }
            else {
              $capacity = ($booking->get('booking_capacity')->value) ? ($booking->get('booking_capacity')->value) : 1;

              for ($i = 0; $i < $capacity; $i++) {
                $event = bat_event_create(['type' => $event_type]);
                $event_dates = [
                  'value' => $start_date->format('Y-m-d\TH:i:00'),
                  'end_value' => $end_date->format('Y-m-d\TH:i:00'),
                ];
                $event->set('event_dates', $event_dates);
                $event->set('event_state_reference', $booked_state->id());

                if ($event_series = $booking->get('booking_event_series_reference')->target_id) {
                  $event->set('event_series', $event_series);
                }

                $available_units = $this->getAvailableUnits($node, $start_date, $end_date);

                $event->set('event_bat_unit_reference', reset($available_units));
                $event->save();

                $events[] = $event;
              }
            }
            $booking->set('booking_event_reference', $events);
            $booking->save();
          }
        }
      }
    }
  }

  /**
   * Get available Units.
   *
   * @param object $node
   *   A Node object.
   * @param object $start_date
   *   A DateTime object.
   * @param object $end_date
   *   A DateTime object.
   *
   * @return array
   *   An array.
   */
  protected function getAvailableUnits($node, $start_date, $end_date) {

    $node_type = $this->nodetypeStorage->load($node->bundle());

    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bws_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        $units_ids[] = $unit->entity->id();
      }
    }

    $temp_end_date = clone($end_date);
    $temp_end_date->sub(new \DateInterval('PT1M'));

    if ($bws_settings['bookable_type'] == 'daily') {
      $available_units_ids = bat_event_get_matching_units($start_date, $temp_end_date, ['bws_daily_available'], [$bws_settings['type_id']], 'availability_daily');
    }

    return array_intersect($units_ids, $available_units_ids);
  }

  /**
   * Get label for a event Serie.
   *
   * @param object $node
   *   A BEE node.
   * @param string $bookable_type
   *   A string.
   * @param object $start_date
   *   A DateTime object.
   * @param object $end_date
   *   A DateTime object.
   * @param string $repeat_frequency
   *   A string.
   * @param object $repeat_until
   *   A DateTime object.
   *
   * @return string
   *   A string.
   */
  protected function getEventSeriesLabel($node, $bookable_type, $start_date, $end_date, $repeat_frequency, $repeat_until) {
    $frequency = $this->t('Day');
    if ($repeat_frequency == 'weekly') {
      $frequency = $start_date->format('l');
    }
    elseif ($repeat_frequency == 'monthly') {
      $frequency = $this->t('@day of Month', ['@day' => $start_date->format('jS')]);
    }

    if ($bookable_type == 'daily') {
      $label = $this->t('Reservations for @node Every @frequency from @start_date -> @end_date', [
        '@node' => $node->label(),
        '@frequency' => $frequency,
        '@start_date' => $start_date->format('M j Y'),
        
        '@end_date' => $repeat_until->format('M j Y'),
      ]);
    }

    return $label;
  }

}
