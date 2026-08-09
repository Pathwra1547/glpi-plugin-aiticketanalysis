<?php

/**
 * -------------------------------------------------------------------------
 * Install / uninstall hooks
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

function plugin_aiticketanalysis_install()
{
    global $DB;

    require_once __DIR__ . '/inc/attachments.class.php';

    $default = [
        'anythingllm_url'      => 'http://localhost:3001',
        'anythingllm_api_key'  => '',
        'default_workspace'    => 'your-workspace',
        'request_timeout'      => '180',
        'use_agent_mcp'        => '0',
        'lite_mode'            => '0',
        'chat_mode'            => 'chat',
        'button_label'         => 'AI L3',
        'extra_prompt'         => '',
        'analyze_attachments'  => '1',
        'vision_base_url'      => 'http://127.0.0.1:1234/v1',
        'vision_api_key'       => '',
        'vision_model'         => 'qwen/qwen2.5-vl-7b',
        'vision_timeout'       => '120',
        'max_attachments'      => '5',
        'max_attachment_chars' => '4000',
        'max_context_chars'    => (string)PluginAiticketanalysisAttachments::DEFAULT_CONTEXT_CHARS,
        'pdf_vision_pages'     => (string)PluginAiticketanalysisAttachments::DEFAULT_PDF_VISION_PAGES,
        'ocr_prompt'           => PluginAiticketanalysisAttachments::DEFAULT_OCR_PROMPT,
        'schema_version'       => PLUGIN_AITICKETANALYSIS_VERSION,
    ];

    // Повторная установка/обновление не должна стирать настройки администратора и ключи API
    $current = Config::getConfigurationValues('plugin:aiticketanalysis');
    $missing = [];
    foreach ($default as $name => $value) {
        if (!array_key_exists($name, $current)) {
            $missing[$name] = $value;
        }
    }
    $missing['schema_version'] = PLUGIN_AITICKETANALYSIS_VERSION;
    Config::setConfigurationValues('plugin:aiticketanalysis', $missing);

    $DB->doQuery("CREATE TABLE IF NOT EXISTS `glpi_plugin_aiticketanalysis_mappings` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `entities_id` int unsigned NOT NULL DEFAULT '0',
        `profiles_id` int unsigned NOT NULL DEFAULT '0',
        `workspace_slug` varchar(255) NOT NULL,
        `is_active` tinyint NOT NULL DEFAULT '1',
        `comment` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `entity_profile` (`entities_id`, `profiles_id`),
        KEY `entities_id` (`entities_id`),
        KEY `profiles_id` (`profiles_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");

    return true;
}

function plugin_aiticketanalysis_uninstall()
{
    global $DB;

    // Config::deleteConfigurationValues в GLPI 11 не всегда удаляет все ключи контекста
    $DB->delete('glpi_configs', ['context' => 'plugin:aiticketanalysis']);
    // tableExists кэшируется — DROP IF EXISTS без предварительной проверки
    $DB->doQuery('DROP TABLE IF EXISTS `glpi_plugin_aiticketanalysis_mappings`');

    return true;
}
