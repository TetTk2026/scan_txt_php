<?php
$textResult = '';
$errorMessage = '';
$imagePreviewPath = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/ocr.php';
    [$textResult, $errorMessage, $imagePreviewPath] = handleOcrRequest($_FILES['book_photo'] ?? null);
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buchseiten-Scanner (Web + Mobile)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            min-height: 100vh;
        }
        .app-card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.25rem rgba(0,0,0,.08);
        }
        .preview-wrapper {
            border: 2px dashed #ced4da;
            border-radius: .75rem;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fff;
        }
        .preview-wrapper img {
            max-width: 100%;
            max-height: 320px;
            object-fit: contain;
        }
    </style>
</head>
<body>
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <div class="card app-card">
                <div class="card-body p-3 p-md-4 p-lg-5">
                    <h1 class="h3 mb-3">📘 Buchseiten-Scanner</h1>
                    <p class="text-muted mb-4">
                        Upload einer Buchseite (Schritt 1) und Umwandlung in Text mit OCR (Schritt 2).
                        Funktioniert responsiv für Web und Mobile.
                    </p>

                    <form method="post" enctype="multipart/form-data" id="ocrForm" novalidate>
                        <div class="mb-3">
                            <label for="book_photo" class="form-label fw-semibold">Schritt 1: Foto hochladen</label>
                            <input class="form-control" type="file" id="book_photo" name="book_photo" accept="image/*" required>
                            <div class="form-text">Erlaubte Formate: JPG, JPEG, PNG, WEBP (max. 8 MB).</div>
                        </div>

                        <div class="preview-wrapper mb-3" id="previewWrapper">
                            <?php if ($imagePreviewPath !== ''): ?>
                                <img src="<?= htmlspecialchars($imagePreviewPath, ENT_QUOTES, 'UTF-8') ?>" alt="Vorschau hochgeladenes Bild">
                            <?php else: ?>
                                <p class="text-secondary mb-0" id="placeholderText">Bildvorschau erscheint hier</p>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Schritt 2: Text extrahieren</button>
                    </form>

                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert alert-danger mt-4 mb-0" role="alert">
                            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($textResult !== ''): ?>
                        <div class="mt-4">
                            <label for="ocr_output" class="form-label fw-semibold">Erkannter Text</label>
                            <textarea id="ocr_output" class="form-control" rows="10" readonly><?= htmlspecialchars($textResult, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted"><span id="charCount"><?= mb_strlen($textResult) ?></span> Zeichen</small>
                                <button class="btn btn-outline-secondary btn-sm" id="copyBtn" type="button">Text kopieren</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const fileInput = document.getElementById('book_photo');
const previewWrapper = document.getElementById('previewWrapper');
const placeholderText = document.getElementById('placeholderText');
const copyBtn = document.getElementById('copyBtn');
const outputField = document.getElementById('ocr_output');

if (fileInput) {
    fileInput.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const img = document.createElement('img');
        img.alt = 'Lokale Vorschau';
        img.src = URL.createObjectURL(file);

        previewWrapper.innerHTML = '';
        previewWrapper.appendChild(img);

        if (placeholderText) {
            placeholderText.remove();
        }
    });
}

if (copyBtn && outputField) {
    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(outputField.value);
            copyBtn.textContent = 'Kopiert!';
            setTimeout(() => {
                copyBtn.textContent = 'Text kopieren';
            }, 1200);
        } catch (error) {
            copyBtn.textContent = 'Kopieren fehlgeschlagen';
        }
    });
}
</script>
</body>
</html>
