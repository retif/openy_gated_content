<?php

namespace Drupal\openy_gc_auth_daxko_sso\Plugin\GCIdentityProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\daxko_sso\DaxkoSSOClient;
use Drupal\openy_gc_auth\GCIdentityProviderPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Example identity provider plugin.
 *
 * @GCIdentityProvider(
 *   id="daxkosso",
 *   label = @Translation("Daxko SSO provider"),
 *   config="openy_gc_auth.provider.daxko_sso"
 * )
 */
class DaxkoSSO extends GCIdentityProviderPluginBase {

  use MessengerTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Daxko Client service instance.
   *
   * @var \Drupal\daxko_sso\DaxkoSSOClient
   */
  protected $daxkoClient;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration():array {
    return [
      'redirect_url' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    DaxkoSSOClient $daxkoSSOClient,
    RequestStack $requestStack,
    FormBuilderInterface $form_builder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config, $entity_type_manager, $form_builder);
    $this->daxkoClient = $daxkoSSOClient;
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('daxko_sso.client'),
      $container->get('request_stack'),
      $container->get('form_builder')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['redirect_url'] = [
      '#title' => $this->t('Url'),
      '#description' => $this->t('Daxko back link redirect e.g. /openy-gc-auth-daxko-sso/back-redirect'),
      '#type' => 'textfield',
      '#default_value' => $config['redirect_url'],
      '#required' => TRUE,
    ];

    $form['error_accompanying_message'] = [
      '#title' => $this->t('Authentication error message'),
      '#description' => $this->t('Message displayed to user when he failed to log in using personify plugin.'),
      '#type' => 'textfield',
      '#default_value' => $config['error_accompanying_message'],
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $this->configuration['redirect_url'] = $form_state->getValue('redirect_url');
      $this->configuration['error_accompanying_message'] = $form_state->getValue('error_accompanying_message');

      $baseUrl = $this->request->getSchemeAndHttpHost();

      $result = $this
        ->daxkoClient
        ->registerSSORedirectLink($baseUrl . $form_state->getValue('redirect_url'));

      if (!$result['error']) {
        $this->messenger()->addStatus('We were able to register your URL at Daxko API settings');
      }
      else {
        $this->messenger()->addError('Attempt to register redirect url was failed. ' . $result['message']);
      }
      parent::submitConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginForm() {
    if ($this->request->query->has('error')) {
      return $this->formBuilder->getForm('Drupal\openy_gc_auth_daxko_sso\Form\TryAgainForm');
    }
    return new RedirectResponse(Url::fromRoute('openy_gc_auth_daxko_sso.daxko_link_controller_hello')->toString());
  }

}
