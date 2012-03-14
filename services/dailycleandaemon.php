<?php

define('SHAISHAI', true);
define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

require INSTALLDIR . '/lib/environment.php';
require INSTALLDIR . '/lib/cache.php';
require INSTALLDIR . '/lib/common.php';
require INSTALLDIR . '/lib/router.php';


require_once INSTALLDIR . '/classes/Invitation.php';

Invitation::deleteUselessInvitations();

Search_request::deleteUselessRequest();

Question::closeExpireQuestions();

Deal::closeExpireDeals();


?>