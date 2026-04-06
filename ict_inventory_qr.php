<?php
session_start();
require_once('db.php');

if (!isset($_GET['id'])) {
    echo "No Asset ID provided.";
    exit;
}

$asset_id = (int)$_GET['id'];

// Fetch Asset Details
$sql = "SELECT inventory, employee_name, department, serial_model, status FROM assets WHERE id = $asset_id LIMIT 1";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "Asset not found!";
    exit;
}

$asset = mysqli_fetch_assoc($result);

// Prepare the text to be converted into QR Code (Using straight string)
$inventory = $asset['inventory'];
$user_name = $asset['employee_name'] ? $asset['employee_name'] : "Unassigned";
$dept = $asset['department'] ? $asset['department'] : "N/A";
$serial = $asset['serial_model'] ? $asset['serial_model'] : "N/A";
$status = $asset['status'];

// Creating a clean string for JavaScript
$qr_text = "Inventory: $inventory | User: $user_name | Dept: $dept | Serial: $serial | Status: $status";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print QR - <?php echo htmlspecialchars($inventory); ?></title>
    
    <!-- QR Code Generator Library (qrious) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .qr-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-align: center;
            width: 250px;
            border: 1px solid #ddd;
        }
        /* Canvas is used by qrious to draw the QR code */
        canvas {
            margin-bottom: 10px;
        }
        .qr-card h3 {
            margin: 5px 0;
            font-size: 22px;
            color: #0b2545;
        }
        .qr-card p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }
        .btn-group {
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            font-weight: bold;
        }
        .btn-print {
            background-color: #0d6efd;
            margin-right: 10px;
        }
        .btn-close {
            background-color: #6c757d;
        }
        @media print {
            body { background: white; height: auto; display: block; padding: 20px; }
            .btn-group { display: none; }
            .qr-card { box-shadow: none; border: 1px dashed #ccc; margin: 0; }
        }
    </style>
</head>
<body>

    <div class="qr-card">
        <!-- Canvas for QR Code -->
        <canvas id="qrcode"></canvas>
        
        <h3><?php echo htmlspecialchars($inventory); ?></h3>
        <p>SCL IT Management</p>
    </div>

    <div class="btn-group">
        <button class="btn btn-print" onclick="window.print()">Print Label</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    <script>
        // Generating QR Code using Qrious JavaScript Library
        var qr = new QRious({
            element: document.getElementById('qrcode'),
            value: "<?php echo addslashes($qr_text); ?>",
            size: 180, // Size of the QR code
            background: 'white',
            foreground: 'black'
        });
    </script>

</body>
</html>
