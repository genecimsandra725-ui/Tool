<?php

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/auth.php';

toolbox_logout();
toolbox_redirect('login.php');
