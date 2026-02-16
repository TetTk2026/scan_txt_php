# Buchseiten-Scanner (PHP + JavaScript + Bootstrap)

Diese App bietet:

1. **Schritt 1:** Foto einer Buchseite hochladen.
2. **Schritt 2:** OCR-Umwandlung des Fotos in editierbaren Text.

## Voraussetzungen

- PHP 8.1+
- Tesseract OCR installiert (inkl. deutscher Sprachdaten)

Beispiel (Ubuntu/Debian):

```bash
sudo apt update
sudo apt install -y tesseract-ocr tesseract-ocr-deu
```

## Starten

```bash
php -S 0.0.0.0:8000
```

Dann im Browser öffnen:

- `http://localhost:8000`

## Hinweise

- Erlaubte Uploads: JPG, PNG, WEBP
- Maximale Dateigröße: 8 MB
- Oberfläche ist mit Bootstrap responsiv für Desktop und Mobile.
