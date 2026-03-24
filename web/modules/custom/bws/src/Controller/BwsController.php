<?php

namespace Drupal\bws\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\office_hours\OfficeHoursDateHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * A BEE Controller.
 */
class BwsController extends ControllerBase implements ContainerInjectionInterface {

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
   * Constructs a new BeeController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
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
   * Availability calendar page.
   */
  public function availability(NodeInterface $node) {

    $nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
    $node_type = $nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $unit_type = $bws_settings['type_id'];

    $bat_unit_ids = [];
    foreach ($node->get('field_availability_' . $bws_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        $bat_unit_ids[] = $unit->entity->id();
      }
    }

    if ($bws_settings['bookable_type'] == 'daily') {
      $event_type = 'availability_daily';
      $event_granularity = 'bat_daily';

      $fc_user_settings = [
        'batCalendar' => [
          [
            'unitType' => $unit_type,
            'unitIds' => implode(',', $bat_unit_ids),
            'eventType' => $event_type,
            'eventGranularity' => $event_granularity,
            'viewsTimelineThirtyDaySlotDuration' => ['days' => 1],
          ],
        ],
      ];
    }

    $calendar_settings['user_settings'] = $fc_user_settings;
    $calendar_settings['calendar_id'] = 'fullcalendar-scheduler';

    $render_array = [
      'calendar' => [
        '#theme' => 'bat_fullcalendar',
        '#calendar_settings' => $calendar_settings,
        '#attached' => [
          'library' => [
            'bat_event_ui/bat_event_ui',
            'bat_fullcalendar/bat-fullcalendar-scheduler',
          ],
        ],
      ],
    ];

    return [
      'form' => $this->formBuilder()->getForm('Drupal\bws\Form\UpdateAvailabilityForm', $node),
      'calendar' => $render_array,
    ];
  }

  /**
   * The _title_callback for the page that renders the availability.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   A BEE node.
   *
   * @return string
   *   The page title.
   */
  public function availabilityTitle(EntityInterface $node) {
    return $this->t('Availability for %label', ['%label' => $node->label()]);
  }

  /**
   * The _title_callback for the page that renders the add reservation form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   A BEE node.
   *
   * @return string
   *   The page title.
   */
  public function addReservationTitle(EntityInterface $node) {
    return $this->t('%label', ['%label' => $node->label()]);
  }

  
  /**
   * The _title_callback for the page that renders the add reservation form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   A BEE node.
   *
   * @return string
   *   The page title.
   */
  public function acceptReservation(EntityInterface $node, EntityInterface $bat_event) {

    if($bat_event) {

      $booked_state = bat_event_load_state_by_machine_name('bws_daily_booked');
      $bat_event->set('event_state_reference', $booked_state->id());
      
      $bat_event->save();

      $type = $bat_event->get('field_type')->getString();
      $unidades_medida = $bat_event->get('field_unidades_medida')->getString();
      $comprimento = $bat_event->get('field_comprimento')->getString();
      $quantidade = $bat_event->get('field_quantidade')->getString();
      $incluir_in = $bat_event->get('field_incluir_in')->getString();
      $largura = $bat_event->get('field_largura')->getString();
      $paletes_altura = $bat_event->get('field_paletes_altura')->getString();
      $incluir_out = $bat_event->get('field_incluir_out')->getString();
      $start_date = $bat_event->get('event_dates')->getValue()[0]['value'];
      $end_date = $bat_event->get('event_dates')->getValue()[0]['end_value'];
      $bat_unit = $bat_event->get('event_bat_unit_reference');
      $price = $bat_event->get('field_price')->getString();

      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
      
      if($type == '0') {

        $tipo_aluguer = "Aluguer total";
        $espaco_alugado = "Total";
        
      } else {
        $tipo_aluguer = "Aluguer parcial";

        if($bat_unit->entity->type->getString() == 'camara_de_frio') {

          $espaco_alugado = $bat_unit->entity->get('name')->getString();

        } else {

          if($unidades_medida == "area_partilhada") {

            $espaco_alugado = $comprimento . " m de comprimento <br>" . $largura . " m de largura";

            
          } elseif($unidades_medida == "euro_paletes") { 

            if($incluir_in == "1") {
              $string_in = "Incluir IN";
            }

            if($incluir_out == "1") {
              $string_out = "Incluir OUT";
            }
            
            if($paletes_altura == null) {
              $paletes_altura = 0;
            }

            $espaco_alugado = $quantidade . " europaletes com " . $paletes_altura . " paletes em altura <br> " . $string_in . "<br>" . $string_out;

          } elseif($unidades_medida == "paletes_americanas") { 

            if($incluir_in == "1") {
              $string_in = "Incluir IN";
            }

            if($incluir_out == "1") {
              $string_out = "Incluir OUT";
            }
            
            if($paletes_altura == null) {
              $paletes_altura = 0;
            }

            $espaco_alugado = $quantidade . " paletes americanas com " . $paletes_altura . " paletes em altura <br> " . $string_in . "<br>" . $string_out;
          }  
        }
      }

      $address = $node->get('field_localidade')->getValue()[0];
      $title = $node->getTitle();
      $config = \Drupal::config('bws.settings');
      $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString();

      $owner_armazem = \Drupal\user\Entity\User::load($node->getOwnerId());
      $owner_event = $bat_event->get('field_user')->entity;
      
      $message = '
        <div>
          <h1>Informação sobre a reserva</h1>
          
          <div>
            <p><b>Datas</b></p>
            <p>
              '.$start_date->format('d-m-Y').' até '.$end_date->format('d-m-Y').'
            </p>
          </div>

          <div>
            <p><b>Tipo de aluguer</b></p>
            <p>
            '.$tipo_aluguer.'
            </p>
          </div>

          <div>
            <p><b>Espaço Alugado</b></p>
            <p>
            '.$espaco_alugado.'
            </p>
          </div>

          <div>
            <p><b>Pagamentos</b></p>
            <p>
            Valor a pagar: <br>' . $price . ' € <br> ' . $config->get('fee') . ' € de fee inicial
            </p>
          </div>

          <h2>Informação do utilizador da reserva</h2>

           <div>
            <p><b>Nome</b></p>
            <p> 
              '.$owner_event->get('field_nome')->getString().'
            </p>
          </div>

          <div>
            <p><b>Email</b></p>
            <p> 
              '.$owner_event->get('mail')->getString().'
            </p>
          </div>

          <div>
            <p><b>Telefone</b></p>
            <p> 
              '.$owner_event->get('field_telefone')->getString().'
            </p>
          </div>

          <div>
            <p><b>Telemovel</b></p>
            <p> 
              '.$owner_event->get('field_telemovel')->getString().'
            </p>
          </div>
        </div>

        <div>
          <h1>Informação do armazem </h1>

          <div>
            <p><b>Nome</b></p>
            <p>
            '.$title.'
            </p>
          </div>

          <div>
            <p><b>Localização</b></p>
            <p>
            '.$address['address_line1'] . "<br>" . $address['postal_code'] . " " . $address['locality'].'
            </p>
          </div>

          <div>
            <p><b>Link armazem</b></p>
            <p> <a href="'.$url.'">Ver armazem</a>
            </p>
          </div>

          <h2>Informação do proprietario do armazem</h2>

          <div>
            <p><b>Nome</b></p>
            <p> 
              '.$owner_armazem->get('field_nome')->getString().'
            </p>
          </div>

          <div>
            <p><b>Email</b></p>
            <p> 
              '.$owner_armazem->get('mail')->getString().'
            </p>
          </div>

          <div>
            <p><b>Telefone</b></p>
            <p> 
              '.$owner_armazem->get('field_telefone')->getString().'
            </p>
          </div>

          <div>
            <p><b>Telemovel</b></p>
            <p> 
              '.$owner_armazem->get('field_telemovel')->getString().'
            </p>
          </div>
        </div>
      ';

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

      foreach($users as $user) {
        $this->send_email($user->getEmail(), "A reserva foi aprovada.", $message);
      }
    }

    return $this->redirect('view.events.page_events_gestor');
  }


  function send_email($user_email, $subject, $message){

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

  // http://armazens.lndo.site/pt/node/50/26/accept-reservation

}
