<?php

namespace Icinga\Module\Ca\Controllers;

use Dompdf\Exception;
use Icinga\Web\Controller;
use Icinga\Web\Notification;
use Icinga\Web\UrlParams;
use Icinga\Application\Config;
use Icinga\Authentication\Auth;

class IndexController extends Controller
{
    /** @var UrlParams */
    protected $params;

    protected $sudo = '/usr/bin/sudo';

    protected $runas = 'nagios';

    protected $icinga2bin = '/usr/sbin/icinga2';
    protected $hideWarnings = false;
    protected $hideRlimit = true;

    public function init()
    {
        $this->sudo = Config::module('ca')->get('config', 'sudo') != null ?
            Config::module('ca')->get('config', 'sudo') :
            $this->sudo;
        $this->runas = Config::module('ca')->get('config', 'runas') != null ?
            Config::module('ca')->get('config', 'runas') :
            $this->runas;
        $this->icinga2bin = Config::module('ca')->get('config', 'icinga2') != null ?
            Config::module('ca')->get('config', 'icinga2') :
            $this->icinga2bin;
        $this->hideWarnings = Config::module('ca')->get('config', 'hide_warnings') != null ?
            Config::module('ca')->get('config', 'hide_warnings') :
            $this->hideWarnings;
        $this->hideRlimit = Config::module('ca')->get('config', 'hide_rlimit') != null ?
            Config::module('ca')->get('config', 'hide_rlimit') :
            $this->hideRlimit;

        $this->command = $this->sudo . " -u " . $this->runas . " " . $this->icinga2bin;
    }

    public function indexAction()
    {
        $auth = Auth::getInstance();
        if ($auth->hasPermission('ca/oper')) {
            $this->view->authz = true;
            $this->getTabs()->add(
                'ca',
                array(
                    'label' => $this->translate('Certificate Authority'),
                    'title' => $this->translate('Certificate Authority'),
                    'url' => $this->getRequest()->getUrl()->without('fingerprint')
                ))->activate('ca');

            if ($this->params->isEmpty()) {
                $this->view->calist = $this->parseIcingaCaList();
            } elseif ($this->params->has('fingerprint')) {
                $this->view->sign = $this->signCertificate($this->params->shift('fingerprint'));
            } else {
                $this->view->authz = false;
                $this->view->authmsg = $this->translate("Invalid fingerprint.");
            }
        } else {
            $this->view->authz = false;
            $this->view->authmsg =
                $this->translate("You do not have the permission to access CA.");
        }
    }

    public function signCertificate($fingerprint)
    {
        $command = $this->command . " ca sign $fingerprint";
        $output = shell_exec($command . " 2>&1");
        $lines = $this->prepareAndFilterOutput($output);
        if (count($lines) ==1 ) {
            if (strpos($lines[0], "Signed certificate for") !== false) {
                Notification::success($lines[0]);
            }

            return $lines[0];
        }else{
            Notification::error(t("Returned signing message should only be one line"));
        }

    }

    public function icinga2Version()
    {
        $command = $this->icinga2bin . " --version";
        $output = shell_exec($command . " 2>&1");
        $lines = $this->prepareAndFilterOutput($output);
        # get first line
        $version = $lines[0];
        # Match version string
        if (preg_match('/r(\d+)\.(\d+)/', $version, $matches)) {
            $ret['major'] = $matches[1];
            $ret['minor'] = $matches[2];
            return $ret;
        } else {
            return;
        }
    }

    protected function prepareAndFilterOutput($output)
    {

        $lines = preg_split('/\n/', $output, -1, PREG_SPLIT_NO_EMPTY);
        $lines = array_values($lines);

        $result = array();
        foreach ($lines as $line) {
            if (strpos($line, "sudo:") !== false) {
                Notification::error(t("Check sudoers file, could not execute icinga2: ") . $this->runas);
            } else if (strpos($line, "(RLIMIT_") !== false) {
                if (!$this->hideRlimit && !$this->hideWarnings) {
                    Notification::warning($line);
                }
            } else if (strpos($line, "warning/Application") !== false) {
                if (!$this->hideWarnings) {
                    Notification::warning($line);
                }
            } else if (strpos($line, "critical/") !== false) {
                Notification::error($line);
            } else {
                array_push($result, $line);
            }

        }
        return $result;
    }

    public function parseIcingaCaList()
    {
        $version = $this->icinga2Version();
        $command = $this->command . " ca list --all --json";
        if (!empty($version) and !empty($version['major']) and !empty($version['minor'])) {
            if ($version['major'] == "2" and ((int)$version['minor']) < 11) {
                Notification::error(t("Unsupported icinga2 version"));
            }
        }
        $output = shell_exec($command . " 2>&1");
        $lines = $this->prepareAndFilterOutput($output);


        $result = array();
        if (count($lines) ==1 ) {
            $line= $lines[0];
            if ($line == "{}") {
                Notification::info(t("Nothing to sign"));
            } else {
                try {
                    $data = json_decode($line);
                    foreach ($data as $name => $request) {
                        array_push($result, array(
                            "fingerprint" => $name,
                            "timestamp" => $request->timestamp,
                            "signed" => isset($request->cert_response) ? "yes" : "no",
                            "subject" => $request->subject,
                        ));

                    }

                } catch (Exception $e) {
                    Notification::error($e->getMessage());
                    throw $e;
                }
            }

        }else{
            Notification::error(t("Returned json should only be one line"));
        }


        return $result;
    }
}
