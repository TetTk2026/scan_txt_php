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
            background: #f3f4f6;
            min-height: 100vh;
            color: #111827;
        }
        .app-card {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(17, 24, 39, .06);
            background: #ffffff;
        }
        .app-title {
            margin: 0;
            font-size: clamp(1.35rem, 2vw, 1.8rem);
            font-weight: 650;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        .app-title-mark {
            display: inline-block;
            width: .55rem;
            height: .55rem;
            margin-right: .55rem;
            border-radius: 50%;
            background: #0f172a;
            vertical-align: middle;
            transform: translateY(-1px);
        }
        .app-title-subtitle {
            margin: .35rem 0 0;
            color: #6b7280;
            font-size: .95rem;
        }
        #textEditor {
            min-height: 260px;
            white-space: pre-wrap;
            overflow-y: auto;
        }
        #translationEditor {
            min-height: 260px;
            resize: vertical;
        }
        #textEditor,
        #translationEditor,
        .dictionary-example {
            border: 1px solid #d1d5db;
            border-radius: .75rem;
            background: #fff;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        #textEditor:focus,
        #translationEditor:focus,
        .dictionary-example:focus {
            border-color: #9ca3af;
            box-shadow: 0 0 0 .15rem rgba(156, 163, 175, .2);
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
        .save-expression-btn {
            background-color: #ffd8a8;
            border-color: #ffc078;
            color: #5c3b00;
            border-radius: 999px;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .save-expression-btn:hover,
        .save-expression-btn:focus {
            background-color: #ffc078;
            border-color: #ffa94d;
            color: #4a2f00;
        }
        .copy-noji-btn {
            border-radius: 999px;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .dictionary-block {
            border: 1px solid #e5e7eb;
            border-radius: .9rem;
            background: #fafafa;
            padding: 1rem;
        }
        .dictionary-table {
            margin-bottom: 0;
            --bs-table-bg: transparent;
            --bs-table-striped-bg: #f3f4f6;
        }
        .dictionary-table thead th {
            text-transform: uppercase;
            letter-spacing: .04em;
            font-size: .74rem;
            color: #6b7280;
            border-bottom-color: #d1d5db;
        }
        .dictionary-table td {
            padding-top: .8rem;
            padding-bottom: .8rem;
            border-color: #e5e7eb;
            vertical-align: top;
        }
        .dictionary-word {
            font-weight: 600;
            color: #111827;
        }
        .dictionary-translation {
            color: #374151;
        }
        .dictionary-example {
            min-height: 96px;
            resize: vertical;
            font-size: .9rem;
            line-height: 1.45;
        }
    </style>
</head>
<body>
<div class="container py-4 py-md-5">
        <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card app-card">
                <div class="card-body p-3 p-md-4 p-lg-5">
                    <h1 class="app-title"><span class="app-title-mark"></span>Текст · словарь</h1>
                    <p class="app-title-subtitle mb-4">Вставь готовый текст. Выделяй немецкое выражение и нажимай «зберегти вираз», чтобы добавить его в словарь и подсветить в тексте.</p>

                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label for="textEditor" class="form-label fw-semibold">Німецький текст</label>
                            <div id="textEditor" class="form-control" contenteditable="true" data-placeholder="Вставь сюда готовый текст..."></div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="translationEditor" class="form-label fw-semibold">Український переклад (можна редагувати)</label>
                            <textarea id="translationEditor" class="form-control" placeholder="Тут автоматично з'явиться переклад українською..."></textarea>
                            <small class="text-muted d-block mt-2" id="translationStatus">Встав текст німецькою зліва, щоб отримати автоматичний переклад.</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted"><span id="charCount">0</span> Zeichen</small>
                        <button class="btn btn-outline-secondary btn-sm" id="copyBtn" type="button">Text kopieren</button>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-3">
                        <button class="btn btn-sm save-expression-btn" id="saveExpressionBtn" type="button">зберегти вираз</button>
                        <button class="btn btn-sm btn-outline-primary copy-noji-btn d-none" id="copyNojiBtn" type="button">Noji-Import kopieren</button>
                    </div>

                    <div class="alert alert-danger mt-4 mb-0 d-none" role="alert" id="errorMessage"></div>
                    <small class="text-muted d-block mt-2" id="selectionHint">Выдели выражение и нажми «зберегти вираз».</small>
                    <small class="text-muted d-block mt-2" id="saveStatus"></small>

                    <div class="mt-4 d-none dictionary-block" id="selectionTableBlock">
                        <label class="form-label fw-semibold">Wörterbuch</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle dictionary-table" id="selectionTable">
                                <thead>
                                <tr>
                                    <th scope="col">Deutsches Wort</th>
                                    <th scope="col">Український переклад</th>
                                    <th scope="col">Приклад (можна редагувати)</th>
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
const translationEditor = document.getElementById('translationEditor');
const translationStatus = document.getElementById('translationStatus');
const selectionHint = document.getElementById('selectionHint');
const selectionTableBlock = document.getElementById('selectionTableBlock');
const selectionTableBody = document.getElementById('selectionTableBody');
const saveExpressionBtn = document.getElementById('saveExpressionBtn');
const copyNojiBtn = document.getElementById('copyNojiBtn');
const saveStatus = document.getElementById('saveStatus');

const selectedWords = new Map();
const wordDetails = new Map();
let translationDebounceTimer = null;
let translationRequestId = 0;

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

function getCaretOffsetWithinEditor() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return null;
    }

    const range = selection.getRangeAt(0);
    if (!textEditor.contains(range.startContainer)) {
        return null;
    }

    const preCaretRange = range.cloneRange();
    preCaretRange.selectNodeContents(textEditor);
    preCaretRange.setEnd(range.startContainer, range.startOffset);

    return preCaretRange.toString().length;
}

function setCaretOffsetWithinEditor(offset) {
    if (!Number.isInteger(offset) || offset < 0) {
        return;
    }

    const selection = window.getSelection();
    if (!selection) {
        return;
    }

    const range = document.createRange();
    let current = 0;
    const walker = document.createTreeWalker(textEditor, NodeFilter.SHOW_TEXT);

    while (walker.nextNode()) {
        const node = walker.currentNode;
        const next = current + node.textContent.length;

        if (offset <= next) {
            range.setStart(node, Math.max(0, offset - current));
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            return;
        }

        current = next;
    }

    range.selectNodeContents(textEditor);
    range.collapse(false);
    selection.removeAllRanges();
    selection.addRange(range);
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
    const sourceText = getPlainText();

    if (entries.length === 0) {
        selectionTableBlock.classList.add('d-none');
        if (copyNojiBtn) {
            copyNojiBtn.classList.add('d-none');
        }
        return;
    }

    for (const [word, count] of entries) {
        const row = document.createElement('tr');

        const wordCell = document.createElement('td');
        wordCell.className = 'dictionary-word';
        wordCell.textContent = word;

        const details = wordDetails.get(word) || { suggestions: [], examples: [] };
        const primaryTranslation = details.suggestions[0] || '…';
        const sentenceExample = findSentenceForWord(sourceText, word);
        const savedExamples = Array.isArray(details.examples) ? details.examples : [];
        const initialExamples = savedExamples.length > 0
            ? savedExamples
            : (sentenceExample ? [sentenceExample] : []);

        if (savedExamples.length === 0 && initialExamples.length > 0) {
            wordDetails.set(word, {
                ...details,
                examples: initialExamples
            });
        }

        const suggestionsCell = document.createElement('td');
        suggestionsCell.className = 'dictionary-translation';
        suggestionsCell.textContent = primaryTranslation;

        const examplesCell = document.createElement('td');
        const examplesInput = document.createElement('textarea');
        examplesInput.className = 'form-control form-control-sm dictionary-example';
        examplesInput.rows = 3;
        examplesInput.placeholder = 'Автоматично додано приклад з тексту. Можна редагувати та дописувати свої приклади.';
        examplesInput.value = initialExamples.join('\n');
        examplesInput.addEventListener('input', () => {
            const currentDetails = wordDetails.get(word) || { suggestions: [], examples: [] };
            wordDetails.set(word, {
                ...currentDetails,
                examples: parseManualExamples(examplesInput.value)
            });
        });
        examplesCell.appendChild(examplesInput);

        row.appendChild(wordCell);
        row.appendChild(suggestionsCell);
        row.appendChild(examplesCell);
        selectionTableBody.appendChild(row);
    }

    selectionTableBlock.classList.remove('d-none');
    if (copyNojiBtn) {
        copyNojiBtn.classList.remove('d-none');
    }
}

function buildNojiImportText() {
    const entries = Array.from(selectedWords.entries()).sort((a, b) => b[1] - a[1]);

    return entries
        .map(([word]) => {
            const suggestions = wordDetails.get(word)?.suggestions || [];
            const translation = suggestions[0] || '—';
            return `${word}\t${translation}`;
        })
        .join('\n');
}

function parseManualExamples(value) {
    return (value || '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

function findSentenceForWord(text, word) {
    if (!text.trim() || !word) {
        return '';
    }

    const sentenceParts = text
        .split(/(?<=[.!?…])\s+|\n+/u)
        .map((item) => item.trim())
        .filter(Boolean);

    if (sentenceParts.length === 0) {
        return '';
    }

    const needle = word.toLowerCase();
    for (const sentence of sentenceParts) {
        const words = extractWordsFromSelection(sentence);
        if (words.includes(needle)) {
            return sentence;
        }
    }

    return '';
}

function extractWordsFromSelection(value) {
    return (value || '')
        .match(/[\p{L}\p{M}'’-]+/gu)
        ?.map((word) => word.toLowerCase())
        .filter(Boolean) || [];
}

function getWordsFromCurrentSelection() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return [];
    }

    const range = selection.getRangeAt(0);
    if (!textEditor.contains(range.commonAncestorContainer)) {
        return [];
    }

    return extractWordsFromSelection(selection.toString());
}

function getExpressionFromCurrentSelection() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return '';
    }

    const range = selection.getRangeAt(0);
    if (!textEditor.contains(range.commonAncestorContainer)) {
        return '';
    }

    return selection.toString().replace(/\s+/g, ' ').trim();
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

const translationDictionary = new Map([
    ['haus', ['будинок', 'дім']],
    ['straße', ['вулиця', 'дорога']],
    ['buch', ['книга']],
    ['zeit', ['час']],
    ['arbeit', ['робота', 'праця']],
    ['leben', ['життя']],
    ['essen', ['їжа', 'їсти']],
    ['lernen', ['вчитися', 'навчатися']],
    ['sprechen', ['говорити', 'розмовляти']],
    ['gehen', ['йти', 'ходити']],
    ['kommen', ['приходити', 'приїжджати']],
    ['freund', ['друг', 'приятель']],
    ['familie', ['сімʼя', 'родина']],
    ['kinder', ['діти']],
    ['gut', ['добре', 'хороший']],
    ['schlecht', ['погано', 'поганий']],
    ['groß', ['великий']],
    ['klein', ['малий', 'маленький']]
]);

async function requestMyMemoryTranslation(word) {
    const params = new URLSearchParams({
        q: word,
        langpair: 'de|uk'
    });

    const response = await fetch(`https://api.mymemory.translated.net/get?${params.toString()}`);
    if (!response.ok) {
        throw new Error('MyMemory request failed');
    }

    const payload = await response.json();
    const candidates = [];

    const primary = String(payload?.responseData?.translatedText || '').trim();
    if (primary) {
        candidates.push(primary);
    }

    const matches = Array.isArray(payload?.matches) ? payload.matches : [];
    for (const match of matches) {
        const translation = extractTranslationFromPayload(match?.translation || '');
        if (translation) {
            candidates.push(translation);
        }
    }

    return [...new Set(candidates)];
}

async function requestMyMemoryTextTranslation(text) {
    const params = new URLSearchParams({
        q: text,
        langpair: 'de|uk'
    });

    const response = await fetch(`https://api.mymemory.translated.net/get?${params.toString()}`);
    if (!response.ok) {
        throw new Error('Text translation request failed');
    }

    const payload = await response.json();
    return String(payload?.responseData?.translatedText || '').trim();
}

function setTranslationStatus(message) {
    if (translationStatus) {
        translationStatus.textContent = message;
    }
}

async function translateGermanTextToUkrainian(text, requestId) {
    if (!translationEditor) {
        return;
    }

    if (!text.trim()) {
        translationEditor.value = '';
        setTranslationStatus('Встав текст німецькою зліва, щоб отримати автоматичний переклад.');
        return;
    }

    setTranslationStatus('Перекладаю...');

    try {
        const translated = await requestMyMemoryTextTranslation(text);
        if (requestId !== translationRequestId) {
            return;
        }

        translationEditor.value = translated || text;
        setTranslationStatus(translated
            ? 'Автоматичний переклад оновлено. За потреби відредагуй текст праворуч.'
            : 'Не вдалося отримати переклад, показано оригінал.');
    } catch (error) {
        if (requestId !== translationRequestId) {
            return;
        }

        translationEditor.value = text;
        setTranslationStatus('Сервіс перекладу тимчасово недоступний. Можеш відредагувати текст вручну праворуч.');
    }
}

function extractTranslationFromPayload(payload) {
    if (typeof payload === 'string') {
        return payload.trim();
    }

    if (!payload || typeof payload !== 'object') {
        return '';
    }

    const directCandidate = [
        payload.translation,
        payload.translatedText,
        payload.targetText,
        payload.text,
        payload.result
    ].find((value) => typeof value === 'string' && value.trim());

    if (directCandidate) {
        return directCandidate.trim();
    }

    const nestedCandidate = payload?.data?.translations?.[0]?.translatedText
        || payload?.translations?.[0]?.text
        || payload?.translation?.text;

    return typeof nestedCandidate === 'string' ? nestedCandidate.trim() : '';
}

function rankTranslationCandidates(word, candidates, dictionaryCandidates) {
    const unique = [...new Set([...candidates, ...dictionaryCandidates].map((item) => item.trim()).filter(Boolean))];

    return unique
        .map((candidate) => {
            let score = 0;

            if (dictionaryCandidates.includes(candidate)) {
                score += 5;
            }

            if (candidate.length >= 3 && candidate.length <= 20) {
                score += 2;
            }

            if (/^[\p{L}\p{M}'’\-\s]+$/u.test(candidate)) {
                score += 1;
            }

            if (candidate.toLowerCase().includes(word.toLowerCase())) {
                score -= 2;
            }

            return { candidate, score };
        })
        .sort((a, b) => b.score - a.score)
        .map((item) => item.candidate);
}

async function buildWordDetailsWithOptionB(word) {
    const fallback = { suggestions: ['—'], examples: [] };
    const dictionaryCandidates = translationDictionary.get(word.toLowerCase()) || [];
    const providerResults = [...dictionaryCandidates];

    try {
        const myMemoryResults = await requestMyMemoryTranslation(word);
        providerResults.push(...myMemoryResults);
    } catch (error) {
        console.warn('MyMemory failed:', error);
    }

    const ranked = rankTranslationCandidates(word, providerResults, dictionaryCandidates).slice(0, 4);

    if (ranked.length === 0) {
        return fallback;
    }

    return {
        suggestions: ranked,
        examples: []
    };
}

function getWordFromCaret() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return '';
    }

    const range = selection.getRangeAt(0);
    if (!textEditor.contains(range.startContainer)) {
        return '';
    }

    const node = range.startContainer;
    if (node.nodeType !== Node.TEXT_NODE) {
        return '';
    }

    const text = node.textContent || '';
    if (!text.trim()) {
        return '';
    }

    const offset = range.startOffset;
    const tokenRegex = /[\p{L}\p{M}'’-]+/gu;
    let match;

    while ((match = tokenRegex.exec(text)) !== null) {
        const start = match.index;
        const end = start + match[0].length;

        if (offset >= start && offset <= end) {
            return match[0].toLowerCase();
        }
    }

    return '';
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
        const caretOffset = getCaretOffsetWithinEditor();
        applyHighlights();
        setCaretOffsetWithinEditor(caretOffset);
        updateCharCount();

        if (translationDebounceTimer) {
            clearTimeout(translationDebounceTimer);
        }

        const sourceText = getPlainText();
        translationDebounceTimer = setTimeout(() => {
            translationRequestId += 1;
            translateGermanTextToUkrainian(sourceText, translationRequestId);
        }, 600);
    });

    textEditor.addEventListener('dblclick', async () => {
        const selectedFromRange = getWordsFromCurrentSelection();
        const words = selectedFromRange.length > 0 ? selectedFromRange : [getWordFromCaret()].filter(Boolean);

        if (words.length === 0) {
            return;
        }

        const uniqueWords = [...new Set(words)];
        const addedWords = [];
        const removedWords = [];

        for (const word of uniqueWords) {
            if (selectedWords.has(word)) {
                selectedWords.delete(word);
                wordDetails.delete(word);
                removedWords.push(word);
                continue;
            }

            selectedWords.set(word, 1);
            addedWords.push(word);
        }

        applyHighlights();
        updateSelectionTable();

        for (const word of addedWords) {
            try {
                const details = await buildWordDetailsWithOptionB(word);
                const sentenceExample = findSentenceForWord(getPlainText(), word);
                if (sentenceExample && !details.examples.includes(sentenceExample)) {
                    details.examples = [sentenceExample, ...details.examples];
                }
                const currentDetails = wordDetails.get(word) || { suggestions: [], examples: [] };
                wordDetails.set(word, {
                    ...details,
                    examples: (currentDetails.examples || []).length > 0
                        ? currentDetails.examples
                        : details.examples
                });
            } catch (error) {
                const sentenceExample = findSentenceForWord(getPlainText(), word);
                const currentDetails = wordDetails.get(word) || { suggestions: [], examples: [] };
                wordDetails.set(word, {
                    suggestions: ['—'],
                    examples: (currentDetails.examples || []).length > 0
                        ? currentDetails.examples
                        : (sentenceExample ? [sentenceExample] : [])
                });
            }
        }

        updateSelectionTable();

        if (selectionHint) {
            const messages = [];
            if (addedWords.length > 0) {
                messages.push(`Добавлено: ${addedWords.join(', ')}`);
            }
            if (removedWords.length > 0) {
                messages.push(`Удалено: ${removedWords.join(', ')}`);
            }
            selectionHint.textContent = messages.length > 0
                ? messages.join(' · ')
                : 'Двойной клик по слову — добавить в словарь.';
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

if (copyNojiBtn) {
    copyNojiBtn.addEventListener('click', async () => {
        const importText = buildNojiImportText();
        if (!importText.trim()) {
            if (saveStatus) {
                saveStatus.textContent = 'Keine Wörter für den Noji-Import vorhanden.';
            }
            return;
        }

        const originalLabel = copyNojiBtn.textContent;
        copyNojiBtn.disabled = true;

        try {
            await navigator.clipboard.writeText(importText);
            copyNojiBtn.textContent = 'Kopiert!';
            if (saveStatus) {
                saveStatus.textContent = 'Noji-Importformat kopiert (Vorderseite↹Rückseite, Zeilenumbruch pro Karte).';
            }
        } catch (error) {
            if (saveStatus) {
                saveStatus.textContent = 'Noji-Import konnte nicht in die Zwischenablage kopiert werden.';
            }
        } finally {
            setTimeout(() => {
                copyNojiBtn.disabled = false;
                copyNojiBtn.textContent = originalLabel;
            }, 1200);
        }
    });
}

if (saveExpressionBtn) {
    saveExpressionBtn.addEventListener('click', async () => {
        clearError();
        if (saveStatus) {
            saveStatus.textContent = '';
        }

        const expression = getExpressionFromCurrentSelection();
        if (!expression) {
            if (saveStatus) {
                saveStatus.textContent = 'Спочатку виділи вираз у тексті.';
            }
            return;
        }

        const normalizedExpression = expression.toLowerCase();
        selectedWords.set(normalizedExpression, (selectedWords.get(normalizedExpression) || 0) + 1);

        const originalLabel = saveExpressionBtn.textContent;
        saveExpressionBtn.disabled = true;
        saveExpressionBtn.textContent = 'Зберігаю...';

        try {
            const details = await buildWordDetailsWithOptionB(normalizedExpression);
            const sentenceExample = findSentenceForWord(getPlainText(), normalizedExpression);
            if (sentenceExample && !details.examples.includes(sentenceExample)) {
                details.examples = [sentenceExample, ...details.examples];
            }
            const currentDetails = wordDetails.get(normalizedExpression) || { suggestions: [], examples: [] };
            wordDetails.set(normalizedExpression, {
                ...details,
                examples: (currentDetails.examples || []).length > 0
                    ? currentDetails.examples
                    : details.examples
            });
        } catch (error) {
            const sentenceExample = findSentenceForWord(getPlainText(), normalizedExpression);
            const currentDetails = wordDetails.get(normalizedExpression) || { suggestions: [], examples: [] };
            wordDetails.set(normalizedExpression, {
                suggestions: ['—'],
                examples: (currentDetails.examples || []).length > 0
                    ? currentDetails.examples
                    : (sentenceExample ? [sentenceExample] : [])
            });
        } finally {
            saveExpressionBtn.disabled = false;
            saveExpressionBtn.textContent = originalLabel;
        }

        applyHighlights();
        updateSelectionTable();

        if (selectionHint) {
            selectionHint.textContent = `Збережено вираз: ${normalizedExpression}`;
        }

        const payload = buildSelectedWordsPayload();
        if (payload.length === 0) {
            if (saveStatus) {
                saveStatus.textContent = 'Es sind noch keine markierten Wörter zum Speichern vorhanden.';
            }
            return;
        }

        saveExpressionBtn.disabled = true;
        saveExpressionBtn.textContent = 'Speichere...';

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
            saveExpressionBtn.disabled = false;
            saveExpressionBtn.textContent = originalLabel;
        }
    });
}
</script>
</body>
</html>
