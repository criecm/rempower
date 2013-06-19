<?php
// verification de l'authentification et initialisation de la session

if (!array_key_exists("PHP_AUTH_USER",$_SERVER)) {
  echo "Pas d'authentification :(";
  die("Unauthenticated");
} elseif (!isset($_SESSION) || !array_key_exists('login',$_SESSION)) {
  session_start();
  $_SESSION['login']=$_SERVER['PHP_AUTH_USER'];
  if (!array_key_exists("renew",$_SESSION) || !is_array($_SESSION["renew"]))
    $_SESSION["renew"]=array();
  $_SESSION["WILLNEEDTOREFRESH"]=false;
}


