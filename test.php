<?php

require_once 'config.php';
require_once 'LogClass.php';

require_once $magePath;
umask(0);


$storeID = $_GET['storeID'];
if($storeID){
  echo $storeID;
}else {
  echo 'no id';
}
