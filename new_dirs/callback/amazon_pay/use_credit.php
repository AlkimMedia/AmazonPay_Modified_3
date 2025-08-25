<?php

chdir('../../');
require_once 'includes/application_top.php';

$_SESSION['cot_gv'] = !empty($_POST['use_credit']);

session_write_close();
xtc_db_close();
