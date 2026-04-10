<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário para submeter uma review de garagem.
 */
class GaragemReviewForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function getFormId() {
    return 'garagem_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    if (!$node || $node->getType() !== 'armazem') {
      $form['erro'] = ['#markup' => '<p>' . $this->t('Garagem inválida.') . '</p>'];
      return $form;
    }

    $uid = $this->currentUser()->id();
    $nid = $node->id();
    $now = \Drupal::time()->getRequestTime();

    $reserva = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr', ['id', 'data_inicio', 'data_fim', 'renovacao_automatica'])
      ->condition('garagem_id', $nid)
      ->condition('user_id', $uid)
      ->condition('estado', 'pago')
      ->condition('data_inicio', $now, '<=')
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    if (!$reserva) {
      $form['erro'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-sm text-yellow-700">{{ msg }}</div>',
        '#context' => ['msg' => $this->t('Só pode avaliar uma garagem depois de ter iniciado uma estadia.')],
      ];
      return $form;
    }

    $ja_avaliou = $this->database->select('garagem_review', 'r')
      ->fields('r', ['id'])
      ->condition('reserva_id', $reserva->id)
      ->execute()
      ->fetchField();

    if ($ja_avaliou) {
      $form['erro'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="bg-gray-50 border border-gray-200 rounded-xl p-5 text-sm text-gray-600">{{ msg }}</div>',
        '#context' => ['msg' => $this->t('Já avaliou esta garagem.')],
      ];
      return $form;
    }

    $form_state->set('node', $node);
    $form_state->set('reserva_id', $reserva->id);

    // Foto da garagem via Media entity.
    $foto_url = NULL;
    if ($node->hasField('field_fotos') && !$node->get('field_fotos')->isEmpty()) {
      $media = $node->get('field_fotos')->first()->entity;
      if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
        $file = $media->get('field_media_image')->entity;
        if ($file) {
          $foto_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    // Localidade.
    $localidade = '';
    if (!$node->get('field_localidade')->isEmpty()) {
      $addr = $node->get('field_localidade')->first();
      $localidade = trim(($addr->locality ?? '') . ', ' . ($addr->administrative_area ?? ''), ', ');
    }

    // Datas da reserva.
    $data_inicio = date('d/m/Y', $reserva->data_inicio);
    $data_fim = $reserva->renovacao_automatica
      ? $this->t('Renovação automática')
      : date('d/m/Y', $reserva->data_fim);

    // Passar dados ao template via propriedades do form.
    $form['#theme']            = 'garagem_review_form';
    $form['#garagem_foto']     = $foto_url;
    $form['#garagem_titulo']   = $node->getTitle();
    $form['#garagem_localidade'] = $localidade;
    $form['#data_inicio']      = $data_inicio;
    $form['#data_fim']         = (string) $data_fim;
    $form['#garagem_url']      = Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString();

    // Rating — campo hidden preenchido por JS.
    $form['rating'] = [
      '#type'       => 'hidden',
      '#attributes' => ['id' => 'review-rating-value'],
    ];

    $star_svg = '<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    $labels = [
      1 => $this->t('Muito mau'),
      2 => $this->t('Mau'),
      3 => $this->t('Razoável'),
      4 => $this->t('Bom'),
      5 => $this->t('Excelente'),
    ];

    $form['rating_ui'] = [
      '#type'     => 'inline_template',
      '#template' => '
        <div class="mb-8">
          <div class="flex gap-3 mb-3" id="star-rating">
            {% for i in 1..5 %}
              <button type="button" data-value="{{ i }}" class="star-btn text-gray-200 hover:text-gray-900 transition-colors" title="{{ labels[i] }}">
                {{ star|raw }}
              </button>
            {% endfor %}
          </div>
          <p id="rating-label" class="text-sm font-medium text-gray-500 h-5"></p>
          <p id="rating-erro" class="text-xs text-red-500 mt-1 hidden">{{ erro }}</p>
        </div>
        <script>
        (function() {
          var labels = {{ labels_json|raw }};
          var stars  = document.querySelectorAll(".star-btn");
          var input  = document.getElementById("review-rating-value");
          var lbl    = document.getElementById("rating-label");
          stars.forEach(function(btn) {
            btn.addEventListener("mouseenter", function() {
              var val = parseInt(this.dataset.value);
              stars.forEach(function(s) {
                s.classList.toggle("text-gray-900", parseInt(s.dataset.value) <= val);
                s.classList.toggle("text-gray-200", parseInt(s.dataset.value) > val);
              });
              lbl.textContent = labels[val] || "";
            });
            btn.addEventListener("click", function() {
              var val = parseInt(this.dataset.value);
              input.value = val;
              document.getElementById("rating-erro").classList.add("hidden");
            });
          });
          document.getElementById("star-rating").addEventListener("mouseleave", function() {
            var current = parseInt(input.value) || 0;
            stars.forEach(function(s) {
              s.classList.toggle("text-gray-900", parseInt(s.dataset.value) <= current);
              s.classList.toggle("text-gray-200", parseInt(s.dataset.value) > current);
            });
            lbl.textContent = current ? labels[current] : "";
          });
        })();
        </script>',
      '#context' => [
        'erro'        => $this->t('Por favor selecione uma classificação.'),
        'star'        => $star_svg,
        'labels'      => $labels,
        'labels_json' => json_encode($labels),
      ],
    ];

    $form['texto'] = [
      '#type'        => 'textarea',
      '#title'       => $this->t('Comentário'),
      '#rows'        => 5,
      '#placeholder' => $this->t('O que gostou? O que poderia ser melhorado?'),
      '#attributes'  => [
        'class' => ['w-full text-sm border border-gray-200 rounded-xl px-4 py-3 resize-none focus:outline-none focus:ring-1 focus:ring-gray-400'],
      ],
    ];

    $form['actions'] = [
      '#type'   => 'actions',
      '#prefix' => '<div class="flex items-center gap-4 mt-8">',
      '#suffix' => '</div>',
      'submit'  => [
        '#type'       => 'submit',
        '#value'      => $this->t('Publicar avaliação'),
        '#attributes' => [
          'class' => ['px-6 py-3 text-sm font-bold text-white bg-gray-900 rounded-full hover:bg-gray-700 transition cursor-pointer'],
        ],
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $rating = (int) $form_state->getValue('rating');
    if ($rating < 1 || $rating > 5) {
      $form_state->setErrorByName('rating', $this->t('Por favor selecione uma classificação.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node      = $form_state->get('node');
    $reserva_id = $form_state->get('reserva_id');

    $this->database->insert('garagem_review')
      ->fields([
        'garagem_id' => $node->id(),
        'user_id'    => $this->currentUser()->id(),
        'reserva_id' => $reserva_id,
        'rating'     => (int) $form_state->getValue('rating'),
        'texto'      => $form_state->getValue('texto'),
        'created'    => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    $this->messenger()->addMessage($this->t('Obrigado pela sua avaliação!'));
    $form_state->setRedirectUrl(Url::fromRoute('entity.node.canonical', ['node' => $node->id()]));
  }

}
