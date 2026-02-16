<?php

declare(strict_types=1);

/**
 * @param array<string, mixed>|null $upload
 * @return array{0:string, 1:string, 2:string}
 */
function handleOcrRequest(?array $upload): array
{
    if ($upload === null || !isset($upload['error']) || (int)$upload['error'] !== UPLOAD_ERR_OK) {
        return ['', 'Bitte wähle ein Bild aus, bevor du den OCR-Scan startest.', ''];
    }

    $maxSize = 8 * 1024 * 1024;
    if (($upload['size'] ?? 0) > $maxSize) {
        return ['', 'Die Datei ist zu groß. Bitte maximal 8 MB hochladen.', ''];
    }

    $tmpPath = (string)($upload['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['', 'Ungültiger Upload erkannt.', ''];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath) ?: '';

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        return ['', 'Nicht unterstütztes Dateiformat. Erlaubt sind JPG, PNG, WEBP.', ''];
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['', 'Upload-Verzeichnis konnte nicht erstellt werden.', ''];
    }

    $safeName = 'scan_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $extension = $allowedMimeTypes[$mimeType];
    $targetImage = $uploadDir . '/' . $safeName . '.' . $extension;
    if (!move_uploaded_file($tmpPath, $targetImage)) {
        return ['', 'Das Bild konnte nicht gespeichert werden.', ''];
    }

    $ocrBinary = trim((string)shell_exec('command -v tesseract 2>/dev/null'));
    if ($ocrBinary === '') {
        return ['', 'Tesseract OCR ist nicht installiert. Bitte auf dem Server installieren: sudo apt install tesseract-ocr', 'uploads/' . basename($targetImage)];
    }

    $outputBase = $uploadDir . '/' . $safeName;
    $command = sprintf(
        '%s %s %s -l deu+eng --dpi 300 2>&1',
        escapeshellarg($ocrBinary),
        escapeshellarg($targetImage),
        escapeshellarg($outputBase)
    );

    exec($command, $commandOutput, $exitCode);
    $txtFile = $outputBase . '.txt';

    if ($exitCode !== 0 || !is_file($txtFile)) {
        $msg = 'OCR-Verarbeitung fehlgeschlagen.';
        if (!empty($commandOutput)) {
            $msg .= ' Details: ' . implode(' ', $commandOutput);
        }
        return ['', $msg, 'uploads/' . basename($targetImage)];
    }

    $text = trim((string)file_get_contents($txtFile));

    if ($text === '') {
        return ['', 'Kein Text erkannt. Bitte versuche ein schärferes Foto mit guter Beleuchtung.', 'uploads/' . basename($targetImage)];
    }

    return [$text, '', 'uploads/' . basename($targetImage)];
}
