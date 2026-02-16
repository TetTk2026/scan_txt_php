<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buchseiten-Scanner (Client-OCR)</title>
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
                        OCR läuft direkt im Browser (Client-Seite), es ist keine Tesseract-Installation auf dem Server nötig.
                    </p>

                    <form id="ocrForm" novalidate>
                        <div class="mb-3">
                            <label for="book_photo" class="form-label fw-semibold">Schritt 1: Foto auswählen</label>
                            <input class="form-control" type="file" id="book_photo" name="book_photo" accept="image/*" required>
                            <div class="form-text">Empfohlen: JPG, JPEG, PNG, WEBP (max. 8 MB).</div>
                        </div>

                        <div class="preview-wrapper mb-3" id="previewWrapper">
                            <p class="text-secondary mb-0" id="placeholderText">Bildvorschau erscheint hier</p>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="extractBtn">Schritt 2: Text extrahieren</button>
                    </form>

                    <div class="alert alert-danger mt-4 mb-0 d-none" role="alert" id="errorMessage"></div>

                    <div class="mt-4 d-none" id="resultBlock">
                        <label for="ocr_output" class="form-label fw-semibold">Erkannter und verbesserter Text</label>
                        <textarea id="ocr_output" class="form-control" rows="10"></textarea>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-muted"><span id="charCount">0</span> Zeichen</small>
                            <button class="btn btn-outline-secondary btn-sm" id="copyBtn" type="button">Text kopieren</button>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-3">
                            <button class="btn btn-outline-primary btn-sm" id="spellcheckBtn" type="button">Schritt 3 (optional): Rechtschreibung korrigieren</button>
                            <button class="btn btn-outline-dark btn-sm" id="selectionBtn" type="button">Schritt 4: Markierte Wörter übernehmen</button>
                        </div>
                        <small class="text-muted d-block mt-2" id="selectionHint">
                            Markiere zuerst ein oder mehrere Wörter im Textfeld und klicke danach auf Schritt 4.
                        </small>
                        <small class="text-muted d-block mt-2">
                            Tipp: Zeilenumbrüche, Worttrennungen und Absatzstruktur werden automatisch verbessert.
                        </small>
                    </div>

                    <div class="mt-4 d-none" id="selectionTableBlock">
                        <label class="form-label fw-semibold">Markierte Wörter</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0" id="selectionTable">
                                <thead>
                                <tr>
                                    <th scope="col">Wort</th>
                                    <th scope="col" class="text-end">Anzahl Markierungen</th>
                                </tr>
                                </thead>
                                <tbody id="selectionTableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 d-none" id="progressBlock">
                        <label class="form-label fw-semibold" for="ocrProgress">OCR-Fortschritt</label>
                        <div class="progress" role="progressbar" aria-label="OCR-Fortschritt">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="ocrProgress" style="width: 0%">0%</div>
                        </div>
                        <small class="text-muted" id="progressStatus">Initialisiere OCR...</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
const maxSize = 8 * 1024 * 1024;
const fileInput = document.getElementById('book_photo');
const ocrForm = document.getElementById('ocrForm');
const previewWrapper = document.getElementById('previewWrapper');
const placeholderText = document.getElementById('placeholderText');
const errorMessage = document.getElementById('errorMessage');
const extractBtn = document.getElementById('extractBtn');
const resultBlock = document.getElementById('resultBlock');
const outputField = document.getElementById('ocr_output');
const charCount = document.getElementById('charCount');
const copyBtn = document.getElementById('copyBtn');
const progressBlock = document.getElementById('progressBlock');
const progressStatus = document.getElementById('progressStatus');
const progressBar = document.getElementById('ocrProgress');
const spellcheckBtn = document.getElementById('spellcheckBtn');
const selectionBtn = document.getElementById('selectionBtn');
const selectionHint = document.getElementById('selectionHint');
const selectionTableBlock = document.getElementById('selectionTableBlock');
const selectionTableBody = document.getElementById('selectionTableBody');

const selectedWords = new Map();

function showError(message) {
    errorMessage.textContent = message;
    errorMessage.classList.remove('d-none');
}

function clearError() {
    errorMessage.textContent = '';
    errorMessage.classList.add('d-none');
}

function resetProgress() {
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    progressStatus.textContent = 'Initialisiere OCR...';
}

function normalizeOcrText(rawText) {
    if (!rawText) {
        return '';
    }

    const cleanedLines = rawText
        .replace(/\r/g, '')
        .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]+/g, '')
        .split('\n')
        .map((line) => line.trim().replace(/\s+/g, ' '));

    const paragraphs = [];
    let currentParagraph = '';

    const flushParagraph = () => {
        if (!currentParagraph) {
            return;
        }
        paragraphs.push(currentParagraph.trim());
        currentParagraph = '';
    };

    for (const line of cleanedLines) {
        if (!line) {
            flushParagraph();
            continue;
        }

        if (!currentParagraph) {
            currentParagraph = line;
            continue;
        }

        const endsWithHyphen = /[\p{L}]-$/u.test(currentParagraph);
        if (endsWithHyphen) {
            currentParagraph = `${currentParagraph.slice(0, -1)}${line}`;
            continue;
        }

        currentParagraph = `${currentParagraph} ${line}`;
    }

    flushParagraph();

    const formattedParagraphs = paragraphs
        .map((paragraph) => paragraph
            .replace(/\s+([,.;:!?])/g, '$1')
            .replace(/([,.;:!?])(\p{L})/gu, '$1 $2')
            .replace(/\(\s+/g, '(')
            .replace(/\s+\)/g, ')')
            .replace(/\s+/g, ' ')
            .trim()
        )
        .filter(Boolean);

    return formattedParagraphs.join('\n\n');
}

function updateSelectionTable() {
    if (!selectionTableBody || !selectionTableBlock) {
        return;
    }

    selectionTableBody.innerHTML = '';
    const entries = Array.from(selectedWords.entries()).sort((a, b) => b[1] - a[1]);

    if (entries.length === 0) {
        selectionTableBlock.classList.add('d-none');
        return;
    }

    for (const [word, count] of entries) {
        const row = document.createElement('tr');
        row.innerHTML = `<td>${word}</td><td class="text-end">${count}</td>`;
        selectionTableBody.appendChild(row);
    }

    selectionTableBlock.classList.remove('d-none');
}

function extractWordsFromSelection(value) {
    return (value || '')
        .match(/[\p{L}\p{M}'’-]+/gu)
        ?.map((word) => word.toLowerCase())
        .filter(Boolean) || [];
}

async function correctGermanSpelling(text) {
    if (!text.trim()) {
        return text;
    }

    const params = new URLSearchParams({
        text,
        language: 'de-DE'
    });

    const response = await fetch('https://api.languagetool.org/v2/check', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: params.toString()
    });

    if (!response.ok) {
        throw new Error('Spellcheck request failed');
    }

    const payload = await response.json();
    const matches = Array.isArray(payload?.matches) ? payload.matches : [];

    const applicable = matches
        .filter((match) => Number.isInteger(match?.offset) && Number.isInteger(match?.length) && match.length > 0)
        .filter((match) => Array.isArray(match?.replacements) && match.replacements[0]?.value)
        .sort((a, b) => b.offset - a.offset);

    let corrected = text;
    for (const match of applicable) {
        const replacement = match.replacements[0].value;
        corrected = `${corrected.slice(0, match.offset)}${replacement}${corrected.slice(match.offset + match.length)}`;
    }

    return corrected;
}

if (fileInput) {
    fileInput.addEventListener('change', (event) => {
        clearError();
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        if (file.size > maxSize) {
            fileInput.value = '';
            showError('Die Datei ist zu groß. Bitte maximal 8 MB auswählen.');
            return;
        }

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

if (ocrForm) {
    ocrForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearError();

        const file = fileInput.files?.[0];
        if (!file) {
            showError('Bitte wähle ein Bild aus, bevor du den OCR-Scan startest.');
            return;
        }

        if (typeof Tesseract === 'undefined') {
            showError('OCR-Bibliothek konnte nicht geladen werden. Bitte Internetverbindung prüfen.');
            return;
        }

        extractBtn.disabled = true;
        extractBtn.textContent = 'OCR läuft...';
        progressBlock.classList.remove('d-none');
        resetProgress();

        try {
            const { data } = await Tesseract.recognize(file, 'deu+eng', {
                logger: (info) => {
                    if (!info || typeof info !== 'object') {
                        return;
                    }

                    if (typeof info.progress === 'number') {
                        const percent = Math.max(0, Math.min(100, Math.round(info.progress * 100)));
                        progressBar.style.width = `${percent}%`;
                        progressBar.textContent = `${percent}%`;
                    }

                    if (typeof info.status === 'string' && info.status.length > 0) {
                        progressStatus.textContent = `Status: ${info.status}`;
                    }
                }
            });

            const rawText = (data?.text || '').trim();
            const improvedText = normalizeOcrText(rawText);

            if (!improvedText) {
                showError('Kein Text erkannt. Bitte versuche ein schärferes Foto mit guter Beleuchtung.');
                resultBlock.classList.add('d-none');
                return;
            }

            outputField.value = improvedText;
            charCount.textContent = String(improvedText.length);
            resultBlock.classList.remove('d-none');
            selectedWords.clear();
            updateSelectionTable();
        } catch (error) {
            showError('OCR-Verarbeitung fehlgeschlagen. Bitte erneut versuchen.');
        } finally {
            extractBtn.disabled = false;
            extractBtn.textContent = 'Schritt 2: Text extrahieren';
            progressStatus.textContent = 'Fertig';
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

if (spellcheckBtn && outputField) {
    spellcheckBtn.addEventListener('click', async () => {
        clearError();
        spellcheckBtn.disabled = true;
        const originalLabel = spellcheckBtn.textContent;
        spellcheckBtn.textContent = 'Korrigiere...';

        try {
            const correctedText = await correctGermanSpelling(outputField.value);
            outputField.value = correctedText;
            charCount.textContent = String(correctedText.length);
        } catch (error) {
            showError('Die Rechtschreibkorrektur ist aktuell nicht verfügbar. Bitte später erneut versuchen.');
        } finally {
            spellcheckBtn.disabled = false;
            spellcheckBtn.textContent = originalLabel;
        }
    });
}

if (selectionBtn && outputField) {
    selectionBtn.addEventListener('click', () => {
        const { selectionStart, selectionEnd, value } = outputField;
        if (selectionStart === selectionEnd) {
            if (selectionHint) {
                selectionHint.textContent = 'Bitte markiere mindestens ein Wort im Textfeld und klicke erneut auf Schritt 4.';
            }
            return;
        }

        const selectedText = value.slice(selectionStart, selectionEnd);
        const words = extractWordsFromSelection(selectedText);

        if (words.length === 0) {
            if (selectionHint) {
                selectionHint.textContent = 'Die Markierung enthält keine auswertbaren Wörter.';
            }
            return;
        }

        for (const word of words) {
            const currentCount = selectedWords.get(word) || 0;
            selectedWords.set(word, currentCount + 1);
        }

        updateSelectionTable();
        if (selectionHint) {
            selectionHint.textContent = `${words.length} Wort/Wörter übernommen.`;
        }
    });
}
</script>
</body>
</html>
