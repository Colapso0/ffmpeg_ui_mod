<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Dashboard Streaming - FFmpeg Empresarial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <style>
        body { font-size: 0.9rem; }
        .container { max-width: 1200px; }
        .form-inline .form-control, .form-inline .btn, .form-inline .form-check { margin-bottom: 0.5rem; margin-right: 0.5rem; }
        .table-sm td, .table-sm th { padding: 0.3rem; }
        .table thead th { vertical-align: middle; }
        .text-truncate { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        [data-toggle="tooltip"] { cursor: help; }
        .copy-btn { cursor: pointer; color: #007bff; margin-left: 5px; }
        .copy-btn:hover { text-decoration: underline; }
        pre { background: #f8f9fa; padding: 1rem; max-height: 250px; overflow: auto; border: 1px solid #e9ecef; border-radius: .25rem; font-size: 0.8rem; }
        .player-container { border: 1px solid #dee2e6; border-radius: .25rem; padding: 10px; background-color: #fff; }
        .video-js { margin-top: 10px; }
        .stats-card { background-color: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; border-radius: 5px; }
        .stats-card h6 { margin-bottom: 0; }
        .stats-card p { font-size: 1.2rem; font-weight: bold; }
    </style>
</head>
