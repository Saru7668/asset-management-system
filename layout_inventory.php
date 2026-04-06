<?php
if (!isset($page_title)) {
    $page_title = 'SCL AMS';
}

if (!isset($page_header_icon)) {
    $page_header_icon = 'fas fa-layer-group';
}

if (!isset($page_header_title)) {
    $page_header_title = 'Inventory Page';
}

if (!isset($page_header_subtitle)) {
    $page_header_subtitle = '';
}

if (!isset($page_top_title)) {
    $page_top_title = $page_header_title;
}

if (!isset($page_top_actions)) {
    $page_top_actions = '';
}

if (!isset($page_container_class) || trim($page_container_class) === '') {
    $page_container_class = 'dashboard-container';
}

if (!isset($body_content)) {
    $body_content = '';
}

if (!isset($body_extra_top)) {
    $body_extra_top = '';
}

if (!isset($extra_css)) {
    $extra_css = '';
}

if (!isset($extra_head)) {
    $extra_head = '';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <script>
        (function () {
            const savedMode = localStorage.getItem('theme-mode') || 'auto';

            function getAutoTheme() {
                const hour = new Date().getHours();
                return (hour >= 6 && hour < 18) ? 'light' : 'dark';
            }

            const appliedTheme = savedMode === 'auto' ? getAutoTheme() : savedMode;
            document.documentElement.setAttribute('data-theme', appliedTheme);
            document.documentElement.setAttribute('data-theme-mode', savedMode);
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php echo $extra_head; ?>

    <style>
        :root {
            --bg-main: linear-gradient(135deg, #f4f7f6 0%, #e8f4f8 100%);
            --bg-card: #ffffff;
            --bg-card-soft: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-heading: #0b2545;
            --border-color: #e2e8f0;
            --shadow-main: 0 8px 25px rgba(0,0,0,0.08);

            --brand-primary: #0b2545;
            --brand-secondary: #1e3a8a;
            --brand-accent: #eab308;

            --sidebar-bg: linear-gradient(180deg, #0b2545 0%, #081c35 100%);
            --sidebar-top-bg: #061830;
            --sidebar-text: #dbeafe;
            --sidebar-hover-text: #fde68a;
            --sidebar-hover-icon: #facc15;
            --sidebar-active-text: #ffffff;
            --sidebar-border: #1e3a5f;

            --header-bg: rgba(11,37,69,0.95);
            --header-text: #ffffff;

            --input-bg: #ffffff;
            --input-text: #0f172a;
            --input-border: #cbd5e1;
            --input-focus: #f59e0b;

            --table-head-bg: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
            --table-head-text: #ffffff;
            --table-row-bg: #ffffff;
            --table-border: #e9ecef;

            --overlay-bg: rgba(0,0,0,0.5);
        }

        html[data-theme="dark"] {
            --bg-main: linear-gradient(135deg, #020617 0%, #0f172a 45%, #111827 100%);
            --bg-card: #0f172a;
            --bg-card-soft: #111827;
            --text-main: #e5edf7;
            --text-muted: #94a3b8;
            --text-heading: #f8fafc;
            --border-color: #243244;
            --shadow-main: 0 12px 30px rgba(0,0,0,0.35);

            --brand-primary: #0b2545;
            --brand-secondary: #1d4ed8;
            --brand-accent: #eab308;

            --sidebar-bg: linear-gradient(180deg, #020617 0%, #0b2545 100%);
            --sidebar-top-bg: #020b18;
            --sidebar-text: #cbd5e1;
            --sidebar-hover-text: #fde68a;
            --sidebar-hover-icon: #facc15;
            --sidebar-active-text: #ffffff;
            --sidebar-border: #22324a;

            --header-bg: rgba(2,6,23,0.92);
            --header-text: #ffffff;

            --input-bg: #0b1220;
            --input-text: #e5edf7;
            --input-border: #334155;
            --input-focus: #facc15;

            --table-head-bg: linear-gradient(135deg, #0b1220 0%, #1e3a8a 100%);
            --table-head-text: #f8fafc;
            --table-row-bg: #0f172a;
            --table-border: #243244;

            --overlay-bg: rgba(0,0,0,0.65);
        }

        html, body {
            min-height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background: var(--bg-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-main);
            transition: background 0.35s ease, color 0.35s ease;
        }

        .main-header,
        .dashboard-card,
        .mini-card,
        .info-box,
        .form-card,
        .assign-card,
        .table-card,
        .recent-table,
        .sidebar,
        .top-mobile-bar,
        .top-mobile-toggle,
        .sidebar-close,
        input,
        select,
        textarea,
        button,
        a {
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-container-wide {
            max-width: 1500px;
            margin: 0 auto;
        }

        .dashboard-container-xl {
            max-width: 1600px;
            margin: 0 auto;
        }

        .main-header {
            background: var(--header-bg);
            color: var(--header-text);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: var(--shadow-main);
            border: 1px solid rgba(255,255,255,0.04);
        }

        .main-header h2 {
            margin-bottom: 8px;
            font-weight: 700;
        }

        .page-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
        }

        .page-subtle {
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
        }

        .page-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .mobile-theme-switcher {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mobile-theme-switcher select {
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.08);
            color: #fff;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            font-weight: 600;
            outline: none;
        }

        .mobile-theme-switcher select option {
            color: #111827;
        }

        .dashboard-card,
        .mini-card,
        .info-box,
        .form-card,
        .assign-card,
        .table-card,
        .recent-table {
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: var(--shadow-main);
            border-top: 4px solid var(--brand-primary);
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        .dashboard-card,
        .mini-card,
        .info-box {
            padding: 18px 20px;
        }

        .form-card,
        .assign-card,
        .table-card,
        .recent-table {
            overflow: hidden;
        }

        .form-card-header,
        .assign-card-header {
            background: var(--brand-primary);
            color: white;
            padding: 18px 22px;
        }

        .form-card-body,
        .assign-card-body {
            padding: 24px;
            background: var(--bg-card);
            color: var(--text-main);
        }

        .recent-table .table {
            color: var(--text-main);
            margin-bottom: 0;
        }

        .recent-table .table thead th {
            background: var(--table-head-bg);
            color: var(--table-head-text);
            border: none;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .recent-table .table tbody tr {
            background: var(--table-row-bg);
        }

        .recent-table .table tbody td {
            border-color: var(--table-border);
            vertical-align: middle;
            padding: 15px 12px;
            color: var(--text-main);
            background: var(--table-row-bg);
        }

        .top-mobile-bar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 72px;
            background: linear-gradient(135deg, var(--brand-primary) 0%, #061830 100%);
            z-index: 1035;
            padding: 0 18px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.18);
            border-bottom: 2px solid var(--sidebar-border);
        }

        .top-mobile-brand {
            color: #fff;
            font-size: 19px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.3px;
        }

        .top-mobile-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .top-mobile-toggle {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 8px;
            width: 46px;
            height: 42px;
            color: #fff;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.22);
        }

        .top-mobile-toggle:hover {
            background: rgba(255,255,255,0.15);
        }

        .sidebar {
            width: 280px;
            position: fixed;
            top: 0;
            left: -280px;
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1050;
            transition: left 0.35s ease, background 0.35s ease;
            box-shadow: 4px 0 20px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-close {
            position: absolute;
            top: 17px;
            right: 14px;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: white;
            font-size: 16px;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1100;
            transition: all 0.3s ease;
        }

        .sidebar-close:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #fff;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--overlay-bg);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .main-content {
            min-height: 100vh;
            padding: 88px 16px 20px;
            transition: padding-left 0.3s ease;
        }

        .fade-in {
            animation: fadeInPage 0.6s ease-in-out;
        }

        @keyframes fadeInPage {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        input, select, textarea, .form-control, .form-select {
            background: var(--input-bg) !important;
            color: var(--input-text) !important;
            border-color: var(--input-border) !important;
        }

        input:focus, select:focus, textarea:focus,
        .form-control:focus, .form-select:focus {
            border-color: var(--input-focus) !important;
            box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.15) !important;
        }

        .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            --bs-table-border-color: var(--table-border);
        }

        .text-muted,
        small,
        .small {
            color: var(--text-muted) !important;
        }

        @media (min-width: 992px) {
                .top-mobile-bar {
                    display: none !important;
                }
            
                .sidebar-close {
                    display: none !important;
                }
            
                .sidebar {
                    left: 0 !important;
                    position: fixed;
                    width: 280px;
                }
            
                .main-content {
                    padding: 20px 20px 20px 300px;
                }
            }

        @media (max-width: 991px) {
            html, body {
                padding: 0;
            }

            .top-mobile-bar {
                display: flex;
            }

            .main-header {
                display: none;
            }

            .main-content {
                padding: 92px 15px 15px 15px;
            }

            .page-title {
                font-size: 24px;
            }

            .page-topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .page-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .form-card-body,
            .assign-card-body {
                padding: 18px 15px;
            }
        }

        <?php echo $extra_css; ?>
    </style>
</head>
<body>

<?php echo $body_extra_top; ?>

<div class="top-mobile-bar">
    <div class="top-mobile-brand">
        <i class="fas fa-building"></i>
        <span>SCL AMS</span>
    </div>

    <div class="top-mobile-actions">
        <div class="mobile-theme-switcher">
            <select id="themeModeMobile" onchange="setThemeMode(this.value)">
                <option value="light">Light</option>
                <option value="dark">Dark</option>
                <option value="auto">Auto</option>
            </select>
        </div>

        <button class="top-mobile-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="sidebar" id="sidebar">
    <button class="sidebar-close" onclick="closeSidebar()">
        <i class="fas fa-times"></i>
    </button>

    <?php require_once('sidebar.php'); ?>
</div>

<div class="main-content fade-in">
    <div class="<?php echo htmlspecialchars($page_container_class); ?>">

        <div class="main-header">
            <h2 class="mb-2 fw-bold">
                <i class="<?php echo htmlspecialchars($page_header_icon); ?> me-2"></i>
                <?php echo htmlspecialchars($page_header_title); ?>
            </h2>
            <?php if (!empty($page_header_subtitle)): ?>
                <p class="mb-0 opacity-90"><?php echo htmlspecialchars($page_header_subtitle); ?></p>
            <?php endif; ?>
        </div>

        <div class="page-topbar">
            <h4 class="page-title">
                <i class="<?php echo htmlspecialchars($page_header_icon); ?> me-2"></i><?php echo htmlspecialchars($page_top_title); ?>
            </h4>

            <div class="page-actions">
                <?php if (!empty($page_top_actions)): ?>
                    <?php echo $page_top_actions; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php echo $body_content; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let sidebarOpen = false;

function getAutoTheme() {
    const hour = new Date().getHours();
    return (hour >= 6 && hour < 18) ? 'light' : 'dark';
}

function applyTheme(mode) {
    const appliedTheme = mode === 'auto' ? getAutoTheme() : mode;
    document.documentElement.setAttribute('data-theme', appliedTheme);
    document.documentElement.setAttribute('data-theme-mode', mode);

    const desktopSelect = document.getElementById('themeModeDesktop');
    const mobileSelect = document.getElementById('themeModeMobile');

    if (desktopSelect) desktopSelect.value = mode;
    if (mobileSelect) mobileSelect.value = mode;

    if (typeof updateThemeButtons === 'function') {
        updateThemeButtons();
    }
}

function setThemeMode(mode) {
    localStorage.setItem('theme-mode', mode);
    applyTheme(mode);
}

function initThemeMode() {
    const savedMode = localStorage.getItem('theme-mode') || 'auto';
    applyTheme(savedMode);
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    sidebarOpen = true;
    sidebar.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    sidebarOpen = false;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebarOpen) {
        closeSidebar();
    }
});

window.addEventListener('resize', function() {
    if (window.innerWidth >= 992) {
        closeSidebar();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    initThemeMode();

    setInterval(function () {
        const currentMode = localStorage.getItem('theme-mode') || 'auto';
        if (currentMode === 'auto') {
            applyTheme('auto');
        }
    }, 60000);
});
</script>
</body>
</html>
