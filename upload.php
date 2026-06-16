<?php

//upload.php

session_start();
$usersdatas=$_SESSION['userdatas'];
session_write_close();

//$usersdatas['AFM']="049498005";

if((isset($_FILES))&&(isset($usersdatas['AFM'])))
{
    //foreach($_FILES as $app_file_name=>$file)
    $app_file_name=array_key_last($_FILES);
    $file=$_FILES[$app_file_name];
    move_uploaded_file($file['tmp_name'], 'uploads/'.$usersdatas['AFM'].'_'.$app_file_name);
	echo 'success';
}

?>