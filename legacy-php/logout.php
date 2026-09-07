<?php

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

toolbox_logout();
toolbox_redirect('index.php');
