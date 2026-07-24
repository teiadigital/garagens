<?php

namespace Drupal\management_armazens\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Prevents one-time-login sessions from browsing before changing a password.
 */
final class PasswordResetAccessSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Restricts a password-reset session to its password form.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || !$this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    if (!$request->hasSession()) {
      return;
    }

    // Do not intercept stylesheets, scripts, images or other non-page
    // requests. These may be handled by Drupal when aggregation is enabled.
    $accept = (string) $request->headers->get('Accept', '');
    if ($accept !== '' && !str_contains($accept, 'text/html')) {
      return;
    }

    $uid = (int) $this->currentUser->id();
    $session_key = 'pass_reset_' . $uid;
    $token = $request->getSession()->get($session_key);
    if (!is_string($token) || $token === '') {
      return;
    }

    $route_name = (string) $request->attributes->get('_route');
    if (in_array($route_name, ['user.logout', 'user.logout.confirm'], TRUE)) {
      return;
    }

    $route_user = $request->attributes->get('user');
    $route_uid = is_object($route_user) && method_exists($route_user, 'id')
      ? (int) $route_user->id()
      : (int) $route_user;
    $request_token = (string) $request->query->get('pass-reset-token', '');

    $is_password_form = $route_name === 'entity.user.edit_form'
      && $route_uid === $uid
      && $request_token !== ''
      && hash_equals($token, $request_token);

    if ($is_password_form) {
      return;
    }

    // Leaving the recovery form cancels the temporary authenticated session.
    // The user can restart recovery or log in normally instead of being
    // trapped in a redirect loop.
    user_logout();
    $event->setResponse(new RedirectResponse(Url::fromRoute('user.login')->toString()));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after routing so the route name and parameters are available.
    return [KernelEvents::REQUEST => ['onRequest', 20]];
  }

}
