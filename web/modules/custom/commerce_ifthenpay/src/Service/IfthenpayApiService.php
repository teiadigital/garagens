<?php

declare(strict_types=1);

namespace Drupal\commerce_ifthenpay\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Serviço para comunicação com a API do ifthenpay.
 *
 * Endpoints oficiais confirmados em https://www.ifthenpay.com/docs/en/
 *
 * Multibanco:
 *   - Produção: POST https://api.ifthenpay.com/multibanco/reference/init
 *   - Sandbox:  POST https://api.ifthenpay.com/multibanco/reference/sandbox
 *
 * MB WAY:
 *   - Criar:    POST https://api.ifthenpay.com/spg/payment/mbway
 *   - Estado:   GET  https://api.ifthenpay.com/spg/payment/mbway/status
 */
final class IfthenpayApiService {

  /**
   * Endpoint Multibanco - produção.
   */
  private const MB_ENDPOINT_PROD    = 'https://api.ifthenpay.com/multibanco/reference/init';

  /**
   * Endpoint Multibanco - sandbox (testes).
   */
  private const MB_ENDPOINT_SANDBOX = 'https://api.ifthenpay.com/multibanco/reference/sandbox';

  /**
   * Endpoint MB WAY - criar pagamento.
   */
  private const MBWAY_ENDPOINT_CREATE = 'https://api.ifthenpay.com/spg/payment/mbway';

  /**
   * Endpoint MB WAY - verificar estado.
   */
  private const MBWAY_ENDPOINT_STATUS = 'https://api.ifthenpay.com/spg/payment/mbway/status';

  /**
   * Timeout das chamadas HTTP em segundos.
   */
  private const HTTP_TIMEOUT = 30;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Gera uma referência Multibanco dinâmica.
   *
   * Campos obrigatórios: mbKey, orderId, amount.
   * Campos opcionais: description, expiryDays, url, clientCode,
   *   clientEmail, clientName, clientPhone, clientUsername.
   *
   * @param string $mbKey
   *   A MB Key atribuída pelo ifthenpay (ex: ZZZ-000000).
   * @param string $orderId
   *   O ID único do pedido (máx. 25 caracteres).
   * @param string $amount
   *   O valor a pagar com 2 casas decimais usando "." (ex: "10.50").
   * @param string $description
   *   Descrição do pagamento (máx. 200 caracteres).
   * @param int|null $daysToExpire
   *   Dias até à expiração. NULL = sem expiração. 0 = expira hoje às 23:59.
   * @param bool $sandbox
   *   TRUE para usar o endpoint sandbox (testes).
   * @param array $clientData
   *   Dados opcionais do cliente: url, clientCode, clientEmail,
   *   clientName, clientPhone, clientUsername.
   *
   * @return array{
   *   status: string,
   *   requestId: string,
   *   entity: string,
   *   reference: string,
   *   expiryDate: string
   * }
   *
   * @throws \RuntimeException
   */
  public function createMultibancoReference(
    string $mbKey,
    string $orderId,
    string $amount,
    string $description = '',
    ?int $daysToExpire = 3,
    bool $sandbox = FALSE,
    array $clientData = [],
  ): array {
    $logger = $this->loggerFactory->get('commerce_ifthenpay');

    $payload = [
      'mbKey'       => $mbKey,
      'orderId'     => mb_substr($orderId, 0, 25),
      'amount'      => $amount,
      'description' => mb_substr($description, 0, 200),
    ];

    // expiryDays só é enviado se não for NULL.
    if ($daysToExpire !== NULL) {
      $payload['expiryDays'] = $daysToExpire;
    }

    // Campos opcionais do cliente.
    $allowedClientFields = ['url', 'clientCode', 'clientEmail', 'clientName', 'clientPhone', 'clientUsername'];
    foreach ($allowedClientFields as $field) {
      if (!empty($clientData[$field])) {
        $payload[$field] = mb_substr((string) $clientData[$field], 0, 200);
      }
    }

    $endpoint = $sandbox ? self::MB_ENDPOINT_SANDBOX : self::MB_ENDPOINT_PROD;

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'json'    => $payload,
        'timeout' => self::HTTP_TIMEOUT,
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept'       => 'application/json',
        ],
      ]);

      $body = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);

      if (!isset($body['Status'])) {
        throw new \RuntimeException('Resposta inválida da API do ifthenpay (Multibanco).');
      }

      // Status "0" = sucesso.
      if ((string) $body['Status'] !== '0') {
        $logger->error('ifthenpay Multibanco API erro. Status: @s, Mensagem: @m, OrderId: @o', [
          '@s' => $body['Status'] ?? 'N/A',
          '@m' => $body['Message'] ?? 'N/A',
          '@o' => $orderId,
        ]);
        throw new \RuntimeException(
          sprintf('ifthenpay Multibanco erro: %s', $body['Message'] ?? 'Erro desconhecido')
        );
      }

      $logger->info('Referência Multibanco gerada. OrderId: @o, Entidade: @e, Referência: @r, Sandbox: @sb', [
        '@o'  => $orderId,
        '@e'  => $body['Entity'] ?? '',
        '@r'  => $body['Reference'] ?? '',
        '@sb' => $sandbox ? 'sim' : 'não',
      ]);

      return [
        'status'     => (string) $body['Status'],
        'requestId'  => (string) ($body['RequestId'] ?? ''),
        'entity'     => (string) ($body['Entity'] ?? ''),
        'reference'  => (string) ($body['Reference'] ?? ''),
        'expiryDate' => (string) ($body['ExpiryDate'] ?? ''),
      ];
    }
    catch (RequestException $e) {
      $logger->error('ifthenpay Multibanco HTTP erro. OrderId: @o, Erro: @e', [
        '@o' => $orderId,
        '@e' => $e->getMessage(),
      ]);
      throw new \RuntimeException(
        'Erro de comunicação com a API do ifthenpay: ' . $e->getMessage(), 0, $e
      );
    }
    catch (\JsonException $e) {
      $logger->error('ifthenpay Multibanco JSON inválido. OrderId: @o', ['@o' => $orderId]);
      throw new \RuntimeException('Resposta JSON inválida da API do ifthenpay.', 0, $e);
    }
  }

  /**
   * Inicia um pagamento MB WAY.
   *
   * Campos obrigatórios: mbWayKey, orderId, amount, mobileNumber.
   * O número de telemóvel deve incluir indicativo separado por "#"
   * (ex: "351#912345678").
   *
   * @param string $mbWayKey
   *   A MBWAY Key atribuída pelo ifthenpay (ex: ZZZ-000000).
   * @param string $orderId
   *   O ID único do pedido (máx. 15 caracteres).
   * @param string $amount
   *   O valor a pagar com "." como separador decimal (ex: "10.50").
   * @param string $mobileNumber
   *   Número de telemóvel com indicativo e "#" (ex: "351#912345678").
   * @param string $description
   *   Descrição do pagamento (máx. 100 caracteres).
   * @param string $email
   *   Email do cliente (máx. 100 caracteres).
   *
   * @return array{
   *   status: string,
   *   message: string,
   *   requestId: string
   * }
   *
   * @throws \RuntimeException
   */
  public function createMbWayPayment(
    string $mbWayKey,
    string $orderId,
    string $amount,
    string $mobileNumber,
    string $description = '',
    string $email = '',
  ): array {
    $logger = $this->loggerFactory->get('commerce_ifthenpay');

    // Garantir formato correto: indicativo#número (ex: 351#912345678).
    $mobileNumber = $this->formatMbWayPhone($mobileNumber);

    $payload = [
      'mbWayKey'     => $mbWayKey,
      'orderId'      => mb_substr($orderId, 0, 15),
      'amount'       => $amount,
      'mobileNumber' => $mobileNumber,
      'email'        => mb_substr($email, 0, 100),
      'description'  => mb_substr($description, 0, 100),
    ];

    try {
      $response = $this->httpClient->request('POST', self::MBWAY_ENDPOINT_CREATE, [
        'json'    => $payload,
        'timeout' => self::HTTP_TIMEOUT,
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept'       => 'application/json',
        ],
      ]);

      $body = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);

      if (!isset($body['Status'])) {
        throw new \RuntimeException('Resposta inválida da API do ifthenpay (MB WAY).');
      }

      // Status "000" = pedido aceite (aguarda aprovação na app).
      if ((string) $body['Status'] !== '000') {
        $logger->error('ifthenpay MB WAY API erro. Status: @s, Mensagem: @m, OrderId: @o', [
          '@s' => $body['Status'] ?? 'N/A',
          '@m' => $body['Message'] ?? 'N/A',
          '@o' => $orderId,
        ]);
        throw new \RuntimeException(
          sprintf('ifthenpay MB WAY erro: %s', $body['Message'] ?? 'Erro desconhecido')
        );
      }

      $logger->info('MB WAY pagamento iniciado. OrderId: @o, RequestId: @r', [
        '@o' => $orderId,
        '@r' => $body['RequestId'] ?? '',
      ]);

      return [
        'status'    => (string) $body['Status'],
        'message'   => (string) ($body['Message'] ?? ''),
        'requestId' => (string) ($body['RequestId'] ?? ''),
      ];
    }
    catch (RequestException $e) {
      $logger->error('ifthenpay MB WAY HTTP erro. OrderId: @o, Erro: @e', [
        '@o' => $orderId,
        '@e' => $e->getMessage(),
      ]);
      throw new \RuntimeException(
        'Erro de comunicação com a API do ifthenpay: ' . $e->getMessage(), 0, $e
      );
    }
    catch (\JsonException $e) {
      $logger->error('ifthenpay MB WAY JSON inválido. OrderId: @o', ['@o' => $orderId]);
      throw new \RuntimeException('Resposta JSON inválida da API do ifthenpay.', 0, $e);
    }
  }

  /**
   * Verifica o estado de um pagamento MB WAY.
   *
   * Query params: mbWayKey, requestId.
   *
   * Resposta: Status "000" = pago, outros = pendente/erro.
   *
   * @param string $mbWayKey
   *   A MBWAY Key.
   * @param string $requestId
   *   O RequestId devolvido pelo createMbWayPayment().
   *
   * @return string
   *   O código de estado: "000" = pago, outros = pendente/falha.
   *
   * @throws \RuntimeException
   */
  public function getMbWayPaymentStatus(string $mbWayKey, string $requestId): string {
    $logger = $this->loggerFactory->get('commerce_ifthenpay');

    try {
      $response = $this->httpClient->request('GET', self::MBWAY_ENDPOINT_STATUS, [
        'query'   => [
          'mbWayKey'  => $mbWayKey,
          'requestId' => $requestId,
        ],
        'timeout' => self::HTTP_TIMEOUT,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      $body = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);

      return (string) ($body['Status'] ?? 'ERR');
    }
    catch (RequestException $e) {
      $logger->warning('ifthenpay MB WAY status check erro. RequestId: @r, Erro: @e', [
        '@r' => $requestId,
        '@e' => $e->getMessage(),
      ]);
      throw new \RuntimeException('Erro ao verificar estado MB WAY: ' . $e->getMessage(), 0, $e);
    }
    catch (\JsonException $e) {
      throw new \RuntimeException('Resposta JSON inválida ao verificar estado MB WAY.', 0, $e);
    }
  }

  /**
   * Formata o número de telemóvel para o formato MB WAY.
   *
   * A API do ifthenpay requer o formato: indicativo#número
   * Exemplos: "351#912345678", "351#962345678"
   *
   * @param string $phone
   *   Número introduzido pelo utilizador (vários formatos aceites).
   *
   * @return string
   *   Número no formato "351#XXXXXXXXX".
   */
  public function formatMbWayPhone(string $phone): string {
    // Remover caracteres não numéricos (exceto #).
    $phone = preg_replace('/[^0-9#]/', '', $phone);

    // Se já tem o formato correto (ex: 351#912345678), devolver.
    if (preg_match('/^\d{1,4}#\d{9}$/', $phone)) {
      return $phone;
    }

    // Remover o # se existir para normalizar.
    $phone = str_replace('#', '', $phone);

    // Se começa com 351 e tem 12 dígitos: 351XXXXXXXXX → 351#XXXXXXXXX.
    if (preg_match('/^351(\d{9})$/', $phone, $matches)) {
      return '351#' . $matches[1];
    }

    // Se tem 9 dígitos e começa com 9 (Portugal): XXXXXXXXX → 351#XXXXXXXXX.
    if (preg_match('/^(9\d{8})$/', $phone, $matches)) {
      return '351#' . $matches[1];
    }

    // Outros formatos: devolver como está (a API irá validar).
    return $phone;
  }

}
