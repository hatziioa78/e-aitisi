<?php
include(__DIR__.'/functions.php'); // main functions 

session_start();
$usersdatas=$_SESSION['userdatas'];
if(!isset($_SESSION['usertype']))
{
    session_write_close();
    if(subnet_login()==0)
    {die;}
}
else
{$usertype=$_SESSION['usertype'];session_write_close();}


/*
if(!(isset($_POST['db_filename'])&&(isset($_POST['filename']))))
{die;}

$db_filename="./applications/uploads/".$_POST['db_filename'];
//$filename=$_POST['filename'];
$filename='test.pdf';

header("Content-Type: application/octet-stream"); 
header("Content-Disposition: attachment; filename=\"".$filename."\""); 
readfile ($db_filename);
exit(); */



if(isset($_POST['db_application_id']))
{
    tcpdfit(application2pdf_format($_POST['db_application_id']),$_POST['afm'].'_'.$_POST['db_application_id'].'.pdf');
    logdata("Λήψη αίτησης PDF για ΑΦΜ: ".$_POST['afm'].' με DB_ID '.$_POST['db_application_id']);
}







?>