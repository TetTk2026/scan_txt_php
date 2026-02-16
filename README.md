# Buchseiten-Scanner (PHP + JavaScript + Bootstrap)

Diese App bietet:

1. **Schritt 1:** Foto einer Buchseite auswählen.
2. **Schritt 2:** OCR-Umwandlung direkt im Browser (Client-seitig).
3. **Automatische Textverbesserung:** Zeilenumbrüche, Worttrennungen und Absatzstruktur werden nach OCR bereinigt.

## Voraussetzungen

- PHP 8.1+
- Internetzugang für CDN-Assets (Bootstrap + Tesseract.js)

> Es wird **kein** Tesseract auf dem Server benötigt, da die Texterkennung im Browser ausgeführt wird.

## Starten

```bash
php -S 0.0.0.0:8000
```

Dann im Browser öffnen:

- `http://localhost:8000`

## Hinweise

- Empfohlene Uploads: JPG, PNG, WEBP
- Maximale Dateigröße: 8 MB
- OCR-Sprachen: Deutsch + Englisch (`deu+eng`)
- Oberfläche ist mit Bootstrap responsiv für Desktop und Mobile.
