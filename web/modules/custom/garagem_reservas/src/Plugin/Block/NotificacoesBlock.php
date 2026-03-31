<?php

namespace Drupal\garagem_reservas\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bloco de notificações com sino e dropdown.
 *
 * @Block(
 *   id = "garagem_notificacoes_block",
 *   admin_label = @Translation("Notificações de Reservas"),
 *   category = @Translation("Garagem Reservas")
 * )
 */
class NotificacoesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $currentUser;
  protected $routeMatch;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('current_route_match')
    );
  }

  public function build() {
    if (!$this->currentUser->isAuthenticated()) {
      return [];
    }

    $uid = $this->currentUser->id();

    // Buscar timestamp da última leitura.
    $lidas_ate = \Drupal::service('user.data')->get(
      'garagem_reservas',
      $uid,
      'notificacoes_lidas_ate'
    ) ?? 0;

    // Buscar todas as mensagens do utilizador.
    $message_storage = \Drupal::entityTypeManager()->getStorage('message');
    $ids = $message_storage->getQuery()
      ->condition('uid', $uid)
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $messages = $message_storage->loadMultiple($ids);
    uasort($messages, fn($a, $b) => $b->getCreatedTime() - $a->getCreatedTime());

    // Contar não lidas.
    $total_nao_lidas = 0;
    foreach ($messages as $message) {
      if ($message->getCreatedTime() > $lidas_ate) {
        $total_nao_lidas++;
      }
    }

    // Mostrar só as 5 mais recentes no dropdown.
    $messages = array_slice($messages, 0, 5);

    $items = [];
    foreach ($messages as $message) {
      $template_id = $message->bundle();
      $template = \Drupal::entityTypeManager()->getStorage('message_template')->load($template_id);
      $label = $template ? $template->label() : $template_id;

      // Obter texto via getText() que usa os argumentos (@garagem etc).
      $texto = '';
      try {
        $texts = $message->getText();
        foreach ($texts as $t) {
          if (is_array($t)) {
            $texto .= \Drupal::service('renderer')->renderPlain($t);
          } else {
            $texto .= $t;
          }
        }
        $texto = trim(strip_tags((string) $texto));
      }
      catch (\Exception $e) {
        $texto = '';
      }

      $items[] = [
        'label' => $label,
        'texto' => $texto,
        'data' => \Drupal::service('date.formatter')->format($message->getCreatedTime(), 'custom', 'd/m/Y H:i'),
      ];
    }

    return [
      '#type' => 'inline_template',
      '#template' => '
        <div class="garagem-notificacoes-block" style="position:relative;">

          {# Sino #}
          <button type="button"
                  onclick="toggleNotificacoes()"
                  style="position:relative;background:none;border:none;cursor:pointer;padding:4px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            {% if total > 0 %}
              <span style="position:absolute;top:-4px;right:-4px;background:#6366f1;color:#fff;border-radius:9999px;font-size:10px;font-weight:700;width:18px;height:18px;display:flex;align-items:center;justify-content:center;line-height:1;">
                {{ total > 9 ? "9+" : total }}
              </span>
            {% endif %}
          </button>

          {# Dropdown #}
          <div id="notificacoes-dropdown"
               style="display:none;position:absolute;top:calc(100% + 12px);right:-12px;width:320px;background:#3b82f6;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.15);z-index:1000;overflow:hidden;">

            {% if items is empty %}
              <div style="padding:20px 16px;color:#fff;font-size:13px;">{{ "Sem notificações."|t }}</div>
            {% else %}
              {% for item in items %}
                <div style="padding:14px 16px;{% if not loop.last %}border-bottom:1px solid rgba(255,255,255,0.15);{% endif %}">
                  <p style="font-size:13px;font-weight:700;color:#fff;margin:0 0 4px;">{{ item.label }}</p>
                  <p style="font-size:12px;color:rgba(255,255,255,0.85);margin:0 0 4px;line-height:1.4;">{{ item.texto }}</p>
                  <p style="font-size:11px;color:rgba(255,255,255,0.6);margin:0;">{{ item.data }}</p>
                </div>
              {% endfor %}
              <div style="padding:12px 16px;border-top:1px solid rgba(255,255,255,0.15);text-align:center;">
                <a href="{{ url_todas }}" style="font-size:12px;font-weight:700;color:#fff;text-decoration:none;text-transform:uppercase;letter-spacing:0.05em;opacity:0.85;">
                  {{ "Ver todas"|t }}
                </a>
              </div>
            {% endif %}

          </div>

        </div>

        <script>
        function toggleNotificacoes() {
          var d = document.getElementById("notificacoes-dropdown");
          d.style.display = d.style.display === "none" ? "block" : "none";
        }
        document.addEventListener("click", function(e) {
          var block = document.querySelector(".garagem-notificacoes-block");
          if (block && !block.contains(e.target)) {
            var d = document.getElementById("notificacoes-dropdown");
            if (d) d.style.display = "none";
          }
        });
        </script>
      ',
      '#context' => [
        'items' => $items,
        'total' => $total_nao_lidas,
        'url_todas' => \Drupal\Core\Url::fromRoute('garagem_reservas.notificacoes')->toString(),
      ],
      '#cache' => [
        'contexts' => ['user'],
        'max-age' => 0,
      ],
    ];
  }

}
