<?php

/**
 * -------------------------------------------------------------------------
 * Кнопка AI-анализа в нижней панели заявки (рядом с «Ответ»)
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

class PluginAiticketanalysisTicket
{
    /** @var array<int, bool> */
    private static $rendered = [];

    /**
     * Hook timeline_actions — вывод в #itil-footer .legacy-timeline-actions
     *
     * @param array $params ['item' => CommonGLPI, 'rand' => int]
     */
    public static function timelineActions(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!$item instanceof Ticket || $item->isNewItem()) {
            return;
        }

        $tid = (int)$item->getID();
        if (isset(self::$rendered[$tid])) {
            return;
        }
        self::$rendered[$tid] = true;

        $entities_id = (int)($item->fields['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
        $profiles_id = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);

        $resolved = PluginAiticketanalysisConfig::resolveWorkspace($entities_id, $profiles_id);
        if (!$resolved['allowed']) {
            return;
        }

        $cfg   = PluginAiticketanalysisConfig::getConfig();
        $label = $cfg['button_label'] ?? 'AI L3';
        $id    = $tid;

        global $CFG_GLPI;

        $safeLabel = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
        // GLPI 11: URL плагина без Plugin::getWebDir()
        $ajaxUrl = PluginAiticketanalysisConfig::escape(
            ($CFG_GLPI['root_doc'] ?? '') . '/plugins/aiticketanalysis/ajax/analyze.php'
        );

        // Только <li> — хук рендерится внутри <ul class="legacy-timeline-actions">
        echo "<li class='aiticketanalysis-footer-action me-2'>";
        echo "<button type='button' class='btn btn-aiticketanalysis' id='aiticketanalysis-btn'";
        echo " data-ticket-id='{$id}'";
        echo " data-ajax-url='{$ajaxUrl}'";
        echo " title='Анализ L3: SLA, влияние на бизнес, история, RAG'>";
        echo "<i class='ti ti-brain'></i> <span>{$safeLabel}</span>";
        echo "</button>";
        echo "</li>";
    }
}
