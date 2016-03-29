<?php
require_once('Net/SSH2.php');

class Config
{
    public static $config;
    public static $mailconfig;

    public static function load()
    {
        if (self::$config == null) {
            $configFile = dirname(__FILE__) . '/../config.json';
            if (!file_exists($configFile)){
                Response::$error = 'no_config_found';
                Response::send();
            }
            self::$config = json_decode(file_get_contents($configFile));
            self::$mailconfig = json_decode(self::remote_load());
        }
    }

    private static function remote_load()
    {
        $output = self::remote_command('getConfig ' . self::$config->mailconfig);
        return $output;
    }

    private static function remote_command($command)
    {
        $ssh = new NET_SSH2(self::$config->ssh_host);

        if (!$ssh->login(self::$config->ssh_user, self::$config->ssh_pass)) {
            Response::$error = 'remote_login_failed';
            Response::send();
        }

        $output = $ssh->exec('sudo ' . Config::$config->api_command . ' ' . $command);

        unset($ssh, $command);
        return $output;
    }

    public function save()
    {
        self::remote_save();
        self::reload();
    }

    private static function remote_save()
    {
        $configString = self::getConfigJson();
        $output = self::remote_command("setConfig '$configString' " . self::$config->mailconfig);
        if ($output != '') {
            Response::$error = 'remote_error';
            Response::$data = $output;
            Response::send();
        }
    }

    private static function getConfigJson()
    {
        return JsonFormatter::format(json_encode(self::$mailconfig, JSON_UNESCAPED_SLASHES));
    }

    public function reload()
    {
        self::remote_reload();
    }

    private static function remote_reload()
    {
        $output = self::remote_command('reloadConfig');
        if (strpos($output, '200 OK') === -1) {
            Response::$error = 'remote_error';
            Response::$data = $output;
            Response::send();
        }
    }
}