<?php
include(__DIR__.'/functions.php'); // main functions 
session_start();

//if (!($_SESSION['usertype']=='manager')||($_SESSION['usertype']=='secretary')) 
if ((!isset($_SESSION['usertype']))&&(!(subnet_login()==1))) // ΠΡΟΒΛΗΜΑ: ΔΕΝ κρατάει το session όταν γίνει secretary_auto_login
{
    //print_r($_SESSION);
    //echo 'SubnetLogin='.subnet_login();
    die("Δεν έχετε δικαίωμα πρόσβασης.");
}

if (isset($_GET['f'])) {
    // 1. Καθαρισμός του ονόματος αρχείου για λόγους ασφαλείας (αποτροπή Path Traversal π.χ. ../../)
    $filename = basename($_GET['f']);
    
    // 2. Ορισμός της λίστας επιτρεπόμενων φακέλων (Whitelist)
    $allowed_dirs = [
        'main' => '/uploads/',
        'app'  => '/applications/uploads/'
    ];

    // 3. Έλεγχος φακέλου: Αν το 'dir' δεν υπάρχει στο URL ή είναι άκυρο, η προεπιλογή είναι το 'main'
    $dir_key = (isset($_GET['dir']) && array_key_exists($_GET['dir'], $allowed_dirs)) ? $_GET['dir'] : 'main';
    
    // 4. Σύνθεση του τελικού, απόλυτου και ασφαλούς path
    $filepath = __DIR__ . $allowed_dirs[$dir_key] . $filename;
    $download_name=$filename;

    // 5. Έλεγχος αν το αρχείο υπάρχει πράγματι στον server
    if (file_exists($filepath)) {
        
        // 6. Απόλυτος καθαρισμός του buffer. 
        // Διαγράφει τυχόν "αόρατα" κενά διαστήματα που θα μπορούσαν να αλλοιώσουν το Excel.
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 7. Ορισμός του τελικού ονόματος λήψης (χρησιμοποιεί το 'n' αν δοθεί, αλλιώς το κανονικό όνομα)
        $download_name = isset($_GET['n']) ? basename($_GET['n']) : $filename;

        // 8. Αποστολή Headers στον Browser για να αναγκάσει το κατέβασμα
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream'); // Γενικός και ασφαλής τύπος
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        
        // 9. Διάβασμα και αποστολή του αρχείου στον χρήστη
        readfile($filepath);
        logdata('Έγινε λήψη του αρχείου '.$download_name.' (filename: '.$filename.') από την IP '.get_ip());
        exit;
    } else {
        // Αν το αρχείο δεν υπάρχει (Δεν τυπώνουμε το path για λόγους ασφαλείας)
        logdata('Το αρχείο '.$download_name.' (filename: '.$filename.') δεν βρέθηκε για λήψη από την IP '.get_ip());
        die("Το αρχείο δεν βρέθηκε στον server.");
    }
} else {
    die("Μη έγκυρο αίτημα. Λείπει η παράμετρος του αρχείου.");
}
?>