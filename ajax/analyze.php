<?php

/**
 * -------------------------------------------------------------------------
 * AJAX: запуск AI-анализа заявки
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

header('Content-Type: application/json; charset=UTF-8');

// LLM + OCR: vision может идти дольше chat; иначе nginx/PHP режут и браузер видит HTML 504
$cfg = PluginAiticketanalysisConfig::getConfig();
$reqTimeout = max(60, min(600, (int)($cfg['request_timeout'] ?? 180)));
$visTimeout = max(30, min(300, (int)($cfg['vision_timeout'] ?? 120)));
$maxAtt     = max(1, min(10, (int)($cfg['max_attachments'] ?? 5)));
// Запас: OCR каждого вложения + chat AnythingLLM
$totalBudget = $reqTimeout + ($visTimeout * $maxAtt) + 60;
$totalBudget = max(120, min(1800, $totalBudget));
@ini_set('max_execution_time', (string)$totalBudget);
@ini_set('default_socket_timeout', (string)$totalBudget);
@set_time_limit($totalBudget);
ignore_user_abort(true);

try {
    if (!Session::getLoginUserID()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tickets_id = (int)($_POST['tickets_id'] ?? 0);
    if ($tickets_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Не указан tickets_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = PluginAiticketanalysisAnalyzer::analyzeTicket($tickets_id);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    Toolbox::logInFile(
        'aiticketanalysis',
        'ajax fatal: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n",
        true
    );
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Внутренняя ошибка анализа, подробности в журнале плагина',
    ], JSON_UNESCAPED_UNICODE);
}
