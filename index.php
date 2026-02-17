<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput ?: '', true);

    if (!is_array($payload) || ($payload['action'] ?? '') !== 'save_selected_words') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ungültige Anfrage.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $words = $payload['words'] ?? [];
    if (!is_array($words)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Wortliste fehlt.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $normalized = [];
    foreach ($words as $item) {
        if (!is_array($item)) {
            continue;
        }

        $word = trim((string)($item['word'] ?? ''));
        if ($word === '') {
            continue;
        }

        $suggestions = $item['suggestions'] ?? [];
        if (!is_array($suggestions)) {
            $suggestions = [];
        }

        $examples = $item['examples'] ?? [];
        if (!is_array($examples)) {
            $examples = [];
        }

        $normalized[] = [
            'word' => $word,
            'count' => max(1, (int)($item['count'] ?? 1)),
            'ukrainianTranslation' => trim((string)($item['ukrainianTranslation'] ?? '')),
            'suggestions' => array_values(array_filter(array_map(static fn($value) => trim((string)$value), $suggestions))),
            'examples' => array_values(array_filter(array_map(static fn($value) => trim((string)$value), $examples))),
        ];
    }

    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Speicherordner konnte nicht erstellt werden.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fileName = 'selected_words_' . date('Ymd_His') . '.json';
    $targetPath = $dataDir . '/' . $fileName;

    $content = json_encode([
        'savedAt' => date(DATE_ATOM),
        'count' => count($normalized),
        'words' => $normalized
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($content === false || file_put_contents($targetPath, $content) === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'JSON-Datei konnte nicht gespeichert werden.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Markierungen gespeichert.',
        'file' => 'data/' . $fileName
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Textmarker für Wörterbuch</title>
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
        #textEditor {
            min-height: 260px;
            white-space: pre-wrap;
            overflow-y: auto;
        }
        #textEditor:empty::before {
            content: attr(data-placeholder);
            color: #6c757d;
        }
        mark.word-highlight {
            background: #87e8a9;
            padding: 0 .1em;
            border-radius: .2em;
        }
    </style>
</head>
<body>
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <div class="card app-card">
                <div class="card-body p-3 p-md-4 p-lg-5">
                    <h1 class="h3 mb-3">📝 Текст и словарь</h1>
                    <p class="text-muted mb-4">Вставь готовый текст. Двойной клик по слову добавляет его в словарь и подсвечивает зелёным во всём тексте.</p>

                    <label for="textEditor" class="form-label fw-semibold">Текст</label>
                    <div id="textEditor" class="form-control" contenteditable="true" data-placeholder="Вставь сюда готовый текст..."></div>

                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted"><span id="charCount">0</span> Zeichen</small>
                        <button class="btn btn-outline-secondary btn-sm" id="copyBtn" type="button">Text kopieren</button>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-3">
                        <button class="btn btn-outline-primary btn-sm" id="spellcheckBtn" type="button">Rechtschreibung korrigieren</button>
                        <button class="btn btn-outline-success btn-sm" id="saveSelectionBtn" type="button">Markierungen als JSON speichern</button>
                    </div>

                    <div class="alert alert-danger mt-4 mb-0 d-none" role="alert" id="errorMessage"></div>
                    <small class="text-muted d-block mt-2" id="selectionHint">Двойной клик по слову — добавить в словарь.</small>
                    <small class="text-muted d-block mt-2" id="saveStatus"></small>

                    <div class="mt-4 d-none" id="selectionTableBlock">
                        <label class="form-label fw-semibold">Wörterbuch</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0" id="selectionTable">
                                <thead>
                                <tr>
                                    <th scope="col">Wort</th>
                                    <th scope="col">Übersetzungsvorschläge (UK)</th>
                                    <th scope="col">Beispielsätze</th>
                                </tr>
                                </thead>
                                <tbody id="selectionTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const textEditor = document.getElementById('textEditor');
const errorMessage = document.getElementById('errorMessage');
const charCount = document.getElementById('charCount');
const copyBtn = document.getElementById('copyBtn');
const spellcheckBtn = document.getElementById('spellcheckBtn');
const selectionHint = document.getElementById('selectionHint');
const selectionTableBlock = document.getElementById('selectionTableBlock');
const selectionTableBody = document.getElementById('selectionTableBody');
const saveSelectionBtn = document.getElementById('saveSelectionBtn');
const saveStatus = document.getElementById('saveStatus');

const selectedWords = new Map();
const wordDetails = new Map();

function showError(message) {
    errorMessage.textContent = message;
    errorMessage.classList.remove('d-none');
}

function clearError() {
    errorMessage.textContent = '';
    errorMessage.classList.add('d-none');
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getPlainText() {
    return (textEditor.innerText || '')
        .replace(/\u00A0/g, ' ')
        .replace(/\r/g, '');
}

function setPlainText(value) {
    textEditor.textContent = value;
    applyHighlights();
    updateCharCount();
}

function updateCharCount() {
    charCount.textContent = String(getPlainText().length);
}

function applyHighlights() {
    const text = getPlainText();
    const words = Array.from(selectedWords.keys()).sort((a, b) => b.length - a.length);
    let html = escapeHtml(text);

    for (const word of words) {
        const regex = new RegExp(`(^|[^\\p{L}\\p{M}'’-])(${escapeRegExp(word)})(?=$|[^\\p{L}\\p{M}'’-])`, 'giu');
        html = html.replace(regex, '$1<mark class="word-highlight">$2</mark>');
    }

    html = html.replace(/\n/g, '<br>');
    textEditor.innerHTML = html;
}

function updateSelectionTable() {
    selectionTableBody.innerHTML = '';
    const entries = Array.from(selectedWords.entries()).sort((a, b) => b[1] - a[1]);

    if (entries.length === 0) {
        selectionTableBlock.classList.add('d-none');
        return;
    }

    for (const [word, count] of entries) {
        const row = document.createElement('tr');

        const wordCell = document.createElement('td');
        wordCell.textContent = word;

        const details = wordDetails.get(word) || { suggestions: [], examples: [] };

        const suggestionsCell = document.createElement('td');
        suggestionsCell.innerHTML = details.suggestions.length
            ? details.suggestions.map((item) => `<div>${escapeHtml(item)}</div>`).join('')
            : '…';

        const examplesCell = document.createElement('td');
        examplesCell.innerHTML = details.examples.length
            ? details.examples.map((item) => `<div>${escapeHtml(item)}</div>`).join('')
            : '—';

        row.appendChild(wordCell);
        row.appendChild(suggestionsCell);
        row.appendChild(examplesCell);
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

function buildSelectedWordsPayload() {
    return Array.from(selectedWords.entries()).map(([word, count]) => ({
        word,
        count,
        ukrainianTranslation: (wordDetails.get(word)?.suggestions || [])[0] || '',
        suggestions: wordDetails.get(word)?.suggestions || [],
        examples: wordDetails.get(word)?.examples || []
    }));
}

function buildWordDetailsFromMyMemory(payload, word) {
    const fallback = { suggestions: ['—'], examples: [] };

    const primary = payload?.responseData?.translatedText?.trim();
    const matches = Array.isArray(payload?.matches) ? payload.matches : [];

    const suggestionSet = new Set();
    if (primary) {
        suggestionSet.add(primary);
    }

    for (const match of matches) {
        const translation = String(match?.translation || '').trim();
        if (!translation) {
            continue;
        }

        suggestionSet.add(translation);
        if (suggestionSet.size >= 4) {
            break;
        }
    }

    const examples = [];
    for (const match of matches) {
        const source = String(match?.segment || '').trim();
        const target = String(match?.translation || '').trim();

        if (!source || !target) {
            continue;
        }

        if (source.toLowerCase() === word.toLowerCase() && target.length <= 24) {
            continue;
        }

        examples.push(`DE: ${source} → UK: ${target}`);
        if (examples.length >= 3) {
            break;
        }
    }

    const suggestions = [...suggestionSet].slice(0, 4);
    if (suggestions.length === 0) {
        return fallback;
    }

    return {
        suggestions,
        examples
    };
}

async function fetchWordDetails(word) {
    if (wordDetails.has(word)) {
        return wordDetails.get(word);
    }

    try {
        const endpoint = `https://api.mymemory.translated.net/get?q=${encodeURIComponent(word)}&langpair=de|uk`;
        const response = await fetch(endpoint);
        if (!response.ok) {
            throw new Error('Translation request failed');
        }

        const payload = await response.json();
        const details = buildWordDetailsFromMyMemory(payload, word);
        wordDetails.set(word, details);
        return details;
    } catch (error) {
        const fallback = { suggestions: ['—'], examples: [] };
        wordDetails.set(word, fallback);
        return fallback;
    }
}

async function updateWordDetails(words) {
    const uniqueWords = [...new Set(words)].filter((word) => !wordDetails.has(word));
    if (uniqueWords.length === 0) {
        return;
    }

    await Promise.all(uniqueWords.map((word) => fetchWordDetails(word)));
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

if (textEditor) {
    textEditor.addEventListener('input', () => {
        updateCharCount();
    });

    textEditor.addEventListener('dblclick', async () => {
        const selectedText = window.getSelection()?.toString()?.trim() || '';
        const words = extractWordsFromSelection(selectedText);

        if (words.length === 0) {
            return;
        }

        for (const word of words) {
            const currentCount = selectedWords.get(word) || 0;
            selectedWords.set(word, currentCount + 1);
        }

        applyHighlights();
        updateSelectionTable();
        await updateWordDetails(words);
        updateSelectionTable();

        if (selectionHint) {
            selectionHint.textContent = `Добавлено: ${words.join(', ')}`;
        }
    });
}

if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(getPlainText());
            copyBtn.textContent = 'Kopiert!';
            setTimeout(() => {
                copyBtn.textContent = 'Text kopieren';
            }, 1200);
        } catch (error) {
            copyBtn.textContent = 'Kopieren fehlgeschlagen';
        }
    });
}

if (spellcheckBtn) {
    spellcheckBtn.addEventListener('click', async () => {
        clearError();
        spellcheckBtn.disabled = true;
        const originalLabel = spellcheckBtn.textContent;
        spellcheckBtn.textContent = 'Korrigiere...';

        try {
            const correctedText = await correctGermanSpelling(getPlainText());
            setPlainText(correctedText);
        } catch (error) {
            showError('Die Rechtschreibkorrektur ist aktuell nicht verfügbar. Bitte später erneut versuchen.');
        } finally {
            spellcheckBtn.disabled = false;
            spellcheckBtn.textContent = originalLabel;
        }
    });
}

if (saveSelectionBtn) {
    saveSelectionBtn.addEventListener('click', async () => {
        if (saveStatus) {
            saveStatus.textContent = '';
        }

        const payload = buildSelectedWordsPayload();
        if (payload.length === 0) {
            if (saveStatus) {
                saveStatus.textContent = 'Es sind noch keine markierten Wörter zum Speichern vorhanden.';
            }
            return;
        }

        saveSelectionBtn.disabled = true;
        const originalLabel = saveSelectionBtn.textContent;
        saveSelectionBtn.textContent = 'Speichere...';

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save_selected_words',
                    words: payload
                })
            });

            const result = await response.json();
            if (!response.ok || !result?.success) {
                throw new Error(result?.message || 'Speichern fehlgeschlagen');
            }

            if (saveStatus) {
                saveStatus.textContent = `Gespeichert: ${result.file}`;
            }
        } catch (error) {
            if (saveStatus) {
                saveStatus.textContent = 'Speichern fehlgeschlagen. Bitte erneut versuchen.';
            }
        } finally {
            saveSelectionBtn.disabled = false;
            saveSelectionBtn.textContent = originalLabel;
        }
    });
}
</script>
</body>
</html>
