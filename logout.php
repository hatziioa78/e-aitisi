<?php
session_start();
require_once(__DIR__.'/functions.php');

// 1. Καταγραφή στα Logs 
if(isset($_SESSION['userdatas']['AFM'])) {
    $userdatas = afm_data($_SESSION['userdatas']['AFM']);
    logdata('ΑΠΟΣΥΝΔΕΣΗ Χρήστη ' . $userdatas['fullname']);    
}

// 2. Πλήρης καθαρισμός συνεδρίας
$_SESSION = array();
session_destroy();

// 3. Δημιουργία του URL επιστροφής (Κωδικοποιημένο)
$my_app = urlencode($srv_conf['CAS Configuration']['client_service_name']);
$cas_url = "https://sso.sch.gr/logout?service=" . $my_app;
if($srv_conf['CAS Configuration']['client_service_name']==''){die('no client service name defined');}
$after_logout_url = $srv_conf['CAS Configuration']['client_service_name'];

// ΕΠΕΙΔΗ το sso.sch.gr δεν έχει ενεργοποιημένη επιστροφή χρησιμοποιείται απλό page load
$cas_url =$srv_conf['CAS Configuration']['client_service_name'];

if(!isset($_GET['local_user'])){$after_logout_url=$cas_url;}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="referrer" content="no-referrer">
    <meta http-equiv="refresh" content="0;url=<?php echo $after_logout_url; ?>">
</head>
<body style="text-align:center; font-family:sans-serif; margin-top:100px; color:#555;">
    <h2>Ολοκλήρωση αποσύνδεσης...</h2><br><h4> Παρακαλώ περιμένετε να επιβεβαιωθεί από το <br>Πανελλήνιο Σχολικό Δίκτυο (sch.gr)</h4>
</body>
</html>