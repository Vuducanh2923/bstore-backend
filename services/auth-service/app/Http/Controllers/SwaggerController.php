<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SwaggerController extends Controller
{
    public function json(): JsonResponse
    {
        return response()->json(config('openapi'));
    }

    public function ui(): Response
    {
        $title = htmlspecialchars(
            (string) data_get(config('openapi'), 'info.title', config('app.name').' API Docs'),
            ENT_QUOTES,
            'UTF-8'
        );
        $specUrl = json_encode(route('swagger.openapi'), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; background: #f6f8fb; }
        #swagger-ui { max-width: 1280px; margin: 0 auto; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.addEventListener('load', () => {
            window.ui = SwaggerUIBundle({
                url: {$specUrl},
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: 'StandaloneLayout'
            });
        });
    </script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
