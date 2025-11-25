<?php

session_start();

if(isset($_SESSION['login']))
{

    $session = $_SESSION['login'];

    if($session!=1 || $admins_only)
    {
        echo 0;
        exit();
    }

}else{
    echo 0;
    exit();
}


$type = $_GET['type'];

if($type == 'rsls')
{
    include('pages/rsls.php');
}

if($type == 'acts')
{
    include('pages/acts.php');
}

if($type == 'stbs')
{
    include('pages/stbs.php');
}

if($type == 'trns')
{
    include('pages/trns.php');
}

if($type == 'plns')
{
    include('pages/plns.php');
}


?>
