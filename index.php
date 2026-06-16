<?php
$useragent=$_SERVER['HTTP_USER_AGENT'];
/*
if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)))
{
    echo '
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Open Graph meta tags -->
        <meta property="og:title" content="e-Aitisi @ Dide Florinas" />
        <meta property="og:description" content="Subbin online forms to dide florina." />
        <meta property="og:image" content="https://aitisi-dide.flo.sch.gr/images/aitisi-dide_welcome.png" />
        <meta property="og:type" content="aitisi" />
        <meta property="og:url" content="https://aitisi-dide.flo.sch.gr/" />

        <title>e-Αίτηση</title>
        <link rel="icon" type="./images/favicon.ico" href="/images/favicon.ico">
        <link href="./css/bootstrap.min.css" rel="stylesheet">
        <link href="./css/main.css?'.time().'" rel="stylesheet">
        <script src="./js/bootstrap.bundle.min.js" ></script>
        <script src="./js/main.js?'.time().'" ></script>
        <!--
        <script src="https://unpkg.com/imask"></script>
        -->
        <script src="./js/imask.js"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    </head>
    <body>

        <div class="container-fluid sticky-top bg-info" style="text-align:center;width: 100%;">
            <section style="color:red;font-weight:bold; font-size: 32px;text-align: center;">
                Δ.Δ.Ε. Φλώρινας - Σύστημα Online Αιτήσεων <div style="float:right;color:darkblue;font-size:40%;font-style:italic;font-weight:none;">(version 1.22)</div><br>
            </section>
            <div id="slide_nav_menu" style="clear:both;width 100%;" class="slide-up">
                <div id="navbar">
               
                </div>
            </div>
        </div>              
    
        <iframe id="sch_logout" style="display:none;" src="#"></iframe>
        <div id="fade_div" class="overlay" style="display:none";></div>
        <div id="fade_div2" class="overlay2" style="display:none";></div>

        <div id="main_div" class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 text-center">
                        <div class="card border-danger shadow-sm rounded-4">
                            <div class="card-header bg-danger text-white py-3">
                                <h4 class="mb-0 fw-bold">Μη Υποστηριζόμενη Συσκευή</h4>
                            </div>
                            <div class="card-body p-5">
                                <h1 class="display-1 text-danger mb-4">📱❌</h1>
                                <h5 class="card-title mb-3">Η πρόσβαση από κινητά τηλέφωνα δεν επιτρέπεται.</h5>
                                <p class="card-text text-muted">
                                    Για λόγους ασφαλείας και σωστής εμφάνισης των φορμών, παρακαλούμε συνδεθείτε στην πλατφόρμα <strong>e-Αίτηση</strong> χρησιμοποιώντας ηλεκτρονικό υπολογιστή (Desktop ή Laptop).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
    </body>
    ';
     die;
    }
*/

include(__DIR__.'/functions.php'); // main functions 

// Load the settings from the central config file
require_once 'casconfig.php';
// Load the CAS lib
require_once $phpcas_path . 'CAS.php';

$execute_login=0;

if(isset($_GET['db_application_id']))
{
    $execute_login=1;
    $secretary['usertype']='secretary';
    $secretary['db_application_id']=$_GET['db_application_id'];
    $secretary['AFM']=$_GET['AFM'];
    if(subnet_login()==1)
    {
        echo main_page($secretary);
    }
    else
    {
        echo 'ACCESS Denied'.subnet_login();
    }
    
}

if(isset($_POST['sch_login']))
{
    $execute_login=1;
    if($_POST['sch_login']==1)
    {
        // Enable debugging
        phpCAS::setLogger();
        // Enable verbose error messages. Disable in production!
        phpCAS::setVerbose(true);

        // Initialize phpCAS
        //phpCAS::client(CAS_VERSION_3_0, $cas_host, $cas_port, $cas_context, $client_service_name);

    if (!preg_match("/^https?:\/\//", $client_service_name)) {
        die("Λάθος στις ρυθμίσεις του CAS");
    }
    //else{$client_service_name='https://my-site.sch.gr';}


    phpCAS::client(CAS_VERSION_3_0, $cas_host, $cas_port, $cas_context, $client_service_name);

        // For production use set the CA certificate that is the issuer of the cert
        // on the CAS server and uncomment the line below
        // phpCAS::setCasServerCACert($cas_server_ca_cert_path);

        // For quick testing you can disable SSL validation of the CAS server.
        // THIS SETTING IS NOT RECOMMENDED FOR PRODUCTION.
        // VALIDATING THE CAS SERVER IS CRUCIAL TO THE SECURITY OF THE CAS PROTOCOL!
        phpCAS::setNoCasServerValidation();

        // force CAS authentication
        phpCAS::forceAuthentication();

        // logout if desired
        if(isset($_POST['logout'])){phpCAS::logout();}
        
    }
}

if(session_id() == '' || !isset($_SESSION) || session_status() === PHP_SESSION_NONE)
{$session_exists=0;}
else{$session_exists=1;}

if((isset($_REQUEST['ticket']))&&($session_exists==0))
{
    session_start();
    $_SESSION['cas_1rst_logon']=time();
    session_write_close();
    $execute_login=1;
    $casGet = 'https://'.$cas_host.'/serviceValidate?ticket='.$_REQUEST['ticket'].'&service='. urlencode($client_service_name.$_SERVER['PHP_SELF']);
    $response = file_get_contents($casGet);

    // write xml response for debug reasons
    $debug_cas_xml_file='./sch_feedback/cas_credentials_'.date("Ym").'.xml';
    $current=file_get_contents($debug_cas_xml_file);
    file_put_contents($debug_cas_xml_file,$current.$response);
    
    // deprecate the following
    //$myfile = fopen("./uploads/cas_credentials.xml", "w") or die("Unable to open file!");
    //fwrite($myfile,$response);
    //fclose($myfile);


    $write_file=$response;
    $response_array=preg_split("/\n/", $response);
    $response=str_replace('cas:','',$response);

    $xml = simplexml_load_string($response);
    $json = json_encode($xml);
    $array = json_decode($json,TRUE);

    $usersdatas=array();
    if(isset($array['authenticationSuccess']['attributes']['gsntaxnumber']))
    {
        $usersdatas['AFM']=$array['authenticationSuccess']['attributes']['gsntaxnumber'];
        $usersdatas['AM']=$array['authenticationSuccess']['attributes']['employeenumber'];
    }
    else
    {
        $usersdatas['AFM']=$array['authenticationSuccess']['attributes']['employeenumber'];
        $usersdatas['AM']=0;
    }   
    if(isset($array['authenticationSuccess']['attributes']['gsnBranch']))
    {
        $usersdatas['KLADOS']=$array['authenticationSuccess']['attributes']['gsnBranch'];
    }
    else
    {
        $usersdatas['KLADOS']=$array['authenticationSuccess']['attributes']['title'];  
    }
    
    $usersdatas['FNAME']=$array['authenticationSuccess']['attributes']['givenName'];
    $usersdatas['SNAME']=$array['authenticationSuccess']['attributes']['sn'];
    $usersdatas['FANAME']=$array['authenticationSuccess']['attributes']['gsnfathername'];
    $usersdatas['ORGANIKI']=$array['authenticationSuccess']['attributes']['ou'];
    
    //check if user has organiki in my dide
    $usersdatas['ORGANIKIbyID']=$array['authenticationSuccess']['attributes']['l'];
    $domestic_user=0;
    if(is_array($usersdatas['ORGANIKIbyID']))
    {
        foreach($usersdatas['ORGANIKIbyID'] as $organiki_description)
        {
            if(strpos($organiki_description,'ou=flo,ou=units,dc=sch,dc=gr')!==false){$domestic_user=1;}           
        }
    }
    else
    {
        if(strpos($usersdatas['ORGANIKIbyID'],'ou=flo,ou=units,dc=sch,dc=gr')!==false){$domestic_user=1;}            
    }
    $usersdatas['ORGANIKIbyID']=$domestic_user;


    $usersdatas['JOB_STATUS']=$array['authenticationSuccess']['attributes']['businesscategory'];
    $usersdatas['MOBILE']=$array['authenticationSuccess']['attributes']['mobile'];
    $usersdatas['EMAIL']=$array['authenticationSuccess']['attributes']['mail'];
    
    $usersdatas['casGet']=$casGet = 'https://'.$cas_host.'/serviceValidate?ticket='.$_REQUEST['ticket'].'&service='. urlencode($client_service_name.$_SERVER['PHP_SELF']);
    $usersdatas['myschool']=1;
    
    $usersdatas['ALL_DATA']=$array;


    // Έλεγχος αν το πεδίο $usersdatas['AFM'] είναι όντως ΑΦΜ
    if (!isValidAFM($usersdatas['AFM'])) {
    
    // Το ΑΦΜ ΔΕΝ είναι έγκυρο. 
    // Ετοιμάζουμε την ανακατεύθυνση όπως ζητήθηκε.
    $service_url = "https://aitisi-dide.flo.sch.gr";
    $redirect_url = "https://sso.sch.gr/logout?service=" . urlencode($service_url);

// Ορίζουμε την ανακατεύθυνση να γίνει μετά από 5 δευτερόλεπτα
    header("Refresh: 3; url=" . $redirect_url);
    
    // Εμφανίζουμε το μήνυμα (μπορείτε να βάλετε κανονική HTML εδώ)
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h2 style='color: red;'>Σφάλμα: Τα στοιχεία σύνδεσης δεν αφορούν ΕΚΠΑΙΔΕΥΤΙΚΟ ή ΔΙΟΙΚΗΤΙΚΌ προσωπικό του Υπουργείου Παιδείας!</h2>";
    echo "<p>Παρακαλώ περιμένετε, θα μεταφερθείτε στην σελίδα αποσύνδεσης σε 3 δευτερόλεπτα...</p>";
    
    // Καλό είναι να δίνουμε και ένα χειροκίνητο link σε περίπτωση που ο browser μπλοκάρει το αυτόματο redirect
    echo "<p>Αν δεν μεταφερθείτε αυτόματα, κάντε <a href='" . htmlspecialchars($redirect_url) . "'>κλικ εδώ</a>.</p>";
    echo "</div>";
    
    // Σταματάμε την εκτέλεση του κώδικα ώστε να μην προχωρήσει η σελίδα
    exit();

}

    echo main_page('','',$usersdatas);

    /*
    echo '

        <html>
        <head>
            <title>phpCAS Response</title>
        </head>
        <body>
            <h1>Successfull Authentication!</h1>
            <br><hr>
            '.show_array($usersdatas).'
            <br>
            <p><a href="https://sso-01-test.sch.gr/logout?service=aitisi-dide.flo.sch.gr">Logout</a></p>
        </body>
        </html>
        ';
        */
}

if($execute_login==0)
{
    session_start();
    
    //$all_sesionsdata=$_SESSION;
    //$all_sesionsdata = json_decode(json_encode($_SESSION), true);
    //unset($_SESSION['sch_datas']);
    session_destroy();
    //echo main_page().'<br><br><hr>'.show_array($all_sesionsdata).'<hr><br>';
    echo main_page();
}

function isValidAFM($afm) {
    // Αφαίρεση τυχόν κενών από την αρχή και το τέλος
    $afm = trim($afm);

    // Έλεγχος 1: Πρέπει να αποτελείται από ακριβώς 9 αριθμητικά ψηφία
    if (!preg_match('/^[0-9]{9}$/', $afm)) {
        return false;
    }

    // Έλεγχος 2: Δεν μπορεί να είναι όλα τα ψηφία μηδενικά
    if ($afm === '000000000') {
        return false;
    }

    // Έλεγχος 3: Μαθηματικός αλγόριθμος επαλήθευσης (checksum)
    $sum = 0;
    for ($i = 0; $i < 8; $i++) {
        $sum += intval($afm[$i]) * pow(2, 8 - $i);
    }

    // Υπολογισμός του υπολοίπου (modulo 11 και μετά modulo 10)
    $remainder = ($sum % 11) % 10;
    
    // Το τελευταίο (9ο) ψηφίο πρέπει να ισούται με το υπόλοιπο
    $lastDigit = intval($afm[8]);

    return $remainder === $lastDigit;
}
?>
