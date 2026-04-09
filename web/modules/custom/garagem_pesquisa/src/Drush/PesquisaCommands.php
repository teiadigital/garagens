<?php

namespace Drupal\garagem_pesquisa\Drush;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;

class PesquisaCommands extends DrushCommands {

  #[CLI\Command(name: 'garagem:seed', aliases: ['g:seed'])]
  #[CLI\Help(description: 'Insere garagens de teste')]
  #[CLI\Option(name: 'count', description: 'Número de garagens a criar', suggestedValues: [5, 10, 20])]
  #[CLI\Option(name: 'user', description: 'Username do autor (ex: fabioabdias)')]
  public function seed(array $options = ['count' => 5, 'user' => NULL]): void {
    $count = (int) $options['count'];

    $localidades = [
      // Lisboa
      ['cidade' => 'Lisboa',            'distrito' => 'Lisboa',   'lat' => 38.7169, 'lng' => -9.1399,
       'moradas' => [['rua' => 'Rua Augusta, 45',                'cp' => '1100-053'], ['rua' => 'Avenida da Liberdade, 110', 'cp' => '1250-096'], ['rua' => 'Rua de Belém, 78', 'cp' => '1300-085']]],
      ['cidade' => 'Sintra',            'distrito' => 'Lisboa',   'lat' => 38.8029, 'lng' => -9.3817,
       'moradas' => [['rua' => 'Rua Visconde de Monserrate, 12', 'cp' => '2710-591'], ['rua' => 'Avenida Heliodoro Salgado, 5', 'cp' => '2710-530']]],
      ['cidade' => 'Cascais',           'distrito' => 'Lisboa',   'lat' => 38.6971, 'lng' => -9.4221,
       'moradas' => [['rua' => 'Rua Frederico Arouca, 33',       'cp' => '2750-353'], ['rua' => 'Avenida 25 de Abril, 18', 'cp' => '2750-511']]],
      ['cidade' => 'Almada',            'distrito' => 'Setúbal',  'lat' => 38.6790, 'lng' => -9.1574,
       'moradas' => [['rua' => 'Rua Capitão Leitão, 20',         'cp' => '2800-158'], ['rua' => 'Praça Gil Vicente, 3', 'cp' => '2800-285']]],
      ['cidade' => 'Torres Vedras',     'distrito' => 'Lisboa',   'lat' => 39.0922, 'lng' => -9.2596,
       'moradas' => [['rua' => 'Rua Paiva de Andrade, 8',        'cp' => '2560-291'], ['rua' => 'Avenida 25 de Abril, 44', 'cp' => '2560-300']]],
      // Porto e Norte
      ['cidade' => 'Porto',             'distrito' => 'Porto',    'lat' => 41.1579, 'lng' => -8.6291,
       'moradas' => [['rua' => 'Rua de Santa Catarina, 200',     'cp' => '4000-447'], ['rua' => 'Avenida dos Aliados, 50', 'cp' => '4000-067'], ['rua' => 'Rua das Flores, 15', 'cp' => '4050-262']]],
      ['cidade' => 'Vila Nova de Gaia', 'distrito' => 'Porto',    'lat' => 41.1333, 'lng' => -8.6167,
       'moradas' => [['rua' => 'Avenida da República, 320',      'cp' => '4430-193'], ['rua' => 'Rua do Calvário, 7', 'cp' => '4400-043']]],
      ['cidade' => 'Braga',             'distrito' => 'Braga',    'lat' => 41.5518, 'lng' => -8.4229,
       'moradas' => [['rua' => 'Rua do Souto, 30',               'cp' => '4700-419'], ['rua' => 'Avenida Central, 67', 'cp' => '4710-228'], ['rua' => 'Largo do Paço, 11', 'cp' => '4704-553']]],
      ['cidade' => 'Guimarães',         'distrito' => 'Braga',    'lat' => 41.4425, 'lng' => -8.2974,
       'moradas' => [['rua' => 'Rua de Santo António, 14',       'cp' => '4800-022'], ['rua' => 'Alameda Dr. Mariano Felgueiras, 9', 'cp' => '4810-272']]],
      ['cidade' => 'Viana do Castelo',  'distrito' => 'Viana do Castelo', 'lat' => 41.6931, 'lng' => -8.8330,
       'moradas' => [['rua' => 'Avenida dos Combatentes, 52',    'cp' => '4900-394'], ['rua' => 'Rua Manuel Espregueira, 8', 'cp' => '4900-316']]],
      ['cidade' => 'Vila Real',         'distrito' => 'Vila Real', 'lat' => 41.3005, 'lng' => -7.7457,
       'moradas' => [['rua' => 'Avenida Carvalho Araújo, 18',    'cp' => '5000-657'], ['rua' => 'Rua Direita, 40', 'cp' => '5000-558']]],
      ['cidade' => 'Bragança',          'distrito' => 'Bragança', 'lat' => 41.8061, 'lng' => -6.7589,
       'moradas' => [['rua' => 'Rua Almirante Reis, 22',         'cp' => '5300-043'], ['rua' => 'Avenida João da Cruz, 10', 'cp' => '5300-182']]],
      ['cidade' => 'Barcelos',          'distrito' => 'Braga',    'lat' => 41.5340, 'lng' => -8.6177,
       'moradas' => [['rua' => 'Rua D. António Barroso, 6',      'cp' => '4750-273'], ['rua' => 'Largo do Município, 3', 'cp' => '4750-439']]],
      // Centro
      ['cidade' => 'Coimbra',           'distrito' => 'Coimbra',  'lat' => 40.2033, 'lng' => -8.4103,
       'moradas' => [['rua' => 'Rua da Sofia, 55',               'cp' => '3000-395'], ['rua' => 'Avenida Emídio Navarro, 20', 'cp' => '3000-150'], ['rua' => 'Rua António Granjo, 14', 'cp' => '3000-033']]],
      ['cidade' => 'Aveiro',            'distrito' => 'Aveiro',   'lat' => 40.6443, 'lng' => -8.6455,
       'moradas' => [['rua' => 'Rua João Mendonça, 8',           'cp' => '3800-200'], ['rua' => 'Avenida Dr. Lourenço Peixinho, 44', 'cp' => '3800-159'], ['rua' => 'Rua do Batalhão de Caçadores, 12', 'cp' => '3810-057']]],
      ['cidade' => 'Viseu',             'distrito' => 'Viseu',    'lat' => 40.6566, 'lng' => -7.9122,
       'moradas' => [['rua' => 'Rua Augusto Hilário, 6',         'cp' => '3500-036'], ['rua' => 'Rua Direita, 48', 'cp' => '3500-146']]],
      ['cidade' => 'Leiria',            'distrito' => 'Leiria',   'lat' => 39.7436, 'lng' => -8.8071,
       'moradas' => [['rua' => 'Rua Barão de Viamonte, 22',      'cp' => '2400-138'], ['rua' => 'Rua Dr. Correia Mateus, 35', 'cp' => '2400-115']]],
      ['cidade' => 'Figueira da Foz',   'distrito' => 'Coimbra',  'lat' => 40.1511, 'lng' => -8.8585,
       'moradas' => [['rua' => 'Rua da República, 14',           'cp' => '3080-162'], ['rua' => 'Avenida 25 de Abril, 88', 'cp' => '3080-003']]],
      ['cidade' => 'Covilhã',           'distrito' => 'Castelo Branco', 'lat' => 40.2803, 'lng' => -7.5044,
       'moradas' => [['rua' => 'Rua Marquês de Ávila e Bolama, 5', 'cp' => '6200-240'], ['rua' => 'Avenida Cidade de Salamanca, 30', 'cp' => '6201-001']]],
      ['cidade' => 'Castelo Branco',    'distrito' => 'Castelo Branco', 'lat' => 39.8227, 'lng' => -7.4961,
       'moradas' => [['rua' => 'Avenida 1º de Maio, 15',         'cp' => '6000-082'], ['rua' => 'Rua da Piscina, 3', 'cp' => '6000-266']]],
      ['cidade' => 'Guarda',            'distrito' => 'Guarda',   'lat' => 40.5364, 'lng' => -7.2672,
       'moradas' => [['rua' => 'Rua Francisco de Passos, 9',     'cp' => '6300-680'], ['rua' => 'Praça Luís de Camões, 2', 'cp' => '6300-758']]],
      ['cidade' => 'Caldas da Rainha',  'distrito' => 'Leiria',   'lat' => 39.4010, 'lng' => -9.1337,
       'moradas' => [['rua' => 'Rua Engenheiro Duarte Pacheco, 16', 'cp' => '2500-169'], ['rua' => 'Praça da República, 1', 'cp' => '2500-192']]],
      ['cidade' => 'Tomar',             'distrito' => 'Santarém', 'lat' => 39.6014, 'lng' => -8.4122,
       'moradas' => [['rua' => 'Rua Serpa Pinto, 20',            'cp' => '2300-590'], ['rua' => 'Avenida Norton de Matos, 7', 'cp' => '2300-496']]],
      ['cidade' => 'Santarém',          'distrito' => 'Santarém', 'lat' => 39.2333, 'lng' => -8.6833,
       'moradas' => [['rua' => 'Rua Capelo e Ivens, 18',         'cp' => '2000-071'], ['rua' => 'Largo Cândido dos Reis, 5', 'cp' => '2000-033']]],
      // Alentejo
      ['cidade' => 'Évora',             'distrito' => 'Évora',    'lat' => 38.5707, 'lng' => -7.9097,
       'moradas' => [['rua' => 'Rua 5 de Outubro, 18',           'cp' => '7000-854'], ['rua' => 'Praça do Giraldo, 4', 'cp' => '7000-508']]],
      ['cidade' => 'Beja',              'distrito' => 'Beja',     'lat' => 38.0153, 'lng' => -7.8659,
       'moradas' => [['rua' => 'Rua Capitão João Francisco de Sousa, 12', 'cp' => '7800-437'], ['rua' => 'Praça da República, 3', 'cp' => '7800-442']]],
      ['cidade' => 'Portalegre',        'distrito' => 'Portalegre', 'lat' => 39.2967, 'lng' => -7.4284,
       'moradas' => [['rua' => 'Rua 19 de Junho, 8',             'cp' => '7300-110'], ['rua' => 'Avenida da Liberdade, 34', 'cp' => '7300-074']]],
      ['cidade' => 'Elvas',             'distrito' => 'Portalegre', 'lat' => 38.8800, 'lng' => -7.1641,
       'moradas' => [['rua' => 'Praça da República, 10',         'cp' => '7350-126'], ['rua' => 'Rua da Cadeia, 6', 'cp' => '7350-009']]],
      ['cidade' => 'Santiago do Cacém', 'distrito' => 'Setúbal',  'lat' => 38.0195, 'lng' => -8.6943,
       'moradas' => [['rua' => 'Rua Machado dos Santos, 14',     'cp' => '7540-124'], ['rua' => 'Avenida D. Nuno Álvares Pereira, 9', 'cp' => '7540-133']]],
      // Setúbal e Península
      ['cidade' => 'Setúbal',           'distrito' => 'Setúbal',  'lat' => 38.5244, 'lng' => -8.8882,
       'moradas' => [['rua' => 'Rua Álvaro Castelões, 12',       'cp' => '2900-139'], ['rua' => 'Avenida Luísa Todi, 250', 'cp' => '2900-461']]],
      ['cidade' => 'Barreiro',          'distrito' => 'Setúbal',  'lat' => 38.6606, 'lng' => -9.0707,
       'moradas' => [['rua' => 'Rua António José de Almeida, 5', 'cp' => '2830-001'], ['rua' => 'Avenida Bento Gonçalves, 18', 'cp' => '2830-141']]],
      ['cidade' => 'Sesimbra',          'distrito' => 'Setúbal',  'lat' => 38.4443, 'lng' => -9.1014,
       'moradas' => [['rua' => 'Rua Jorge Nunes, 3',             'cp' => '2970-628'], ['rua' => 'Avenida da Liberdade, 22', 'cp' => '2970-617']]],
      // Algarve
      ['cidade' => 'Faro',              'distrito' => 'Faro',     'lat' => 37.0193, 'lng' => -7.9304,
       'moradas' => [['rua' => 'Rua de Santo António, 25',       'cp' => '8000-283'], ['rua' => 'Avenida da República, 60', 'cp' => '8000-078']]],
      ['cidade' => 'Portimão',          'distrito' => 'Faro',     'lat' => 37.1359, 'lng' => -8.5376,
       'moradas' => [['rua' => 'Rua Júdice Biker, 10',           'cp' => '8500-511'], ['rua' => 'Avenida São João de Deus, 30', 'cp' => '8500-329']]],
      ['cidade' => 'Lagos',             'distrito' => 'Faro',     'lat' => 37.1017, 'lng' => -8.6751,
       'moradas' => [['rua' => 'Rua 25 de Abril, 14',            'cp' => '8600-763'], ['rua' => 'Rua de São Gonçalo de Lagos, 5', 'cp' => '8600-680']]],
      ['cidade' => 'Tavira',            'distrito' => 'Faro',     'lat' => 37.1284, 'lng' => -7.6506,
       'moradas' => [['rua' => 'Rua da Liberdade, 8',            'cp' => '8800-325'], ['rua' => 'Praça da República, 5', 'cp' => '8800-308']]],
      ['cidade' => 'Albufeira',         'distrito' => 'Faro',     'lat' => 37.0888, 'lng' => -8.2500,
       'moradas' => [['rua' => 'Rua 5 de Outubro, 20',           'cp' => '8200-109'], ['rua' => 'Avenida 25 de Abril, 6', 'cp' => '8200-385']]],
      ['cidade' => 'Loulé',             'distrito' => 'Faro',     'lat' => 37.1392, 'lng' => -8.0233,
       'moradas' => [['rua' => 'Rua 5 de Outubro, 3',            'cp' => '8100-752'], ['rua' => 'Rua Afonso III, 17', 'cp' => '8100-519']]],
      // Ilhas
      ['cidade' => 'Funchal',           'distrito' => 'Madeira',  'lat' => 32.6669, 'lng' => -16.9241,
       'moradas' => [['rua' => 'Avenida Arriaga, 12',            'cp' => '9000-064'], ['rua' => 'Rua do Aljube, 4', 'cp' => '9000-044']]],
      ['cidade' => 'Ponta Delgada',     'distrito' => 'Açores',   'lat' => 37.7412, 'lng' => -25.6756,
       'moradas' => [['rua' => 'Rua Marquês da Praia e Monforte, 22', 'cp' => '9500-050'], ['rua' => 'Avenida Infante D. Henrique, 8', 'cp' => '9500-155']]],
    ];

    // Resolve utilizador
    if ($options['user']) {
      $users = \Drupal::entityQuery('user')
        ->condition('name', $options['user'])
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
      if (!$users) {
        $this->io()->error("Utilizador '{$options['user']}' não encontrado.");
        return;
      }
      $uid = reset($users);
    }
    else {
      $uids = \Drupal::entityQuery('user')
        ->condition('status', 1)->condition('uid', 1, '>')
        ->accessCheck(FALSE)->range(0, 1)->execute();
      $uid = $uids ? reset($uids) : 1;
    }

    // IDs de imagens picsum
    $picsum_ids = [270, 271, 280, 281, 282, 390, 391, 392, 393, 431, 433, 435, 437, 439, 440];

    // Pisos disponíveis: 1-Alcatroado, 2-Cimentado, 29-Epóxi, 22-Fresagem, 3-Terra
    $pisos = [1, 2, 29, 22, 3];

    for ($i = 1; $i <= $count; $i++) {
      $loc    = $localidades[array_rand($localidades)];
      $morada = $loc['moradas'][array_rand($loc['moradas'])];
      $lat    = $loc['lat'] + (mt_rand(-500, 500) / 10000);
      $lng    = $loc['lng'] + (mt_rand(-500, 500) / 10000);

      // Preços — combinações aleatórias
      $combinacoes = [
        ['dia'], ['mes'], ['ano'],
        ['dia', 'mes'], ['dia', 'ano'], ['mes', 'ano'],
        ['dia', 'mes', 'ano'], ['dia', 'mes', 'ano'],
      ];
      $tipos_preco    = $combinacoes[array_rand($combinacoes)];
      $preco_dia_ativo = in_array('dia', $tipos_preco);
      $preco_mes_ativo = in_array('mes', $tipos_preco);
      $preco_ano_ativo = in_array('ano', $tipos_preco);
      $preco_dia = $preco_dia_ativo ? mt_rand(5, 30) + 0.99   : NULL;
      $preco_mes = $preco_mes_ativo ? mt_rand(50, 150) + 0.99 : NULL;
      $preco_ano = $preco_ano_ativo ? mt_rand(400, 1200) + 0.99 : NULL;

      // Campos condicionais — baseados em sim/nao aleatório
      $controlo_acesso      = (bool) mt_rand(0, 1) ? 'sim' : 'nao';
      $limitacoes_acesso    = (bool) mt_rand(0, 1) ? 'sim' : 'nao';
      $limitacoes_horario   = (bool) mt_rand(0, 1) ? 'sim' : 'nao';
      $seguranca            = (bool) mt_rand(0, 1) ? 'sim' : 'nao';
      $seguro               = (bool) mt_rand(0, 1) ? 'sim' : 'nao';
      $sistema_incendios    = (bool) mt_rand(0, 1) ? 'sim' : 'nao';
      $certificados         = (bool) mt_rand(0, 1) ? 'sim' : 'nao';

      // Recursos (multivalue — pode ter agua, luz, ambos ou nenhum)
      $recursos_possiveis = [[], ['agua'], ['luz'], ['agua', 'luz']];
      $recursos = $recursos_possiveis[array_rand($recursos_possiveis)];

      // Fotos
      $media_ids = [];
      $ids_usados = [];
      $dir = 'public://garagens_seed';
      \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

      for ($f = 0; $f < mt_rand(1, 4); $f++) {
        do { $pid = $picsum_ids[array_rand($picsum_ids)]; } while (in_array($pid, $ids_usados));
        $ids_usados[] = $pid;
        $uri      = "public://garagens_seed/garagem_seed_{$pid}.jpg";
        $existing = \Drupal::entityQuery('file')->condition('uri', $uri)->accessCheck(FALSE)->range(0, 1)->execute();
        if ($existing) {
          $file = File::load(reset($existing));
        }
        else {
          $data = @file_get_contents("https://picsum.photos/id/{$pid}/800/600");
          if (!$data) { $this->io()->warning("Não foi possível descarregar imagem pid=$pid"); continue; }
          $file = \Drupal::service('file.repository')->writeData($data, $uri, \Drupal\Core\File\FileExists::Replace);
          $file->setPermanent();
          $file->save();
        }
        $media = Media::create([
          'bundle' => 'image', 'uid' => $uid, 'status' => 1,
          'name'   => "Garagem seed {$i} - foto " . ($f + 1),
          'field_media_image' => ['target_id' => $file->id(), 'alt' => "Garagem {$i}"],
        ]);
        $media->save();
        $media_ids[] = ['target_id' => $media->id()];
      }

      // Paragraph area_de_armazem
      $paragraph = Paragraph::create([
        'type'             => 'area_de_armazem',
        'field_comprimento' => round(mt_rand(20, 80) / 10, 1),
        'field_largura'     => round(mt_rand(15, 50) / 10, 1),
        'field_altura'      => round(mt_rand(20, 40) / 10, 1),
      ]);
      $paragraph->save();

      // Nomes criativos baseados na localização
      $prefixos = [
        'Garagem no centro de', 'Box privada em', 'Espaço seguro em',
        'Garagem junto ao coração de', 'Box coberta em', 'Garagem tranquila em',
        'Estacionamento privado em', 'Garagem com portão automático em',
        'Box individual em', 'Garagem espaçosa em',
      ];
      $titulo = $prefixos[array_rand($prefixos)] . ' ' . $loc['cidade'];

      $descricoes = [
        "Garagem individual bem localizada no centro de {$loc['cidade']}, com acesso fácil a partir das principais vias da cidade. Espaço amplo, limpo e seco, ideal para guardar o seu veículo com toda a segurança. A poucos minutos de transportes públicos e comércio local.",
        "Excelente box privada situada numa zona residencial tranquila de {$loc['cidade']}. O espaço dispõe de boa altura livre, permitindo o acesso de SUVs e carrinhas. Portão com telecomando e iluminação interior incluída.",
        "Garagem coberta num dos melhores pontos de {$loc['cidade']}. Ideal para quem procura uma solução de estacionamento mensal ou anual sem as preocupações do parque público. Acesso direto da rua, sem rampas apertadas.",
        "Box em condomínio fechado em {$loc['cidade']}, com videovigilância 24h e porteiro automático. Espaço seco e ventilado, com piso em epóxi e tomada elétrica disponível. Perfeita para mota, carro ligeiro ou arrumos.",
        "Garagem ampla em zona privilegiada de {$loc['cidade']}. Fácil acesso pela {$morada['rua']}, com portão automático e lugar reservado. Ótima opção para quem trabalha ou estuda no centro e precisa de estacionamento garantido.",
        "Espaço de garagem muito bem conservado em {$loc['cidade']}, perto do centro histórico. Pavimento cimentado, porta basculante com telecomando e iluminação LED. Disponível para reservas de curta ou longa duração.",
        "Garagem fechada num edifício moderno de {$loc['cidade']}. O espaço tem câmara de segurança, acesso condominial e lugar numerado. Ideal para residentes ou visitantes frequentes da zona.",
        "Box privada junto a {$morada['rua']} em {$loc['cidade']}. Espaço com boa dimensão, portão automático e acesso pedonal separado. Uma solução prática e económica para o seu estacionamento diário.",
      ];
      $descricao = $descricoes[array_rand($descricoes)];

      $desc_exteriores = [
        'O acesso é feito diretamente pela rua, sem necessidade de percorrer rampas ou corredores. A entrada tem boa largura e permite manobras confortáveis mesmo com viaturas de maior porte.',
        'Entrada pela fachada do edifício com portão automático. A zona de acesso é bem iluminada e o passeio permite paragem breve para carga e descarga.',
        'Espaço exterior com balizas e marcações no chão que facilitam o estacionamento. A rua tem trânsito moderado e acesso desafogado.',
        'O exterior do espaço está inserido numa rua calma e bem iluminada. Existe espaço para aproximação e entrada do veículo sem dificuldade.',
      ];

      $servicos = [
        'Iluminação LED automática, tomada elétrica 220V, portão com telecomando extra disponível mediante pedido.',
        'Acesso 24 horas por dia, 7 dias por semana. Limpeza periódica incluída no valor da reserva.',
        'Portão automático com 2 telecomandos, iluminação interior e ponto de água na proximidade.',
        'Acesso controlado por código e app. Sistema de ventilação natural, piso tratado e marcações de segurança.',
        'Iluminação automática por sensor de movimento. Acesso pedonal independente do portão de veículos.',
      ];

      $desc_controlo_acesso_opts = [
        'Portão basculante com telecomando de longo alcance. Código de acesso pedonal disponível após reserva confirmada.',
        'Portão automático de correr com motor silencioso. Acesso por app e telecomando físico incluído.',
        'Sistema de portão com código alfanumérico renovado a cada reserva. Acesso imediato após pagamento.',
      ];
      $desc_limitacoes_opts = [
        'Altura máxima de entrada: 2,10 m. Não adequado para veículos com tejadilho elevado ou autocaravanas.',
        'Largura de passagem: 2,40 m. Adequado para a maioria dos veículos ligeiros. Não permitida entrada de veículos pesados ou comerciais.',
        'Apenas veículos ligeiros de passageiros. Peso máximo por eixo indicado na entrada.',
      ];
      $desc_horario_opts = [
        'Acesso permitido entre as 6h00 e as 23h00. Fora deste horário contactar o proprietário com 24h de antecedência.',
        'Acesso livre entre as 7h00 e as 22h00. Para acessos nocturnos, disponível mediante acordo prévio.',
      ];
      $desc_seguranca_opts = [
        'Câmaras de videovigilância a cobrir a entrada e o interior da garagem, com gravação contínua de 15 dias.',
        'Sistema de alarme com deteção de movimento e ligação a central de segurança privada.',
        'Câmaras HD no exterior e interior, monitorizadas pelo proprietário em tempo real via smartphone.',
      ];
      $desc_seguros_opts = [
        'Seguro de responsabilidade civil do proprietário válido para danos causados no imóvel durante o período de reserva.',
        'Seguro multirriscos do edifício cobre danos estruturais. Recomendamos seguro próprio do veículo para cobertura total.',
      ];
      $desc_incendios_opts = [
        'Detetores de fumo instalados em conformidade com a legislação vigente. Extintor de pó químico em local visível.',
        'Sistema automático de deteção de incêndio com sinalização sonora e visual. Revisão anual certificada.',
      ];
      $desc_cert_opts = [
        'Certificado energético classe B válido. Licença de utilização emitida pela Câmara Municipal em dia.',
        'Certificado de uso e ocupação atualizado. Edifício com inspeção técnica aprovada.',
      ];

      $values = [
        'type'   => 'armazem',
        'title'  => $titulo,
        'status' => 1,
        'uid'    => $uid,
        // Campos base
        'field_titulo'                   => $titulo,
        'field_estado'                   => 3,
        'field_espaco'                   => mt_rand(0, 1) ? 'fechado' : 'comum',
        'field_descricao'                => $descricao,
        'field_descricao_espaco_exterior' => $desc_exteriores[array_rand($desc_exteriores)],
        'field_descreva_servicos_incluido' => $servicos[array_rand($servicos)],
        'field_peso_maximo_piso'         => mt_rand(1000, 5000),
        'field_min_dias_reserva'         => $preco_dia_ativo ? mt_rand(1, 7) : NULL,
        'field_renovacao_automatica'     => (bool) mt_rand(0, 1),
        // Preços
        'field_preco_dia'                => $preco_dia,
        'field_preco_mes'                => $preco_mes,
        'field_preco_ano'                => $preco_ano,
        'field_preco_dia_ativo'          => $preco_dia_ativo,
        'field_preco_mes_ativo'          => $preco_mes_ativo,
        'field_preco_ano_ativo'          => $preco_ano_ativo,
        // Campos sim/nao
        'field_controlo_acesso'          => $controlo_acesso,
        'field_limitacoes_de_acesso'     => $limitacoes_acesso,
        'field_limitacoes_horario_acesso' => $limitacoes_horario,
        'field_seguranca'                => $seguranca,
        'field_seguro'                   => $seguro,
        'field_sistema_detecao_incendios' => $sistema_incendios,
        'field_certificados'             => $certificados,
        'field_licenca_de_utilizacao'    => mt_rand(0, 1) ? 'sim' : 'nao',
        // Campos condicionais — só preenchidos se o dependente = sim
        'field_desc_controlo_acesso'     => $controlo_acesso === 'sim' ? $desc_controlo_acesso_opts[array_rand($desc_controlo_acesso_opts)] : NULL,
        'field_desc_limitacoes_acesso'   => $limitacoes_acesso === 'sim' ? $desc_limitacoes_opts[array_rand($desc_limitacoes_opts)] : NULL,
        'field_desc_lim_horario_acesso'  => $limitacoes_horario === 'sim' ? $desc_horario_opts[array_rand($desc_horario_opts)] : NULL,
        'field_desc_seguranca'           => $seguranca === 'sim' ? $desc_seguranca_opts[array_rand($desc_seguranca_opts)] : NULL,
        'field_desc_seguros'             => $seguro === 'sim' ? $desc_seguros_opts[array_rand($desc_seguros_opts)] : NULL,
        'field_desc_sistema_det_incendios' => $sistema_incendios === 'sim' ? $desc_incendios_opts[array_rand($desc_incendios_opts)] : NULL,
        'field_identifique_os_certificado' => $certificados === 'sim' ? $desc_cert_opts[array_rand($desc_cert_opts)] : NULL,
        // Recursos, piso, área, fotos
        'field_recursos'                 => array_map(fn($r) => ['value' => $r], $recursos),
        'field_piso'                     => [['target_id' => $pisos[array_rand($pisos)]]],
        'field_area_individual'          => [['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()]],
        'field_fotos'                    => $media_ids,
        // Localização
        'field_localidade'               => [
          'country_code'        => 'PT',
          'administrative_area' => $loc['distrito'],
          'locality'            => $loc['cidade'],
          'postal_code'         => $morada['cp'],
          'address_line1'       => $morada['rua'],
        ],
        'field_geo_coordenadas'          => "POINT($lng $lat)",
        'field_coordenadas'              => "$lat,$lng",
      ];

      $node = Node::create($values);
      $node->save();

      $precos_str = implode('+', $tipos_preco);
      $this->io()->writeln("✓ {$node->getTitle()} — {$loc['cidade']} [{$precos_str}] — " . count($media_ids) . " foto(s) (nid {$node->id()})");
    }

    $this->io()->success("$count garagens criadas.");
  }

}
