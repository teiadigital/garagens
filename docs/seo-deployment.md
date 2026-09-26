# Aplicação das correções SEO

As alterações no repositório não modificam a base de dados ou a CDN de produção.
O anúncio `/pt/garagens/pt26080001` respondeu HTTP 403 em 26/09/2026,
com cabeçalhos do Drupal. Não foi possível determinar, sem acesso à base de
produção, se o anúncio está publicado ou se os registos de acesso estão desatualizados.
Não publicar um anúncio privado só para obter HTTP 200.

## Aplicar no ambiente Drupal de destino

Depois de disponibilizar o código, executar a partir da raiz do projeto:

```sh
vendor/bin/drush --uri=https://garagens.pt updatedb -y
vendor/bin/drush --uri=https://garagens.pt php:eval 'node_access_rebuild();'
vendor/bin/drush --uri=https://garagens.pt cache:rebuild
vendor/bin/drush --uri=https://garagens.pt php:eval '\Drupal::service("simple_sitemap.generator")->rebuildQueue()->generate();'
```

A atualização `garagem_pesquisa_update_10001` aplica apenas as configurações
relevantes. Evitar uma importação integral: existem duas exportações distintas,
em `config/sync` e `web/config/sync`. A geração de sitemaps pode precisar de
execuções adicionais do cron até esvaziar a fila. Limpar também a cache da CDN.
As regras Apache pressupõem que o proxy HTTPS define `X-Forwarded-Proto`
corretamente. Se o servidor não usar `.htaccess`, configurar as mesmas regras
na CDN/servidor: HTTP e www devem ir diretamente para HTTPS sem www, preservando
caminho e parâmetros. Preservar `.htaccess` e `robots.txt` em atualizações do scaffold.

## Verificar após aplicação

- Pedidos GET anónimos à homepage, listagem, FAQ e dois anúncios publicados: 200.
- Confirmar que rascunhos e anúncios privados continuam inacessíveis.
- Confirmar 301 de HTTP/www para HTTPS sem www, preservando a query string e sem ciclos.
- Confirmar um title, uma description e um H1 nas páginas públicas.
- Confirmar canonical com alias e idioma da página, sem www.
- Confirmar noindex, follow nas pesquisas filtradas e listagens vazias.
- Confirmar `/pt/garagens` no sitemap e ausência de anúncios privados.
- Validar o JSON-LD da homepage e executar o teste de URL publicado no Search Console.

## Itens que dependem dos conteúdos e de produção

Se um anúncio publicado continuar com 403, verificar estado da tradução, registos
`node_access`, módulos de acesso e logs do servidor/CDN. Não usar exceções por User-Agent.
A correção do erro só fica confirmada com resposta pública 200 e teste do Search Console.

Páginas por localidade, novos aliases com redirecionamentos, schema de FAQs e
breadcrumbs requerem inventário dos conteúdos e URLs ativos. Não foram gerados
conteúdos locais, perguntas, preços, avaliações ou URLs fictícias. Os aliases atuais
foram preservados. Rever também alt text das fotografias nos conteúdos publicados.
