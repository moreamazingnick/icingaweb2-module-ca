<?php

use Icinga\Application\Config;
use Icinga\Authentication\Auth;

/** @var \Icinga\Application\Modules\Module $this */
$auth = Auth::getInstance();
if ($auth->hasPermission('ca/overview')){
   $this->menuSection('Icinga2 CA')->setIcon('check')
     ->setUrl('ca');
}

$this->providePermission(
    'ca/oper',
    $this->translate('Certificate Authority Operator')
);

$this->provideConfigTab('config', array(
    'title' => 'Configuration',
    'label' => 'Configuration',
    'url' => 'config'
));
