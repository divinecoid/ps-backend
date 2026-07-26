<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SplFileObject;

class LogViewerController extends Controller
{
    public function index(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');
        
        if ($request->query('clear') === '1' || $request->query('clear') === 'true') {
            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }
            return redirect('/logs');
        }

        $content = 'laravel.log does not exist.';
        if (file_exists($logPath)) {
            $limit = (int) $request->query('lines', 2000);
            $limit = $limit > 0 ? $limit : 2000;
            
            $fileSize = filesize($logPath);
            if ($fileSize > 2 * 1024 * 1024) {
                $file = new SplFileObject($logPath, 'r');
                $file->seek(PHP_INT_MAX);
                $totalLines = $file->key();
                
                $start = max(0, $totalLines - $limit);
                $file->seek($start);
                $lines = [];
                while (!$file->eof()) {
                    $lines[] = $file->current();
                    $file->next();
                }
                $content = implode("", $lines);
            } else {
                $content = file_get_contents($logPath);
            }
        }
        
        $escapedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Laravel Log Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace; background: #121212; color: #e0e0e0; padding: 20px; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; color: #fff; }
        .btn { background: #e53935; color: white; border: none; padding: 8px 16px; font-weight: bold; cursor: pointer; border-radius: 4px; font-family: inherit; }
        .btn:hover { background: #c62828; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; font-size: 13px; line-height: 1.5; border: 1px solid #2d2d2d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Laravel Log Viewer</h1>
            <form method="GET" action="/logs" onsubmit="return confirm('Clear all logs?');">
                <input type="hidden" name="clear" value="true">
                <button type="submit" class="btn">Clear Log</button>
            </form>
        </div>
        <pre>{$escapedContent}</pre>
    </div>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
