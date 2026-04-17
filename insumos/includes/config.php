<?php
declare(strict_types=1);
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'insumos_ti');
define('DB_USER',    'insumos');
define('DB_PASS',    'Insumos@2025');
define('DB_CHARSET', 'utf8mb4');
define('SESSION_HOURS', 8);
date_default_timezone_set('America/Belem');
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        } catch (PDOException $e) { http_response_code(500); die(json_encode(['success'=>false,'message'=>'Erro de conexão: '.$e->getMessage()])); }
    }
    return $pdo;
}
function json_response(array $data, int $status = 200): void {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
function clean(string $v): string { return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8'); }
function getToken(): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($h, 'Bearer ')) return trim(substr($h, 7));
    return $_COOKIE['insumos_token'] ?? '';
}
function authUser(): ?array {
    $token = getToken(); if (!$token) return null;
    $stmt = db()->prepare("SELECT u.id,u.nome,u.email,p.nome AS perfil,COALESCE(pe.p_entrada,1) p_entrada,COALESCE(pe.p_saida,1) p_saida,COALESCE(pe.p_estoque,1) p_estoque,COALESCE(pe.p_inventario,1) p_inventario,COALESCE(pe.p_historico,0) p_historico,COALESCE(pe.p_relatorio,0) p_relatorio,COALESCE(pe.p_inv_editar,0) p_inv_editar,COALESCE(pe.p_usuarios,0) p_usuarios FROM sessoes s JOIN usuarios u ON u.id=s.usuario_id JOIN perfis p ON p.id=u.perfil_id LEFT JOIN permissoes pe ON pe.usuario_id=u.id WHERE s.token=:t AND s.expira_em>NOW() AND u.ativo=1");
    $stmt->execute([':t'=>$token]); return $stmt->fetch() ?: null;
}
function requireAuth(): array {
    $u = authUser();
    if (!$u) json_response(['success'=>false,'message'=>'Não autenticado.','redirect'=>'login.php'], 401);
    return $u;
}
function requirePerm(array $user, string $perm): void {
    if ($user['perfil']==='superadmin') return;
    if (empty($user[$perm])) json_response(['success'=>false,'message'=>'Sem permissão.'], 403);
}
function estoqueAtual(int $insumoId): int {
    $stmt = db()->prepare("SELECT COALESCE(SUM(CASE tipo WHEN 'ENTRADA' THEN quantidade ELSE -quantidade END),0) FROM movimentacoes WHERE insumo_id=:id");
    $stmt->execute([':id'=>$insumoId]); return (int)$stmt->fetchColumn();
}
function statusEstoque(int $atual, int $minimo): string {
    if ($atual<=0) return 'zerado';
    if ($atual<=intval($minimo*0.5)) return 'critico';
    if ($atual<=$minimo) return 'atencao';
    return 'ok';
}
