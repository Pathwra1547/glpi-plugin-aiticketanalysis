<?php

/**
 * -------------------------------------------------------------------------
 * Настройки плагина
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

class PluginAiticketanalysisConfig extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __('AI Ticket Analysis', 'aiticketanalysis');
    }

    public static function getConfig(): array
    {
        return Config::getConfigurationValues('plugin:aiticketanalysis');
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Адреса внешних сервисов берутся из настроек и уходят в curl — разрешаем только http(s)
     * без логина/пароля в URL, иначе получаем SSRF через настройки плагина.
     */
    public static function isAllowedServiceUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        if (($parts['host'] ?? '') === '') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        return true;
    }

    public static function isValidWorkspaceSlug(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9][a-z0-9._-]{0,62}$/', $slug);
    }

    public static function showConfigForm()
    {
        if (!Session::haveRight('config', UPDATE)) {
            return false;
        }

        $cfg = self::getConfig();

        global $CFG_GLPI;
        $cfgUrl = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/aiticketanalysis/front/config.form.php';

        echo "<form method='post' action='" . self::escape($cfgUrl) . "'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo "<div class='spaced' id='tabsbody'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>Настройки AnythingLLM</th></tr>";

        echo "<tr class='tab_bg_1'><td>URL AnythingLLM</td><td>";
        echo Html::input('anythingllm_url', [
            'value' => $cfg['anythingllm_url'] ?? 'http://localhost:3001',
            'size'  => 60,
        ]);
        echo "<br><small>Адрес AnythingLLM глазами сервера GLPI. На одном хосте: http://localhost:3001; "
            . "GLPI в Docker, AnythingLLM в соседнем контейнере: http://ИМЯ_КОНТЕЙНЕРА:3001; "
            . "GLPI в Docker, AnythingLLM на хосте: http://host.docker.internal:3001</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>API Key AnythingLLM</td><td>";
        // Значение никогда не отдаётся в HTML: пустое поле при сохранении не затирает ключ
        echo Html::input('anythingllm_api_key', [
            'value' => '',
            'size'  => 60,
            'type'  => 'password',
            'autocomplete' => 'new-password',
            'placeholder' => !empty($cfg['anythingllm_api_key'])
                ? '•••••••• (сохранён, введите новый чтобы заменить)'
                : '',
        ]);
        echo "<br><small>Пустое при сохранении — текущий ключ не меняется.</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Workspace по умолчанию (slug)</td><td>";
        echo Html::input('default_workspace', [
            'value' => $cfg['default_workspace'] ?? 'your-workspace',
            'size'  => 40,
        ]);
        echo "<br><small>Slug workspace в AnythingLLM. Основной промпт анализа задаётся в system prompt этого workspace.</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Lite-режим (компактный контекст)</td><td>";
        Dropdown::showYesNo('lite_mode', (int)($cfg['lite_mode'] ?? 0));
        echo "<br><small>Короче JSON-контекст заявки (меньше истории/полей). Инструкции анализа — в AnythingLLM.</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Таймаут запроса (сек)</td><td>";
        echo Html::input('request_timeout', [
            'value' => $cfg['request_timeout'] ?? '180',
            'size'  => 10,
            'type'  => 'number',
            'min'   => 30,
            'max'   => 600,
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Режим чата</td><td>";
        Dropdown::showFromArray('chat_mode', [
            'chat'  => 'chat (диалог + RAG)',
            'query' => 'query (только RAG)',
        ], ['value' => $cfg['chat_mode'] ?? 'chat']);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Использовать @agent + MCP GLPI</td><td>";
        Dropdown::showYesNo('use_agent_mcp', (int)($cfg['use_agent_mcp'] ?? 0));
        echo "<br><small>На слабом ПК держите Выкл. В lite-режиме игнорируется.</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Текст кнопки</td><td>";
        echo Html::input('button_label', [
            'value' => $cfg['button_label'] ?? 'AI L3',
            'size'  => 40,
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Дополнительный промпт (дописывается)</td><td>";
        echo "<textarea name='extra_prompt' rows='5' cols='70'>" .
            self::escape($cfg['extra_prompt'] ?? '') .
            "</textarea>";
        echo "<br><small>Опционально: текст дописывается к запросу перед данными заявки. "
            . "Не заменяет system prompt workspace в AnythingLLM.</small>";
        echo "</td></tr>";

        echo "<tr><th colspan='2'>Вложения заявки (v2) — OCR / текст</th></tr>";

        echo "<tr class='tab_bg_1'><td>Анализировать вложения</td><td>";
        Dropdown::showYesNo('analyze_attachments', (int)($cfg['analyze_attachments'] ?? 1));
        echo "<br><small>PDF/DOCX/XLSX/TXT — текст; PNG/JPG/сканы — vision/OCR через OpenAI-совместимый vision API.</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Vision API (OpenAI-совместимый: LM Studio / Ollama / vLLM)</td><td>";
        echo Html::input('vision_base_url', [
            'value' => $cfg['vision_base_url'] ?? 'http://127.0.0.1:1234/v1',
            'size'  => 60,
        ]);
        $vUrl = (string)($cfg['vision_base_url'] ?? '');
        if ($vUrl !== '' && str_contains($vUrl, 'host.docker.internal')) {
            echo "<br><small class='text-danger'>"
                . "Сейчас указан <code>host.docker.internal</code> — это работает только если GLPI в Docker. "
                . "Для GLPI без Docker замените на <code>http://127.0.0.1:1234/v1</code> "
                . "или <code>http://IP_СЕРВЕРА_VISION:1234/v1</code>."
                . "</small>";
        }
        echo "<br><small>"
            . "Адрес OpenAI-совместимого vision-сервера <b>глазами сервера GLPI</b> (не браузера). "
            . "Тот же хост: <code>http://127.0.0.1:1234/v1</code>. "
            . "GLPI в Docker, сервер на хосте: <code>http://host.docker.internal:1234/v1</code>. "
            . "Отдельный сервер: <code>http://IP_СЕРВЕРА_VISION:1234/v1</code>."
            . "</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>API Key vision-сервера</td><td>";
        echo Html::input('vision_api_key', [
            'value' => '',
            'size'  => 60,
            'type'  => 'password',
            'autocomplete' => 'new-password',
            'placeholder' => !empty($cfg['vision_api_key']) ? '•••••••• (сохранён, введите новый чтобы заменить)' : '',
        ]);
        echo "<br><small>"
            . "Токен vision-сервера, если он требует авторизацию (например, YOUR_API_KEY). "
            . "Уходит как <code>Authorization: Bearer …</code>. "
            . "Пустое при сохранении — текущий ключ не меняется."
            . "</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Vision / OCR модель</td><td>";
        echo Html::input('vision_model', [
            'value' => $cfg['vision_model'] ?? 'qwen/qwen2.5-vl-7b',
            'size'  => 40,
        ]);
        echo "<br><small>Любая vision-модель вашего сервера, напр. qwen/qwen2.5-vl-7b. Модель должна быть загружена.</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Таймаут vision (сек)</td><td>";
        echo Html::input('vision_timeout', [
            'value' => $cfg['vision_timeout'] ?? '120',
            'size'  => 10,
            'type'  => 'number',
            'min'   => 30,
            'max'   => 300,
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Макс. вложений / символов на файл</td><td>";
        echo Html::input('max_attachments', [
            'value' => $cfg['max_attachments'] ?? '5',
            'size'  => 6,
            'type'  => 'number',
            'min'   => 1,
            'max'   => 10,
        ]);
        echo " / ";
        echo Html::input('max_attachment_chars', [
            'value' => $cfg['max_attachment_chars'] ?? '4000',
            'size'  => 8,
            'type'  => 'number',
            'min'   => 500,
            'max'   => 20000,
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Общий бюджет символов на вложения</td><td>";
        echo Html::input('max_context_chars', [
            'value' => $cfg['max_context_chars'] ?? (string)PluginAiticketanalysisAttachments::DEFAULT_CONTEXT_CHARS,
            'size'  => 8,
            'type'  => 'number',
            'min'   => 2000,
            'max'   => 60000,
        ]);
        echo "<br><small>Потолок на все вложения вместе. Больше — полнее контекст, но выше риск таймаута "
            . "и выгрузки модели из VRAM. Лишнее обрезается с пометкой для модели.</small>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>Страниц скан-PDF в OCR</td><td>";
        echo Html::input('pdf_vision_pages', [
            'value' => $cfg['pdf_vision_pages'] ?? (string)PluginAiticketanalysisAttachments::DEFAULT_PDF_VISION_PAGES,
            'size'  => 6,
            'type'  => 'number',
            'min'   => 1,
            'max'   => 10,
        ]);
        echo "<br><small>Сколько первых страниц PDF без текстового слоя распознавать через vision "
            . "(нужен pdftoppm из poppler-utils; ~20–25 с на страницу).</small>";
        echo "</td></tr>";

        $ocrPrompt = trim((string)($cfg['ocr_prompt'] ?? ''));
        if ($ocrPrompt === '') {
            $ocrPrompt = PluginAiticketanalysisAttachments::DEFAULT_OCR_PROMPT;
        }
        echo "<tr class='tab_bg_1'><td>Промпт OCR (vision)</td><td>";
        echo "<textarea name='ocr_prompt' rows='6' cols='70'>" .
            self::escape($ocrPrompt) .
            "</textarea>";
        echo "<br><small>Инструкция для модели при разборе PNG/JPG/сканов. Пустое при сохранении → подставится значение по умолчанию.</small>";
        echo "</td></tr>";

        if (function_exists('plugin_aiticketanalysis_environment_report')) {
            echo "<tr class='tab_bg_1'><td>Окружение сервера</td><td>";
            $report = plugin_aiticketanalysis_environment_report();
            $rows = [];
            foreach ($report as $label => $ok) {
                $rows[] = ($ok ? '✔ ' : '✖ ') . self::escape((string)$label);
            }
            echo "<small>" . implode('<br>', $rows) . "</small>";
            if (empty($report['exec() для poppler-utils'])) {
                echo "<br><small>Без poppler-utils (pdftoppm) страницы скан-PDF не распознаются: "
                    . "текстовые PDF читает встроенный парсер, сканы помечаются как нераспознанные.</small>";
            }
            echo "</td></tr>";
        }

        echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
        echo Html::submit(__('Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
        echo "</td></tr>";
        echo "</table>";
        echo "</div>";
        Html::closeForm();

        self::showMappingsForm();
        return true;
    }

    public static function showMappingsForm()
    {
        global $DB, $CFG_GLPI;
        $cfgUrl = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/aiticketanalysis/front/config.form.php';

        echo "<br><table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='5'>Маппинг Entity + Profile → Workspace</th></tr>";
        echo "<tr><th>Организация (Entity)</th><th>Профиль</th><th>Workspace slug</th><th>Активен</th><th></th></tr>";

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_aiticketanalysis_mappings',
            'ORDER' => 'id ASC',
        ]);

        foreach ($iterator as $row) {
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . Dropdown::getDropdownName('glpi_entities', $row['entities_id']) . "</td>";
            echo "<td>" . Dropdown::getDropdownName('glpi_profiles', $row['profiles_id']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['workspace_slug'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . ((int)$row['is_active'] ? 'Да' : 'Нет') . "</td>";
            echo "<td class='center'>";
            echo "<form method='post' action='" . self::escape($cfgUrl) . "' style='display:inline'>";
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo Html::hidden('mapping_id', ['value' => $row['id']]);
            echo Html::submit('Удалить', ['name' => 'delete_mapping', 'class' => 'btn btn-sm btn-danger']);
            Html::closeForm();
            echo "</td></tr>";
        }

        echo "<tr class='tab_bg_2'><th colspan='5'>Добавить маппинг</th></tr>";
        echo "<tr class='tab_bg_1'><td colspan='5'>";
        echo "<form method='post' action='" . self::escape($cfgUrl) . "'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "Entity: ";
        Entity::dropdown(['name' => 'entities_id', 'value' => 0, 'display_emptychoice' => false]);
        echo " &nbsp; Profile: ";
        Profile::dropdown(['name' => 'profiles_id', 'value' => $_SESSION['glpiactiveprofile']['id'] ?? 0]);
        echo " &nbsp; Workspace: ";
        echo Html::input('workspace_slug', ['value' => 'default', 'size' => 30]);
        echo " &nbsp; ";
        echo Html::submit('Добавить', ['name' => 'add_mapping', 'class' => 'btn btn-primary']);
        Html::closeForm();
        echo "</td></tr>";
        echo "</table>";

        echo "<p class='text-muted' style='margin-top:1em'>";
        echo "Кнопка AI-анализа показывается только если для текущей пары Entity+Profile есть активный маппинг ";
        echo "(или есть маппинг с Entity=0 / Profile=0 как wildcard). ";
        echo "Workspace выбирается по точному совпадению Entity+Profile, затем wildcard, иначе — workspace по умолчанию.";
        echo "</p>";
    }

    public static function saveConfig(array $input): void
    {
        $fields = [
            'anythingllm_url',
            'anythingllm_api_key',
            'default_workspace',
            'request_timeout',
            'use_agent_mcp',
            'lite_mode',
            'chat_mode',
            'button_label',
            'extra_prompt',
            'analyze_attachments',
            'vision_base_url',
            'vision_api_key',
            'vision_model',
            'vision_timeout',
            'max_attachments',
            'max_attachment_chars',
            'max_context_chars',
            'pdf_vision_pages',
            'ocr_prompt',
        ];
        $toSave = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $input)) {
                $toSave[$f] = $input[$f];
            }
        }

        // Пустые password-поля не затирают уже сохранённые ключи
        foreach (['vision_api_key', 'anythingllm_api_key'] as $secret) {
            if (array_key_exists($secret, $toSave) && trim((string)$toSave[$secret]) === '') {
                unset($toSave[$secret]);
            }
        }

        // Адреса сервисов уходят в curl — не даём записать file://, gopher:// и URL с учётными данными
        foreach (['anythingllm_url', 'vision_base_url'] as $urlField) {
            if (!array_key_exists($urlField, $toSave)) {
                continue;
            }
            $value = trim((string)$toSave[$urlField]);
            if ($value !== '' && !self::isAllowedServiceUrl($value)) {
                unset($toSave[$urlField]);
                Session::addMessageAfterRedirect(
                    'Некорректный адрес в поле ' . self::escape($urlField) . ' — допустимы только http(s)-URL. Значение не сохранено.',
                    false,
                    ERROR
                );
                continue;
            }
            $toSave[$urlField] = $value;
        }

        if (array_key_exists('default_workspace', $toSave)) {
            $slug = trim((string)$toSave['default_workspace']);
            if ($slug === '' || !self::isValidWorkspaceSlug($slug)) {
                unset($toSave['default_workspace']);
                Session::addMessageAfterRedirect(
                    'Workspace slug должен быть вида a-z0-9-_. Значение не сохранено.',
                    false,
                    ERROR
                );
            } else {
                $toSave['default_workspace'] = $slug;
            }
        }

        if (array_key_exists('ocr_prompt', $toSave) && trim((string)$toSave['ocr_prompt']) === '') {
            $toSave['ocr_prompt'] = PluginAiticketanalysisAttachments::DEFAULT_OCR_PROMPT;
        }
        if ($toSave) {
            Config::setConfigurationValues('plugin:aiticketanalysis', $toSave);
        }
    }

    /**
     * Резолв workspace и права на кнопку по Entity+Profile.
     *
     * @return array{allowed:bool, workspace:string, reason?:string}
     */
    public static function resolveWorkspace(int $entities_id, int $profiles_id): array
    {
        global $DB;

        $cfg = self::getConfig();
        $default = $cfg['default_workspace'] ?? 'default';

        if (!$DB->tableExists('glpi_plugin_aiticketanalysis_mappings')) {
            return ['allowed' => false, 'workspace' => $default, 'reason' => 'Таблица маппингов не установлена'];
        }

        $candidates = [
            ['entities_id' => $entities_id, 'profiles_id' => $profiles_id],
            ['entities_id' => $entities_id, 'profiles_id' => 0],
            ['entities_id' => 0, 'profiles_id' => $profiles_id],
            ['entities_id' => 0, 'profiles_id' => 0],
        ];

        try {
            foreach ($candidates as $crit) {
                $it = $DB->request([
                    'FROM'  => 'glpi_plugin_aiticketanalysis_mappings',
                    'WHERE' => [
                        'entities_id'  => $crit['entities_id'],
                        'profiles_id'  => $crit['profiles_id'],
                        'is_active'    => 1,
                    ],
                    'LIMIT' => 1,
                ]);
                if ($row = $it->current()) {
                    return [
                        'allowed'   => true,
                        'workspace' => $row['workspace_slug'] ?: $default,
                    ];
                }
            }
        } catch (Throwable $e) {
            if (class_exists('Toolbox')) {
                Toolbox::logInFile('aiticketanalysis', 'resolveWorkspace failed: ' . $e->getMessage() . "\n", true);
            }
            return ['allowed' => false, 'workspace' => $default, 'reason' => 'Таблица маппингов не установлена'];
        }

        return [
            'allowed'   => false,
            'workspace' => $default,
            'reason'    => 'Нет активного маппинга Entity+Profile для текущего пользователя',
        ];
    }
}
