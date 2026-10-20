<?php

// Isolated browser fixture: never serve the repository root, app, settings or user data.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__.'/search-hotfix.html');
} elseif (preg_match('~^/ui/chat/(chat-controller|chat-api|chat-images|chat-search|chat-search-dog)\.js$~D', $path, $match)) {
    header('Content-Type: text/javascript; charset=UTF-8');
    readfile(__DIR__.'/../../public/ui/chat/'.$match[1].'.js');
} elseif ($path === '/ui/chat/chat-search-dog.css') {
    header('Content-Type: text/css; charset=UTF-8');
    readfile(__DIR__.'/../../public/ui/chat/chat-search-dog.css');
} else {
    http_response_code(404);
}
