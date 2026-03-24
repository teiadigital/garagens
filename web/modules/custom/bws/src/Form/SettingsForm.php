<?php

declare(strict_types=1);

namespace Drupal\bws\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Booking Warehouse System settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bws_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bws.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    
    $form['message_form_add_reservation'] = [
      '#type' => 'textarea',
      
      '#title' => $this->t('Message form add reservation'),
      '#default_value' => $this->config('bws.settings')->get('message_form_add_reservation'),
    ];


    $form['fee'] = [
      '#type' => 'number',
      '#title' => $this->t('Enter the fee (€)'),
      '#default_value' => $this->config('bws.settings')->get('fee'),
      '#step' => 0.01, 
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('bws.settings')
      ->set('fee', $form_state->getValue('fee'))
      ->set('message_form_add_reservation', $form_state->getValue('message_form_add_reservation'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
