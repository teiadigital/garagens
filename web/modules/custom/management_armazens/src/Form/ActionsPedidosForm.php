<?php declare(strict_types = 1);

namespace Drupal\management_armazens\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\node\NodeInterface;
use \Drupal\user\Entity\User;

/**
 * Provides a management armazens form.
 */
final class ActionsPedidosForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'management_armazens_actions_pedidos';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL): array {


    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Approve'),
      '#submit' => array([$this, 'submitFormApprove']),
      '#attributes' => [
        'class' => [
          'button-actions-armazem','aprovado'
        ]
      ]
    ];
    
    $form['actions']['deny'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#submit' => array([$this, 'submitFormDeny']),
      '#attributes' => [
        'class' => [
          'button-actions-armazem','nao-aprovado'
        ]
      ]
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

    /**
   * {@inheritdoc}
   */
  public function submitFormDeny(array &$form, FormStateInterface $form_state): void {
    \Drupal::messenger()->addMessage(t('The request has failed.'));

    $node = $form_state->getBuildInfo()['args'][0];
    $node->set('field_estado', 1);
    $node->setUnPublished(TRUE);
    $node->save();

    // $user = User::load($node->getOwner()->id());
    // $subject = t('The warehouse has failed'); 
    // $message = t("Your warehouse has been rejected. The reason was:") . "<br>" . $description_deny;

    // $this->send_mail($user, $subject, $message);
  } 

  /**
   * {@inheritdoc}
   */
  public function submitFormApprove(array &$form, FormStateInterface $form_state): void {

    \Drupal::messenger()->addMessage(t('The request has been approved.'));
    
    $node = $form_state->getBuildInfo()['args'][0];
    $node->set('field_estado', 2);
    $node->setPublished(TRUE);
    $node->save();

    // $user = User::load($node->getOwner()->id());
    // $subject = $this->t('The warehouse has been approved'); 
    // $message = t("Your warehouse has been approved and you can now make reservations");

    // $this->send_mail($user, $subject, $message);
    
  }


  private function send_mail($user, $subject, $message) {
    $user_email = $user->getEmail();

    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'management_armazens';
    $key = 'general_mail';
    $to = $user_email;
    $params['body'] = $message;
    $params['subject'] = $subject;
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    
    if ($result['result'] !== true) {
      \Drupal::messenger()->addError(t('There was a problem sending your message and it was not sent.'), 'error');
    }
  }

}
