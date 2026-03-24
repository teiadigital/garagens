<?php

namespace Drupal\management_armazens\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Component\Utility\UrlHelper;

class SanitizeLoginRedirectSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 35],
    ];
  }

  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Verifica se o path é /user/login e tem ?destination= externa
    if (str_starts_with($request->getPathInfo(), '/user') && $request->query->has('destination')) {
      $destination = $request->query->get('destination');

      if (UrlHelper::isExternal($destination)) {
        // Remove destination insegura
        $query = $request->query->all();
        unset($query['destination']);
        $request->query->replace($query);
      }
    }
  }
}
