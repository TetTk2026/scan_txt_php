<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вставка текста</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        form {
            width: min(900px, 92vw);
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        textarea {
            width: 100%;
            min-height: 320px;
            resize: vertical;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            font: inherit;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
<form>
    <label for="text">Вставь текст</label>
    <textarea id="text" name="text" placeholder="Вставь сюда текст..."></textarea>
</form>
</body>
</html>
