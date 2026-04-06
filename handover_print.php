<?php
// Error reporting ?? ??? ????? ??? ???? ????????? 500 ??? ?? ??? ??? ????? ???????? ?????
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid Request.");
}

$id = (int)$_GET['id'];

// Get asset details
$query = "SELECT * FROM assets WHERE id = $id";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Asset not found!");
}

$row = mysqli_fetch_assoc($result);

if (empty($row['employee_id'])) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>This device is not assigned to any employee currently.</h3>");
}

// Null safe data fetching (PHP 8.1+ ? ??? ?????)
$emp_id = htmlspecialchars($row['employee_id'] ?? '');
$emp_name = htmlspecialchars($row['employee_name'] ?? '');
$department = htmlspecialchars($row['department'] ?? '');
$inventory = htmlspecialchars($row['inventory'] ?? '');
$brand = htmlspecialchars($row['brand'] ?? '');
$details = htmlspecialchars($row['details'] ?? '');
$serial = htmlspecialchars($row['serial_model'] ?? '');
$date = date("F d, Y");
$year = date("Y");

// Designation fetch (Enhanced to prevent Unknown column error)
$designation = '';

if (!empty($row['designation'])) {
    $designation = htmlspecialchars($row['designation']);
} else {
    // 1. Try employees table with employee_id only to avoid "Unknown column 'emp_id'" error
    try {
        $emp_query = "SELECT designation FROM employees WHERE employee_id = '$emp_id' LIMIT 1"; 
        $emp_result = @mysqli_query($conn, $emp_query);
        
        if ($emp_result && mysqli_num_rows($emp_result) > 0) {
            $emp_row = mysqli_fetch_assoc($emp_result);
            $designation = trim($emp_row['designation'] ?? '');
        }
    } catch (Throwable $e) {
        // Silently ignore if employees table or employee_id column doesn't exist
    }
    
    // 2. Try users table if previous step failed
    if (empty($designation)) {
        try {
            $user_query = "SELECT designation FROM users WHERE username = '$emp_id' OR employee_id = '$emp_id' LIMIT 1";
            $user_result = @mysqli_query($conn, $user_query);
            
            if ($user_result && mysqli_num_rows($user_result) > 0) {
                $user_row = mysqli_fetch_assoc($user_result);
                $designation = trim($user_row['designation'] ?? '');
            }
        } catch (Throwable $e) {
            // Silently ignore if users table doesn't have these columns
        }
    }
    
    // 3. Fallback if still empty
    if (empty($designation)) {
        $designation = "Designation Missing"; 
    } else {
        $designation = htmlspecialchars($designation);
    }
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Handover Sheet - <?php echo $emp_name; ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 15px; line-height: 1.6; color: #000; background: #f0f2f5; margin: 0; padding: 0;}
        /* ?? ?????? ?????? ??? ???????? ???? */
        .page { 
            width: 210mm; 
            min-height: 297mm; 
            /* padding: 25mm 20mm; ?? ???????? ??????? ??????? ???? */
            padding: 10mm 20mm 25mm 20mm; /* Top 10mm, Right 20mm, Bottom 25mm, Left 20mm */
            margin: 20px auto; 
            border: 1px solid #ddd; 
            background: #fff; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            box-sizing: border-box;
        }
        .company-header {
            text-align: center;
            margin-top: 0; /* ???? ??? ???? ????? ????? ????? ?? ???? */
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        .company-header img {
            max-width: 50%;
            height: auto;
            max-height: 60px; /* ????? ???? ???????? ???? ???? */
        }
        .header-ref { margin-bottom: 30px; font-weight: bold; }
        .emp-details { margin-bottom: 30px; font-weight: bold; line-height: 1.5;}
        .subject { font-weight: bold; text-decoration: underline; margin-bottom: 25px; font-size: 16px;}
        .body-text { margin-bottom: 15px; text-align: justify; }
        
        .signature-area { margin-top: 100px; display: flex; justify-content: space-between; align-items: flex-end; }
        .management-sig { text-align: left; }
        .receiver-sig { text-align: right; }
        
        .line { border-bottom: 1px solid #000; display: inline-block; margin-bottom: 5px; }
        
        .print-btn-container { text-align: center; margin: 20px 0; background: #fff; padding: 15px; border-bottom: 1px solid #ccc; position: sticky; top: 0; z-index: 1000;}
        
        @media print {
            body { background: #fff; margin: 0; }
            .page { 
                margin: 0; 
                border: none; 
                box-shadow: none; 
                width: 100%; 
                min-height: 100vh; 
                /* padding: 15mm; ?? ???????? ??????? ??????? ???? */
                padding: 5mm 15mm 15mm 15mm; /* ????????? ??? ???? 5mm ????? ????? */
            }
            .print-btn-container { display: none; }
        }
    </style>
</head>
<body>

<div class="print-btn-container">
    <button onclick="window.print()" style="padding: 10px 25px; font-size: 16px; cursor: pointer; background: #0b2545; color: white; border: none; border-radius: 5px; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">Print Document
    </button>
</div>

<div class="page">

<!-- Header Image / Logo Section -->
    <div class="company-header">
        <!-- ????? ????? ???? ?? ????? ?????? ??? ??? -->
        <img src="assets/images/header_logo.png" alt="Sheltech Ceramics Ltd. Logo">
    </div>


    <div class="header-ref">
        SCL/<?php echo $year; ?>/ICT/<?php echo $inventory; ?><br>
        <?php echo $date; ?>
    </div>

    <div class="emp-details">
        Name: <?php echo $emp_name; ?><br>
        ID: <?php echo $emp_id; ?><br>
        Designation: <?php echo $designation; ?><br>
        Department: <?php echo $department; ?><br>
        Company: Sheltech Ceramics Ltd.
    </div>

    <div class="subject">
        Subject: Handing over the ICT Equipment
    </div>

    <div class="body-text">
        Dear Concern,
    </div>

    <div class="body-text">
        The management has decided to allot you a <b><?php echo trim("$brand $details"); ?>, Serial: <?php echo $serial; ?></b>, Power adapter, and Carrying case, considering the necessity of your job responsibilities with effect from Today.
    </div>

    <div class="body-text">
        Please note that your allotted Laptop is an asset belongs to the company. We expect that you will take proper care of it and make the best possible use for the betterment of the company.
    </div>

    <div class="body-text">
        You will be fully responsible for any sort of damage, or malfunction of the Laptop due to mishandling or if it is lost or stolen. Therefore, you are advised to ensure the safety of the Laptop.
    </div>

    <div class="body-text">
        Thanking You,
    </div>

    <div class="signature-area">
        <div class="management-sig">
            <b>On behalf of the Management</b><br><br><br><br>
            <span class="line" style="width: 250px;"></span><br>
            <b>Signature & Seal</b>
        </div>
        
        <div class="receiver-sig">
            <br><br><br><br>
            <span class="line" style="width: 200px;"></span><br>
            <b>Received by</b>
        </div>
    </div>

    <div style="margin-top: 60px; font-size: 14px;">
        Copy to: HR Personal File <span class="line" style="width: 250px;"></span>
    </div>
</div>

</body>
</html>
