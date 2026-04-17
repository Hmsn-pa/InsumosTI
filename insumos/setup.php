<?php
/**
 * Plugin Insumos T.I. para GLPI
 * Controle de insumos integrado ao menu Assistência do GLPI
 */

define('PLUGIN_INSUMOS_VERSION',    '1.0.0');
define('PLUGIN_INSUMOS_MIN_GLPI',   '10.0.0');
define('PLUGIN_INSUMOS_MAX_GLPI',   '10.0.99');
define('PLUGIN_INSUMOS_SSO_SECRET', 'InsumosSSOSecret2025');

function plugin_version_insumos(): array
{
    return [
        'name'         => 'Controle de Insumos T.I.',
        'version'      => PLUGIN_INSUMOS_VERSION,
        'author'       => 'T.I.',
        'license'      => 'GPL v2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_INSUMOS_MIN_GLPI,
                'max' => PLUGIN_INSUMOS_MAX_GLPI,
            ],
            'php' => ['min' => '7.4'],
        ],
    ];
}

function plugin_init_insumos(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::CSRF_COMPLIANT]['insumos'] = true;

    if (Session::getLoginUserID()) {
        $PLUGIN_HOOKS['redefine_menus']['insumos'] = 'plugin_insumos_redefine_menus';

        // JS que força o link do menu a abrir em nova aba —
        // evita o problema de redirecionamento errado na segunda vez.
        // Mesma estratégia do dashglpi.
        $PLUGIN_HOOKS['add_javascript']['insumos'] = 'public/js/menu.js';
    }
}

function plugin_insumos_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_INSUMOS_MIN_GLPI, 'lt')) {
        echo 'Requer GLPI >= ' . PLUGIN_INSUMOS_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_insumos_check_config(bool $verbose = false): bool
{
    return true;
}
