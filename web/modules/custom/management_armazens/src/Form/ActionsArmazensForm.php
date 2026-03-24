<?php

declare(strict_types=1);

namespace Drupal\management_armazens\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Actions (aprovar / rejeitar / erros) para Armazéns.
 */
final class ActionsArmazensForm extends FormBase
{

  public function getFormId(): string
  {
    return 'management_armazens_actions_armazens';
  }

  /**
   * @param \Drupal\node\NodeInterface|null $node
   *   Node Armazém passado pelo bloco (obrigatório para esta UI).
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array
  {
    // Sem node não há ações.
    if (!$node || $node->bundle() !== 'armazem') {
      return $form;
    }

    // Apenas gestores/administradores devem ver/usar este formulário.
    $account = $this->currentUser();
    if (!array_intersect(['gestor', 'administrator'], $account->getRoles())) {
      return $form;
    }

    // Flags para mostrar os textareas (guardadas em estado da form).
    $bool_errors = (bool) $form_state->get('ma_bool_errors');
    $bool_deny   = (bool) $form_state->get('ma_bool_deny');

    // Wrapper AJAX para atualizar só esta área.
    $form['actions_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ma-actions-wrapper'],
    ];

    if ($bool_errors) {
      $form['actions_wrapper']['description_error'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Error description'),
        '#required' => TRUE,
      ];
    }

    if ($bool_deny) {
      $form['actions_wrapper']['description_deny'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Reason for disapproval'),
        '#required' => TRUE,
      ];
    }

    $form['actions_wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    // Botão para abrir o textarea de "erros".
    if (!$bool_errors && !$bool_deny) {
      $form['actions_wrapper']['actions']['show_textarea_errors'] = [
        '#type' => 'submit',
        '#value' => $this->t('Warehouse with errors'),
        '#limit_validation_errors' => [],
        '#submit' => ['::submitShowTextareaErrors'],
        '#ajax' => [
          'callback' => '::ajaxRebuild',
          'wrapper'  => 'ma-actions-wrapper',
          'event'    => 'click',
        ],
        '#attributes' => ['class' => ['button-actions-armazem', 'com-erros']],
      ];
    }

    // Aprovar (estado = 3).
    if (!$bool_errors && !$bool_deny) {
      $form['actions_wrapper']['actions']['approve'] = [
        '#type' => 'submit',
        '#value' => $this->t('Approve'),
        '#submit' => ['::submitFormApprove'],
        '#attributes' => ['class' => ['button-actions-armazem', 'aprovado']],
      ];
    }

    // Botão para abrir o textarea de "rejeição".
    if (!$bool_deny && !$bool_errors) {
      $form['actions_wrapper']['actions']['show_textarea_deny'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reject'),
        '#limit_validation_errors' => [],
        '#submit' => ['::submitShowTextareaDeny'],
        '#ajax' => [
          'callback' => '::ajaxRebuild',
          'wrapper'  => 'ma-actions-wrapper',
          'event'    => 'click',
        ],
        '#attributes' => ['class' => ['button-actions-armazem', 'nao-aprovado']],
      ];
    }

    // Submeter “erros” (estado = 1).
    if ($bool_errors && !$bool_deny) {
      $form['actions_wrapper']['actions']['errors'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#submit' => ['::submitFormErrors'],
        '#attributes' => ['class' => ['button-actions-armazem', 'com-erros']],
      ];
    }

    // Submeter “rejeição” (estado = 2).
    if ($bool_deny) {
      $form['actions_wrapper']['actions']['deny'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#submit' => ['::submitFormDeny'],
        '#attributes' => ['class' => ['button-actions-armazem', 'nao-aprovado']],
      ];
    }

    // Guarda o node no build_info para usar nos submits.
    $build_info = $form_state->getBuildInfo();
    $build_info['args'][0] = $node;
    $form_state->setBuildInfo($build_info);

    return $form;
  }

  /** AJAX: devolve o wrapper para atualizar. */
  public function ajaxRebuild(array &$form, FormStateInterface $form_state)
  {
    return $form['actions_wrapper'];
  }

  public function submitShowTextareaErrors(array &$form, FormStateInterface $form_state): void
  {
    $form_state->set('ma_bool_errors', TRUE);
    $form_state->set('ma_bool_deny', FALSE);
    $form_state->setRebuild();
  }

  public function submitShowTextareaDeny(array &$form, FormStateInterface $form_state): void
  {
    $form_state->set('ma_bool_deny', TRUE);
    $form_state->set('ma_bool_errors', FALSE);
    $form_state->setRebuild();
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /** Submeter com “erros” (estado = 1, despublicado). */
  public function submitFormErrors(array &$form, FormStateInterface $form_state): void
  {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getBuildInfo()['args'][0];

    $this->messenger()->addStatus($this->t('A message has been sent to the stockist to correct the errors.'));
    $description = (string) $form_state->getValue('description_error');

    $node->set('field_estado', 1);
    $node->setUnpublished();
    $node->save();

    $user = $node->getOwner(); // devolve UserInterface
    $email = $user instanceof UserInterface ? $user->getEmail() : '';
    $subject = $this->t('The warehouse has errors');
    $message = $this->t('Errors have been found, please correct the following errors.') . '<br>' . $description;

    if ($email) {
      $this->sendMail($email, $subject, $message);
    } else {
      $this->messenger()->addWarning($this->t('No email found for the warehouse owner.'));
    }
  }

  /** Submeter “rejeição” (estado = 2, despublicado). */
  public function submitFormDeny(array &$form, FormStateInterface $form_state): void
  {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getBuildInfo()['args'][0];

    $this->messenger()->addStatus($this->t('The warehouse has failed.'));
    $description = (string) $form_state->getValue('description_deny');

    $node->set('field_estado', 2);
    $node->setUnpublished();
    $node->save();

    $user = $node->getOwner();                      
    $email = ($user instanceof UserInterface) ? $user->getEmail() : '';

    $subject = $this->t('The warehouse has failed');
    $message = $this->t('Your warehouse has been rejected. The reason was:') . '<br>' . $description;

    if ($email) {
      $this->sendMail($email, $subject, $message);
    } else {
      $this->messenger()->addWarning($this->t('No email found for the warehouse owner.'));
    }
  }

  /** Aprovar (estado = 3, publicado). */
  public function submitFormApprove(array &$form, FormStateInterface $form_state): void
  {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getBuildInfo()['args'][0];

    $this->messenger()->addStatus($this->t('The warehouse has been approved.'));

    $node->set('field_estado', 3);
    $node->setPublished(TRUE);
    $node->save();

    $user = $node->getOwner();                      // UserInterface
    $email = ($user instanceof UserInterface) ? $user->getEmail() : '';

    $subject = $this->t('The warehouse has been approved');
    $message = $this->t('Your warehouse has been approved and you can now make reservations');

    if ($email) {
      $this->sendMail($email, $subject, $message);
    } else {
      $this->messenger()->addWarning($this->t('No email found for the warehouse owner.'));
    }

    // Call opcional (evita fatal se a função não existir).
    if (empty($node->get('field_availability_daily')->getValue()) && function_exists('bws_node_insert_add_booking')) {
      bws_node_insert_add_booking($node);
    }
  }

  protected function sendMail(
    string $to,
    TranslatableMarkup|string $subject,
    TranslatableMarkup|string $body
  ): bool {
    $mailManager = \Drupal::service('plugin.manager.mail');

    $params = [
      'subject' => str_replace(["\r\n", "\r", "\n"], ' ', (string) $subject),
      'body'    => (string) $body,
    ];

    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    $result = $mailManager->mail(
      'management_armazens',
      'general_mail',
      $to,
      $langcode,
      $params,
      NULL,
      TRUE
    );

    if (empty($result['result'])) {
      \Drupal::logger('management_armazens')->error(
        'Falha ao enviar email para @to com assunto "@s".',
        ['@to' => $to, '@s' => (string) $subject]
      );
      $this->messenger()->addError($this->t('There was a problem sending the email.'));
      return FALSE;
    }

    return TRUE;
  }
}
