<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? '';

// Rotas públicas
if ($action === 'login')  { actionLogin(); exit; }
if ($action === 'logout') { actionLogout(); exit; }

// Rotas protegidas
$me = requireAuth();

match(true) {
    $action === 'me'                  => json_response(['success'=>true,'usuario'=>$me]),
    $action === 'dashboard'           => actionDashboard($me),
    $action === 'estoque'             => actionEstoque($me),
    $action === 'historico'           => actionHistorico($me),
    $action === 'insumos'             => actionInsumos(),
    $action === 'insumo_criar'        => actionInsumoCriar($me),
    $action === 'insumo_editar'       => actionInsumoEditar($me),
    $action === 'insumo_toggle'       => actionInsumoToggle($me),
    $action === 'responsaveis'        => actionResponsaveis(),
    $action === 'setores'             => actionSetores(),
    $action === 'entrada'             => actionMovimentacao('ENTRADA', $me),
    $action === 'saida'               => actionMovimentacao('SAIDA', $me),
    $action === 'inventario_abrir'    => actionInventarioAbrir($me),
    $action === 'inventario_salvar'   => actionInventarioSalvar($me),
    $action === 'inventario_finalizar'=> actionInventarioFinalizar($me),
    $action === 'inventario_cancelar' => actionInventarioCancelar($me),
    $action === 'inventario_aberto'   => actionInventarioAberto(),
    $action === 'inventarios'         => actionInventarioListar($me),
    $action === 'inventario_detalhe'  => actionInventarioDetalhe($me),
    $action === 'inventario_excluir'  => actionInventarioExcluir($me),
    $action === 'relatorio'           => actionRelatorio($me),
    $action === 'usuarios'            => actionUsuarios($me),
    $action === 'usuario_criar'       => actionUsuarioCriar($me),
    $action === 'usuario_editar'      => actionUsuarioEditar($me),
    $action === 'usuario_excluir'     => actionUsuarioExcluir($me),
    $action === 'permissoes_salvar'   => actionPermissoesSalvar($me),
    $action === 'meu_perfil'          => actionMeuPerfil($me),
    $action === 'alterar_senha'       => actionAlterarSenha($me),
    $action === 'chamado_info'          => actionChamadoInfo($me),
    default                           => json_response(['success'=>false,'message'=>'Ação inválida'],404)
};

// ============================================
// AUTH
// ============================================
function actionLogin(): void {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($b['email'] ?? '');
    $senha = $b['senha'] ?? '';

    if (!$email || !$senha) json_response(['success'=>false,'message'=>'Preencha e-mail e senha.'], 422);

    $stmt = db()->prepare("SELECT u.id, u.nome, u.email, u.senha_hash, u.ativo, p.nome AS perfil
        FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.email = :e");
    $stmt->execute([':e' => $email]);
    $u = $stmt->fetch();

    if (!$u || !$u['ativo'] || !password_verify($senha, $u['senha_hash'])) {
        json_response(['success'=>false,'message'=>'E-mail ou senha incorretos.'], 401);
    }

    // Cria token
    $token    = bin2hex(random_bytes(32));
    $expiraEm = date('Y-m-d H:i:s', strtotime('+'.SESSION_HOURS.' hours'));

    db()->prepare("INSERT INTO sessoes (usuario_id, token, expira_em) VALUES (:uid,:tok,:exp)")
        ->execute([':uid'=>$u['id'],':tok'=>$token,':exp'=>$expiraEm]);

    db()->prepare("UPDATE usuarios SET ultimo_login=NOW() WHERE id=:id")->execute([':id'=>$u['id']]);

    // Carrega permissões
    $perm = db()->prepare("SELECT * FROM permissoes WHERE usuario_id=:id");
    $perm->execute([':id'=>$u['id']]);
    $perms = $perm->fetch() ?: [];

    json_response(['success'=>true,'token'=>$token,'expira_em'=>SESSION_HOURS,
        'usuario'=>['id'=>$u['id'],'nome'=>$u['nome'],'email'=>$u['email'],'perfil'=>$u['perfil']],
        'permissoes'=>$perms
    ]);
}

function actionLogout(): void {
    $token = getToken();
    if ($token) db()->prepare("DELETE FROM sessoes WHERE token=:t")->execute([':t'=>$token]);
    json_response(['success'=>true,'message'=>'Sessão encerrada.']);
}

// ============================================
// DASHBOARD
// ============================================
function actionDashboard(array $me): void {
    $pdo = db();
    $estoque = $pdo->query("
        SELECT i.id,i.nome,i.estoque_minimo,i.unidade,
          COALESCE(SUM(CASE m.tipo WHEN 'ENTRADA' THEN m.quantidade ELSE -m.quantidade END),0) AS estoque_atual
        FROM insumos i LEFT JOIN movimentacoes m ON m.insumo_id=i.id
        WHERE i.ativo=1 GROUP BY i.id ORDER BY i.nome")->fetchAll();

    $total=0; $criticos=0; $zerados=0;
    foreach ($estoque as &$r) {
        $r['estoque_atual']=(int)$r['estoque_atual'];
        $r['status']=statusEstoque($r['estoque_atual'],(int)$r['estoque_minimo']);
        $total+=$r['estoque_atual'];
        if($r['status']==='critico')$criticos++;
        if($r['status']==='zerado')$zerados++;
    } unset($r);

    $ranking=$pdo->query("SELECT i.nome,SUM(m.quantidade) AS total_saidas FROM movimentacoes m
        JOIN insumos i ON i.id=m.insumo_id WHERE m.tipo='SAIDA' AND m.criado_em>=DATE_SUB(NOW(),INTERVAL 30 DAY)
        GROUP BY i.id ORDER BY total_saidas DESC LIMIT 5")->fetchAll();

    $grafico=$pdo->query("SELECT DATE(criado_em) AS dia,tipo,SUM(quantidade) AS total FROM movimentacoes
        WHERE criado_em>=DATE_SUB(NOW(),INTERVAL 14 DAY) GROUP BY DATE(criado_em),tipo ORDER BY dia")->fetchAll();

    $ultimas=$pdo->query("SELECT m.id,m.tipo,m.quantidade,m.referencia,m.descricao,
        DATE_FORMAT(m.criado_em,'%d/%m/%Y %H:%i') AS criado_em,
        i.nome AS insumo, r.nome AS responsavel, s.nome AS setor
        FROM movimentacoes m JOIN insumos i ON i.id=m.insumo_id
        JOIN responsaveis r ON r.id=m.responsavel_id
        LEFT JOIN setores s ON s.id=m.setor_id ORDER BY m.criado_em DESC LIMIT 5")->fetchAll();

    json_response(['success'=>true,
        'metricas'=>['total_estoque'=>$total,'total_criticos'=>$criticos,'total_zerados'=>$zerados,'total_insumos'=>count($estoque)],
        'estoque'=>$estoque,'ranking'=>$ranking,'grafico'=>$grafico,'ultimas_movimentacoes'=>$ultimas
    ]);
}

// ============================================
// ESTOQUE
// ============================================
function actionEstoque(array $me): void {
    requirePerm($me,'p_estoque');
    $rows=db()->query("SELECT i.id,i.nome,i.estoque_minimo,i.unidade,
        COALESCE(SUM(CASE m.tipo WHEN 'ENTRADA' THEN m.quantidade ELSE -m.quantidade END),0) AS estoque_atual
        FROM insumos i LEFT JOIN movimentacoes m ON m.insumo_id=i.id
        WHERE i.ativo=1 GROUP BY i.id ORDER BY i.nome")->fetchAll();
    $result=array_map(function($r){
        $r['estoque_atual']=(int)$r['estoque_atual'];
        $r['status']=statusEstoque($r['estoque_atual'],(int)$r['estoque_minimo']);
        return $r;
    },$rows);
    json_response(['success'=>true,'data'=>$result]);
}

// ============================================
// HISTORICO
// ============================================
function actionHistorico(array $me): void {
    requirePerm($me,'p_historico');
    $pdo=$pdo=db();
    $page=max(1,(int)($_GET['page']??1));
    $limit=min(100,max(10,(int)($_GET['limit']??20)));
    $offset=($page-1)*$limit;
    $tipo=$_GET['tipo']??''; $insumo=$_GET['insumo']??'';
    $ini=$_GET['data_ini']??''; $fim=$_GET['data_fim']??'';

    $where=['1=1']; $params=[];
    if(in_array($tipo,['ENTRADA','SAIDA'])){$where[]='m.tipo=:tipo';$params[':tipo']=$tipo;}
    if($insumo!==''){$where[]='i.nome LIKE :insumo';$params[':insumo']='%'.$insumo.'%';}
    if($ini!==''){$where[]='DATE(m.criado_em)>=:ini';$params[':ini']=$ini;}
    if($fim!==''){$where[]='DATE(m.criado_em)<=:fim';$params[':fim']=$fim;}
    $wh=implode(' AND ',$where);

    $cnt=$pdo->prepare("SELECT COUNT(*) FROM movimentacoes m JOIN insumos i ON i.id=m.insumo_id WHERE $wh");
    $cnt->execute($params); $total=(int)$cnt->fetchColumn();

    $stmt=$pdo->prepare("SELECT m.id,m.tipo,m.quantidade,m.referencia,m.descricao,
        DATE_FORMAT(m.criado_em,'%d/%m/%Y %H:%i') AS criado_em,
        i.nome AS insumo, r.nome AS responsavel, s.nome AS setor, u.nome AS usuario
        FROM movimentacoes m JOIN insumos i ON i.id=m.insumo_id
        JOIN responsaveis r ON r.id=m.responsavel_id
        LEFT JOIN setores s ON s.id=m.setor_id
        LEFT JOIN usuarios u ON u.id=m.usuario_id
        WHERE $wh ORDER BY m.criado_em DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
    $stmt->bindValue(':off',$offset,PDO::PARAM_INT);
    foreach($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();

    json_response(['success'=>true,'data'=>$stmt->fetchAll(),'total'=>$total,
        'page'=>$page,'pages'=>(int)ceil($total/$limit),'limit'=>$limit]);
}

// ============================================
// RELATÓRIO
// ============================================
function actionRelatorio(array $me): void {
    requirePerm($me,'p_relatorio');
    $pdo=db();
    $tipo   = $_GET['tipo']     ?? 'movimentacoes';
    $ini    = $_GET['data_ini'] ?? date('Y-m-01');
    $fim    = $_GET['data_fim'] ?? date('Y-m-d');
    $fmt    = $_GET['formato']  ?? 'json';

    if($tipo === 'movimentacoes') {
        $stmt=$pdo->prepare("SELECT m.tipo, i.nome AS insumo, m.quantidade, r.nome AS responsavel,
            s.nome AS setor, m.referencia, m.descricao,
            DATE_FORMAT(m.criado_em,'%d/%m/%Y %H:%i') AS data_hora
            FROM movimentacoes m JOIN insumos i ON i.id=m.insumo_id
            JOIN responsaveis r ON r.id=m.responsavel_id
            LEFT JOIN setores s ON s.id=m.setor_id
            WHERE DATE(m.criado_em) BETWEEN :ini AND :fim
            ORDER BY m.criado_em DESC");
        $stmt->execute([':ini'=>$ini,':fim'=>$fim]);
        $rows=$stmt->fetchAll();

        $totEntrada=0; $totSaida=0;
        foreach($rows as $r){ if($r['tipo']==='ENTRADA') $totEntrada+=$r['quantidade']; else $totSaida+=$r['quantidade']; }

        json_response(['success'=>true,'tipo'=>$tipo,'periodo'=>['ini'=>$ini,'fim'=>$fim],
            'resumo'=>['total_entradas'=>$totEntrada,'total_saidas'=>$totSaida,'total_registros'=>count($rows)],
            'data'=>$rows]);
    }

    if($tipo === 'estoque_atual') {
        $rows=$pdo->query("SELECT i.nome,i.unidade,i.estoque_minimo,
            COALESCE(SUM(CASE m.tipo WHEN 'ENTRADA' THEN m.quantidade ELSE -m.quantidade END),0) AS estoque_atual
            FROM insumos i LEFT JOIN movimentacoes m ON m.insumo_id=i.id
            WHERE i.ativo=1 GROUP BY i.id ORDER BY i.nome")->fetchAll();
        foreach($rows as &$r){ $r['status']=statusEstoque((int)$r['estoque_atual'],(int)$r['estoque_minimo']); } unset($r);
        json_response(['success'=>true,'tipo'=>$tipo,'gerado_em'=>date('d/m/Y H:i'),'data'=>$rows]);
    }

    if($tipo === 'consumo_por_insumo') {
        $stmt=$pdo->prepare("SELECT i.nome AS insumo,
            SUM(CASE WHEN m.tipo='SAIDA' THEN m.quantidade ELSE 0 END) AS total_saidas,
            SUM(CASE WHEN m.tipo='ENTRADA' THEN m.quantidade ELSE 0 END) AS total_entradas
            FROM movimentacoes m JOIN insumos i ON i.id=m.insumo_id
            WHERE DATE(m.criado_em) BETWEEN :ini AND :fim
            GROUP BY i.id ORDER BY total_saidas DESC");
        $stmt->execute([':ini'=>$ini,':fim'=>$fim]);
        json_response(['success'=>true,'tipo'=>$tipo,'periodo'=>['ini'=>$ini,'fim'=>$fim],'data'=>$stmt->fetchAll()]);
    }

    if($tipo === 'inventarios') {
        requirePerm($me,'p_inv_editar');
        $stmt=$pdo->prepare("SELECT i.id, i.status,
            DATE_FORMAT(i.aberto_em,'%d/%m/%Y %H:%i') AS aberto_em,
            DATE_FORMAT(i.finalizado_em,'%d/%m/%Y %H:%i') AS finalizado_em,
            r.nome AS responsavel, i.observacao,
            (SELECT COUNT(*) FROM inventario_itens ii WHERE ii.inventario_id=i.id AND ii.diferenca<>0 AND ii.diferenca IS NOT NULL) AS divergencias
            FROM inventarios i JOIN responsaveis r ON r.id=i.responsavel_id
            WHERE DATE(i.aberto_em) BETWEEN :ini AND :fim
            ORDER BY i.aberto_em DESC");
        $stmt->execute([':ini'=>$ini,':fim'=>$fim]);
        json_response(['success'=>true,'tipo'=>$tipo,'periodo'=>['ini'=>$ini,'fim'=>$fim],'data'=>$stmt->fetchAll()]);
    }

    json_response(['success'=>false,'message'=>'Tipo de relatório inválido.'],422);
}

// ============================================
// INSUMOS / RESPONSÁVEIS / SETORES
// ============================================
function actionInsumos(): void {
    // Retorna lista básica para selects (cadastro de entrada/saída)
    $full = isset($_GET['full']) && $_GET['full'] === '1';
    if ($full) {
        // Dados completos para o painel de estoque
        $rows = db()->query("
            SELECT
                i.id, i.nome, i.estoque_minimo, i.unidade, i.ativo,
                DATE_FORMAT(i.criado_em,'%d/%m/%Y') AS criado_em,
                COALESCE(SUM(
                    CASE m.tipo WHEN 'ENTRADA' THEN m.quantidade ELSE -m.quantidade END
                ),0) AS estoque_atual,
                (SELECT COUNT(*) FROM movimentacoes mv WHERE mv.insumo_id = i.id AND mv.tipo='ENTRADA') AS total_entradas,
                (SELECT COUNT(*) FROM movimentacoes mv WHERE mv.insumo_id = i.id AND mv.tipo='SAIDA')   AS total_saidas,
                (SELECT DATE_FORMAT(MAX(mv2.criado_em),'%d/%m/%Y %H:%i') FROM movimentacoes mv2 WHERE mv2.insumo_id = i.id) AS ultima_mov
            FROM insumos i
            LEFT JOIN movimentacoes m ON m.insumo_id = i.id
            GROUP BY i.id
            ORDER BY i.nome
        ")->fetchAll();
        $result = array_map(function($r) {
            $r['estoque_atual'] = (int)$r['estoque_atual'];
            $r['status']        = statusEstoque($r['estoque_atual'], (int)$r['estoque_minimo']);
            return $r;
        }, $rows);
        json_response(['success'=>true,'data'=>$result]);
    } else {
        $rows = db()->query("SELECT id,nome,unidade FROM insumos WHERE ativo=1 ORDER BY nome")->fetchAll();
        json_response(['success'=>true,'data'=>$rows]);
    }
}

function actionInsumoCriar(array $me): void {
    requirePerm($me, 'p_inv_editar');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b    = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome = clean($b['nome'] ?? '');
    $uni  = clean($b['unidade'] ?? 'un');
    $min  = max(0, (int)($b['estoque_minimo'] ?? 0));
    $qtd  = max(0, (int)($b['qtd_inicial'] ?? 0));

    if (!$nome) json_response(['success'=>false,'message'=>'Nome do insumo é obrigatório.'], 422);

    $pdo = db();
    try {
        $pdo->prepare("INSERT INTO insumos (nome, unidade, estoque_minimo) VALUES (:n,:u,:m)")
            ->execute([':n'=>$nome, ':u'=>$uni, ':m'=>$min]);
        $iid = (int)$pdo->lastInsertId();

        // Lança entrada inicial se informada
        if ($qtd > 0) {
            $resp = $pdo->query("SELECT id FROM responsaveis LIMIT 1")->fetchColumn();
            $pdo->prepare("INSERT INTO movimentacoes (tipo,insumo_id,responsavel_id,usuario_id,quantidade,descricao,referencia)
                VALUES ('ENTRADA',:ins,:resp,:uid,:qtd,'Estoque inicial','INICIAL')")
                ->execute([':ins'=>$iid,':resp'=>$resp,':uid'=>$me['id'],':qtd'=>$qtd]);
        }

        json_response(['success'=>true,'message'=>'Insumo cadastrado com sucesso!','id'=>$iid]);
    } catch (\PDOException $e) {
        $msg = str_contains($e->getMessage(),'Duplicate') ? 'Já existe um insumo com esse nome.' : $e->getMessage();
        json_response(['success'=>false,'message'=>$msg], 422);
    }
}

function actionInsumoEditar(array $me): void {
    requirePerm($me, 'p_inv_editar');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b    = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($b['id'] ?? 0);
    $nome = clean($b['nome'] ?? '');
    $uni  = clean($b['unidade'] ?? 'un');
    $min  = max(0, (int)($b['estoque_minimo'] ?? 0));
    $ativo = (int)($b['ativo'] ?? 1);

    if (!$id || !$nome) json_response(['success'=>false,'message'=>'Dados inválidos.'], 422);

    try {
        db()->prepare("UPDATE insumos SET nome=:n, unidade=:u, estoque_minimo=:m, ativo=:a WHERE id=:id")
            ->execute([':n'=>$nome,':u'=>$uni,':m'=>$min,':a'=>$ativo,':id'=>$id]);
        json_response(['success'=>true,'message'=>'Insumo atualizado!']);
    } catch (\PDOException $e) {
        $msg = str_contains($e->getMessage(),'Duplicate') ? 'Já existe um insumo com esse nome.' : $e->getMessage();
        json_response(['success'=>false,'message'=>$msg], 422);
    }
}

function actionInsumoToggle(array $me): void {
    requirePerm($me, 'p_inv_editar');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id'] ?? 0);
    if (!$id) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $atual = (int)db()->prepare("SELECT ativo FROM insumos WHERE id=:id")->execute([':id'=>$id]) ? db()->query("SELECT ativo FROM insumos WHERE id=$id")->fetchColumn() : 1;
    $novo  = $atual ? 0 : 1;
    db()->prepare("UPDATE insumos SET ativo=:a WHERE id=:id")->execute([':a'=>$novo,':id'=>$id]);
    json_response(['success'=>true,'message'=> $novo ? 'Insumo reativado.' : 'Insumo desativado.','ativo'=>$novo]);
}
function actionResponsaveis(): void {
    // Retorna usuários do sistema (vindos do GLPI via SSO)
    $rows=db()->query("SELECT id,nome FROM usuarios WHERE ativo=1 ORDER BY nome")->fetchAll();
    json_response(['success'=>true,'data'=>$rows]);
}
function actionSetores(): void {
    $iid=(int)($_GET['insumo_id']??0);
    if($iid>0){
        $ins=db()->prepare("SELECT nome FROM insumos WHERE id=:id AND ativo=1");
        $ins->execute([':id'=>$iid]);
        $nome=$ins->fetchColumn();
        $mapa=['Cupom'=>'Cupom','Pulseira 25x280'=>'Pulseira'];
        $tipo=$mapa[$nome]??'Etiqueta';
        $stmt=db()->prepare("SELECT id,nome FROM setores WHERE tipo_insumo=:t AND ativo=1 ORDER BY nome");
        $stmt->execute([':t'=>$tipo]);
        $rows=$stmt->fetchAll();
    } else {
        $rows=db()->query("SELECT id,nome,tipo_insumo FROM setores WHERE ativo=1 ORDER BY tipo_insumo,nome")->fetchAll();
    }
    json_response(['success'=>true,'data'=>$rows]);
}

// ============================================
// MOVIMENTAÇÃO
// ============================================
function actionMovimentacao(string $tipo, array $me): void {
    $perm = $tipo==='ENTRADA' ? 'p_entrada' : 'p_saida';
    requirePerm($me, $perm);
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??$_POST;
    $insumoId=(int)($b['insumo_id']??0);
    $setorId=isset($b['setor_id'])?(int)$b['setor_id']:null;
    $qtd=(int)($b['quantidade']??0);
    $desc=clean((string)($b['descricao']??'')); 
    $ref=clean((string)($b['referencia']??'')); 
    // Busca ou cria responsavel com o nome do usuario logado
    $pdo=db();
    $nomeUsuario=$me['nome'];
    $stmtR=$pdo->prepare("SELECT id FROM responsaveis WHERE nome=:n LIMIT 1");
    $stmtR->execute([':n'=>$nomeUsuario]);
    $respId=$stmtR->fetchColumn();
    if(!$respId){
        $pdo->prepare("INSERT INTO responsaveis (nome) VALUES (:n)")->execute([':n'=>$nomeUsuario]);
        $respId=(int)$pdo->lastInsertId();
    }
    $erros=[];
    if($insumoId<=0) $erros[]='Insumo inválido.';
    if($qtd<=0)      $erros[]='Quantidade deve ser maior que zero.';
    if($tipo==='SAIDA'&&!$setorId) $erros[]='Setor obrigatório para saída.';
    if($tipo==='SAIDA'&&$insumoId>0&&$qtd>0){
        $atual=estoqueAtual($insumoId);
        if($qtd>$atual) $erros[]="Estoque insuficiente! Disponível: {$atual}.";
    }
    if(!empty($erros)) json_response(['success'=>false,'message'=>implode(' ',$erros)],422);
    $stmt=db()->prepare("INSERT INTO movimentacoes (tipo,insumo_id,responsavel_id,usuario_id,setor_id,quantidade,descricao,referencia)
        VALUES (:tipo,:ins,:resp,:uid,:setor,:qtd,:desc,:ref)");
    $stmt->execute([':tipo'=>$tipo,':ins'=>$insumoId,':resp'=>$respId,':uid'=>$me['id'],
        ':setor'=>$setorId,':qtd'=>$qtd,':desc'=>$desc?:null,':ref'=>$ref?:null]);
    json_response(['success'=>true,'message'=>$tipo==='ENTRADA'?'Entrada registrada!':'Saída registrada!',
        'estoque_atual'=>estoqueAtual($insumoId)]);
}
// ============================================
// INVENTÁRIO
// ============================================
function actionInventarioAbrir(array $me): void {
    requirePerm($me,'p_inventario');
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??$_POST;
    $obs=clean((string)($b['observacao']??'')); 
    $pdo=db();
    $nomeUsuario=$me['nome'];
    $stmtR=$pdo->prepare("SELECT id FROM responsaveis WHERE nome=:n LIMIT 1");
    $stmtR->execute([':n'=>$nomeUsuario]);
    $respId=$stmtR->fetchColumn();
    if(!$respId){
        $pdo->prepare("INSERT INTO responsaveis (nome) VALUES (:n)")->execute([':n'=>$nomeUsuario]);
        $respId=(int)$pdo->lastInsertId();
    }
    $aberto=$pdo->query("SELECT id FROM inventarios WHERE status='ABERTO' LIMIT 1")->fetchColumn();
    if($aberto) json_response(['success'=>false,'message'=>'Já existe um inventário em andamento.'],409);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO inventarios (responsavel_id,usuario_id,observacao) VALUES (:r,:u,:o)")
            ->execute([':r'=>$respId,':u'=>$me['id'],':o'=>$obs?:null]);
        $invId=(int)$pdo->lastInsertId();

        $insumos=$pdo->query("SELECT i.id,COALESCE(SUM(CASE m.tipo WHEN 'ENTRADA' THEN m.quantidade ELSE -m.quantidade END),0) AS ea
            FROM insumos i LEFT JOIN movimentacoes m ON m.insumo_id=i.id WHERE i.ativo=1 GROUP BY i.id")->fetchAll();

        $ins=$pdo->prepare("INSERT INTO inventario_itens (inventario_id,insumo_id,qtd_sistema) VALUES (:inv,:ins,:qtd)");
        foreach($insumos as $i) $ins->execute([':inv'=>$invId,':ins'=>$i['id'],':qtd'=>(int)$i['ea']]);

        $pdo->commit();
        json_response(['success'=>true,'message'=>'Inventário aberto!','inventario_id'=>$invId]);
    } catch(\Throwable $e){ $pdo->rollBack(); json_response(['success'=>false,'message'=>$e->getMessage()],500); }
}

function actionInventarioAberto(): void {
    $inv=db()->query("SELECT i.id,i.status,i.observacao,DATE_FORMAT(i.aberto_em,'%d/%m/%Y %H:%i') AS aberto_em,
        r.nome AS responsavel FROM inventarios i JOIN responsaveis r ON r.id=i.responsavel_id
        WHERE i.status='ABERTO' LIMIT 1")->fetch();
    if(!$inv){ json_response(['success'=>true,'inventario'=>null]); }
    $itens=db()->prepare("SELECT ii.id,ii.insumo_id,ii.qtd_sistema,ii.qtd_contada,ins.nome AS insumo,ins.unidade
        FROM inventario_itens ii JOIN insumos ins ON ins.id=ii.insumo_id
        WHERE ii.inventario_id=:id ORDER BY ins.nome");
    $itens->execute([':id'=>$inv['id']]);
    json_response(['success'=>true,'inventario'=>$inv,'itens'=>$itens->fetchAll()]);
}

function actionInventarioSalvar(array $me): void {
    requirePerm($me,'p_inventario');
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $invId=(int)($b['inventario_id']??0); $itens=$b['itens']??[];
    if($invId<=0||empty($itens)) json_response(['success'=>false,'message'=>'Dados inválidos.'],422);

    $upd=db()->prepare("UPDATE inventario_itens SET qtd_contada=:qtd WHERE inventario_id=:inv AND insumo_id=:ins");
    foreach($itens as $item){
        $qtd=(isset($item['qtd_contada'])&&$item['qtd_contada']!==''&&$item['qtd_contada']!==null)?(int)$item['qtd_contada']:null;
        $upd->execute([':qtd'=>$qtd,':inv'=>$invId,':ins'=>(int)$item['insumo_id']]);
    }
    json_response(['success'=>true,'message'=>'Contagem salva!']);
}

function actionInventarioFinalizar(array $me): void {
    requirePerm($me,'p_inventario');
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $invId=(int)($b['inventario_id']??0);
    if($invId<=0) json_response(['success'=>false,'message'=>'ID inválido.'],422);

    $pdo=db();
    $nc=(int)$pdo->prepare("SELECT COUNT(*) FROM inventario_itens WHERE inventario_id=:id AND qtd_contada IS NULL")->execute([':id'=>$invId])?$pdo->query("SELECT COUNT(*) FROM inventario_itens WHERE inventario_id=$invId AND qtd_contada IS NULL")->fetchColumn():0;
    if($nc>0) json_response(['success'=>false,'message'=>"Ainda há {$nc} item(ns) sem contagem."],422);

    $pdo->beginTransaction();
    try {
        $itens=$pdo->prepare("SELECT insumo_id,qtd_sistema,qtd_contada FROM inventario_itens WHERE inventario_id=:id");
        $itens->execute([':id'=>$invId]); $rows=$itens->fetchAll();

        // Busca ou cria responsavel com o nome do usuario logado
        $nomeUsuario=$me['nome'];
        $stmtR=$pdo->prepare("SELECT id FROM responsaveis WHERE nome=:n LIMIT 1");
        $stmtR->execute([':n'=>$nomeUsuario]);
        $respId=$stmtR->fetchColumn();
        if(!$respId){
            $pdo->prepare("INSERT INTO responsaveis (nome) VALUES (:n)")->execute([':n'=>$nomeUsuario]);
            $respId=(int)$pdo->lastInsertId();
        }
        $insM=$pdo->prepare("INSERT INTO movimentacoes (tipo,insumo_id,responsavel_id,usuario_id,quantidade,descricao,referencia)
            VALUES (:tipo,:ins,:resp,:uid,:qtd,:desc,:ref)");
        $updI=$pdo->prepare("UPDATE inventario_itens SET diferenca=:dif WHERE inventario_id=:inv AND insumo_id=:ins");

        foreach($rows as $r){
            $dif=(int)$r['qtd_contada']-(int)$r['qtd_sistema'];
            $updI->execute([':dif'=>$dif,':inv'=>$invId,':ins'=>$r['insumo_id']]);
            if($dif===0) continue;
            $insM->execute([':tipo'=>$dif>0?'ENTRADA':'SAIDA',':ins'=>$r['insumo_id'],':resp'=>$respId,':uid'=>$me['id'],
                ':qtd'=>abs($dif),':desc'=>'Ajuste de inventário #'.$invId,':ref'=>'INV-'.$invId]);
        }
        $pdo->prepare("UPDATE inventarios SET status='FINALIZADO',finalizado_em=NOW() WHERE id=:id")->execute([':id'=>$invId]);
        $pdo->commit();
        json_response(['success'=>true,'message'=>'Inventário finalizado! Estoque ajustado.']);
    } catch(\Throwable $e){ $pdo->rollBack(); json_response(['success'=>false,'message'=>$e->getMessage()],500); }
}

function actionInventarioCancelar(array $me): void {
    requirePerm($me,'p_inventario');
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $id=(int)($b['inventario_id']??0);
    $stmt=db()->prepare("UPDATE inventarios SET status='CANCELADO' WHERE id=:id AND status='ABERTO'");
    $stmt->execute([':id'=>$id]);
    if($stmt->rowCount()===0) json_response(['success'=>false,'message'=>'Inventário não encontrado ou já finalizado.'],409);
    json_response(['success'=>true,'message'=>'Inventário cancelado.']);
}

function actionInventarioListar(array $me): void {
    requirePerm($me,'p_inventario');
    $page=max(1,(int)($_GET['page']??1)); $limit=10; $offset=($page-1)*$limit;
    $pdo=db();
    $total=(int)$pdo->query("SELECT COUNT(*) FROM inventarios")->fetchColumn();
    $stmt=$pdo->prepare("SELECT i.id,i.status,i.observacao,
        DATE_FORMAT(i.aberto_em,'%d/%m/%Y %H:%i') AS aberto_em,
        DATE_FORMAT(i.finalizado_em,'%d/%m/%Y %H:%i') AS finalizado_em,
        r.nome AS responsavel,
        (SELECT COUNT(*) FROM inventario_itens ii WHERE ii.inventario_id=i.id) AS total_itens,
        (SELECT COUNT(*) FROM inventario_itens ii WHERE ii.inventario_id=i.id AND ii.diferenca<>0 AND ii.diferenca IS NOT NULL) AS itens_divergentes
        FROM inventarios i JOIN responsaveis r ON r.id=i.responsavel_id
        ORDER BY i.aberto_em DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
    $stmt->bindValue(':off',$offset,PDO::PARAM_INT);
    $stmt->execute();
    json_response(['success'=>true,'data'=>$stmt->fetchAll(),'total'=>$total,'pages'=>(int)ceil($total/$limit),'page'=>$page]);
}

function actionInventarioDetalhe(array $me): void {
    requirePerm($me,'p_inventario');
    $id=(int)($_GET['id']??0);
    $inv=db()->prepare("SELECT i.id,i.status,i.observacao,
        DATE_FORMAT(i.aberto_em,'%d/%m/%Y %H:%i') AS aberto_em,
        DATE_FORMAT(i.finalizado_em,'%d/%m/%Y %H:%i') AS finalizado_em,
        r.nome AS responsavel FROM inventarios i JOIN responsaveis r ON r.id=i.responsavel_id WHERE i.id=:id");
    $inv->execute([':id'=>$id]); $inventario=$inv->fetch();
    if(!$inventario) json_response(['success'=>false,'message'=>'Não encontrado.'],404);
    $itens=db()->prepare("SELECT ii.qtd_sistema,ii.qtd_contada,ii.diferenca,ins.nome AS insumo,ins.unidade
        FROM inventario_itens ii JOIN insumos ins ON ins.id=ii.insumo_id
        WHERE ii.inventario_id=:id ORDER BY ins.nome");
    $itens->execute([':id'=>$id]);
    json_response(['success'=>true,'inventario'=>$inventario,'itens'=>$itens->fetchAll()]);
}

function actionInventarioExcluir(array $me): void {
    if($me['perfil']!=='superadmin') json_response(['success'=>false,'message'=>'Apenas o Super Admin pode excluir inventários.'],403);
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $id=(int)($b['inventario_id']??0);
    db()->prepare("DELETE FROM inventarios WHERE id=:id")->execute([':id'=>$id]);
    json_response(['success'=>true,'message'=>'Inventário excluído.']);
}

// ============================================
// USUÁRIOS
// ============================================
function actionUsuarios(array $me): void {
    requirePerm($me,'p_usuarios');
    $rows=db()->query("SELECT u.id,u.nome,u.email,p.nome AS perfil,u.ativo,
        DATE_FORMAT(u.criado_em,'%d/%m/%Y') AS criado_em,
        DATE_FORMAT(u.ultimo_login,'%d/%m/%Y %H:%i') AS ultimo_login,
        pe.p_entrada,pe.p_saida,pe.p_estoque,pe.p_inventario,
        pe.p_historico,pe.p_relatorio,pe.p_inv_editar,pe.p_usuarios
        FROM usuarios u JOIN perfis p ON p.id=u.perfil_id
        LEFT JOIN permissoes pe ON pe.usuario_id=u.id
        ORDER BY u.perfil_id,u.nome")->fetchAll();
    json_response(['success'=>true,'data'=>$rows]);
}

function actionUsuarioCriar(array $me): void {
    requirePerm($me,'p_usuarios');
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $nome=clean($b['nome']??''); $email=clean($b['email']??'');
    $senha=$b['senha']??''; $perfilId=(int)($b['perfil_id']??3);

    if(!$nome||!$email||!$senha) json_response(['success'=>false,'message'=>'Preencha todos os campos.'],422);
    if(strlen($senha)<6) json_response(['success'=>false,'message'=>'Senha mínima de 6 caracteres.'],422);
    // Somente superadmin pode criar outro superadmin
    if($perfilId===1 && $me['perfil']!=='superadmin') json_response(['success'=>false,'message'=>'Sem permissão.'],403);

    $hash=password_hash($senha,PASSWORD_BCRYPT,['cost'=>12]);
    $pdo=db();
    try {
        $pdo->prepare("INSERT INTO usuarios (nome,email,senha_hash,perfil_id) VALUES (:n,:e,:h,:p)")
            ->execute([':n'=>$nome,':e'=>$email,':h'=>$hash,':p'=>$perfilId]);
        $uid=(int)$pdo->lastInsertId();
        // Permissões padrão conforme perfil
        $pEntrada=$pSaida=$pEstoque=$pInventario=1;
        $pHistorico=$pRelatorio=$pInvEditar=$pUsuarios=0;
        if($perfilId===1){ $pHistorico=$pRelatorio=$pInvEditar=$pUsuarios=1; }
        elseif($perfilId===2){ $pHistorico=$pRelatorio=1; }
        $pdo->prepare("INSERT INTO permissoes (usuario_id,p_entrada,p_saida,p_estoque,p_inventario,p_historico,p_relatorio,p_inv_editar,p_usuarios)
            VALUES (:uid,:e,:s,:es,:i,:h,:r,:ie,:u)")->execute([
            ':uid'=>$uid,':e'=>$pEntrada,':s'=>$pSaida,':es'=>$pEstoque,':i'=>$pInventario,
            ':h'=>$pHistorico,':r'=>$pRelatorio,':ie'=>$pInvEditar,':u'=>$pUsuarios]);
        json_response(['success'=>true,'message'=>'Usuário criado com sucesso!','id'=>$uid]);
    } catch(PDOException $e){
        json_response(['success'=>false,'message'=>str_contains($e->getMessage(),'Duplicate')?'E-mail já cadastrado.':$e->getMessage()],422);
    }
}

function actionUsuarioEditar(array $me): void {
    requirePerm($me,'p_usuarios');
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $id=(int)($b['id']??0);
    $nome=clean($b['nome']??''); $email=clean($b['email']??'');
    $perfilId=(int)($b['perfil_id']??3); $ativo=(int)($b['ativo']??1);
    if(!$id||!$nome||!$email) json_response(['success'=>false,'message'=>'Dados inválidos.'],422);
    if($perfilId===1&&$me['perfil']!=='superadmin') json_response(['success'=>false,'message'=>'Sem permissão.'],403);
    $pdo=db();
    $pdo->prepare("UPDATE usuarios SET nome=:n,email=:e,perfil_id=:p,ativo=:a WHERE id=:id")
        ->execute([':n'=>$nome,':e'=>$email,':p'=>$perfilId,':a'=>$ativo,':id'=>$id]);
    if(!empty($b['nova_senha'])&&strlen($b['nova_senha'])>=6){
        $hash=password_hash($b['nova_senha'],PASSWORD_BCRYPT,['cost'=>12]);
        $pdo->prepare("UPDATE usuarios SET senha_hash=:h WHERE id=:id")->execute([':h'=>$hash,':id'=>$id]);
    }
    json_response(['success'=>true,'message'=>'Usuário atualizado!']);
}

function actionUsuarioExcluir(array $me): void {
    if($me['perfil']!=='superadmin') json_response(['success'=>false,'message'=>'Apenas Super Admin pode excluir usuários.'],403);
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $id=(int)($b['id']??0);
    if($id===$me['id']) json_response(['success'=>false,'message'=>'Você não pode excluir a própria conta.'],422);
    db()->prepare("DELETE FROM usuarios WHERE id=:id")->execute([':id'=>$id]);
    json_response(['success'=>true,'message'=>'Usuário excluído.']);
}

function actionPermissoesSalvar(array $me): void {
    if ($me['perfil'] !== 'superadmin')
        json_response(['success'=>false,'message'=>'Apenas o Super Admin pode alterar permissões.'], 403);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        json_response(['success'=>false,'message'=>'Método inválido'], 405);

    $b   = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid = (int)($b['usuario_id'] ?? 0);

    if (!$uid)
        json_response(['success'=>false,'message'=>'ID de usuário inválido.'], 422);

    // Verifica que o usuário existe
    $exists = db()->prepare("SELECT COUNT(*) FROM usuarios WHERE id = :id");
    $exists->execute([':id' => $uid]);
    if (!(int)$exists->fetchColumn())
        json_response(['success'=>false,'message'=>'Usuário não encontrado.'], 404);

    $pe  = (int)($b['p_entrada']    ?? 0);
    $ps  = (int)($b['p_saida']      ?? 0);
    $pes = (int)($b['p_estoque']    ?? 0);
    $pi  = (int)($b['p_inventario'] ?? 0);
    $ph  = (int)($b['p_historico']  ?? 0);
    $pr  = (int)($b['p_relatorio']  ?? 0);
    $pie = (int)($b['p_inv_editar'] ?? 0);
    $pu  = (int)($b['p_usuarios']   ?? 0);

    // Usa variáveis separadas para evitar parâmetros duplicados no PDO
    $stmt = db()->prepare("
        INSERT INTO permissoes
            (usuario_id, p_entrada, p_saida, p_estoque, p_inventario,
             p_historico, p_relatorio, p_inv_editar, p_usuarios)
        VALUES
            (:uid, :e1, :s1, :es1, :i1, :h1, :r1, :ie1, :u1)
        ON DUPLICATE KEY UPDATE
            p_entrada    = :e2,
            p_saida      = :s2,
            p_estoque    = :es2,
            p_inventario = :i2,
            p_historico  = :h2,
            p_relatorio  = :r2,
            p_inv_editar = :ie2,
            p_usuarios   = :u2
    ");

    $stmt->execute([
        ':uid' => $uid,
        ':e1'  => $pe,  ':e2'  => $pe,
        ':s1'  => $ps,  ':s2'  => $ps,
        ':es1' => $pes, ':es2' => $pes,
        ':i1'  => $pi,  ':i2'  => $pi,
        ':h1'  => $ph,  ':h2'  => $ph,
        ':r1'  => $pr,  ':r2'  => $pr,
        ':ie1' => $pie, ':ie2' => $pie,
        ':u1'  => $pu,  ':u2'  => $pu,
    ]);

    json_response(['success'=>true,'message'=>'Permissões salvas com sucesso!']);
}

function actionMeuPerfil(array $me): void {
    $stmt=db()->prepare("SELECT id,nome,email,DATE_FORMAT(criado_em,'%d/%m/%Y') AS criado_em,
        DATE_FORMAT(ultimo_login,'%d/%m/%Y %H:%i') AS ultimo_login FROM usuarios WHERE id=:id");
    $stmt->execute([':id'=>$me['id']]);
    json_response(['success'=>true,'data'=>$stmt->fetch()]);
}

function actionAlterarSenha(array $me): void {
    if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false,'message'=>'Método inválido'],405);
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $atual=$b['senha_atual']??''; $nova=$b['nova_senha']??'';
    if(!$atual||!$nova) json_response(['success'=>false,'message'=>'Preencha os campos.'],422);
    if(strlen($nova)<6) json_response(['success'=>false,'message'=>'Nova senha mínima 6 caracteres.'],422);
    $stmt=db()->prepare("SELECT senha_hash FROM usuarios WHERE id=:id");
    $stmt->execute([':id'=>$me['id']]);
    $hash=$stmt->fetchColumn();
    if(!password_verify($atual,$hash)) json_response(['success'=>false,'message'=>'Senha atual incorreta.'],401);
    $novoHash=password_hash($nova,PASSWORD_BCRYPT,['cost'=>12]);
    db()->prepare("UPDATE usuarios SET senha_hash=:h WHERE id=:id")->execute([':h'=>$novoHash,':id'=>$me['id']]);
    json_response(['success'=>true,'message'=>'Senha alterada com sucesso!']);
}

function actionChamadoInfo(array $me): void {
    $chamadoId = (int)($_GET['chamado_id'] ?? 0);
    if (!$chamadoId) json_response(['success'=>false,'message'=>'ID do chamado inválido.'], 422);
    $glpiUrl  = 'http://10.10.1.15/ti/apirest.php';
    $appToken = 'insumos_ti_token_2025';
    $userLogin= 'Daniel.anjos';
    $userPass = '28062022@Oa.';
    $ctx = stream_context_create(['http'=>[
        'method' => 'GET',
        'header' => "App-Token: {$appToken}\r\nAuthorization: Basic " . base64_encode("{$userLogin}:{$userPass}") . "\r\n",
        'ignore_errors' => true,
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]]);
    $sess = json_decode(@file_get_contents("{$glpiUrl}/initSession", false, $ctx), true);
    if (empty($sess['session_token'])) json_response(['success'=>false,'message'=>'Erro ao conectar ao GLPI.'], 500);
    $sessionToken = $sess['session_token'];
    $ctx2 = stream_context_create(['http'=>[
        'method' => 'GET',
        'header' => "App-Token: {$appToken}\r\nSession-Token: {$sessionToken}\r\n",
        'ignore_errors' => true,
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]]);
    $ticket = json_decode(@file_get_contents("{$glpiUrl}/Ticket/{$chamadoId}?expand_dropdowns=true", false, $ctx2), true);
    $ctx3 = stream_context_create(['http'=>[
        'method' => 'GET',
        'header' => "App-Token: {$appToken}\r\nSession-Token: {$sessionToken}\r\n",
        'ignore_errors' => true,
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]]);
    @file_get_contents("{$glpiUrl}/killSession", false, $ctx3);
    if (empty($ticket['id'])) json_response(['success'=>false,'message'=>'Chamado não encontrado.'], 404);
    $localizacao = '';
    if (!empty($ticket['locations_id']) && $ticket['locations_id'] !== '0') {
        $loc = html_entity_decode((string)$ticket['locations_id'], ENT_QUOTES, 'UTF-8');
        $partes = explode('>', $loc);
        $localizacao = trim(end($partes));
    }
    json_response([
        'success'     => true,
        'chamado_id'  => $ticket['id'],
        'titulo'      => $ticket['name'] ?? '',
        'localizacao' => $localizacao,
        'localizacao_completa' => html_entity_decode((string)($ticket['locations_id'] ?? ''), ENT_QUOTES, 'UTF-8'),
    ]);
}
