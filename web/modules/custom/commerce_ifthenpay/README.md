# Commerce ifthenpay

Módulo Drupal 10/11 para integração do ifthenpay com o Drupal Commerce.
Suporta pagamentos **Multibanco** e **MB WAY** através de Payment Gateways nativos do Commerce.

---

## Índice

1. [Requisitos](#requisitos)
2. [Instalação](#instalação)
3. [Configuração dos Gateways](#configuração-dos-gateways)
   - [Multibanco](#multibanco)
   - [MB WAY](#mb-way)
4. [Configuração do Callback no ifthenpay](#configuração-do-callback-no-ifthenpay)
5. [Segurança](#segurança)
6. [Fluxo de Pagamento](#fluxo-de-pagamento)
7. [Suporte](#suporte)

---

## Requisitos

| Componente         | Versão mínima |
|--------------------|---------------|
| PHP                | 8.1           |
| Drupal             | 10.0          |
| Drupal Commerce    | 3.0           |
| Extensões PHP      | cURL, JSON    |

---

## Instalação

### Via Composer (recomendado)

```bash
composer require drupal/commerce_ifthenpay
drush en commerce_ifthenpay
drush cr
```

### Manual

1. Copie a pasta `commerce_ifthenpay` para `web/modules/custom/`
2. Ative o módulo em **Administração → Módulos** ou via Drush:
   ```bash
   drush en commerce_ifthenpay
   drush cr
   ```

---

## Configuração dos Gateways

Acesse **Administração → Commerce → Configuração → Gateways de Pagamento → Adicionar gateway**.

### Multibanco

1. Selecione **"Multibanco (ifthenpay)"** na lista de plugins
2. Preencha os campos:
   - **MB Key**: A chave atribuída pelo ifthenpay (formato `ITP-XXXXX`)
   - **Chave Anti-Phishing**: Chave para validar os callbacks (recomendado). Deve ser a mesma configurada no backoffice do ifthenpay
   - **Dias até expiração**: Número de dias até a referência expirar (0 = expira hoje às 23:59)
3. Anote a **URL do Callback** exibida no formulário
4. Guarde o gateway

### MB WAY

1. Selecione **"MB WAY (ifthenpay)"** na lista de plugins
2. Preencha os campos:
   - **MBWAY Key**: A chave atribuída pelo ifthenpay (formato `ITP-XXXXX`)
   - **Chave Anti-Phishing**: Chave para validar os callbacks (recomendado)
   - **Minutos até expiração**: Tempo de expiração da notificação MB WAY (padrão: 4 minutos)
3. Anote a **URL do Callback** exibida no formulário
4. Guarde o gateway

---

## Configuração do Callback no ifthenpay

Para que o módulo receba confirmações automáticas de pagamento, é necessário configurar as URLs de callback no **backoffice do ifthenpay** (<https://backoffice.ifthenpay.com>).

### URLs de Callback

As URLs são exibidas na página de configuração de cada gateway e têm o formato:

**Multibanco:**
```
https://seusite.com/payment/notify/GATEWAY_ID?key=[ANTI_PHISHING_KEY]&orderId=[ORDER_ID]&amount=[AMOUNT]&requestId=[REQUEST_ID]&entity=[entity]&reference=[REFERENCE]&payment_datetime=[PAYMENT_DATETIME]
```

**MB WAY:**
```
https://seusite.com/payment/notify/GATEWAY_ID?key=[ANTI_PHISHING_KEY]&orderId=[ORDER_ID]&amount=[AMOUNT]&requestId=[REQUEST_ID]&payment_datetime=[PAYMENT_DATETIME]
```

> **Importante:** Substitua `[ANTI_PHISHING_KEY]` pela sua chave anti-phishing **literal** (não como placeholder). Os restantes parâmetros entre `[...]` são preenchidos automaticamente pelo ifthenpay.

### Exemplo real:
```
https://loja.exemplo.pt/payment/notify/ifthenpay_multibanco?key=minhaChaveSecreta&orderId=[ORDER_ID]&amount=[AMOUNT]&requestId=[REQUEST_ID]&entity=[entity]&reference=[REFERENCE]&payment_datetime=[PAYMENT_DATETIME]
```

---

## Segurança

Este módulo foi desenvolvido com as seguintes medidas de segurança:

### Chave Anti-Phishing
- **Sempre configure** a chave anti-phishing em ambos os gateways
- A chave é validada em **todos** os callbacks recebidos usando `hash_equals()` (comparação em tempo constante, resistente a timing attacks)
- Callbacks com chave inválida são rejeitados com HTTP 403

### Validação de Valores
- O valor recebido no callback é comparado com o valor guardado no pedido
- Tolerância de €0.01 para arredondamentos de moeda
- Discrepâncias são registadas e o callback é rejeitado

### Validação de Parâmetros
- Todos os parâmetros do callback são sanitizados antes do uso
- IDs de pedidos são validados como numéricos antes de carregar da base de dados
- Formulários protegidos com CSRF tokens do Drupal

### Idempotência
- Callbacks duplicados para pedidos já pagos são ignorados (retorna 200 OK)
- Evita duplo processamento em caso de reenvio

### Logging
- Todos os eventos de pagamento são registados no sistema de log do Drupal
- Tentativas de callback inválidas são registadas com nível ERROR
- Consulte os logs em **Administração → Relatórios → Mensagens de registo** (filtrar por `commerce_ifthenpay`)

### HTTPS
- O módulo emite um aviso no Status Report se o site não usar HTTPS
- **Os pagamentos NÃO devem ser processados em HTTP puro**

### Tokens de Sessão
- Os dados de pagamento são armazenados na sessão do utilizador
- As páginas de pagamento requerem acesso autenticado ao pedido

---

## Fluxo de Pagamento

### Multibanco

```
1. Cliente seleciona Multibanco no checkout
2. Sistema chama API ifthenpay → gera Entidade/Referência/Validade
3. Pagamento criado em estado "authorization"
4. Cliente vê os dados de pagamento (entidade + referência + valor)
5. Cliente paga num ATM / homebanking / app bancária
6. ifthenpay envia callback → pagamento marcado como "completed"
7. Pedido transita para estado "completed"
```

### MB WAY

```
1. Cliente seleciona MB WAY no checkout
2. Cliente introduz número de telemóvel
3. Sistema chama API ifthenpay → envia notificação para a app MB WAY
4. Pagamento criado em estado "authorization"
5. Página de espera com countdown e polling automático
6. Cliente aprova na app MB WAY
7. ifthenpay envia callback → pagamento marcado como "completed"
8. Página de espera deteta pagamento e redireciona automaticamente
```

---

## Troubleshooting

### O callback não está a ser recebido

1. Verifique se a URL de callback está corretamente configurada no backoffice do ifthenpay
2. Confirme que o servidor aceita pedidos GET externos
3. Verifique os logs do Drupal para erros
4. Use a ferramenta **Webhook Tester** em <https://ifthenpay.com/docs/tools/webhook-tester/>

### O pagamento fica em estado "authorization" após pagamento

- O callback pode ainda não ter chegado (pode demorar alguns minutos)
- Verifique se a URL de callback está acessível publicamente
- Confirme que a chave anti-phishing no gateway corresponde à configurada no ifthenpay

### Erro "MB Key inválida"

- Confirme que a chave começa com `ITP-` seguido de letras/números
- Verifique com o suporte do ifthenpay se a chave está ativa

---

## Suporte ifthenpay

- **E-mail**: suporte@ifthenpay.com
- **Telefone**: +351 256 245 560 | 808 222 777
- **Helpdesk**: <https://helpdesk.ifthenpay.com>
- **Documentação API**: <https://www.ifthenpay.com/docs/en/>

---

## Licença

GPL-2.0-or-later. Consulte o ficheiro `LICENSE` para mais detalhes.
