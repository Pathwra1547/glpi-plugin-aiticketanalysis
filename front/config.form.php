<?php

/**
 * -------------------------------------------------------------------------
 * Plugin configuration form
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

include __DIR__ . '/../../../inc/includes.php';

Session::checkRight('config', UPDATE);

global $CFG_GLPI;
$cfgRedirect = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/aiticketanalysis/front/config.form.php';

if (isset($_POST['update'])) {
    PluginAiticketanalysisConfig::saveConfig($_POST);
    Session::addMessageAfterRedirect('Настройки AI Ticket Analysis сохранены');
    Html::redirect($cfgRedirect);
}

if (isset($_POST['add_mapping'])) {
    global $DB;
    $slug = trim((string)($_POST['workspace_slug'] ?? ''));
    if (!PluginAiticketanalysisConfig::isValidWorkspaceSlug($slug)) {
        Session::addMessageAfterRedirect(
            'Некорректный workspace slug: допустимы строчные латинские буквы, цифры, дефис, подчёркивание и точка.',
            false,
            ERROR
        );
        Html::redirect($cfgRedirect);
    }
    $DB->insert('glpi_plugin_aiticketanalysis_mappings', [
        'entities_id'    => (int)($_POST['entities_id'] ?? 0),
        'profiles_id'    => (int)($_POST['profiles_id'] ?? 0),
        'workspace_slug' => $slug,
        'is_active'      => 1,
    ]);
    Session::addMessageAfterRedirect('Маппинг добавлен');
    Html::redirect($cfgRedirect);
}

if (isset($_POST['delete_mapping'])) {
    global $DB;
    $DB->delete('glpi_plugin_aiticketanalysis_mappings', [
        'id' => (int)($_POST['mapping_id'] ?? 0),
    ]);
    Session::addMessageAfterRedirect('Маппинг удалён');
    Html::redirect($cfgRedirect);
}

Html::header(
    'AI Ticket Analysis',
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

PluginAiticketanalysisConfig::showConfigForm();

Html::footer();
