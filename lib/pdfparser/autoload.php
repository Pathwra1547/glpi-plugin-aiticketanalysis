<?php

/**
 * -------------------------------------------------------------------------
 * Автозагрузчик встроенной копии smalot/pdfparser (PSR-0, LGPL-3.0).
 * Плагин поставляется без Composer, поэтому классы подключаются напрямую.
 * -------------------------------------------------------------------------
 */

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Smalot\\PdfParser\\')) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $file = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relative;
    if (is_file($file)) {
        require_once $file;
    }
});
