<?php declare(strict_types = 1);

namespace Drupal\management_armazens\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use \Drupal\user\Entity\User;

/**
 * Provides an actions armazens block.
 *
 * @Block(
 *   id = "management_armazens_actions_pedidos",
 *   admin_label = @Translation("Actions Pedidos"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
final class ActionsPedidosBlock extends BlockBase {

  private function states($state) {

    switch ($state) {
      case '-1':
        return [
          'class' => 'aguardar-aprovacao',
          'state' => t("Awaiting approval")
        ];
      case '1': 
        return [
          'class' => 'nao-aprovado',
          'state' => t("Not Approved")
        ];
      case '2': 
        return [
          'class' => 'aprovado',
          'state' => t("Approved")
        ];
    }
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $form = null;
    $data = null;

    $node = $this->getContextValue('node');
    $nid_fld = $node->nid->getValue();
    $nid = $nid_fld[0]['value'];

    $user = User::load(\Drupal::currentUser()->id());

    $estado = $node->get('field_estado')->getString();

    if (in_array('gestor', $user->getRoles()) || in_array('administrator', $user->getRoles())) {

      if($estado !== "") {
        $form = \Drupal::formBuilder()->getForm('Drupal\management_armazens\Form\ActionsPedidosForm', $node);
      }

      $data = array(
        'estado' => $this->states($estado)
      );
    }

    // if (in_array('utilizador', $user->getRoles())) {
      
    //   $author_id = $node->getOwner()->id();

    //   if($author_id == \Drupal::currentUser()->id()) {

    //     $url = Url::fromRoute('entity.node.edit_form', array('node' => $nid));
    //     $link = Link::fromTextAndUrl(t("Edit"), $url)->toRenderable();

    //     $data = array(
    //       'estado' => $this->states($estado),
    //       'edit' => $link
    //     );
    //   }
    // }

    $block = [
      '#theme' => 'actions_armazens',
      '#data' => $data,
      '#form' => $form
    ];

    return $block;
  }

  public function getCacheMaxAge() {
    return 0;
  }

}
