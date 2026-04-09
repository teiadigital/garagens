# Contexto do Projeto — Garagens (Drupal 10)

## Stack

- **Drupal 10** com **Lando** (local dev)
- **PHP 8.3**, **MariaDB**
- **Tema:** `armazens_theme` — sub-tema Bootstrap com **Tailwind CSS** (via CDN, não compilado — usar apenas classes do core do Tailwind disponíveis no CDN)
- **Git/GitHub**
- URL local: `https://garagens.lndo.site`

---

## Content Types

- `armazem` — garagem/armazém para alugar
- `Landing Page`
- `Página Pesquisa`
- `contrato_template`

### Campos relevantes do `armazem`
- `field_preco_dia`, `field_preco_mes`, `field_preco_ano` — preços por tipo
- `field_localidade` — morada (address field)
- `field_min_dias_reserva` — mínimo de dias de reserva (integer, adicionado manualmente no admin)
- `field_telefone`, `field_telemovel` — contactos do proprietário

---

## Módulos Contrib Principais

- **Commerce** (Drupal Commerce 2.x) — gestão de orders e pagamentos
- **Message** + **Message Notify** — sistema de notificações
- **Symfony Mailer** — envio de emails
- **Paragraphs**
- **commerce_ifthenpay** (custom/modificado) — gateway de pagamento Ifthenpay

---

## Módulo Custom: `garagem_reservas`

**Localização:** `web/modules/custom/garagem_reservas/`

### Tabelas BD

**`garagem_reserva`**
```
id, garagem_id, user_id, proprietario_id,
data_inicio (timestamp), data_fim (timestamp, NULL = renovação automática),
renovacao_automatica (boolean),
tipo_preco (dia|mes|ano),
preco_total, taxa_plataforma,
estado (pendente|aprovado|aguarda_pagamento|pago|rejeitado|cancelado|expirado),
data_criacao, data_aprovacao, data_pagamento,
commerce_order_id,
notas (texto livre do utilizador),
motivo (motivo de cancelamento/rejeição — adicionado em update_8004)
```

**`garagem_indisponibilidade`**
```
id, garagem_id, data_inicio, data_fim, data_criacao
```

### Fluxo de Estados
```
pendente → aprovado → aguarda_pagamento → pago
                   ↘ rejeitado
         ↘ cancelado (qualquer estado)
         ↘ expirado (cron — pagamento não concluído)
```

### Tipos de Preço
- **dia** — Flatpickr range (início e fim exclusivo: 01→02 = 1 dia)
- **mes** — Flatpickr single, fim = início + 1 mês (com fix de overflow)
- **ano** — Flatpickr single, fim = início + 1 ano

### Ficheiros PHP

```
src/
  Controller/
    ReservaController.php     — rotas principais, CRUD reservas, encomendas
    ReservaAdminController.php — admin
  Form/
    ReservaForm.php            — formulário de nova reserva (com validação min_dias)
    ReservaCancelarForm.php
    ReservaAdminEditForm.php
    ReservaAdminDeleteForm.php
    GaragemReservasSettingsForm.php
    GaragemDisponibilidadeForm.php
  Service/
    PrecoService.php           — cálculo de preços e taxas
    DisponibilidadeService.php — verificar disponibilidade
    NotificacaoService.php     — enviar notificações via Message module
    CommerceService.php        — criação de orders no Commerce
  EventSubscriber/
    CommerceOrderEventSubscriber.php — escuta ORDER_PAID → muda reserva para 'pago'
  Plugin/Block/
    ReservarBlock.php          — bloco do botão "Reservar"
    ProprietarioBlock.php      — bloco do proprietário
    NotificacoesBlock.php      — sino de notificações com dropdown
```

### Rotas Principais

| Rota | Path | Descrição |
|------|------|-----------|
| `garagem_reservas.reserva_add` | `/garagem/{node}/reservar` | Formulário de reserva |
| `garagem_reservas.reserva_view` | `/reserva/{reserva}` | Detalhe da reserva |
| `garagem_reservas.reserva_cancelar_direto` | `/reserva/{reserva}/cancelar-direto` | Cancelar pelo user |
| `garagem_reservas.reserva_cancelar_proprietario` | `/reserva/{reserva}/cancelar-proprietario` | Cancelar pelo proprietário |
| `garagem_reservas.reserva_rejeitar` | `/reserva/{reserva}/rejeitar` | Rejeitar (proprietário) |
| `garagem_reservas.reserva_aprovar` | `/reserva/{reserva}/aprovar` | Aprovar (proprietário) |
| `garagem_reservas.lista_user` | `/dashboard/reservas` | Lista unificada |
| `garagem_reservas.lista_garagem` | `/dashboard/garagens/{node}/reservas` | Reservas de uma garagem |
| `garagem_reservas.disponibilidade` | `/dashboard/garagens/{node}/disponibilidade` | Gerir disponibilidade |
| `garagem_reservas.notificacoes` | `/dashboard/notificacoes` | Página de notificações |
| `garagem_reservas.encomendas_lista` | `/dashboard/encomendas` | Lista de encomendas do user |
| `garagem_reservas.encomenda_detalhe` | `/dashboard/encomendas/{order_id}` | Detalhe de encomenda |
| `garagem_reservas.admin_lista` | `/admin/garagem-reservas/reservas` | Admin — lista |
| `garagem_reservas.json_disponibilidade` | `/garagem/{node}/disponibilidade` | JSON endpoint para Flatpickr |

Todas as rotas têm `options: no_cache: true`.

### Templates Twig

```
templates/
  garagem-reserva-form.html.twig        — formulário de reserva
  garagem-reserva.html.twig             — detalhe da reserva
  garagem-reservas-lista.html.twig      — lista de reservas
  garagem-reservas-notificacoes.html.twig — página de notificações
  garagem-reserva-pdf.html.twig         — PDF do contrato
  garagem-encomendas-lista.html.twig    — lista de encomendas (cards)
  garagem-encomenda-detalhe.html.twig   — detalhe da encomenda
```

---

## Notificações (Message Module)

### Templates de Mensagem (13 no total)

| Template ID | Quando é enviado | Para quem |
|-------------|-----------------|-----------|
| `reserva_criada_proprietario` | Nova reserva | Proprietário |
| `reserva_criada_user` | Nova reserva | Arrendatário |
| `reserva_aprovada_user` | Aprovação | Arrendatário |
| `reserva_aguarda_pagamento_user` | Checkout iniciado | Arrendatário |
| `reserva_rejeitada_user` | Rejeição | Arrendatário |
| `reserva_paga_proprietario` | Pagamento confirmado | Proprietário |
| `reserva_paga_user` | Pagamento confirmado | Arrendatário |
| `reserva_cancelada_proprietario` | User cancela | Proprietário |
| `reserva_cancel_user_confirm` | User cancela | Arrendatário (confirmação) |
| `reserva_cancel_prop_user` | Proprietário cancela | Arrendatário |
| `reserva_cancel_prop_confirm` | Proprietário cancela | Proprietário (confirmação) |
| `reserva_expirada` | Cron — expirou | Arrendatário |
| `reserva_expirada_proprietario` | Cron — expirou | Proprietário |

### Argumentos das Mensagens
- `@garagem` — título da garagem
- `@motivo` — motivo de cancelamento/rejeição (string simples, vazia se não houver)
- `@reserva_id` — ID da reserva (usado para gerar link clicável nas notificações)

### NotificacaoService
O método `enviar()` guarda `@reserva_id` nos arguments para permitir link para a reserva no bloco de notificações e na página de notificações.

---

## Bloco de Notificações (NotificacoesBlock)

- Sino com badge de contagem de não lidas
- Dropdown azul com 5 notificações mais recentes
- **Cada notificação é clicável** → vai para `/reserva/{id}` se tiver `@reserva_id` nos arguments
- Link "Ver todas" → `/dashboard/notificacoes`
- Marcar como lidas: ao visitar `/dashboard/notificacoes` (guarda timestamp em `user.data`)

---

## Modais de Cancelamento/Rejeição

### Na lista de reservas (`garagem-reservas-lista.html.twig`)
- **Cancelar (proprietário):** modal com textarea de motivo (opcional) + mensagem legal
- **Rejeitar:** modal com textarea de motivo (opcional)
- Botão "Cancelar" do proprietário **não aparece** quando estado = `pendente`

### Na página da reserva (`garagem-reserva.html.twig`)
- **Cancelar (user):** modal com textarea + mensagem legal
- **Cancelar (proprietário):** modal com textarea + mensagem legal
- **Rejeitar:** modal com textarea (sem mensagem legal)
- Botão cancelar proprietário **não aparece** quando estado = `pendente`

### Mensagem legal (só nos modais de cancelar, não no rejeitar)
> "Este tipo de operação não dispensa procedimentos legais vigentes em contrato."

### Como o motivo é guardado
O motivo é passado como query param `?motivo=texto` na URL. O controller lê `$request->query->get('motivo')` e guarda na coluna `motivo` da tabela `garagem_reserva`.

O motivo aparece na página da reserva quando estado = `cancelado` ou `rejeitado`.

---

## Encomendas (`/dashboard/encomendas`)

### Lista
Cards clicáveis com: número da encomenda, garagem associada, data, total, badge de estado.

### Detalhe (`/dashboard/encomendas/{id}`)
- Reserva associada (garagem, datas início/fim, link para reserva)
- Tabela de itens com totais
- Informação de pagamento:
  - **Multibanco:** Entidade + Referência formatada (XXX XXX XXX) + Valor
    - `remote_id` está em **JSON**: `{"entity": "12537", "reference": "456003808", "requestId": "...", "expiryDate": "..."}`
  - **MB WAY:** mostra "MB WAY"
  - Estados traduzidos: `authorization` → "Aguarda pagamento", `completed` → "Pago", etc.
- Morada de faturação

---

## Integração Commerce

### CommerceOrderEventSubscriber
Escuta `OrderEvents::ORDER_PAID`:
1. Encontra a reserva com `commerce_order_id` = order ID
2. Muda estado para `pago`
3. Envia notificações

**Importante:** O módulo `commerce_ifthenpay` guarda a order explicitamente no `onNotify` quando o balanço é zero, para garantir que o evento `ORDER_PAID` dispara em contextos webhook.

### Gateway de Pagamento Ifthenpay (módulo custom)
Localização: `web/modules/contrib/commerce_ifthenpay/` (é um módulo modificado, não o original)

**Multibanco (`IfthenpayMultibanco.php`):**
- Plugin ID: `ifthenpay_multibanco`
- Usa **MB Key** + API dinâmica da Ifthenpay: `POST https://ifthenpay.com/api/multibanco/reference/init`
- `remote_id` guardado como JSON: `{"entity", "reference", "requestId", "expiryDate", "sandbox"}`
- Callback URL format: `https://dominio.com/payment/notify/multibanco?key=[ANTI_PHISHING_KEY]&reference=[REFERENCE]&amount=[AMOUNT]&entity=[ENTITY]&orderId=[ORDER_ID]`

**MB WAY (`IfthenpayMbWay.php`):**
- Plugin ID: `ifthenpay_mbway`
- Usa MB WAY Key `PND-105002`
- Callback: `https://dominio.com/payment/notify/mb_way?key=[ANTI_PHISHING_KEY]&requestId=[REQUEST_ID]&amount=[AMOUNT]&orderId=[ORDER_ID]`

**Credenciais da conta:**
- MB Key: `MMX-359564`
- Entidade Multibanco: `12537`
- MBWAY Key: `PND-105002`

---

## Segurança Implementada

- Proprietário não pode reservar a própria garagem
- Garagens não publicadas retornam 404
- Rate limiting: máx. 5 reservas pendentes/aprovadas por utilizador
- `notas` e `motivo` sanitizados com `strip_tags()` e limitados a 1000 chars
- Endpoint JSON verifica garagem publicada e tipo correto
- PDF só acessível quando estado = `pago` (exceto admins)

---

## Update Hooks

- `update_8001` — cria tabela `garagem_indisponibilidade`
- `update_8002` — substitui coluna `indefinido` por `renovacao_automatica`
- `update_8003` — atualiza templates de notificação com `@garagem`
- `update_8004` — adiciona coluna `motivo` à tabela `garagem_reserva`

---

## Validação do Formulário de Reserva

Em `ReservaForm.php::validateForm()`:
- Verifica datas preenchidas
- Para tipo `dia`: verifica que fim > início, e que `num_dias >= field_min_dias_reserva` (se definido na garagem)
- Para tipo `mes`/`ano`: calcula fim automaticamente com fix de overflow
- Verifica disponibilidade via `DisponibilidadeService`

---

## Calendário (Flatpickr)

- Datas bloqueadas vêm de `/garagem/{node}/disponibilidade` (JSON)
- `data_fim` usa `00:00:00` do dia de fim (exclusivo)
- JS subtrai 1ms ao `to` do range desativado — reserva 01→02 bloqueia só dia 1
- Tipo `dia`: modo range; tipo `mes`/`ano`: modo single

---

## Convenções de Código

- Todos os templates usam **Tailwind CSS** (classes do CDN core — sem purge/compile)
- Sem títulos `h2` nas páginas do módulo
- Botões: `px-4 py-2 text-sm font-bold rounded-full`
- Estilos de estado: `bg-yellow-100 text-yellow-700` (pendente), `bg-green-100 text-green-700` (pago), etc.
- Todas as rotas têm `no_cache: true`

---

## Comandos Úteis

```bash
# Cache clear
lando drush cr

# Executar update hooks
lando drush updb -y

# Importar config de notificações
lando drush cim --partial --source=modules/custom/garagem_reservas/config/install -y

# Executar testes
lando drush php-script web/modules/custom/garagem_reservas/test_reservas.php

# Ver logs
lando drush watchdog:show --count=20

# Ngrok (para testes de callback Ifthenpay)
ngrok http 32772  # porta HTTP do Lando
# Adicionar ao settings.php: $settings['trusted_host_patterns'][] = '^.*\.ngrok-free\.app$';
```
