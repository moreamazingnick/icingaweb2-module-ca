<?php

namespace Icinga\Module\Ca\Forms\Config;

use Icinga\Forms\ConfigForm;

class CaConfigForm extends ConfigForm
{
    public function init()
    {
        $this->setName('form_config_ca');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData)
    {
        $this->addElements([
            [
                'text',
                'config_runas',
                [
                    'required'      => true,
                    'label'         => $this->translate('Run icingacli as user'),
                    'value'         => "nagios",
                    'description' => $this->translate(
                        'use nagios for ubuntu/debian and icinga for redhat/centos'
                    ),
                ]
            ],
            [
                'text',
                'config_sudo',
                [
                    'required'      => true,
                    'label'         => $this->translate('Sudo path'),
                    'value'         => "/usr/bin/sudo"
                ]
            ],
            [
                'text',
                'config_icinga2',
                [
                    'required'      => true,
                    'label'             => $this->translate('icinga2 binary path'),
                    'value'             => "/usr/sbin/icinga2"
                ]
            ],
        ]);
        $this->addElement(
            'checkbox',
            'config_hide_warnings',
            array(
                'required' => false,
                'label' => $this->translate('Hide all Warnings'),
                'description' => $this->translate(
                    'hides lines containing warning/Application'
                ),
                'value' => 0
            )
        );

        $this->addElement(
            'checkbox',
            'config_hide_rlimit',
            array(
                'required' => false,
                'label' => $this->translate('Hide all RLIMIT_'),
                'description' => $this->translate(
                    'hides lines containing RLIMIT_'
                ),
                'value' => 1
            )
        );
    }
}
