<?php

/**
 * -------------------------------------------------------------------------
 * AI Ticket Analysis — GLPI 11 ↔ AnythingLLM integration (+ read-only GLPI MCP)
 *
 * Copyright (C) 2026 AI Ticket Analysis contributors
 *
 * This file is part of the AI Ticket Analysis plugin for GLPI.
 * It is free software: you can redistribute it and/or modify it under the terms
 * of the GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 * See the LICENSE file for the full license text.
 * -------------------------------------------------------------------------
 */

define('PLUGIN_AITICKETANALYSIS_VERSION', '2.1.2');
define('PLUGIN_AITICKETANALYSIS_MIN_GLPI', '11.0.0');
define('PLUGIN_AITICKETANALYSIS_MAX_GLPI', '11.99.99');

function plugin_init_aiticketanalysis()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['aiticketanalysis'] = true;

    $base = dirname(__FILE__);
    require_once $base . '/inc/config.class.php';
    require_once $base . '/inc/ticket.class.php';
    require_once $base . '/inc/attachments.class.php';
    require_once $base . '/inc/analyzer.class.php';

    // Без этого хука ядро отдаёт значения ключей плагина через REST/HL API и выгрузки конфигурации
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::UNDISCLOSED_CONFIG_VALUE]['aiticketanalysis'] =
        'plugin_aiticketanalysis_undiscloseConfigValue';

    if (Session::getLoginUserID()) {
        $PLUGIN_HOOKS['config_page']['aiticketanalysis'] = 'front/config.form.php';

        $PLUGIN_HOOKS['add_javascript']['aiticketanalysis'] = 'js/aiticketanalysis.js';
        $PLUGIN_HOOKS['add_css']['aiticketanalysis']        = 'css/aiticketanalysis.css';

        // Кнопка в нижней панели рядом с «Ответ» (timeline footer)
        $PLUGIN_HOOKS['timeline_actions']['aiticketanalysis'] = 'plugin_aiticketanalysis_timeline_actions';
    }
}

/**
 * Hook timeline_actions — вывод в футер заявки рядом с «Ответ».
 * Ошибки плагина не должны ломать форму (fail-soft).
 */
function plugin_aiticketanalysis_timeline_actions($params)
{
    try {
        PluginAiticketanalysisTicket::timelineActions(is_array($params) ? $params : ['item' => $params]);
    } catch (Throwable $e) {
        Toolbox::logInFile(
            'php-errors',
            '[aiticketanalysis] timeline_actions: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"
        );
    }
}

function plugin_version_aiticketanalysis()
{
    return [
        'name'         => 'AI Ticket Analysis',
        'version'      => PLUGIN_AITICKETANALYSIS_VERSION,
        'author'       => 'AI Ticket Analysis contributors',
        'license'      => 'GPLv3+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_AITICKETANALYSIS_MIN_GLPI,
                'max' => PLUGIN_AITICKETANALYSIS_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_aiticketanalysis_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_AITICKETANALYSIS_MIN_GLPI, 'lt')) {
        echo 'Требуется GLPI >= ' . PLUGIN_AITICKETANALYSIS_MIN_GLPI;
        return false;
    }
    return true;
}

/**
 * Секреты плагина не должны раскрываться через API и выгрузки конфигурации.
 */
function plugin_aiticketanalysis_undiscloseConfigValue($fields)
{
    $secrets = ['anythingllm_api_key', 'vision_api_key'];
    if (($fields['context'] ?? '') === 'plugin:aiticketanalysis'
        && in_array($fields['name'] ?? '', $secrets, true)
    ) {
        unset($fields['value']);
    }
    return $fields;
}

function plugin_aiticketanalysis_check_config()
{
    return true;
}

/**
 * Что доступно окружению для разбора вложений — показывается в форме настроек.
 *
 * @return array<string,bool>
 */
function plugin_aiticketanalysis_environment_report(): array
{
    $execAvailable = function_exists('exec')
        && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);

    return [
        'GD (обработка изображений)'   => function_exists('imagecreatefromstring'),
        'ZipArchive (DOCX/XLSX)'       => class_exists('ZipArchive'),
        'cURL (запросы к сервисам)'    => function_exists('curl_init'),
        'Встроенный парсер PDF'        => is_file(__DIR__ . '/lib/pdfparser/autoload.php'),
        'exec() для poppler-utils'     => $execAvailable,
    ];
}
