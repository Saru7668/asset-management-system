<?php
// Session start ??? (??? ??? ?? ??? ????)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCL Asset Management System</title>
    
    <!-- ? Favicon -->
    <link rel="icon" type="image/png" href="logo_login.png">
    <link rel="shortcut icon" type="image/png" href="logo_login.png">

    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==========================================
           ?? GLOBAL PAGE LOADER CSS
        ========================================== */
        #global-loader {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: #ffffff; 
            z-index: 999999; 
            display: flex; justify-content: center; align-items: center;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
        }

        #global-loader.hide-loader { opacity: 0; visibility: hidden; }

        .loader-content { position: relative; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }

        .loader-logo {
            width: 70px; height: auto; z-index: 2;
            animation: pulseLogo 1.5s infinite ease-in-out;
        }

        .loader-ring {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 120px; height: 120px; border-radius: 50%; border: 4px solid transparent;
            border-top-color: #0d6efd; border-bottom-color: #ffc107;
            animation: spinRing 1.2s linear infinite; z-index: 1;
        }

        .loader-text {
            margin-top: 45px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-weight: 600; font-size: 15px; color: #1a2a3a; letter-spacing: 2px;
            animation: flashText 1.5s infinite linear;
        }

        @keyframes pulseLogo { 0% { transform: scale(0.9); opacity: 0.8; } 50% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(0.9); opacity: 0.8; } }
        @keyframes spinRing { 0% { transform: translate(-50%, -50%) rotate(0deg); } 100% { transform: translate(-50%, -50%) rotate(360deg); } }
        @keyframes flashText { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
    </style>
</head>
<body>

<!-- ?? GLOBAL PAGE LOADER HTML -->
<div id="global-loader">
    <div class="loader-content">
        <img src="logo_login.png" alt="SCL Logo" class="loader-logo" onerror="this.src='https://via.placeholder.com/70?text=SCL'">
        <div class="loader-ring"></div>
        <div class="loader-text">LOADING...</div>
    </div>
</div>

<script>
    // ??? ???????? ??? ????? ?? ????? ???? ??? ????
    window.addEventListener('load', function() {
        var loader = document.getElementById('global-loader');
        if (loader) {
            setTimeout(function() { loader.classList.add('hide-loader'); }, 300); 
        }
    });

    // ???? ???? ?????? ??? ?? ???? ???????? ??? ????? ???? ??????
    window.addEventListener('beforeunload', function() {
        var loader = document.getElementById('global-loader');
        if (loader) { loader.classList.remove('hide-loader'); }
    });
</script>
