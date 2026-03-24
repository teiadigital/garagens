<?php

namespace Drupal\user_menu_avatar_dropdown\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "user_menu_avatar_dropdown",
 *   admin_label = @Translation("User Menu Avatar Dropdown"),
 *   category = @Translation("User Menu Avatar Dropdown")
 * )
 */
class MenuAvatarDropdown extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $user = NULL;

  /**
   * If the current page is a user page.
   *
   * @var bool
   */
  protected $isUserPage;

  /**
   * The user statistics manager.
   *
   * @var \Drupal\opigno_statistics\Services\UserStatisticsManager
   */
  protected $statsManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $account, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, ...$default) {
    parent::__construct(...$default);
    $uid = (int) $account->id();
    $this->isUserPage = $route_match->getRouteName() === 'entity.user.canonical' && (int) $route_match->getRawParameter('user') === $uid;
    // $this->statsManager = $user_stats_manager;

    try {
      $this->user = $entity_type_manager->getStorage('user')->load($uid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
    }
  }

   /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      // $container->get('opigno_statistics.user_stats_manager'),
      // $container->get('menu.link_tree'),
      // $container->get('opigno_notification.manager'),
      // $container->get('private_message.service'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    if (!$this->user instanceof UserInterface) {
      return [
        '#cache' => ['max-age' => 0],
      ];
    }

    // Get the logo path and the main menu.
    $role = $this->getUserRole($this->user);

    return [
      '#theme' => 'user_menu_avatar_dropdown_template',
      '#is_anonymous' => $this->user->isAnonymous(),
      '#is_user_page' => $this->isUserPage,
      '#user_name' => $this->user->get('name')->value,
      '#user_email' => $this->user->get('mail')->value,
      '#user_url' => Url::fromRoute('entity.user.canonical', ['user' => (int) $this->user->id()])->toString(),
      '#user_picture' => $this->getUserPicture($this->user),
      '#dropdown_menu' => $this->buildUserDropdownMenu($role),
      '#dropdown_menu_anonymous' => $this->buildAnonymousDropdownMenu(),
    ];
  }

  public function getCacheContexts() {
    $contexts = Cache::mergeContexts(parent::getCacheContexts(), [
      // 'url.path.is_current_user_page',
      'url.path',
    ]);
    if ($this->user instanceof UserInterface) {
      $contexts = Cache::mergeContexts($contexts, ['user']);
    }

    return $contexts;
  }

  public static function getUserPicture(UserInterface $user, string $image_style = 'user_compact_image'): array {
    // Display the user image if it isn't empty.
    if (!$user->get('user_picture')->isEmpty()) {
      return $user->get('user_picture')->view([
        'label' => 'hidden',
        'type' => 'image',
        'settings' => [
          'image_style' => $image_style,
        ]
      ]);
    }

    // Display the default profile picture.
    return static::getDefaultUserPicture($user);
  }

  public static function getDefaultUserPicture(?UserInterface $user = NULL): array {

    $path = \Drupal::service('extension.list.module')->getPath('user_menu_avatar_dropdown') . '/images/profil.svg';
    $name = $user instanceof UserInterface ? $user->getDisplayName() : t('User');

    return [
      '#theme' => 'image',
      '#uri' => file_exists($path) ? \Drupal::service('file_url_generator')->transformRelative(base_path() . $path) : '',
      '#alt' => $name,
      '#title' => $name,
    ];
  }

  /**
   * Get the user role name.
   *
   * @param \Drupal\user\UserInterface|\Drupal\Core\Session\AccountInterface|null $user
   *   The user to get the role of.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable user role name.
   */
  public function getUserRole(?AccountInterface $user = NULL): TranslatableMarkup {
    // Add user role.

    $roles = $user->getRoles(TRUE);
    
    if (in_array('candidato', $roles)) {
      $role = $this->t('Candidato');
    }
    elseif (in_array('administrator', $roles)) {
      $role = $this->t('Administrator');
    }
    elseif (in_array('gestor', $roles)) {
      $role = $this->t('Gestor');
    }
    elseif (in_array('cliente', $roles)) {
      $role = $this->t('Empresa');
    }
    else {
      $role = $this->t('Anónimo');
    }

    return $role;
  }

  /**
   * Prepare the user dropdown menu.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $role
   *   The user role.
   *
   * @return array
   *   The array to build the user dropdown menu.
   */
  private function buildUserDropdownMenu(TranslatableMarkup $role): array {
    if (!$this->user instanceof UserInterface) {
      return [];
    }

    return [
      'user_picture' => $this->getUserPicture($this->user),
      'name' => $this->user->getDisplayName(),
      'url_user' => Url::fromRoute('entity.user.canonical', ['user' => (int) $this->user->id()])->toString(),
      'role' => $role,
      'is_admin' => $this->user->hasPermission('access administration pages'),
      'links' => [
        'account' => [
          'title' => $this->t('My account'),
          'path' =>  Url::fromRoute('user.page')->toString(),
        ],
        'edit' => [
          'title' => $this->t('Edit Profile'),
          'path' =>  Url::fromRoute('user.edit')->toString(),
          'icon_class' => 'edit-icon',
        ],
        'faqs' => [
          'title' => $this->t('FAQs'),
          'path' =>  Url::fromUserInput('/pt/faqs'),
          'icon_class' => 'faqs-icon',
        ],
        'logout' => [
          'title' => $this->t('Logout'),
          'path' => Url::fromRoute('user.logout')->toString(),
          'icon_class' => 'sign-out-icon',
        ],
      ],
    ];
  }

  private function buildAnonymousDropdownMenu(): array {

    return [
      'links' => [
        'entrar' => [
          'title' => t('Login'),
          'path' => Url::fromRoute('user.login')->toString(),
          'icon_class' => 'login-icon',
        ]
      ],
    ];
  }
}

