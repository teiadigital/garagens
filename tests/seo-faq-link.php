<?php

/**
 * Regression check for internal FAQ links, without a running database.
 * Run: php tests/seo-faq-link.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Drupal\Core\Template\TwigExtension;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$validator = new class {
  public bool $resolve = TRUE;
  public function getUrlIfValidWithoutAccessCheck($path) {
    if ($path !== 'faqs') {
      throw new RuntimeException('Unexpected FAQ path.');
    }
    return $this->resolve ? Url::fromRoute('entity.node.canonical', ['node' => 42]) : FALSE;
  }
};
$container->set('path.validator', $validator);
Drupal::setContainer($container);
// getLink() uses no constructor dependencies; exercise the actual Drupal method.
$extension = (new ReflectionClass(TwigExtension::class))->newInstanceWithoutConstructor();
foreach ([TRUE, FALSE] as $resolve) {
  $validator->resolve = $resolve;
  $build = $extension->getLink('Perguntas frequentes', 'internal:/faqs');
  if ($build['#type'] !== 'link' || !$build['#url'] instanceof Url) {
    throw new RuntimeException('FAQ link did not produce a Drupal link element.');
  }
  if ($resolve && $build['#url']->getRouteName() !== 'entity.node.canonical') {
    throw new RuntimeException('FAQ alias was not resolved.');
  }
  if (!$resolve && $build['#url']->getUri() !== 'base:faqs') {
    throw new RuntimeException('Missing alias must use the safe internal fallback.');
  }
}
$template = file_get_contents(dirname(__DIR__) . '/web/themes/armazens_theme/templates/layout/page--front.html.twig');
if (!str_contains($template, "link('Perguntas frequentes'|t, 'internal:/faqs')") || str_contains($template, "url('internal:")) {
  throw new RuntimeException('Homepage must use link() for internal URIs.');
}
echo "PASS: Drupal FAQ link resolves aliases and handles missing aliases without an invalid route.\n";
