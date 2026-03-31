# Garagem Reservas

Módulo custom de gestão de reservas de garagens com integração Drupal Commerce.

## Funcionalidades

- Criação de reservas com datas/horas de início e fim (ou tempo indefinido)
- Cálculo automático de preço (hora/dia/mês) com base nas tarifas da garagem
- Fluxo de aprovação: pendente → aprovado → pago
- Expiração automática de reservas não pagas (via cron)
- Notificações por email e na plataforma
- Geração de PDF para proprietários
- Verificação de disponibilidade em tempo real

## Campos necessários no Content Type Garagem

Adicionar manualmente os seguintes campos:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| field_preco_hora_ativo | Boolean | Ativar preço por hora |
| field_preco_hora | Decimal | Preço por hora (€) |
| field_preco_dia_ativo | Boolean | Ativar preço por dia |
| field_preco_dia | Decimal | Preço por dia (€) |
| field_preco_mes_ativo | Boolean | Ativar preço por mês |
| field_preco_mes | Decimal | Preço por mês (€) |

## Estados das Reservas

- `pendente` — aguarda aprovação do proprietário
- `aprovado` — aprovado, aguarda pagamento
- `pago` — pagamento confirmado, reserva ativa
- `rejeitado` — rejeitado pelo proprietário
- `cancelado` — cancelado pelo utilizador
- `expirado` — prazo de pagamento ultrapassado

## Definições Globais

Configurar em **Admin → Configuration → Garagens → Definições**:

- Prazo de pagamento após aprovação (horas)
- Taxa fixa da plataforma (€)
- Percentagem da plataforma (%)

## TODO

- [ ] Integração completa com Commerce (pagamentos Multibanco/MB Way)
- [ ] Integração com biblioteca PDF (dompdf)
- [ ] Calendário visual de disponibilidade
- [ ] Campos adicionais no PDF
- [ ] Pagamentos recorrentes para reservas indefinidas
