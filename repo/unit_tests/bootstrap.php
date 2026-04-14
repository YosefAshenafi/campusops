<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__) . '/backend/');

require ROOT_PATH . 'vendor/autoload.php';

require ROOT_PATH . 'app/model/User.php';
require ROOT_PATH . 'app/model/Role.php';
require ROOT_PATH . 'app/model/Order.php';
require ROOT_PATH . 'app/model/OrderStateHistory.php';
require ROOT_PATH . 'app/model/ActivityGroup.php';
require ROOT_PATH . 'app/model/ActivityVersion.php';
require ROOT_PATH . 'app/model/ActivitySignup.php';
require ROOT_PATH . 'app/model/Violation.php';
require ROOT_PATH . 'app/model/ViolationRule.php';
require ROOT_PATH . 'app/model/SearchIndex.php';