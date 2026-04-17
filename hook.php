<?php
// =============================================================
// plugins/insumos/hook.php
// Hooks do Plugin: menu lateral + SSO automático
// =============================================================

/**
 * Instalação do plugin (executa ao instalar pelo painel GLPI)
 */
function plugin_insumos_install(): bool
{
    // Nada para instalar no banco — o sistema de insumos
    // tem seu próprio banco (insumos_ti). Apenas retorna true.
    return true;
}

/**
 * Desinstalação do plugin
 */
function plugin_insumos_uninstall(): bool
{
    return true;
}

/**
 * Adiciona o item "Insumos T.I." no menu lateral do GLPI
 * Aparece para todos os usuários com perfil ativo
 */
function plugin_insumos_getMenuContent(): array
{
    $menu = [];

    // Ícone e título no menu lateral
    $menu['title'] = 'Insumos T.I.';
    $menu['page']  = Plugin::getWebDir('insumos') . '/front/main.php';
    $menu['icon']  = 'ti ti-package'; // ícone Tabler (padrão GLPI 10)

    // Sub-itens do menu (aparecem ao expandir)
    $menu['links']['search'] = Plugin::getWebDir('insumos') . '/front/main.php';

    $menu['options']['main'] = [
        'title' => 'Controle de Insumos',
        'page'  => Plugin::getWebDir('insumos') . '/front/main.php',
        'links' => [
            'Dashboard'  => Plugin::getWebDir('insumos') . '/front/main.php?page=dashboard',
            'Estoque'    => Plugin::getWebDir('insumos') . '/front/main.php?page=estoque',
            'Entrada'    => Plugin::getWebDir('insumos') . '/front/main.php?page=entrada',
            'Saída'      => Plugin::getWebDir('insumos') . '/front/main.php?page=saida',
            'Inventário' => Plugin::getWebDir('insumos') . '/front/main.php?page=inventario',
            'Relatórios' => Plugin::getWebDir('insumos') . '/front/main.php?page=relatorio',
        ],
    ];

    return $menu;
}
