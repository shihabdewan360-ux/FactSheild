<?php
    // 1. Get the current page name without the '.php' extension (e.g., 'about')
    $pageName = basename($_SERVER['PHP_SELF'], '.php');

    // 2. Map the correct CSS file (Handling the one naming exception we have)
    if ($pageName === 'blockchain-explorer') {
        $cssFileName = 'explorer.css';
    } else {
        // For index, verify, about, heatmap, discover, results, journal
        $cssFileName = $pageName . '.css'; 
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FACTSHIELD - Know What's Real</title>
    
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <link rel="stylesheet" href="/assets/css/<?= $cssFileName ?>">
</head>
<body>