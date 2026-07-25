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
                return response('Log cleared successfully.');
            }
            return response('Log file does not exist to clear.');
        }

        if (!file_exists($logPath)) {
            return response('laravel.log does not exist.', 404);
        }
        
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
        
        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
